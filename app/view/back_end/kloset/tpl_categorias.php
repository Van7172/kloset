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
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1.4fr 1.4fr .8fr .9fr; --min-w:560px;">
                <div class="kl-grid-head">
                    <div>nombre_categoria</div>
                    <div>url_categoria</div>
                    <div class="kl-cell--right">productos</div>
                    <div class="kl-cell--right">estado</div>
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
                <dl class="kl-kv" style="margin:0;"><dt>ON DELETE</dt><dd>RESTRICT si tiene productos</dd></dl>
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
                <button class="kl-btn kl-btn--danger kl-btn--sm" type="button" id="cat-eliminar" hidden>Eliminar</button>
            </div>
        </form>
    </aside>
</div>
