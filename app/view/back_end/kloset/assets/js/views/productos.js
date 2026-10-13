(function () {
  const PANEL = 'detalle-producto';
  const CONFIRM = 'confirmar-producto';
  const form = document.getElementById('form-producto');
  if (!form) return;
  const titulo = document.getElementById('prod-titulo');
  const kicker = document.getElementById('prod-kicker');
  const resumen = document.getElementById('prod-resumen');

  function nuevo() {
    form.reset(); form.elements.id.value = '';
    Kloset.showError('form-producto-error', '');
    kicker.textContent = 'Nuevo producto'; titulo.textContent = 'Nuevo producto';
    resumen.hidden = true; Kloset.marcarFila(null); Kloset.abrirDetalle(PANEL);
    form.elements.nombre.focus();
  }
  async function editar(id) {
    const res = await Kloset.ajax('Productos', 'getById', { id });
    if (res.status !== 'success') return Kloset.toast(res.message || 'No se pudo cargar', 'error');
    const p = res.producto;
    form.reset(); Kloset.showError('form-producto-error', '');
    form.elements.id.value = p.id_producto;
    form.elements.nombre.value = p.nombre_producto || '';
    form.elements.url.value = p.url_producto || '';
    form.elements.id_categoria.value = p.id_categoria || '';
    form.elements.precio.value = p.precio_producto || '';
    form.elements.descripcion.value = p.descripcion_producto || '';
    form.elements.estado.value = p.estado_producto || 'activo';
    kicker.textContent = 'Producto #' + p.id_producto; titulo.textContent = p.nombre_producto;
    document.getElementById('prod-kv-variantes').textContent = Number(p.stock_total || 0) + ' unidades en ' + Number(p.total_variantes || 0) + ' variantes';
    resumen.hidden = false; Kloset.marcarFila(p.id_producto); Kloset.abrirDetalle(PANEL);
  }
  function eliminar(id, fila) {
    const nombre = fila.querySelector('.kl-cell b').textContent;
    Kloset.confirmarEliminar(CONFIRM, PANEL, {
      titulo: '¿Eliminar "' + nombre + '"?',
      texto: 'Solo se eliminará si no existen pedidos o bolsas asociados. Si se vendió, desactívalo.',
      onConfirmar: async () => {
        const res = await Kloset.ajax('Productos', 'deleteProducto', { id });
        if (res.status !== 'success') return Kloset.toast(res.message || 'No se pudo eliminar', 'error');
        Kloset.toast(res.message); Kloset.recargar();
      },
    });
  }
  Kloset.tabla({ onSelect: editar });
  document.getElementById('kl-primary')?.addEventListener('click', nuevo);
  document.getElementById('prod-crear')?.addEventListener('click', nuevo);
  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL)));
  document.getElementById('confirmar-producto-cancelar')?.addEventListener('click', () => Kloset.cerrarDetalle(CONFIRM));
  document.querySelectorAll('.kl-grid-row').forEach((fila) => {
    fila.querySelector('[data-accion="editar"]')?.addEventListener('click', (ev) => { ev.stopPropagation(); editar(fila.dataset.id); });
    fila.querySelector('[data-accion="eliminar"]')?.addEventListener('click', (ev) => { ev.stopPropagation(); eliminar(fila.dataset.id, fila); });
  });
  form.elements.nombre.addEventListener('blur', () => { form.elements.url.value = Kloset.slug(form.elements.nombre.value); });
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault(); Kloset.showError('form-producto-error', '');
    const metodo = form.elements.id.value ? 'updateProducto' : 'store';
    const res = await Kloset.ajax('Productos', metodo, new FormData(form));
    if (res.status !== 'success') return Kloset.showError('form-producto-error', res.message || 'No se pudo guardar');
    Kloset.toast(res.message); Kloset.recargar();
  });
})();
