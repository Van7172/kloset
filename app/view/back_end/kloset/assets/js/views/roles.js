(function () {
  const PANEL = 'detalle-rol';
  const CONFIRM = 'confirmar-rol';
  const form = document.getElementById('form-rol');
  if (!form) return;

  const titulo = document.getElementById('rol-titulo');
  const kicker = document.getElementById('rol-kicker');
  const resumen = document.getElementById('rol-resumen');

  function modoNuevo() {
    form.reset();
    form.elements.id.value = '';
    Kloset.showError('form-rol-error', '');
    kicker.textContent = 'sistema_roles · nueva fila';
    titulo.textContent = 'Nuevo rol';
    resumen.hidden = true;
    Kloset.marcarFila(null);
    Kloset.abrirDetalle(PANEL);
    form.elements.nombre.focus();
  }

  async function modoEditar(id) {
    const res = await Kloset.ajax('Roles', 'getById', { id: id });
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo cargar el rol', 'error');
      return;
    }
    const r = res.rol;
    form.reset();
    Kloset.showError('form-rol-error', '');
    form.elements.id.value = r.id_rol;
    form.elements.nombre.value = r.nombre_rol || '';
    form.elements.descripcion.value = r.descripcion_rol || '';
    form.elements.estado.value = r.estado_rol || 'activo';

    kicker.textContent = 'sistema_roles · id_rol ' + r.id_rol;
    titulo.textContent = r.nombre_rol;
    document.getElementById('rol-kv-usuarios').textContent = r.total_usuarios;
    resumen.hidden = false;
    Kloset.marcarFila(r.id_rol);
    Kloset.abrirDetalle(PANEL);
  }

  function pedirEliminar(id, fila) {
    const nombre = fila.querySelector('.kl-mono').textContent;
    Kloset.confirmarEliminar(CONFIRM, PANEL, {
      titulo: '¿Eliminar "' + nombre + '"?',
      texto: 'ON DELETE RESTRICT: no podrá borrarse si algún sistema_usuarios lo tiene asignado.',
      onConfirmar: async () => {
        const res = await Kloset.ajax('Roles', 'deleteRol', { id: id });
        if (res.status !== 'success') {
          Kloset.toast(res.message || 'No se pudo eliminar', 'error');
          return;
        }
        Kloset.toast(res.message);
        Kloset.recargar();
      },
    });
  }

  Kloset.tabla({ onSelect: (id) => modoEditar(id) });

  document.getElementById('kl-primary')?.addEventListener('click', modoNuevo);
  document.getElementById('rol-crear')?.addEventListener('click', modoNuevo);
  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });
  document.getElementById('confirmar-rol-cancelar')?.addEventListener('click', () => Kloset.cerrarDetalle(CONFIRM));

  document.querySelectorAll('.kl-grid-row').forEach((fila) => {
    fila.querySelector('[data-accion="editar"]')?.addEventListener('click', (ev) => {
      ev.stopPropagation();
      modoEditar(fila.dataset.id);
    });
    fila.querySelector('[data-accion="eliminar"]')?.addEventListener('click', (ev) => {
      ev.stopPropagation();
      pedirEliminar(fila.dataset.id, fila);
    });
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    Kloset.showError('form-rol-error', '');

    const metodo = form.elements.id.value ? 'updateRol' : 'store';
    const res = await Kloset.ajax('Roles', metodo, new FormData(form));

    if (res.status !== 'success') {
      Kloset.showError('form-rol-error', res.message || 'No se pudo guardar');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
