/* Kloset · utilidades compartidas del panel */
window.Kloset = (function () {
  const AJAX_URL = URL_ADMIN + 'ajax.php';
  const feedback = document.getElementById('kl-feedback');
  let feedbackResolve = null;
  let feedbackMode = '';
  let previousFocus = null;
  let pendingRequests = 0;
  let loadingTimer = null;
  let lastNotice = '';

  function closeFeedback(result = false) {
    if (!feedback || feedback.hidden) return;
    feedback.hidden = true;
    feedback.classList.remove('kl-feedback--error', 'kl-feedback--confirm');
    if (!document.querySelector('.kl-modal:not([hidden])')) document.body.classList.remove('kl-no-scroll');
    const resolve = feedbackResolve;
    feedbackResolve = null;
    feedbackMode = '';
    resolve?.(result);
    previousFocus?.focus?.();
    previousFocus = null;
  }

  function openFeedback({ mode, title, message, action = 'Aceptar', cancel = 'Cancelar', danger = false }) {
    if (!feedback) return mode === 'confirm' ? Promise.resolve(false) : undefined;
    if (!feedback.hidden) closeFeedback(false);
    feedbackMode = mode;
    previousFocus = document.activeElement;
    feedback.classList.toggle('kl-feedback--error', mode === 'error');
    feedback.classList.toggle('kl-feedback--confirm', mode === 'confirm');
    document.getElementById('kl-feedback-kicker').textContent = mode === 'loading' ? 'Un momento' : mode === 'error' ? 'No se completó la acción' : mode === 'confirm' ? 'Confirmar acción' : 'Aviso';
    document.getElementById('kl-feedback-title').textContent = title;
    document.getElementById('kl-feedback-message').textContent = message || '';
    document.getElementById('kl-feedback-spinner').hidden = mode !== 'loading';
    document.getElementById('kl-feedback-actions').hidden = mode === 'loading';
    const cancelButton = document.getElementById('kl-feedback-cancelar');
    const acceptButton = document.getElementById('kl-feedback-aceptar');
    cancelButton.hidden = mode !== 'confirm';
    cancelButton.textContent = cancel;
    acceptButton.textContent = action;
    acceptButton.classList.toggle('kl-btn--danger', danger);
    feedback.setAttribute('role', mode === 'error' || mode === 'confirm' ? 'alertdialog' : 'dialog');
    feedback.hidden = false;
    document.body.classList.add('kl-no-scroll');
    (mode === 'loading' ? feedback.querySelector('.kl-modal-box') : mode === 'confirm' ? cancelButton : acceptButton).focus();
    if (mode === 'loading') return;
    return new Promise((resolve) => { feedbackResolve = resolve; });
  }

  feedback?.addEventListener('click', (ev) => {
    if (ev.target.id === 'kl-feedback-aceptar') closeFeedback(true);
    else if (ev.target.id === 'kl-feedback-cancelar' || ev.target === feedback) {
      if (feedbackMode !== 'loading') closeFeedback(false);
    }
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && feedback && !feedback.hidden && feedbackMode !== 'loading') closeFeedback(false);
    if (ev.key === 'Tab' && feedback && !feedback.hidden) {
      const controls = [...feedback.querySelectorAll('button:not([hidden]):not([disabled])')];
      const targets = controls.length ? controls : [feedback.querySelector('.kl-modal-box')];
      const first = targets[0];
      const last = targets[targets.length - 1];
      if (ev.shiftKey && (document.activeElement === first || !feedback.contains(document.activeElement))) { ev.preventDefault(); last.focus(); }
      else if (!ev.shiftKey && (document.activeElement === last || !feedback.contains(document.activeElement))) { ev.preventDefault(); first.focus(); }
    }
  });

  function confirmar({ titulo, mensaje, aceptar = 'Confirmar', cancelar = 'Cancelar', peligro = false }) {
    return openFeedback({ mode: 'confirm', title: titulo, message: mensaje, action: aceptar, cancel: cancelar, danger: peligro });
  }

  function informar(titulo, mensaje, tipo = 'info') {
    return openFeedback({ mode: tipo === 'error' ? 'error' : 'info', title: titulo, message: mensaje });
  }

  function beginRequest(metodo) {
    pendingRequests++;
    if (pendingRequests !== 1) return;
    loadingTimer = setTimeout(() => {
      if (pendingRequests && feedback?.hidden) {
        const lectura = /^(get|listar|galeria)/i.test(metodo);
        openFeedback({ mode: 'loading', title: lectura ? 'Cargando información…' : 'Procesando cambios…', message: 'Espera mientras completamos la acción.' });
      }
    }, 180);
  }

  function endRequest() {
    pendingRequests = Math.max(0, pendingRequests - 1);
    if (pendingRequests) return;
    clearTimeout(loadingTimer);
    loadingTimer = null;
    if (feedbackMode === 'loading') closeFeedback();
  }

  /* ---------------------------------------------------------------- AJAX */

  async function ajax(clase, metodo, datos) {
    const body = datos instanceof FormData ? datos : new FormData();
    if (!(datos instanceof FormData) && datos) {
      Object.keys(datos).forEach((k) => body.append(k, datos[k]));
    }
    body.set('class', clase);
    body.set('method', metodo);
    body.set('csrf', CSRF_PANEL);

    let res;
    let payload;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 120000);
    beginRequest(metodo);
    try {
      res = await fetch(AJAX_URL, { method: 'POST', body: body, credentials: 'same-origin', signal: controller.signal });
      try { payload = await res.json(); }
      catch { payload = { status: 'error', message: 'Respuesta inválida del servidor' }; }
    } catch (e) {
      payload = { status: 'error', message: e?.name === 'AbortError' ? 'La solicitud tardó demasiado. Inténtalo de nuevo.' : 'No hay conexión con el panel' };
    } finally {
      clearTimeout(timeout);
      endRequest();
    }

    if (res?.status === 401) {
      toast('Tu sesión expiró · vuelve a entrar', 'error');
      setTimeout(() => (window.location.href = URL_ADMIN + 'sign-in'), 1500);
      return { status: 'error', message: 'Sesión expirada' };
    }
    return payload && typeof payload === 'object' ? payload : { status: 'error', message: 'Respuesta inválida del servidor' };
  }

  /* --------------------------------------------------------------- Avisos */

  function toast(mensaje, tipo) {
    if (tipo === 'error') return informar('Revisa la acción', mensaje || 'No se pudo completar la acción', 'error');
    lastNotice = mensaje;
    let stack = document.getElementById('kl-toasts');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'kl-toasts';
      stack.className = 'kl-toasts';
      stack.setAttribute('role', 'status');
      stack.setAttribute('aria-live', 'polite');
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

  function reportarError(id, mensaje) {
    showError(id, mensaje);
    informar('No se pudo completar', mensaje, 'error');
  }

  try {
    const flash = sessionStorage.getItem('kloset-panel-aviso');
    if (flash) { sessionStorage.removeItem('kloset-panel-aviso'); toast(flash); }
  } catch (e) { /* El panel funciona aunque el almacenamiento esté deshabilitado. */ }
  document.querySelector('.kl-login form')?.addEventListener('submit', () => {
    openFeedback({ mode: 'loading', title: 'Iniciando sesión…', message: 'Verificando el acceso al panel.' });
  });
  const loginError = document.querySelector('.kl-login .kl-error');
  if (loginError) informar('No se pudo iniciar sesión', loginError.textContent.trim(), 'error');
  document.querySelector('a[href$="/sign-out"]')?.addEventListener('click', () => {
    openFeedback({ mode: 'loading', title: 'Cerrando sesión…', message: 'Espera un momento.' });
  });

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

  /* ------------------------------------------------- Confirmación de borrado */

  async function confirmarEliminar(opciones) {
    const aceptado = await confirmar({ titulo: opciones.titulo, mensaje: opciones.texto, aceptar: 'Eliminar', peligro: true });
    if (!aceptado) return;
    try { await opciones.onConfirmar(); }
    catch { informar('No se pudo eliminar', 'Ocurrió un error inesperado. Vuelve a intentarlo.', 'error'); }
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
          if (ev.target !== fila) return;
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
    try { if (lastNotice) sessionStorage.setItem('kloset-panel-aviso', lastNotice); } catch (e) { /* Sin almacenamiento, recargamos igual. */ }
    window.location.reload();
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
  }

  return { ajax, toast, showError, reportarError, confirmar, informar, setTheme, abrirDetalle, cerrarDetalle, marcarFila, confirmarEliminar, tabla, slug, recargar, escapeHtml };
})();
