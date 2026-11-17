<h3>Статистика просмотров</h3>
<p>Всего просмотров портала: <strong><?= number_format(array_sum($pageViews), 0, ',', ' ') ?></strong></p>
<p>Считаются открытия публичных страниц, включая повторные. Счётчики страниц накапливаются с момента установки этой версии. Прежние счётчики статей доступны во вкладке «Новости».</p>

<table class="table">
    <thead><tr><th scope="col">Страница</th><th scope="col">Просмотры</th></tr></thead>
    <tbody>
        <?php foreach (IbPortalSettings::PAGES as $key => [, $action]): ?>
            <tr>
                <th scope="row"><a href="<?= $h(IbPortalSupport::url($action)) ?>"><?= $h($settings['title_' . $key]) ?></a></th>
                <td><?= number_format($pageViews[$key] ?? 0, 0, ',', ' ') ?></td>
            </tr>
        <?php endforeach ?>
        <?php foreach ($articles as $id => $article): ?>
            <tr>
                <th scope="row">
                    <?php if ($article['published']): ?>
                        <a href="<?= $h(IbPortalSupport::url('article', $id)) ?>"><?= $h($article['title']) ?></a>
                    <?php else: ?>
                        <?= $h($article['title']) ?>
                    <?php endif ?>
                </th>
                <td><?= number_format($pageViews['article:' . $id] ?? 0, 0, ',', ' ') ?></td>
            </tr>
        <?php endforeach ?>
        <?php foreach ($pageViews as $key => $count): ?>
            <?php if (str_starts_with($key, 'article:') && !isset($articles[(int) substr($key, 8)])): ?>
                <tr><th scope="row">Удалённая новость №<?= (int) substr($key, 8) ?></th><td><?= number_format($count, 0, ',', ' ') ?></td></tr>
            <?php endif ?>
        <?php endforeach ?>
    </tbody>
</table>
