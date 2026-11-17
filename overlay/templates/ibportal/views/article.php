<article class="single-news">
    <a class="back-link" href="<?= IbPortalSupport::h(IbPortalSupport::url('news')) ?>">← Все новости</a>
    <header class="single-news-heading">
        <?php if ($item['is_new']): ?><span class="card-tag">НОВОЕ</span><?php endif ?>
        <h1><?= IbPortalSupport::h($title) ?></h1>
        <div class="single-news-meta">
            <span><?= IbPortalSupport::date($item['created_at']) ?></span>
            <span><?= IbPortalSupport::date($item['created_at'], 'H:i') ?></span>
            <span>Просмотров: <?= (int) $item['views'] ?></span>
        </div>
    </header>
    <?php if ($item['picture']): ?>
        <figure class="single-news-image"><img src="<?= IbPortalSupport::h(IbPortalSupport::url('cover', $item['id'])) ?>" alt=""></figure>
    <?php endif ?>
    <div class="single-news-body glass-panel"><?= IbPortalSupport::richText($item['body']) ?></div>
</article>
