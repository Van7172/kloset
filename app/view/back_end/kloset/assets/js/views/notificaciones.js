(function () {
  const PANEL = 'detalle-notificacion';
  const panel = document.getElementById(PANEL);
  if (!panel) return;

  const titulo = document.getElementById('not-titulo');
  const resumen = document.getElementById('not-resumen');
  const btnMarcar = document.getElementById('not-marcar');
  let actual = null;

  function kv(clave, valor, clase) {
    return (
      '<dl class="kl-kv" style="margin:0;"><dt>' + clave + '</dt>' +
      '<dd class="' + (clase || '') + '">' + valor + '</dd></dl>'
    );
  }

  function pintar(fila) {
    actual = fila;
    const leido = fila.dataset.leido === '1';
    titulo.textContent = fila.dataset.cliente;
    resumen.innerHTML =
      kv('mensaje', fila.dataset.mensaje) +
      kv('id_pedido', '#' + fila.dataset.pedido) +
      kv('fecha_creacion', fila.dataset.fecha) +
      kv('leido_notificacion', leido ? 'true' : 'false', leido ? 'kl-fg-soft' : 'kl-fg-red');
    btnMarcar.hidden = leido;

    Kloset.marcarFila(fila.dataset.id);
    Kloset.abrirDetalle(PANEL);
  }

  Kloset.tabla({ onSelect: (id, fila) => pintar(fila) });

  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });

  btnMarcar.addEventListener('click', async () => {
    if (!actual) return;
    const res = await Kloset.ajax('Notificaciones', 'marcarLeida', { id: actual.dataset.id });
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo actualizar', 'error');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });

  document.getElementById('kl-primary')?.addEventListener('click', async () => {
    const res = await Kloset.ajax('Notificaciones', 'marcarTodasLeidas', {});
    if (res.status !== 'success') {
      Kloset.toast(res.message || 'No se pudo actualizar', 'error');
      return;
    }
    Kloset.toast(res.message);
    Kloset.recargar();
  });
})();
