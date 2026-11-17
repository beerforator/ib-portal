# Портал информационной безопасности

Компонент и оформление для InstantCMS 2.18.2. PHP 8.2, Apache и MariaDB. Публичные разделы принимают посетителей локальной сети, а содержимым и результатами управляют администраторы InstantCMS. Для браузера используются локальные CSS и JavaScript, пользовательская часть не требует Node.js.

1. [Развёртывание на чистом Linux](docs/DEPLOYMENT.md) — пакеты, Git, ядро InstantCMS, мастер, проверка SHA-256.
2. [Работа с админкой](docs/ADMIN.md) — регистрация, вход, публикация и редактирование содержимого.

## Состав

- `overlay/` содержит компонент, шаблоны и стили.
- `database/schema.sql` содержит таблицы портала.
- `bin/bootstrap-core.sh` распаковывает закреплённый релиз InstantCMS с проверкой SHA-256.
- `bin/install-portal.php` устанавливает и обновляет компонент.
- `database/initial-content/` содержит логический бэкап настроек и материалов со всеми вложениями. Для импорта после установки выполните `sudo -u www-data php bin/restore-content.php /var/www/ib-portal`.
- `bin/seed-demo.php` создаёт демонстрационные записи с нуля. Выберите импорт готового бэкапа или генерацию демоданных.
- `deploy/apache-vhost.conf` содержит пример настроек Apache.
- `Dockerfile` и `compose.yaml` относятся к локальной разработке.

Лицензия ядра и его уведомления поставляются с исходниками InstantCMS. [Загрузка и сведения о лицензии](https://instantcms.ru/get).

## Локальный стенд в OrbStack

На машине разработки проект находится в Docker-томе `ib-portal-source`, внутри веб-контейнера каталог `/project/repo`. Конфигурация и ядро CMS находятся в `ib-portal-cms`, MariaDB в `ib-portal-db`, загруженные документы и изображения в `ib-portal-private`. Веб-порт опубликован только на `127.0.0.1:8082`.

```sh
docker exec -it ib-portal-web-1 sh -c 'cd /project/repo && exec sh'
```

Для отправки проекта в Git выполняйте команды из `/project/repo` внутри контейнера. БД, загруженные файлы, штатный конфиг CMS и пароль локального администратора хранятся отдельно от Git. Инструкцию для редактора с реквизитами локального стенда можно прочитать в отдельной закрытой копии:

```sh
docker exec ib-portal-web-1 cat /project/operator-docs/ADMIN.md
```

Чтобы возобновить остановленные контейнеры, используйте `docker start ib-portal-db-1 ib-portal-web-1`. Удаление томов уничтожает в том числе единственную копию исходников в Docker. После отправки исходников в Git удалить весь стенд можно командами:

```sh
docker rm -f ib-portal-web-1 ib-portal-db-1
docker network rm ib-portal_default
docker volume rm ib-portal-source ib-portal-cms ib-portal-db ib-portal-private
docker image rm ib-portal-php:8.2
```
