<div class="page-heading">
    <h1><?php echo IbPortalSupport::h($title); ?></h1>
    <div class="search-field"><input id="polls-search" type="search" placeholder="Найти тест…" autocomplete="off" aria-label="Поиск тестов"></div>
</div>
<div class="polls-grid" id="polls-container"></div>
<p class="search-empty" id="polls-empty" hidden>По запросу ничего не найдено.</p>

<div class="modal-overlay" id="quiz-overlay">
    <div class="poll-modal glass-panel">
        <button type="button" class="modal-close" id="quiz-close" aria-label="Закрыть тест">&times;</button>
        <div id="quiz-content"></div>
    </div>
</div>

<script type="application/json" id="quiz-data"><?= json_encode([
    'tests' => $testsData, 'nonce' => $token, 'url' => IbPortalSupport::url('submit')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
