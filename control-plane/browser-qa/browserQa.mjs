import { createRequire } from 'node:module';
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 1000, isMobile: false, hasTouch: false },
  { name: 'mobile', width: 390, height: 844, isMobile: true, hasTouch: true },
];

function usage() {
  return 'Usage: browser-qa --url https://example.test/ [--url …] --output-dir PATH [--headed] | --preflight';
}

function parseArgs(argv) {
  const result = { urls: [], headed: false, preflight: false, outputDir: '' };
  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (argument === '--url') result.urls.push(argv[++index] || '');
    else if (argument === '--output-dir') result.outputDir = argv[++index] || '';
    else if (argument === '--headed') result.headed = true;
    else if (argument === '--preflight') result.preflight = true;
    else throw new Error(`${usage()}\nUnknown argument: ${argument}`);
  }
  if (!result.preflight && (!result.outputDir || result.urls.length === 0)) throw new Error(usage());
  return result;
}

function sanitizedUrl(value) {
  const url = new URL(value);
  if (url.username || url.password) throw new Error('URLs with embedded credentials are not allowed.');
  if (!['https:', 'http:'].includes(url.protocol)) throw new Error('Only HTTP(S) URLs are supported.');
  if (url.protocol === 'http:' && !['localhost', '127.0.0.1'].includes(url.hostname)) throw new Error('Public URLs must use HTTPS.');
  const keys = [...url.searchParams.keys()];
  url.search = keys.length ? `?${[...new Set(keys)].map((key) => `${encodeURIComponent(key)}=%5BREDACTED%5D`).join('&')}` : '';
  url.hash = '';
  return url.toString();
}

function slugFor(url, index) {
  const parsed = new URL(url);
  const base = `${parsed.hostname}${parsed.pathname}`.replace(/[^a-z0-9]+/gi, '-').replace(/(^-|-$)/g, '').toLowerCase();
  return `${base || 'page'}-${index + 1}`;
}

function scrub(value) {
  return String(value)
    .replace(/([?&][^=\s]+)=([^\s&#]+)/g, '$1=[REDACTED]')
    .replace(/\b(?:bearer|token|password|passwd|secret|key)\s*[:=]\s*[^\s,;]+/gi, '$1=[REDACTED]')
    .replace(/\beyJ[a-zA-Z0-9_-]{20,}\.[a-zA-Z0-9_.-]+/g, '[REDACTED]')
    .slice(0, 500);
}

async function sha256(file) {
  return crypto.createHash('sha256').update(await fs.readFile(file)).digest('hex');
}

async function preflight(repoRoot) {
  const require = createRequire(path.join(repoRoot, 'package.json'));
  let playwright;
  try {
    playwright = require('playwright');
  } catch {
    throw new Error('Playwright is not installed. Run npm install.');
  }
  try {
    const browser = await playwright.chromium.launch({ headless: true });
    await browser.close();
  } catch {
    throw new Error('Chromium is unavailable. Run ./node_modules/.bin/playwright install chromium');
  }
  return { playwright, version: require('playwright/package.json').version };
}

function markdown(report) {
  const rows = report.urls.map((entry) => `| ${entry.sanitizedRequestedUrl} | ${entry.status} | ${entry.viewports.map((item) => item.status).join(', ')} |`).join('\n');
  return `# Browser-QA\n\nStatus: **${report.status}**\n\n- URLs: ${report.totals.urls}\n- Hard failures: ${report.totals.hardFailures}\n- Hints: ${report.totals.hints}\n\n| URL | Status | Viewports |\n| --- | --- | --- |\n${rows}\n`;
}

async function inspectViewport(browser, requestedUrl, slug, viewport, outputDir) {
  const context = await browser.newContext({ viewport, isMobile: viewport.isMobile, hasTouch: viewport.hasTouch, deviceScaleFactor: 1 });
  const page = await context.newPage();
  const consoleErrors = [];
  const consoleHints = [];
  const pageErrors = [];
  const failedRequests = [];
  const blockedRequests = [];
  const redirects = [];
  const started = Date.now();
  page.on('console', (message) => {
    if (message.type() !== 'error') return;
    const text = scrub(message.text());
    const location = message.location().url;
    const external = location && !location.startsWith(new URL(requestedUrl).origin);
    if (external || /ERR_BLOCKED_BY_CLIENT/i.test(text)) consoleHints.push(`console hint: ${text}`);
    else consoleErrors.push(text);
  });
  page.on('pageerror', (error) => pageErrors.push(scrub(error.message)));
  page.on('requestfailed', (request) => failedRequests.push({ url: sanitizedUrl(request.url()), failure: scrub(request.failure()?.errorText || 'failed') }));
  page.on('request', (request) => { if (request.isNavigationRequest() && request.frame() === page.mainFrame()) redirects.push(sanitizedUrl(request.url())); });
  await page.route('**/*', async (route) => {
    const method = route.request().method();
    if (method !== 'GET' && method !== 'HEAD') {
      blockedRequests.push({ method, url: sanitizedUrl(route.request().url()) });
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
  let response;
  let navigationError = '';
  try {
    response = await page.goto(requestedUrl, { waitUntil: 'networkidle', timeout: 30000 });
  } catch (error) {
    navigationError = scrub(error.message);
  }
  const metrics = navigationError ? null : await page.evaluate(() => ({
    title: document.title,
    h1Count: document.querySelectorAll('h1').length,
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
    images: [...document.images].map((image) => ({ src: image.currentSrc || image.src, complete: image.complete, width: image.naturalWidth, alt: image.alt })),
  }));
  const screenshotRelative = path.join('screenshots', `${slug}-${viewport.name}.png`);
  const screenshot = path.join(outputDir, screenshotRelative);
  if (!navigationError) await page.screenshot({ path: screenshot, fullPage: true });
  const hardFailures = [];
  const hints = [];
  if (navigationError) hardFailures.push(`navigation: ${navigationError}`);
  if (response && response.status() >= 400) hardFailures.push(`main document status: ${response.status()}`);
  if (metrics && metrics.scrollWidth > metrics.clientWidth) hardFailures.push(`horizontal overflow: ${metrics.scrollWidth}px > ${metrics.clientWidth}px`);
  if (pageErrors.length) hardFailures.push(...pageErrors.map((item) => `page error: ${item}`));
  if (consoleErrors.length) hardFailures.push(...consoleErrors.map((item) => `console error: ${item}`));
  if (metrics) {
    for (const image of metrics.images) {
      const sameOrigin = image.src.startsWith(new URL(requestedUrl).origin);
      if (!image.complete || image.width === 0) (sameOrigin ? hardFailures : hints).push(`broken image: ${sanitizedUrl(image.src)}`);
      if (!image.alt) hints.push(`missing image alt: ${sanitizedUrl(image.src)}`);
    }
  }
  hints.push(...consoleHints);
  hints.push(...failedRequests.map((item) => `failed request: ${item.url}`));
  hints.push(...blockedRequests.map((item) => `blocked ${item.method}: ${item.url}`));
  let screenshotMeta = null;
  try {
    const stat = await fs.stat(screenshot);
    if (stat.size <= 0) throw new Error('empty');
    screenshotMeta = { path: screenshotRelative, size: stat.size, sha256: await sha256(screenshot) };
  } catch {
    hardFailures.push('missing or empty screenshot');
  }
  const finalUrl = navigationError ? '' : sanitizedUrl(page.url());
  await context.close();
  return {
    name: viewport.name, width: viewport.width, height: viewport.height, isMobile: viewport.isMobile, hasTouch: viewport.hasTouch,
    requestedUrl: sanitizedUrl(requestedUrl), finalUrl, navigationStatus: response?.status() ?? null, redirectChain: redirects,
    title: metrics?.title ?? '', h1Count: metrics?.h1Count ?? 0, overflow: metrics ? { clientWidth: metrics.clientWidth, scrollWidth: metrics.scrollWidth } : null,
    images: metrics?.images.map((image) => ({ src: sanitizedUrl(image.src), complete: image.complete, width: image.width, hasAlt: Boolean(image.alt) })) ?? [],
    pageErrors, consoleErrors, consoleHints, failedRequests, blockedRequests, screenshot: screenshotMeta, startedAt: new Date(started).toISOString(), finishedAt: new Date().toISOString(), durationMs: Date.now() - started,
    hardFailures, hints, status: hardFailures.length ? 'failed' : 'passed',
  };
}

export async function runBrowserQaCli(argv, { repoRoot }) {
  let args;
  try { args = parseArgs(argv); } catch (error) { console.error(error.message); return 2; }
  let runtime;
  try { runtime = await preflight(repoRoot); } catch (error) { console.error(`Browser QA infrastructure error: ${error.message}`); return 2; }
  if (args.preflight) { console.log(`Browser QA preflight passed with Playwright ${runtime.version}.`); return 0; }
  const outputDir = path.resolve(repoRoot, args.outputDir);
  if (!outputDir.startsWith(`${path.resolve(repoRoot)}${path.sep}`)) { console.error('Browser QA infrastructure error: output directory must be inside the repository.'); return 2; }
  const started = Date.now();
  await fs.mkdir(path.join(outputDir, 'screenshots'), { recursive: true });
  const browser = await runtime.playwright.chromium.launch({ headless: !args.headed });
  const urls = [];
  try {
    for (const [index, requestedUrl] of args.urls.entries()) {
      const sanitizedRequestedUrl = sanitizedUrl(requestedUrl);
      const slug = slugFor(sanitizedRequestedUrl, index);
      const viewports = [];
      for (const viewport of VIEWPORTS) viewports.push(await inspectViewport(browser, requestedUrl, slug, viewport, outputDir));
      urls.push({ requestedUrl: sanitizedRequestedUrl, sanitizedRequestedUrl, slug, viewports, status: viewports.every((item) => item.status === 'passed') ? 'passed' : 'failed' });
    }
  } catch (error) {
    await browser.close();
    console.error(`Browser QA infrastructure error: ${scrub(error.message)}`);
    return 2;
  }
  await browser.close();
  const flattened = urls.flatMap((item) => item.viewports);
  const report = {
    schema: 'browser-qa-report/v1', status: urls.every((item) => item.status === 'passed') ? 'passed' : 'failed',
    startedAt: new Date(started).toISOString(), finishedAt: new Date().toISOString(), durationMs: Date.now() - started,
    repoPlaywrightVersion: runtime.version, headless: !args.headed, hostResolutions: [], urls,
    totals: { urls: urls.length, viewports: flattened.length, hardFailures: flattened.reduce((total, item) => total + item.hardFailures.length, 0), hints: flattened.reduce((total, item) => total + item.hints.length, 0) },
  };
  await fs.writeFile(path.join(outputDir, 'browser-qa-report.json'), `${JSON.stringify(report, null, 2)}\n`);
  await fs.writeFile(path.join(outputDir, 'browser-qa-summary.md'), markdown(report));
  console.log(`Browser QA ${report.status}: ${report.totals.urls} URL(s), ${report.totals.hardFailures} hard failure(s), ${report.totals.hints} hint(s).`);
  return report.status === 'passed' ? 0 : 1;
}
