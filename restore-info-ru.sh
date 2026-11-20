#!/usr/bin/env bash
# Восстановление ib-portal / InstantCMS 2 на автономной Astra Linux.
# Требует предварительно установленных Apache 2.4, PHP 8.2 (mod_php), MariaDB.
# Использование: sudo bash restore-info-ru.sh /путь/ib-portal-transfer.tar.gz
set -Eeuo pipefail
umask 077

SITE_HOST='info.ru'
SITE_URL="http://${SITE_HOST}"
WEBROOT='/var/www/ib-portal'
DATAROOT='/var/lib/ib-portal'
SOURCE_ROOT='/srv/ib-portal-src'
APACHE_SITE='/etc/apache2/sites-available/ib-portal.conf'
PHP_INI='/etc/php/8.2/apache2/conf.d/99-ib-portal.ini'
DB_NAME='ib_portal'
DB_USER='ib_portal'
CREDENTIALS_FILE='/root/ib-portal-info-credentials.txt'
STAGE=''

info() { printf '\n[+] %s\n' "$*"; }
warn() { printf '\n[!] %s\n' "$*" >&2; }
die() { printf '\n[ОШИБКА] %s\n' "$*" >&2; exit 1; }
cleanup() {
    local status=$?
    if [[ -n "$STAGE" && -d "$STAGE" ]]; then
        rm -rf -- "$STAGE"
    fi
    if (( status != 0 )); then
        warn 'Развёртывание прервалось. Возможна частично созданная БД/файлы. Скрипт не удаляет данные автоматически.'
    fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

[[ $EUID -eq 0 ]] || die 'Запустите через sudo.'
[[ $# -eq 1 ]] || die 'Использование: sudo bash restore-info-ru.sh /путь/ib-portal-transfer.tar.gz'
ARCHIVE=$(readlink -f -- "$1")
[[ -f "$ARCHIVE" && -s "$ARCHIVE" ]] || die "Архив не найден или пуст: $1"
[[ -f "${ARCHIVE}.sha256" ]] || die "Нет файла контрольной суммы: ${ARCHIVE}.sha256"

for cmd in tar sha256sum awk grep php mariadb apache2ctl a2enmod a2ensite systemctl od; do
    command -v "$cmd" >/dev/null 2>&1 || die "Не найдена команда '$cmd'. Установите необходимые пакеты."
done
getent passwd www-data >/dev/null || die 'Нет пользователя www-data. Установите Apache.'
[[ -d /etc/apache2/sites-available ]] || die 'Не найдена конфигурация Apache.'
[[ -d /etc/php/8.2/apache2 ]] || die 'Не найдены настройки PHP 8.2 для Apache (libapache2-mod-php8.2).'
[[ -x "$(command -v php)" ]] || die 'Не запускается PHP CLI.'
if [[ "$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" != '8.2' ]]; then
    die 'Для данного проекта ожидается PHP CLI 8.2. Проверьте php -v.'
fi
for module in mysqli mbstring dom gd; do
    php -m | grep -Fxqi "$module" || die "Отсутствует модуль PHP: $module"
done
if ! apache2ctl -M 2>/dev/null | grep -Eq '^[[:space:]]*php_module[[:space:]]'; then
    die 'Apache не загрузил php_module. Установите/включите libapache2-mod-php8.2 (mod_php) до запуска скрипта.'
fi

# Не перезаписываем ранее установленный портал или БД.
[[ ! -e "$WEBROOT" && ! -L "$WEBROOT" ]] || die "$WEBROOT уже существует. Сделайте отдельную резервную копию и освободите путь."
[[ ! -e "$DATAROOT" && ! -L "$DATAROOT" ]] || die "$DATAROOT уже существует. Сделайте отдельную резервную копию и освободите путь."
[[ ! -e "$APACHE_SITE" && ! -L "$APACHE_SITE" ]] || die "$APACHE_SITE уже существует. Скрипт не перезаписывает конфигурацию сайта."
[[ ! -e "$CREDENTIALS_FILE" ]] || die "$CREDENTIALS_FILE уже существует. Скрипт не перезаписывает секреты."

info 'Проверяю SHA-256 архива'
EXPECTED_SHA=$(awk 'NR==1 {print $1}' "${ARCHIVE}.sha256")
[[ "$EXPECTED_SHA" =~ ^[0-9a-fA-F]{64}$ ]] || die 'Файл SHA-256 имеет неверный формат.'
ACTUAL_SHA=$(sha256sum "$ARCHIVE" | awk '{print $1}')
[[ "${EXPECTED_SHA,,}" == "$ACTUAL_SHA" ]] || die 'SHA-256 не совпадает! Файлы могли повредиться при переносе.'
info "Контрольная сумма совпала: $ACTUAL_SHA"

# Проверка имён файлов в архиве. Ожидаем формат, созданный предыдущим сценарием экспорта.
info 'Проверяю структуру архива'
while IFS= read -r member; do
    path=${member#./}
    case "$path" in
        var/www/ib-portal|var/www/ib-portal/*|\
        var/lib/ib-portal|var/lib/ib-portal/*|\
        srv/ib-portal-src|srv/ib-portal-src/*|\
        etc/apache2/sites-available/ib-portal.conf|\
        etc/php/8.2/apache2/conf.d/99-ib-portal.ini|\
        ib_portal.sql|apache2.conf.reference) ;;
        *) die "Неожиданный путь в архиве: $member" ;;
    esac
    case "/$path/" in
        *'/../'*|*'/./'* ) die "Недопустимый путь в архиве: $member" ;;
    esac
done < <(tar -tzf "$ARCHIVE")

STAGE=$(mktemp -d /var/tmp/ib-portal-restore.XXXXXXXX)
tar --no-same-owner --same-permissions -xzpf "$ARCHIVE" -C "$STAGE"
for path in \
    'var/www/ib-portal/index.php' \
    'var/www/ib-portal/system/config/config.php' \
    'ib_portal.sql'; do
    [[ -f "$STAGE/$path" && ! -L "$STAGE/$path" && -s "$STAGE/$path" ]] || die "Не найден обязательный файл в архиве: $path"
done
[[ -d "$STAGE/var/lib/ib-portal" && ! -L "$STAGE/var/lib/ib-portal" ]] || die 'В архиве отсутствует каталог var/lib/ib-portal.'
if [[ -d "$STAGE/srv/ib-portal-src" && -e "$SOURCE_ROOT" ]]; then
    die "$SOURCE_ROOT уже существует; скрипт не перезаписывает исходный код."
fi

# Не разворачиваем пустой дамп или архив вместо действующей БД.
grep -Eq '^(CREATE TABLE|INSERT INTO|-- MariaDB dump|-- MySQL dump)' "$STAGE/ib_portal.sql" || \
    die 'SQL-файл не похож на дамп MariaDB. Проверьте архив.'
if grep -Eqi 'ibbb-portal\.ru|ib-portal\.example\.internal' "$STAGE/ib_portal.sql"; then
    warn 'В SQL-дампе найдены ссылки на старый домен. Скрипт не переписывает их, чтобы не повредить данные CMS: проверьте контент после переноса.'
fi

info 'Проверяю доступ к MariaDB и отсутствие существующей БД'
systemctl start mariadb
mariadb -Nse 'SELECT 1' >/dev/null || die 'Не удаётся подключиться к MariaDB от root через локальный сокет.'
DB_EXISTS=$(mariadb -Nse "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}'")
[[ "$DB_EXISTS" == '0' ]] || die "База $DB_NAME уже существует. Скрипт отказывается её перезаписывать."
USER_EXISTS=$(mariadb -Nse "SELECT COUNT(*) FROM mysql.user WHERE User='${DB_USER}' AND Host='localhost'")
[[ "$USER_EXISTS" == '0' ]] || die "Пользователь MariaDB '${DB_USER}'@'localhost' уже существует. Скрипт не меняет его пароль."

# Непредсказуемый пароль содержит только 0-9a-f, пригоден для SQL и PHP.
DB_PASSWORD=$(od -An -tx1 -N24 /dev/urandom | tr -d '[:space:]')
[[ "$DB_PASSWORD" =~ ^[0-9a-f]{48}$ ]] || die 'Не удалось сгенерировать пароль БД.'

info 'Создаю базу, пользователя и импортирую SQL-дамп'
mariadb <<SQL
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
SQL
# Сохраняем пароль сразу после создания пользователя: даже при сбое импорта он не потеряется.
( umask 077; cat > "$CREDENTIALS_FILE" <<EOF
Портал: ${SITE_URL}
База данных: ${DB_NAME}
Пользователь БД: ${DB_USER}
Пароль БД: ${DB_PASSWORD}
Конфигурация CMS: ${WEBROOT}/system/config/config.php
EOF
)
chmod 0600 "$CREDENTIALS_FILE"
mariadb "$DB_NAME" < "$STAGE/ib_portal.sql"
TABLE_COUNT=$(mariadb -Nse "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}'")
[[ "$TABLE_COUNT" =~ ^[0-9]+$ && "$TABLE_COUNT" -gt 0 ]] || die 'Импорт завершился, но таблицы в БД отсутствуют.'
info "Импортировано таблиц: $TABLE_COUNT"

info 'Размещаю файлы сайта и данных'
install -d -m 0755 /var/www /var/lib /srv
mv -- "$STAGE/var/www/ib-portal" "$WEBROOT"
mv -- "$STAGE/var/lib/ib-portal" "$DATAROOT"
if [[ -d "$STAGE/srv/ib-portal-src" ]]; then
    [[ ! -e "$SOURCE_ROOT" ]] || die "$SOURCE_ROOT уже существует; сохранённый исходный код не перезаписан."
    mv -- "$STAGE/srv/ib-portal-src" "$SOURCE_ROOT"
fi

# Конфигурация InstantCMS — текст PHP с ключами массива. Меняем значения,
# не исполняя код из архива и не затрагивая SQL-содержимое.
info "Меняю адрес CMS на $SITE_URL и реквизиты БД"
CFG_PATH="$WEBROOT/system/config/config.php" DB_PASSWORD="$DB_PASSWORD" SITE_URL="$SITE_URL" php <<'PHP'
<?php
$path = getenv('CFG_PATH');
$source = file_get_contents($path);
if ($source === false) {
    fwrite(STDERR, "Не удалось прочитать config.php\n");
    exit(1);
}
$required = [
    'host' => getenv('SITE_URL'),
    'upload_host' => getenv('SITE_URL') . '/upload',
    'db_host' => 'localhost',
    'db_base' => 'ib_portal',
    'db_user' => 'ib_portal',
    'db_pass' => getenv('DB_PASSWORD'),
];
$optional = ['session_save_path' => '/var/lib/ib-portal/.sessions'];
foreach ($required + $optional as $key => $value) {
    // Ожидаемый формат InstantCMS: 'ключ' => 'значение', (кавычки могут быть двойными).
    $pattern = '~([\'\"]' . preg_quote($key, '~') . '[\'\"]\s*=>\s*)([\'\"])(?:\\\\.|(?!\2)[^\\\\])*\2~s';
    $count = 0;
    $replacement = var_export($value, true);
    $updated = preg_replace_callback($pattern, static fn($m) => $m[1] . $replacement, $source, -1, $count);
    if ($updated === null || $count > 1 || (!isset($optional[$key]) && $count !== 1)) {
        fwrite(STDERR, "Не удалось однозначно заменить ключ {$key} в config.php (совпадений: {$count}).\n");
        exit(1);
    }
    if ($count === 0) {
        fwrite(STDERR, "Предупреждение: необязательный ключ {$key} отсутствует.\n");
    }
    $source = $updated;
}
if (file_put_contents($path, $source, LOCK_EX) === false) {
    fwrite(STDERR, "Не удалось записать config.php\n");
    exit(1);
}
PHP
php -l "$WEBROOT/system/config/config.php" >/dev/null || die 'После изменений PHP-конфигурация не проходит синтаксическую проверку.'

info 'Выставляю права доступа'
chown -R root:root "$WEBROOT"
chmod 0755 "$WEBROOT"
for dir in "$WEBROOT/cache" "$WEBROOT/upload" "$WEBROOT/system/config"; do
    [[ -d "$dir" && ! -L "$dir" ]] || die "Не найден обязательный каталог: $dir"
    chown -R www-data:www-data "$dir"
done
chown -R www-data:www-data "$DATAROOT"
chmod 0750 "$DATAROOT"
install -d -o www-data -g www-data -m 0700 "$DATAROOT/.sessions"
chown www-data:www-data "$WEBROOT/system/config/config.php"
chmod 0600 "$WEBROOT/system/config/config.php"

info 'Настраиваю Apache для HTTP на порту 80 (без SSL)'
cat > "$APACHE_SITE" <<'APACHE'
<VirtualHost *:80>
    ServerName info.ru
    DocumentRoot /var/www/ib-portal

    <Directory /var/www/ib-portal>
        AllowOverride All
        Options -Indexes +FollowSymLinks
        Require all granted
    </Directory>

    <Directory /var/www/ib-portal/upload>
        <FilesMatch "\.(?i:php[0-9]*|phtml|phar)$">
            Require all denied
        </FilesMatch>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/ib-portal-error.log
    CustomLog ${APACHE_LOG_DIR}/ib-portal-access.log combined
</VirtualHost>
APACHE
chmod 0644 "$APACHE_SITE"

# Если в архиве был настроечный ini-файл, используем его; иначе создаём стандартный.
if [[ -f "$STAGE/etc/php/8.2/apache2/conf.d/99-ib-portal.ini" ]]; then
    if [[ ! -e "$PHP_INI" ]]; then
        cp "$STAGE/etc/php/8.2/apache2/conf.d/99-ib-portal.ini" "$PHP_INI"
    else
        warn "$PHP_INI уже существует. Сохраняю настройку целевой машины."
    fi
else
    if [[ ! -e "$PHP_INI" ]]; then
        printf 'upload_max_filesize=32M\npost_max_size=36M\n' > "$PHP_INI"
    fi
fi
chmod 0644 "$PHP_INI"
a2enmod rewrite headers >/dev/null
a2ensite ib-portal >/dev/null
apache2ctl configtest || die 'Apache сообщил об ошибке в конфигурации.'
systemctl enable --now mariadb apache2 >/dev/null
systemctl restart apache2

# Проверяем соединение PHP с MariaDB, не раскрывая пароль в аргументах процессов.
DB_PASSWORD="$DB_PASSWORD" php -r '
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $db = new mysqli("localhost", "ib_portal", getenv("DB_PASSWORD"), "ib_portal");
  echo "PHP -> MariaDB: OK\n";
  $db->close();
' || die 'Проверка соединения PHP с MariaDB не пройдена.'

info 'Проверка HTTP через 127.0.0.1 с заголовком Host: info.ru'
if command -v curl >/dev/null 2>&1; then
    HTTP_CODE=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 \
        -H "Host: ${SITE_HOST}" "http://127.0.0.1/" || true)
    printf 'HTTP-ответ: %s\n' "${HTTP_CODE:-не получен}"
    if [[ "$HTTP_CODE" == '500' || "$HTTP_CODE" == '000' ]]; then
        warn 'HTTP-проверка не пройдена. Посмотрите /var/log/apache2/ib-portal-error.log; на Astra может требоваться разрешённая настройка обязательной аутентификации.'
    fi
else
    warn 'curl отсутствует — автоматический HTTP-тест пропущен.'
fi

printf '\n============================================\n'
printf 'Файлы и база данных восстановлены.\n'
printf 'Адрес: %s\n' "$SITE_URL"
printf 'Вход: %s/auth/login\n' "$SITE_URL"
printf 'Админка компонента: %s/admin/controllers/edit/ibportal\n' "$SITE_URL"
printf 'Секреты БД (только root): %s\n' "$CREDENTIALS_FILE"
printf 'IP сервера: '
hostname -I || true
printf '\nНа клиентских ПК настройте внутренний DNS или hosts:\n'
printf '<IP_ВМ> %s\n' "$SITE_HOST"
printf '\nНЕ запускайте повторно /install/ и НЕ выполняйте импорт демоданных.\n'
printf 'Проверьте старые абсолютные ссылки в базе/контенте и cron-задачи вручную.\n'
printf 'Для HTTP без сертификата не передавайте реальные пароли через недоверенные сети.\n'
printf '============================================\n'
if [[ "${HTTP_CODE:-}" == '500' || "${HTTP_CODE:-}" == '000' ]]; then
    die 'Сайт перенесён, но HTTP-проверка обнаружила ошибку. Исправьте её до приёмки.'
fi
