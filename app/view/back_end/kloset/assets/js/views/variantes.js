(function () {
  const form = document.getElementById('form-variante');
  if (!form) return;
  const filas = [...document.querySelectorAll('.kl-grid-row')];
  const producto = document.getElementById('var-producto');
  const color = document.getElementById('var-color');
  let colores = [];

  async function cargarColores(id, elegido) {
    color.replaceChildren(new Option(id ? 'Selecciona un color' : 'Selecciona un producto', ''));
    if (!id) return;
    const res = await Kloset.ajax('Colores', 'listar', { id_producto: id });
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    colores = res.colores;
    colores.forEach((c) => color.add(new Option(c.nombre_color, c.id_color)));
    if (elegido) color.value = String(elegido);
  }
  producto.addEventListener('change', () => cargarColores(producto.value));
  function nuevo() {
    form.reset(); form.elements.id.value = ''; color.replaceChildren(new Option('Selecciona un producto', ''));
    document.getElementById('var-titulo').textContent = 'Nueva variante';
    Kloset.showError('form-variante-error', ''); Kloset.marcarFila(null); Kloset.abrirDetalle('detalle-variante');
  }
  async function editar(id) {
    const res = await Kloset.ajax('Variantes', 'getById', { id });
    if (res.status !== 'success') return Kloset.toast(res.message || 'No se pudo cargar', 'error');
    const v = res.variante;
    form.reset(); form.elements.id.value = v.id_variante; producto.value = v.id_producto;
    await cargarColores(v.id_producto, v.id_color);
    form.elements.talla.value = v.talla_variante; form.elements.corte.value = v.corte_variante;
    form.elements.sku.value = v.sku_variante; form.elements.stock.value = v.stock_variante;
    document.getElementById('var-titulo').textContent = v.sku_variante;
    Kloset.showError('form-variante-error', ''); Kloset.marcarFila(id); Kloset.abrirDetalle('detalle-variante');
  }
  function eliminar(id, fila) {
    Kloset.confirmarEliminar('confirmar-variante', 'detalle-variante', {
      titulo: '¿Eliminar "' + fila.querySelector('.kl-mono').textContent + '"?',
      texto: 'No se eliminará si esta variante participa en pedidos, bolsas o guardados.',
      onConfirmar: async () => {
        const res = await Kloset.ajax('Variantes', 'deleteVariante', { id });
        if (res.status !== 'success') return Kloset.toast(res.message, 'error');
        Kloset.toast(res.message); Kloset.recargar();
      },
    });
  }
  filas.forEach((fila) => {
    fila.addEventListener('click', () => editar(fila.dataset.id));
    fila.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') editar(fila.dataset.id); });
    fila.querySelector('[data-accion="editar"]')?.addEventListener('click', (ev) => { ev.stopPropagation(); editar(fila.dataset.id); });
    fila.querySelector('[data-accion="eliminar"]')?.addEventListener('click', (ev) => { ev.stopPropagation(); eliminar(fila.dataset.id, fila); });
  });
  document.getElementById('kl-primary')?.addEventListener('click', nuevo);
  document.getElementById('var-crear')?.addEventListener('click', nuevo);
  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => btn.addEventListener('click', () => Kloset.cerrarDetalle('detalle-variante')));
  document.getElementById('confirmar-variante-cancelar')?.addEventListener('click', () => Kloset.cerrarDetalle('confirmar-variante'));
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault(); Kloset.showError('form-variante-error', '');
    const res = await Kloset.ajax('Variantes', form.elements.id.value ? 'updateVariante' : 'store', new FormData(form));
    if (res.status !== 'success') return Kloset.showError('form-variante-error', res.message || 'No se pudo guardar');
    Kloset.toast(res.message); Kloset.recargar();
  });

  const filtro = { disponibilidad: '', producto: '', color: '', talla: '', corte: '' };
  function aplicar() {
    const texto = (document.getElementById('kl-search')?.value || '').trim().toLowerCase();
    let n = 0;
    filas.forEach((fila) => {
      const ok = (!texto || fila.dataset.buscar.includes(texto)) &&
        (!filtro.disponibilidad || fila.dataset.estado === filtro.disponibilidad) &&
        (!filtro.producto || fila.dataset.producto === filtro.producto) &&
        (!filtro.color || fila.dataset.color === filtro.color) &&
        (!filtro.talla || fila.dataset.talla === filtro.talla) &&
        (!filtro.corte || fila.dataset.corte === filtro.corte);
      fila.hidden = !ok; if (ok) n++;
    });
    document.getElementById('kl-rowcount').textContent = n;
  }
  document.getElementById('filtros-estado').addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-filtro]'); if (!btn) return;
    document.querySelectorAll('#filtros-estado .kl-filter').forEach((x) => x.classList.toggle('active', x === btn));
    filtro.disponibilidad = btn.dataset.filtro; aplicar();
  });
  [['inv-producto','producto'],['inv-color','color'],['inv-talla','talla'],['inv-corte','corte']].forEach(([id,key]) => {
    document.getElementById(id).addEventListener('change', (ev) => { filtro[key] = ev.target.value; aplicar(); });
  });
  document.getElementById('kl-search')?.addEventListener('input', aplicar);

  const colorProducto = document.getElementById('color-producto');
  const colorExistente = document.getElementById('color-existente');
  const colorRenombre = document.getElementById('color-renombre');
  const galeria = document.getElementById('color-galeria');
  async function cargarColoresPanel(elegido) {
    colorExistente.replaceChildren(new Option('Selecciona un color', ''));
    if (!colorProducto.value) return;
    const res = await Kloset.ajax('Colores', 'listar', { id_producto: colorProducto.value });
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    res.colores.forEach((c) => colorExistente.add(new Option(c.nombre_color, c.id_color)));
    if (elegido) colorExistente.value = String(elegido);
    await cargarGaleria();
  }
  async function cargarGaleria() {
    galeria.innerHTML = '';
    colorRenombre.value = colorExistente.value ? colorExistente.selectedOptions[0].textContent : '';
    if (!colorExistente.value) return;
    const res = await Kloset.ajax('Colores', 'galeria', { id_color: colorExistente.value });
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    res.imagenes.forEach((img) => {
      const fig = document.createElement('figure'); fig.className = 'kl-img';
      fig.innerHTML = '<img src="' + Kloset.escapeHtml(img.url_imagen) + '" alt=""><div style="display:flex;gap:4px;flex-wrap:wrap;"><button type="button" data-action="principal">' + (Number(img.principal_imagen) ? 'Principal' : 'Elegir principal') + '</button><button type="button" data-action="arriba">↑</button><button type="button" data-action="abajo">↓</button><button type="button" data-action="eliminar">Eliminar</button></div>';
      fig.querySelectorAll('button').forEach((b) => b.addEventListener('click', async () => {
        const action = b.dataset.action;
        if (action === 'eliminar' && !confirm('¿Eliminar esta imagen?')) return;
        const datos = action === 'eliminar' ? { id_imagen: img.id_imagen } : { id_imagen: img.id_imagen, orden: Number(img.orden_imagen) + (action === 'arriba' ? -1 : action === 'abajo' ? 1 : 0), principal: action === 'principal' ? 1 : 0 };
        const res = await Kloset.ajax('Colores', action === 'eliminar' ? 'eliminarImagen' : 'ordenar', datos);
        if (res.status !== 'success') return Kloset.toast(res.message, 'error');
        await cargarGaleria();
      }));
      galeria.appendChild(fig);
    });
  }
  document.getElementById('color-abrir').addEventListener('click', () => Kloset.abrirDetalle('detalle-color'));
  document.getElementById('color-cerrar').addEventListener('click', () => Kloset.cerrarDetalle('detalle-color'));
  colorProducto.addEventListener('change', () => cargarColoresPanel());
  colorExistente.addEventListener('change', cargarGaleria);
  document.getElementById('color-guardar').addEventListener('click', async () => {
    const res = await Kloset.ajax('Colores', 'guardar', { id_producto: colorProducto.value, nombre: document.getElementById('color-nombre').value });
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    document.getElementById('color-nombre').value = ''; await cargarColoresPanel(res.id_color); Kloset.toast(res.message);
  });
  document.getElementById('color-actualizar').addEventListener('click', async () => {
    if (!colorExistente.value) return Kloset.toast('Selecciona un color', 'error');
    const res = await Kloset.ajax('Colores', 'renombrar', { id_color: colorExistente.value, nombre: colorRenombre.value });
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    await cargarColoresPanel(colorExistente.value); Kloset.toast(res.message);
  });
  document.getElementById('color-subir').addEventListener('click', async () => {
    const archivos = document.getElementById('color-imagenes').files;
    if (!colorExistente.value || !archivos.length) return Kloset.toast('Selecciona un color y sus imágenes', 'error');
    const datos = new FormData(); datos.set('id_color', colorExistente.value);
    [...archivos].forEach((file) => datos.append('imagenes[]', file));
    const res = await Kloset.ajax('Colores', 'subir', datos);
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    document.getElementById('color-imagenes').value = ''; await cargarGaleria(); Kloset.toast('Imágenes guardadas');
  });
})();
