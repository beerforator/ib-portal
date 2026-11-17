<section class="ib-hero" aria-labelledby="ib-hero-title">
    <div class="ib-hero-copy">
        <h1 id="ib-hero-title"><?= IbPortalSupport::h($title) ?><?php if ($settings['home_accent'] !== ''): ?><br><em><?= IbPortalSupport::h($settings['home_accent']) ?></em><?php endif ?></h1>
        <p><?= IbPortalSupport::h($settings['home_intro']) ?></p>
        <div class="ib-hero-actions">
            <a class="hero-primary" href="<?php echo IbPortalSupport::h(IbPortalSupport::url('documents')); ?>">Открыть документы <span aria-hidden="true">↗</span></a>
            <a class="hero-secondary" href="<?php echo IbPortalSupport::h(IbPortalSupport::url('polls')); ?>">Проверить знания <span aria-hidden="true">→</span></a>
        </div>
    </div>
    <div class="ib-hero-visual" aria-hidden="true">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="orbit orbit-three"></div>
        <div class="orbit-core"><svg viewBox="0 0 100 110" fill="none"><path d="M50 5 89 20v31c0 25-16 41-39 54C27 92 11 76 11 51V20L50 5Z" stroke="currentColor" stroke-width="2"/><path d="m32 54 12 12 25-28" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
    </div>
    <div class="ib-hero-stats">
        <div><strong><?php echo (int) $docs_count; ?></strong><span><?php echo IbPortalSupport::h(IbPortalSupport::countLabel($docs_count, ['документ', 'документа', 'документов'])); ?></span></div>
        <div><strong><?php echo (int) $news_count; ?></strong><span><?php echo IbPortalSupport::h(IbPortalSupport::countLabel($news_count, ['новость', 'новости', 'новостей'])); ?></span></div>
        <div><strong><?php echo (int) $tests_count; ?></strong><span><?php echo IbPortalSupport::h(IbPortalSupport::countLabel($tests_count, ['тест', 'теста', 'тестов'])); ?></span></div>
    </div>
</section>

<section class="glass-panel employee-memo portal-copy" aria-labelledby="memo-title">
    <h2 id="memo-title"><?= IbPortalSupport::h($settings['memo_title']) ?></h2>
    <?= IbPortalSupport::richText($settings['memo_body']) ?>
</section>

<div class="hero-links">
    <a href="<?php echo IbPortalSupport::h(IbPortalSupport::url('password')); ?>" class="hero-link-card glass-panel">
        <div class="hero-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></div>
        <div>
            <div class="hero-link-title"><?= IbPortalSupport::h($settings['title_password']) ?></div>
            <div class="hero-link-description">Создать надежный пароль</div>
        </div>
    </a>
    <a href="<?php echo IbPortalSupport::h(IbPortalSupport::url('polls')); ?>" class="hero-link-card glass-panel">
        <div class="hero-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h5m-5 4 2 2 4-4"/></svg></div>
        <div>
            <div class="hero-link-title">Тестирование</div>
            <div class="hero-link-description">Проверка знаний</div>
        </div>
    </a>
</div>

<section class="featured-news" aria-labelledby="featured-news-title">
    <div class="featured-news-heading">
        <h2 id="featured-news-title"><?= IbPortalSupport::h($settings['home_news_title']) ?></h2>
        <a href="<?php echo IbPortalSupport::h(IbPortalSupport::url('news')); ?>">Все новости &rarr;</a>
    </div>

    <?php if ($news): ?>
        <div class="featured-news-grid">
            <?php foreach ($news as $item): ?>
                <a href="<?= IbPortalSupport::h(IbPortalSupport::url('article', $item['id'])) ?>" class="featured-news-card glass-panel">
                    <?php if ($item['picture']): ?>
                        <div class="featured-news-image" style="background-image: url('<?= IbPortalSupport::h(IbPortalSupport::url('cover', $item['id'])) ?>')"></div>
                    <?php else: ?>
                        <div class="featured-news-image fallback-image">ИБ</div>
                    <?php endif ?>
                    <div class="featured-news-content">
                        <div class="featured-news-date"><?= IbPortalSupport::date($item['created_at']) ?></div>
                        <div class="featured-news-title"><?= IbPortalSupport::h($item['title']) ?></div>
                    </div>
                </a>
            <?php endforeach ?>
        </div>
    <?php else: ?>
        <div class="glass-panel featured-news-empty"><p>Новостей пока нет.</p></div>
    <?php endif ?>
</section>
