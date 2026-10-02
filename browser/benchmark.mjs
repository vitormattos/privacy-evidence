import { chromium } from 'playwright';
import { performance } from 'node:perf_hooks';

const iterations = Number.parseInt(process.env.ITERATIONS ?? '10', 10);
if (!Number.isInteger(iterations) || iterations < 1 || iterations > 1000) {
  throw new Error('ITERATIONS must be an integer between 1 and 1000.');
}

const html = `<!doctype html>
<html>
  <body>
    <div id="app"></div>
    <script>
      document.querySelector('#app').innerHTML =
        '<p>Privacy policy</p><button id="accept">Accept all cookies</button>';
      localStorage.setItem('fixture', '1');
    </script>
  </body>
</html>`;

const started = performance.now();
const browser = await chromium.launch({ headless: true });
const browserStarted = performance.now();

let renderedCharacters = 0;
for (let i = 0; i < iterations; i += 1) {
  const context = await browser.newContext({
    serviceWorkers: 'block',
    acceptDownloads: false,
  });
  const page = await context.newPage();
  await page.setContent(html, { waitUntil: 'domcontentloaded' });
  renderedCharacters += (await page.content()).length;
  const state = await page.evaluate(() => localStorage.getItem('fixture'));
  if (state !== '1') {
    throw new Error('Dynamic fixture did not preserve expected storage state.');
  }
  await context.close();
}

const beforeClose = performance.now();
const browserVersion = browser.version();
await browser.close();
const finished = performance.now();

const usage = process.resourceUsage();
process.stdout.write(JSON.stringify({
  schemaVersion: '1.0.0',
  backend: 'playwright',
  browserVersion,
  nodeVersion: process.version,
  iterations,
  startupMs: Math.round((browserStarted - started) * 100) / 100,
  workloadMs: Math.round((beforeClose - browserStarted) * 100) / 100,
  totalMs: Math.round((finished - started) * 100) / 100,
  averageContextMs: Math.round(((beforeClose - browserStarted) / iterations) * 100) / 100,
  maxRssKiB: usage.maxRSS,
  userCpuMicros: usage.userCPUTime,
  systemCpuMicros: usage.systemCPUTime,
  renderedCharacters,
}, null, 2) + '\n');
