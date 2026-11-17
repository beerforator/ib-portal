<?php

final class IbPortalSettings
{
    public const PAGES = [
        'home' => ['Главная', ''],
        'news' => ['Новости', 'news'],
        'documents' => ['Документы', 'documents'],
        'polls' => ['Тесты', 'polls'],
        'password' => ['Генератор паролей', 'password'],
        'crypto' => ['Криптошлюз', 'crypto'],
    ];

    public const APPEARANCE = [
        'site_name' => ['Подпись во вкладке браузера', 'Портал ИБ'],
        'site_logo' => ['Подпись в шапке', 'safeNIIP'],
        'home_accent' => ['Вторая строка заголовка главной', 'в ваших руках.'],
        'home_intro' => ['Текст под заголовком главной', 'Актуальные инструкции, новости и короткие проверки знаний для спокойной работы с информацией каждый день.'],
        'home_news_title' => ['Заголовок блока новостей на главной', 'Важное в фокусе'],
        'footer' => ['Содержимое футера', '<p>© [year] Отдел Информационной Безопасности</p><p>Акционерное общество «ГосНИИП»</p>'],
    ];

    public const CONTENT = [
        'memo_title' => ['Заголовок памятки', 'Памятка сотрудника'],
        'memo_body' => ['Текст памятки на главной', '<ul><li>Блокируйте компьютер, когда уходите с рабочего места.</li><li>Уточняйте у отправителя, если получили неожиданное вложение.</li><li>Сообщайте о подозрительных письмах и инцидентах в отдел ИБ.</li></ul>'],
        'crypto_body' => ['Правила работы с криптошлюзом', '<p>По вопросам доступа, настройки и работы с криптошлюзом обращайтесь в отдел информационной безопасности.</p>'],
        'crypto_docs_title' => ['Заголовок блока документов', 'Документы по криптошлюзу'],
    ];

    public static function fields(string $group): array
    {
        if ($group === 'content') {
            return self::CONTENT;
        }
        $fields = self::APPEARANCE;
        foreach (self::PAGES as $key => [$label]) {
            $fields["title_$key"] = ['Заголовок: ' . $label, $key === 'home' ? 'Безопасность' : $label];
            $fields["menu_$key"] = ['Пункт меню: ' . $label, $label];
        }
        return $fields;
    }

    public static function values(array $saved): array
    {
        $defaults = array_map(static fn($field) => $field[1], self::fields('appearance') + self::fields('content'));
        return array_replace($defaults, array_intersect_key($saved, $defaults));
    }

    public static function isRich(string $key): bool
    {
        return in_array($key, ['footer', 'memo_body', 'crypto_body'], true);
    }
}
