(function () {
  const PANEL = 'detalle-pedido';
  const panel = document.getElementById(PANEL);
  if (!panel) return;

  const ESTADOS = ['pagado', 'en_preparacion', 'enviado', 'en_reparto', 'entregado'];
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

  let idActual = null;
  let estadoElegido = null;
  let estadoRegistrado = null;

  function kv(clave, valor, clase) {
    return (
      '<dl class="kl-kv" style="margin:0;"><dt>' + clave + '</dt>' +
      '<dd class="' + (clase || '') + '">' + valor + '</dd></dl>'
    );
  }

  function pintarEstados(actual) {
    estadoElegido = actual;
    const siguiente = { pagado: 'en_preparacion', en_preparacion: 'enviado', enviado: 'en_reparto', en_reparto: 'entregado' }[estadoRegistrado];
    estadosBox.innerHTML = ESTADOS.filter((e) => e === siguiente || (e === 'enviado' && estadoRegistrado === 'enviado')).map((e) => {
      const cur = e === estadoElegido;
      return (
        '<button type="button" class="kl-btn' + (cur ? '' : ' kl-btn--ghost') + '" style="font-size:11.5px; min-height:40px;" data-estado="' + e + '"' +
        (cur && e !== 'enviado' ? ' disabled' : '') + '>' + label(e) + '</button>'
      );
    }).join('');
    document.getElementById('ped-logistica').hidden = !['enviado'].includes(actual);
    document.getElementById('ped-codigo-bloque').hidden = actual !== 'entregado';
  }

  async function cargar(id) {
    const res = await Kloset.ajax('PedidosAdmin', 'getById', { id: id });
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo cargar el pedido', 'error');
      return;
    }
    idActual = id;
    const p = res.pedido;

    kicker.textContent = 'pedidos · id_pedido ' + p.id_pedido;
    titulo.textContent = p.cliente;

    resumen.innerHTML =
      kv('Pedido', '#' + esc(p.id_pedido)) + kv('Cliente', esc(p.cliente)) + kv('Fecha', esc(p.fecha_creacion)) +
      kv('Estado', label(p.estado_pedido), estadoClase(p.estado_pedido)) +
      kv('Correo', esc(p.correo)) +
      kv('Dirección de entrega', esc(p.direccion_envio_cliente + ', ' + p.ciudad_envio_cliente + (p.referencia_envio_cliente ? ' (' + p.referencia_envio_cliente + ')' : ''))) +
      kv('Subtotal', money(p.subtotal_pedido)) +
      kv('Total', money(p.total_pedido)) +
      kv('Pago', esc((p.metodo_pago || '—') + (p.marca_pago ? ' · ' + p.marca_pago : '') + ' · ' + (p.estado_pago || '—')), p.estado_pago === 'aprobado' ? 'kl-fg-ok' : p.estado_pago === 'rechazado' ? 'kl-fg-red' : 'kl-fg-warn') +
      kv('Transacción', esc(p.id_transaccion_pasarela_pago || '—')) +
      kv('Entrega estimada', esc(p.entrega_estimada_pedido || 'Por confirmar')) +
      kv('Transportista', esc(p.transportista_pedido || 'Por registrar')) + kv('Seguimiento', esc(p.seguimiento_pedido || 'Por registrar'));

    itemsBox.innerHTML = res.items
      .map((it) => (
        '<div class="kl-listrow"><div><b>' + esc(it.nombre_producto) + '</b>' +
        '<small>' + esc(it.nombre_color) + ' · ' + esc(it.sku_variante) + ' · ' + esc(it.talla_variante) + ' · ' + esc(it.corte_variante) + ' · x' + Number(it.cantidad_pedido_item) + '</small></div>' +
        '<strong>' + money(it.precio_unitario_pedido_item * it.cantidad_pedido_item) + '</strong></div>'
      ))
      .join('') || '<p class="kl-fg-soft" style="font-size:13px;">Sin ítems.</p>';

    estadoRegistrado = p.estado_pedido;
    pintarEstados(p.estado_pedido);
    btnGuardar.disabled = ['cancelado','entregado'].includes(p.estado_pedido);
    document.getElementById('ped-transportista').value = p.transportista_pedido || '';
    document.getElementById('ped-seguimiento').value = p.seguimiento_pedido || '';
    document.getElementById('ped-codigo').value = '';
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

  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });

  estadosBox.addEventListener('click', (ev) => {
    const btn = ev.target.closest('button[data-estado]');
    if (!btn || btn.disabled) return;
    pintarEstados(btn.dataset.estado);
  });

  btnGuardar.addEventListener('click', async () => {
    if (!idActual || !estadoElegido) return;
    btnGuardar.disabled = true;
    Kloset.showError('form-pedido-error', '');

    const res = await Kloset.ajax('PedidosAdmin', 'cambiarEstado', {
      id: idActual,
      estado: estadoElegido,
      comentario: comentario.value,
      transportista: document.getElementById('ped-transportista').value,
      seguimiento: document.getElementById('ped-seguimiento').value,
      codigo_entrega: document.getElementById('ped-codigo').value,
    });

    if (res.status !== 'success') {
      btnGuardar.disabled = false;
      Kloset.showError('form-pedido-error', res.message || 'No se pudo guardar');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
