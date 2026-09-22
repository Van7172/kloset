(function () {
  const PANEL = 'detalle-pago';
  const panel = document.getElementById(PANEL);
  if (!panel) return;

  const titulo = document.getElementById('pago-titulo');
  const resumen = document.getElementById('pago-resumen');

  function kv(clave, valor, clase) {
    return (
      '<dl class="kl-kv" style="margin:0;"><dt>' + clave + '</dt>' +
      '<dd class="' + (clase || '') + '">' + valor + '</dd></dl>'
    );
  }

  function pintar(p) {
    titulo.textContent = 'Pago #' + p.id_pedido;
    const estadoClase = p.estado_pago === 'aprobado' ? 'kl-fg-ok' : p.estado_pago === 'rechazado' ? 'kl-fg-red' : 'kl-fg-warn';
    resumen.innerHTML =
      kv('cliente', p.cliente) +
      kv('correo', p.correo) +
      kv('monto_pago', 'S/ ' + Number(p.monto_pago).toFixed(2)) +
      kv('metodo_pago', p.metodo_pago + (p.marca_pago ? ' · ' + p.marca_pago + (p.ultimos_digitos_pago ? ' ····' + p.ultimos_digitos_pago : '') : '')) +
      kv('estado_pago', p.estado_pago, estadoClase) +
      kv('id_transaccion', p.id_transaccion_pasarela_pago || '—') +
      kv('estado_pedido', p.estado_pedido.replace(/_/g, ' '));

    Kloset.marcarFila(p.id_pago);
    Kloset.abrirDetalle(PANEL);
  }

  Kloset.tabla({
    onSelect: async (id) => {
      const res = await Kloset.ajax('Pagos', 'getById', { id: id });
      if (res.status !== 'success') {
        Kloset.toast(res.message || 'No se pudo cargar el pago', 'error');
        return;
      }
      pintar(res.pago);
    },
  });

  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });
})();
