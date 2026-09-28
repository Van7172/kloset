<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · pagos</div>
            <div class="kl-top-spacer"></div>
            <div class="kl-filters" id="filtros-estado">
                <button class="kl-filter active" type="button" data-filtro="">Todos</button>
                <button class="kl-filter" type="button" data-filtro="aprobado">aprobado</button>
                <button class="kl-filter" type="button" data-filtro="pendiente">pendiente</button>
                <button class="kl-filter" type="button" data-filtro="rechazado">rechazado</button>
            </div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($pagos) ?></span> filas</span>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:.8fr 1.2fr 1.6fr .8fr 1fr; --min-w:700px;">
                <div class="kl-grid-head">
                    <div>id_pedido</div>
                    <div>metodo_pago</div>
                    <div>id_transaccion_pasarela_pago</div>
                    <div class="kl-cell--right">monto</div>
                    <div class="kl-cell--right">estado_pago</div>
                </div>

                <?php if (empty($pagos)): ?>
                    <div class="kl-empty">Todavía no se ha registrado ningún pago.</div>
                <?php else: ?>
                    <?php foreach ($pagos as $p): ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $p['id_pago'] ?>"
                             data-estado="<?= htmlspecialchars($p['estado_pago']) ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($p['cliente'] . ' ' . $p['id_transaccion_pasarela_pago'])) ?>">
                            <div class="kl-cell"><b class="kl-serif" style="font-size:16px;">#<?= (int) $p['id_pedido'] ?></b><small><?= htmlspecialchars($p['cliente']) ?></small></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($p['metodo_pago']) ?><?= $p['marca_pago'] ? ' · ' . htmlspecialchars($p['marca_pago']) : '' ?></b></div>
                            <div class="kl-cell"><b class="kl-mono kl-fg-soft" style="text-transform:none;"><?= htmlspecialchars($p['id_transaccion_pasarela_pago'] ?? '—') ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--num"><b>S/ <?= number_format((float) $p['monto_pago'], 2) ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--tag">
                                <b class="<?= $p['estado_pago'] === 'aprobado' ? 'kl-fg-ok' : ($p['estado_pago'] === 'rechazado' ? 'kl-fg-red' : 'kl-fg-warn') ?>"><?= htmlspecialchars($p['estado_pago']) ?></b>
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

    <aside class="kl-detail" id="detalle-pago" hidden>
        <div class="kl-detail-head">
            <div>
                <div class="kl-kicker" id="pago-kicker">pagos · uq_pedido_pago</div>
                <div class="kl-detail-title" id="pago-titulo">—</div>
            </div>
            <button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>
        <div class="kl-detail-body" id="pago-resumen"></div>
        <div class="kl-detail-actions">
            <button class="kl-btn kl-btn--ghost" type="button" data-cerrar-detalle>Cerrar</button>
        </div>
    </aside>
</div>
