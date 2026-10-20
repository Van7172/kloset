<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">Inventario · variantes</div>
            <div class="kl-top-spacer"></div>
            <div class="kl-filters" id="filtros-estado">
                <button class="kl-filter active" type="button" data-filtro="">Todos</button>
                <button class="kl-filter" type="button" data-filtro="normal">Stock normal</button>
                <button class="kl-filter" type="button" data-filtro="critico">Stock crítico</button>
                <button class="kl-filter" type="button" data-filtro="agotado">Sin stock</button>
            </div>
            <select class="kl-filter" id="inv-producto"><option value="">Productos</option><?php foreach ($productos as $p): ?><option value="<?= (int)$p['id_producto'] ?>"><?= htmlspecialchars($p['nombre_producto']) ?></option><?php endforeach; ?></select>
            <select class="kl-filter" id="inv-color"><option value="">Colores</option><?php foreach (\Develoweb\App\Model\Colores::todos() as $c): ?><option value="<?= (int)$c['id_color'] ?>" data-producto="<?= (int)$c['id_producto'] ?>"><?= htmlspecialchars($c['nombre_color']) ?></option><?php endforeach; ?></select>
            <select class="kl-filter" id="inv-talla"><option value="">Tallas</option><?php foreach (['XS','S','M','L','XL','XXL'] as $t): ?><option><?= $t ?></option><?php endforeach; ?></select>
            <select class="kl-filter" id="inv-corte"><option value="">Cortes</option><?php foreach (['No aplica','Slim','Regular','Oversize'] as $c): ?><option><?= $c ?></option><?php endforeach; ?></select>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($variantes) ?></span> filas</span>
            <button class="kl-create-btn" type="button" id="var-crear" title="Crear registro" aria-label="Crear registro" <?= empty($productos) ? 'disabled' : '' ?>>
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 2.4v11.2M2.4 8h11.2" /></svg>
            </button>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1.8fr 1fr .6fr .9fr 1.2fr 1fr 84px; --min-w:880px;">
                <div class="kl-grid-head">
                    <div>producto</div>
                    <div>color</div>
                    <div class="kl-cell--center">talla</div>
                    <div>corte</div>
                    <div>SKU</div>
                    <div class="kl-cell--right">stock</div>
                    <div class="kl-cell--right" style="position:sticky; right:0; background:var(--panel);">acciones</div>
                </div>

                <?php if (empty($variantes)): ?>
                    <div class="kl-empty">No hay variantes registradas. Crea la primera con «Nueva variante».</div>
                <?php else: ?>
                    <?php foreach ($variantes as $v): ?>
                        <?php $critico = (int) $v['stock_variante'] < $umbralStock; ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $v['id_variante'] ?>"
                             data-estado="<?= (int) $v['stock_variante'] === 0 ? 'agotado' : ($critico ? 'critico' : 'normal') ?>"
                             data-producto="<?= (int) $v['id_producto'] ?>" data-color="<?= (int) $v['id_color'] ?>" data-talla="<?= htmlspecialchars($v['talla_variante']) ?>" data-corte="<?= htmlspecialchars($v['corte_variante']) ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($v['sku_variante'] . ' ' . $v['nombre_producto'] . ' ' . $v['nombre_color'])) ?>">
                            <div class="kl-cell"><b><?= htmlspecialchars($v['nombre_producto']) ?></b></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($v['nombre_color']) ?></b></div>
                            <div class="kl-cell kl-cell--center kl-cell--num"><b><?= htmlspecialchars($v['talla_variante']) ?></b></div>
                            <div class="kl-cell"><b><?= htmlspecialchars($v['corte_variante']) ?></b></div>
                            <div class="kl-cell"><b class="kl-mono"><?= htmlspecialchars($v['sku_variante']) ?></b></div>
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

                <label class="kl-label" for="var-producto">Producto *</label>
                <select class="kl-field" id="var-producto" name="id_producto" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= (int) $p['id_producto'] ?>"><?= htmlspecialchars($p['nombre_producto']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="kl-label" for="var-color">Color *</label>
                <select class="kl-field" id="var-color" name="id_color" required><option value="">Selecciona un producto</option></select>
                <div id="color-gestion" hidden>
                    <p class="kl-hint">Las imágenes de este color se comparten entre sus tallas y cortes.</p>
                    <div class="kl-imgs" id="color-galeria" aria-live="polite"></div>
                    <label class="kl-label" for="color-imagenes">Agregar imágenes a este color</label>
                    <input class="kl-field" id="color-imagenes" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                    <button class="kl-btn kl-btn--ghost kl-btn--sm" id="color-subir" type="button">Guardar imágenes</button>
                    <label class="kl-label" for="color-renombre">Nombre comercial del color</label>
                    <div class="kl-inline-actions"><input class="kl-field" id="color-renombre" maxlength="100"><button class="kl-btn kl-btn--ghost kl-btn--sm" id="color-actualizar" type="button">Actualizar</button></div>
                </div>

                <div class="kl-grid-2">
                    <div>
                        <label class="kl-label" for="var-talla">Talla *</label>
                        <select class="kl-field" id="var-talla" name="talla" required>
                            <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="kl-label" for="var-corte">Corte</label>
                        <select class="kl-field" id="var-corte" name="corte" required>
                            <?php foreach (['No aplica', 'Slim', 'Regular', 'Oversize'] as $c): ?>
                                <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label class="kl-label" for="var-sku">SKU</label>
                <input class="kl-field kl-mono" type="text" id="var-sku" placeholder="Se genera al guardar" readonly aria-describedby="var-sku-ayuda">
                <p class="kl-hint" id="var-sku-ayuda">Código único asignado automáticamente.</p>

                <label class="kl-label" for="var-stock">Stock *</label>
                <input class="kl-field" type="number" id="var-stock" name="stock" min="0" step="1" required>
            </div>

            <div class="kl-detail-actions">
                <button class="kl-btn" type="submit" id="var-guardar">Guardar variante</button>
                <button class="kl-btn kl-btn--ghost kl-btn--sm" type="button" data-cerrar-detalle>Cancelar</button>
            </div>
        </form>
    </aside>

    <div class="kl-modal" id="modal-nuevo-color" role="dialog" aria-modal="true" aria-labelledby="nuevo-color-titulo" hidden>
        <div class="kl-modal-box kl-color-modal">
            <div class="kl-modal-head"><h3 id="nuevo-color-titulo">Agregar nuevo color</h3><button class="kl-modal-close" type="button" data-cancelar-color aria-label="Cerrar">&times;</button></div>
            <form id="form-nuevo-color" class="kl-form">
                <p class="kl-hint" id="nuevo-color-producto"></p>
                <div class="kl-form-error" id="nuevo-color-error" hidden></div>
                <label class="kl-label" for="nuevo-color-nombre">Nombre comercial del color *</label>
                <input class="kl-field" id="nuevo-color-nombre" name="nombre" maxlength="100" placeholder="Ej. Icey Blue" required>
                <label class="kl-label" for="nuevo-color-imagenes">Imágenes del producto en ese color *</label>
                <input class="kl-field" id="nuevo-color-imagenes" name="imagenes[]" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                <p class="kl-hint">Una o varias imágenes JPEG, PNG, GIF o WebP, hasta 10 MB cada una.</p>
                <div class="kl-modal-foot"><button class="kl-btn" type="submit">Guardar color</button><button class="kl-btn kl-btn--ghost" type="button" data-cancelar-color>Cancelar</button></div>
            </form>
        </div>
    </div>

</div>
