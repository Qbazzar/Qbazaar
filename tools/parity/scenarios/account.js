// Account settings (assets/acct.js, assets/cropper.js) and the chat (assets/chat.js, assets/chatcards.js).
// Inventory ids acct.*, msg.* in spec/account.json. Nothing here saves, sends or confirms.
const { scenario, click, hover, press, clickAt, chooseFile, observe, motion } = require('./dsl');
const { inventory, refOf } = require('./spec');

const NAV_ROW = (label, href) => ({ ref: `${refOf('acct.nav.account')}:has-text("${label}")`, target: `nav a[href="${href}"]` });
const MODAL = { ref: '[style*="position: fixed"][style*="z-index: 120"] > div', target: '[role=dialog]' };
const EDIT_NAME = inventory('acct.profile.editName', 'button:has-text("Edit"):near(:text("Full name"))');
// The inventory names the name modal's ×; every other modal draws its own × (another template node), so any modal's
// × closes whichever modal is open.
const MODAL_CLOSE = { ref: `${refOf('acct.modal.close')}, ${MODAL.ref} span.qb-btn:text-is("×")`, target: '[role=dialog] button[aria-label="Close"]' };
// The target's Edit buttons carry the field in their accessible name ("Edit Email"); :near() also caught the nav's "Email Message".
const EDIT_EMAIL = inventory('acct.account.editEmail', 'main button:has-text("Edit"):has-text("Email")');
const EDIT_PASSWORD = inventory('acct.account.editPassword', 'main button:has-text("Edit"):has-text("Password")');
const TOGGLE = { ref: `${refOf('acct.dataprot.toggle0')}`, target: '[role=switch]' };
const CHANGE_PHOTO = inventory('acct.profile.changePhoto', 'input[type=file]');
const CROPPER = { ref: '.qb-crop-back > div', target: '[role=dialog]' };
const CROP_ZOOM = { ref: '.qb-crop-zoom input', target: '[role=dialog] input[type=range]' };
const THREAD = (n) => ({ ref: `.qb-mlist > div:has(.qb-av) >> nth=${n}`, target: `main button:has(span[role=img]) >> nth=${n}` });
const CHAT_MENU_BUTTON = { ref: '.qb-mchat span:text-is("⋯")', target: 'main button[aria-haspopup=menu][aria-label*="ettings"], main button[aria-label*="ore"]' };
const CHAT_MENU = inventory('msg.menu', '[role=menu]');
const COMPOSER = { ref: '.qb-mchat input[placeholder="Type your message.."]', target: 'textarea[aria-label^="Type"]' };

module.exports = [
  scenario({
    id: 'account-nav-hover-and-select',
    page: 'account',
    viewports: [1440],
    login: true,
    steps: [
      hover(NAV_ROW('Account Settings', '/account/security')),
      observe('inactive-row-hover', NAV_ROW('Account Settings', '/account/security'), { text: 'skip', crop: true }),
      click(NAV_ROW('Account Settings', '/account/security')),
      observe('active-row-after-click', NAV_ROW('Account Settings', '/account/security'), { text: 'skip', crop: true }),
    ],
  }),
  scenario({
    id: 'account-edit-name-modal',
    page: 'account',
    viewports: [1440],
    login: true,
    steps: [
      click(EDIT_NAME),
      observe('modal-open', MODAL, { crop: true }),
      click(MODAL_CLOSE),
      observe('closed-by-x', MODAL, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'account-edit-name-modal-escape',
    page: 'account',
    viewports: [1440],
    login: true,
    steps: [click(EDIT_NAME), press('Escape'), observe('after-escape', MODAL, { text: 'skip', styles: false })],
  }),
  scenario({
    id: 'account-edit-name-modal-backdrop',
    page: 'account',
    viewports: [1440],
    login: true,
    steps: [click(EDIT_NAME), clickAt(8, 8), observe('after-backdrop-click', MODAL, { text: 'skip', styles: false })],
  }),
  scenario({
    id: 'account-modal-motion',
    page: 'account',
    viewports: [1440],
    login: true,
    steps: [motion('modal-open', MODAL, click(EDIT_NAME), 600)],
  }),
  scenario({
    id: 'account-security-modals',
    page: 'account',
    viewports: [1440],
    login: true,
    targetRoute: '/account/security',
    steps: [
      click(NAV_ROW('Account Settings', '/account/security')),
      click(EDIT_EMAIL),
      observe('email-modal', MODAL, { crop: true }),
      click(MODAL_CLOSE),
      click(EDIT_PASSWORD),
      observe('password-modal', MODAL, { crop: true }),
      click(MODAL_CLOSE),
    ],
  }),
  scenario({
    id: 'account-data-protection-toggles',
    page: 'account',
    viewports: [1440],
    login: true,
    writes: true,
    targetRoute: '/account/privacy',
    steps: [
      click(NAV_ROW('Data Protection', '/account/privacy')),
      observe('switch-before', TOGGLE, { text: 'skip', crop: true }),
      click(TOGGLE),
      observe('switch-after', TOGGLE, { text: 'skip', crop: true }),
      motion('switch-slide', TOGGLE, click(TOGGLE), 500),
    ],
  }),
  scenario({
    id: 'account-photo-cropper',
    page: 'account',
    viewports: [1440],
    login: true,
    steps: [
      chooseFile(CHANGE_PHOTO),
      observe('cropper-open', CROPPER, { crop: true }),
      observe('zoom-slider', CROP_ZOOM, { text: 'skip', styles: false }),
      clickAt(6, 6),
      observe('closed-by-backdrop', CROPPER, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'messages-thread-select',
    page: 'messages',
    viewports: [1440],
    login: true,
    steps: [
      observe('first-thread-active', THREAD(0), { text: 'skip', crop: true }),
      click(THREAD(1)),
      observe('second-thread-active', THREAD(1), { text: 'skip', crop: true }),
      observe('first-thread-inactive', THREAD(0), { text: 'skip' }),
    ],
  }),
  scenario({
    id: 'messages-chat-menu',
    page: 'messages',
    viewports: [1440],
    login: true,
    steps: [
      // The reference opens with its first chat showing; the target shows the chat once a conversation is picked.
      click(THREAD(0)),
      click(CHAT_MENU_BUTTON),
      observe('menu-open', CHAT_MENU, { crop: true, count: true }),
      click(inventory('msg.menu.block', '[role=menuitem]:has-text("Block")')),
      // The dialog box itself (assets/chat.js .qb-blk), not the dimmed backdrop around it, pairs with [role=dialog].
      observe('block-dialog', { ref: '.qb-blk', target: '[role=dialog]' }, { crop: true }),
      press('Escape'),
      observe('block-closed-by-escape', inventory('msg.block.backdrop', '[role=dialog]'), { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'messages-composer-focus',
    page: 'messages',
    viewports: [1440],
    login: true,
    steps: [
      click(THREAD(0)),
      // The composer's box (r14 label) is on the target only once a conversation is open, so it is compared here,
      // not by the inventory sweep of the page as it loads.
      observe('composer-box', inventory('msg.composer', 'label:has(> textarea)'), { text: 'skip', crop: true }),
      click(COMPOSER),
      observe('composer-focused', COMPOSER, { text: 'skip', focus: true, crop: true }),
    ],
  }),
];
