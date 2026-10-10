// Auth pages (assets/auth.js): password eye, language pill, OTP boxes.
const { scenario, click, type, press, observe } = require('./dsl');

const PASSWORD = { ref: 'input#pw', target: 'input[name="password"]' };
const EYE = { ref: '.qb-eye', target: 'button[aria-label="Show or hide password"]' };
const LANGUAGE_PILL = { ref: '.qb-lang', target: 'header button[aria-label]' };
const LANGUAGE_PANEL = { ref: '.qb-langpanel', target: 'header div.absolute, [role=listbox], [role=menu]' };
const OTP_BOXES = { ref: '.qb-otp input', target: 'input[autocomplete="one-time-code"], input[inputmode="numeric"]' };

module.exports = [
  scenario({
    id: 'login-password-eye',
    page: 'login',
    viewports: [1440, 390],
    steps: [
      type(PASSWORD, 'secret'),
      observe('hidden-by-default', PASSWORD, { text: 'skip', props: ['type'], styles: false }),
      click(EYE),
      observe('shown-after-eye', PASSWORD, { text: 'skip', props: ['type'], styles: false }),
      observe('eye-button', EYE, { text: 'skip', crop: true }),
      click(EYE),
      observe('hidden-again', PASSWORD, { text: 'skip', props: ['type'], styles: false }),
    ],
  }),
  scenario({
    id: 'login-language-pill',
    page: 'login',
    viewports: [1440],
    steps: [
      observe('pill', LANGUAGE_PILL, { text: 'skip', crop: true }),
      click(LANGUAGE_PILL),
      observe('language-panel', LANGUAGE_PANEL, { crop: true, count: true }),
    ],
  }),
  scenario({
    id: 'signup-otp-boxes',
    page: 'signup-verify',
    viewports: [1440, 390],
    writes: true,
    steps: [
      click(OTP_BOXES),
      type(OTP_BOXES, '1'),
      observe('focus-moved-to-next', { ref: '.qb-otp input:focus', target: 'input[autocomplete="one-time-code"]:focus, input[inputmode="numeric"]:focus' }, { text: 'skip', styles: false }),
      press('Backspace'),
      press('Backspace'),
      observe('focus-back-on-backspace', { ref: '.qb-otp input:focus', target: 'input[inputmode="numeric"]:focus' }, { text: 'skip', styles: false }),
    ],
  }),
];
