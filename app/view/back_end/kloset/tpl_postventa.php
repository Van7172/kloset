<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · postventa</div>
            <div class="kl-top-spacer"></div>
            <input class="kl-field" id="post-buscar" placeholder="Pedido, cliente o motivo" style="max-width:240px;">
            <input class="kl-field" id="post-desde" type="date" aria-label="Fecha desde" style="max-width:150px;">
            <input class="kl-field" id="post-hasta" type="date" aria-label="Fecha hasta" style="max-width:150px;">
            <div class="kl-filters" id="filtros-postventa">
                <button class="kl-filter active" type="button" data-filtro="">Todas</button>
                <button class="kl-filter" type="button" data-filtro-tipo="reembolso">Reembolsos</button>
                <button class="kl-filter" type="button" data-filtro-tipo="cambio">Cambios</button>
                <button class="kl-filter" type="button" data-filtro="pendiente_revision">Pendientes</button>
                <button class="kl-filter" type="button" data-filtro="aprobada">Aprobadas</button>
                <button class="kl-filter" type="button" data-filtro="rechazada">Rechazadas</button>
            </div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($solicitudes) ?></span> filas</span>
        </div>
        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1fr 1.4fr 1.2fr 1fr 1.3fr 1.2fr; --min-w:900px;">
                <div class="kl-grid-head"><div>solicitud</div><div>pedido</div><div>cliente</div><div>tipo</div><div>estado</div><div class="kl-cell--right">fecha</div></div>
                <?php if (empty($solicitudes)): ?>
                    <div class="kl-empty">Todavía no hay solicitudes postventa.</div>
                <?php else: foreach ($solicitudes as $s): ?>
                    <div class="kl-grid-row" role="button" tabindex="0" data-id="<?= (int) $s['id_solicitud_postventa'] ?>"
                         data-estado="<?= htmlspecialchars($s['estado_solicitud']) ?>" data-tipo="<?= htmlspecialchars($s['tipo_solicitud']) ?>"
                         data-fecha="<?= htmlspecialchars(substr((string) $s['fecha_creacion'], 0, 10)) ?>"
                         data-buscar="<?= htmlspecialchars(mb_strtolower(($s['cliente'] ?? '') . ' ' . ($s['correo'] ?? '') . ' ' . ($s['codigo_pedido'] ?? '') . ' ' . ($s['motivo_solicitud'] ?? '') . ' ' . $s['id_solicitud_postventa'])) ?>">
                        <div class="kl-cell"><b class="kl-serif">#<?= (int) $s['id_solicitud_postventa'] ?></b></div>
                        <div class="kl-cell"><b><?= htmlspecialchars($s['codigo_pedido'] ?? '—') ?></b></div>
                        <div class="kl-cell"><?= htmlspecialchars($s['cliente'] ?? '—') ?></div>
                        <div class="kl-cell kl-cell--tag"><b><?= htmlspecialchars(strtoupper($s['tipo_solicitud'])) ?></b></div>
                        <div class="kl-cell kl-cell--tag"><b><?= htmlspecialchars(str_replace('_', ' ', $s['estado_solicitud'])) ?></b></div>
                        <div class="kl-cell kl-cell--right"><span class="kl-fg-soft"><?= htmlspecialchars($s['fecha_creacion']) ?></span></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="kl-table-foot"><span>Página 1 de 1</span><span>Pulsa una fila para revisar la solicitud</span></div>
    </div>
    <aside class="kl-detail" id="detalle-postventa" hidden>
        <div class="kl-detail-head"><div><div class="kl-kicker" id="post-kicker">postventa</div><div class="kl-detail-title" id="post-titulo">—</div></div><button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button></div>
        <div class="kl-detail-body" id="post-resumen"></div>
        <div class="kl-detail-section"><div class="kl-kicker" style="margin-bottom:8px;">Productos afectados</div><div id="post-items"></div></div>
        <div class="kl-detail-section"><div class="kl-kicker" style="margin-bottom:8px;">Evidencias</div><div id="post-evidencias"></div></div>
        <div class="kl-detail-section" id="post-acciones">
            <div class="kl-kicker" style="margin-bottom:8px;">Resolución administrativa</div>
            <select class="kl-field" id="post-estado"></select>
            <div id="post-reemplazos" style="margin-top:12px;"></div>
            <textarea class="kl-field" id="post-comentario" rows="3" maxlength="1000" placeholder="Comentario o motivo de rechazo"></textarea>
            <input class="kl-field" id="post-codigo-confirmacion" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="Código de recepción del cambio" hidden>
            <button class="kl-btn" type="button" id="post-guardar" style="width:100%;margin-top:12px;">Guardar resolución</button>
            <button class="kl-btn kl-btn--ghost" type="button" id="post-cambiar-solucion" style="width:100%;margin-top:8px;" hidden>Cambiar a reembolso</button>
            <div class="kl-form-error" id="form-post-error" hidden style="margin-top:12px;"></div>
        </div>
        <div class="kl-detail-section"><div class="kl-kicker" style="margin-bottom:8px;">Historial</div><div id="post-historial"></div></div>
    </aside>
</div>
