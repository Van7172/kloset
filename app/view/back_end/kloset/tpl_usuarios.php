<div class="kl-work kl-rise">
    <div class="kl-work-main">
        <div class="kl-table-bar">
            <div class="kl-kicker">tabla · sistema_usuarios</div>
            <div class="kl-top-spacer"></div>
            <div class="kl-filters" id="filtros-estado">
                <button class="kl-filter active" type="button" data-filtro="">Todos</button>
                <button class="kl-filter" type="button" data-filtro="activo">activo</button>
                <button class="kl-filter" type="button" data-filtro="inactivo">inactivo</button>
            </div>
            <span class="kl-rowcount"><span id="kl-rowcount"><?= count($users) ?></span> filas</span>
        </div>

        <div class="kl-scroll">
            <div class="kl-grid" style="--cols:1.6fr 1.8fr 1fr .8fr .8fr; --min-w:680px;">
                <div class="kl-grid-head">
                    <div>nombre_usuario_sistema</div>
                    <div>correo_usuario_sistema</div>
                    <div>rol</div>
                    <div class="kl-cell--right">secciones</div>
                    <div class="kl-cell--right">estado</div>
                </div>

                <?php foreach ($users as $u): ?>
                    <div class="kl-grid-row" role="button" tabindex="0"
                         data-id="<?= (int) $u['id_usuario_sistema'] ?>"
                         data-estado="<?= htmlspecialchars($u['estado_usuario_sistema']) ?>"
                         data-buscar="<?= htmlspecialchars(mb_strtolower($u['nombre_usuario_sistema'] . ' ' . $u['correo_usuario_sistema'] . ' ' . $u['nombre_rol'])) ?>">
                        <div class="kl-cell"><b><?= htmlspecialchars($u['nombre_usuario_sistema']) ?></b></div>
                        <div class="kl-cell"><b class="kl-fg-soft"><?= htmlspecialchars($u['correo_usuario_sistema']) ?></b></div>
                        <div class="kl-cell kl-cell--tag"><b><?= htmlspecialchars($u['nombre_rol']) ?></b></div>
                        <div class="kl-cell kl-cell--right kl-cell--num"><b><?= (int) $u['total_secciones'] ?></b></div>
                        <div class="kl-cell kl-cell--right kl-cell--tag">
                            <b class="<?= $u['estado_usuario_sistema'] === 'activo' ? 'kl-fg-ok' : 'kl-fg-soft' ?>"><?= htmlspecialchars($u['estado_usuario_sistema']) ?></b>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="kl-table-foot">
            <span>Página 1 de 1</span>
            <span>Pulsa una fila para editar su acceso</span>
        </div>
    </div>

    <aside class="kl-detail" id="detalle-usuario" hidden>
        <div class="kl-detail-head">
            <div>
                <div class="kl-kicker" id="usr-kicker">sistema_usuarios</div>
                <div class="kl-detail-title" id="usr-titulo">—</div>
            </div>
            <button class="kl-detail-close" type="button" data-cerrar-detalle aria-label="Cerrar">&times;</button>
        </div>

        <form id="form-usuario">
            <input type="hidden" name="id" value="">

            <div class="kl-detail-body" id="usr-resumen"></div>

            <div class="kl-detail-section" id="usr-alta" hidden>
                <label class="kl-label" for="usr-nombre">Nombre *</label>
                <input class="kl-field" type="text" id="usr-nombre" name="nombre" maxlength="120">

                <label class="kl-label" for="usr-correo">Correo electrónico *</label>
                <input class="kl-field" type="email" id="usr-correo" name="correo" maxlength="150">

                <label class="kl-label" for="usr-password">Contraseña * <span class="kl-hint">— 8 caracteres, mayúscula, minúscula, número y símbolo</span></label>
                <input class="kl-field" type="password" id="usr-password" name="password" minlength="8" maxlength="72" autocomplete="new-password">

                <label class="kl-label" for="usr-rol">Tipo de cuenta *</label>
                <select class="kl-field" id="usr-rol" name="id_rol">
                    <option value="">Selecciona…</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id_rol'] ?>"><?= htmlspecialchars($r['nombre_rol']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="usr-alta-perms" hidden>
                    <div class="kl-kicker">Secciones del asesor</div>
                    <?php foreach ($secciones_asesor as $s): ?>
                        <label class="kl-perm">
                            <input type="checkbox" name="secciones[]" value="<?= (int) $s['id_seccion'] ?>" <?= in_array($s['url_seccion'], ['pedidos','notificaciones','clientes'], true) ? 'checked' : '' ?>>
                            <div><b><?= htmlspecialchars($s['nombre_seccion']) ?></b><small><?= htmlspecialchars($s['nombre_modulo']) ?></small></div>
                            <span class="kl-switch"><i></i></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="kl-form-error" id="form-usuario-error" hidden></div>
            <div class="kl-detail-section" id="usr-perms-wrap">
                <div class="kl-kicker">usuarios_secciones · acceso por pantalla</div>
                <div id="usr-perms"></div>
            </div>

            <div class="kl-detail-actions">
                <button class="kl-btn" type="submit" id="usr-guardar">Guardar acceso</button>
                <button class="kl-btn kl-btn--ghost kl-btn--sm" type="button" data-cerrar-detalle>Cancelar</button>
            </div>
        </form>
    </aside>
</div>
