// Posting an ad and the offer form (add-ads.html, offer.html, assets/selects.js, assets/buynow.js).
// Inventory ids addads.*, offer.* in spec/sell.json. Nothing here submits a form.
const { scenario, click, hover, press, clickAt, observe, expectRoute } = require('./dsl');
const { inventory } = require('./spec');

const OFFERING = inventory('addads.adtype.offering', 'label:has(input[name="ad_type"][value="offering"])');
const LOOKING = inventory('addads.adtype.looking', 'label:has(input[name="ad_type"][value="wanted"])');
const PICKUP = inventory('addads.shipping.pickup', 'label:has(input[name="shipping"][value="pickup_only"])');
const DELIVERY = inventory('addads.shipping.delivery', 'label:has(input[name="shipping"][value="delivery"])');
const PRICE_TYPE = inventory('addads.pricetype.select', '#post-ad-price-type');
const CITY = inventory('addads.city.select', '#post-ad-locationId');
const DROPDOWN_PANEL = { ref: '.qb-ddpanel', target: '[role=listbox], [role=menu]' };
const CATEGORY_BUTTON = inventory('addads.category.select', 'button:text-is("Select")');
const CATEGORY_MODAL = inventory('addads.catmodal.panel', '[role=dialog]');
const PUSH_UP = inventory('addads.highlight.pushup', 'label:has-text("Push")');
const OFFER_CHIP = inventory('offer.preset.chip', 'button[aria-label*="below the asking price"]');
const OFFER_MESSAGE = inventory('offer.input.message', 'textarea');
const OFFER_CANCEL = inventory('offer.btn.cancel', 'a:text-is("Cancel")');

module.exports = [
  scenario({
    id: 'addads-ad-type',
    page: 'add-ads',
    viewports: [1440, 390],
    login: true,
    steps: [
      observe('offering-selected-by-default', OFFERING, { text: 'skip', crop: true }),
      click(LOOKING),
      observe('looking-selected', LOOKING, { text: 'skip', crop: true }),
      observe('offering-unselected', OFFERING, { text: 'skip' }),
    ],
  }),
  scenario({
    id: 'addads-shipping',
    page: 'add-ads',
    viewports: [1440, 390],
    login: true,
    steps: [
      observe('pickup-selected-by-default', PICKUP, { text: 'skip', crop: true }),
      click(DELIVERY),
      observe('delivery-selected', DELIVERY, { text: 'skip', crop: true }),
      observe('pickup-unselected', PICKUP, { text: 'skip' }),
    ],
  }),
  scenario({
    id: 'addads-price-type-dropdown',
    page: 'add-ads',
    viewports: [1440],
    login: true,
    steps: [
      click(PRICE_TYPE),
      observe('panel', DROPDOWN_PANEL, { crop: true, count: true }),
      hover({ ref: '.qb-ddpanel .qb-dditem:nth-child(2)', target: '[role=option]:nth-child(2)' }),
      observe('option-hover', { ref: '.qb-ddpanel .qb-dditem:nth-child(2)', target: '[role=option]:nth-child(2)' }, { crop: true }),
      press('Escape'),
      observe('closed-by-escape', DROPDOWN_PANEL, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'addads-city-dropdown',
    page: 'add-ads',
    viewports: [1440],
    login: true,
    steps: [
      click(CITY),
      observe('panel', DROPDOWN_PANEL, { crop: true, count: true }),
      clickAt(30, 600),
      observe('closed-by-outside-click', DROPDOWN_PANEL, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'addads-category-modal',
    page: 'add-ads',
    viewports: [1440, 390],
    login: true,
    steps: [
      click(CATEGORY_BUTTON),
      observe('modal-open', CATEGORY_MODAL, { crop: true }),
      press('Escape'),
      observe('closed-by-escape', CATEGORY_MODAL, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'addads-boost-toggle',
    page: 'add-ads',
    viewports: [1440],
    login: true,
    steps: [
      observe('unchecked', PUSH_UP, { text: 'skip', crop: true }),
      click(PUSH_UP),
      observe('checked', PUSH_UP, { text: 'skip', crop: true }),
      click(PUSH_UP),
      observe('unchecked-again', PUSH_UP, { text: 'skip' }),
    ],
  }),
  scenario({
    id: 'offer-preset-chip',
    page: 'offer',
    viewports: [1440, 390],
    login: true,
    steps: [
      observe('chip-resting', OFFER_CHIP, { text: 'skip' }),
      hover(OFFER_CHIP),
      observe('chip-hover', OFFER_CHIP, { text: 'skip' }),
      click(OFFER_CHIP),
      observe('chip-selected', OFFER_CHIP, { text: 'skip', crop: true }),
      click(OFFER_MESSAGE),
      observe('message-focused', OFFER_MESSAGE, { text: 'skip', focus: true, crop: true }),
    ],
  }),
  scenario({
    id: 'offer-cancel',
    page: 'offer',
    viewports: [1440],
    login: true,
    steps: [click(OFFER_CANCEL), expectRoute('cancel-returns-to-product', 'product')],
  }),
];
