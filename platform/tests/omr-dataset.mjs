import Ajv2020 from 'ajv/dist/2020.js';
import { chromium } from '@playwright/test';
import { build } from 'esbuild';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { isDeepStrictEqual } from 'node:util';

import { classifyBubble, selectAnswer } from '../../duoscanner/src/lib/bubble-classifier.ts';

const root = path.resolve('..');
const contractDirectory = path.join(root, 'contracts', 'omr');
const manifests = [
  JSON.parse(readFileSync(path.join(contractDirectory, 'dataset-tuning.v1.json'), 'utf8')),
  JSON.parse(readFileSync(path.join(contractDirectory, 'dataset-holdout.v1.json'), 'utf8')),
];
const requestedSplit = process.argv.find((argument) => argument.startsWith('--split='))?.split('=')[1] ?? 'tuning';
if (!['tuning', 'holdout'].includes(requestedSplit)) fail(`Split desconhecido: ${requestedSplit}.`);
const activeManifests = manifests.filter((manifest) => manifest.split === requestedSplit);
const manifestSchema = JSON.parse(readFileSync(path.join(contractDirectory, 'dataset-manifest.schema.json'), 'utf8'));
const resultSchema = JSON.parse(readFileSync(path.join(contractDirectory, 'engine-result.schema.json'), 'utf8'));
const thresholds = JSON.parse(readFileSync(path.join(contractDirectory, 'dataset-thresholds.v1.json'), 'utf8'));
const thresholdBytes = readFileSync(path.join(contractDirectory, 'dataset-thresholds.v1.json'));
const excludedAssets = JSON.parse(readFileSync(path.join(contractDirectory, 'excluded-assets.v1.json'), 'utf8'));
const expectedBaseline = JSON.parse(readFileSync(path.join(contractDirectory, 'dataset-baseline.expected.json'), 'utf8'));
const thresholdHash = sha256(thresholdBytes);
const qrContractHash = sha256(readFileSync(path.join(contractDirectory, 'qr-payload.schema.json')));
const dependencyHash = sha256(Buffer.concat([
  readFileSync('package-lock.json'),
  readFileSync(path.join(root, 'duoscanner', 'package-lock.json')),
]));
const gitSha = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();

if (requestedSplit === 'holdout') {
  if (!process.argv.includes('--confirm-frozen-profile')) fail('O holdout exige confirmação explícita do perfil congelado.');
  if (!thresholds.frozen_before_holdout || expectedBaseline.threshold_profile_hash !== thresholdHash) {
    fail('O holdout não pode ser aberto: o perfil não está congelado no baseline esperado.');
  }
}

const ajv = new Ajv2020({ allErrors: true, strict: true });
const validateManifest = ajv.compile(manifestSchema);
const validateResult = ajv.compile(resultSchema);
for (const manifest of manifests) {
  if (!validateManifest(manifest)) fail(`Manifest ${manifest.dataset_id} inválido`, validateManifest.errors);
  validateManifestSemantics(manifest);
}

const tuningGroups = new Set(manifests.find((manifest) => manifest.split === 'tuning').samples.map((sample) => sample.group_id));
const holdoutGroups = manifests.find((manifest) => manifest.split === 'holdout').samples.map((sample) => sample.group_id);
if (holdoutGroups.some((group) => tuningGroups.has(group))) fail('Há vazamento de group_id entre tuning e holdout.');

verifyExcludedAssets(excludedAssets);

const generated = activeManifests.flatMap((manifest) => manifest.samples.map((sample) => ({
  manifest,
  sample,
  image: loadSampleImage(sample),
})));
const hashMismatches = generated.filter(({ sample, image }) => sample.source.sha256 !== sha256(image.gray));
if (hashMismatches.length > 0) {
  console.error(JSON.stringify({
    hash_mismatches: hashMismatches.map(({ sample, image }) => ({ id: sample.id, expected: sample.source.sha256, actual: sha256(image.gray) })),
  }, null, 2));
  process.exit(2);
}

const mobileResults = generated.map(({ manifest, sample, image }) => normalizeResult(
  manifest,
  sample,
  'mobile-classifier',
  classifyMobile(sample, image),
));
const temporaryDirectory = mkdtempSync(path.join(tmpdir(), 'tembo-omr-dataset-'));
const bundlePath = path.join(temporaryDirectory, 'web-adapter.js');
await build({
  entryPoints: [path.resolve('tests/support/omr-web-adapter.ts')],
  bundle: true,
  format: 'iife',
  outfile: bundlePath,
  platform: 'browser',
  target: 'es2022',
  logLevel: 'silent',
});

const server = createServer((request, response) => {
  const files = {
    '/opencv.js': path.resolve('public/vendor/opencv/opencv-4.8.0.js'),
    '/web-adapter.js': bundlePath,
  };
  if (!files[request.url]) {
    response.writeHead(200, { 'Content-Type': 'text/html' });
    response.end('<!doctype html><title>Tembo OMR dataset</title>');
    return;
  }
  response.writeHead(200, { 'Content-Type': 'text/javascript' });
  response.end(readFileSync(files[request.url]));
});

let browser;
let webResults;
try {
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto(`http://127.0.0.1:${server.address().port}/`);
  await page.addScriptTag({ url: '/opencv.js' });
  await page.waitForFunction(() => globalThis.cv?.Mat && globalThis.cv?.matFromArray, null, { timeout: 60_000 });
  await page.addScriptTag({ url: '/web-adapter.js' });

  webResults = [];
  for (const { manifest, sample, image } of generated) {
    const questions = await page.evaluate(({ payload, classifierThresholds }) => globalThis.TemboOmrDatasetWeb.run(payload, classifierThresholds), {
      payload: {
        gray_base64: image.gray.toString('base64'),
        width: image.width,
        height: image.height,
        contract: sample.contract,
      },
      classifierThresholds: {
        mark: thresholds.classification.web.mark,
        blank: thresholds.classification.web.blank,
        uncertain_low: thresholds.classification.web.uncertain_low,
        uncertain_high: thresholds.classification.web.uncertain_high,
      },
    });
    webResults.push(normalizeResult(manifest, sample, 'web-classifier', questions));
  }
} finally {
  if (browser) await browser.close();
  await new Promise((resolve) => server.close(resolve));
  if (temporaryDirectory.startsWith(path.join(tmpdir(), 'tembo-omr-dataset-'))) {
    rmSync(temporaryDirectory, { recursive: true, force: true });
  }
}

const results = [...mobileResults, ...webResults];
for (const result of results) {
  if (!validateResult(result)) fail(`Resultado ${result.engine.adapter}/${result.sample_id} inválido`, validateResult.errors);
}

const report = {
  schema_version: 1,
  baseline_id: 'omr-synthetic-baseline-v1',
  evaluated_split: requestedSplit,
  evidence_class: 'synthetic_classifier_only',
  git_sha: gitSha,
  threshold_profile_hash: thresholdHash,
  manifests: activeManifests.map((manifest) => ({
    id: manifest.dataset_id,
    split: manifest.split,
    samples: manifest.samples.length,
    sha256: sha256(Buffer.from(JSON.stringify(manifest))),
  })),
  excluded_assets: {
    inventory_id: excludedAssets.inventory_id,
    count: excludedAssets.assets.length,
    sha256: sha256(Buffer.from(JSON.stringify(excludedAssets))),
    included_in_metrics: false,
  },
  pipeline_coverage: {
    web: {
      status: 'classifier_component_only',
      exercised: 'production OmrEngine.readBubbles and assessQuality with real OpenCV',
      not_exercised: ['qr_decode', 'fiducial_detection', 'perspective_correction', 'student_association'],
    },
    mobile: {
      status: 'classifier_component_only',
      exercised: 'production bubble-classifier classifyBubble and selectAnswer',
      not_exercised: ['expo_capture', 'image_decode', 'homography', 'qr_decode', 'student_association'],
      known_blocker: 'RGBA buffers are indexed as one byte per pixel in the full production processor; tracked by OMR-002.',
    },
  },
  metrics: ['mobile-classifier', 'web-classifier'].flatMap((adapter) => activeManifests.map((manifest) => evaluate(
    adapter,
    manifest,
    results.filter((result) => result.engine.adapter === adapter && result.dataset_id === manifest.dataset_id),
    thresholds.acceptance_gates,
  ))),
  parity: parity(results),
  unmeasured_gates: [
    { id: 'qr_first_attempt', target: thresholds.acceptance_gates.qr_first_attempt_min, status: 'pending_physical_pipeline' },
    { id: 'qr_two_attempts', target: thresholds.acceptance_gates.qr_two_attempts_min, status: 'pending_physical_pipeline' },
    { id: 'silent_association_error', target: thresholds.acceptance_gates.silent_association_error_max, status: 'pending_full_pipeline' },
  ],
  physical_holdout: {
    status: 'pending_human_collection',
    required_captures_min: 200,
    required_phone_classes: 3,
    required_printers: 3,
    commercial_homologation: false,
  },
};

const observedBaseline = baselineSummary(report);
const splitBaseline = expectedBaselineForSplit(expectedBaseline, requestedSplit);
if (!isDeepStrictEqual(observedBaseline, splitBaseline)) {
  fail('O baseline sintético mudou. Revise a alteração e atualize dataset-baseline.expected.json explicitamente.', {
    expected: splitBaseline,
    observed: observedBaseline,
  });
}

console.log(JSON.stringify(report, null, 2));

if (process.argv.includes('--require-gates') && (
  report.metrics.some((entry) => Object.values(entry.gates).includes(false))
  || report.unmeasured_gates.some((entry) => entry.status !== 'passed')
)) {
  process.exitCode = 3;
}

function loadSampleImage(sample) {
  if (sample.source.kind !== 'synthetic') {
    fail(`O adapter físico ainda não existe; ${sample.id} não pode ser sintetizada silenciosamente.`);
  }

  const width = sample.capture.width;
  const height = sample.capture.height;
  const gray = new Uint8Array(width * height).fill(255);
  const [gx, gy, , row, bubble, option] = sample.contract.g;
  const startX = (gx / 10_000) * width;
  const startY = (gy / 10_000) * height;
  const rowSpacing = (row / 10_000) * height;
  const bubbleSize = (bubble / 10_000) * width;
  const optionSpacing = (option / 10_000) * width;
  const rois = [];

  sample.ground_truth.questions.forEach((question, questionIndex) => {
    const questionRois = question.recipe.map((recipe, optionIndex) => {
      const roi = {
        x: Math.round(startX + optionIndex * optionSpacing),
        y: Math.round(startY + questionIndex * rowSpacing),
        w: Math.round(bubbleSize),
        h: Math.round(bubbleSize),
      };
      drawRecipe(gray, width, height, roi, recipe, question.position * 31 + optionIndex * 17);
      return roi;
    });
    rois.push(questionRois);
  });

  return { gray: Buffer.from(gray), width, height, rois };
}

function drawRecipe(gray, width, height, roi, recipe, seed) {
  const ratios = { empty: 0.06, strong: 0.72, weak: 0.29, erased: 0.17, ambiguous: 0.37 };
  const ratio = ratios[recipe];
  const total = roi.w * roi.h;
  const dark = Math.round(total * ratio);
  for (let index = 0; index < total; index += 1) {
    const x = index % roi.w;
    const y = Math.floor(index / roi.w);
    const rank = (index * 37 + seed) % total;
    const targetX = roi.x + x;
    const targetY = roi.y + y;
    if (targetX >= 0 && targetX < width && targetY >= 0 && targetY < height) {
      gray[targetY * width + targetX] = rank < dark ? 25 : 245;
    }
  }
}

function classifyMobile(sample, image) {
  const mappedThresholds = {
    mark: thresholds.classification.mobile.mark,
    blank: thresholds.classification.mobile.blank,
    uncertainLow: thresholds.classification.mobile.uncertain_low,
    uncertainHigh: thresholds.classification.mobile.uncertain_high,
  };
  return sample.ground_truth.questions.map((question, index) => {
    const bubbles = image.rois[index].map((roi) => classifyBubble(
      image.gray,
      image.width,
      image.height,
      roi,
      null,
      128,
      mappedThresholds,
    ));
    const answer = selectAnswer(bubbles, mappedThresholds);
    return {
      position: question.position,
      status: answer.type,
      selected_index: answer.optionIndex,
      marked_indices: answer.optionIndices,
      confidence: answer.confidence,
      fill_ratios: answer.fillRatios,
      action: 'review',
    };
  });
}

function normalizeResult(manifest, sample, adapter, questions) {
  return {
    schema_version: 1,
    dataset_id: manifest.dataset_id,
    sample_id: sample.id,
    engine: {
      adapter,
      version: 'baseline-v1',
      git_sha: gitSha,
      dependency_hash: dependencyHash,
      threshold_profile_hash: thresholdHash,
    },
    outcome: 'processed',
    path: 'classifier_only',
    qr: { detected: false, structurally_valid: false, authenticated: null },
    association: { status: 'unknown', exam_id: null, copy_id: null, student_id: null, page: sample.contract.page },
    fiducials: { found: 0, confidence: 0 },
    questions: questions.map((question) => {
      const status = question.status === 'weak' || question.status === 'erased' ? 'ambiguous' : question.status;
      return {
        ...question,
        status,
        action: decisionAction(status, question.confidence),
      };
    }),
    duration_ms: 0,
    error_code: null,
  };
}

function evaluate(adapter, manifest, adapterResults, gates) {
  const truths = manifest.samples.flatMap((sample) => sample.ground_truth.questions.map((question) => ({ sample_id: sample.id, ...question })));
  const predictions = new Map(adapterResults.flatMap((result) => result.questions.map((question) => [`${result.sample_id}:${question.position}`, question])));
  let answerable = 0;
  let correctAnswers = 0;
  let correctDecisions = 0;
  let autoAccepted = 0;
  let correctAutoAccepted = 0;
  let confidentErrors = 0;
  let reviewExpected = 0;
  let reviewReferred = 0;

  for (const truth of truths) {
    const prediction = predictions.get(`${truth.sample_id}:${truth.position}`);
    const answerCorrect = prediction?.status === truth.state && prediction.selected_index === truth.selected_index;
    const decisionCorrect = truth.expected_action === 'review'
      ? prediction?.action === 'review'
      : prediction?.action === 'accept' && answerCorrect;
    if (truth.expected_action === 'accept') {
      answerable += 1;
      if (answerCorrect) correctAnswers += 1;
    }
    if (decisionCorrect) correctDecisions += 1;
    if (prediction?.action === 'accept') {
      autoAccepted += 1;
      if (decisionCorrect) correctAutoAccepted += 1;
      else confidentErrors += 1;
    }
    if (truth.expected_action === 'review') {
      reviewExpected += 1;
      if (prediction?.action === 'review') reviewReferred += 1;
    }
  }

  const total = truths.length;
  const questionAccuracy = ratio(correctAnswers, answerable);
  const decisionAccuracy = ratio(correctDecisions, total);
  const autoAcceptAccuracy = ratio(correctAutoAccepted, autoAccepted);
  const confidentError = ratio(confidentErrors, autoAccepted);
  const ambiguityReferral = ratio(reviewReferred, reviewExpected);
  return {
    adapter,
    split: manifest.split,
    samples: manifest.samples.length,
    questions: total,
    question_accuracy: metric(questionAccuracy, answerable),
    decision_accuracy: metric(decisionAccuracy, total),
    auto_accept_accuracy: metric(autoAcceptAccuracy, autoAccepted),
    auto_accept_coverage: metric(ratio(autoAccepted, total), total),
    confident_error: metric(confidentError, autoAccepted),
    ambiguity_referral: metric(ambiguityReferral, reviewExpected),
    gates: {
      question_accuracy: questionAccuracy >= gates.question_accuracy_min,
      auto_accept_accuracy: autoAccepted > 0 && autoAcceptAccuracy >= gates.auto_accept_accuracy_min,
      confident_error: autoAccepted > 0 && confidentError <= gates.confident_error_max,
      ambiguity_referral: reviewExpected > 0 && ambiguityReferral >= gates.ambiguity_referral_min,
    },
  };
}

function parity(results) {
  const byKey = new Map();
  for (const result of results) {
    for (const question of result.questions) {
      const key = `${result.dataset_id}:${result.sample_id}:${question.position}`;
      byKey.set(key, { ...(byKey.get(key) ?? {}), [result.engine.adapter]: question });
    }
  }
  const pairs = [...byKey.values()].filter((entry) => entry['mobile-classifier'] && entry['web-classifier']);
  const equal = pairs.filter((entry) => ['status', 'selected_index', 'action'].every(
    (field) => entry['mobile-classifier'][field] === entry['web-classifier'][field],
  )).length;
  return { compared_questions: pairs.length, exact_matches: equal, rate: ratio(equal, pairs.length) };
}

function decisionAction(status, confidence) {
  if (status === 'error' || confidence < thresholds.decision.rescan_below) return 'rescan';
  if (status === 'multiple_marks' || status === 'ambiguous') return 'review';
  return confidence >= thresholds.decision.auto_accept ? 'accept' : 'review';
}

function metric(value, n) {
  return { value, n, wilson_95: wilson(value, n) };
}

function wilson(value, n) {
  if (n === 0) return [0, 0];
  const z = 1.959963984540054;
  const denominator = 1 + z * z / n;
  const center = (value + z * z / (2 * n)) / denominator;
  const margin = z * Math.sqrt((value * (1 - value) + z * z / (4 * n)) / n) / denominator;
  return [Math.max(0, center - margin), Math.min(1, center + margin)];
}

function ratio(numerator, denominator) {
  return denominator === 0 ? 0 : numerator / denominator;
}

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function verifyExcludedAssets(inventory) {
  if (inventory.policy !== 'excluded_from_all_metrics_until_provenance_rights_consent_and_ground_truth_are_documented') {
    fail('A política dos ativos excluídos foi alterada sem revisão explícita.');
  }

  for (const asset of inventory.assets) {
    const absolutePath = path.join(root, asset.path);
    const bytes = readFileSync(absolutePath);
    if (bytes.length !== asset.bytes || sha256(bytes) !== asset.sha256) {
      fail(`Ativo excluído mudou sem nova classificação: ${asset.path}`);
    }
  }
}

function validateManifestSemantics(manifest) {
  if (manifest.threshold_profile !== thresholds.profile_id) {
    fail(`Perfil de limiares divergente em ${manifest.dataset_id}.`);
  }

  for (const sample of manifest.samples) {
    if (sample.contract.qr_contract_hash !== qrContractHash) fail(`Hash do contrato QR divergente em ${sample.id}.`);
    const templateContract = {
      slug: sample.contract.template_slug,
      version: sample.contract.template_version,
      g: sample.contract.g,
      rpp: sample.contract.rpp,
      oc: sample.contract.oc,
    };
    if (sample.contract.template_hash !== sha256(Buffer.from(JSON.stringify(templateContract)))) {
      fail(`Hash do template divergente em ${sample.id}.`);
    }
    if (
      sample.contract.page > sample.contract.total_pages
      || sample.contract.qe < sample.contract.qs
    ) {
      fail(`Paginação inválida em ${sample.id}.`);
    }
    const expectedQuestions = sample.contract.qe - sample.contract.qs + 1;
    if (expectedQuestions !== sample.ground_truth.questions.length || expectedQuestions !== sample.contract.oc.length) {
      fail(`Faixa, ground truth e oc divergentes em ${sample.id}.`);
    }

    sample.ground_truth.questions.forEach((question, index) => {
      const expectedPosition = sample.contract.qs + index;
      const optionCount = Number(sample.contract.oc[index]);
      const indexes = [...question.marked_indices, ...(question.selected_index === null ? [] : [question.selected_index])];
      if (
        question.position !== expectedPosition
        || question.recipe.length !== optionCount
        || indexes.some((markedIndex) => markedIndex >= optionCount)
      ) {
        fail(`Posição ou alternativas divergentes em ${sample.id}, questão ${question.position}.`);
      }
      validateTruthCoherence(sample, question);
    });

    if (sample.source.kind === 'physical') {
      if (sample.source.consent !== 'documented' || sample.source.pii === 'contains_personal_data') {
        fail(`Amostra física sem consentimento documentado ou anonimização: ${sample.id}.`);
      }
      if (
        sample.ground_truth.annotation.method !== 'manual_double_annotation'
        || sample.ground_truth.annotation.annotators.length < 2
        || !sample.ground_truth.annotation.adjudicated
        || !sample.ground_truth.annotation.adjudicator
      ) {
        fail(`Amostra física sem dupla anotação: ${sample.id}.`);
      }
    }
  }
}

function validateTruthCoherence(sample, question) {
  const markedRecipes = question.marked_indices.map((index) => question.recipe[index]);
  const invalid = (
    (question.state === 'answered' && (
      question.expected_action !== 'accept'
      || question.selected_index === null
      || question.marked_indices.length !== 1
      || question.marked_indices[0] !== question.selected_index
      || question.recipe[question.selected_index] !== 'strong'
    ))
    || (question.state === 'blank' && (
      question.expected_action !== 'accept'
      || question.selected_index !== null
      || question.marked_indices.length !== 0
      || question.recipe.some((recipe) => recipe !== 'empty')
    ))
    || (question.state === 'weak' && (
      question.expected_action !== 'review'
      || question.selected_index === null
      || question.recipe[question.selected_index] !== 'weak'
    ))
    || (question.state === 'erased' && (
      question.expected_action !== 'review'
      || question.selected_index !== null
      || !markedRecipes.includes('erased')
    ))
    || (question.state === 'multiple_marks' && (
      question.expected_action !== 'review'
      || question.selected_index !== null
      || question.marked_indices.length < 2
      || markedRecipes.some((recipe) => recipe !== 'strong')
    ))
    || (question.state === 'ambiguous' && (
      question.expected_action !== 'review'
      || question.selected_index !== null
      || !markedRecipes.includes('ambiguous')
    ))
  );
  if (invalid) fail(`Ground truth incoerente em ${sample.id}, questão ${question.position}.`);
}

function baselineSummary(report) {
  return {
    schema_version: 1,
    baseline_id: report.baseline_id,
    threshold_profile_hash: report.threshold_profile_hash,
    manifests: report.manifests.map(({ id, split, sha256: hash }) => ({ id, split, sha256: hash })),
    metrics: report.metrics.map((entry) => ({
      adapter: entry.adapter,
      split: entry.split,
      question_accuracy: entry.question_accuracy.value,
      decision_accuracy: entry.decision_accuracy.value,
      auto_accept_accuracy: entry.auto_accept_accuracy.value,
      auto_accept_coverage: entry.auto_accept_coverage.value,
      confident_error: entry.confident_error.value,
      ambiguity_referral: entry.ambiguity_referral.value,
      gates: entry.gates,
    })),
    parity: report.parity,
    unmeasured_gates: report.unmeasured_gates,
  };
}

function expectedBaselineForSplit(baseline, split) {
  const { parity, parity_by_split: parityBySplit, ...shared } = baseline;
  return {
    ...shared,
    manifests: baseline.manifests.filter((manifest) => manifest.split === split),
    metrics: baseline.metrics.filter((metricEntry) => metricEntry.split === split),
    parity: parityBySplit?.[split] ?? parity,
  };
}

function fail(message, details = null) {
  console.error(message, details ? JSON.stringify(details, null, 2) : '');
  process.exit(1);
}
