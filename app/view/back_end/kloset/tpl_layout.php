<!DOCTYPE html>
<html lang="es">
<head>
    <?php include ADMIN_INCLUDE . 'inc_head.php'; ?>
    <title><?= htmlspecialchars($title ?? 'Admin') ?> | <?= htmlspecialchars(NOMBRE_SITIO) ?></title>
</head>
<body>
<div class="kl-shell">
    <?php include ADMIN_INCLUDE . 'inc_side.php'; ?>
    <div class="kl-main">
        <header class="kl-top">
            <div style="min-width:0;">
                <div class="kl-kicker"><?= htmlspecialchars($crumb ?? 'Kloset') ?></div>
                <h1><?= htmlspecialchars($title ?? '') ?></h1>
            </div>
            <div class="kl-top-spacer"></div>
            <?php if (!empty($search_ph)): ?>
                <div class="kl-search">
                    <span>/</span>
                    <input type="search" id="kl-search" placeholder="<?= htmlspecialchars($search_ph) ?>" autocomplete="off">
                </div>
            <?php endif; ?>
            <?php if (!empty($primary_label)): ?>
                <button class="kl-btn" type="button" id="kl-primary" <?= !empty($primary_disabled) ? 'disabled' : '' ?>>
                    <?= htmlspecialchars($primary_label) ?>
                </button>
            <?php endif; ?>
        </header>

        <main class="kl-content">
            <?php include ADMIN_TEMPLATE_HOST . $tpl; ?>
        </main>

        <footer class="kl-foot">
            <span>Kloset · Panel administrativo</span>
            <span>MySQL · utf8mb4_unicode_ci · kernel sistema_*</span>
        </footer>
    </div>
</div>
<?php include ADMIN_INCLUDE . 'inc_bottom.php'; ?>
</body>
</html>
