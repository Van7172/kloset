<section class="kl-panel kl-rise" style="margin-bottom:18px;"><div class="kl-panel-head"><div class="kl-kicker">Alertas del negocio</div></div><div class="kl-panel-body">
  <?php foreach ($alertas['stock'] as $a): ?><div class="kl-listrow"><div><b><?= (int)$a['stock_variante']===0 ? 'Sin stock' : 'Stock crítico' ?> · <?= htmlspecialchars($a['nombre_producto']) ?></b><small><?= htmlspecialchars($a['nombre_color'].' · '.$a['sku_variante']) ?></small></div><a href="<?= DW_PANEL ?>variantes">Inventario</a></div><?php endforeach; ?>
  <?php foreach ($alertas['cancelaciones'] as $a): ?><div class="kl-listrow"><div><b>Cancelación de pedido #<?= (int)$a['id_pedido'] ?></b><small><?= htmlspecialchars($a['fecha_creacion']) ?></small></div><a href="<?= DW_PANEL ?>pedidos">Pedidos</a></div><?php endforeach; ?>
  <?php if (!$alertas['stock'] && !$alertas['cancelaciones']): ?><p class="kl-fg-soft">Sin alertas pendientes.</p><?php endif; ?>
</div></section>
<h2 class="kl-kicker" style="margin:0 0 12px;">Consultas de clientes</h2>
<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · consultas</div>
            <div class="kl-top-spacer"></div>
            <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                <input class="kl-filter" style="border:1px solid var(--rule); background:var(--surface-2); color:var(--ink);" type="date" id="consulta-desde" aria-label="Fecha inicial">
                <input class="kl-filter" style="border:1px solid var(--rule); background:var(--surface-2); color:var(--ink);" type="date" id="consulta-hasta" aria-label="Fecha final">
                <select class="kl-filter" style="border:1px solid var(--rule); background:var(--surface-2); color:var(--ink);" id="consulta-tema" aria-label="Filtrar por tipo de consulta">
                    <option value="">Todos los tipos</option>
                    <option value="talla">Duda de talla</option>
                    <option value="pedido">Mi pedido</option>
                    <option value="otro">Otro tema</option>
                </select>
                <select class="kl-filter" style="border:1px solid var(--rule); background:var(--surface-2); color:var(--ink);" id="consulta-estado" aria-label="Filtrar por estado de atención">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="atendida">Atendida</option>
                </select>
            </div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($consultas) ?></span> filas</span>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1.3fr .9fr 1fr .8fr; --min-w:760px;">
                <div class="kl-grid-head">
                    <div>cliente</div>
                    <div>tipo de consulta</div>
                    <div>fecha de envío</div>
                    <div class="kl-cell--right">estado</div>
                </div>

                <?php if (empty($consultas)): ?>
                    <div class="kl-empty">No hay consultas de clientes todavía.</div>
                <?php else: ?>
                    <?php foreach ($consultas as $c): ?>
                        <?php
                            $temaEtiqueta = ['talla' => 'Duda de talla', 'pedido' => 'Mi pedido', 'otro' => 'Otro tema'][$c['tema_contacto']] ?? 'Otro tema';
                            $estadoEtiqueta = $c['estado_contacto'] === 'atendida' ? 'Atendida' : 'Pendiente';
                        ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $c['id_contacto'] ?>"
                             data-nombre="<?= htmlspecialchars($c['nombre_contacto']) ?>"
                             data-correo="<?= htmlspecialchars($c['correo_contacto']) ?>"
                             data-telefono="<?= htmlspecialchars($c['telefono_contacto'] ?? '') ?>"
                             data-tema="<?= htmlspecialchars($c['tema_contacto']) ?>"
                             data-fecha="<?= htmlspecialchars($c['fecha_creacion']) ?>"
                             data-estado="<?= htmlspecialchars($c['estado_contacto']) ?>"
                             data-mensaje="<?= htmlspecialchars($c['mensaje_contacto']) ?>"
                             data-respuesta="<?= htmlspecialchars($c['respuesta_contacto'] ?? '') ?>"
                             data-fecha-respuesta="<?= htmlspecialchars($c['fecha_respuesta'] ?? '') ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($c['nombre_contacto'] . ' ' . $c['correo_contacto'])) ?>">
                            <div class="kl-cell"><b><?= htmlspecialchars($c['nombre_contacto']) ?></b><small><?= htmlspecialchars($c['correo_contacto']) ?></small></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($temaEtiqueta) ?></b></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($c['fecha_creacion']) ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--tag">
                                <b class="<?= $c['estado_contacto'] === 'atendida' ? 'kl-fg-ok' : 'kl-fg-red' ?>"><?= htmlspecialchars($estadoEtiqueta) ?></b>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="kl-table-foot">
            <span>Consultas recibidas</span>
            <span>Selecciona una fila para ver el mensaje completo</span>
        </div>
    </div>

</div>
<div class="kl-modal" id="detalle-consulta" hidden role="dialog" aria-modal="true" aria-labelledby="consulta-titulo">
      <div class="kl-modal-box kl-modal-box--wide">
        <div class="kl-modal-head">
            <div>
                <div class="kl-kicker">consulta de cliente</div>
                <h3 id="consulta-titulo">—</h3>
            </div>
            <button class="kl-modal-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>
        <div class="kl-detail-body" id="consulta-resumen"></div>
        <div class="kl-modal-form">
            <label for="consulta-respuesta" class="kl-label">Respuesta al cliente</label>
            <textarea class="kl-field kl-textarea" id="consulta-respuesta" rows="5" maxlength="5000" placeholder="Escribe una respuesta clara para resolver la consulta..."></textarea>
        </div>
        <div class="kl-modal-foot">
            <button class="kl-btn kl-btn--ghost" type="button" data-cerrar-detalle>Cerrar</button>
            <button class="kl-btn" type="button" id="consulta-marcar">Enviar respuesta y marcar como atendida</button>
      </div>
    </div>
</div>
