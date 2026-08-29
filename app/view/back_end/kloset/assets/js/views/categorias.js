(function () {
  const PANEL = 'detalle-categoria';
  const form = document.getElementById('form-categoria');
  if (!form) return;

  const titulo = document.getElementById('cat-titulo');
  const kicker = document.getElementById('cat-kicker');
  const resumen = document.getElementById('cat-resumen');
  const btnEliminar = document.getElementById('cat-eliminar');

  function modoNuevo() {
    form.reset();
    form.elements.id.value = '';
    Kloset.showError('form-categoria-error', '');
    kicker.textContent = 'categorias · nueva fila';
    titulo.textContent = 'Nueva categoría';
    resumen.hidden = true;
    btnEliminar.hidden = true;
    Kloset.marcarFila(null);
    Kloset.abrirDetalle(PANEL);
    form.elements.nombre.focus();
  }

  function modoEditar(cat, fila) {
    form.reset();
    Kloset.showError('form-categoria-error', '');
    form.elements.id.value = cat.id_categoria;
    form.elements.nombre.value = cat.nombre_categoria || '';
    form.elements.url.value = cat.url_categoria || '';
    form.elements.descripcion.value = cat.descripcion_categoria || '';
    form.elements.estado.value = cat.estado_categoria || 'activo';

    kicker.textContent = 'categorias · id_categoria ' + cat.id_categoria;
    titulo.textContent = cat.nombre_categoria;
    document.getElementById('cat-kv-productos').textContent =
      fila ? fila.querySelector('.kl-cell--num b').textContent : '—';
    resumen.hidden = false;
    btnEliminar.hidden = false;
    Kloset.marcarFila(cat.id_categoria);
    Kloset.abrirDetalle(PANEL);
  }

  Kloset.tabla({
    onSelect: async (id, fila) => {
      const res = await Kloset.ajax('Categorias', 'getById', { id: id });
      if (res.status !== 'success') {
        Kloset.toast(res.message || 'No se pudo cargar la categoría', 'error');
        return;
      }
      modoEditar(res.categoria, fila);
    },
  });

  document.getElementById('kl-primary')?.addEventListener('click', modoNuevo);
  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });

  form.elements.nombre.addEventListener('blur', () => {
    if (form.elements.url.value.trim() === '') {
      form.elements.url.value = Kloset.slug(form.elements.nombre.value);
    }
  });

  btnEliminar.addEventListener('click', async () => {
    const id = form.elements.id.value;
    if (!id || !confirm('¿Eliminar la categoría "' + form.elements.nombre.value + '"?')) return;

    const res = await Kloset.ajax('Categorias', 'deleteCategoria', { id: id });
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo eliminar', 'error');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    Kloset.showError('form-categoria-error', '');

    const metodo = form.elements.id.value ? 'updateCategoria' : 'store';
    const res = await Kloset.ajax('Categorias', metodo, new FormData(form));

    if (res.status !== 'success') {
      Kloset.showError('form-categoria-error', res.message || 'No se pudo guardar');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
