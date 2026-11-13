(function () {
  const PANEL = 'detalle-consulta';
  const panel = document.getElementById(PANEL);
  if (!panel) return;

  const titulo = document.getElementById('consulta-titulo');
  const resumen = document.getElementById('consulta-resumen');
  const btnMarcar = document.getElementById('consulta-marcar');
  const campoRespuesta = document.getElementById('consulta-respuesta');
  const desde = document.getElementById('consulta-desde');
  const hasta = document.getElementById('consulta-hasta');
  const tema = document.getElementById('consulta-tema');
  const estado = document.getElementById('consulta-estado');
  let actual = null;
  let focoAnterior = null;

  function escapar(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
  }

  function kv(clave, valor) {
    return (
      '<dl class="kl-kv" style="margin:0;"><dt>' + escapar(clave) + '</dt>' +
      '<dd style="white-space:pre-wrap">' + escapar(valor) + '</dd></dl>'
    );
  }

  function pintar(fila) {
    actual = fila;
    titulo.textContent = fila.dataset.nombre;
    const temaEtiqueta = { talla: 'Duda de talla', pedido: 'Mi pedido', otro: 'Otro tema' }[fila.dataset.tema] || 'Otro tema';
    const estadoEtiqueta = fila.dataset.estado === 'atendida' ? 'Atendida' : 'Pendiente';
    resumen.innerHTML =
      kv('Correo electrónico', fila.dataset.correo) +
      kv('Teléfono', fila.dataset.telefono || '—') +
      kv('Tipo de consulta', temaEtiqueta) +
      kv('Fecha de envío', fila.dataset.fecha) +
      kv('Estado de atención', estadoEtiqueta) +
      kv('Mensaje completo', fila.dataset.mensaje) +
      (fila.dataset.respuesta ? kv('Respuesta enviada', fila.dataset.respuesta) : '');
    btnMarcar.hidden = fila.dataset.estado === 'atendida';
    campoRespuesta.value = fila.dataset.respuesta || '';
    campoRespuesta.disabled = fila.dataset.estado === 'atendida';

    focoAnterior = document.activeElement;
    Kloset.marcarFila(fila.dataset.id);
    Kloset.abrirDetalle(PANEL);
    document.body.classList.add('kl-no-scroll');
    (fila.dataset.estado === 'atendida' ? panel.querySelector('[data-cerrar-detalle]') : campoRespuesta).focus();
  }

  const tabla = Kloset.tabla({
    onSelect: (_id, fila) => pintar(fila),
    filterRow: (fila) => {
      const fecha = (fila.dataset.fecha || '').slice(0, 10);
      return (!desde.value || fecha >= desde.value)
        && (!hasta.value || fecha <= hasta.value)
        && (!tema.value || fila.dataset.tema === tema.value)
        && (!estado.value || fila.dataset.estado === estado.value);
    },
  });

  [desde, hasta, tema, estado].forEach((control) => {
    control.addEventListener('change', () => tabla.aplicar());
  });

  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => cerrarModal());
  });

  function cerrarModal() {
    Kloset.cerrarDetalle(PANEL);
    document.body.classList.remove('kl-no-scroll');
    focoAnterior?.focus?.();
    focoAnterior = null;
  }

  panel.addEventListener('click', (ev) => {
    if (ev.target === panel) cerrarModal();
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && !panel.hidden) cerrarModal();
  });

  btnMarcar.addEventListener('click', async () => {
    if (!actual) return;
    const respuesta = campoRespuesta.value.trim();
    if (respuesta.length < 10) {
      Kloset.toast('Escribe una respuesta de al menos 10 caracteres antes de atender la consulta', 'error');
      campoRespuesta.focus();
      return;
    }
    btnMarcar.disabled = true;
    const res = await Kloset.ajax('Notificaciones', 'responderConsulta', { id: actual.dataset.id, respuesta });
    btnMarcar.disabled = false;
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo actualizar la consulta', 'error');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
