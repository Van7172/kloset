const { chromium } = require('C:/Users/ADMIN/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

const root = 'http://127.0.0.1/kloset';
const errors = [];
let browser;

function watch(page, label) {
  page.on('pageerror', (error) => errors.push(`${label}: ${error.message}`));
  page.on('response', (response) => {
    if (response.status() >= 500 && response.url().startsWith(root)) errors.push(`${label}: HTTP ${response.status()} ${response.url()}`);
    if (response.status() === 404 && ['image', 'script', 'stylesheet'].includes(response.request().resourceType()) && response.url().startsWith(root)) {
      errors.push(`${label}: recurso ausente ${response.url()}`);
    }
  });
}

async function step(label, action, page) {
  try {
    await action();
    console.log(`OK ${label}`);
  } catch (error) {
    if (page) {
      const file = path.join(process.env.TEMP || 'C:/Windows/Temp', `kloset-browser-${Date.now()}.png`);
      await page.screenshot({ path: file, fullPage: true }).catch(() => undefined);
      console.error(`Captura de fallo: ${file}`);
    }
    throw new Error(`${label}: ${error.message}`);
  }
}

async function main() {
  let deliveryOrderId;
  browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  const publicContext = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-PE', timezoneId: 'America/Lima' });
  const publicPage = await publicContext.newPage();
  watch(publicPage, 'público');

  await step('Inicio y catálogo públicos', async () => {
    await publicPage.goto(`${root}/`, { waitUntil: 'networkidle' });
    assert.match(await publicPage.title(), /Kloset/);
    await publicPage.goto(`${root}/catalogo`, { waitUntil: 'networkidle' });
    assert.ok((await publicPage.locator('a[href*="/producto/"]').count()) > 0);
  }, publicPage);
  await step('Detalle público con talla, color y SKU', async () => {
    await publicPage.goto(`${root}/producto/malla-vector-long`, { waitUntil: 'networkidle' });
    await publicPage.getByRole('heading', { name: /Malla Vector Long/i }).waitFor();
    await publicPage.getByRole('button', { name: /^M$/ }).click();
    assert.ok(await publicPage.getByRole('button', { name: /Añadir a la bolsa/i }).isEnabled());
    assert.match(await publicPage.locator('body').innerText(), /SKU/i);
  }, publicPage);
  await publicContext.close();

  const clientContext = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-PE', timezoneId: 'America/Lima' });
  const clientPage = await clientContext.newPage();
  watch(clientPage, 'cliente');
  await step('Acceso de cliente', async () => {
    await clientPage.goto(`${root}/entrar`, { waitUntil: 'networkidle' });
    await clientPage.locator('input[type=email]').fill(process.env.KLOSET_BROWSER_CLIENT_EMAIL);
    await clientPage.locator('#auth-password').fill(process.env.KLOSET_BROWSER_CLIENT_PASSWORD);
    await clientPage.locator('form button[type=submit]').click();
    await clientPage.waitForURL((url) => !url.pathname.endsWith('/entrar'));
    assert.ok(await clientPage.evaluate(() => Boolean(localStorage.getItem('kloset_token'))));
    await clientPage.goto(`${root}/dw-panel/`, { waitUntil: 'networkidle' });
    assert.ok(await clientPage.locator('#login-correo').count(), 'El cliente entró al panel administrativo');
  }, clientPage);
  await step('Bolsa con variante y checkout rechazado', async () => {
    await clientPage.goto(`${root}/producto/malla-vector-long`, { waitUntil: 'networkidle' });
    await clientPage.getByRole('button', { name: /^M$/ }).click();
    await clientPage.getByRole('button', { name: /Añadir a la bolsa/i }).click();
    await clientPage.goto(`${root}/bolsa`, { waitUntil: 'networkidle' });
    assert.match(await clientPage.locator('body').innerText(), /SKU/);
    await clientPage.getByRole('button', { name: /Ir al pago de prueba/i }).click();
    await clientPage.locator('#envio-direccion').fill('Av. Prueba Navegador 123');
    await clientPage.locator('#envio-ciudad').fill('Miraflores');
    await clientPage.locator('#pago-holder').fill('QA Navegador');
    await clientPage.locator('#pago-num').fill('4000000000000002');
    await clientPage.locator('#pago-exp').fill('1230');
    await clientPage.locator('#pago-cvc').fill('123');
    await clientPage.locator('input[type=checkbox]').check();
    await clientPage.getByRole('button', { name: 'Confirmar pago' }).click();
    await clientPage.getByText(/rechazad/i).first().waitFor({ timeout: 8000 });
  }, clientPage);
  await step('Pago aprobado y cancelación desde la cuenta', async () => {
    await clientPage.getByRole('button', { name: 'Probar otra tarjeta' }).click();
    await clientPage.locator('#pago-holder').fill('QA Navegador');
    await clientPage.locator('#pago-num').fill('4242424242424242');
    await clientPage.locator('#pago-exp').fill('1230');
    await clientPage.locator('#pago-cvc').fill('123');
    await clientPage.getByRole('button', { name: 'Confirmar pago' }).click();
    await clientPage.getByRole('heading', { name: /Pago confirmado/i }).waitFor({ timeout: 10000 });
    await clientPage.getByRole('link', { name: 'Ver mis pedidos' }).click();
    await clientPage.locator('a[href*="/pedidos/"]').first().click();
    clientPage.once('dialog', (dialog) => dialog.accept());
    await clientPage.getByRole('button', { name: 'Cancelar mi pedido' }).click();
    await clientPage.getByText(/Este pedido fue cancelado/i).waitFor();
  }, clientPage);
  await step('Segundo pedido para seguimiento de entrega', async () => {
    await clientPage.goto(`${root}/producto/malla-vector-long`, { waitUntil: 'networkidle' });
    await clientPage.getByRole('button', { name: /Añadir a la bolsa/i }).click();
    await clientPage.goto(`${root}/bolsa`, { waitUntil: 'networkidle' });
    await clientPage.getByRole('button', { name: /Ir al pago de prueba/i }).click();
    await clientPage.getByText('Av. Prueba Navegador 123').waitFor();
    await clientPage.locator('#pago-holder').fill('QA Navegador');
    await clientPage.locator('#pago-num').fill('4242424242424242');
    await clientPage.locator('#pago-exp').fill('1230');
    await clientPage.locator('#pago-cvc').fill('123');
    await clientPage.locator('input[type=checkbox]').check();
    await clientPage.getByRole('button', { name: 'Confirmar pago' }).click();
    await clientPage.getByRole('heading', { name: /Pago confirmado/i }).waitFor({ timeout: 10000 });
    await clientPage.getByRole('link', { name: 'Ver mis pedidos' }).click();
    await clientPage.locator('a[href*="/pedidos/"]').first().click();
    deliveryOrderId = Number(clientPage.url().match(/\/pedidos\/(\d+)/)?.[1]);
    assert.ok(deliveryOrderId > 0);
  }, clientPage);

  const adminContext = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-PE' });
  const adminPage = await adminContext.newPage();
  watch(adminPage, 'panel');
  await step('Credenciales incorrectas y cuenta de cliente bloqueadas en el panel', async () => {
    await adminPage.goto(`${root}/dw-panel/`, { waitUntil: 'networkidle' });
    await adminPage.locator('#login-correo').fill(process.env.KLOSET_BROWSER_ADMIN_EMAIL);
    await adminPage.locator('#login-pass').fill('ClaveIncorrecta!123');
    await adminPage.locator('form button[type=submit]').click();
    await adminPage.getByText('El usuario o contraseña son incorrectos').waitFor();
    assert.equal(await adminPage.locator('.kl-shell').count(), 0);
    await adminPage.locator('#login-correo').fill(process.env.KLOSET_BROWSER_CLIENT_EMAIL);
    await adminPage.locator('#login-pass').fill(process.env.KLOSET_BROWSER_CLIENT_PASSWORD);
    await adminPage.locator('form button[type=submit]').click();
    await adminPage.getByText('Esta cuenta no tiene acceso al panel').waitFor();
    assert.equal(await adminPage.locator('.kl-shell').count(), 0);
  }, adminPage);
  await step('Login, logout y nuevo login administrativo', async () => {
    await adminPage.locator('#login-correo').fill(process.env.KLOSET_BROWSER_ADMIN_EMAIL);
    await adminPage.locator('#login-pass').fill(process.env.KLOSET_BROWSER_ADMIN_PASSWORD);
    await adminPage.locator('form button[type=submit]').click();
    await adminPage.getByText('Panel general').first().waitFor({ timeout: 10000 });
    const oldCsrf = await adminPage.evaluate(() => CSRF_PANEL);
    const oldSession = (await adminContext.cookies(root)).find((cookie) => cookie.name === 'PHPSESSID')?.value;
    assert.ok(oldSession);
    await adminPage.locator('a[href$="/sign-out"]').click();
    await adminPage.locator('#login-correo').waitFor();
    const newSession = (await adminContext.cookies(root)).find((cookie) => cookie.name === 'PHPSESSID')?.value;
    assert.notEqual(newSession, oldSession, 'La cookie de sesión anterior sobrevivió al logout');
    await adminPage.goto(`${root}/dw-panel/productos`, { waitUntil: 'networkidle' });
    assert.ok(await adminPage.locator('#login-correo').count(), 'La sección protegida siguió visible tras salir');
    const stale = await adminPage.request.post(`${root}/dw-panel/ajax.php`, {
      form: { class: 'Productos', method: 'getById', id: '1', csrf: oldCsrf },
    });
    assert.equal(stale.status(), 401);
    await adminPage.locator('#login-correo').fill(process.env.KLOSET_BROWSER_ADMIN_EMAIL);
    await adminPage.locator('#login-pass').fill(process.env.KLOSET_BROWSER_ADMIN_PASSWORD);
    await adminPage.locator('form button[type=submit]').click();
    await adminPage.getByText('Panel general').first().waitFor({ timeout: 10000 });
    const links = await adminPage.locator('nav a').allTextContents();
    assert.ok(links.some((value) => /Usuarios/.test(value)));
    assert.ok(!links.some((value) => /Roles/.test(value)));
  }, adminPage);
  await step('Secciones y galería del panel', async () => {
    for (const section of ['categorias', 'productos', 'variantes', 'pedidos', 'pagos', 'notificaciones', 'clientes', 'usuarios', 'configuracion', 'mi-cuenta']) {
      await adminPage.goto(`${root}/dw-panel/${section}`, { waitUntil: 'networkidle' });
      assert.ok(await adminPage.locator('.kl-shell').count(), `Falta ${section}`);
    }
    await adminPage.goto(`${root}/dw-panel/variantes`, { waitUntil: 'networkidle' });
    await adminPage.locator('#color-abrir').click();
    await adminPage.locator('#color-producto').selectOption({ index: 1 });
    await adminPage.locator('#color-existente option').nth(1).waitFor({ state: 'attached' });
    await adminPage.locator('#color-existente').selectOption({ index: 1 });
    await adminPage.locator('#color-galeria img').first().waitFor();
    assert.ok(await adminPage.locator('#color-renombre').inputValue());
  }, adminPage);
  await step('Alta de producto, color, imagen y variante desde el panel', async () => {
    const slug = process.env.KLOSET_BROWSER_QA_SLUG;
    const name = `QA Browser ${slug.slice(-10)}`;
    const sku = `QA${slug.replace(/[^a-z0-9]/gi, '').slice(-14).toUpperCase()}1`;
    await adminPage.goto(`${root}/dw-panel/productos`, { waitUntil: 'networkidle' });
    await adminPage.locator('#prod-crear').click();
    await adminPage.locator('#prod-nombre').fill(name);
    await adminPage.locator('#prod-nombre').blur();
    await adminPage.locator('#prod-url').fill(slug);
    await adminPage.locator('#prod-categoria').selectOption({ index: 1 });
    await adminPage.locator('#prod-precio').fill('79.90');
    await adminPage.locator('#prod-descripcion').fill('Prueba temporal de navegador');
    await adminPage.locator('#prod-guardar').click();
    await adminPage.locator('.kl-grid-row').filter({ hasText: name }).waitFor();

    await adminPage.goto(`${root}/dw-panel/variantes`, { waitUntil: 'networkidle' });
    await adminPage.locator('#color-abrir').click();
    await adminPage.locator('#color-producto').selectOption({ label: name });
    await adminPage.locator('#color-nombre').fill('Smoke Blue');
    await adminPage.locator('#color-guardar').click();
    await adminPage.locator('#color-existente option').filter({ hasText: 'Smoke Blue' }).waitFor({ state: 'attached' });
    await adminPage.locator('#color-renombre').fill('  Smoke   Navy  ');
    await adminPage.locator('#color-actualizar').click();
    await adminPage.locator('#color-existente option').filter({ hasText: 'Smoke Navy' }).waitFor({ state: 'attached' });
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXYAAAAASUVORK5CYII=', 'base64');
    await adminPage.locator('#color-imagenes').setInputFiles({ name: 'qa.png', mimeType: 'image/png', buffer: png });
    await adminPage.locator('#color-subir').click();
    await adminPage.locator('#color-galeria img').first().waitFor();
    await adminPage.locator('#color-cerrar').click();

    await adminPage.locator('#var-crear').click();
    await adminPage.locator('#var-producto').selectOption({ label: name });
    await adminPage.locator('#var-color option').filter({ hasText: 'Smoke Navy' }).waitFor({ state: 'attached' });
    await adminPage.locator('#var-color').selectOption({ label: 'Smoke Navy' });
    await adminPage.locator('#var-talla').selectOption('M');
    await adminPage.locator('#var-corte').selectOption('Regular');
    await adminPage.locator('#var-sku').fill(sku);
    await adminPage.locator('#var-stock').fill('7');
    await adminPage.locator('#var-guardar').click();
    await adminPage.locator('.kl-grid-row').filter({ hasText: sku }).waitFor();

    await clientPage.goto(`${root}/producto/${slug}`, { waitUntil: 'networkidle' });
    await clientPage.getByRole('heading', { name }).waitFor();
    assert.match(await clientPage.locator('body').innerText(), /Smoke Navy/);
    assert.ok(await clientPage.getByRole('button', { name: /Añadir a la bolsa/i }).isEnabled());

    await adminPage.goto(`${root}/dw-panel/productos`, { waitUntil: 'networkidle' });
    const row = adminPage.locator('.kl-grid-row').filter({ hasText: name });
    await row.locator('[data-accion="eliminar"]').click();
    await adminPage.locator('#confirmar-producto [data-rol="ok"]').click();
    await row.waitFor({ state: 'detached' });
  }, adminPage);
  await step('Transiciones de pedido y código de entrega', async () => {
    const openOrder = async () => {
      await adminPage.goto(`${root}/dw-panel/pedidos`, { waitUntil: 'networkidle' });
      await adminPage.locator(`.kl-grid-row[data-id="${deliveryOrderId}"]`).click();
      await adminPage.locator('#ped-resumen').getByText(`#${deliveryOrderId}`).waitFor();
    };
    const save = async (state) => {
      await adminPage.locator(`#ped-estados [data-estado="${state}"]`).click();
      if (state === 'enviado') await adminPage.locator('#ped-transportista').fill('QA Reparto');
      await Promise.all([
        adminPage.waitForNavigation({ waitUntil: 'networkidle' }),
        adminPage.locator('#ped-guardar').click(),
      ]);
    };
    await openOrder(); await save('en_preparacion');
    await openOrder(); await save('enviado');
    await openOrder(); await save('en_reparto');
    const noOwner = await adminPage.request.get(`${root}/api/v1/pedido/codigo?id=${deliveryOrderId}`);
    assert.equal(noOwner.status(), 401);
    await clientPage.goto(`${root}/pedidos/${deliveryOrderId}`, { waitUntil: 'networkidle' });
    if (!(await clientPage.getByText('Código de entrega', { exact: true }).count())) {
      const file = path.join(process.env.TEMP || 'C:/Windows/Temp', 'kloset-client-delivery-debug.png');
      await clientPage.screenshot({ path: file, fullPage: true });
      throw new Error(`El cliente no muestra el código en ${clientPage.url()}. Captura: ${file}`);
    }
    const code = (await clientPage.locator('strong').filter({ hasText: /^\d{8}$/ }).first().textContent({ timeout: 5000 })).trim();
    assert.match(code, /^\d{8}$/);
    await openOrder();
    await adminPage.locator('#ped-estados [data-estado="entregado"]').click();
    await adminPage.locator('#ped-codigo').fill(code === '00000000' ? '11111111' : '00000000');
    await adminPage.locator('#ped-guardar').click();
    await adminPage.locator('#form-pedido-error').getByText(/no coincide/i).waitFor();
    await adminPage.locator('#ped-codigo').fill(code);
    await Promise.all([
      adminPage.waitForNavigation({ waitUntil: 'networkidle' }),
      adminPage.locator('#ped-guardar').click(),
    ]);
    await clientPage.reload({ waitUntil: 'networkidle' });
    await clientPage.getByText(/Entregado/i).first().waitFor();
    assert.ok(!(await clientPage.locator('body').innerText()).includes(code));
  }, adminPage);
  await step('Usuarios habilitado y Roles retirado', async () => {
    await adminPage.goto(`${root}/dw-panel/usuarios`, { waitUntil: 'networkidle' });
    assert.ok(await adminPage.locator('form#form-usuario').count());
    await adminPage.goto(`${root}/dw-panel/roles`, { waitUntil: 'networkidle' });
    assert.ok(!(await adminPage.locator('form#form-rol').count()));
  }, adminPage);
  await adminContext.close();

  const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, deviceScaleFactor: 1, locale: 'es-PE' });
  const mobilePage = await mobileContext.newPage();
  watch(mobilePage, 'móvil');
  await step('Vista móvil sin desborde horizontal', async () => {
    for (const route of ['/', '/catalogo', '/producto/malla-vector-long']) {
      await mobilePage.goto(`${root}${route}`, { waitUntil: 'networkidle' });
      assert.ok(await mobilePage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Desborde en ${route}`);
    }
  }, mobilePage);
  await mobileContext.close();

  assert.deepEqual(errors, []);
  console.log('OK navegador sin errores de JavaScript ni HTTP 5xx');
}

main().catch((error) => { console.error(error.stack || error); process.exitCode = 1; }).finally(async () => { if (browser) await browser.close(); });
