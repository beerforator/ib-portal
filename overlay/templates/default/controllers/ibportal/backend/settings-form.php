<h3><?= $h($labels[$kind]) ?></h3>
<?php if ($kind === 'appearance'): ?>
    <p>Заголовок страницы и подпись пункта меню можно задать отдельно. Пустая подпись скрывает пункт из меню.</p>
<?php endif ?>
<form method="post" class="ib-editor">
    <input type="hidden" name="csrf_token" value="<?= $h($token) ?>">
    <?php foreach ($fields as $key => [$caption]): ?>
        <?php
        $id = 'ib-setting-' . $key;
        $name = 'settings[' . $key . ']';
        $value = $settings[$key];
        ?>
        <label for="<?= $h($id) ?>"><?= $h($caption) ?></label>
        <?php if (IbPortalSettings::isRich($key)): ?>
            <?php if ($key !== 'footer'): ?>
                <div class="ib-inline-image" data-upload="<?= $h($url('content', ['upload' => 1])) ?>" data-editor="<?= $h($id) ?>">
                    <label>Изображение JPEG, PNG или WebP до 8 МБ
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="form-control" data-image-file>
                    </label>
                    <label>Описание изображения
                        <input type="text" class="form-control" maxlength="250" data-image-alt>
                    </label>
                    <button type="button" class="btn btn-outline-primary" data-image-insert>Вставить в текст</button>
                    <span role="status" data-image-status></span>
                </div>
            <?php endif ?>
            <?= html_wysiwyg($name, $value, 'tinymce', [
                'id' => $id, 'toolbar' => 'undo redo | blocks | bold italic | link | bullist numlist | table | removeformat',
                'convert_urls' => false, 'paste_data_images' => false,
                'quickbars_insert_toolbar' => '', 'quickbars_selection_toolbar' => '',
            ]) ?>
            <?php if ($key === 'footer'): ?><small><code>[year]</code> подставляет текущий год.</small><?php endif ?>
        <?php else: ?>
            <input type="text" class="form-control" id="<?= $h($id) ?>" name="<?= $h($name) ?>"
                   value="<?= $h($value) ?>" maxlength="<?= $key === 'home_intro' ? 600 : 160 ?>"
                   <?= str_starts_with($key, 'title_') || in_array($key, ['site_name', 'memo_title', 'crypto_docs_title'], true) ? 'required' : '' ?>>
        <?php endif ?>
    <?php endforeach ?>
    <div><button class="btn btn-primary" type="submit">Сохранить</button></div>
</form>
