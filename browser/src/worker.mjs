import dns from 'node:dns/promises';
import net from 'node:net';
import { chromium } from 'playwright';

function isForbiddenIp(address) {
  const family = net.isIP(address);
  if (family === 4) {
    const parts = address.split('.').map(Number);
    const [a, b] = parts;

    return (
      a === 0 ||
      a === 10 ||
      a === 127 ||
      (a === 169 && b === 254) ||
      (a === 172 && b >= 16 && b <= 31) ||
      (a === 192 && b === 168) ||
      (a === 100 && b >= 64 && b <= 127) ||
      a >= 224
    );
  }

  if (family === 6) {
    const value = address.toLowerCase();
    return (
      value === '::1' ||
      value === '::' ||
      value.startsWith('fe8') ||
      value.startsWith('fe9') ||
      value.startsWith('fea') ||
      value.startsWith('feb') ||
      value.startsWith('fc') ||
      value.startsWith('fd') ||
      value.startsWith('ff')
    );
  }

  return true;
}

async function assertPublicUrl(rawUrl) {
  const url = new URL(rawUrl);
  if (!['http:', 'https:'].includes(url.protocol)) {
    throw new Error('Browser worker accepts only HTTP/HTTPS URLs.');
  }

  const hostname = url.hostname.replace(/^\[|\]$/g, '');
  if (hostname === 'localhost' || hostname.endsWith('.localhost')) {
    throw new Error('Localhost is not allowed.');
  }

  if (net.isIP(hostname)) {
    if (isForbiddenIp(hostname)) {
      throw new Error('Private or reserved IP address is not allowed.');
    }
    return;
  }

  const addresses = await dns.lookup(hostname, { all: true, verbatim: true });
  if (addresses.length === 0 || addresses.some(({ address }) => isForbiddenIp(address))) {
    throw new Error('Hostname resolves to a private or reserved address.');
  }
}

const chunks = [];
for await (const chunk of process.stdin) {
  chunks.push(chunk);
}

const input = JSON.parse(Buffer.concat(chunks).toString('utf8'));
if (typeof input.url !== 'string') {
  throw new Error('A URL is required.');
}

await assertPublicUrl(input.url);

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  serviceWorkers: 'block',
  acceptDownloads: false,
});
const page = await context.newPage();

const requests = [];
await page.route('**/*', async (route) => {
  try {
    await assertPublicUrl(route.request().url());
    await route.continue();
  } catch {
    await route.abort('blockedbyclient');
  }
});

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
