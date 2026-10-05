// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later
import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { createServer } from 'node:http';
import { createHash } from 'node:crypto';
import { readFile, mkdir, mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';

const require = createRequire(new URL('../../browser/package.json', import.meta.url));
const engines = require('playwright');
const engine = engines[process.env.REVIEW_BROWSER || 'chromium'];
const template = await readFile(new URL('../../resources/review/reviewer.html', import.meta.url), 'utf8');
const states = ['present', 'absent', 'unknown', 'unavailable', 'invalid', 'excluded', 'not_applicable'];
const fixtureCases = Array.from({ length: 85 }, (_, i) => ({
  evidenceId: String(i).padStart(64, '0'), resourceId: 'resource-' + i,
  evidenceType: i % 2 ? 'privacy_notice' : 'cookie_accept_control',
  sourceUrl: i === 0 ? 'javascript:alert(1)' : 'https://example.test/site-' + i,
  artifactHash: String(i).padStart(64, '0'), reviewContext: { resourceName: 'Synthetic site ' + i, resourceUrl: 'https://sample.test/' + i, fetchedAt: '2026-10-05T00:00:00Z', truncated: false, reason: i % 3 ? null : 'missing_artifact' }, excerpt: i % 3 ? 'Preserved fixture text ' + i : null,
  automatedState: 'present', detector: 'fixture', detectorVersion: '1.0.0',
  confidence: 0.9, needsReview: false, humanState: null, rationale: null, reviewedAt: null
}));
let browser;
let server;
let baseUrl;

before(async () => {
  server = createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    const cases = url.searchParams.has('empty') ? [] : url.searchParams.has('missing')
      ? fixtureCases.filter(c => c.excerpt === null) : fixtureCases;
    const data = { packageVersion: '1.0.0', annotationHandbookVersion: '1.0.0', runId: 'fixture', seed: 'fixture', perStratum: 2, cases, reviewDocuments: Object.fromEntries(cases.filter(c => c.excerpt).map(c => [c.artifactHash, {title: 'Preserved policy', text: 'Full preserved content for site ' + c.resourceId + '. The data controller is Synthetic Example Ltd. Contact privacy@example.test.'}])) };
    // A changed packet under the same path must not recover obsolete progress.
    if (url.searchParams.has('context')) {
      data.cases = [{...fixtureCases[1], evidenceType: 'controller_identity', excerpt: null, sourceUrl: 'https://external.test/policy'}];
      data.reviewDocuments = {[fixtureCases[1].artifactHash]: {title: 'Controller information', text: 'A controlador de dados is a generic legal term. The responsible controller is Synthetic Example Ltd. <script>window.contextExecuted = true</script>'}};
    }
    if (url.searchParams.has('changed')) data.seed = 'changed';
    const json = JSON.stringify(data).replaceAll('<', '\\u003C');
    const config = { testMode: url.searchParams.has('test'), packageHash: createHash('sha256').update(json).digest('hex') };
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.end(template.replace('__PACKAGE_JSON__', json).replace('__REVIEW_CONFIG_JSON__', JSON.stringify(config)));
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  baseUrl = 'http://127.0.0.1:' + server.address().port + '/reviewer.html';
  browser = await engine.launch({ headless: true });
});
after(async () => {
  if (browser) await browser.close();
  if (server) await new Promise(resolve => server.close(resolve));
});

async function session(query = '') {
  const context = await browser.newContext({ acceptDownloads: true });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(baseUrl + query);
  return { context, page, errors };
}
async function answer(page, state = 'present', rationale = 'Arbitrary form test answer.') {
  if (['unavailable', 'invalid', 'excluded', 'not_applicable'].includes(state)) {
    if (!await page.locator('#secondaryStates').isVisible()) await page.locator('#moreStates').click();
  }
  await page.locator('.states button[data-state="' + state + '"]').click();
  await page.locator('#rationale').fill(rationale);
  await page.locator('#next').click();
}
async function download(page) {
  const pending = page.waitForEvent('download');
  await page.locator('#export').click();
  const result = await pending;
  return { filename: result.suggestedFilename(), data: JSON.parse(await readFile(await result.path(), 'utf8')) };
}

const screenshots = process.env.REVIEW_SCREENSHOT_DIR;
async function screenshot(page, name) {
  if (!screenshots) return;
  await mkdir(screenshots, { recursive: true });
  await page.screenshot({ path: screenshots + '/' + name + '.png', fullPage: true });
}

test('test mode traverses all 85 pages, all labels, drafts, languages and JSON export', async () => {
  const { context, page, errors } = await session('?test');
  try {
    assert.equal(await page.locator('#testNotice').isVisible(), true);
    await screenshot(page, 'test-desktop');
    for (let i = 0; i < 85; i++) {
      assert.match(await page.locator('#categoryText').innerText(), new RegExp(' ' + (i + 1) + ' / 85$'));
      if (i === 0) {
        await page.locator('#rationale').fill('Draft must survive state selection.');
        await page.locator('#primaryStates button').first().click();
        assert.equal(await page.locator('#rationale').inputValue(), 'Draft must survive state selection.');
        await page.locator('#languageToggle').click();
        assert.equal(await page.locator('#rationale').inputValue(), 'Draft must survive state selection.');
        await page.locator('#languageToggle').click();
      }
      await answer(page, states[i % states.length]);
    }
    assert.match(await page.locator('#progressText').innerText(), /^85 \/ 85/);
    const result = await download(page);
    assert.match(result.filename, /TEST/);
    assert.equal(result.data.testMode, true);
    assert.equal(result.data.cases.length, 85);
    for (const c of result.data.cases) {
      assert.ok(c.reviewedAt);
      assert.ok(states.includes(c.humanState));
      assert.equal(c.automatedState, 'present');
      assert.equal(c.confidence, 0.9);
    }
    await page.locator('#rationale').fill('');
    assert.equal(await page.locator('#export').isDisabled(), true);
    await page.reload();
    assert.equal(await page.locator('#rationale').inputValue(), '');
    assert.equal(await page.locator('#export').isDisabled(), true);
    assert.deepEqual(errors, []);
  } finally { await context.close(); }
});

test('research mode separates triage without discarding sites or creating forced labels', async () => {
  const { context, page, errors } = await session();
  try {
    assert.match(await page.locator('#queueNotice').innerText(), /85.*56.*29/);
    assert.equal(await page.locator('#testNotice').isVisible(), false);
    await screenshot(page, 'research-desktop');
    await page.locator('#queueToggle').click();
    assert.equal(await page.locator('#decisionCard').isVisible(), false);
    assert.equal(await page.locator('#sourceUrl').getAttribute('href'), null);
    assert.match(await page.locator('#sourceUrl').innerText(), /javascript:/);
    await page.locator('#next').click();
    assert.match(await page.locator('#sourceUrl').getAttribute('href'), /^https:\/\/example.test/);
    await screenshot(page, 'investigation-desktop');
    for (let i = 1; i < 28; i++) await page.locator('#next').click();
    assert.equal(await page.locator('#next').isDisabled(), true);
    await page.locator('#queueToggle').click();
    for (let i = 0; i < 56; i++) await answer(page, 'unknown', 'Fixture text is insufficient for a conclusion.');
    const result = await download(page);
    assert.equal(result.data.testMode, undefined);
    assert.equal(result.data.cases.length, 85);
    const deferred = result.data.cases.filter(c => c.excerpt === null);
    assert.equal(deferred.length, 29);
    for (const c of deferred) assert.equal(c.humanState, null);
    assert.deepEqual(errors, []);
  } finally { await context.close(); }
});

test('test answers cannot restore into research, and a changed packet cannot reuse old progress', async () => {
  const { context, page } = await session('?test');
  try {
    await answer(page);
    await page.goto(baseUrl);
    assert.match(await page.locator('#progressText').innerText(), /^0 \/ 56/);
    await answer(page);
    await page.reload();
    assert.match(await page.locator('#progressText').innerText(), /^1 \/ 56/);
    await page.goto(baseUrl + '?changed');
    assert.match(await page.locator('#progressText').innerText(), /^0 \/ 56/);
  } finally { await context.close(); }
});

test('empty and entirely deferred packets remain navigable and exportable without invented labels', async () => {
  for (const query of ['?empty', '?missing']) {
    const { context, page, errors } = await session(query);
    try {
      assert.equal(await page.locator('#next').isDisabled(), true);
      const result = await download(page);
      assert.equal(result.data.cases.length, query === '?empty' ? 0 : 29);
      if (query === '?missing') {
        await page.locator('#queueToggle').click();
        assert.equal(await page.locator('#evidenceCard').isVisible(), true);
        assert.equal(await page.locator('#decisionCard').isVisible(), false);
      }
      assert.deepEqual(errors, []);
    } finally { await context.close(); }
  }
});

test('blocked local storage does not prevent completing and exporting the form', async () => {
  const context = await browser.newContext({ acceptDownloads: true });
  const page = await context.newPage();
  await page.addInitScript(() => {
    Storage.prototype.getItem = () => { throw new Error('blocked'); };
    Storage.prototype.setItem = () => { throw new Error('blocked'); };
  });
  try {
    await page.goto(baseUrl + '?test&missing');
    for (let i = 0; i < 29; i++) await answer(page);
    assert.match(await page.locator('#saveStatus').innerText(), /Não foi possível salvar/);
    assert.equal((await download(page)).data.cases.length, 29);
  } finally { await context.close(); }
});

test('mobile layout and keyboard save preserve independent review', async () => {
  const { context, page, errors } = await session('?test');
  try {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.keyboard.press('1');
    await page.locator('#rationale').fill('Keyboard test');
    await page.locator('#rationale').press('Control+Enter');
    assert.match(await page.locator('#progressText').innerText(), /^1 \/ 85/);
    const width = await page.evaluate(() => ({ body: document.body.scrollWidth, viewport: innerWidth }));
    assert.ok(width.body <= width.viewport);
    await screenshot(page, 'test-mobile');
    assert.deepEqual(errors, []);
  } finally { await context.close(); }
});


test('generated HTML works directly from a local file without an HTTP server', async () => {
  const directory = await mkdtemp(tmpdir() + '/reviewer-offline-');
  const context = await browser.newContext({ acceptDownloads: true });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const path = directory + '/reviewer.html';
  const data = { runId: 'offline', seed: 'offline', cases: [fixtureCases[1]] };
  const json = JSON.stringify(data);
  await writeFile(path, template.replace('__PACKAGE_JSON__', json).replace('__REVIEW_CONFIG_JSON__', JSON.stringify({ testMode: true, packageHash: 'offline-fixture' })));
  try {
    await page.goto(pathToFileURL(path).href);
    await answer(page);
    assert.equal((await download(page)).data.testMode, true);
    assert.deepEqual(errors, []);
  } finally {
    await context.close();
    await rm(directory, { recursive: true, force: true });
  }
});


test('review presents sampled site, full document, concrete question and date even without detector excerpt', async () => {
  const {context, page, errors} = await session('?context');
  try {
    assert.match(await page.locator('#sampleSite').innerText(), /Synthetic site 1/);
    assert.match(await page.locator('#sampleUrl').innerText(), /sample.test/);
    assert.match(await page.locator('#type').innerText(), /quem é o controlador/);
    assert.equal(await page.locator('#sourceUrl').innerText(), 'https://external.test/policy');
    assert.match(await page.locator('#pageContext').innerText(), /2026-10-05/);
    assert.match(await page.locator('#excerpt').innerText(), /Synthetic Example Ltd/);
    assert.equal(await page.evaluate(() => window.contextExecuted), undefined);
    assert.equal(await page.locator('#decisionCard').isVisible(), true);
    await screenshot(page, 'full-page-context');
    await answer(page, 'present', 'Synthetic Example Ltd is explicitly identified in the preserved page.');
    const result = await download(page);
    assert.equal(result.data.cases[0].excerpt, null);
    assert.equal(result.data.cases[0].sourceUrl, 'https://external.test/policy');
    assert.deepEqual(errors, []);
  } finally { await context.close(); }
});
