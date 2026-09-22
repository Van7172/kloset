<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · notificaciones</div>
            <div class="kl-top-spacer"></div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($notificaciones) ?></span> filas</span>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1.1fr 2.4fr .7fr .7fr; --min-w:640px;">
                <div class="kl-grid-head">
                    <div>cliente</div>
                    <div>mensaje_notificacion</div>
                    <div class="kl-cell--right">pedido</div>
                    <div class="kl-cell--right">leído</div>
                </div>

                <?php if (empty($notificaciones)): ?>
                    <div class="kl-empty">No hay notificaciones todavía.</div>
                <?php else: ?>
                    <?php foreach ($notificaciones as $n): ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $n['id_notificacion'] ?>"
                             data-cliente="<?= htmlspecialchars($n['cliente']) ?>"
                             data-mensaje="<?= htmlspecialchars($n['mensaje_notificacion']) ?>"
                             data-pedido="<?= (int) $n['id_pedido'] ?>"
                             data-fecha="<?= htmlspecialchars($n['fecha_creacion']) ?>"
                             data-leido="<?= $n['leido_notificacion'] ? '1' : '0' ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($n['cliente'] . ' ' . $n['mensaje_notificacion'])) ?>">
                            <div class="kl-cell"><b><?= htmlspecialchars($n['cliente']) ?></b><small><?= htmlspecialchars($n['fecha_creacion']) ?></small></div>
                            <div class="kl-cell kl-cell--wrap"><b style="font-weight:400; white-space:normal;"><?= htmlspecialchars($n['mensaje_notificacion']) ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--num"><b>#<?= (int) $n['id_pedido'] ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--tag">
                                <b class="<?= $n['leido_notificacion'] ? 'kl-fg-soft' : 'kl-fg-red' ?>"><?= $n['leido_notificacion'] ? 'sí' : 'no' ?></b>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="kl-table-foot">
            <span>Página 1 de 1</span>
            <span>Pulsa una fila para marcarla como leída</span>
        </div>
    </div>

    <aside class="kl-detail" id="detalle-notificacion" hidden>
        <div class="kl-detail-head">
            <div>
                <div class="kl-kicker" id="not-kicker">notificaciones</div>
                <div class="kl-detail-title" id="not-titulo">—</div>
            </div>
            <button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>
        <div class="kl-detail-body" id="not-resumen"></div>
        <div class="kl-detail-actions">
            <button class="kl-btn" type="button" id="not-marcar">Marcar como leída</button>
            <button class="kl-btn kl-btn--ghost kl-btn--sm" type="button" data-cerrar-detalle>Cerrar</button>
        </div>
    </aside>
</div>
