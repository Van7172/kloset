(function () {
  const form = document.getElementById('form-variante');
  if (!form) return;
  const filas = [...document.querySelectorAll('.kl-grid-row')];
  const producto = document.getElementById('var-producto');
  const color = document.getElementById('var-color');
  const galeria = document.getElementById('color-galeria');
  const gestionColor = document.getElementById('color-gestion');
  const modalColor = document.getElementById('modal-nuevo-color');
  const formColor = document.getElementById('form-nuevo-color');
  let colores = [];
  let colorAnterior = '';

  function nombreClave(nombre) {
    return nombre.trim().replace(/\s+/g, ' ').toLocaleLowerCase('es');
  }

  function validarArchivos(archivos) {
    if (!archivos.length || archivos.length > 20) return 'Selecciona entre 1 y 20 imágenes';
    const tipos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if ([...archivos].some((archivo) => !tipos.includes(archivo.type) || archivo.size < 1 || archivo.size > 10 * 1024 * 1024)) {
      return 'Usa imágenes JPEG, PNG, GIF o WebP de hasta 10 MB cada una';
    }
    return '';
  }

  async function cargarColores(id, elegido) {
    colores = [];
    colorAnterior = '';
    gestionColor.hidden = true;
    galeria.replaceChildren();
    color.replaceChildren(new Option(id ? 'Selecciona un color' : 'Selecciona un producto', ''));
    if (!id) return;
    const res = await Kloset.ajax('Colores', 'listar', { id_producto: id });
    if (producto.value !== String(id)) return;
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    colores = res.colores;
    colores.forEach((c) => color.add(new Option(c.nombre_color, c.id_color)));
    color.add(new Option('+ Agregar nuevo color', '__nuevo__'));
    if (elegido && colores.some((c) => String(c.id_color) === String(elegido))) color.value = String(elegido);
    colorAnterior = color.value;
    await cargarGaleria();
  }
  producto.addEventListener('change', () => cargarColores(producto.value));
  function nuevo() {
    form.reset(); form.elements.id.value = ''; colores = []; colorAnterior = '';
    color.replaceChildren(new Option('Selecciona un producto', ''));
    gestionColor.hidden = true; galeria.replaceChildren();
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
    Kloset.confirmarEliminar({
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
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault(); Kloset.showError('form-variante-error', '');
    const sku = form.elements.sku.value.trim().toUpperCase();
    const stock = form.elements.stock.value.trim();
    if (!producto.value || !color.value || color.value === '__nuevo__' || !colores.some((c) => String(c.id_color) === color.value)) return Kloset.showError('form-variante-error', 'Selecciona un color del producto');
    if (!/^(?=[A-Z0-9]*[A-Z])(?=[A-Z0-9]*[0-9])[A-Z0-9]{3,50}$/.test(sku)) return Kloset.showError('form-variante-error', 'El SKU debe tener de 3 a 50 letras y números, incluyendo ambos');
    if (!/^(0|[1-9][0-9]*)$/.test(stock) || Number(stock) > 2147483647) return Kloset.showError('form-variante-error', 'El stock debe ser un entero mayor o igual a cero');
    const duplicado = filas.find((fila) => fila.dataset.id !== form.elements.id.value && fila.dataset.producto === producto.value && fila.dataset.color === color.value && fila.dataset.talla === form.elements.talla.value && fila.dataset.corte === form.elements.corte.value);
    if (duplicado) return Kloset.showError('form-variante-error', 'La combinación de producto, color, talla y corte ya existe');
    if (filas.some((fila) => fila.dataset.id !== form.elements.id.value && fila.querySelector('.kl-mono')?.textContent.trim().toUpperCase() === sku)) return Kloset.showError('form-variante-error', 'El SKU ya está en uso');
    form.elements.sku.value = sku;
    const res = await Kloset.ajax('Variantes', form.elements.id.value ? 'updateVariante' : 'store', new FormData(form));
    if (res.status !== 'success') return Kloset.reportarError('form-variante-error', res.message || 'No se pudo guardar');
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

  const colorRenombre = document.getElementById('color-renombre');
  async function cargarGaleria() {
    const id = color.value;
    galeria.replaceChildren();
    gestionColor.hidden = !id || id === '__nuevo__';
    colorRenombre.value = gestionColor.hidden ? '' : color.selectedOptions[0].textContent;
    if (gestionColor.hidden) return;
    const res = await Kloset.ajax('Colores', 'galeria', { id_color: id });
    if (color.value !== id) return;
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    if (!res.imagenes.length) {
      const vacio = document.createElement('span'); vacio.className = 'kl-hint'; vacio.textContent = 'Este color aún no tiene imágenes.'; galeria.appendChild(vacio);
    }
    res.imagenes.forEach((img) => {
      const fig = document.createElement('figure'); fig.className = 'kl-color-photo';
      fig.innerHTML = '<img src="' + Kloset.escapeHtml(img.url_imagen) + '" alt="Imagen del color seleccionado"><div class="kl-color-photo-actions"><button type="button" data-action="principal">' + (Number(img.principal_imagen) ? 'Principal' : 'Elegir principal') + '</button><button type="button" data-action="arriba" aria-label="Subir imagen">↑</button><button type="button" data-action="abajo" aria-label="Bajar imagen">↓</button><button type="button" data-action="eliminar">Eliminar</button></div>';
      fig.querySelectorAll('button').forEach((b) => b.addEventListener('click', async () => {
        const action = b.dataset.action;
        if (action === 'eliminar' && !await Kloset.confirmar({
          titulo: 'Eliminar imagen',
          mensaje: 'Se quitará de la galería de este producto y color, incluidas todas sus variantes.',
          aceptar: 'Eliminar',
          peligro: true,
        })) return;
        const datos = action === 'eliminar' ? { id_imagen: img.id_imagen } : { id_imagen: img.id_imagen, orden: Number(img.orden_imagen) + (action === 'arriba' ? -1 : action === 'abajo' ? 1 : 0), principal: action === 'principal' ? 1 : 0 };
        const cambio = await Kloset.ajax('Colores', action === 'eliminar' ? 'eliminarImagen' : 'ordenar', datos);
        if (cambio.status !== 'success') return Kloset.toast(cambio.message, 'error');
        await cargarGaleria();
        Kloset.toast(action === 'eliminar' ? 'Imagen eliminada' : action === 'principal' ? 'Imagen principal actualizada' : 'Orden de imágenes actualizado');
      }));
      galeria.appendChild(fig);
    });
  }
  function cerrarModalColor() {
    modalColor.hidden = true;
    document.body.classList.remove('kl-no-scroll');
    formColor.reset();
    Kloset.showError('nuevo-color-error', '');
    color.value = colorAnterior;
    color.focus();
  }
  color.addEventListener('change', () => {
    if (color.value === '__nuevo__') {
      document.getElementById('nuevo-color-producto').textContent = 'Producto: ' + producto.selectedOptions[0].textContent;
      modalColor.hidden = false;
      document.body.classList.add('kl-no-scroll');
      document.getElementById('nuevo-color-nombre').focus();
      return;
    }
    colorAnterior = color.value;
    cargarGaleria();
  });
  document.querySelectorAll('[data-cancelar-color]').forEach((boton) => boton.addEventListener('click', cerrarModalColor));
  modalColor.addEventListener('click', (ev) => { if (ev.target === modalColor) cerrarModalColor(); });
  document.addEventListener('keydown', (ev) => {
    if (modalColor.hidden || !document.getElementById('kl-feedback').hidden) return;
    if (ev.key === 'Escape') cerrarModalColor();
    if (ev.key !== 'Tab') return;
    const controles = [...modalColor.querySelectorAll('input:not([disabled]), button:not([disabled])')];
    const primero = controles[0];
    const ultimo = controles[controles.length - 1];
    if (ev.shiftKey && document.activeElement === primero) { ev.preventDefault(); ultimo.focus(); }
    else if (!ev.shiftKey && document.activeElement === ultimo) { ev.preventDefault(); primero.focus(); }
  });
  formColor.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    Kloset.showError('nuevo-color-error', '');
    const nombre = document.getElementById('nuevo-color-nombre').value.trim().replace(/\s+/g, ' ');
    const imagenes = document.getElementById('nuevo-color-imagenes').files;
    if (!producto.value) return Kloset.showError('nuevo-color-error', 'Selecciona un producto');
    if (!nombre || nombre.length > 100) return Kloset.showError('nuevo-color-error', 'Escribe un nombre comercial de hasta 100 caracteres');
    if (colores.some((c) => nombreClave(c.nombre_color) === nombreClave(nombre))) return Kloset.showError('nuevo-color-error', 'Ese color ya existe para este producto');
    const errorArchivos = validarArchivos(imagenes);
    if (errorArchivos) return Kloset.showError('nuevo-color-error', errorArchivos);
    const datos = new FormData(formColor);
    datos.set('id_producto', producto.value);
    datos.set('nombre', nombre);
    const guardar = formColor.querySelector('[type="submit"]');
    guardar.disabled = true;
    try {
      const res = await Kloset.ajax('Colores', 'guardarConImagenes', datos);
      if (res.status !== 'success') return Kloset.reportarError('nuevo-color-error', res.message || 'No se pudo guardar el color');
      cerrarModalColor();
      await cargarColores(producto.value, res.id_color);
      Kloset.toast('Color e imágenes guardados');
    } finally { guardar.disabled = false; }
  });
  document.getElementById('color-actualizar').addEventListener('click', async () => {
    if (!color.value) return Kloset.toast('Selecciona un color', 'error');
    const nombre = colorRenombre.value.trim().replace(/\s+/g, ' ');
    if (!nombre || nombre.length > 100) return Kloset.toast('Escribe un nombre de color válido', 'error');
    if (colores.some((c) => String(c.id_color) !== color.value && nombreClave(c.nombre_color) === nombreClave(nombre))) return Kloset.toast('Ese color ya existe para este producto', 'error');
    const res = await Kloset.ajax('Colores', 'renombrar', { id_color: color.value, nombre });
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    await cargarColores(producto.value, color.value); Kloset.toast(res.message);
  });
  document.getElementById('color-subir').addEventListener('click', async () => {
    const archivos = document.getElementById('color-imagenes').files;
    if (!color.value) return Kloset.toast('Selecciona un color', 'error');
    const errorArchivos = validarArchivos(archivos);
    if (errorArchivos) return Kloset.toast(errorArchivos, 'error');
    const datos = new FormData(); datos.set('id_color', color.value);
    [...archivos].forEach((file) => datos.append('imagenes[]', file));
    const res = await Kloset.ajax('Colores', 'subir', datos);
    if (res.status !== 'success') return Kloset.toast(res.message, 'error');
    document.getElementById('color-imagenes').value = ''; await cargarGaleria(); Kloset.toast('Imágenes guardadas');
  });
})();
