const { chromium } = require('C:/Users/ADMIN/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert = require('node:assert/strict');
const root = 'http://127.0.0.1/kloset/dw-panel/';

async function login(page, email, password) {
  await page.goto(root);
  await page.locator('#login-correo').fill(email);
  await page.locator('#login-pass').fill(password);
  await page.getByRole('button', { name: 'Entrar' }).click();
}

async function main() {
  const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  try {
    const admin = await browser.newPage();
    await login(admin, process.env.QA_ADMIN_EMAIL, process.env.QA_ADMIN_PASS);
    await admin.waitForURL('**/dashboard');
    await admin.goto(root + 'usuarios');
    await admin.getByRole('heading', { name: 'Usuarios' }).waitFor();
    await admin.locator('#kl-primary').click();
    assert.deepEqual(await admin.locator('#usr-rol option').allTextContents(), ['Selecciona…', 'Asesor', 'Cliente']);
    console.log('OK Formulario: solo Asesor y Cliente');

    await admin.locator('#usr-nombre').fill('Asesor QA navegador');
    await admin.locator('#usr-correo').fill(process.env.QA_ADVISOR_EMAIL);
    await admin.locator('#usr-password').fill(process.env.QA_USER_PASS);
    await admin.locator('#usr-rol').selectOption({ label: 'Asesor' });
    assert.equal(await admin.locator('#usr-alta-perms').isVisible(), true);
    assert.equal(await admin.locator('#usr-alta-perms input[type=checkbox]:checked').count() > 0, true);
    await admin.locator('#usr-guardar').click();
    await admin.locator('.kl-grid-row').filter({ hasText: process.env.QA_ADVISOR_EMAIL }).waitFor();
    console.log('OK Alta de asesor desde navegador');

    await admin.locator('#kl-primary').click();
    await admin.locator('#usr-nombre').fill('Cliente QA navegador');
    await admin.locator('#usr-correo').fill(process.env.QA_CLIENT_EMAIL);
    await admin.locator('#usr-password').fill(process.env.QA_USER_PASS);
    await admin.locator('#usr-rol').selectOption({ label: 'Cliente' });
    assert.equal(await admin.locator('#usr-alta-perms').isVisible(), false);
    await admin.locator('#usr-guardar').click();
    await admin.locator('.kl-grid-row').filter({ hasText: process.env.QA_CLIENT_EMAIL }).waitFor();
    console.log('OK Alta de cliente desde navegador');

    const advisor = await browser.newPage();
    await login(advisor, process.env.QA_ADVISOR_EMAIL, process.env.QA_USER_PASS);
    await advisor.waitForURL('**/dashboard');
    assert.equal(await advisor.locator('a[href$="pedidos"]').count() > 0, true);
    assert.equal(await advisor.locator('a[href$="usuarios"]').count(), 0);
    await advisor.goto(root + 'usuarios');
    await advisor.getByText('No encontrado').first().waitFor();
    console.log('OK Asesor entra y no accede a Usuarios');

    const client = await browser.newPage();
    await login(client, process.env.QA_CLIENT_EMAIL, process.env.QA_USER_PASS);
    await client.getByText('Esta cuenta no tiene acceso al panel').waitFor();
    console.log('OK Cliente no entra al panel');
  } finally {
    await browser.close();
  }
}
main().catch((error) => { console.error(error.message); process.exitCode = 1; });
