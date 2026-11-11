// pdfgen.cpp — генератор PDF для АИС «Паспортный стол» (интеграция C++17 + PHP)
// Использование:  pdfgen <out.pdf> [путь/к/шрифту.ttf]
//   stdin — текст документа в UTF-8.
//   TTF-шрифт с кириллицей встраивается в PDF (Type0 / CIDFontType2 /
//   Identity-H + ToUnicode), поэтому текст корректно отображается
//   и извлекается в любом просмотрщике PDF (Linux / РедОС 8.0+).
// Сборка:  g++ -std=c++17 -O2 pdfgen.cpp -o pdfgen
// Шрифт ищется автоматически; явный путь — аргумент командной строки
// или переменная окружения PDFGEN_FONT.
#include <algorithm>
#include <cctype>
#include <cstdint>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <filesystem>
#include <fstream>
#include <iostream>
#include <iterator>
#include <map>
#include <string>
#include <vector>

namespace fs = std::filesystem;

static uint16_t rd16(const uint8_t* p) { return (uint16_t)((p[0] << 8) | p[1]); }
static uint32_t rd32(const uint8_t* p) {
    return ((uint32_t)p[0] << 24) | ((uint32_t)p[1] << 16) | ((uint32_t)p[2] << 8) | p[3];
}

// ---------- UTF-8 -> кодовые точки ----------
static std::vector<uint32_t> utf8_decode(const std::string& s) {
    std::vector<uint32_t> out;
    size_t i = 0;
    while (i < s.size()) {
        uint8_t c = (uint8_t)s[i];
        uint32_t cp = '?'; size_t n = 1;
        bool ok = true;
        if (c < 0x80) { cp = c; }
        else if ((c & 0xE0) == 0xC0) { cp = c & 0x1Fu; n = 2; }
        else if ((c & 0xF0) == 0xE0) { cp = c & 0x0Fu; n = 3; }
        else if ((c & 0xF8) == 0xF0) { cp = c & 0x07u; n = 4; }
        else { ok = false; }
        if (ok && i + n > s.size()) ok = false;
        if (ok) {
            for (size_t k = 1; k < n; ++k) {
                uint8_t cc = (uint8_t)s[i + k];
                if ((cc & 0xC0) != 0x80) { ok = false; break; }
                cp = (cp << 6) | (cc & 0x3Fu);
            }
        }
        if (!ok) { cp = '?'; n = 1; }
        out.push_back(cp);
        i += n;
    }
    return out;
}

// ---------- шрифт TTF ----------
struct Font {
    std::vector<uint8_t> data;
    uint16_t upem = 1000;
    int xMin = 0, yMin = 0, xMax = 0, yMax = 0;
    int asc = 0, desc = 0, cap = 0;
    uint16_t numHMetrics = 0;
    std::vector<uint16_t> adv;
    // cmap: формат 4 (BMP) и формат 12 (полный Unicode)
    struct F4 { uint16_t start, end, delta, ro; uint32_t roPos; };
    std::vector<F4> f4;
    struct F12 { uint32_t start, end, gid; };
    std::vector<F12> f12;
    std::string psName = "EmbeddedFont";
    std::map<uint32_t, uint16_t> cache;

    const uint8_t* tbl(const char* tag, uint32_t& len) const {
        if (data.size() < 12) return nullptr;
        uint16_t n = rd16(data.data() + 4);
        if ((size_t)12 + (size_t)n * 16 > data.size()) return nullptr;
        const uint8_t* rec = data.data() + 12;
        for (uint16_t i = 0; i < n; ++i, rec += 16)
            if (std::memcmp(rec, tag, 4) == 0) {
                uint32_t off = rd32(rec + 8), l = rd32(rec + 12);
                if ((size_t)off + l > data.size()) return nullptr;
                len = l;
                return data.data() + off;
            }
        return nullptr;
    }

    bool load(const fs::path& path) {
        std::ifstream f(path, std::ios::binary);
        if (!f) return false;
        data.assign(std::istreambuf_iterator<char>(f), std::istreambuf_iterator<char>());
        cache.clear(); f4.clear(); f12.clear();
        if (data.size() < 12) return false;
        uint32_t len = 0;
        // head: unitsPerEm, FontBBox
        const uint8_t* p = tbl("head", len);
        if (!p || len < 54) return false;
        upem = rd16(p + 18);
        if (!upem) return false;
        xMin = (int16_t)rd16(p + 36); yMin = (int16_t)rd16(p + 38);
        xMax = (int16_t)rd16(p + 40); yMax = (int16_t)rd16(p + 42);
        // hhea: ascender/descender, число метрических глифов
        p = tbl("hhea", len);
        if (!p || len < 36) return false;
        asc = (int16_t)rd16(p + 4);
        desc = (int16_t)rd16(p + 6);
        numHMetrics = rd16(p + 34);
        if (!numHMetrics) return false;
        // hmtx: ширины глифов
        p = tbl("hmtx", len);
        if (!p || len < (uint32_t)numHMetrics * 4) return false;
        adv.resize(numHMetrics);
        for (uint16_t i = 0; i < numHMetrics; ++i) adv[i] = rd16(p + i * 4);
        // OS/2: CapHeight (таблица версии >= 2)
        p = tbl("OS/2", len);
        if (p && len >= 90 && rd16(p) >= 2) {
            int c = (int16_t)rd16(p + 88);
            if (c) cap = c;
        }
        if (!cap) cap = asc;
        parse_cmap();
        parse_name();
        return !f4.empty() || !f12.empty();
    }

    void parse_cmap() {
        uint32_t len = 0;
        const uint8_t* t = tbl("cmap", len);
        if (!t || len < 4) return;
        uint16_t num = rd16(t + 2);
        const uint8_t* best12 = nullptr;
        const uint8_t* best4 = nullptr;
        for (uint16_t i = 0; i < num; ++i) {
            const uint8_t* rec = t + 4 + (size_t)i * 8;
            if ((size_t)(rec + 8 - t) > len) break;
            uint16_t plat = rd16(rec), enc = rd16(rec + 2);
            uint32_t off = rd32(rec + 4);
            if ((size_t)off + 2 > len) continue;
            const uint8_t* sub = t + off;
            uint16_t fmt = rd16(sub);
            if (fmt == 12 && !best12 && ((plat == 3 && enc == 10) || plat == 0)) best12 = sub;
            if (fmt == 4 && !best4 && ((plat == 3 && enc == 1) || plat == 0)) best4 = sub;
        }
        if (best12) {
            uint32_t ng = rd32(best12 + 12);
            if ((size_t)16 + (size_t)ng * 12 <= len) {
                f12.reserve(ng);
                for (uint32_t g = 0; g < ng; ++g) {
                    const uint8_t* r = best12 + 16 + (size_t)g * 12;
                    f12.push_back({rd32(r), rd32(r + 4), rd32(r + 8)});
                }
            }
        }
        if (best4) {
            uint16_t segX2 = rd16(best4 + 6);
            uint16_t seg = segX2 / 2;
            const uint8_t* ends   = best4 + 14;
            const uint8_t* starts = ends + segX2 + 2;
            const uint8_t* deltas = starts + segX2;
            const uint8_t* ros    = deltas + segX2;
            if ((size_t)(ros + segX2 - best4) <= len) {
                for (uint16_t i = 0; i < seg; ++i) {
                    uint16_t s = rd16(starts + 2 * i);
                    if (s == 0xFFFF) continue;
                    uint16_t e = rd16(ends + 2 * i);
                    if (e < s) continue;
                    f4.push_back({s, e, rd16(deltas + 2 * i), rd16(ros + 2 * i),
                                  (uint32_t)((ros + 2 * i) - data.data())});
                }
            }
        }
    }

    void parse_name() {
        uint32_t len = 0;
        const uint8_t* p = tbl("name", len);
        if (!p || len < 6) return;
        uint16_t count = rd16(p + 2), so = rd16(p + 4);
        for (uint16_t i = 0; i < count; ++i) {
            const uint8_t* r = p + 6 + (size_t)i * 12;
            if ((size_t)(r + 12 - p) > len) break;
            uint16_t plat = rd16(r), nid = rd16(r + 6), l = rd16(r + 8), o = rd16(r + 10);
            if (nid != 6 || (size_t)so + o + l > len) continue;
            std::string nm;
            const uint8_t* s = p + so + o;
            if (plat == 3) { // UTF-16BE -> ASCII
                for (uint16_t k = 0; k + 1 < l; k += 2) {
                    uint16_t ch = rd16(s + k);
                    nm += (ch >= 32 && ch < 127) ? (char)ch : '-';
                }
            } else {
                for (uint16_t k = 0; k < l; ++k)
                    nm += (s[k] >= 32 && s[k] < 127) ? (char)s[k] : '-';
            }
            if (!nm.empty()) {
                std::string cl;
                for (char c : nm)
                    cl += (std::isalnum((unsigned char)c) || c == '-' || c == '_') ? c : '-';
                psName = cl;
                return;
            }
        }
    }

    uint16_t gid(uint32_t cp) {
        auto it = cache.find(cp);
        if (it != cache.end()) return it->second;
        uint16_t g = 0;
        if (!f12.empty()) {
            size_t lo = 0, hi = f12.size();
            while (lo < hi) {
                size_t mid = (lo + hi) / 2;
                if (f12[mid].end < cp) lo = mid + 1;
                else hi = mid;
            }
            if (lo < f12.size() && f12[lo].start <= cp)
                g = (uint16_t)(f12[lo].gid + (cp - f12[lo].start));
        }
        if (!g && !f4.empty()) {
            for (const F4& f : f4) {
                if (cp < f.start) break;
                if (cp > f.end) continue;
                if (f.ro == 0) {
                    g = (uint16_t)(cp + f.delta);
                } else {
                    size_t pos = (size_t)f.roPos + f.ro + 2 * (cp - f.start);
                    if (pos + 2 <= data.size()) {
                        g = rd16(data.data() + pos);
                        if (g) g = (uint16_t)(g + f.delta);
                    }
                }
                break;
            }
        }
        cache[cp] = g;
        return g;
    }

    uint32_t advance(uint16_t g) const {
        if (g < numHMetrics) return adv[g];
        return adv.empty() ? 600 : adv.back();
    }
    int scaled(int v) const { return (int)((int64_t)v * 1000 / upem); }
    double widthOf(const std::vector<uint32_t>& cps, double size) {
        double w = 0;
        for (uint32_t cp : cps) w += advance(gid(cp));
        return w * size / upem;
    }
};

// ---------- перенос длинных строк по словам ----------
static std::vector<std::vector<uint32_t>> wrap(Font& fo, const std::vector<uint32_t>& cps,
                                               double size, double maxW) {
    std::vector<std::vector<uint32_t>> out;
    std::vector<uint32_t> cur;
    double cw = 0;
    auto cwOf = [&](const std::vector<uint32_t>& v) {
        double w = 0;
        for (uint32_t c : v) w += fo.advance(fo.gid(c)) * size / fo.upem;
        return w;
    };
    for (uint32_t cp : cps) {
        double w = fo.advance(fo.gid(cp)) * size / fo.upem;
        if (cw + w > maxW && !cur.empty()) {
            // ищем последний пробел, чтобы разорвать по слову
            size_t sp = cur.size();
            while (sp > 0 && cur[sp - 1] != ' ') --sp;
            size_t cut = (sp >= 4 && sp < cur.size()) ? sp + 1 : cur.size();
            std::vector<uint32_t> head(cur.begin(), cur.begin() + cut);
            while (!head.empty() && head.back() == ' ') head.pop_back();
            out.push_back(head);
            cur.assign(cur.begin() + cut, cur.end());
            cw = cwOf(cur);
        }
        cur.push_back(cp);
        cw += w;
    }
    out.push_back(cur);
    return out;
}

int main(int argc, char** argv) {
    if (argc < 2) {
        std::fprintf(stderr, "usage: pdfgen <out.pdf> [font.ttf]\n");
        return 1;
    }
    // текст документа из stdin (UTF-8)
    std::string text((std::istreambuf_iterator<char>(std::cin)), std::istreambuf_iterator<char>());
    std::vector<std::string> lines;
    std::string cur;
    for (char c : text) {
        if (c == '\n') { lines.push_back(cur); cur.clear(); }
        else if (c != '\r') cur += c;
    }
    lines.push_back(cur);

    // поиск TTF-шрифта с кириллицей:
    // 1) аргумент командной строки, 2) PDFGEN_FONT, 3) типовые пути, 4) обход /usr/share/fonts
    std::vector<fs::path> tries;
    if (argc >= 3) tries.emplace_back(argv[2]);
    if (const char* env = std::getenv("PDFGEN_FONT")) if (*env) tries.emplace_back(env);
    static const char* CAND[] = {
        "/usr/share/fonts/dejavu-sans-fonts/DejaVuSans.ttf",
        "/usr/share/fonts/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/TTF/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/liberation/LiberationSans-Regular.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
        "/usr/share/fonts/pt-sans-fonts/PTS55F.ttf",
        "/usr/share/fonts/google-noto/NotoSans-Regular.ttf",
        "/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf",
        nullptr
    };
    for (int i = 0; CAND[i]; ++i) tries.emplace_back(CAND[i]);

    Font fo;
    fs::path used;
    for (const fs::path& p : tries) {
        Font t;
        if (t.load(p) && t.gid(0x410) && t.gid(0x430)) { fo = std::move(t); used = p; break; }
    }
    if (fo.data.empty()) {
        for (const char* root : {"/usr/share/fonts", "/usr/local/share/fonts"}) {
            if (!fs::exists(root)) continue;
            std::error_code ec;
            fs::recursive_directory_iterator it(root, ec), end;
            for (; it != end && !ec; it.increment(ec)) {
                std::error_code ec2;
                if (!it->is_regular_file(ec2)) continue;
                std::string ext = it->path().extension().string();
                if (ext != ".ttf" && ext != ".TTF") continue;
                Font t;
                if (t.load(it->path()) && t.gid(0x410) && t.gid(0x430)) {
                    fo = std::move(t); used = it->path(); break;
                }
            }
            if (!fo.data.empty()) break;
        }
    }
    if (fo.data.empty()) {
        std::fprintf(stderr,
            "не найден TTF-шрифт с кириллицей; установите пакет шрифтов "
            "(dejavu-sans-fonts) или задайте PDFGEN_FONT=/путь/к/шрифту.ttf\n");
        return 3;
    }

    // нормализация текста и перенос строк (заголовок — 16 пт, текст — 11 пт)
    struct Vis { std::vector<uint32_t> cps; double size; bool title; };
    std::vector<Vis> visuals;
    for (size_t i = 0; i < lines.size(); ++i) {
        std::vector<uint32_t> cps = utf8_decode(lines[i]);
        double size = (i == 0) ? 16.0 : 11.0;
        std::vector<uint32_t> norm;
        norm.reserve(cps.size());
        for (uint32_t cp : cps) {
            if (cp == '\t') cp = ' ';
            if (cp < 32) continue;
            if (cp != ' ' && !fo.gid(cp)) cp = '?'; // нет глифа в шрифте -> '?'
            norm.push_back(cp);
        }
        for (auto& part : wrap(fo, norm, size, 495.0))
            visuals.push_back({std::move(part), size, i == 0});
    }

    // раскладка по страницам (A4, поля: лево 50, низ 45)
    struct L { std::vector<uint32_t> cps; double size; long x, y; };
    struct Pg { std::vector<L> ls; };
    std::vector<Pg> pgs(1);
    double y = 782.0;
    bool wasTitle = false;
    for (auto& v : visuals) {
        double lead = v.title ? 22.0 : 15.0;
        if (!v.title && wasTitle) y -= 10.0; // отступ после заголовка
        if (y - lead < 45.0) { pgs.emplace_back(); y = 782.0; }
        y -= lead;
        pgs.back().ls.push_back({v.cps, v.size, 50, (long)y});
        wasTitle = v.title;
    }

    // содержимое страниц + сборник использованных глифов (для ToUnicode и ширин W)
    std::map<uint16_t, uint32_t> usedG2U;
    std::map<uint16_t, uint32_t> usedW;
    char b[64];
    auto toHex = [&](const std::vector<uint32_t>& cps) {
        std::string h;
        for (uint32_t cp : cps) {
            uint16_t g = fo.gid(cp);
            usedG2U.emplace(g, cp);
            usedW[g] = (uint32_t)fo.scaled((int)fo.advance(g));
            std::snprintf(b, sizeof b, "%04X", (unsigned)g);
            h += b;
        }
        return h;
    };
    std::vector<std::string> contents(pgs.size());
    for (size_t pi = 0; pi < pgs.size(); ++pi) {
        std::ostringstream cs;
        for (auto& l : pgs[pi].ls)
            cs << "BT /F1 " << (long)l.size << " Tf " << l.x << " " << l.y
               << " Td <" << toHex(l.cps) << "> Tj ET\n";
        // колонтитул: «Страница X из Y»
        std::vector<uint32_t> num;
        std::string numStr = "Страница " + std::to_string(pi + 1) +
                             " из " + std::to_string(pgs.size());
        for (uint32_t cp : utf8_decode(numStr)) {
            if (cp < 32) continue;
            if (cp != ' ' && !fo.gid(cp)) cp = '?';
            num.push_back(cp);
        }
        double nw = fo.widthOf(num, 9.0);
        long nx = (long)((595.0 - nw) / 2);
        if (nx < 20) nx = 20;
        cs << "BT /F1 9 Tf " << nx << " 28 Td <" << toHex(num) << "> Tj ET\n";
        contents[pi] = cs.str();
    }

    // ---------- сборка объектов PDF ----------
    const int pagesN = (int)pgs.size();
    const int lastObj = 7 + 2 * pagesN;
    std::vector<size_t> off(lastObj + 1, 0);
    std::string pdf;
    pdf.reserve(fo.data.size() + 65536);
    pdf += "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    auto objH = [&](int n) { off[n] = pdf.size(); pdf += std::to_string(n) + " 0 obj\n"; };
    auto objT = [&]() { pdf += "endobj\n"; };

    objH(1);
    pdf += "<< /Type /Catalog /Pages 2 0 R >>\n";
    objT();

    std::string kids = "[";
    for (int i = 0; i < pagesN; ++i)
        kids += std::string(i ? " " : "") + std::to_string(8 + 2 * i) + " 0 R";
    kids += "]";
    objH(2);
    pdf += "<< /Type /Pages /Kids " + kids + " /Count " + std::to_string(pagesN) + " >>\n";
    objT();

    // шрифт Type0 (2-байтовые глифы Identity-H)
    objH(3);
    pdf += "<< /Type /Font /Subtype /Type0 /BaseFont /" + fo.psName +
           " /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\n";
    objT();

    std::ostringstream wos;
    wos << "/W [";
    for (const auto& kv : usedW) wos << " " << kv.first << " [" << kv.second << "]";
    wos << "]";
    objH(4);
    pdf += "<< /Type /Font /Subtype /CIDFontType2 /BaseFont /" + fo.psName +
           " /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>"
           " /FontDescriptor 5 0 R /DW 600 " + wos.str() + " /CIDToGIDMap /Identity >>\n";
    objT();

    char db[512];
    std::snprintf(db, sizeof db,
        "<< /Type /FontDescriptor /FontName /%s /Flags 32 /FontBBox [%d %d %d %d] "
        "/ItalicAngle 0 /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile2 7 0 R >>",
        fo.psName.c_str(), fo.scaled(fo.xMin), fo.scaled(fo.yMin),
        fo.scaled(fo.xMax), fo.scaled(fo.yMax),
        fo.scaled(fo.asc), fo.scaled(fo.desc), fo.scaled(fo.cap));
    objH(5);
    pdf += std::string(db) + "\n";
    objT();

    // ToUnicode: gid -> символ (корректное выделение/копирование/поиск текста)
    std::ostringstream cm;
    cm << "/CIDInit /ProcSet findresource begin\n"
          "12 dict begin\nbegincmap\n"
          "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
          "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
          "1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
    std::vector<std::pair<uint16_t, uint32_t>> pairs(usedG2U.begin(), usedG2U.end());
    for (size_t i = 0; i < pairs.size(); i += 100) {
        size_t n = std::min<size_t>(100, pairs.size() - i);
        cm << n << " beginbfchar\n";
        for (size_t k = 0; k < n; ++k) {
            uint16_t g = pairs[i + k].first;
            uint32_t cp = pairs[i + k].second;
            // строка назначения — UTF-16BE: 4 цифры (BMP) или суррогатная пара (>= U+10000)
            if (cp <= 0xFFFFu) {
                std::snprintf(b, sizeof b, "<%04X> <%04X>\n",
                              (unsigned)g, (unsigned)cp);
            } else {
                uint32_t v = cp - 0x10000u;
                std::snprintf(b, sizeof b, "<%04X> <%04X%04X>\n", (unsigned)g,
                              (unsigned)(0xD800u + (v >> 10)),
                              (unsigned)(0xDC00u + (v & 0x3FFu)));
            }
            cm << b;
        }
        cm << "endbfchar\n";
    }
    cm << "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";
    std::string cmStr = cm.str();
    objH(6);
    pdf += "<< /Length " + std::to_string(cmStr.size()) + " >>\nstream\n" + cmStr + "endstream\n";
    objT();

    // файл шрифта (полностью, без подсечки — надёжно и просто)
    objH(7);
    pdf += "<< /Length " + std::to_string(fo.data.size()) +
           " /Length1 " + std::to_string(fo.data.size()) + " >>\nstream\n";
    pdf.append(reinterpret_cast<const char*>(fo.data.data()), fo.data.size());
    pdf += "\nendstream\n";
    objT();

    // страницы и их содержимое
    for (int i = 0; i < pagesN; ++i) {
        int pobj = 8 + 2 * i, cobj = 9 + 2 * i;
        objH(pobj);
        pdf += "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
               "/Resources << /Font << /F1 3 0 R >> >> /Contents " +
               std::to_string(cobj) + " 0 R >>\n";
        objT();
        objH(cobj);
        pdf += "<< /Length " + std::to_string(contents[i].size()) + " >>\nstream\n" +
               contents[i] + "endstream\n";
        objT();
    }

    // xref + trailer
    size_t xrefPos = pdf.size();
    int count = lastObj + 1;
    std::ostringstream xr;
    xr << "xref\n0 " << count << "\n0000000000 65535 f \n";
    for (int i = 1; i <= lastObj; ++i) {
        char xb[32];
        std::snprintf(xb, sizeof xb, "%010zu 00000 n \n", off[i]);
        xr << xb;
    }
    xr << "trailer\n<< /Size " << count << " /Root 1 0 R >>\nstartxref\n"
       << xrefPos << "\n%%EOF\n";
    pdf += xr.str();

    std::ofstream outF(argv[1], std::ios::binary);
    if (!outF) {
        std::fprintf(stderr, "не удалось открыть файл для записи: %s\n", argv[1]);
        return 2;
    }
    outF.write(pdf.data(), (std::streamsize)pdf.size());
    outF.close();
    std::printf("OK %zu bytes, шрифт: %s\n", pdf.size(), used.string().c_str());
    return 0;
}
