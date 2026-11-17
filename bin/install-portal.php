<?php

if (PHP_SAPI !== 'cli' || $argc !== 2) {
    fwrite(STDERR, "Вызов: php bin/install-portal.php /абсолютный/путь/к/InstantCMS\n");
    exit(2);
}
$root = realpath($argv[1]);
$configPath = $root . '/system/config/config.php';
if (!$root || !is_file($configPath) || !is_file($root . '/bootstrap.php')) {
    throw new RuntimeException('Сначала установите InstantCMS на пустую базу.');
}
$config = require $configPath;
require dirname(__DIR__) . '/overlay/system/controllers/ibportal/store.php';

$store = new IbPortalStore($config);
$store->install(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));

$overlay = dirname(__DIR__) . '/overlay';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($overlay, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $relative = substr($file->getPathname(), strlen($overlay) + 1);
    $target = $root . '/' . $relative;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
        throw new RuntimeException('Не удалось создать каталог ' . dirname($target));
    }
    if (!copy($file->getPathname(), $target)) {
        throw new RuntimeException('Не удалось установить ' . $relative);
    }
    chmod($target, 0644);
}

$config['ct_autoload'] = 'ibportal';
$config['is_check_updates'] = 0;
$config['time_zone'] = 'Europe/Moscow';
$temp = tempnam(dirname($configPath), '.ibconfig');
try {
    $contents = "<?php\n\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($temp, $contents) !== strlen($contents)) {
        throw new RuntimeException('Не удалось записать конфигурацию.');
    }
    $stat = stat($configPath);
    if (!chown($temp, $stat['uid']) || !chgrp($temp, $stat['gid']) || !chmod($temp, $stat['mode'] & 0777) ||
        !rename($temp, $configPath)) {
        throw new RuntimeException('Не удалось сохранить владельца, права или конфигурацию.');
    }
} finally {
    if (is_file($temp)) {
        unlink($temp);
    }
}
echo "Компонент «Портал ИБ» установлен.\n";
