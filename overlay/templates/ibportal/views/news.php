<div class="page-heading">
    <h1><?php echo IbPortalSupport::h($title); ?></h1>
    <div class="search-field"><input id="news-search" type="search" placeholder="Найти новость…" autocomplete="off" aria-label="Поиск новостей"></div>
</div>
<div class="news-page-container">
    <?php if ($news): ?>
        <div class="ib-news-grid" id="news-list">
            <?php foreach ($news as $item):
                $excerpt = trim(html_entity_decode(strip_tags($item['body']), ENT_QUOTES, 'UTF-8'));
                ?>
                <a href="<?= IbPortalSupport::h(IbPortalSupport::url('article', $item['id'])) ?>" class="ib-news-card glass-panel" data-search="<?= IbPortalSupport::h($item['title'] . ' ' . $excerpt) ?>">
                    <div class="card-image-wrap">
                        <?php if ($item['picture']): ?>
                            <div class="card-image" style="background-image: url('<?= IbPortalSupport::h(IbPortalSupport::url('cover', $item['id'])) ?>')"></div>
                        <?php else: ?>
                            <div class="card-image fallback-image"><span>ИБ</span></div>
                        <?php endif ?>
                        <?php if ($item['is_new']): ?><div class="card-tag">НОВОЕ</div><?php endif ?>
                    </div>
                    <div class="card-content">
                        <div class="card-meta">
                            <span class="meta-date"><?= IbPortalSupport::date($item['created_at'], 'd / m / Y') ?></span>
                            <span class="meta-separator">&bull;</span>
                            <span class="meta-time"><?= IbPortalSupport::date($item['created_at'], 'H:i') ?></span>
                            <span class="meta-separator">&bull;</span>
                            <span class="meta-views">Просмотров: <?= (int) $item['views'] ?></span>
                        </div>
                        <h3 class="card-title"><?= IbPortalSupport::h($item['title']) ?></h3>
                        <div class="card-desc"><?= IbPortalSupport::h(mb_strimwidth($excerpt, 0, 120, '…')) ?></div>
                    </div>
                </a>
            <?php endforeach ?>
        </div>
        <p class="search-empty" id="news-empty" hidden>По запросу ничего не найдено.</p>
    <?php else: ?>
        <div class="glass-panel featured-news-empty"><p>Новостей пока нет.</p></div>
    <?php endif ?>
</div>
