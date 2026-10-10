// Seller page tabs (seller-organization.html; tb.select in the engine).
const { scenario, click, observe } = require('./dsl');

const tab = (label) => ({ ref: `span:text-is("${label}")`, target: `[role=tab]:has-text("${label}")` });

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
