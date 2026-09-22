(function () {
  const PANEL = 'detalle-usuario';
  const form = document.getElementById('form-usuario');
  if (!form) return;

  const titulo = document.getElementById('usr-titulo');
  const kicker = document.getElementById('usr-kicker');
  const resumen = document.getElementById('usr-resumen');
  const perms = document.getElementById('usr-perms');
  const alta = document.getElementById('usr-alta');
  const permsWrap = document.getElementById('usr-perms-wrap');
  const btnGuardar = document.getElementById('usr-guardar');

  function kv(clave, valor, clase) {
    return (
      '<dl class="kl-kv" style="margin:0;"><dt>' + clave + '</dt>' +
      '<dd class="' + (clase || '') + '">' + valor + '</dd></dl>'
    );
  }

  function modoNuevo() {
    form.reset();
    form.elements.id.value = '';
    Kloset.showError('form-usuario-error', '');
    kicker.textContent = 'sistema_usuarios · nueva fila';
    titulo.textContent = 'Nuevo usuario';
    resumen.innerHTML = '';
    alta.hidden = false;
    permsWrap.hidden = true;
    btnGuardar.textContent = 'Crear usuario';
    Kloset.marcarFila(null);
    Kloset.abrirDetalle(PANEL);
    form.elements.nombre.focus();
  }

  function pintar(datos) {
    alta.hidden = true;
    permsWrap.hidden = false;
    btnGuardar.textContent = 'Guardar acceso';
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

  document.getElementById('kl-primary')?.addEventListener('click', modoNuevo);
  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    Kloset.showError('form-usuario-error', '');

    const esNuevo = !form.elements.id.value;
    const res = await Kloset.ajax('Usuarios', esNuevo ? 'store' : 'updateSecciones', new FormData(form));
    if (res.status !== 'success') {
      Kloset.showError('form-usuario-error', res.message || 'No se pudo guardar');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
