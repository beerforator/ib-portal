<?php

require_once dirname(__DIR__) . '/overlay/system/controllers/ibportal/support.php';

if (PHP_SAPI !== 'cli' || $argc !== 2) {
    fwrite(STDERR, "Вызов: php bin/restore-content.php /абсолютный/путь/к/InstantCMS\n");
    exit(2);
}

try {
    $configPath = rtrim($argv[1], '/') . '/system/config/config.php';
    if (!is_readable($configPath)) {
        throw new RuntimeException('Сначала установите InstantCMS и компонент портала.');
    }
    $snapshotPath = dirname(__DIR__) . '/database/initial-content';
    $snapshot = json_decode(file_get_contents($snapshotPath . '/content.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($snapshot['format'] ?? null) !== 1 || !is_array($snapshot['files'] ?? null) ||
        !is_array($snapshot['tables'] ?? null)) {
        throw new RuntimeException('Формат бэкапа не поддерживается.');
    }

    $storage = IbPortalSupport::storage();
    $lock = fopen($storage . '/.initial-content.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Не удалось получить доступ к каталогу файлов или импорт уже запущен.');
    }

    try {
        $store = new IbPortalStore(require $configPath);
        $store->restoreInitialContent($snapshot['tables'], function () use ($snapshot, $snapshotPath, $storage): array {
            $copies = [];
            try {
                foreach ($snapshot['files'] as $path => $hash) {
                    if (!preg_match('~^(?:content/)?[a-f0-9]{40}\.[a-z0-9]{2,5}$~D', $path) ||
                        !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                        throw new RuntimeException('Некорректное имя файла в бэкапе.');
                    }
                    $source = $snapshotPath . '/files/' . $path;
                    $target = $storage . '/' . $path;
                    if (!is_file($source) || hash_file('sha256', $source) !== $hash) {
                        throw new RuntimeException('Файл отсутствует или не прошёл проверку SHA-256: ' . $path);
                    }
                    if (file_exists($target)) {
                        if (!is_file($target) || hash_file('sha256', $target) !== $hash) {
                            throw new RuntimeException('Файл с таким именем уже существует: ' . $path);
                        }
                        continue;
                    }
                    $directory = dirname($target);
                    if (!is_dir($directory) && !mkdir($directory, 0750) && !is_dir($directory)) {
                        throw new RuntimeException('Не удалось создать каталог: ' . $directory);
                    }
                    $temporary = tempnam($directory, '.ib-restore-');
                    if ($temporary === false) {
                        throw new RuntimeException('Не удалось подготовить файл: ' . $path);
                    }
                    try {
                        if (!copy($source, $temporary) || !chmod($temporary, 0640) || !rename($temporary, $target)) {
                            throw new RuntimeException('Не удалось восстановить файл: ' . $path);
                        }
                        $copies[] = $target;
                    } finally {
                        if (is_file($temporary)) {
                            unlink($temporary);
                        }
                    }
                }
            } catch (Throwable $error) {
                foreach ($copies as $file) {
                    unlink($file);
                }
                throw $error;
            }
            return $copies;
        });
    } finally {
        fclose($lock);
    }
    echo "Настройки и материалы портала восстановлены.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
