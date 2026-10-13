<div class="kl-rise" style="max-width:680px;">
  <section class="kl-panel" style="margin-bottom:18px;"><div class="kl-panel-head"><div class="kl-kicker">Datos de cuenta</div></div><div class="kl-panel-body">
    <form id="cuenta-datos"><label class="kl-label" for="cuenta-nombre">Nombre</label><input class="kl-field" id="cuenta-nombre" name="nombre" maxlength="120" value="<?= htmlspecialchars($miCuenta['nombre_usuario_sistema']) ?>" required>
      <label class="kl-label" for="cuenta-correo">Correo</label><input class="kl-field" id="cuenta-correo" name="correo" type="email" maxlength="150" value="<?= htmlspecialchars($miCuenta['correo_usuario_sistema']) ?>" required>
      <button class="kl-btn" type="submit" style="margin-top:18px;">Guardar datos</button></form>
  </div></section>
  <section class="kl-panel"><div class="kl-panel-head"><div class="kl-kicker">Cambiar contraseña</div></div><div class="kl-panel-body">
    <form id="cuenta-clave"><label class="kl-label" for="cuenta-actual">Contraseña actual</label><input class="kl-field" id="cuenta-actual" name="actual" type="password" required>
      <label class="kl-label" for="cuenta-nueva">Nueva contraseña</label><input class="kl-field" id="cuenta-nueva" name="nueva" type="password" minlength="8" required>
      <button class="kl-btn" type="submit" style="margin-top:18px;">Cambiar contraseña</button></form>
  </div></section>
</div>
