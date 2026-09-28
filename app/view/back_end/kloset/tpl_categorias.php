<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · categorias</div>
            <div class="kl-top-spacer"></div>
            <div class="kl-filters" id="filtros-estado">
                <button class="kl-filter active" type="button" data-filtro="">Todos</button>
                <button class="kl-filter" type="button" data-filtro="activo">activo</button>
                <button class="kl-filter" type="button" data-filtro="inactivo">inactivo</button>
            </div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($categorias) ?></span> filas</span>
            <button class="kl-create-btn" type="button" id="cat-crear" title="Crear registro" aria-label="Crear registro">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 2.4v11.2M2.4 8h11.2" /></svg>
            </button>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1.4fr 1.4fr .8fr .9fr 84px; --min-w:640px;">
                <div class="kl-grid-head">
                    <div>nombre_categoria</div>
                    <div>url_categoria</div>
                    <div class="kl-cell--right">productos</div>
                    <div class="kl-cell--right">estado</div>
                    <div class="kl-cell--right" style="position:sticky; right:0; background:var(--panel);">acciones</div>
                </div>

                <?php if (empty($categorias)): ?>
                    <div class="kl-empty">No hay categorías registradas. Crea la primera con «Nueva categoría».</div>
                <?php else: ?>
                    <?php foreach ($categorias as $cat): ?>
                        <div class="kl-grid-row" role="button" tabindex="0"
                             data-id="<?= (int) $cat['id_categoria'] ?>"
                             data-estado="<?= htmlspecialchars($cat['estado_categoria']) ?>"
                             data-buscar="<?= htmlspecialchars(mb_strtolower($cat['nombre_categoria'] . ' ' . $cat['url_categoria'])) ?>">
                            <div class="kl-cell"><b><?= htmlspecialchars($cat['nombre_categoria']) ?></b></div>
                            <div class="kl-cell"><b class="kl-fg-soft">/<?= htmlspecialchars($cat['url_categoria']) ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--num"><b><?= (int) $cat['total_productos'] ?></b></div>
                            <div class="kl-cell kl-cell--right kl-cell--tag">
                                <b class="<?= $cat['estado_categoria'] === 'activo' ? 'kl-fg-ok' : 'kl-fg-soft' ?>"><?= htmlspecialchars($cat['estado_categoria']) ?></b>
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

    <aside class="kl-detail" id="detalle-categoria" hidden>
        <div class="kl-detail-head">
            <div>
                <div class="kl-kicker" id="cat-kicker">categorias</div>
                <div class="kl-detail-title" id="cat-titulo">Nueva categoría</div>
            </div>
            <button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>

        <form id="form-categoria">
            <input type="hidden" name="id" value="">

            <div class="kl-detail-body" id="cat-resumen" hidden>
                <dl class="kl-kv" style="margin:0;"><dt>productos</dt><dd id="cat-kv-productos">0</dd></dl>
                <dl class="kl-kv" style="margin:0;"><dt>ON DELETE</dt><dd>SET NULL en productos.id_categoria</dd></dl>
            </div>

            <div class="kl-detail-section">
                <div class="kl-form-error" id="form-categoria-error" hidden></div>

                <label class="kl-label" for="cat-nombre">nombre_categoria *</label>
                <input class="kl-field" type="text" id="cat-nombre" name="nombre" maxlength="80" required>

                <label class="kl-label" for="cat-url">url_categoria <span class="kl-hint">— se genera del nombre</span></label>
                <input class="kl-field" type="text" id="cat-url" name="url" maxlength="120">

                <label class="kl-label" for="cat-descripcion">descripcion_categoria</label>
                <textarea class="kl-field kl-textarea" id="cat-descripcion" name="descripcion" rows="3"></textarea>

                <label class="kl-label" for="cat-estado">estado_categoria</label>
                <select class="kl-field" id="cat-estado" name="estado">
                    <option value="activo">activo</option>
                    <option value="inactivo">inactivo</option>
                </select>
            </div>

            <div class="kl-detail-actions">
                <button class="kl-btn" type="submit" id="cat-guardar">Guardar categoría</button>
                <button class="kl-btn kl-btn--ghost kl-btn--sm" type="button" data-cerrar-detalle>Cancelar</button>
            </div>
        </form>
    </aside>

    <aside class="kl-detail kl-confirm" id="confirmar-categoria" hidden>
        <div class="kl-detail-section" style="padding-top:18px;">
            <div class="kl-kicker kl-fg-red">DELETE · categorias</div>
            <div class="kl-detail-title" data-rol="titulo">¿Eliminar?</div>
            <p data-rol="texto" style="margin:0; color:var(--body); font-size:13.5px; line-height:1.55;"></p>
        </div>
        <div class="kl-detail-actions">
            <button class="kl-btn kl-btn--danger" type="button" data-rol="ok">Eliminar</button>
            <button class="kl-btn kl-btn--ghost" type="button" id="confirmar-categoria-cancelar">Cancelar</button>
        </div>
    </aside>
</div>
