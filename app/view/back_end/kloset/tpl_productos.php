<?php if (empty($categorias)): ?>
    <div class="kl-notice" style="margin-bottom:20px;">
        Necesitas al menos una categoría activa antes de registrar productos.
        <a href="<?= DW_PANEL ?>categorias">Crear categoría</a>
    </div>
<?php endif; ?>

<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · productos</div>
            <div class="kl-top-spacer"></div>
            <div class="kl-filters" id="filtros-estado">
                <button class="kl-filter active" type="button" data-filtro="">Todos</button>
                <button class="kl-filter" type="button" data-filtro="activo">activo</button>
                <button class="kl-filter" type="button" data-filtro="inactivo">inactivo</button>
            </div>
            <select class="kl-filter" id="filtro-categoria" style="min-height:36px; border:1px solid var(--rule); background:transparent; color:var(--soft);">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= (int) $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($productos) ?></span> filas</span>
            <button class="kl-create-btn" type="button" id="prod-crear" title="Crear registro" aria-label="Crear registro" <?= empty($categorias) ? 'disabled' : '' ?>>
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 2.4v11.2M2.4 8h11.2" /></svg>
            </button>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:2.2fr 1fr .8fr .8fr .7fr .9fr 84px; --min-w:840px;">
                <div class="kl-grid-head">
                    <div>producto</div>
                    <div>categoría</div>
                    <div class="kl-cell--right">precio</div>
                    <div class="kl-cell--right">imágenes</div>
                    <div class="kl-cell--right">stock</div>
                    <div class="kl-cell--right">estado</div>
                    <div class="kl-cell--right" style="position:sticky; right:0; background:var(--panel);">acciones</div>
                </div>

                <?php if (empty($productos)): ?>
                    <div class="kl-empty">No hay productos registrados. Crea el primero con «Nuevo producto».</div>
                <?php else: ?>
                    <?php foreach ($productos as $prod): ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $prod['id_producto'] ?>"
                             data-categoria="<?= (int) $prod['id_categoria'] ?>"
                             data-estado="<?= htmlspecialchars($prod['estado_producto']) ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($prod['nombre_producto'] . ' ' . $prod['url_producto'])) ?>">
                            <div class="kl-cell">
                                <b><?= htmlspecialchars($prod['nombre_producto']) ?></b>
                                <small>/<?= htmlspecialchars($prod['url_producto']) ?></small>
                            </div>
                            <div class="kl-cell"><b><?= htmlspecialchars($prod['nombre_categoria'] ?? '—') ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--num"><b>S/ <?= number_format((float) $prod['precio_producto'], 2) ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--num"><b><?= (int) $prod['total_imagenes'] ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--num">
                                <b class="<?= (int) $prod['stock_total'] < 15 ? 'kl-fg-red' : '' ?>"><?= (int) $prod['stock_total'] ?></b>
                            </div>
                            <div class="kl-cell kl-cell--right kl-cell--tag">
                                <b class="<?= $prod['estado_producto'] === 'activo' ? 'kl-fg-ok' : 'kl-fg-soft' ?>"><?= htmlspecialchars($prod['estado_producto']) ?></b>
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

    <aside class="kl-detail" id="detalle-producto" hidden>
        <div class="kl-detail-head">
            <div>
                <div class="kl-kicker" id="prod-kicker">productos</div>
                <div class="kl-detail-title" id="prod-titulo">Nuevo producto</div>
            </div>
            <button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>

        <form id="form-producto">
            <input type="hidden" name="id" value="">

            <div class="kl-detail-body" id="prod-resumen" hidden>
                <dl class="kl-kv" style="margin:0;"><dt>productos_variantes</dt><dd id="prod-kv-variantes">0 SKU</dd></dl>
                <dl class="kl-kv" style="margin:0;"><dt>productos_imagenes</dt><dd id="prod-kv-imagenes">0 imágenes</dd></dl>
            </div>

            <div class="kl-detail-section">
                <div class="kl-form-error" id="form-producto-error" hidden></div>

                <label class="kl-label" for="prod-nombre">nombre_producto *</label>
                <input class="kl-field" type="text" id="prod-nombre" name="nombre" maxlength="150" required>

                <label class="kl-label" for="prod-url">url_producto <span class="kl-hint">— se genera del nombre</span></label>
                <input class="kl-field" type="text" id="prod-url" name="url" maxlength="180">

                <label class="kl-label" for="prod-categoria">id_categoria *</label>
                <select class="kl-field" id="prod-categoria" name="id_categoria" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="kl-label" for="prod-precio">precio_producto (S/) *</label>
                <input class="kl-field" type="number" id="prod-precio" name="precio" min="0.01" step="0.01" required>

                <label class="kl-label" for="prod-descripcion">descripcion_producto</label>
                <textarea class="kl-field kl-textarea" id="prod-descripcion" name="descripcion" rows="3"></textarea>

                <label class="kl-label" for="prod-estado">estado_producto</label>
                <select class="kl-field" id="prod-estado" name="estado">
                    <option value="activo">activo</option>
                    <option value="inactivo">inactivo</option>
                </select>

                <label class="kl-label" for="prod-imagenes">productos_imagenes <span class="kl-hint">— JPG, PNG, GIF o WebP</span></label>
                <input class="kl-field" type="file" id="prod-imagenes" name="imagenes[]" accept="image/*" multiple>
                <div class="kl-imgs" id="prod-galeria"></div>
            </div>

            <div class="kl-detail-actions">
                <button class="kl-btn" type="submit" id="prod-guardar">Guardar producto</button>
                <button class="kl-btn kl-btn--ghost kl-btn--sm" type="button" data-cerrar-detalle>Cancelar</button>
            </div>
        </form>
    </aside>

    <aside class="kl-detail kl-confirm" id="confirmar-producto" hidden>
        <div class="kl-detail-section" style="padding-top:18px;">
            <div class="kl-kicker kl-fg-red">DELETE · productos</div>
            <div class="kl-detail-title" data-rol="titulo">¿Eliminar?</div>
            <p data-rol="texto" style="margin:0; color:var(--body); font-size:13.5px; line-height:1.55;"></p>
        </div>
        <div class="kl-detail-actions">
            <button class="kl-btn kl-btn--danger" type="button" data-rol="ok">Eliminar</button>
            <button class="kl-btn kl-btn--ghost" type="button" id="confirmar-producto-cancelar">Cancelar</button>
        </div>
    </aside>
</div>
