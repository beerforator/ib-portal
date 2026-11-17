<?php

final class IbPortalStore
{
    private const TABLES = [
        'news' => 'ib_news', 'docs' => 'ib_docs', 'sections' => 'ib_sections',
        'tests' => 'ib_tests', 'results' => 'ib_results',
        'settings' => 'ib_settings', 'page_views' => 'ib_page_views',
    ];

    private PDO $pdo;
    private string $prefix;

    public function __construct(array $config)
    {
        $this->prefix = $config['db_prefix'];
        if (!preg_match('/^\w*$/D', $this->prefix)) {
            throw new RuntimeException('Некорректный префикс БД.');
        }
        $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_base'] . ';charset=utf8mb4';
        if (!empty($config['db_port'])) {
            $dsn .= ';port=' . (int) $config['db_port'];
        }
        $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec("SET time_zone = '+00:00'");
    }

    private function table(string $kind): string
    {
        return $this->prefix . self::TABLES[$kind];
    }

    public function all(string $kind, bool $published = false, ?int $limit = null): array
    {
        $order = match ($kind) {
            'sections' => 'title, id',
            'docs', 'tests' => 'position, id',
            default => 'created_at DESC, id DESC',
        };
        $where = $published ? ' WHERE published = 1' : '';
        $max = $limit === null ? '' : ' LIMIT ' . $limit;
        return $this->pdo->query("SELECT * FROM {$this->table($kind)}{$where} ORDER BY {$order}{$max}")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPublished(string $kind): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$this->table($kind)} WHERE published = 1")->fetchColumn();
    }

    public function find(string $kind, int $id, bool $published = false): ?array
    {
        $where = $published ? ' AND published = 1' : '';
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table($kind)} WHERE id = ?{$where}");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(string $kind, array $fields, int $id = 0): int
    {
        $sets = implode(', ', array_map(static fn($key) => "$key = ?", array_keys($fields)));
        $params = array_values($fields);
        if ($id) {
            $params[] = $id;
            $stmt = $this->pdo->prepare("UPDATE {$this->table($kind)} SET {$sets} WHERE id = ?");
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table($kind)} SET {$sets}");
        }
        $stmt->execute($params);
        return $id ?: (int) $this->pdo->lastInsertId();
    }

    public function delete(string $kind, int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table($kind)} WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function settings(): array
    {
        return $this->pdo->query("SELECT setting_key, setting_value FROM {$this->table('settings')}")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function saveSettings(array $settings): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table('settings')} (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $this->pdo->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function pageViews(): array
    {
        return $this->pdo->query("SELECT page_key, views FROM {$this->table('page_views')}")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function countView(string $page, ?int $articleId = null): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table('page_views')} (page_key, views) VALUES (?, 1)
            ON DUPLICATE KEY UPDATE views = views + 1");
        if ($articleId === null) {
            $stmt->execute([$page]);
            return;
        }
        $this->pdo->beginTransaction();
        try {
            $this->addView($articleId);
            $stmt->execute([$page]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function addView(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->table('news')} SET views = views + 1 WHERE id = ? AND published = 1");
        $stmt->execute([$id]);
    }

    public function resultFor(string $attempt): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, score, total FROM {$this->table('results')} WHERE attempt = ?");
        $stmt->execute([$attempt]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function restoreInitialContent(array $tables, Closure $copyFiles): void
    {
        $columns = [
            'sections' => ['id', 'title'],
            'docs' => ['id', 'section_id', 'title', 'file_key', 'file_name', 'mime', 'file_size', 'published', 'position', 'for_crypto', 'created_at'],
            'news' => ['id', 'title', 'body', 'picture', 'published', 'is_new', 'views', 'created_at'],
            'tests' => ['id', 'title', 'description', 'questions', 'revision', 'published', 'position'],
        ];
        $this->pdo->beginTransaction();
        $copied = [];
        try {
            foreach (array_keys($columns + ['settings' => []]) as $kind) {
                if ($this->pdo->query("SELECT 1 FROM {$this->table($kind)} LIMIT 1")->fetchColumn()) {
                    throw new RuntimeException('Импорт рассчитан на пустой портал. Существующее содержимое сохранено.');
                }
            }
            $settings = $tables['settings'] ?? null;
            if (!is_array($settings) || array_filter($settings, 'is_string') !== $settings) {
                throw new RuntimeException('Некорректные настройки в бэкапе.');
            }
            $insert = $this->pdo->prepare("INSERT INTO {$this->table('settings')} (setting_key, setting_value) VALUES (?, ?)");
            foreach ($settings as $key => $value) {
                $insert->execute([$key, $value]);
            }
            foreach ($columns as $kind => $fields) {
                $rows = $tables[$kind] ?? null;
                if (!is_array($rows) || !array_is_list($rows)) {
                    throw new RuntimeException('В бэкапе отсутствует набор ' . $kind . '.');
                }
                $expected = array_fill_keys($fields, true);
                foreach ($rows as $row) {
                    if (!is_array($row) || count($row) !== count($fields) || array_diff_key($expected, $row)) {
                        throw new RuntimeException('Неверные поля в наборе ' . $kind . '.');
                    }
                    $this->save($kind, $row);
                }
            }

            $copied = $copyFiles();
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($copied as $file) {
                unlink($file);
            }
            throw $error;
        }
    }

    public function install(string $schema): void
    {
        foreach (explode(';', $schema) as $statement) {
            if (trim($statement) !== '') {
                $this->pdo->exec(str_replace('__PREFIX__', $this->prefix, $statement));
            }
        }
        $docs = $this->table('docs');
        if (!$this->pdo->query("SHOW COLUMNS FROM {$docs} LIKE 'for_crypto'")->fetch()) {
            $this->pdo->exec("ALTER TABLE {$docs} ADD COLUMN for_crypto TINYINT NOT NULL DEFAULT 0");
        }
        $table = $this->prefix . 'controllers';
        $this->pdo->exec("INSERT INTO {$table}
            (name, title, is_enabled, is_backend, version, author) SELECT 'ibportal', 'Портал ИБ', 1, 1, '1.0.0', 'IB Portal'
            WHERE NOT EXISTS (SELECT 1 FROM {$table} WHERE name = 'ibportal')");
    }
}
