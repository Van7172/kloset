<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · pedidos</div>
            <div class="kl-top-spacer"></div>
            <div class="kl-filters" id="filtros-estado">
                <button class="kl-filter active" type="button" data-filtro="">Todos</button>
                <button class="kl-filter" type="button" data-filtro="pagado">pagado</button>
                <button class="kl-filter" type="button" data-filtro="en_preparacion">en preparación</button>
                <button class="kl-filter" type="button" data-filtro="enviado">enviado</button>
                <button class="kl-filter" type="button" data-filtro="en_reparto">en reparto</button>
                <button class="kl-filter" type="button" data-filtro="entregado">entregado</button>
                <button class="kl-filter" type="button" data-filtro="cancelado">cancelado</button>
            </div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($pedidos) ?></span> filas</span>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1fr 1.2fr 1.4fr 1.2fr .8fr 1.2fr; --min-w:900px;">
                <div class="kl-grid-head">
                    <div>codigo_pedido</div>
                    <div>cliente</div>
                    <div>correo</div>
                    <div>estado_pedido</div>
                    <div class="kl-cell--right">total</div>
                    <div class="kl-cell--right">fecha</div>
                </div>

                <?php if (empty($pedidos)): ?>
                    <div class="kl-empty">Todavía no hay pedidos registrados.</div>
                <?php else: ?>
                    <?php foreach ($pedidos as $p): ?>
                        <?php $estadoClase = $p['estado_pedido'] === 'entregado' ? 'kl-fg-ok' : ($p['estado_pedido'] === 'cancelado' ? 'kl-fg-red' : ''); ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $p['id_pedido'] ?>"
                             data-estado="<?= htmlspecialchars($p['estado_pedido']) ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($p['cliente'] . ' ' . $p['correo'] . ' ' . $p['codigo_pedido'] . ' ' . $p['id_pedido'])) ?>">
                            <div class="kl-cell"><b class="kl-serif" style="font-size:15px; white-space:nowrap;"><?= htmlspecialchars($p['codigo_pedido'] ?? '—') ?></b></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($p['cliente']) ?></b></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($p['correo']) ?></b></div>
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
            <div class="kl-kicker" style="margin-bottom:8px;">Productos del pedido</div>
            <div id="ped-items"></div>
        </div>

        <div id="ped-formulario-estado">
            <div class="kl-detail-section">
                <div class="kl-kicker" style="margin-bottom:10px;">Cambiar estado_pedido</div>
                <div class="kl-grid-2" id="ped-estados" style="gap:6px;"></div>
                <div id="ped-logistica" hidden>
                    <label class="kl-kicker" for="ped-transportista" style="display:block;margin-top:16px;">Transportista *</label>
                    <input class="kl-field" id="ped-transportista" maxlength="120" placeholder="Nombre del operador" style="border-bottom:1px solid var(--rule);">
                </div>
                <div id="ped-codigo-bloque" hidden>
                    <label class="kl-kicker" id="ped-codigo-etiqueta" for="ped-codigo" style="display:block;margin-top:12px;">Código proporcionado por el cliente *</label>
                    <input class="kl-field" id="ped-codigo" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="off">
                </div>
                <input class="kl-field" id="ped-comentario" maxlength="255" placeholder="Comentario para el historial (opcional)" style="border-bottom:1px solid var(--rule); margin-top:12px;">
                <div class="kl-form-error" id="form-pedido-error" hidden style="margin-top:12px;"></div>
                <button class="kl-btn" type="button" id="ped-guardar" style="width:100%; margin-top:14px;" disabled>Guardar cambio de estado</button>
                <button class="kl-btn kl-btn--ghost" type="button" id="ped-registrar-incidencia" style="width:100%; margin-top:8px;" hidden>Registrar incidencia</button>
                <p id="ped-incidencia-registrada" hidden style="margin:12px 0 0; font-size:12px; color:var(--soft); line-height:1.5;">Este pedido ya cuenta con una incidencia registrada.</p>
                <p style="margin:12px 0 0; font-size:12px; color:var(--soft); line-height:1.5;">La entrega estimada se calcula desde la aprobación del pago.</p>
            </div>
        </div>

        <div class="kl-detail-section" id="ped-formulario-incidencia" hidden>
            <div class="kl-kicker" style="margin-bottom:8px;">Registrar incidencia en Devoluciones e incidencias</div>
            <div id="ped-incidencia-resumen" style="font-size:12.5px; line-height:1.5; margin-bottom:12px;"></div>
            <label class="kl-kicker" for="ped-incidencia-tipo" style="display:block;">Solución solicitada *</label>
            <select class="kl-field" id="ped-incidencia-tipo">
                <option value="reembolso">Reembolso</option>
                <option value="cambio">Cambio de producto</option>
            </select>
            <div id="ped-incidencia-codigo-bloque">
                <label class="kl-kicker" for="ped-incidencia-codigo" style="display:block;margin-top:12px;">Código de cancelación del cliente *</label>
                <input class="kl-field" id="ped-incidencia-codigo" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="off">
            </div>
            <label class="kl-kicker" for="ped-incidencia-motivo" style="display:block;margin-top:12px;">Motivo *</label>
            <input class="kl-field" id="ped-incidencia-motivo" maxlength="120" placeholder="Producto equivocado, defectuoso o diferente">
            <label class="kl-kicker" for="ped-incidencia-descripcion" style="display:block;margin-top:12px;">Descripción del inconveniente *</label>
            <textarea class="kl-field" id="ped-incidencia-descripcion" rows="4" maxlength="5000" placeholder="Describe lo ocurrido"></textarea>
            <label class="kl-kicker" for="ped-incidencia-evidencias" style="display:block;margin-top:12px;">Fotografías como evidencia</label>
            <input class="kl-field" id="ped-incidencia-evidencias" type="file" accept="image/jpeg,image/png,image/webp" multiple>
            <div class="kl-form-error" id="form-ped-incidencia-error" hidden style="margin-top:12px;"></div>
            <button class="kl-btn" type="button" id="ped-incidencia-guardar" style="width:100%;margin-top:14px;">Guardar incidencia</button>
            <button class="kl-btn kl-btn--ghost" type="button" id="ped-incidencia-cancelar" style="width:100%;margin-top:8px;">Cancelar</button>
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
