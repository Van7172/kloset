<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · productos_variantes</div>
            <div class="kl-top-spacer"></div>
            <div class="kl-filters" id="filtros-estado">
                <button class="kl-filter active" type="button" data-filtro="">Todos</button>
                <button class="kl-filter" type="button" data-filtro="Slim">Slim</button>
                <button class="kl-filter" type="button" data-filtro="Regular">Regular</button>
                <button class="kl-filter" type="button" data-filtro="Oversize">Oversize</button>
            </div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($variantes) ?></span> filas</span>
            <button class="kl-create-btn" type="button" id="var-crear" title="Crear registro" aria-label="Crear registro" <?= empty($productos) ? 'disabled' : '' ?>>
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 2.4v11.2M2.4 8h11.2" /></svg>
            </button>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1.3fr 1.8fr .6fr .9fr 1fr 84px; --min-w:760px;">
                <div class="kl-grid-head">
                    <div>sku_variante</div>
                    <div>producto</div>
                    <div class="kl-cell--center">talla</div>
                    <div>corte</div>
                    <div class="kl-cell--right">stock_variante</div>
                    <div class="kl-cell--right" style="position:sticky; right:0; background:var(--panel);">acciones</div>
                </div>

                <?php if (empty($variantes)): ?>
                    <div class="kl-empty">No hay variantes registradas. Crea la primera con «Nueva variante».</div>
                <?php else: ?>
                    <?php foreach ($variantes as $v): ?>
                        <?php $critico = (int) $v['stock_variante'] < $umbralStock; ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $v['id_variante'] ?>"
                             data-estado="<?= htmlspecialchars($v['corte_variante']) ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($v['sku_variante'] . ' ' . $v['nombre_producto'])) ?>">
                            <div class="kl-cell"><b class="kl-mono"><?= htmlspecialchars($v['sku_variante']) ?></b></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($v['nombre_producto']) ?></b></div>
                            <div class="kl-cell kl-cell--center kl-cell--num"><b><?= htmlspecialchars($v['talla_variante']) ?></b></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($v['corte_variante']) ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--num">
                                <b class="<?= $critico ? 'kl-fg-red' : '' ?>"><?= (int) $v['stock_variante'] ?><?= $critico ? ' · crítico' : '' ?></b>
                            </div>
                            <div class="kl-row-actions">
                                <button class="kl-icon-btn" type="button" data-accion="editar" title="Editar" aria-label="Editar">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M11.1 2.3l2.6 2.6-8 8H3.1v-2.6l8-8Z" /><path d="M9.6 3.8l2.6 2.6" /></svg>
                                </button>
                                <button class="kl-icon-btn kl-icon-btn--danger" type="button" data-accion="eliminar" title="Eliminar" aria-label="Eliminar">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2.9 4.6h10.2M6.1 4.6V2.9h3.8v1.7M4.2 4.6l.7 8.5h6.2l.7-8.5M6.6 7v4M9.4 7v4" /></svg>
                                </button>
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

    <aside class="kl-detail" id="detalle-variante" hidden>
        <div class="kl-detail-head">
            <div>
                <div class="kl-kicker" id="var-kicker">productos_variantes</div>
                <div class="kl-detail-title" id="var-titulo">Nueva variante</div>
            </div>
            <button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>

        <form id="form-variante">
            <input type="hidden" name="id" value="">

            <div class="kl-detail-section">
                <div class="kl-form-error" id="form-variante-error" hidden></div>

                <label class="kl-label" for="var-producto">id_producto *</label>
                <select class="kl-field" id="var-producto" name="id_producto" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= (int) $p['id_producto'] ?>"><?= htmlspecialchars($p['nombre_producto']) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="kl-grid-2">
                    <div>
                        <label class="kl-label" for="var-talla">talla_variante *</label>
                        <select class="kl-field" id="var-talla" name="talla" required>
                            <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="kl-label" for="var-corte">corte_variante *</label>
                        <select class="kl-field" id="var-corte" name="corte" required>
                            <?php foreach (['Slim', 'Regular', 'Oversize'] as $c): ?>
                                <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label class="kl-label" for="var-sku">sku_variante * <span class="kl-hint">— único en todo el catálogo</span></label>
                <input class="kl-field" type="text" id="var-sku" name="sku" maxlength="50" required>

                <label class="kl-label" for="var-stock">stock_variante *</label>
                <input class="kl-field" type="number" id="var-stock" name="stock" min="0" step="1" required>
            </div>

            <div class="kl-detail-actions">
                <button class="kl-btn" type="submit" id="var-guardar">Guardar variante</button>
                <button class="kl-btn kl-btn--ghost kl-btn--sm" type="button" data-cerrar-detalle>Cancelar</button>
            </div>
        </form>
    </aside>

    <aside class="kl-detail kl-confirm" id="confirmar-variante" hidden>
        <div class="kl-detail-section" style="padding-top:18px;">
            <div class="kl-kicker kl-fg-red">DELETE · productos_variantes</div>
            <div class="kl-detail-title" data-rol="titulo">¿Eliminar?</div>
            <p data-rol="texto" style="margin:0; color:var(--body); font-size:13.5px; line-height:1.55;"></p>
        </div>
        <div class="kl-detail-actions">
            <button class="kl-btn kl-btn--danger" type="button" data-rol="ok">Eliminar</button>
            <button class="kl-btn kl-btn--ghost" type="button" id="confirmar-variante-cancelar">Cancelar</button>
        </div>
    </aside>
</div>
