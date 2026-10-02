import { chromium } from 'playwright';

const chunks = [];
for await (const chunk of process.stdin) {
  chunks.push(chunk);
}

const input = JSON.parse(Buffer.concat(chunks).toString('utf8'));
if (typeof input.url !== 'string' || !/^https?:\/\//i.test(input.url)) {
  throw new Error('Browser worker accepts only HTTP/HTTPS URLs.');
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  serviceWorkers: 'block',
  acceptDownloads: false,
});
const page = await context.newPage();

const requests = [];
page.on('request', (request) => {
  requests.push({
    url: request.url(),
    method: request.method(),
    resourceType: request.resourceType(),
  });
});

try {
  await page.goto(input.url, {
    waitUntil: 'domcontentloaded',
    timeout: input.timeoutMs ?? 30000,
  });

  for (const action of input.actions ?? []) {
    if (action.action === 'click' && typeof action.selector === 'string') {
      await page.locator(action.selector).first().click({ timeout: 5000 });
    }
    if (action.action === 'wait' && Number.isInteger(action.milliseconds)) {
      await page.waitForTimeout(action.milliseconds);
    }
  }

  const result = {
    url: page.url(),
    html: await page.content(),
    capturedAt: new Date().toISOString(),
    browserVersion: browser.version(),
    cookies: await context.cookies(),
    localStorage: await page.evaluate(() => ({ ...localStorage })),
    sessionStorage: await page.evaluate(() => ({ ...sessionStorage })),
    requests,
  };

  process.stdout.write(JSON.stringify(result));
} finally {
  await context.close();
  await browser.close();
}
