const test = require('node:test');
const assert = require('node:assert/strict');
const { diffStyles } = require('../src/compare');

const base = () => ({
  color: 'rgb(51, 51, 51)', 'background-color': 'rgba(0, 0, 0, 0)', 'background-image': 'none',
  'border-width': '0px 0px 0px 0px', 'border-style': 'none none none none', 'border-color': 'rgb(0, 0, 0) rgb(0, 0, 0) rgb(0, 0, 0) rgb(0, 0, 0)',
  'border-radius': '0px 0px 0px 0px', 'box-shadow': 'none', 'outline-width': '0px', 'outline-style': 'none', 'outline-color': 'rgb(0, 0, 0)', 'outline-offset': '0px',
  opacity: '1', transform: 'none', filter: 'none', cursor: 'auto', 'font-family': 'Poppins, sans-serif', 'font-size': '16px', 'font-weight': '400', 'font-style': 'normal',
  'line-height': '24px', 'letter-spacing': '0px', 'text-transform': 'none', 'text-decoration-line': 'none', padding: '0px 0px 0px 0px', margin: '0px 0px 0px 0px', gap: 'normal normal',
  width: '100px', height: '40px', display: 'block', visibility: 'visible', 'z-index': 'auto',
  'transition-property': 'all', 'transition-duration': '0s', 'transition-timing-function': 'ease', 'transition-delay': '0s',
  'animation-name': 'none', 'animation-duration': '0s', 'animation-timing-function': 'ease', 'animation-iteration-count': '1', 'animation-delay': '0s',
});

test('identical styles produce no diff', () => {
  assert.deepEqual(diffStyles(base(), base()), []);
});

test('colours are compared exactly, sizes within half a pixel', () => {
  const target = { ...base(), color: 'rgb(51, 51, 52)', 'font-size': '16.4px' };
  assert.deepEqual(diffStyles(base(), target).map((d) => d.property), ['color']);
});

test('width and height only count for fixed-size components', () => {
  const target = { ...base(), width: '180px' };
  assert.equal(diffStyles(base(), target).length, 0);
  assert.equal(diffStyles(base(), target, { fixedSize: true })[0].property, 'width');
});

test('an undrawn outline and transparent shadow stacks are not differences', () => {
  const target = { ...base(), 'outline-color': 'rgb(1, 2, 3)', 'box-shadow': 'rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px' };
  assert.deepEqual(diffStyles(base(), target), []);
});

test('text styles are ignored where no text is drawn', () => {
  const target = { ...base(), 'font-size': '30px', color: 'rgb(0, 0, 0)' };
  assert.deepEqual(diffStyles(base(), target, { ownText: false }), []);
});

test('transition lists with the same longest duration and easing read the same', () => {
  const ref = { ...base(), 'transition-property': 'all', 'transition-duration': '0.18s' };
  const target = { ...base(), 'transition-property': 'color, background-color', 'transition-duration': '0.18s' };
  assert.deepEqual(diffStyles(ref, target), []);
  assert.equal(diffStyles(ref, { ...target, 'transition-duration': '0.3s' })[0].property, 'transition');
});

test('a translate-only transform is position, a scale is a difference', () => {
  const lifted = { ...base(), transform: 'matrix(1, 0, 0, 1, 0, -21)' };
  assert.deepEqual(diffStyles(base(), lifted), []);
  assert.equal(diffStyles(base(), { ...base(), transform: 'matrix(1.1, 0, 0, 1.1, 0, 0)' })[0].property, 'transform');
});
