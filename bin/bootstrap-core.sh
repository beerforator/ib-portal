#!/bin/sh
set -eu
if [ "$#" -lt 1 ] || [ "$#" -gt 2 ]; then
    echo 'Вызов: sh bin/bootstrap-core.sh /путь/к/webroot [локальный-архив.tar.gz]' >&2
    exit 2
fi
dest=$1
mkdir -p "$dest"
if [ -n "$(find "$dest" -mindepth 1 -maxdepth 1 -print -quit)" ]; then
    echo 'Выберите пустой каталог для установки ядра InstantCMS.' >&2
    exit 1
fi
checksum=06afc678c757f34a2070204a06ea321c1ff0849b043590ccd02fb933a4bb77be
release=4a13609c480cccfcbd27dbab424d6bf00ad67375
if [ "$#" -eq 2 ]; then
    archive=$2
else
    archive=$(mktemp)
    trap 'rm -f "$archive"' EXIT
    curl --retry 3 --max-time 180 -fLsS "https://codeload.github.com/instantsoft/icms2/tar.gz/$release" -o "$archive"
fi
echo "$checksum  $archive" | sha256sum -c -
umask 022
tar --no-same-owner --no-same-permissions --strip-components=1 -xzf "$archive" -C "$dest"
echo 'InstantCMS 2.18.2 распакована.'
