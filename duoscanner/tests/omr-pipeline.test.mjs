import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import {
  computeHomography,
  reprojectionError,
  transformPoint,
  validateFiducialQuadrilateral,
} from '../src/lib/homography.ts';
import {
  analyzeImageQuality,
  rgbaToGrayscale,
} from '../src/lib/image-quality.ts';
import { processOMRPixels } from '../src/lib/omr-pixel-pipeline.ts';
import { orientationFromQrLocation, pointFromNormalizedImage, rotateRgbaQuarterTurns } from '../src/lib/qr-orientation.ts';

test('converts RGBA pixels to one grayscale byte per pixel', () => {
  const rgba = new Uint8Array([
    10, 10, 10, 255,
    200, 200, 200, 255,
    255, 0, 0, 128,
    0, 255, 0, 255,
  ]);

  assert.deepEqual([...rgbaToGrayscale(rgba, 2, 2)], [10, 200, 76, 150]);
});

test('uses the asymmetric QR location to resolve every card orientation', () => {
  const at = (x, y) => [{ x: x - 2, y: y - 2 }, { x: x + 2, y: y - 2 }, { x: x + 2, y: y + 2 }, { x: x - 2, y: y + 2 }];
  assert.equal(orientationFromQrLocation(at(90, 10), undefined, 100, 100), 0);
  assert.equal(orientationFromQrLocation(at(10, 10), undefined, 100, 100), 1);
  assert.equal(orientationFromQrLocation(at(10, 90), undefined, 100, 100), 2);
  assert.equal(orientationFromQrLocation(at(90, 90), undefined, 100, 100), 3);
  assert.equal(orientationFromQrLocation(at(50, 50), undefined, 100, 100), null);
});

test('normalizes RGBA pixels for 90, 180 and 270 degree cards', () => {
  const rgba = new Uint8Array([
    1, 1, 1, 255, 2, 2, 2, 255,
    3, 3, 3, 255, 4, 4, 4, 255,
  ]);
  const values = (result) => Array.from({ length: result.width * result.height }, (_, index) => result.data[index * 4]);
  assert.deepEqual(values(rotateRgbaQuarterTurns({ data: rgba, width: 2, height: 2 }, 1)), [3, 1, 4, 2]);
  assert.deepEqual(values(rotateRgbaQuarterTurns({ data: rgba, width: 2, height: 2 }, 2)), [4, 3, 2, 1]);
  assert.deepEqual(values(rotateRgbaQuarterTurns({ data: rgba, width: 2, height: 2 }, 3)), [2, 4, 1, 3]);
});

test('maps normalized overlay points back onto the original captured photo', () => {
  const original = { x: 1, y: 0 };
  const normalizedByTurn = [{ x: 1, y: 0 }, { x: 1, y: 1 }, { x: 0, y: 1 }, { x: 0, y: 0 }];
  for (const turns of [0, 1, 2, 3]) {
    assert.deepEqual(pointFromNormalizedImage(normalizedByTurn[turns], 2, 2, turns), original);
  }
});

test('rejects an undersized or inconsistent pixel buffer', () => {
  assert.throws(() => rgbaToGrayscale(new Uint8Array(3), 2, 2, 4), /Invalid pixel buffer/);
  assert.throws(() => rgbaToGrayscale(new Uint8Array(4), 2, 2, 5), /Invalid pixel buffer/);
});

test('measures image quality instead of accepting flat images', () => {
  const white = new Uint8Array(25).fill(255);
  const dark = new Uint8Array(25).fill(10);
  const checkerboard = Uint8Array.from({ length: 25 }, (_, index) => {
    const x = index % 5;
    const y = Math.floor(index / 5);
    return (x + y) % 2 === 0 ? 30 : 220;
  });

  assert.equal(analyzeImageQuality(white, 5, 5).acceptable, false);
  assert.ok(analyzeImageQuality(white, 5, 5).reasons.includes('overexposed'));
  assert.ok(analyzeImageQuality(dark, 5, 5).reasons.includes('too_dark'));
  assert.equal(analyzeImageQuality(checkerboard, 5, 5).acceptable, true);
});

test('solves a translated rectangle with negligible reprojection error', () => {
  const source = [
    { x: 10, y: 20 },
    { x: 110, y: 20 },
    { x: 110, y: 220 },
    { x: 10, y: 220 },
  ];
  const destination = [
    { x: 0, y: 0 },
    { x: 100, y: 0 },
    { x: 100, y: 200 },
    { x: 0, y: 200 },
  ];

  const homography = computeHomography(source, destination);
  const error = reprojectionError(homography, source, destination);

  assert.ok(error.rms < 1e-8, `RMS ${error.rms}`);
  assert.ok(error.max < 1e-8, `max ${error.max}`);
  const projected = transformPoint(homography, source[0]);
  assert.ok(Math.abs(projected.x - destination[0].x) < 1e-8);
  assert.ok(Math.abs(projected.y - destination[0].y) < 1e-8);
});

test('solves a perspective quadrilateral and rejects singular points', () => {
  const source = [
    { x: 15, y: 25 },
    { x: 205, y: 5 },
    { x: 220, y: 305 },
    { x: 5, y: 280 },
  ];
  const destination = [
    { x: 0, y: 0 },
    { x: 200, y: 0 },
    { x: 200, y: 300 },
    { x: 0, y: 300 },
  ];
  const homography = computeHomography(source, destination);
  assert.ok(reprojectionError(homography, source, destination).max < 1e-7);

  const collinear = [
    { x: 0, y: 0 },
    { x: 1, y: 1 },
    { x: 2, y: 2 },
    { x: 3, y: 3 },
  ];
  assert.throws(() => computeHomography(collinear, destination), /singular/);
});

test('rejects fiducials outside the four corner regions', () => {
  const central = [
    { x: 40, y: 40 },
    { x: 60, y: 40 },
    { x: 60, y: 60 },
    { x: 40, y: 60 },
  ];
  assert.equal(validateFiducialQuadrilateral(central, 100, 100).valid, false);
  const corners = [
    { x: 5, y: 5 },
    { x: 95, y: 5 },
    { x: 95, y: 95 },
    { x: 5, y: 95 },
  ];
  assert.equal(validateFiducialQuadrilateral(corners, 100, 100).valid, true);
});

test('fails closed without four fiducials and never infers an answer', () => {
  const width = 120;
  const height = 180;
  const rgba = new Uint8Array(width * height * 4).fill(255);
  const result = processOMRPixels(
    { data: rgba, width, height },
    {
      questionIds: [17],
      optionCounts: { 17: 5 },
      qrVersion: 5,
      orientationVerified: true,
      rowsPerPage: 20,
      qrGeometry: [753, 1404, 10000, 1579, 296, 403],
    },
  );

  assert.equal(result.action, 'rescan');
  assert.equal(result.answers['17'], null);
  assert.equal(result.confidences['17'], 0);
  assert.equal(result.fiducialCount, 0);
  assert.ok(result.reasons.includes('fiducial_count'));
});

test('processes a complete synthetic sheet through fiducials, homography and grayscale ROIs', () => {
  const width = 400;
  const height = 600;
  const rgba = new Uint8Array(width * height * 4);
  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      const value = y % 8 < 2 ? 155 : 215;
      const offset = (y * width + x) * 4;
      rgba[offset] = value;
      rgba[offset + 1] = value;
      rgba[offset + 2] = value;
      rgba[offset + 3] = 255;
    }
  }
  const fillRect = (left, top, rectWidth, rectHeight, value = 0) => {
    for (let y = top; y < top + rectHeight; y += 1) {
      for (let x = left; x < left + rectWidth; x += 1) {
        const offset = (y * width + x) * 4;
        rgba[offset] = value;
        rgba[offset + 1] = value;
        rgba[offset + 2] = value;
      }
    }
  };
  fillRect(10, 10, 20, 20);
  fillRect(370, 10, 20, 20);
  fillRect(370, 570, 20, 20);
  fillRect(10, 570, 20, 20);
  // First option of the first signed ROI after the affine-like rectification.
  fillRect(47, 99, 12, 12);

  const result = processOMRPixels(
    { data: rgba, width, height },
    {
      questionIds: [17],
      optionCounts: { 17: 2 },
      qrVersion: 5,
      orientationVerified: true,
      rowsPerPage: 20,
      qrGeometry: [753, 1404, 10000, 1579, 296, 403],
    },
  );

  assert.equal(result.usedHomography, true);
  assert.equal(result.fiducialCount, 4);
  assert.equal(result.processingPath, 'homography');
  assert.ok(result.reprojectionError.max < 1e-6);
  assert.notEqual(result.action, 'rescan');
  assert.equal(result.answers['17'], 0);
  assert.equal(result.questionEvidence['17'].action, 'accept');
  assert.equal(result.questionEvidence['17'].originalPolygon.length, 4);
});

test('production capture has no simulated quality, mandatory crop, or JPEG-size answer fallback', () => {
  const camera = readFileSync(new URL('../app/scan/camera.tsx', import.meta.url), 'utf8');
  const processor = readFileSync(new URL('../src/lib/omr-processor.ts', import.meta.url), 'utf8');
  const review = readFileSync(new URL('../app/scan/review-marks.tsx', import.meta.url), 'utf8');

  assert.doesNotMatch(camera, /evaluatePreCapture|auto_capturing|\/scan\/adjust/);
  assert.doesNotMatch(processor, /legacyFileSizeProcessing|legacyAnalyzeBubbles/);
  assert.doesNotMatch(review, /Continuar assim/);
  assert.match(review, /disabled=\{omrMeta\?\.action !== 'accept'\}/);
});
