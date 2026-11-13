<?php
$bloques = [
  'Información de la tienda' => ['NOMBRE_SITIO'=>'Nombre comercial','CORREO_CONTACTO'=>'Correo de contacto','TELEFONO_CONTACTO'=>'Teléfono'],
  'Inventario' => ['inventario.alerta_stock'=>'Umbral de stock crítico'],
  'Asistente de talla' => ['tallas.tolerancia_cm'=>'Tolerancia de recomendación (cm)'],
];
$porLlave = array_column($configurations,'valor_configuracion','llave_configuracion');
$ids = array_column($configurations,'id_configuracion','llave_configuracion');
?>
<div class="kl-rise" style="max-width:820px;"><form id="form-config">
  <?php foreach ($bloques as $bloque=>$campos): ?>
  <section class="kl-panel" style="margin-bottom:18px;"><div class="kl-panel-head"><div class="kl-kicker"><?= htmlspecialchars($bloque) ?></div></div><div class="kl-panel-body">
    <?php foreach ($campos as $llave=>$etiqueta): if (!isset($ids[$llave])) continue; ?>
    <div class="kl-config-row"><label for="cfg-<?= (int)$ids[$llave] ?>"><?= htmlspecialchars($etiqueta) ?></label>
      <input id="cfg-<?= (int)$ids[$llave] ?>" type="<?= $llave==='CORREO_CONTACTO' ? 'email' : (in_array($llave,['inventario.alerta_stock','tallas.tolerancia_cm'],true) ? 'number' : 'text') ?>" <?= $llave==='inventario.alerta_stock' ? 'min="1" step="1"' : ($llave==='tallas.tolerancia_cm' ? 'min="0" max="10" step="0.1"' : '') ?> name="valores[<?= (int)$ids[$llave] ?>]" value="<?= htmlspecialchars((string)($porLlave[$llave] ?? '')) ?>" required>
    </div><?php endforeach; ?>
  </div></section>
  <?php endforeach; ?>
  <section class="kl-panel" style="margin-bottom:18px;"><div class="kl-panel-head"><div class="kl-kicker">Envíos</div></div><div class="kl-panel-body"><p>El checkout actual ofrece envío gratis en todas las compras.</p></div></section>
  <section class="kl-panel" style="margin-bottom:18px;"><div class="kl-panel-head"><div class="kl-kicker">Pagos</div></div><div class="kl-panel-body"><p>Modo actual: <strong>Simulador</strong>. Las transacciones son de demostración.</p></div></section>
  <button class="kl-btn" type="submit">Guardar configuración</button>
</form></div>
