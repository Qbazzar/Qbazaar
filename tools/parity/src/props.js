// The property catalogue: which computed properties are captured, how they group into report
// categories and how each kind of value is compared.
const SIDES = ['top', 'right', 'bottom', 'left'];
const CORNERS = ['top-left', 'top-right', 'bottom-right', 'bottom-left'];

const CATEGORY_OF = {
  typography: ['font-family', 'rendered-font', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'text-transform', 'text-decoration-line'],
  colour: ['color', 'background-color', 'background-image', 'border-color', 'fill', 'stroke'],
  spacing: ['padding', 'margin', 'gap', 'border-width', 'border-radius', 'width', 'height'],
  states: ['box-shadow', 'outline', 'opacity', 'transform', 'filter', 'cursor', 'display', 'visibility', 'z-index', 'border-style', 'state-change'],
  motion: ['transition', 'animation', 'keyframes', 'js-motion'],
};

const SEVERITY_OF = {
  high: ['font-family', 'rendered-font', 'font-size', 'font-weight', 'color', 'background-color', 'background-image', 'border-color', 'display', 'visibility', 'width', 'height'],
  medium: ['line-height', 'letter-spacing', 'padding', 'gap', 'border-width', 'border-radius', 'box-shadow', 'opacity', 'transform', 'cursor', 'text-transform', 'fill', 'stroke', 'margin'],
};

function categoryOf(property) {
  const name = property.replace(/^.*::?placeholder /, '');
  return Object.keys(CATEGORY_OF).find((c) => CATEGORY_OF[c].includes(name)) || 'states';
}

function severityOf(property) {
  const name = property.replace(/^.*::?placeholder /, '');
  if (SEVERITY_OF.high.includes(name)) return 'high';
  return SEVERITY_OF.medium.includes(name) ? 'medium' : 'low';
}

const COLOUR_PROPS = new Set(['color', 'background-color', 'border-color', 'fill', 'stroke']);
const PX_TOLERANCE_PROPS = new Set(['font-size', 'line-height', 'letter-spacing', 'padding', 'margin', 'gap', 'border-width', 'border-radius', 'width', 'height']);

module.exports = { SIDES, CORNERS, CATEGORY_OF, categoryOf, severityOf, COLOUR_PROPS, PX_TOLERANCE_PROPS };
