// ================= Окна сообщений (Приложение 1: заголовок + пиктограмма) =================
var messageQueue = [];
var messageOpen = false;

function showMessageWindow(type, text, title) {
    messageQueue.push([type, text, title]);
    if (!messageOpen) nextMessageWindow();
}

function nextMessageWindow() {
    var m = messageQueue.shift();
    if (!m) { messageOpen = false; return; }
    messageOpen = true;

    var presets = {
        error:   ['Ошибка',        'fa-circle-exclamation',   '#E74C3C'],
        warning: ['Предупреждение','fa-triangle-exclamation', '#F39C12'],
        info:    ['Информация',    'fa-circle-info',          '#3498DB'],
        success: ['Успешно',       'fa-circle-check',         '#2ECC71']
    };
    var cfg = presets[m[0]] || presets.info;

    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(30,42,54,.55);display:flex;align-items:center;justify-content:center;z-index:300;padding:16px';
    var box = document.createElement('div');
    box.style.cssText = 'background:#fff;border-radius:12px;max-width:460px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.35);overflow:hidden;font:14px/1.5 Roboto,Arial,sans-serif;color:#333333';

    var head = document.createElement('div');
    head.style.cssText = 'display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid #E1E7EC';
    head.innerHTML = '<i class="fa-solid ' + cfg[1] + '" style="font-size:20px;color:' + cfg[2] + '"></i>' +
                     '<b style="font-size:15px;color:#2C3E50">' + (m[2] || cfg[0]) + '</b>';

    var body = document.createElement('div');
    body.style.cssText = 'padding:16px';
    body.innerHTML = m[1];

    var foot = document.createElement('div');
    foot.style.cssText = 'padding:0 16px 16px;text-align:right';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = 'OK';
    btn.style.cssText = 'background:#3498DB;color:#fff;border:none;border-radius:8px;padding:9px 24px;font:500 14px Roboto,sans-serif;cursor:pointer';
    btn.onmouseover = function () { this.style.background = '#2980B9'; };
    btn.onmouseout  = function () { this.style.background = '#3498DB'; };
    btn.onclick = function () { overlay.remove(); nextMessageWindow(); };
    foot.appendChild(btn);

    box.appendChild(head); box.appendChild(body); box.appendChild(foot);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    btn.focus();
}

// ================= Капча-пазл: сборка изображения из фрагментов =================
function initPuzzle() {
    var board = document.getElementById('puzzleBoard');
    if (!board) return;
    var tray = document.getElementById('puzzleTray');
    var placeInput = document.getElementById('capPlace');
    var selected = null;

    // синхронизация скрытого поля: для каждой ячейки — индекс фрагмента (-1 = пусто)
    function sync() {
        var place = [];
        board.querySelectorAll('.puzzle-slot').forEach(function (slot) {
            var frag = slot.querySelector('.puzzle-frag');
            place.push(frag ? parseInt(frag.getAttribute('data-frag'), 10) : -1);
            slot.classList.toggle('filled', !!frag);
        });
        placeInput.value = JSON.stringify(place);
        // когда все фрагменты на поле, лента сжимается до небольшого поля-«сброса»,
        // но НЕ исчезает: фрагменты можно вернуть перетаскиванием или кликом
        tray.classList.toggle('empty', !tray.querySelector('.puzzle-frag'));
    }
    function setSelected(frag) {
        if (selected) selected.classList.remove('selected');
        selected = (selected === frag) ? null : frag;
        if (selected) selected.classList.add('selected');
    }
    function placeInto(frag, slot) {
        var from = frag.parentElement;
        var existing = slot.querySelector('.puzzle-frag');
        if (existing && existing !== frag) from.appendChild(existing); // обмен при занятой ячейке
        slot.appendChild(frag);
        setSelected(null);
        sync();
    }
    function findFrag(idx) { return document.querySelector('.puzzle-frag[data-frag="' + idx + '"]'); }

    board.querySelectorAll('.puzzle-slot').forEach(function (slot) {
        slot.addEventListener('click', function () { if (selected) placeInto(selected, slot); });
        slot.addEventListener('dragover', function (e) { e.preventDefault(); });
        slot.addEventListener('drop', function (e) {
            e.preventDefault();
            var frag = findFrag(e.dataTransfer.getData('text/plain'));
            if (frag) placeInto(frag, slot);
        });
    });
    document.querySelectorAll('.puzzle-frag').forEach(function (frag) {
        frag.addEventListener('click', function () { setSelected(frag); });
        frag.addEventListener('dragstart', function (e) {
            e.dataTransfer.setData('text/plain', frag.getAttribute('data-frag'));
        });
    });
    // клик по ленте (пустой области или подсказке) возвращает выбранный фрагмент;
    // клик по самому фрагменту в ленте обрабатывается его собственным обработчиком
    tray.addEventListener('click', function (e) {
        if (!selected) return;
        if (e.target.closest('.puzzle-frag')) return;
        tray.appendChild(selected);
        setSelected(null);
        sync();
    });
    tray.addEventListener('dragover', function (e) { e.preventDefault(); });
    tray.addEventListener('drop', function (e) {
        e.preventDefault();
        var frag = findFrag(e.dataTransfer.getData('text/plain'));
        if (frag) { tray.appendChild(frag); setSelected(null); sync(); }
    });
    sync();
}

// ================= Живой чек-лист правил пароля =================
// Работает на любой странице: регистрация, смена пароля, админ-панель.
// Каждый блок .pw-rules с атрибутом data-for привязан к полю пароля по id.
function initPasswordChecklist() {
    function isDigit(ch) { return ch >= '0' && ch <= '9'; }
    function isLetter(ch) { return /[a-zа-яё]/.test(ch); }
    // Запрещены: одинаковые символы подряд (11, aa) и последовательности
    // из 2+ соседних символов (12, ab, 89, 21, ba…) — зеркалит серверную
    // проверку password_has_forbidden_sequence() из config.php
    function hasForbiddenSequence(value) {
        var s = value.toLowerCase();
        for (var i = 0; i + 1 < s.length; i++) {
            var a = s[i], b = s[i + 1];
            if (a === b) return true; // одинаковые символы подряд
            var sameClass = (isDigit(a) && isDigit(b)) || (isLetter(a) && isLetter(b));
            if (sameClass) {
                var diff = b.charCodeAt(0) - a.charCodeAt(0);
                if (diff === 1 || diff === -1) return true;
            }
        }
        return false;
    }
    document.querySelectorAll('.pw-rules[data-for]').forEach(function (box) {
        var input = document.getElementById(box.getAttribute('data-for'));
        if (!input) return;
        function refresh() {
            var v = input.value;
            var results = {
                len: v.length >= 6,
                dig: /\d/.test(v),
                low: /[a-zа-яё]/.test(v),
                up:  /[A-ZА-ЯЁ]/.test(v),
                seq: v.length > 0 && !hasForbiddenSequence(v)
            };
            box.querySelectorAll('[data-rule]').forEach(function (el) {
                var ok = !!results[el.getAttribute('data-rule')];
                el.classList.toggle('ok', ok);
                el.querySelector('i').className = ok ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
            });
        }
        input.addEventListener('input', refresh);
        refresh();
    });
}

// ================= Общее =================
document.addEventListener('DOMContentLoaded', function () {
    // сообщения, переданные с сервера (страницы входа/регистрации и flash-сообщения)
    if (window.authMessages) {
        window.authMessages.forEach(function (m) { showMessageWindow(m[0], m[1]); });
    }
    initPuzzle();
    initPasswordChecklist();
    initMfaCountdown();
    document.querySelectorAll('form.confirm').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!confirm('Подтвердить действие?')) ev.preventDefault();
        });
    });
});

// ================= Одноразовый код 2FA =================
// Обратный отсчёт срока действия кода на странице verify.php (элемент #mfaCountdown).
function initMfaCountdown() {
    var el = document.getElementById('mfaCountdown');
    if (!el) return;
    var left = parseInt(el.getAttribute('data-left'), 10) || 0;
    var timer = setInterval(function () {
        left -= 1;
        if (left <= 0) {
            clearInterval(timer);
            el.textContent = '0 с — истёк';
            return;
        }
        el.textContent = left + ' с';
    }, 1000);
}