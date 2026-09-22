(function () {
  const PANEL = 'detalle-variante';
  const CONFIRM = 'confirmar-variante';
  const form = document.getElementById('form-variante');
  if (!form) return;

  const titulo = document.getElementById('var-titulo');
  const kicker = document.getElementById('var-kicker');

  function modoNuevo() {
    form.reset();
    form.elements.id.value = '';
    Kloset.showError('form-variante-error', '');
    kicker.textContent = 'productos_variantes · nueva fila';
    titulo.textContent = 'Nueva variante';
    Kloset.marcarFila(null);
    Kloset.abrirDetalle(PANEL);
  }

  async function modoEditar(id) {
    const res = await Kloset.ajax('Variantes', 'getById', { id: id });
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo cargar la variante', 'error');
      return;
    }
    const v = res.variante;
    form.reset();
    Kloset.showError('form-variante-error', '');
    form.elements.id.value = v.id_variante;
    form.elements.id_producto.value = v.id_producto;
    form.elements.talla.value = v.talla_variante;
    form.elements.corte.value = v.corte_variante;
    form.elements.sku.value = v.sku_variante || '';
    form.elements.stock.value = v.stock_variante;

    kicker.textContent = 'productos_variantes · id_variante ' + v.id_variante;
    titulo.textContent = v.sku_variante;
    Kloset.marcarFila(v.id_variante);
    Kloset.abrirDetalle(PANEL);
  }

  function pedirEliminar(id, fila) {
    const sku = fila.querySelector('.kl-mono').textContent;
    Kloset.confirmarEliminar(CONFIRM, PANEL, {
      titulo: '¿Eliminar "' + sku + '"?',
      texto: 'El SKU desaparece del inventario. No se puede eliminar si tiene pedidos o carritos asociados.',
      onConfirmar: async () => {
        const res = await Kloset.ajax('Variantes', 'deleteVariante', { id: id });
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
  document.getElementById('var-crear')?.addEventListener('click', modoNuevo);
  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });
  document.getElementById('confirmar-variante-cancelar')?.addEventListener('click', () => Kloset.cerrarDetalle(CONFIRM));

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
    Kloset.showError('form-variante-error', '');

    const metodo = form.elements.id.value ? 'updateVariante' : 'store';
    const res = await Kloset.ajax('Variantes', metodo, new FormData(form));

    if (res.status !== 'success') {
      Kloset.showError('form-variante-error', res.message || 'No se pudo guardar');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
