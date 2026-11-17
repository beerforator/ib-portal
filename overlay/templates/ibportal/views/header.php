<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= IbPortalSupport::h($title) ?> · <?= IbPortalSupport::h($settings['site_name']) ?></title>
    <link rel="stylesheet" href="<?= IbPortalSupport::h(IbPortalSupport::asset('style.css')) ?>">
    <script defer src="<?= IbPortalSupport::h(IbPortalSupport::asset('main.js')) ?>"></script>
</head>
<body class="ib-portal">

    <div class="animated-background"></div>

    <header class="main-header glass-panel">
        <div class="container header-content">
            <div class="logo">
                <?php if ($settings['site_logo'] === 'safeNIIP'): ?>
                    <span class="portal-logo-accent">safe</span>NIIP
                <?php else: ?>
                    <?= IbPortalSupport::h($settings['site_logo']) ?>
                <?php endif ?>
            </div>
            <nav class="main-nav" aria-label="Разделы портала">
                <?php foreach (IbPortalSettings::PAGES as $key => [, $action]): ?>
                    <?php if ($settings['menu_' . $key] === '') { continue; } ?>
                    <a href="<?= IbPortalSupport::h(IbPortalSupport::url($action)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= IbPortalSupport::h($settings['menu_' . $key]) ?></a>
                <?php endforeach ?>
            </nav>
        </div>
    </header>

    <main class="page-content">
        <div class="container">
