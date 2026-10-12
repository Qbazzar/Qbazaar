const test = require('node:test');
const assert = require('node:assert/strict');
const { pairTexts, isUserData } = require('../src/matching');
const { shapeScore, classify } = require('../src/icons');
const { parseKeyframes } = require('../src/capture');
const { compareKeyframes, compareSequences } = require('../src/motion');
const { buildConfig } = require('../src/config');

const row = (key, relTop, extra = {}) => ({ key, text: key, relTop, relLeft: 0.1, ...extra });

test('texts pair by key and nearest page position', () => {
  const { pairs, unmatchedRef } = pairTexts([row('view all', 0.1), row('view all', 0.6), row('gone', 0.2)], [row('view all', 0.62), row('view all', 0.12)]);
  assert.equal(pairs.length, 2);
  assert.equal(pairs[0].target.relTop, 0.12);
  assert.equal(unmatchedRef[0].key, 'gone');
});

test('prices and long copy are user data', () => {
  assert.equal(isUserData('QAR 285,000'), true);
  assert.equal(isUserData('Browse Categories'), false);
});

test('shape score separates a glyph from a different one', () => {
  const dot = (x, y) => Array.from({ length: 48 * 48 }, (_, i) => (Math.abs((i % 48) - x) < 3 && Math.abs(Math.floor(i / 48) - y) < 3 ? '1' : '0')).join('');
  assert.equal(classify(shapeScore(dot(10, 10), dot(10, 10))), 'same');
  assert.equal(classify(shapeScore(dot(10, 10), dot(40, 40))), 'different');
});

test('keyframes are parsed and compared by content', () => {
  const css = '@keyframes qbFade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; } }';
  const [fade] = parseKeyframes(css, 'styles.css', 0);
  assert.equal(fade.name, 'qbFade');
  assert.equal(compareKeyframes([fade], ['qbFade'], [{ ...fade, name: 'other' }]).length, 0);
  assert.equal(compareKeyframes([fade], ['qbFade'], []).length, 1);
});

test('sequences differ in duration and easing', () => {
  const linear = { moved: true, key: 'ty', duration: 300, curve: [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1] };
  assert.equal(compareSequences(linear, { ...linear }).length, 0);
  assert.equal(compareSequences(linear, { ...linear, duration: 600 }).length, 1);
  assert.equal(compareSequences(linear, { ...linear, curve: linear.curve.map((v) => v * v) }).length, 1);
});

test('config requires a target and resolves viewports', () => {
  assert.throws(() => buildConfig([]));
  const config = buildConfig(['--target', 'http://localhost:3000', '--viewports', '390']);
  assert.equal(config.localTarget, true);
  assert.deepEqual(config.viewports, [{ width: 390, height: 844 }]);
});
