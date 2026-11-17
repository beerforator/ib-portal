<?php
$h = [IbPortalSupport::class, 'h'];
$this->setPageTitle('Портал ИБ');
$this->addCSS(ltrim(IbPortalSupport::asset('admin.css'), '/'), false);
$this->addJS(ltrim(IbPortalSupport::asset('admin.js'), '/'), '', false);
?>
<div class="ib-admin">
    <h2>Портал ИБ</h2>
    <nav aria-label="Разделы администрирования">
        <?php foreach ($labels as $key => $label): ?>
            <a class="btn <?= $kind === $key ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= $h($url($key)) ?>"
               <?= $kind === $key ? 'aria-current="page"' : '' ?>><?= $h($label) ?></a>
        <?php endforeach ?>
        <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="<?= $h(IbPortalSupport::url()) ?>">Открыть портал ↗</a>
    </nav>

    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= $h($error) ?></p><?php endif ?>
    <?php if ($kind === 'views'): ?>
        <?php require __DIR__ . '/page-views.php'; ?>
    <?php elseif ($kind === 'appearance' || $kind === 'content'): ?>
        <?php require __DIR__ . '/settings-form.php'; ?>
    <?php elseif ($edit && $kind !== 'results'): ?>
        <h3><?= $item ? 'Редактирование' : 'Добавление' ?></h3>
        <form method="post" enctype="multipart/form-data" class="ib-editor">
            <input type="hidden" name="csrf_token" value="<?= $h($token) ?>">
            <label>Название<input class="form-control" name="title" value="<?= $h($form['title'] ?? '') ?>" maxlength="<?= $kind === 'sections' ? 180 : 200 ?>" required></label>
            <?php if ($kind !== 'sections'): ?>
                <label><input type="checkbox" name="published" <?= !empty($form['published']) ? 'checked' : '' ?>> Показывать на портале</label>
            <?php endif ?>
            <?php if ($kind === 'news'): ?>
                <label><input type="checkbox" name="is_new" <?= !empty($form['is_new']) ? 'checked' : '' ?>> Показывать метку «Новое»</label>
                <label for="ib-news-body">Текст новости</label>
                <label>Вставить ссылку на документ
                    <select class="form-control" id="ib-doc-link">
                        <option value="">Выберите опубликованный документ</option>
                        <?php foreach ($publishedDocs as $doc): ?>
                            <option value="<?= (int) $doc['id'] ?>" data-href="<?= $h(IbPortalSupport::url('documents') . '?doc=' . $doc['id']) ?>"><?= $h($doc['title']) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <?= html_wysiwyg('body', $form['body'] ?? '', 'tinymce', [
                    'id' => 'ib-news-body', 'toolbar' => 'undo redo | blocks | bold italic | link | bullist numlist | removeformat',
                    'paste_data_images' => false, 'quickbars_insert_toolbar' => '', 'quickbars_selection_toolbar' => '',
                ]) ?>
                <label>Фото новости (JPEG, PNG или WebP до 8 МБ)<input class="form-control" type="file" name="picture" accept="image/jpeg,image/png,image/webp"></label>
                <?php if (!empty($item['picture'])): ?><label><input type="checkbox" name="remove_picture"> Убрать текущее фото</label><?php endif ?>
            <?php endif ?>
            <?php if ($kind === 'docs'): ?>
                <label><input type="checkbox" name="for_crypto" <?= !empty($form['for_crypto']) ? 'checked' : '' ?>> Для страницы криптошлюза</label>
                <label>Раздел
                    <select class="form-control" name="section_id">
                        <option value="0">Прочие документы</option>
                        <?php foreach ($sections as $sectionId => $name): ?>
                            <option value="<?= (int) $sectionId ?>" <?= (int) ($form['section_id'] ?? 0) === (int) $sectionId ? 'selected' : '' ?>><?= $h($name) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <label>Файл (PDF, TXT, DOCX, XLSX, PPTX, ODT, ODS, ODP, до 32 МБ)
                    <input class="form-control" type="file" name="file" accept=".pdf,.txt,.docx,.xlsx,.pptx,.odt,.ods,.odp" <?= $item ? '' : 'required' ?>>
                </label>
                <?php if ($item): ?><p>Текущий файл: <?= $h($item['file_name']) ?></p><?php endif ?>
            <?php endif ?>
            <?php if ($kind === 'docs' || $kind === 'tests'): ?>
                <label>Порядок показа<input class="form-control" type="number" name="position" value="<?= (int) ($form['position'] ?? 0) ?>"></label>
            <?php endif ?>
            <?php if ($kind === 'tests'):
                $questions = $form['question_rows'] ?? json_decode($form['questions'] ?? '[]', true);
                ?>
                <label>Описание<textarea class="form-control" name="description" rows="4" maxlength="5000"><?= $h($form['description'] ?? '') ?></textarea></label>
                <section class="ib-questions" aria-label="Вопросы">
                    <h4>Вопросы</h4>
                    <p>Добавьте варианты ответа и отметьте один правильный вариант.</p>
                    <div id="ib-questions"></div>
                    <button type="button" class="btn btn-outline-primary" id="ib-add-question">Добавить вопрос</button>
                    <script type="application/json" id="ib-question-data"><?= json_encode(array_values((array) $questions), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                </section>
            <?php endif ?>
            <div><button class="btn btn-primary" type="submit">Сохранить</button>
                <a class="btn btn-outline-secondary" href="<?= $h($url($kind)) ?>">Отмена</a></div>
        </form>
    <?php elseif ($kind === 'results' && $item): ?>
        <h3><?= $h($item['participant']) ?> · <?= $h($item['test_title']) ?></h3>
        <dl>
            <dt>Дата</dt><dd><?= IbPortalSupport::date($item['created_at'], 'd.m.Y H:i:s') ?></dd>
            <dt>Результат</dt><dd><?= (int) $item['score'] ?> / <?= (int) $item['total'] ?></dd>
            <dt>IP</dt><dd><?= $h($item['ip'] ?: '—') ?></dd>
            <dt>Учётная запись InstantCMS</dt><dd><?= $h($item['login'] ?: 'Гость') ?></dd>
            <dt>Сетевая учётная запись</dt><dd><?= $h($item['network_login'] ?: 'Не передана сервером') ?></dd>
            <dt>Браузер</dt><dd><?= $h($item['user_agent'] ?: '—') ?></dd>
        </dl>
        <table class="table"><thead><tr><th>Вопрос</th><th>Выбранный ответ</th><th>Правильный ответ</th><th>Оценка</th></tr></thead>
            <tbody>
                <?php foreach (json_decode($item['details'], true, 512, JSON_THROW_ON_ERROR) as $answer): ?>
                    <tr><td><?= $h($answer['question']) ?></td><td><?= $h($answer['selected']) ?></td>
                        <td><?= $h($answer['expected']) ?></td><td><?= $answer['correct'] ? 'Верно' : 'Ошибка' ?></td></tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <a class="btn btn-outline-secondary" href="<?= $h($url($kind)) ?>">К результатам</a>
    <?php else: ?>
        <h3><?= $h($labels[$kind]) ?></h3>
        <?php if ($kind !== 'results'): ?><p><a class="btn btn-primary" href="<?= $h($url($kind, ['edit' => 1])) ?>">Добавить</a></p><?php endif ?>
        <?php if (!$items): ?><p>Записей пока нет.</p><?php else: ?>
            <table class="table">
                <thead><tr><th>Название</th><th>Сведения</th><th>Действия</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $entry):
                        $info = match ($kind) {
                            'results' => $entry['test_title'] . ' · ' . $entry['score'] . '/' . $entry['total'] . ' · ' . IbPortalSupport::date($entry['created_at'], 'd.m.Y H:i'),
                            'docs' => ($sections[$entry['section_id'] ?? 0] ?? 'Прочие документы') . ' · ' . $entry['file_name'] . ($entry['for_crypto'] ? ' · Криптошлюз' : ''),
                            'news' => 'Просмотров: ' . $entry['views'],
                            default => '',
                        };
                        $entryUrl = $url($kind, ['id' => $entry['id']] + ($kind === 'results' ? [] : ['edit' => 1]));
                        ?>
                        <tr>
                            <td><a href="<?= $h($entryUrl) ?>"><?= $h($entry['title'] ?? $entry['participant']) ?></a></td>
                            <td><?= $h($info) ?><?= array_key_exists('published', $entry) ? ($entry['published'] ? ' · Опубликовано' : ' · Черновик') : '' ?></td>
                            <td><form method="post" action="<?= $h($entryUrl) ?>" data-confirm="Удалить эту запись?">
                                <input type="hidden" name="csrf_token" value="<?= $h($token) ?>">
                                <button name="operation" value="delete" type="submit" class="btn btn-outline-danger btn-sm">Удалить</button>
                            </form></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    <?php endif ?>
</div>
