#!/usr/bin/env node

import assert from "node:assert/strict";
import fsSync from "node:fs";
import fs from "node:fs/promises";
import { createRequire } from "node:module";
import path from "node:path";
import process from "node:process";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const playwrightRoots = [
  path.join(process.cwd(), "package.json"),
  path.resolve(root, "../Agent-model-for-websites/package.json"),
];
const playwrightPackage = playwrightRoots.find((packageFile) => {
  if (!fsSync.existsSync(packageFile)) {
    return false;
  }

  try {
    createRequire(packageFile).resolve("playwright");
    return true;
  } catch {
    return false;
  }
});

if (!playwrightPackage) {
  throw new Error("Playwright was not found in the current repository or the adjacent Agent-model-for-websites checkout.");
}

const { chromium } = createRequire(playwrightPackage)("playwright");
const fixtureUrl = pathToFileURL(path.join(root, "tests/fixtures/etappenstopp-responsive.html")).href;
const outputDir = path.resolve(process.argv[2] || path.join(root, "artifacts/etappenstopp-browser-harness"));
const viewports = [
  { name: "desktop", width: 1440, height: 1000 },
  { name: "mobile", width: 390, height: 844 },
];

await fs.mkdir(outputDir, { recursive: true });
const browser = await chromium.launch({ headless: true });
const results = [];

try {
  for (const viewport of viewports) {
    const page = await browser.newPage({ viewport: { width: viewport.width, height: viewport.height } });
    await page.goto(fixtureUrl);
    const trigger = page.locator('.toggle-box');
    const firstTrigger = trigger.first();
    assert.equal(await firstTrigger.evaluate((element) => element.tagName), 'BUTTON', `${viewport.name}: action control must be a button`);
    assert.equal(await firstTrigger.getAttribute('href'), null, `${viewport.name}: action control must have no link target`);
    const rowMetrics = await page.locator('.wg-etappenstopp-entry-toggle').evaluateAll((elements) => elements.map((element) => ({
      timeRight: element.parentElement.lastElementChild.getBoundingClientRect().right,
      expanded: element.getAttribute('aria-expanded'),
      closedDisplay: getComputedStyle(element.querySelector('.wg-entry-toggle-icon__closed')).display,
      openDisplay: getComputedStyle(element.querySelector('.wg-entry-toggle-icon__open')).display,
    })));
    assert.equal(rowMetrics.length, 3, `${viewport.name}: all three entry controls must be present`);
    assert(Math.max(...rowMetrics.map((row) => row.timeRight)) - Math.min(...rowMetrics.map((row) => row.timeRight)) <= 1, `${viewport.name}: time markers must share one right edge`);
    rowMetrics.forEach((row) => {
      assert.equal(row.expanded, 'false', `${viewport.name}: entries must start collapsed`);
      assert.notEqual(row.closedDisplay, 'none', `${viewport.name}: closed icon must be visible`);
      assert.equal(row.openDisplay, 'none', `${viewport.name}: open icon must be hidden initially`);
    });
    await firstTrigger.scrollIntoViewIfNeeded();
    const before = await firstTrigger.evaluate((element) => ({ scrollY, hash: location.hash, top: element.getBoundingClientRect().top }));

    await firstTrigger.click();
    await page.locator('#v-box-49589').waitFor({ state: 'visible' });
    await page.waitForTimeout(1600);
    const after = await firstTrigger.evaluate((element) => {
      const box = document.querySelector('.dynamic-box');
      const media = box.querySelector('video');
      return {
        scrollY,
        hash: location.hash,
        top: element.getBoundingClientRect().top,
        clientWidth: document.documentElement.clientWidth,
        documentWidth: document.documentElement.scrollWidth,
        boxWidth: box.getBoundingClientRect().width,
        mediaWidth: media.getBoundingClientRect().width,
        captionMarginBottom: parseFloat(getComputedStyle(box.querySelector('figcaption')).marginBottom),
        captionTranscriptGap: box.querySelector('.transcript').getBoundingClientRect().top - box.querySelector('figcaption').getBoundingClientRect().bottom,
        expanded: element.getAttribute('aria-expanded'),
        closedDisplay: getComputedStyle(element.querySelector('.wg-entry-toggle-icon__closed')).display,
        openDisplay: getComputedStyle(element.querySelector('.wg-entry-toggle-icon__open')).display,
      };
    });

    assert.equal(after.hash, before.hash, `${viewport.name}: hash must remain unchanged`);
    assert(Math.abs(after.top - before.top) <= 2, `${viewport.name}: trigger must remain in the reading position`);
    assert(after.documentWidth <= after.clientWidth, `${viewport.name}: document must not overflow horizontally`);
    assert(after.mediaWidth <= after.boxWidth + 1, `${viewport.name}: video must remain inside the content box`);
    assert.equal(after.captionMarginBottom, 32, `${viewport.name}: caption must own a 2rem bottom margin`);
    assert(after.captionTranscriptGap >= 31, `${viewport.name}: caption and transcript must keep visible spacing`);
    assert.equal(after.expanded, 'true', `${viewport.name}: opened entry must expose its state`);
    assert.equal(after.closedDisplay, 'none', `${viewport.name}: closed icon must hide after opening`);
    assert.notEqual(after.openDisplay, 'none', `${viewport.name}: open icon must show after opening`);

    await firstTrigger.click();
    await page.locator('#v-box-49589').waitFor({ state: 'hidden' });
    await firstTrigger.focus();
    await firstTrigger.press('Enter');
    await page.locator('#v-box-49589').waitFor({ state: 'visible' });

    const screenshot = path.join(outputDir, `${viewport.name}.png`);
    await page.screenshot({ path: screenshot, fullPage: true });
    results.push({ viewport: viewport.name, before, after, screenshot });
    await page.close();
  }
} finally {
  await browser.close();
}

await fs.writeFile(path.join(outputDir, 'report.json'), `${JSON.stringify({ status: 'passed', results }, null, 2)}\n`, 'utf8');
console.log(`Etappenstopp browser harness: ${results.length} scenarios passed.`);
