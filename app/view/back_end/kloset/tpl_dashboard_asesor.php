<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar"><div class="kl-kicker">Tu espacio de trabajo</div></div>
        <div class="kl-detail-body">
            <p>Selecciona una sección del menú para comenzar.</p>
            <?php foreach ($admin_menu as $modulo): ?>
                <?php foreach ($modulo['secciones'] as $sec): ?>
                    <p><a href="<?= DW_PANEL . rawurlencode($sec['url_seccion']) ?>"><?= htmlspecialchars($sec['nombre_seccion']) ?></a></p>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
