import http from 'node:http';
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

const server = http.createServer((_request, response) => {
  response.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
  response.end(html);
});

await new Promise((resolve, reject) => {
  server.once('error', reject);
  server.listen(0, '127.0.0.1', resolve);
});
const address = server.address();
if (address === null || typeof address === 'string') {
  server.close();
  throw new Error('Unable to determine benchmark fixture address.');
}
const fixtureUrl = `http://127.0.0.1:${address.port}/`;

const started = performance.now();
const browser = await chromium.launch({ headless: true });
const browserStarted = performance.now();

let renderedCharacters = 0;
try {
  for (let i = 0; i < iterations; i += 1) {
    const context = await browser.newContext({
      serviceWorkers: 'block',
      acceptDownloads: false,
    });
    const page = await context.newPage();
    await page.goto(fixtureUrl, { waitUntil: 'domcontentloaded' });
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
} finally {
  if (browser.isConnected()) {
    await browser.close();
  }
  await new Promise((resolve) => server.close(resolve));
}
