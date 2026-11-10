# PiDIS_Filip
# Руководство по установке и запуску — АИС «Паспортный стол»

> Актуально для состава репозитория: PHP-сайт + MariaDB + C++-генератор PDF (`cpp/pdfgen.cpp`),
> сборщик `build.php`, маршрутизатор `router.php`, демо-данные и API `GET /api/notes`.

---

## 1. Что нужно, чтобы сайт заработал

| Компонент | Зачем нужен | Минимум |
|---|---|---|
| PHP | всё веб-приложение | 7.4 или 8.x |
| Расширение PHP `pdo_mysql` | доступ к MariaDB | — |
| Расширение PHP `gd` | капча-пазл (нарезка и выдача фрагментов) | — |
| Расширение PHP `zip` | экспорт в Excel (.xlsx); без него включается CSV-фолбэк | — |
| Расширение PHP `mbstring` | политика паролей, регистронезависимые проверки | — |
| MariaDB / MySQL | база данных `passport_db` | MariaDB 10.4+ |
| Компилятор C++ (`g++`) | сборка генератора PDF `cpp/pdfgen` | C++17 |
| TTF-шрифт с кириллицей | встраивание кириллицы в PDF | DejaVu Sans / Liberation / PT Sans / Noto |
| Доступ в интернет (необязательно) | иконки Font Awesome грузятся с CDN; без сети сайт работает, но без иконок | — |

Внешних PHP-библиотек и Composer **не требуется**: XLSX, PDF, капча и окна сообщений реализованы внутри репозитория.

---

## 2. Зависимости и как их поставить

### 2.1. Проверка текущего окружения

```bash
php -v
php -m | grep -E 'pdo_mysql|gd|zip|mbstring'
mysql --version || mariadb --version
g++ --version
```

### 2.2. Linux (РедОС 8.0+ / Fedora / CentOS)

```bash
sudo dnf install php php-pdo_mysql php-gd php-zip php-mbstring
sudo dnf install mariadb-server mariadb
sudo dnf install gcc-c++
sudo dnf install dejavu-sans-fonts          # шрифт с кириллицей для PDF
sudo systemctl enable --now mariadb
```

### 2.3. Linux (Ubuntu / Debian)

```bash
sudo apt update
sudo apt install php php-mysql php-gd php-zip php-mbstring
sudo apt install mariadb-server
sudo apt install g++
sudo apt install fonts-dejavu
sudo systemctl enable --now mariadb
```

### 2.4. Windows

1. PHP: архив с <https://windows.php.net/download> (TS/NTS x64), распаковать, добавить в `PATH`;
   в `php.ini` включить: `extension=pdo_mysql`, `extension=gd`, `extension=zip` (`mbstring` включено по умолчанию).
2. СУБД: MariaDB MSI-установщик или XAMPP (тогда БД и phpMyAdmin уже есть).
3. Компилятор: MinGW-w64 (MSYS2: `pacman -S mingw-w64-x86_64-gcc`) или Visual Studio Build Tools.
4. Шрифт для PDF есть в системе: `C:\Windows\Fonts\arial.ttf` — укажите его через переменную
   окружения `PDFGEN_FONT` (см. раздел 6).
5. Сборка бинарника вручную: `g++ -std=c++17 -O2 cpp/pdfgen.cpp -o cpp/pdfgen.exe`
   (`build.php` компилирует Linux-вариант `cpp/pdfgen`).

---

## 3. База данных: учётная запись для сайта

Сайт читает доступы из `config.php` (константы `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`;
по умолчанию `admin` / `admin`). Либо создайте такого пользователя в СУБД, либо поправьте `config.php` под своего (например, `root` без пароля в XAMPP).

```sql
CREATE DATABASE IF NOT EXISTS passport_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'admin'@'localhost' IDENTIFIED BY 'admin';
CREATE USER IF NOT EXISTS 'admin'@'127.0.0.1' IDENTIFIED BY 'admin';
GRANT ALL PRIVILEGES ON passport_db.* TO 'admin'@'localhost';
GRANT ALL PRIVILEGES ON passport_db.* TO 'admin'@'127.0.0.1';
FLUSH PRIVILEGES;
```

---

## 4. Запуск сайта: последовательность

### Основной путь (рекомендуется) — всё делает сборщик

```bash
# 1) получить репозиторий
git clone <адрес-репозитория> passport-desk
cd passport-desk

# 2) убедиться, что MariaDB запущена (Linux)
sudo systemctl status mariadb || sudo systemctl start mariadb

# 3) сборка: проверки расширений, компиляция cpp/pdfgen, создание БД,
#    применение schema.sql, демо-пользователи и демо-данные, таблица notes
php build.php

# 4) запуск веб-сервера
php -S localhost:3000 router.php
```

Открыть в браузере: **http://localhost:3000**

Вход (демо-доступы, показываются и на странице входа):

| Логин | Пароль | Роль |
|---|---|---|
| `admin` | `Admin7x2` | администратор |
| `operator` | `Operat0r` | оператор |
| `user` | `User2026q` | пользователь |

Второй шаг входа (2FA): одноразовый 6-значный код в учебной системе выводится на экране
(`verify.php`), срок действия 300 секунд.

### Альтернативный путь — вручную (без `build.php`)

```bash
# 1) схема БД
mysql -u admin -p passport_db < schema.sql

# 2) таблица notes для API (нужна только для /api/notes и коллекции Postman)
mysql -u admin -p < notes_migration.sql

# 3) генератор PDF
g++ -std=c++17 -O2 cpp/pdfgen.cpp -o cpp/pdfgen
chmod +x cpp/pdfgen                      # Linux

# 4) демо-пользователи и демо-данные через веб-установщик
#    открыть http://localhost:3000/install.php  (запустить php -S до этого шага)

# 5) запуск
php -S localhost:3000 router.php
```

После первичной установки **удалите `install.php`** (рекомендация выводится и самим установщиком).

### Маршрутизация

- `php -S localhost:3000 router.php` — нужны «чистые» адреса API: `GET /api/notes`, `GET /notes`.
- Без `router.php` (`php -S localhost:3000 -t .`) сайт тоже работает, но API доступно по прямой ссылке `http://localhost:3000/api/notes.php`.

---

## 5. Проверка после установки (smoke-тест)

1. Вход `admin / Admin7x2` → код с экрана → окно «Вы успешно авторизовались» → главная.
2. Раздел «Транзакции» → «Регистрация гражданина» → запись появилась в «Граждане» и в журнале.
3. «Граждане» → иконка PDF у записи → скачался файл справки (кириллица читается).
   Если PDF не формируется — смотрите раздел 6, пункт про шрифт.
4. «Импорт/Экспорт» → экспорт JSON/Excel — файлы скачиваются.
5. Панель администратора → «Журнал действий», «SQL-запросы» — открываются.
6. API: `curl http://localhost:3000/api/notes` → JSON-массив из 5 тестовых заметок.
   Готовая коллекция проверок: `postman/notes_collection.json` (импорт в Postman, переменная `baseUrl = http://localhost:3000`).

---

## 6. Возможные проблемы и решения

| Симптом | Причина / решение |
|---|---|
| `build.php`: `[ X ] расширение …` | Доустановить пакет расширения (раздел 2), перезапустить терминал |
| Капча не рисуется / пустые квадраты | Не включено `gd`: `php -m \| grep gd` ; включить в `php.ini` (`extension=gd`) |
| Экспорт Excel отдаёт `.csv` | Нет `ZipArchive` (расширение `zip`) — это штатный фолбэк, либо включить `zip` |
| `не удалось скомпилировать pdfgen` | Старый g++ без C++17/filesystem: обновите компилятор; либо соберите вручную командой из раздела 4 |
| PDF: `не найден TTF-шрифт с кириллицей` | Поставить шрифт (`dejavu-sans-fonts`) **или** задать переменную окружения: `PDFGEN_FONT=/путь/к/шрифту.ttf php -S localhost:3000 router.php` (Windows: `PDFGEN_FONT=C:\Windows\Fonts\arial.ttf`) |
| `Access denied for user 'admin'` | Не создан пользователь БД (раздел 3) или иные доступы — поправьте `config.php` |
| Порт 3000 занят | `php -S localhost:3001 router.php` и открыть соответствующий порт |
| Ошибка «База данных настроена не полностью» | Не применён `schema.sql` — выполнить `php build.php` или раздел 4 (альтернативный путь) |
| Нет иконок (квадратики вместо значков) | Нет доступа к CDN Font Awesome; сайт работоспособен, при желании скачайте CSS и шрифты локально и поправьте `<link>` в `layout/header.php`, `layout/admin_header.php`, `index.php`, `register.php`, `verify.php` |
| Windows: PDF не формируется | Бинарник должен называться `cpp/pdfgen.exe` (см. 2.4); путь выбирается в `config.php` автоматически по ОС |

---

## 7. Состав репозитория (ориентир)

| Файлы | Что  делают |
|---|---|
| index.php, verify.php, register.php | вход (логин+пароль+капча), 2FA, саморегистрация |
| dashboard.php, citizens.php, passports.php, applications.php, transactions.php, report.php, document.php, pdf.php, import_export.php, password.php | основные разделы |
| admin_panel.php, admin_users.php, admin_journal.php, admin_sql.php | панель администратора |
| api/notes.php + router.php | API GET /api/notes (Приложение 2) |
| config.php, config/modules.php | настройки БД/PDF и реестр модулей меню |
| components/ (puzzle, messages, password_checklist) | переиспользуемые части страниц |
| mfa_lib.php, captcha_lib.php, captcha.php | 2FA и капча-пазл |
| xlsx_writer.php, doc_data.php | экспорт Excel и шаблоны документов {{таблица.поле}} |
| schema.sql, notes_migration.sql, install.php, build.php | БД и установка |
| cpp/pdfgen.cpp | C++-генератор PDF (кириллица, TTF) |
| postman/notes_collection.json | тесты API |
| layout/ (header, admin_header, footer) | шапки и подвал |
| assets/ (style.css, app.js, captcha/) | стили, JS, картинки капчи |

---

## 8. Короткая шпаргалка (одной строкой)

```bash
sudo dnf install php php-pdo_mysql php-gd php-zip php-mbstring mariadb-server gcc-c++ dejavu-sans-fonts \
&& sudo systemctl enable --now mariadb \
&& php build.php \
&& php -S localhost:3000 router.php
# затем: http://localhost:3000 → admin / Admin7x2 → код с экрана
```
