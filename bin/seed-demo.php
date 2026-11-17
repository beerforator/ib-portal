<?php

if (PHP_SAPI !== 'cli' || $argc !== 2) {
    exit("Вызов: php bin/seed-demo.php /абсолютный/путь/к/InstantCMS\n");
}
require dirname(__DIR__) . '/overlay/system/controllers/ibportal/support.php';
$config = require $argv[1] . '/system/config/config.php';
$store = new IbPortalStore($config);
foreach (['news', 'docs', 'sections', 'tests'] as $kind) {
    if ($store->all($kind, false, 1)) {
        exit("Демоданные можно добавить только на пустой портал.\n");
    }
}
function writeDemo(string $extension, string $content): string
{
    $key = bin2hex(random_bytes(20)) . '.' . $extension;
    $path = IbPortalSupport::storage() . '/' . $key;
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Не удалось записать демофайл.');
    }
    chmod($path, 0640);
    return $key;
}

function pdfDemo(string $title): string
{
    $stream = "BT /F1 22 Tf 60 760 Td ({$title}) Tj 0 -36 Td /F1 12 Tf (Demo document for the information security portal.) Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = sprintf("%010d 00000 n \n", strlen($pdf));
        $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    return $pdf . "xref\n0 6\n0000000000 65535 f \n" . implode('', $offsets) .
        "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

function coverDemo(bool $lock): string
{
    $image = imagecreatetruecolor(960, 540);
    for ($x = 0; $x < 960; $x++) {
        $fraction = $x / 959;
        $r = (int) (17 + 13 * $fraction);
        $g = (int) (37 + ($lock ? 65 : 83) * $fraction);
        $b = (int) (68 + 92 * $fraction);
        imageline($image, $x, 0, $x, 539, imagecolorallocate($image, $r, $g, $b));
    }
    $lines = imagecolorallocate($image, 98, 175, 218);
    for ($span = 220; $span <= 790; $span += 95) {
        imageellipse($image, 480, 270, $span, $span, $lines);
    }
    $dark = imagecolorallocate($image, 24, 54, 86);
    $white = imagecolorallocate($image, 220, 246, 255);
    imagefilledellipse($image, 480, 270, 250, 250, $dark);
    imagesetthickness($image, 9);
    if ($lock) {
        imagearc($image, 480, 252, 70, 110, 180, 360, $white);
        imagerectangle($image, 422, 248, 538, 342, $white);
        imageline($image, 480, 282, 480, 307, $white);
    } else {
        imagepolygon($image, [480,194, 548,219, 537,304, 480,351, 423,304, 412,219], $white);
        imageline($image, 451, 269, 472, 291, $white);
        imageline($image, 472, 291, 513, 246, $white);
    }
    ob_start();
    imagepng($image);
    return ob_get_clean();
}

$basics = $store->save('sections', ['title' => 'Инструкции']);
$policies = $store->save('sections', ['title' => 'Регламенты']);
$documents = [
    ['Памятка о подозрительных письмах', 'email-guide.pdf', $basics, 'Suspicious email checklist', 'application/pdf'],
    ['Инструкция по паролям', 'password-guide.pdf', $basics, 'Password guide', 'application/pdf'],
    ['Куда сообщить об инциденте', 'incident-guide.txt', $basics, 'Demo instructions: contact your information security team.', 'text/plain'],
    ['Правила работы с файлами', 'file-policy.pdf', $policies, 'File handling policy', 'application/pdf'],
];
$docId = null;
foreach ($documents as [$title, $name, $section, $text, $mime]) {
    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $content = $extension === 'pdf' ? pdfDemo($text) : $text . "\n";
    $id = $store->save('docs', [
        'title' => $title, 'section_id' => $section, 'file_key' => writeDemo($extension, $content),
        'file_name' => $name, 'mime' => $mime, 'file_size' => strlen($content), 'published' => 1,
    ]);
    $docId ??= $id;
}
$store->save('news', ['title' => 'Как распознать подозрительное письмо',
    'body' => '<p>Проверьте адрес отправителя и убедитесь, что ожидали письмо. При сомнениях уточните подлинность сообщения по известному контакту.</p><p>[doc:' . $docId . ']</p>',
    'picture' => writeDemo('png', coverDemo(false)), 'published' => 1, 'is_new' => 1]);
$store->save('news', ['title' => 'Проверьте настройки защиты учётной записи',
    'body' => '<p>Проверьте, используете ли вы отдельный пароль для рабочих сервисов и доступна ли дополнительная проверка при входе.</p>',
    'picture' => writeDemo('png', coverDemo(true)), 'published' => 1]);
$store->save('tests', ['title' => 'Основы информационной безопасности',
    'description' => 'Три вопроса о работе с почтой, паролями и документами.',
    'questions' => json_encode([
        ['question' => 'Что делать с неожиданным вложением?', 'answers' => ['Уточнить у отправителя', 'Сразу открыть', 'Переслать коллегам'], 'correct' => 0],
        ['question' => 'Какой подход к рабочим паролям лучше?', 'answers' => ['Один общий пароль', 'Уникальные пароли', 'Одинаковый пароль с разными цифрами'], 'correct' => 1],
        ['question' => 'Кому сообщить о подозрительной активности?', 'answers' => ['Никому', 'Внешнему адресату', 'Отделу ИБ'], 'correct' => 2],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    'revision' => bin2hex(random_bytes(16)), 'published' => 1]);
echo "Добавлены две новости, четыре документа и один тест.\n";
