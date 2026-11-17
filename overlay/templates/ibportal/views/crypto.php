<h1><?= IbPortalSupport::h($title) ?></h1>
<?php if ($settings['crypto_body'] !== ''): ?>
    <div class="glass-panel portal-copy crypto-rules"><?= IbPortalSupport::richText($settings['crypto_body']) ?></div>
<?php endif ?>

<section class="crypto-documents" aria-labelledby="crypto-documents-title">
    <div class="page-heading">
        <h2 id="crypto-documents-title"><?= IbPortalSupport::h($settings['crypto_docs_title']) ?></h2>
        <?php if ($all_sections): ?>
            <div class="search-field"><input id="documents-search" type="search" placeholder="Найти документ или раздел…" autocomplete="off" aria-label="Поиск документов по криптошлюзу"></div>
        <?php endif ?>
    </div>
    <?php require __DIR__ . '/document-widget.php'; ?>
</section>
