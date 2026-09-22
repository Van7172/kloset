(function () {
  const PANEL = 'detalle-producto';
  const CONFIRM = 'confirmar-producto';
  const form = document.getElementById('form-producto');
  if (!form) return;

  const titulo = document.getElementById('prod-titulo');
  const kicker = document.getElementById('prod-kicker');
  const resumen = document.getElementById('prod-resumen');
  const galeria = document.getElementById('prod-galeria');

  let cambios = false;

  /* ------------------------------------------------------------- Galería */

  function pintarGaleria(imagenes) {
    galeria.innerHTML = '';
    if (!imagenes || !imagenes.length) {
      galeria.innerHTML = '<p class="kl-hint" style="margin:6px 0 0;">Sin imágenes cargadas.</p>';
      return;
    }
    imagenes.forEach((img) => {
      const fig = document.createElement('figure');
      fig.className = 'kl-img';
      fig.innerHTML =
        '<img src="' + img.url_imagen + '" alt="">' +
        '<button type="button" class="kl-img-del" data-id-imagen="' + img.id_imagen + '" title="Eliminar">&times;</button>';
      galeria.appendChild(fig);
    });
  }

  galeria.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.kl-img-del');
    if (!btn) return;
    if (!confirm('¿Eliminar esta imagen?')) return;

    const res = await Kloset.ajax('Productos', 'deleteImagen', { id_imagen: btn.dataset.idImagen });
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo eliminar la imagen', 'error');
      return;
    }
    cambios = true;
    pintarGaleria(res.imagenes);
    document.getElementById('prod-kv-imagenes').textContent = res.imagenes.length + ' imágenes';
    Kloset.toast(res.message);
  });

  /* --------------------------------------------------------------- Panel */

  function modoNuevo() {
    form.reset();
    form.elements.id.value = '';
    Kloset.showError('form-producto-error', '');
    kicker.textContent = 'productos · nueva fila';
    titulo.textContent = 'Nuevo producto';
    resumen.hidden = true;
    pintarGaleria([]);
    cambios = false;
    Kloset.marcarFila(null);
    Kloset.abrirDetalle(PANEL);
    form.elements.nombre.focus();
  }

  async function modoEditar(id, fila) {
    const res = await Kloset.ajax('Productos', 'getById', { id: id });
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo cargar el producto', 'error');
      return;
    }
    const p = res.producto;
    form.reset();
    Kloset.showError('form-producto-error', '');
    form.elements.id.value = p.id_producto;
    form.elements.nombre.value = p.nombre_producto || '';
    form.elements.url.value = p.url_producto || '';
    form.elements.id_categoria.value = p.id_categoria || '';
    form.elements.precio.value = p.precio_producto || '';
    form.elements.descripcion.value = p.descripcion_producto || '';
    form.elements.estado.value = p.estado_producto || 'activo';

    kicker.textContent = 'productos · id_producto ' + p.id_producto;
    titulo.textContent = p.nombre_producto;
    const stock = fila ? fila.querySelectorAll('.kl-cell--num b')[2].textContent : '0';
    document.getElementById('prod-kv-variantes').textContent = stock + ' uds en stock';
    document.getElementById('prod-kv-imagenes').textContent = res.imagenes.length + ' imágenes';
    resumen.hidden = false;
    pintarGaleria(res.imagenes);
    cambios = false;
    Kloset.marcarFila(p.id_producto);
    Kloset.abrirDetalle(PANEL);
  }

  function pedirEliminar(id, fila) {
    const nombre = fila.querySelector('.kl-cell b').textContent;
    Kloset.confirmarEliminar(CONFIRM, PANEL, {
      titulo: '¿Eliminar "' + nombre + '"?',
      texto: 'Se eliminan en cascada sus productos_variantes y productos_imagenes. Los pedidos_items existentes conservan el nombre histórico.',
      onConfirmar: async () => {
        const res = await Kloset.ajax('Productos', 'deleteProducto', { id: id });
        if (res.status !== 'success') {
          Kloset.toast(res.message || 'No se pudo eliminar', 'error');
          return;
        }
        Kloset.toast(res.message);
        Kloset.recargar();
      },
    });
  }

  Kloset.tabla({ onSelect: (id, fila) => modoEditar(id, fila) });

  document.getElementById('kl-primary')?.addEventListener('click', modoNuevo);
  document.getElementById('prod-crear')?.addEventListener('click', modoNuevo);
  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      Kloset.cerrarDetalle(PANEL);
      if (cambios) Kloset.recargar();
    });
  });
  document.getElementById('confirmar-producto-cancelar')?.addEventListener('click', () => Kloset.cerrarDetalle(CONFIRM));

  document.querySelectorAll('.kl-grid-row').forEach((fila) => {
    fila.querySelector('[data-accion="editar"]')?.addEventListener('click', (ev) => {
      ev.stopPropagation();
      modoEditar(fila.dataset.id, fila);
    });
    fila.querySelector('[data-accion="eliminar"]')?.addEventListener('click', (ev) => {
      ev.stopPropagation();
      pedirEliminar(fila.dataset.id, fila);
    });
  });

  form.elements.nombre.addEventListener('blur', () => {
    if (form.elements.url.value.trim() === '') {
      form.elements.url.value = Kloset.slug(form.elements.nombre.value);
    }
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    Kloset.showError('form-producto-error', '');

    const metodo = form.elements.id.value ? 'updateProducto' : 'store';
    const res = await Kloset.ajax('Productos', metodo, new FormData(form));

    if (res.status !== 'success') {
      Kloset.showError('form-producto-error', res.message || 'No se pudo guardar');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
