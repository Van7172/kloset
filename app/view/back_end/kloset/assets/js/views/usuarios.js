(function () {
  const PANEL = 'detalle-usuario';
  const form = document.getElementById('form-usuario');
  if (!form) return;

  const titulo = document.getElementById('usr-titulo');
  const kicker = document.getElementById('usr-kicker');
  const resumen = document.getElementById('usr-resumen');
  const perms = document.getElementById('usr-perms');

  function kv(clave, valor, clase) {
    return (
      '<dl class="kl-kv" style="margin:0;"><dt>' + clave + '</dt>' +
      '<dd class="' + (clase || '') + '">' + valor + '</dd></dl>'
    );
  }

  function pintar(datos) {
    const u = datos.usuario;
    form.elements.id.value = u.id_usuario_sistema;
    kicker.textContent = 'sistema_usuarios · id_usuario_sistema ' + u.id_usuario_sistema;
    titulo.textContent = u.nombre_usuario_sistema;

    resumen.innerHTML =
      kv('correo', u.correo_usuario_sistema) +
      kv('rol', u.nombre_rol) +
      kv('estado', u.estado_usuario_sistema, u.estado_usuario_sistema === 'activo' ? 'kl-fg-ok' : 'kl-fg-soft') +
      kv('alta', u.fecha_creacion);

    perms.innerHTML = datos.secciones
      .map(
        (s) =>
          '<label class="kl-perm">' +
          '<input type="checkbox" name="secciones[]" value="' + s.id_seccion + '"' + (s.activa ? ' checked' : '') + '>' +
          '<div><b>' + s.nombre_seccion + '</b><small>' + s.nombre_modulo + ' · /' + s.url_seccion + '</small></div>' +
          '<span class="kl-switch"><i></i></span>' +
          '</label>'
      )
      .join('');

    Kloset.showError('form-usuario-error', '');
    Kloset.marcarFila(u.id_usuario_sistema);
    Kloset.abrirDetalle(PANEL);
  }

  Kloset.tabla({
    onSelect: async (id) => {
      const res = await Kloset.ajax('Usuarios', 'getById', { id: id });
      if (res.status !== 'success') {
        Kloset.toast(res.message || 'No se pudo cargar el usuario', 'error');
        return;
      }
      pintar(res);
    },
  });

  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    Kloset.showError('form-usuario-error', '');

    const res = await Kloset.ajax('Usuarios', 'updateSecciones', new FormData(form));
    if (res.status !== 'success') {
      Kloset.showError('form-usuario-error', res.message || 'No se pudo guardar');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
