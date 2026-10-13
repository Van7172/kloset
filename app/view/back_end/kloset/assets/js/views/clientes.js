(function () {
  const PANEL = 'detalle-cliente';
  const panel = document.getElementById(PANEL);
  if (!panel) return;

  const titulo = document.getElementById('cli-titulo');
  const kicker = document.getElementById('cli-kicker');
  const resumen = document.getElementById('cli-resumen');
  const pedidosWrap = document.getElementById('cli-pedidos-wrap');
  const pedidosBox = document.getElementById('cli-pedidos');

  const money = (n) => 'S/ ' + Number(n).toFixed(2);
  const fecha = (f) => (f || '').replace(' ', ' · ').slice(0, 16);

  function kv(clave, valor, clase) {
    return (
      '<dl class="kl-kv" style="margin:0;"><dt>' + clave + '</dt>' +
      '<dd class="' + (clase || '') + '">' + Kloset.escapeHtml(valor) + '</dd></dl>'
    );
  }

  function pintar(datos) {
    const c = datos.cliente;
    kicker.textContent = 'Cliente #' + c.id_usuario_sistema;
    titulo.textContent = c.nombre_usuario_sistema;

    let html =
      kv('Correo', c.correo_usuario_sistema) + kv('Teléfono', c.telefono_usuario_sistema || '—') +
      kv('Registrado', c.fecha_creacion) + kv('Total comprado', money(datos.total_comprado));

    if (datos.perfil) {
      const p = datos.perfil;
      html +=
        kv('estatura · pecho · cintura · cadera', p.estatura_perfil_corporal + ' · ' + p.pecho_perfil_corporal + ' · ' + p.cintura_perfil_corporal + ' · ' + p.cadera_perfil_corporal) +
        kv('Perfil', datos.perfil_completo ? 'Completo' : 'Incompleto') +
        kv('Talla recomendada', p.talla_recomendada_perfil_corporal || '—');
    } else {
      html += kv('perfil_corporal', 'Sin registrar', 'kl-fg-soft');
    }
    html += kv('Avatar asociado', datos.avatar || 'No registrado', datos.avatar ? '' : 'kl-fg-soft');

    resumen.innerHTML = html;

    if (datos.pedidos && datos.pedidos.length) {
      pedidosBox.innerHTML = datos.pedidos
        .map((p) => (
          '<div class="kl-listrow"><div><b>#' + p.id_pedido + ' · ' + p.estado_pedido.replace(/_/g, ' ') + '</b>' +
          '<small>' + fecha(p.fecha_creacion) + '</small></div><strong>' + money(p.total_pedido) + '</strong></div>'
        ))
        .join('');
      pedidosWrap.hidden = false;
    } else {
      pedidosWrap.hidden = true;
    }

    Kloset.marcarFila(c.id_usuario_sistema);
    Kloset.abrirDetalle(PANEL);
  }

  Kloset.tabla({
    onSelect: async (id) => {
      const res = await Kloset.ajax('Clientes', 'getById', { id: id });
      if (res.status !== 'success') {
        Kloset.toast(res.message || 'No se pudo cargar el cliente', 'error');
        return;
      }
      pintar(res);
    },
  });

  document.querySelectorAll('[data-cerrar-detalle]').forEach((btn) => {
    btn.addEventListener('click', () => Kloset.cerrarDetalle(PANEL));
  });
})();
