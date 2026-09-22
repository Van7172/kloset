<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · sistema_usuarios (rol Cliente) + perfiles_corporales</div>
            <div class="kl-top-spacer"></div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($clientes) ?></span> filas</span>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1.6fr 1.2fr .8fr .8fr; --min-w:640px;">
                <div class="kl-grid-head">
                    <div>cliente</div>
                    <div>perfil_corporal</div>
                    <div class="kl-cell--right">pedidos</div>
                    <div class="kl-cell--right">estado</div>
                </div>

                <?php if (empty($clientes)): ?>
                    <div class="kl-empty">Todavía no hay clientes registrados en la tienda.</div>
                <?php else: ?>
                    <?php foreach ($clientes as $c): ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $c['id_usuario_sistema'] ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($c['nombre_usuario_sistema'] . ' ' . $c['correo_usuario_sistema'])) ?>">
                            <div class="kl-cell">
                                <b><?= htmlspecialchars($c['nombre_usuario_sistema']) ?></b>
                                <small><?= htmlspecialchars($c['correo_usuario_sistema']) ?></small>
                            </div>
                            <div class="kl-cell">
                                <b class="<?= $c['tiene_perfil'] ? '' : 'kl-fg-soft' ?>">
                                    <?= $c['tiene_perfil'] ? 'talla ' . htmlspecialchars($c['talla_recomendada_perfil_corporal']) : 'Sin perfil' ?>
                                </b>
                            </div>
                            <div class="kl-cell kl-cell--right kl-cell--num"><b><?= (int) $c['total_pedidos'] ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--tag">
                                <b class="<?= $c['estado_usuario_sistema'] === 'activo' ? 'kl-fg-ok' : 'kl-fg-soft' ?>"><?= htmlspecialchars($c['estado_usuario_sistema']) ?></b>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="kl-table-foot">
            <span>Página 1 de 1</span>
            <span>Pulsa una fila para ver el detalle</span>
        </div>
    </div>

    <aside class="kl-detail" id="detalle-cliente" hidden>
        <div class="kl-detail-head">
            <div>
                <div class="kl-kicker" id="cli-kicker">sistema_usuarios</div>
                <div class="kl-detail-title" id="cli-titulo">—</div>
            </div>
            <button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>
        <div class="kl-detail-body" id="cli-resumen"></div>
        <div class="kl-detail-section" id="cli-pedidos-wrap" hidden>
            <div class="kl-kicker">últimos pedidos</div>
            <div id="cli-pedidos"></div>
        </div>
        <div class="kl-detail-actions">
            <button class="kl-btn kl-btn--ghost" type="button" data-cerrar-detalle>Cerrar</button>
        </div>
    </aside>
</div>
