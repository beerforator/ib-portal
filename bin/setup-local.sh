#!/bin/sh
set -eu
cd /var/www/html
umask 077
install -d -o www-data -g www-data -m 0750 /var/lib/ib-portal
install -d -o www-data -g www-data -m 0700 /var/lib/ib-portal/.sessions
if test -f system/config/config.php; then
    echo 'InstantCMS уже установлена.'
    exit 0
fi
test -f install/index.php
test -f install/languages/ru/sql/base.sql
password=$(php -r 'echo bin2hex(random_bytes(16));')
printf '%s\n' "$password" > /var/lib/ib-portal/.local-admin-password
jar=$(mktemp)
trap 'rm -f "$jar"' EXIT
origin='http://127.0.0.1:8082'
installer='http://127.0.0.1/install/index.php'
curl -sS -c "$jar" -H 'Host: 127.0.0.1:8082' "$installer?lang=ru" > /dev/null
submit() {
    stage=$1
    shift
    response=$(curl -fsS -b "$jar" -c "$jar" -H 'Host: 127.0.0.1:8082' \
        -H 'X-Requested-With: XMLHttpRequest' --data-urlencode "step=$stage" --data 'submit=1' \
        "$@" "$installer")
    printf '%s' "$response" | php -r '
        $data = json_decode(stream_get_contents(STDIN), true);
        if (!is_array($data) || !array_key_exists("error", $data) || $data["error"]) {
            fwrite(STDERR, ($data["message"] ?? "Установщик вернул неожиданный ответ") . PHP_EOL);
            exit(1);
        }
    '
    printf 'Шаг %s готов\n' "$stage"
}
submit 3 --data-urlencode 'paths[root]=/' --data-urlencode 'paths[upload]=/upload/' \
    --data-urlencode 'paths[cache]=/cache/' --data-urlencode 'paths[session_save_path]=/var/lib/ib-portal/.sessions' \
    --data-urlencode "hosts[root]=$origin" --data-urlencode "hosts[upload]=$origin/upload"
submit 4 --data-urlencode 'sitename=Портал информационной безопасности' --data-urlencode 'hometitle=Портал ИБ' \
    --data 'template=modern&template_admin=admincoreui&is_check_updates=0'
submit 5 --data 'db[host]=db&db[user]=ib_portal&db[pass]=ib-portal-local-db&db[base]=ib_portal&db[prefix]=cms_&db[engine]=InnoDB&db[db_charset]=utf8mb4&db[users_exists]=0'
submit 6 --data-urlencode 'nickname=Администратор' --data-urlencode 'email=admin@ib-portal.test' \
    --data-urlencode "pass1=$password" --data-urlencode "pass2=$password"
submit 7
test -f system/config/config.php
mv install /var/lib/ib-portal/.installer
chmod 0600 system/config/config.php
