(function () {
  const panel = document.getElementById('detalle-postventa');
  if (!panel) return;
  const esc = (v) => Kloset.escapeHtml(v ?? '—');
  const money = (v) => 'S/ ' + Number(v || 0).toFixed(2);
  let actual = null;
  const resumen = document.getElementById('post-resumen');
  const items = document.getElementById('post-items');
  const evidencias = document.getElementById('post-evidencias');
  const historial = document.getElementById('post-historial');
  const estado = document.getElementById('post-estado');
  const comentario = document.getElementById('post-comentario');
  const codigoConfirmacion = document.getElementById('post-codigo-confirmacion');
  const error = document.getElementById('form-post-error');
  const reemplazos = document.getElementById('post-reemplazos');
  const cambiarSolucion = document.getElementById('post-cambiar-solucion');

  function aplicarFiltros() {
    const texto = (document.getElementById('post-buscar')?.value || '').trim().toLowerCase();
    const desde = document.getElementById('post-desde')?.value || '';
    const hasta = document.getElementById('post-hasta')?.value || '';
    const estadoFiltro = document.querySelector('#filtros-postventa .kl-filter.active')?.dataset.filtro || '';
    const tipoFiltro = document.querySelector('#filtros-postventa .kl-filter.active[data-filtro-tipo]')?.dataset.filtroTipo || '';
    let total = 0;
    document.querySelectorAll('#filtros-postventa').length;
    document.querySelectorAll('.kl-grid-row[data-id]').forEach((fila) => {
      const coincide = (!texto || fila.dataset.buscar.includes(texto))
        && (!desde || fila.dataset.fecha >= desde)
        && (!hasta || fila.dataset.fecha <= hasta)
        && (!estadoFiltro || fila.dataset.estado === estadoFiltro)
        && (!tipoFiltro || fila.dataset.tipo === tipoFiltro);
      fila.hidden = !coincide;
      if (coincide) total++;
    });
    const contador = document.getElementById('kl-rowcount');
    if (contador) contador.textContent = total;
  }

  function kv(k, v) { return '<dl class="kl-order-kv"><dt>' + esc(k) + '</dt><dd>' + esc(v) + '</dd></dl>'; }
  function render(data) {
    actual = data.solicitud;
    document.getElementById('post-kicker').textContent = 'solicitud #' + actual.id_solicitud_postventa;
    document.getElementById('post-titulo').textContent = actual.codigo_pedido || 'Solicitud postventa';
    resumen.innerHTML = kv('Cliente', actual.cliente) + kv('Correo', actual.correo) + kv('Tipo', actual.tipo_solicitud === 'reembolso' ? 'Reembolso' : 'Cambio de prenda') + kv('Estado', actual.estado_solicitud.replace(/_/g, ' ')) + kv('Motivo', actual.motivo_solicitud) + kv('Descripción', actual.descripcion_solicitud) + (actual.importe_aprobado ? kv('Importe involucrado', money(actual.importe_aprobado)) : '');
    items.innerHTML = (data.items || []).map((it) => {
      const lista = (data.opciones_reemplazo?.[it.id_item_postventa] || []).map((v) => '<option value="' + v.id_variante + '">' + esc(v.nombre_color + ' · ' + v.talla_variante + ' · ' + v.corte_variante + ' · ' + v.sku_variante) + ' · stock ' + v.stock_variante + '</option>').join('');
      const selector = actual.tipo_solicitud === 'cambio' && actual.estado_solicitud === 'en_revision'
        ? '<label class="kl-kicker" style="display:block;margin-top:8px;">Variante de reemplazo<select class="kl-field post-reemplazo" data-item="' + it.id_item_postventa + '"><option value="">Selecciona una variante</option>' + lista + '</select></label>'
        : '';
      return '<div class="kl-listrow"><div><b>' + esc(it.nombre_producto_compra) + '</b><small>' + esc(it.nombre_color_compra) + ' · talla ' + esc(it.talla_compra) + ' · corte ' + esc(it.corte_compra) + ' · variante original ' + esc(it.sku_compra) + ' · cantidad ' + esc(it.cantidad) + '</small>' + selector + '</div><strong>' + money(it.importe_item) + '</strong></div>';
    }).join('') || '<p class="kl-fg-soft">Sin productos.</p>';
    evidencias.innerHTML = (data.evidencias || []).map((ev) => '<a href="' + esc(ev.ruta_evidencia) + '" target="_blank" rel="noreferrer" class="kl-listrow"><span>' + esc(ev.tipo_mime_evidencia) + '</span><span>Ver evidencia ↗</span></a>').join('') || '<p class="kl-fg-soft">No adjuntó fotografías.</p>';
    historial.innerHTML = (data.historial || []).map((h) => '<div style="padding:8px 0;border-bottom:1px solid var(--rule);"><b>' + esc(h.estado_nuevo.replace(/_/g, ' ')) + '</b><small class="kl-fg-soft"> · ' + esc(h.fecha_creacion) + '</small><div>' + esc(h.comentario) + '</div></div>').join('') || '<p class="kl-fg-soft">Sin historial.</p>';
    const estadosCambio = {
      pendiente_revision: [['en_revision', 'En revisión']],
      en_revision: [['aprobada', 'Aprobar'], ['rechazada', 'Rechazar']],
      aprobada: [['cambio_en_preparacion', 'Cambio en preparación']],
      cambio_en_preparacion: [['cambio_enviado', 'Cambio enviado']],
      cambio_enviado: [['cambio_en_reparto', 'Cambio en reparto']],
      cambio_en_reparto: [['completada', 'Completar cambio']],
    };
    const estadosReembolso = {
      pendiente_revision: [['en_revision', 'En revisión']],
      en_revision: [['aprobada', 'Aprobar'], ['rechazada', 'Rechazar']],
      aprobada: [['reembolso_en_proceso', 'Reembolso en proceso']],
      reembolso_en_proceso: [['reembolso_realizado', 'Reembolso realizado']],
      reembolso_realizado: [['completada', 'Completar incidencia']],
    };
    const opciones = (actual.tipo_solicitud === 'cambio' ? estadosCambio : estadosReembolso)[actual.estado_solicitud] || [];
    estado.innerHTML = opciones.map(([value, text]) => '<option value="' + value + '">' + text + '</option>').join('');
    codigoConfirmacion.hidden = actual.tipo_solicitud !== 'cambio' || actual.estado_solicitud !== 'cambio_en_reparto';
    document.getElementById('post-acciones').hidden = ['completada', 'rechazada'].includes(actual.estado_solicitud);
    cambiarSolucion.hidden = actual.tipo_solicitud !== 'cambio' || !['aprobada', 'cambio_en_preparacion'].includes(actual.estado_solicitud);
    Kloset.abrirDetalle('detalle-postventa');
  }
  async function cargar(id) {
    const res = await Kloset.ajax('Postventa', 'detalle', { id });
    if (res.status !== 'success') return Kloset.toast(res.message || 'No se pudo cargar la solicitud', 'error');
    render(res);
  }
  Kloset.tabla({ onSelect: (id) => cargar(id) });
  document.querySelectorAll('#filtros-postventa .kl-filter').forEach((btn) => btn.addEventListener('click', () => {
    document.querySelectorAll('#filtros-postventa .kl-filter').forEach((otro) => {
      if (btn.dataset.filtro !== undefined && otro.dataset.filtro !== undefined) otro.classList.toggle('active', otro === btn);
      if (btn.dataset.filtroTipo !== undefined && otro.dataset.filtroTipo !== undefined) otro.classList.toggle('active', otro === btn);
    });
    aplicarFiltros();
  }));
  ['post-buscar', 'post-desde', 'post-hasta'].forEach((id) => document.getElementById(id)?.addEventListener('input', aplicarFiltros));
  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => btn.addEventListener('click', () => Kloset.cerrarDetalle('detalle-postventa')));
  document.getElementById('post-guardar').addEventListener('click', async () => {
    error.hidden = true;
    if (estado.value === 'rechazada' && comentario.value.trim().length < 10) {
      error.textContent = 'Indica obligatoriamente el motivo del rechazo'; error.hidden = false; return;
    }
    const reemplazosSeleccionados = {};
    document.querySelectorAll('.post-reemplazo').forEach((select) => { reemplazosSeleccionados[select.dataset.item] = select.value; });
    const res = await Kloset.ajax('Postventa', 'cambiarEstado', { id: actual.id_solicitud_postventa, estado: estado.value, comentario: comentario.value, motivo_rechazo: comentario.value, codigo_confirmacion: codigoConfirmacion.value.trim(), reemplazos: JSON.stringify(reemplazosSeleccionados) });
    if (res.status !== 'success') { error.textContent = res.message || 'No se pudo actualizar'; error.hidden = false; return; }
    Kloset.toast('Solicitud actualizada', 'ok');
    window.location.reload();
  });
  cambiarSolucion.addEventListener('click', async () => {
    const motivo = window.prompt('Motivo del cambio de solución a reembolso:') || '';
    if (motivo.trim().length < 10) return Kloset.toast('Indica un motivo de al menos 10 caracteres', 'error');
    const res = await Kloset.ajax('Postventa', 'cambiarSolucion', { id: actual.id_solicitud_postventa, motivo: motivo.trim() });
    if (res.status !== 'success') return Kloset.toast(res.message || 'No se pudo cambiar la solución', 'error');
    Kloset.toast(res.message, 'ok'); window.location.reload();
  });
})();
