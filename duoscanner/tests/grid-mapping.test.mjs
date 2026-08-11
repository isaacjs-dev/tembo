import assert from 'node:assert/strict';
import test from 'node:test';

import { resolveGrid, resolveGridPosition } from '../src/lib/grid-mapping.ts';
import { canCaptureOffline, parseQRCode } from '../src/lib/qr-parser.ts';
import { gradeAnswers } from '../src/lib/grading.ts';
import { individualizedStudent } from '../src/lib/exam-copy.ts';
import {
  parseCardPageGeometry,
  resolveCardPageGeometryPixels,
  resolvePageLocalGridPosition,
} from '../src/lib/geometry-contract.ts';
import { readFileSync } from 'node:fs';
import {
  mapQuestionValuesToPrintedPositions,
  mapVisualAnswersToOriginalOptions,
} from '../src/lib/answer-mapping.ts';

const geometryFixture = JSON.parse(readFileSync(
  new URL('../../contracts/omr/card-page-geometry.v1.json', import.meta.url),
  'utf8'
));

test('matches all PHP card-page geometry golden vectors', () => {
  for (const fixture of geometryFixture.cases) {
    const contract = parseCardPageGeometry({
      g: fixture.expected.g,
      rpp: fixture.expected.rpp,
      qs: fixture.q_start,
      qe: fixture.expected.q_end,
      oc: fixture.option_counts,
      tpl_id: fixture.template_id,
      tpl_v: fixture.template_version,
    });

    assert.ok(contract, fixture.name);
    assert.deepEqual(
      resolvePageLocalGridPosition(fixture.q_start, fixture.q_start, contract.rpp),
      { column: 0, row: 0 }
    );
    const pixels = resolveCardPageGeometryPixels(contract.g, 1000, 1000);
    assert.ok(Math.abs(pixels.startX - (fixture.expected.g[0] / 10)) < 1e-9);
    assert.ok(Math.abs(pixels.bubbleWidth - (fixture.expected.g[4] / 10)) < 1e-9);
  }
});

test('uses signed rpp instead of the legacy registry capacity', () => {
  const grid = resolveGrid(40, { numCols: 4, rowsPerCol: 15 }, 20);

  assert.deepEqual(grid, { rowsPerColumn: 20, columns: 2, totalQuestions: 40 });
  assert.deepEqual(resolveGridPosition(15, grid.rowsPerColumn), { col: 0, row: 15 });
  assert.deepEqual(resolveGridPosition(20, grid.rowsPerColumn), { col: 1, row: 0 });
});

test('maps a four-column 50-question page with rpp 13', () => {
  const grid = resolveGrid(50, { numCols: 4, rowsPerCol: 15 }, 13);

  assert.deepEqual(grid, { rowsPerColumn: 13, columns: 4, totalQuestions: 50 });
  assert.deepEqual(resolveGridPosition(15, grid.rowsPerColumn), { col: 1, row: 2 });
});

test('keeps the historical signed v4 template slug compatible', () => {
  const qr = parseQRCode(JSON.stringify({
    e: 10,
    c: 20,
    h: 'legacy-hash',
    p: 1,
    pt: 1,
    qs: 1,
    qe: 2,
    v: 4,
    rpp: 20,
    cols: 1,
    tpl: 'legacy-professional',
    tpl_id: 5,
    tpl_v: 2,
    g: [100, 200, 300, 400, 500, 600],
    oc: '22',
    chk: 'signed-value',
  }));

  assert.equal(qr?.v, 4);
  assert.equal(qr?.signedPayload?.tpl, 'legacy-professional');
});

test('keeps an early v4 QR readable but not eligible for offline capture', () => {
  const qr = parseQRCode(JSON.stringify({
    e: 10,
    c: 20,
    h: 'legacy-hash',
    p: 1,
    v: 4,
    tpl: 'legacy-professional',
    tpl_id: 5,
    tpl_v: 2,
    g: [1, 2, 3, 4, 5, 6],
    chk: 'signed-value',
  }));

  assert.equal(qr?.v, 4);
  assert.equal(qr?.qs, undefined);
  assert.equal(qr?.g?.length, 6);
  assert.equal(canCaptureOffline(qr), false);
});

test('rejects unknown fields in the current v5 contract', () => {
  const qr = parseQRCode(JSON.stringify({
    e: 10,
    c: 20,
    h: 'hash',
    p: 1,
    pt: 1,
    qs: 1,
    qe: 1,
    v: 5,
    rpp: 20,
    tpl_id: 5,
    tpl_v: 2,
    g: [100, 200, 300, 400, 500, 600],
    oc: '2',
    chk: 'signed-value',
    student_name: 'must-not-be-trusted',
  }));

  assert.equal(qr, null);
});

test('rejects modern QR contracts with absent or out-of-frame geometry', () => {
  const base = {
    e: 10,
    c: 20,
    h: 'hash',
    p: 1,
    pt: 1,
    qs: 1,
    qe: 2,
    v: 5,
    rpp: 20,
    tpl_id: 5,
    tpl_v: 2,
    oc: '55',
    chk: 'signed-value',
  };

  assert.equal(parseQRCode(JSON.stringify(base)), null);
  assert.equal(parseQRCode(JSON.stringify({
    ...base,
    g: [9900, 1000, 5000, 500, 300, 400],
  })), null);
});

test('maps shuffled visual alternatives back to authoritative option indexes', () => {
  assert.deepEqual(
    mapVisualAnswersToOriginalOptions(
      { '101': 0, '102': 1, '103': null },
      { '101': [2, 0, 1], '102': [1, 0], '103': null }
    ),
    { '101': 2, '102': 0, '103': null }
  );
});

test('maps database question ids to signed printed positions for upload', () => {
  assert.deepEqual(
    mapQuestionValuesToPrintedPositions(
      { '101': 2, '205': null, '999': 1 },
      [205, 101],
      5
    ),
    { '2': 2, '1': null, '999': 1 }
  );
});

test('grades a historical copy with its immutable snapshot instead of the current key', () => {
  const copy = {
    id: 7,
    copy_number: 1,
    validation_hash: 'historical-copy',
    questions_map: [11],
    options_map: { 11: [0, 1] },
    question_snapshot: [{
      id: 11,
      type: 'multiple_choice',
      correct_option: 0,
      option_count: 2,
      points: 2,
      order: 1,
    }],
  };
  const currentQuestions = [{
    id: 11,
    type: 'multiple_choice',
    correct_option: 1,
    option_count: 2,
    points: 9,
    order: 1,
  }];

  const result = gradeAnswers({ 11: 0 }, copy, currentQuestions);

  assert.equal(result.totalScore, 2);
  assert.equal(result.maxScore, 2);
  assert.equal(result.results[0].correctOptionIndex, 0);
});

test('resolves and locks the student carried by an individualized copy', () => {
  const data = {
    exam: { id: 1, title: 'Avaliação', status: 'published', settings: {} },
    copies: [{
      id: 21,
      copy_number: 1,
      student_id: 8,
      validation_hash: 'copy-hash',
      questions_map: [],
      options_map: {},
    }],
    questions: [],
    students: [{ id: 8, name: 'Aluno vinculado', registration_number: null }],
    downloaded_at: new Date(0).toISOString(),
  };

  assert.deepEqual(individualizedStudent(data, 21), {
    studentId: 8,
    studentName: 'Aluno vinculado',
  });
  assert.equal(individualizedStudent(data, 999), null);
});
