<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · sistema_roles</div>
            <div class="kl-top-spacer"></div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($roles) ?></span> filas</span>
            <button class="kl-create-btn" type="button" id="rol-crear" title="Crear registro" aria-label="Crear registro">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 2.4v11.2M2.4 8h11.2" /></svg>
            </button>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1fr 2.2fr .8fr .8fr 84px; --min-w:700px;">
                <div class="kl-grid-head">
                    <div>nombre_rol</div>
                    <div>descripcion_rol</div>
                    <div class="kl-cell--right">usuarios</div>
                    <div class="kl-cell--right">estado</div>
                    <div class="kl-cell--right" style="position:sticky; right:0; background:var(--panel);">acciones</div>
                </div>

                <?php foreach ($roles as $r): ?>
                    <div class="kl-grid-row" role="button" tabindex="0"
                         data-id="<?= (int) $r['id_rol'] ?>"
                         data-estado="<?= htmlspecialchars($r['estado_rol']) ?>"
                         data-buscar="<?= htmlspecialchars(mb_strtolower($r['nombre_rol'] . ' ' . $r['descripcion_rol'])) ?>">
                        <div class="kl-cell"><b class="kl-mono"><?= htmlspecialchars($r['nombre_rol']) ?></b></div>
                        <div class="kl-cell kl-cell--wrap"><b class="kl-fg-soft" style="font-weight:400;"><?= htmlspecialchars($r['descripcion_rol']) ?></b></div>
                        <div class="kl-cell kl-cell--right kl-cell--num"><b><?= (int) $r['total_usuarios'] ?></b></div>
                        <div class="kl-cell kl-cell--right kl-cell--tag">
                            <b class="<?= $r['estado_rol'] === 'activo' ? 'kl-fg-ok' : 'kl-fg-soft' ?>"><?= htmlspecialchars($r['estado_rol']) ?></b>
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
            </div>
        </div>

        <div class="kl-table-foot">
            <span>Página 1 de 1</span>
            <span>Pulsa una fila para ver el detalle</span>
        </div>
    </div>

    <aside class="kl-detail" id="detalle-rol" hidden>
        <div class="kl-detail-head">
            <div>
                <div class="kl-kicker" id="rol-kicker">sistema_roles</div>
                <div class="kl-detail-title" id="rol-titulo">Nuevo rol</div>
            </div>
            <button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>

        <form id="form-rol">
            <input type="hidden" name="id" value="">

            <div class="kl-detail-body" id="rol-resumen" hidden>
                <dl class="kl-kv" style="margin:0;"><dt>usuarios</dt><dd id="rol-kv-usuarios">0</dd></dl>
                <dl class="kl-kv" style="margin:0;"><dt>ON DELETE</dt><dd>RESTRICT en sistema_usuarios.id_rol</dd></dl>
            </div>

            <div class="kl-detail-section">
                <div class="kl-form-error" id="form-rol-error" hidden></div>

                <label class="kl-label" for="rol-nombre">nombre_rol *</label>
                <input class="kl-field" type="text" id="rol-nombre" name="nombre" maxlength="60" required>

                <label class="kl-label" for="rol-descripcion">descripcion_rol</label>
                <textarea class="kl-field kl-textarea" id="rol-descripcion" name="descripcion" rows="3"></textarea>

                <label class="kl-label" for="rol-estado">estado_rol</label>
                <select class="kl-field" id="rol-estado" name="estado">
                    <option value="activo">activo</option>
                    <option value="inactivo">inactivo</option>
                </select>
            </div>

            <div class="kl-detail-actions">
                <button class="kl-btn" type="submit" id="rol-guardar">Guardar rol</button>
                <button class="kl-btn kl-btn--ghost kl-btn--sm" type="button" data-cerrar-detalle>Cancelar</button>
            </div>
        </form>
    </aside>

    <aside class="kl-detail kl-confirm" id="confirmar-rol" hidden>
        <div class="kl-detail-section" style="padding-top:18px;">
            <div class="kl-kicker kl-fg-red">DELETE · sistema_roles</div>
            <div class="kl-detail-title" data-rol="titulo">¿Eliminar?</div>
            <p data-rol="texto" style="margin:0; color:var(--body); font-size:13.5px; line-height:1.55;"></p>
        </div>
        <div class="kl-detail-actions">
            <button class="kl-btn kl-btn--danger" type="button" data-rol="ok">Eliminar</button>
            <button class="kl-btn kl-btn--ghost" type="button" id="confirmar-rol-cancelar">Cancelar</button>
        </div>
    </aside>
</div>
