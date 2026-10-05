// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createServer } from 'node:http';
import { readFile, mkdir } from 'node:fs/promises';
import { createRequire } from 'node:module';

const require = createRequire(new URL('../../browser/package.json', import.meta.url));
const { chromium } = require('playwright');
const formPath = 'runs/01a0fe51-0926-7f3c-9353-3185879b900a/reviewer-test.html';
const site = new URL('../../site/', import.meta.url);
const html = await readFile(new URL(formPath, site), 'utf8');
const packageData = JSON.parse(html.match(/const packageData = (.*);\n/)[1]);

test('approved public packet has blank answers and no original page documents', () => {
  assert.equal(packageData.cases.length, 85);
  assert.equal(new Set(packageData.cases.map(c => c.evidenceId)).size, 85);
  assert.deepEqual(packageData.reviewDocuments, {});
  assert.match(html, /const reviewConfig = \{"testMode":true,/);
  for (const c of packageData.cases) {
    assert.equal(c.humanState, null);
    assert.equal(c.rationale, null);
    assert.equal(c.reviewedAt, null);
    assert.equal(c.reviewContext.reason, 'missing_artifact');
  }
});

test('Pages project subpath links to a functioning 85-case test and exports JSON', async () => {
  // Only the two approved HTML routes are served; source-site links are never visited.
  const root = '/privacy-evidence/';
  const server = createServer(async (req, res) => {
    const path = new URL(req.url, 'http://localhost').pathname;
    const file = path === root ? 'index.html' : path === root + formPath ? formPath : null;
    if (!file) { res.writeHead(404).end(); return; }
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.end(await readFile(new URL(file, site)));
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ acceptDownloads: true });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('http://127.0.0.1:' + server.address().port + root);
    await mkdir('/tmp/reviewer-pages-screenshots', { recursive: true });
    await page.screenshot({ path: '/tmp/reviewer-pages-screenshots/index.png', fullPage: true });
    await page.getByRole('link', { name: 'Abrir formulário de teste' }).click();
    assert.ok(page.url().endsWith(root + formPath));
    assert.equal(await page.locator('#testNotice').isVisible(), true);
    assert.equal(await page.locator('#decisionCard').isVisible(), true);
    await page.screenshot({ path: '/tmp/reviewer-pages-screenshots/form.png', fullPage: true });
    for (let i = 0; i < 85; i++) {
      assert.match(await page.locator('#categoryText').innerText(), new RegExp(' ' + (i + 1) + ' / 85$'));
      await page.locator('[data-state="unknown"]').click();
      await page.locator('#rationale').fill('Resposta fictícia para testar o formulário publicado.');
      await page.locator('#next').click();
    }
    const pending = page.waitForEvent('download');
    await page.locator('#export').click();
    const download = await pending;
    assert.equal(download.suggestedFilename(), 'privacy-evidence-review-TEST.json');
    const result = JSON.parse(await readFile(await download.path(), 'utf8'));
    assert.equal(result.testMode, true);
    assert.equal(result.cases.length, 85);
    assert.ok(result.cases.every(c => c.humanState === 'unknown' && c.rationale && c.reviewedAt));
    const originalCases = result.cases.map(c => ({...c, humanState: null, rationale: null, reviewedAt: null}));
    assert.deepEqual(originalCases, packageData.cases);
    assert.deepEqual(errors, []);
    await page.reload();
    assert.match(await page.locator('#progressText').innerText(), /^85 \/ 85/);
    await context.close();
  } finally {
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
});
