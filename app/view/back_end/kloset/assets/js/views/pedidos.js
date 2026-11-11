(function () {
  const PANEL = 'detalle-pedido';
  const panel = document.getElementById(PANEL);
  if (!panel) return;

  const SIGUIENTES = {
    pagado: ['en_preparacion'],
    en_preparacion: ['enviado'],
    enviado: ['en_reparto'],
    en_reparto: ['entregado', 'cancelado'],
  };
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  const label = (e) => e.replace(/_/g, ' ');
  const money = (n) => 'S/ ' + Number(n).toFixed(2);
  const estadoClase = (e) => (e === 'entregado' ? 'kl-fg-ok' : e === 'cancelado' ? 'kl-fg-red' : '');

  const titulo = document.getElementById('ped-titulo');
  const kicker = document.getElementById('ped-kicker');
  const resumen = document.getElementById('ped-resumen');
  const itemsBox = document.getElementById('ped-items');
  const estadosBox = document.getElementById('ped-estados');
  const historialBox = document.getElementById('ped-historial');
  const comentario = document.getElementById('ped-comentario');
  const btnGuardar = document.getElementById('ped-guardar');
  const formularioEstado = document.getElementById('ped-formulario-estado');
  const bloqueCodigo = document.getElementById('ped-codigo-bloque');
  const etiquetaCodigo = document.getElementById('ped-codigo-etiqueta');
  const entradaCodigo = document.getElementById('ped-codigo');
  const logistica = document.getElementById('ped-logistica');
  const btnIncidencia = document.getElementById('ped-registrar-incidencia');
  const incidenciaRegistrada = document.getElementById('ped-incidencia-registrada');
  const formularioIncidencia = document.getElementById('ped-formulario-incidencia');
  const resumenIncidencia = document.getElementById('ped-incidencia-resumen');
  const tipoIncidencia = document.getElementById('ped-incidencia-tipo');
  const bloqueCodigoIncidencia = document.getElementById('ped-incidencia-codigo-bloque');
  const codigoIncidencia = document.getElementById('ped-incidencia-codigo');
  const motivoIncidencia = document.getElementById('ped-incidencia-motivo');
  const descripcionIncidencia = document.getElementById('ped-incidencia-descripcion');
  const evidenciasIncidencia = document.getElementById('ped-incidencia-evidencias');
  const btnGuardarIncidencia = document.getElementById('ped-incidencia-guardar');
  const btnCancelarIncidencia = document.getElementById('ped-incidencia-cancelar');
  const errorIncidencia = document.getElementById('form-ped-incidencia-error');

  let idActual = null;
  let codigoActual = '';
  let estadoElegido = null;
  let estadoRegistrado = null;
  let tieneIncidencia = false;
  let puedeRegistrarIncidencia = false;
  let resItems = [];

  function kv(clave, valor, clase = '') {
    return (
      '<dl class="kl-order-kv"><dt>' + clave + '</dt>' +
      '<dd class="' + clase + '">' + valor + '</dd></dl>'
    );
  }

  function abrirFormularioIncidencia() {
    const seleccionados = [...document.querySelectorAll('input[name="ped-incidencia-item"]:checked')].map((input) => input.value);
    resumenIncidencia.innerHTML = '<b>Pedido:</b> ' + esc(codigoActual) + '<br><b>Cliente:</b> ' + esc(document.getElementById('ped-titulo').textContent) +
      '<div style="margin-top:10px;"><b>Productos afectados *</b></div>' +
      resItems.map((it) => '<label style="display:flex;gap:8px;align-items:flex-start;margin-top:8px;"><input type="checkbox" name="ped-incidencia-item" value="' + it.id_pedido_item + '"' + (seleccionados.includes(String(it.id_pedido_item)) ? ' checked' : '') + '><span><b>' + esc(it.nombre_producto) + '</b><br><small>' + esc(it.nombre_color) + ' · talla ' + esc(it.talla_variante) + ' · corte ' + esc(it.corte_variante) + ' · variante comprada: ' + esc(it.sku_variante) + '</small></span></label>').join('');
    formularioIncidencia.hidden = false;
    formularioIncidencia.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function actualizarCodigoIncidencia() {
    const requiereCodigo = tipoIncidencia.value === 'reembolso';
    bloqueCodigoIncidencia.hidden = !requiereCodigo;
    codigoIncidencia.required = requiereCodigo;
    if (!requiereCodigo) codigoIncidencia.value = '';
  }

  function grupo(tituloGrupo, contenido, clase = '') {
    return '<section class="kl-order-card ' + clase + '"><h3>' + tituloGrupo + '</h3>' + contenido + '</section>';
  }

  function pintarEstados(seleccionado = null) {
    const seleccionAnterior = estadoElegido;
    estadoElegido = seleccionado;
    const opciones = SIGUIENTES[estadoRegistrado] || [];
    estadosBox.innerHTML = opciones.map((e) => {
      const cur = e === seleccionado;
      return (
        '<button type="button" class="kl-btn' + (cur ? '' : ' kl-btn--ghost') + '" style="font-size:11.5px; min-height:40px;" data-estado="' + e + '"' +
        '>' + label(e) + '</button>'
      );
    }).join('');
    const requiereCodigo = seleccionado === 'entregado' || seleccionado === 'cancelado';
    formularioEstado.hidden = opciones.length === 0;
    btnIncidencia.hidden = estadoRegistrado !== 'en_reparto' || tieneIncidencia || !puedeRegistrarIncidencia;
    incidenciaRegistrada.hidden = !tieneIncidencia;
    logistica.hidden = seleccionado !== 'enviado';
    bloqueCodigo.hidden = !requiereCodigo;
    entradaCodigo.required = requiereCodigo;
    etiquetaCodigo.textContent = seleccionado === 'cancelado'
      ? 'Código de cancelación proporcionado por el cliente *'
      : 'Código de confirmación proporcionado por el cliente *';
    if (!requiereCodigo || seleccionAnterior !== seleccionado) entradaCodigo.value = '';
    btnGuardar.disabled = !seleccionado;
  }

  async function cargar(id) {
    const res = await Kloset.ajax('PedidosAdmin', 'getById', { id: id });
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo cargar el pedido', 'error');
      return;
    }
    idActual = id;
    const p = res.pedido;
    tieneIncidencia = Boolean(res.incidencia);
    puedeRegistrarIncidencia = res.puede_registrar_incidencia === true;
    codigoActual = p.codigo_pedido || `pedido #${p.id_pedido}`;

    kicker.textContent = 'pedidos · id interno ' + p.id_pedido;
    titulo.textContent = p.cliente;

    const direccion = [p.direccion_envio_cliente, p.ciudad_envio_cliente, 'Lima']
      .filter(Boolean)
      .map(esc)
      .join(', ');
    const direccionCompleta = direccion + (p.referencia_envio_cliente
      ? '<span class="kl-order-note">Referencia: ' + esc(p.referencia_envio_cliente) + '</span>'
      : '');
    const estadoPagoClase = p.estado_pago === 'aprobado' ? 'kl-fg-ok' : p.estado_pago === 'reembolsado'
      ? 'kl-fg-soft'
      : p.estado_pago === 'rechazado' ? 'kl-fg-red' : 'kl-fg-warn';
    resumen.innerHTML = [
      grupo('Pedido', kv('Código visible', '<strong class="kl-order-code">' + esc(p.codigo_pedido || 'Pendiente') + '</strong>') + kv('ID interno', esc(p.id_pedido))),
      grupo('Cliente', kv('Nombre', esc(p.cliente)) + kv('Correo electrónico', esc(p.correo)) + kv('Fecha de creación', esc(p.fecha_creacion))),
      grupo('Estado y entrega', kv('Estado actual', '<span class="kl-order-status ' + estadoClase(p.estado_pedido) + '">' + esc(label(p.estado_pedido)) + '</span>') +
        kv('Dirección de entrega', direccionCompleta) +
        kv('Fecha estimada', esc(p.entrega_estimada_pedido || 'Por confirmar')) +
        kv('Transportista', esc(p.transportista_pedido || 'Por registrar'))),
      grupo('Pago y totales', kv('Información del pago', esc([p.metodo_pago, p.marca_pago].filter(Boolean).join(' · ') || '—')) +
        kv('Estado del pago', '<span class="' + estadoPagoClase + '">' + esc(p.estado_pago || 'No disponible') + '</span>') +
        kv('Transacción', '<span class="kl-order-transaction">' + esc(p.id_transaccion_pasarela_pago || '—') + '</span>') +
        kv('Subtotal', money(p.subtotal_pedido)) +
        kv('Total', '<strong class="kl-order-total">' + money(p.total_pedido) + '</strong>')),
    ].join('');

    resItems = res.items || [];
    itemsBox.innerHTML = resItems
      .map((it) => {
        const cantidad = Number(it.cantidad_pedido_item);
        const precio = Number(it.precio_unitario_pedido_item);
        return (
          '<article class="kl-order-item"><div class="kl-order-item-main"><h3>' + esc(it.nombre_producto) + '</h3>' +
          '<div class="kl-order-item-attributes">' +
          '<span><b>Color</b>' + esc(it.nombre_color) + '</span>' +
          '<span><b>Talla</b>' + esc(it.talla_variante) + '</span>' +
          '<span><b>Corte</b>' + esc(it.corte_variante) + '</span>' +
          '<span><b>Cantidad</b>' + cantidad + '</span>' +
          '</div><small>SKU: ' + esc(it.sku_variante) + '</small></div>' +
          '<div class="kl-order-item-price"><span>' + money(precio) + ' c/u</span><strong>' + money(precio * cantidad) + '</strong></div></article>'
        );
      })
      .join('') || '<p class="kl-fg-soft" style="font-size:13px;">Sin ítems.</p>';

    estadoRegistrado = p.estado_pedido;
    pintarEstados();
    formularioIncidencia.hidden = true;
    errorIncidencia.hidden = true;
    motivoIncidencia.value = '';
    descripcionIncidencia.value = '';
    evidenciasIncidencia.value = '';
    codigoIncidencia.value = '';
    tipoIncidencia.value = 'reembolso';
    actualizarCodigoIncidencia();
    document.getElementById('ped-transportista').value = p.transportista_pedido || '';
    entradaCodigo.value = '';
    comentario.value = '';
    Kloset.showError('form-pedido-error', '');

    historialBox.innerHTML = res.historial
      .map((h) => (
        '<div style="padding:11px 0; border-bottom:1px solid var(--rule);">' +
        '<div style="display:flex; justify-content:space-between; gap:10px;">' +
        '<span class="kl-mono ' + estadoClase(h.estado_historial_estado_pedido) + '" style="font-size:11.5px;">' + (h.estado_anterior_historial ? label(h.estado_anterior_historial) + ' → ' : '') + label(h.estado_historial_estado_pedido) + '</span>' +
        '<span class="kl-fg-soft" style="font-family:\'Archivo Narrow\',sans-serif; font-size:10.5px; white-space:nowrap;">' + h.fecha_creacion + '</span>' +
        '</div><div style="font-size:12.5px; color:var(--body); margin-top:4px; line-height:1.45;">' + esc(h.comentario_historial_estado_pedido || '—') +
        (h.autor ? ' · ' + esc(h.autor) : '') + '</div></div>'
      ))
      .join('') || '<p class="kl-fg-soft" style="font-size:13px;">Sin movimientos.</p>';

    Kloset.marcarFila(id);
    Kloset.abrirDetalle(PANEL);
  }

  Kloset.tabla({ onSelect: (id) => cargar(id) });
  const pedidoEnlace = new URLSearchParams(window.location.search).get('pedido');
  if (pedidoEnlace && /^[0-9]+$/.test(pedidoEnlace)) cargar(pedidoEnlace);

  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });

  estadosBox.addEventListener('click', (ev) => {
    const btn = ev.target.closest('button[data-estado]');
    if (!btn) return;
    pintarEstados(btn.dataset.estado);
  });

  btnIncidencia.addEventListener('click', () => abrirFormularioIncidencia());
  tipoIncidencia.addEventListener('change', actualizarCodigoIncidencia);
  btnCancelarIncidencia.addEventListener('click', () => {
    formularioIncidencia.hidden = true;
    errorIncidencia.hidden = true;
  });

  btnGuardarIncidencia.addEventListener('click', async () => {
    const afectados = [...document.querySelectorAll('input[name="ped-incidencia-item"]:checked')].map((input) => Number(input.value));
    if (!afectados.length) return Kloset.reportarError('form-ped-incidencia-error', 'Selecciona al menos un producto afectado');
    if (!motivoIncidencia.value.trim() || descripcionIncidencia.value.trim().length < 10) {
      return Kloset.reportarError('form-ped-incidencia-error', 'Indica el motivo y describe el inconveniente con al menos 10 caracteres');
    }
    if (tipoIncidencia.value === 'reembolso' && !/^\d{8}$/.test(codigoIncidencia.value.trim())) {
      return Kloset.reportarError('form-ped-incidencia-error', 'Ingresa el código de cancelación de 8 dígitos proporcionado por el cliente');
    }
    if (!evidenciasIncidencia.files.length) {
      return Kloset.reportarError('form-ped-incidencia-error', 'Adjunta al menos una fotografía como evidencia');
    }
    if (!await Kloset.confirmar({
      titulo: 'Registrar incidencia',
      mensaje: `La incidencia del pedido ${codigoActual} aparecerá en Devoluciones e incidencias para su revisión.`,
      aceptar: 'Registrar incidencia',
    })) return;
    btnGuardarIncidencia.disabled = true;
    errorIncidencia.hidden = true;
    const datos = new FormData();
    datos.set('id_pedido', String(idActual));
    datos.set('tipo', tipoIncidencia.value);
    datos.set('items', JSON.stringify(afectados));
    datos.set('motivo', motivoIncidencia.value.trim());
    datos.set('descripcion', descripcionIncidencia.value.trim());
    if (tipoIncidencia.value === 'reembolso') datos.set('codigo_cancelacion', codigoIncidencia.value.trim());
    [...evidenciasIncidencia.files].forEach((file) => datos.append('evidencias[]', file));
    const res = await Kloset.ajax('Postventa', 'registrarAdmin', datos);
    if (res.status !== 'success') {
      btnGuardarIncidencia.disabled = false;
      return Kloset.reportarError('form-ped-incidencia-error', res.message || 'No se pudo registrar la incidencia');
    }
    Kloset.toast(res.message, 'ok');
    formularioIncidencia.hidden = true;
    btnGuardarIncidencia.disabled = false;
    Kloset.recargar();
  });

  btnGuardar.addEventListener('click', async () => {
    if (!idActual || !estadoElegido) return;
    if ((estadoElegido === 'entregado' || estadoElegido === 'cancelado') && !/^\d{8}$/.test(entradaCodigo.value.trim())) {
      return Kloset.reportarError('form-pedido-error', 'Ingresa el código de 8 dígitos proporcionado por el cliente');
    }
    if (!await Kloset.confirmar({
      titulo: 'Cambiar estado del pedido',
      mensaje: `El pedido ${codigoActual} pasará a ${label(estadoElegido)}.`,
      aceptar: 'Guardar cambios',
    })) return;
    btnGuardar.disabled = true;
    Kloset.showError('form-pedido-error', '');

    const res = await Kloset.ajax('PedidosAdmin', 'cambiarEstado', {
      id: idActual,
      estado: estadoElegido,
      comentario: comentario.value,
      transportista: document.getElementById('ped-transportista').value,
      codigo_confirmacion: entradaCodigo.value.trim(),
    });

    if (res.status !== 'success') {
      btnGuardar.disabled = false;
      Kloset.reportarError('form-pedido-error', res.message || 'No se pudo guardar');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
