import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync, rmSync, mkdtempSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import path from 'node:path';
import process from 'node:process';

const temporaryDirectory = mkdtempSync(path.join(tmpdir(), 'tembo-qr-raster-'));
const pdfPath = path.join(temporaryDirectory, 'qr-physical-profile.pdf');
const fixture = JSON.parse(execFileSync('php', ['artisan', 'omr:qr-raster-fixtures', `--output=${pdfPath}`], {
  cwd: process.cwd(),
  encoding: 'utf8',
  windowsHide: true,
  env: process.env,
  maxBuffer: 10 * 1024 * 1024,
  shell: false,
}));

const scenarios = [
  { name: 'source-300px', sourceSize: true, rotation: 0 },
  { name: 'camera-min-300px', side: 300, rotation: 0 },
  { name: 'rotation-7deg', side: 300, rotation: 7 },
  { name: 'soft-blur', side: 300, rotation: 0, blur: 0.55 },
  { name: 'low-contrast-shadow', side: 300, rotation: -5, contrast: true, shadow: true },
];

const server = createServer((request, response) => {
  const files = {
    '/pdf.mjs': path.resolve('node_modules/pdfjs-dist/build/pdf.mjs'),
    '/pdf.worker.mjs': path.resolve('node_modules/pdfjs-dist/build/pdf.worker.mjs'),
  };
  const file = files[request.url];
  if (!file) {
    response.writeHead(200, { 'Content-Type': 'text/html' });
    response.end('<!doctype html><title>Tembo QR raster regression</title>');
    return;
  }
  response.writeHead(200, { 'Content-Type': 'text/javascript' });
  response.end(readFileSync(file));
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const address = server.address();

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
await page.goto(`http://127.0.0.1:${address.port}/`);
await page.addScriptTag({ path: path.resolve('node_modules/jsqr/dist/jsQR.js') });

try {
  const results = await page.evaluate(async ({ cases, scenarios }) => {
    const load = (src) => new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = reject;
      image.src = src;
    });
    const output = [];

    for (const qrCase of cases) {
      const image = await load(`data:image/svg+xml;base64,${qrCase.svg_base64}`);
      for (const scenario of scenarios) {
        const side = scenario.sourceSize
          ? qrCase.profile.source_size_px
          : (scenario.dpi
            ? Math.round((qrCase.profile.size_mm / 25.4) * scenario.dpi)
            : scenario.side);
        const canvas = document.createElement('canvas');
        canvas.width = 512;
        canvas.height = 512;
        const context = canvas.getContext('2d', { willReadFrequently: true });
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.save();
        context.translate(256, 256);
        context.rotate((scenario.rotation * Math.PI) / 180);
        context.imageSmoothingEnabled = true;
        context.filter = scenario.blur ? `blur(${scenario.blur}px)` : 'none';
        context.drawImage(image, -side / 2, -side / 2, side, side);
        context.restore();
        context.filter = 'none';

        if (scenario.contrast || scenario.shadow) {
          const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
          for (let i = 0; i < pixels.data.length; i += 4) {
            if (scenario.contrast) {
              pixels.data[i] = 70 + Math.round(pixels.data[i] * 0.72);
              pixels.data[i + 1] = 70 + Math.round(pixels.data[i + 1] * 0.72);
              pixels.data[i + 2] = 70 + Math.round(pixels.data[i + 2] * 0.72);
            }
            if (scenario.shadow) {
              const x = (i / 4) % canvas.width;
              const factor = 1 - (0.28 * (x / canvas.width));
              pixels.data[i] = Math.round(pixels.data[i] * factor);
              pixels.data[i + 1] = Math.round(pixels.data[i + 1] * factor);
              pixels.data[i + 2] = Math.round(pixels.data[i + 2] * factor);
            }
          }
          context.putImageData(pixels, 0, 0);
        }

        const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
        const decoded = window.jsQR(pixels.data, pixels.width, pixels.height, { inversionAttempts: 'dontInvert' });
        output.push({
          case: qrCase.name,
          scenario: scenario.name,
          decoded: decoded?.data ?? null,
          version: decoded?.version ?? null,
          expected: qrCase.encoded,
          side,
        });
      }
    }

    return output;
  }, { cases: fixture.cases, scenarios });

  const pdfResults = await page.evaluate(async ({ pdfBase64, cases, dpis }) => {
    const pdfjs = await import('/pdf.mjs');
    pdfjs.GlobalWorkerOptions.workerSrc = '/pdf.worker.mjs';
    const bytes = Uint8Array.from(atob(pdfBase64), (character) => character.charCodeAt(0));
    const document = await pdfjs.getDocument({ data: bytes }).promise;
    const output = [];

    for (let pageNumber = 1; pageNumber <= document.numPages; pageNumber += 1) {
      const pdfPage = await document.getPage(pageNumber);
      for (const dpi of dpis) {
        const viewport = pdfPage.getViewport({ scale: dpi / 72 });
        const canvas = window.document.createElement('canvas');
        canvas.width = Math.ceil(viewport.width);
        canvas.height = Math.ceil(viewport.height);
        const context = canvas.getContext('2d', { willReadFrequently: true });
        await pdfPage.render({ canvasContext: context, viewport }).promise;
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
        const decoded = window.jsQR(pixels.data, pixels.width, pixels.height, { inversionAttempts: 'dontInvert' });
        output.push({
          case: cases[pageNumber - 1]?.name ?? `unexpected-page-${pageNumber}`,
          scenario: `dompdf-${dpi}dpi`,
          decoded: decoded?.data ?? null,
          version: decoded?.version ?? null,
          expected: cases[pageNumber - 1]?.encoded ?? null,
          side: null,
        });
      }
    }

    if (document.numPages !== cases.length) {
      output.push({
        case: 'pdf-page-count',
        scenario: 'dompdf',
        decoded: String(document.numPages),
        expected: String(cases.length),
        version: null,
        side: null,
      });
    }

    return output;
  }, {
    pdfBase64: readFileSync(pdfPath).toString('base64'),
    cases: fixture.cases,
    dpis: [150, 200, 300],
  });

  const allResults = [...results, ...pdfResults];
  const failures = allResults.filter((result) => result.decoded !== result.expected);
  console.log(JSON.stringify({
    constraints: fixture.constraints,
    results: allResults.map((result) => ({
      case: result.case,
      scenario: result.scenario,
      version: result.version,
      side: result.side,
      passed: result.decoded === result.expected,
    })),
  }, null, 2));
  if (failures.length > 0) {
    console.error(`QR raster regression failed in ${failures.length}/${allResults.length} cases.`);
    process.exitCode = 1;
  }
} finally {
  await browser.close();
  await new Promise((resolve) => server.close(resolve));
  if (temporaryDirectory.startsWith(path.join(tmpdir(), 'tembo-qr-raster-'))) {
    rmSync(temporaryDirectory, { recursive: true, force: true });
  }
}
