<?php
$usuario_actual = $sesion->getUsuario();
$nombre_admin = trim((string) $usuario_actual->getNombre() . ' ' . (string) $usuario_actual->getApellidos());
if ($nombre_admin === '') {
    $nombre_admin = (string) $usuario_actual->getCorreo();
}
$rol_admin = $usuario_actual->getRol() ? (string) $usuario_actual->getRol() : 'Administrador';
$ruta_actual = $_GET['param1'] ?? 'dashboard';
?>
<aside class="kl-aside">
    <div class="kl-brand">
        <img src="<?= IMGS ?>kloset-logo-transparente.png" alt="Kloset">
        <div class="kl-brand-dark" role="img" aria-label="Kloset"></div>
        <span class="kl-badge">Admin</span>
    </div>

    <nav class="kl-nav">
        <?php foreach ($admin_menu as $modulo): ?>
            <div class="kl-nav-group">
                <div class="kl-kicker"><?= htmlspecialchars($modulo['nombre_modulo']) ?></div>
            </div>
            <?php foreach ($modulo['secciones'] as $sec): ?>
                <?php $activa = $ruta_actual === $sec['url_seccion']; ?>
                <a class="kl-nav-link<?= $activa ? ' active' : '' ?>" href="<?= DW_PANEL . htmlspecialchars($sec['url_seccion']) ?>">
                    <span><?= htmlspecialchars($sec['nombre_seccion']) ?></span>
                    <?php if ($sec['count'] !== null): ?>
                        <span class="kl-nav-count"><?= (int) $sec['count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="kl-aside-foot">
        <div class="kl-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($nombre_admin, 0, 1))) ?></div>
        <div class="kl-aside-user">
            <b><?= htmlspecialchars($nombre_admin) ?></b>
            <span><?= htmlspecialchars($rol_admin) ?> · <a href="<?= DW_PANEL ?>sign-out">Salir</a></span>
        </div>
        <button class="kl-theme" type="button" id="btn-theme" title="Cambiar tema" aria-label="Cambiar tema">
            <span class="kl-theme-knob"></span>
            <span class="kl-theme-ico kl-theme-sun">
                <svg width="14" height="14" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="9" cy="9" r="3.2"/><path d="M9 1.6V3M9 15v1.4M1.6 9H3M15 9h1.4M3.8 3.8l1 1M13.2 13.2l1 1M14.2 3.8l-1 1M4.8 13.2l-1 1"/></svg>
            </span>
            <span class="kl-theme-ico kl-theme-moon">
                <svg width="13" height="13" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M15.2 11.4A6.8 6.8 0 0 1 6.6 2.8a6.8 6.8 0 1 0 8.6 8.6Z"/></svg>
            </span>
        </button>
    </div>
</aside>
