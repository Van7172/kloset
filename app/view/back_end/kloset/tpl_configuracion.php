<div class="kl-rise" style="max-width:820px;">
    <div class="kl-panel">
        <div class="kl-panel-head">
            <div class="kl-kicker">tabla · sistema_configuraciones · almacén llave-valor</div>
        </div>
        <div class="kl-panel-body">
            <form id="form-config">
                <?php if (empty($configurations)): ?>
                    <p class="kl-fg-soft" style="font-size:13.5px;">No hay llaves registradas.</p>
                <?php else: ?>
                    <?php foreach ($configurations as $c): ?>
                        <div class="kl-config-row">
                            <div>
                                <div class="kl-mono"><?= htmlspecialchars($c['llave_configuracion']) ?></div>
                                <p><?= htmlspecialchars($c['descripcion_configuracion'] ?? '') ?></p>
                            </div>
                            <input type="text"
                                   name="valores[<?= (int) $c['id_configuracion'] ?>]"
                                   value="<?= htmlspecialchars((string) $c['valor_configuracion']) ?>"
                                   aria-label="<?= htmlspecialchars($c['llave_configuracion']) ?>">
                        </div>
                    <?php endforeach; ?>

                    <button class="kl-btn" type="submit" style="margin-top:18px; min-height:48px; padding:0 22px;">
                        Guardar configuración
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
