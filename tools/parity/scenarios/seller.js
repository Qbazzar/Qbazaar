// Seller page tabs (seller-organization.html; tb.select in the engine).
const { scenario, click, observe } = require('./dsl');

// The prototype's tab is the 120 px flex span; its label sits in the template's inner span.sc-interp, which
// span:text-is() would pick and which has no box of its own.
const tab = (label) => ({ ref: `span[style*="flex: 1 1 120px"]:has-text("${label}")`, target: `[role=tab]:has-text("${label}")` });

module.exports = [
  scenario({
    id: 'seller-organization-tabs',
    page: 'seller-organization',
    viewports: [1440, 390],
    steps: [
      observe('about-unselected', tab('About us'), { text: 'skip' }),
      click(tab('About us')),
      observe('about-selected', tab('About us'), { text: 'skip', attributes: true, crop: true }),
      click(tab('Legal Info')),
      observe('legal-selected', tab('Legal Info'), { text: 'skip', attributes: true, crop: true }),
      observe('about-after-leaving', tab('About us'), { text: 'skip' }),
    ],
  }),
];
