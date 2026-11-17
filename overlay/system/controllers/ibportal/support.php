<?php

require_once __DIR__ . '/store.php';
require_once __DIR__ . '/settings.php';

final class IbPortalSupport
{
    public static function store(): IbPortalStore
    {
        $config = cmsConfig::getInstance();
        $keys = ['db_host', 'db_port', 'db_base', 'db_user', 'db_pass', 'db_prefix'];
        $options = [];
        foreach ($keys as $key) {
            $options[$key] = $config->$key ?? null;
        }
        return new IbPortalStore($options);
    }

    public static function h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url(string $action = '', int $id = 0): string
    {
        return $action === '' ? href_to('') : href_to('ibportal', $action, $id ? [$id] : []);
    }

    public static function assets(): string
    {
        return rtrim(cmsConfig::get('root'), '/') . '/templates/ibportal/assets/';
    }

    public static function asset(string $filename): string
    {
        $path = dirname(__DIR__, 3) . '/templates/ibportal/assets/' . $filename;
        return self::assets() . $filename . '?v=' . filemtime($path);
    }

    public static function countLabel(int $count, array $labels): string
    {
        if ($count % 100 >= 11 && $count % 100 <= 14) {
            return $labels[2];
        }
        return match ($count % 10) {1 => $labels[0], 2, 3, 4 => $labels[1], default => $labels[2]};
    }

    public static function date(string $value, string $format = 'd.m.Y'): string
    {
        $zone = new DateTimeZone('Europe/Moscow');
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone($zone)->format($format);
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' МБ' : max(1, round($bytes / 1024)) . ' КБ';
    }

    public static function storage(): string
    {
        return '/var/lib/ib-portal';
    }

    public static function upload(string $field, bool $image, bool $contentImage = false): ?array
    {
        $file = $_FILES[$field] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) ||
            $file['size'] === 0 || $file['size'] > ($image ? 8 : 32) * 1024 * 1024) {
            throw new InvalidArgumentException('Не удалось загрузить файл. Проверьте размер файла.');
        }

        $name = trim(basename(str_replace('\\', '/', $file['name'])));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $types = $image ? [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
        ] : [
            'pdf' => 'application/pdf', 'txt' => 'text/plain',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
        ];
        $expected = $types[$extension] ?? null;
        $office = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'];
        $valid = $expected && $expected === $mime;
        if ($expected && !$image && isset($office[$extension]) && ($valid || $mime === 'application/zip')) {
            $archive = new ZipArchive();
            $valid = false;
            if ($archive->open($file['tmp_name'], ZipArchive::RDONLY) === true) {
                $valid = $archive->locateName($office[$extension]) !== false;
                $archive->close();
            }
        }
        if (!$valid || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Формат файла не поддерживается или имя слишком длинное.');
        }

        $key = bin2hex(random_bytes(20)) . '.' . $extension;
        $directory = self::storage() . ($contentImage ? '/content' : '');
        if (!is_dir($directory) && !mkdir($directory, 0750) && !is_dir($directory)) {
            throw new RuntimeException('Каталог файлов недоступен для записи.');
        }
        $path = $directory . '/' . $key;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new RuntimeException('Каталог файлов недоступен для записи.');
        }
        chmod($path, 0640);
        return ['key' => $key, 'name' => $name, 'mime' => $expected, 'size' => (int) $file['size']];
    }

    public static function remove(?string $key): void
    {
        if ($key && preg_match('/^[a-f0-9]{40}\.[a-z0-9]{2,5}$/D', $key)) {
            $path = self::storage() . '/' . $key;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public static function stream(string $key, string $name, string $mime, bool $inline, bool $contentImage = false): void
    {
        if (!preg_match('/^[a-f0-9]{40}\.[a-z0-9]{2,5}$/D', $key)) {
            cmsCore::error404();
        }
        $path = self::storage() . ($contentImage ? '/content/' : '/') . $key;
        if (!is_readable($path)) {
            cmsCore::error404();
        }
        header("Content-Type: $mime");
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($name));
        readfile($path);
        exit;
    }

    public static function imageMime(string $key): ?string
    {
        if (!preg_match('/^[a-f0-9]{40}\.(jpe?g|png|webp)$/D', $key, $match)) {
            return null;
        }
        return match ($match[1]) {'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg'};
    }

    public static function richText(string $body): string
    {
        // Preserve a small rich text vocabulary and emit links only for supported URL forms.
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $body . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $root = $doc->getElementsByTagName('body')->item(0);
        $html = '';
        foreach ($root?->childNodes ?? [] as $node) {
            $html .= self::cleanNode($node);
        }
        return $html;
    }

    private static function cleanNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            $plain = self::h($node->nodeValue);
            return preg_replace_callback('/\[doc:([1-9][0-9]{0,9})\]/', static function ($match) {
                $url = self::h(self::url('documents') . '?doc=' . (int) $match[1]);
                return '<a href="' . $url . '">Открыть документ</a>';
            }, $plain);
        }
        if (!$node instanceof DOMElement || in_array($node->tagName, ['script', 'style', 'iframe', 'object'], true)) {
            return '';
        }
        $tag = $node->tagName;
        if ($tag === 'img') {
            $url = href_to('ibportal', 'content_image') . '/';
            $src = $node->getAttribute('src');
            if (!str_starts_with($src, $url) || !self::imageMime(substr($src, strlen($url)))) {
                return '';
            }
            return '<img src="' . self::h($src) . '" alt="' . self::h($node->getAttribute('alt')) . '" loading="lazy">';
        }
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= self::cleanNode($child);
        }
        if ($tag === 'br' || $tag === 'hr') {
            return "<$tag>";
        }
        if ($tag === 'a') {
            $href = $node->getAttribute('href');
            if (preg_match('~^(?:https?://[^/\s]+|/(?![/\\\\])[^\\\\\x00-\x20]*)~iu', $href)) {
                return '<a href="' . self::h($href) . '">' . $inner . '</a>';
            }
        }
        if (in_array($tag, ['p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'blockquote', 'pre', 'code', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'caption'], true)) {
            return "<$tag>$inner</$tag>";
        }
        return $inner;
    }
}
