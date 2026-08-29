(function () {
  const form = document.getElementById('form-config');
  if (!form) return;

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const res = await Kloset.ajax('Configuracion', 'saveAll', new FormData(form));
    Kloset.toast(res.message || 'No se pudo guardar', res.status === 'success' ? 'ok' : 'error');
  });
})();
