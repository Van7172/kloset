<div class="kl-rise">
    <div class="kl-kpis">
        <?php foreach ($kpis as $kpi): ?>
            <div class="kl-kpi">
                <div class="kl-kicker"><?= htmlspecialchars($kpi['label']) ?></div>
                <div class="kl-kpi-value"><?= htmlspecialchars($kpi['value']) ?></div>
                <div class="kl-kpi-delta <?= htmlspecialchars($kpi['clase']) ?>"><?= htmlspecialchars($kpi['delta']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="kl-dash">
        <div class="kl-dash-main kl-panel">
            <div class="kl-panel-head">
                <div class="kl-kicker">Pedidos por estado</div>
                <div class="kl-kicker">tabla · pedidos</div>
            </div>
            <div class="kl-panel-body">
                <?php foreach ($estado_stats as $e): ?>
                    <div class="kl-stat">
                        <div class="kl-stat-head">
                            <span><?= htmlspecialchars($e['label']) ?></span>
                            <span class="kl-stat-n"><?= (int) $e['n'] ?></span>
                        </div>
                        <div class="kl-bar">
                            <div style="width:<?= htmlspecialchars($e['pct']) ?>; background:<?= htmlspecialchars($e['color']) ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="kl-dash-side">
            <div class="kl-panel">
                <div class="kl-panel-head">
                    <div class="kl-kicker">Stock crítico</div>
                </div>
                <div class="kl-panel-body">
                    <?php if (empty($stock_critico)): ?>
                        <p class="kl-fg-soft" style="font-size:13.5px; margin:8px 0;">Ningún SKU por debajo del umbral.</p>
                    <?php else: ?>
                        <?php foreach ($stock_critico as $v): ?>
                            <div class="kl-listrow">
                                <div>
                                    <b><?= htmlspecialchars($v['nombre_producto']) ?></b>
                                    <small><?= htmlspecialchars($v['sku_variante']) ?> · <?= htmlspecialchars($v['talla_variante']) ?> · <?= htmlspecialchars($v['corte_variante']) ?></small>
                                </div>
                                <strong class="kl-fg-red"><?= (int) $v['stock_variante'] ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kl-panel">
                <div class="kl-panel-head">
                    <div class="kl-kicker">Actividad · historial_estados_pedidos</div>
                </div>
                <div class="kl-panel-body">
                    <?php if (empty($actividad)): ?>
                        <p class="kl-fg-soft" style="font-size:13.5px; margin:8px 0;">Sin movimientos registrados todavía.</p>
                    <?php else: ?>
                        <?php foreach ($actividad as $a): ?>
                            <div class="kl-feed">
                                <p><?= htmlspecialchars($a['text']) ?></p>
                                <?php if (!empty($a['comentario'])): ?>
                                    <p class="kl-fg-soft" style="font-size:12.5px; margin-top:4px;"><?= htmlspecialchars($a['comentario']) ?></p>
                                <?php endif; ?>
                                <small><?= htmlspecialchars($a['meta']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
