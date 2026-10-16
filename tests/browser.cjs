// Local form regression checks. Endpoint responses are mocked; no live mail is sent.
// npm install --prefix .tools/browser --no-save playwright
// node tests/browser.cjs (uses installed Chrome; override TEST_BROWSER if needed)
const { chromium } = require('../.tools/browser/node_modules/playwright');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const fixture = JSON.parse(fs.readFileSync(path.join(__dirname, 'valid-enquiry.json'), 'utf8'));
const server = http.createServer((req, res) => {
  const pathname = new URL(req.url, 'http://localhost').pathname;
  const file = pathname === '/' ? 'index.html' : pathname.slice(1);
  if (!/^(index\.html|script\.js|styles\.css|assets\/[a-z0-9.-]+)$/.test(file)) {
    res.writeHead(404).end(); return;
  }
  const mime = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.webp': 'image/webp', '.png': 'image/png', '.ico': 'image/x-icon' };
  res.setHeader('Content-Type', mime[path.extname(file)] || 'application/octet-stream');
  fs.createReadStream(path.join(root, file)).pipe(res);
});

(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const browser = await chromium.launch({ channel: process.env.TEST_BROWSER || 'chrome', headless: true });
  let checks = 0;
  try {
    for (const [name, viewport] of [['desktop', { width: 1440, height: 1000 }], ['mobile', { width: 390, height: 844 }]]) {
      const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('https://fonts.googleapis.com/**', route => route.abort());
      await page.route('https://fonts.gstatic.com/**', route => route.abort());
      let count = 0, lastPayload, release;
      let behavior = async route => {
        await new Promise(resolve => { release = resolve; });
        await route.fulfill({ status: 502, contentType: 'application/json', body: JSON.stringify({ ok: false, code: 'EMAIL_SEND_FAILED', message: 'Email sending failed. Please contact SleepSync.' }) });
      };
      await page.route('https://thesleepsync.in/api/submit-enquiry.php', async route => {
        count += 1;
        lastPayload = route.request().postDataJSON();
        await behavior(route);
      });
      await page.goto(`http://127.0.0.1:${server.address().port}/#enquiry`);
      const step = n => page.locator(`.form-step[data-step="${n}"]`);
      const submit = page.locator('button[type="submit"]');
      const message = page.locator('#form-message');
      await page.locator('[data-progress="4"]').click();
      assert(await step(1).isVisible());
      await page.locator('#franchise-form').evaluate(form => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
      assert.equal(count, 0); checks += 2;
      for (let n = 1; n <= 4; n += 1) {
        const fields = await step(n).locator('input:not([type="checkbox"]):not([type="radio"]),textarea,select').evaluateAll(elements => elements.map(el => ({ name: el.name, tag: el.tagName })));
        for (const field of fields) {
          const control = page.locator(`[name="${field.name}"]`);
          if (field.tag === 'SELECT') await control.selectOption(fixture[field.name]);
          else await control.fill(String(fixture[field.name]));
        }
        if (n === 1) {
          await page.locator('#sameWhatsapp').check();
          assert.equal(await page.locator('#whatsapp').inputValue(), fixture.mobile);
        }
        if (n === 2) {
          await page.locator('[name="currentBusiness"][value="Furniture"]').uncheck();
          await step(n).locator('[data-next]').click();
          assert(await step(n).isVisible()); checks += 1;
          await page.locator('[name="currentBusiness"][value="Furniture"]').check();
          await page.locator('[name="currentBusiness"][value="Mattresses"]').check();
        }
        if (n < 4) await step(n).locator('[data-next]').click();
      }
      await page.locator('#consent').check();
      await submit.click();
      await page.waitForFunction(() => document.querySelector('#franchise-form').getAttribute('aria-busy') === 'true');
      assert(await submit.isDisabled());
      await page.locator('#franchise-form').evaluate(form => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
      await page.waitForTimeout(100);
      assert.equal(count, 1);
      assert.deepEqual(Object.keys(lastPayload).sort(), Object.keys(fixture).sort());
      assert.deepEqual(lastPayload.currentBusiness, fixture.currentBusiness);
      assert.equal(lastPayload.consent, true);
      release();
      await page.waitForFunction(() => !document.querySelector('#franchise-form').hasAttribute('aria-busy'));
      assert((await message.getAttribute('class')).includes('error'));
      assert.equal(await page.locator('#fullName').inputValue(), fixture.fullName); checks += 6;
      for (const [status, payload] of [[200, { ok: false, message: 'Rejected' }], [200, 'not JSON'], [429, { ok: false, message: 'Rate limited' }]]) {
        behavior = route => route.fulfill({ status, contentType: 'application/json', headers: { 'Retry-After': '120' }, body: typeof payload === 'string' ? payload : JSON.stringify(payload) });
        await submit.click();
        await page.waitForFunction(() => !document.querySelector('#franchise-form').hasAttribute('aria-busy'));
        assert((await message.getAttribute('class')).includes('error'));
        assert.equal(await page.locator('#fullName').inputValue(), fixture.fullName); checks += 2;
      }
      behavior = route => route.abort('failed');
      await submit.click();
      await page.waitForFunction(() => !document.querySelector('#franchise-form').hasAttribute('aria-busy'));
      assert.match(await message.textContent(), /could not confirm/); checks += 1;
      behavior = route => route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ ok: false, errors: { fullName: 'Please correct your name.' } }) });
      await submit.click();
      await page.waitForFunction(() => !document.querySelector('#franchise-form').hasAttribute('aria-busy'));
      assert(await step(1).isVisible());
      assert.equal(await page.evaluate(() => document.activeElement.id), 'fullName'); checks += 2;
      await page.locator('[data-progress="4"]').focus();
      await page.keyboard.press('Enter');
      assert(await step(4).isVisible()); checks += 1;
      behavior = route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, code: 'SMTP_ACCEPTED', reference: 'SS-0123456789ABCDEF' }) });
      await submit.click();
      await page.waitForFunction(() => !document.querySelector('#franchise-form').hasAttribute('aria-busy'));
      assert((await message.getAttribute('class')).includes('success'));
      assert(await step(1).isVisible());
      assert.equal(await page.locator('#fullName').inputValue(), '');
      assert.equal(await page.locator('#whatsapp').evaluate(el => el.readOnly), false);
      assert.equal(await page.locator('#consent').isChecked(), false);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      assert.deepEqual(errors, []); checks += 7;
      fs.mkdirSync(path.join(root, '.test-output'), { recursive: true });
      await page.locator('.form-card').screenshot({ path: path.join(root, '.test-output', `form-${name}.png`) });
      await context.close();
    }
    console.log(`PASS: ${checks} desktop/mobile browser checks. Responses mocked; no live email sent.`);
  } finally {
    await browser.close();
    server.close();
  }
})().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
