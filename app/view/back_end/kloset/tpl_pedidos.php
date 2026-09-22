<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · pedidos</div>
            <div class="kl-top-spacer"></div>
            <div class="kl-filters" id="filtros-estado">
                <button class="kl-filter active" type="button" data-filtro="">Todos</button>
                <button class="kl-filter" type="button" data-filtro="pendiente_pago">pendiente pago</button>
                <button class="kl-filter" type="button" data-filtro="pagado">pagado</button>
                <button class="kl-filter" type="button" data-filtro="en_preparacion">en preparación</button>
                <button class="kl-filter" type="button" data-filtro="enviado">enviado</button>
                <button class="kl-filter" type="button" data-filtro="entregado">entregado</button>
            </div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($pedidos) ?></span> filas</span>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:.7fr 1.6fr 1.2fr .8fr 1.2fr; --min-w:720px;">
                <div class="kl-grid-head">
                    <div>id_pedido</div>
                    <div>cliente</div>
                    <div>estado_pedido</div>
                    <div class="kl-cell--right">total</div>
                    <div class="kl-cell--right">fecha</div>
                </div>

                <?php if (empty($pedidos)): ?>
                    <div class="kl-empty">Todavía no hay pedidos registrados.</div>
                <?php else: ?>
                    <?php foreach ($pedidos as $p): ?>
                        <?php $estadoClase = $p['estado_pedido'] === 'entregado' ? 'kl-fg-ok' : ($p['estado_pedido'] === 'cancelado' ? 'kl-fg-red' : ($p['estado_pedido'] === 'pendiente_pago' ? 'kl-fg-warn' : '')); ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $p['id_pedido'] ?>"
                             data-estado="<?= htmlspecialchars($p['estado_pedido']) ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($p['cliente'] . ' ' . $p['correo'] . ' ' . $p['id_pedido'])) ?>">
                            <div class="kl-cell"><b class="kl-serif" style="font-size:16px;">#<?= (int) $p['id_pedido'] ?></b></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($p['cliente']) ?></b><small><?= htmlspecialchars($p['correo']) ?></small></div>
                            <div class="kl-cell kl-cell--tag"><b class="<?= $estadoClase ?>"><?= htmlspecialchars(str_replace('_', ' ', $p['estado_pedido'])) ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--num"><b>S/ <?= number_format((float) $p['total_pedido'], 2) ?></b></div>
                            <div class="kl-cell kl-cell--right"><b class="kl-fg-soft" style="font-weight:400; font-size:12.5px;"><?= htmlspecialchars($p['fecha_creacion']) ?></b></div>
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

    <aside class="kl-detail" id="detalle-pedido" hidden>
        <div class="kl-detail-head">
            <div>
                <div class="kl-kicker" id="ped-kicker">pedidos</div>
                <div class="kl-detail-title" id="ped-titulo">—</div>
            </div>
            <button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>

        <div class="kl-detail-body" id="ped-resumen"></div>

        <div class="kl-detail-section">
            <div class="kl-kicker" style="margin-bottom:8px;">pedidos_items</div>
            <div id="ped-items"></div>
        </div>

        <div class="kl-detail-section">
            <div class="kl-kicker" style="margin-bottom:10px;">Cambiar estado_pedido</div>
            <div class="kl-grid-2" id="ped-estados" style="gap:6px;"></div>
            <input class="kl-field" id="ped-comentario" placeholder="Comentario para el historial (opcional)" style="border-bottom:1px solid var(--rule); margin-top:12px;">
            <div class="kl-form-error" id="form-pedido-error" hidden style="margin-top:12px;"></div>
            <button class="kl-btn" type="button" id="ped-guardar" style="width:100%; margin-top:14px;">Guardar cambio de estado</button>
            <p style="margin:12px 0 0; font-size:12px; color:var(--soft); line-height:1.5;">Al guardar se inserta en historial_estados_pedidos y se crea una notificación para el cliente.</p>
        </div>

        <div class="kl-detail-section">
            <div class="kl-kicker" style="margin-bottom:8px;">Historial</div>
            <div id="ped-historial"></div>
        </div>

        <div class="kl-detail-actions">
            <button class="kl-btn kl-btn--ghost" type="button" data-cerrar-detalle>Cerrar</button>
        </div>
    </aside>
</div>
