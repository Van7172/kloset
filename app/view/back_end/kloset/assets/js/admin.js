/* Kloset · utilidades compartidas del panel */
window.Kloset = (function () {
  const AJAX_URL = URL_ADMIN + 'ajax.php';

  /* ---------------------------------------------------------------- AJAX */

  async function ajax(clase, metodo, datos) {
    const body = datos instanceof FormData ? datos : new FormData();
    if (!(datos instanceof FormData) && datos) {
      Object.keys(datos).forEach((k) => body.append(k, datos[k]));
    }
    body.set('class', clase);
    body.set('method', metodo);

    const res = await fetch(AJAX_URL, { method: 'POST', body: body, credentials: 'same-origin' });

    if (res.status === 401) {
      toast('Tu sesión expiró · vuelve a entrar', 'error');
      setTimeout(() => (window.location.href = URL_ADMIN + 'sign-in'), 1500);
      return { status: 'error', message: 'Sesión expirada' };
    }

    try {
      return await res.json();
    } catch (e) {
      return { status: 'error', message: 'Respuesta inválida del servidor' };
    }
  }

  /* --------------------------------------------------------------- Avisos */

  function toast(mensaje, tipo) {
    let stack = document.getElementById('kl-toasts');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'kl-toasts';
      stack.className = 'kl-toasts';
      document.body.appendChild(stack);
    }
    const el = document.createElement('div');
    el.className = 'kl-toast' + (tipo === 'error' ? ' kl-toast--error' : '');
    el.textContent = mensaje;
    stack.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  }

  function showError(id, mensaje) {
    const box = document.getElementById(id);
    if (!box) return;
    box.textContent = mensaje || '';
    box.hidden = !mensaje;
  }

  /* ----------------------------------------------------------- Panel tema */

  function setTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem('kloset-theme', t); } catch (e) {}
  }

  document.getElementById('btn-theme')?.addEventListener('click', () => {
    const actual = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    setTheme(actual === 'dark' ? 'light' : 'dark');
  });

  /* -------------------------------------------------------- Panel detalle */

  function abrirDetalle(id) {
    const panel = document.getElementById(id);
    if (panel) panel.hidden = false;
  }

  function cerrarDetalle(id) {
    const panel = document.getElementById(id);
    if (panel) panel.hidden = true;
    document.querySelectorAll('.kl-grid-row.active').forEach((r) => r.classList.remove('active'));
  }

  function marcarFila(id) {
    document.querySelectorAll('.kl-grid-row').forEach((r) => {
      r.classList.toggle('active', r.dataset.id === String(id));
    });
  }

  /* ---------------------------------------------------------------- Tabla
     Conecta buscador, filtros de estado/categoría, contador y selección
     de fila con el panel de detalle. */

  function tabla(opciones) {
    const filas = Array.from(document.querySelectorAll('.kl-grid-row'));
    const contador = document.getElementById('kl-rowcount');
    const buscador = document.getElementById('kl-search');
    const filtros = document.getElementById('filtros-estado');
    const selCategoria = document.getElementById('filtro-categoria');

    let texto = '';
    let estado = '';
    let categoria = '';

    function aplicar() {
      let visibles = 0;
      filas.forEach((fila) => {
        const ok =
          (texto === '' || (fila.dataset.buscar || '').includes(texto)) &&
          (estado === '' || fila.dataset.estado === estado) &&
          (categoria === '' || fila.dataset.categoria === categoria);
        fila.hidden = !ok;
        if (ok) visibles++;
      });
      if (contador) contador.textContent = visibles;
    }

    buscador?.addEventListener('input', () => {
      texto = buscador.value.trim().toLowerCase();
      aplicar();
    });

    filtros?.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.kl-filter');
      if (!btn) return;
      filtros.querySelectorAll('.kl-filter').forEach((b) => b.classList.toggle('active', b === btn));
      estado = btn.dataset.filtro || '';
      aplicar();
    });

    selCategoria?.addEventListener('change', () => {
      categoria = selCategoria.value;
      aplicar();
    });

    if (opciones && opciones.onSelect) {
      filas.forEach((fila) => {
        const abrir = () => opciones.onSelect(fila.dataset.id, fila);
        fila.addEventListener('click', abrir);
        fila.addEventListener('keydown', (ev) => {
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            abrir();
          }
        });
      });
    }

    return { aplicar };
  }

  /* -------------------------------------------------------------- Varios */

  function slug(texto) {
    return texto
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function recargar() {
    window.location.reload();
  }

  return { ajax, toast, showError, setTheme, abrirDetalle, cerrarDetalle, marcarFila, tabla, slug, recargar };
})();
