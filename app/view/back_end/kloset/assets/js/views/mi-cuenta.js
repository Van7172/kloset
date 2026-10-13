(function () {
  [['cuenta-datos','guardar'],['cuenta-clave','cambiarClave']].forEach(([id,metodo]) => {
    const form = document.getElementById(id);
    form?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const res = await Kloset.ajax('MiCuentaAdmin', metodo, new FormData(form));
      Kloset.toast(res.message || 'No se pudo guardar', res.status === 'success' ? 'ok' : 'error');
      if (res.status === 'success' && metodo === 'cambiarClave') form.reset();
    });
  });
})();
