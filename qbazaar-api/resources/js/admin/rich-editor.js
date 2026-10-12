import '../../css/rich-editor.css';

import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import { Placeholder } from '@tiptap/extensions';

import { GROUPS, iconFor } from './rich-editor-toolbar.js';

const SAFE_LINK = /^(https?:\/\/|mailto:|tel:|\/|#)/i;
const BARE_DOMAIN = /^[\w-]+(\.[\w-]+)+(\/|$)/;

/** Turns what an editor typed into a link we accept, or null when it is unusable. */
export function normaliseUrl(raw) {
    const url = raw.trim();
    if (url === '') return '';
    if (SAFE_LINK.test(url)) return url;
    return BARE_DOMAIN.test(url) ? `https://${url}` : null;
}

const isExternal = (url) => /^https?:\/\//i.test(url) && new URL(url).host !== window.location.host;

function el(tag, attrs = {}, html = '') {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
    node.innerHTML = html;
    return node;
}

class RichEditor {
    constructor(root) {
        this.root = root;
        this.textarea = root.querySelector('textarea');
        this.labels = JSON.parse(root.dataset.labels || '{}');
        this.dir = root.dataset.dir || 'rtl';
        this.lang = root.dataset.lang || 'ar';
        this.buttons = new Map();
        this.sourceMode = false;
        this.build();
        this.bind();
    }

    build() {
        const { textarea, root } = this;
        this.fieldLabel = document.querySelector(`label[for="${textarea.id}"]`);
        if (this.fieldLabel && !this.fieldLabel.id) this.fieldLabel.id = `${textarea.id}-label`;

        // A hidden required textarea would block submit with an unfocusable control.
        textarea.removeAttribute('required');
        this.toolbar = this.buildToolbar();
        this.linkBar = this.buildLinkBar();
        this.surface = el('div', { class: 'qb-rte-surface' });
        this.frame = el('div', { class: 'qb-rte-frame' });
        this.frame.append(this.toolbar, this.linkBar, this.surface);
        if (root.dataset.invalid === '1') this.frame.classList.add('is-invalid');
        root.prepend(this.frame);
        root.classList.add('is-enhanced');

        this.editor = this.createEditor();
    }

    createEditor() {
        const { textarea } = this;
        const describedBy = textarea.getAttribute('aria-describedby');

        return new Editor({
            element: this.surface,
            content: textarea.value,
            extensions: [
                StarterKit.configure({
                    heading: { levels: [2, 3] },
                    code: false,
                    codeBlock: false,
                    link: { openOnClick: false, autolink: false, HTMLAttributes: { target: null, rel: null } },
                }),
                TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['start', 'center', 'end', 'justify'] }),
                Placeholder.configure({ placeholder: this.labels.placeholder || '' }),
            ],
            editorProps: {
                attributes: {
                    class: 'qb-rte-content',
                    role: 'textbox',
                    'aria-multiline': 'true',
                    dir: this.dir,
                    lang: this.lang,
                    ...(this.fieldLabel ? { 'aria-labelledby': this.fieldLabel.id } : {}),
                    ...(describedBy ? { 'aria-describedby': describedBy } : {}),
                    ...(textarea.getAttribute('aria-invalid') ? { 'aria-invalid': 'true' } : {}),
                },
                handleKeyDown: (_view, event) => {
                    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                        event.preventDefault();
                        this.openLinkBar();
                        return true;
                    }
                    return false;
                },
            },
            onUpdate: () => this.syncToTextarea(),
            onTransaction: () => this.refreshButtons(),
        });
    }

    buildToolbar() {
        const isRtl = getComputedStyle(this.root).direction === 'rtl';
        const bar = el('div', { class: 'qb-rte-toolbar', role: 'toolbar', 'aria-label': this.labels.toolbar || '' });
        GROUPS.forEach((group) => {
            const wrap = el('div', { class: 'qb-rte-group', role: 'group' });
            group.forEach((def) => wrap.append(this.buildButton(def, isRtl)));
            bar.append(wrap);
        });
        const first = bar.querySelector('button');
        if (first) first.tabIndex = 0;
        return bar;
    }

    buildButton(def, isRtl) {
        const label = this.labels[def.id] || def.id;
        const button = el('button', { type: 'button', class: 'qb-rte-btn', 'aria-label': label, title: label, tabindex: '-1', 'data-cmd': def.id }, iconFor(def.id, isRtl));
        if (def.active || def.special) button.setAttribute('aria-pressed', 'false');
        // Keep the editor selection while a button is pressed with the mouse.
        button.addEventListener('mousedown', (e) => e.preventDefault());
        button.addEventListener('click', () => this.runCommand(def));
        this.buttons.set(def.id, { button, def });
        return button;
    }

    buildLinkBar() {
        const bar = el('div', { class: 'qb-rte-linkbar', hidden: '' });
        this.linkInput = el('input', { type: 'text', class: 'qb-rte-linkinput', dir: 'ltr', inputmode: 'url', 'aria-label': this.labels.linkUrl || '', placeholder: 'https://' });
        this.linkError = el('span', { class: 'qb-rte-linkerror', role: 'alert' });
        const apply = el('button', { type: 'button', class: 'qb-rte-textbtn qb-rte-primary' }, this.labels.linkApply);
        const remove = el('button', { type: 'button', class: 'qb-rte-textbtn' }, this.labels.linkRemove);
        const cancel = el('button', { type: 'button', class: 'qb-rte-textbtn' }, this.labels.linkCancel);
        apply.addEventListener('click', () => this.applyLink());
        remove.addEventListener('click', () => this.applyLink(true));
        cancel.addEventListener('click', () => this.closeLinkBar());
        this.linkInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); this.applyLink(); }
            if (e.key === 'Escape') { e.preventDefault(); this.closeLinkBar(); }
        });
        bar.append(this.linkInput, apply, remove, cancel, this.linkError);
        return bar;
    }

    bind() {
        this.toolbar.addEventListener('keydown', (e) => this.onToolbarKey(e));
        this.toolbar.addEventListener('focusin', (e) => this.setRoving(e.target.closest('button')));
        this.fieldLabel?.addEventListener('click', () => (this.sourceMode ? this.textarea.focus() : this.editor.commands.focus()));
        this.textarea.form?.addEventListener('submit', () => this.flush());
        this.refreshButtons();
    }

    // Roving tabindex: one tab stop for the whole toolbar, arrows move inside it.
    onToolbarKey(e) {
        const items = [...this.toolbar.querySelectorAll('button:not(:disabled)')];
        const index = items.indexOf(document.activeElement);
        if (index < 0) return;
        const rtl = getComputedStyle(this.toolbar).direction === 'rtl';
        const next = rtl ? 'ArrowLeft' : 'ArrowRight';
        const prev = rtl ? 'ArrowRight' : 'ArrowLeft';
        const target = { [next]: items[(index + 1) % items.length], [prev]: items[(index - 1 + items.length) % items.length], Home: items[0], End: items.at(-1) }[e.key];
        if (!target) return;
        e.preventDefault();
        target.focus();
    }

    setRoving(active) {
        if (!active) return;
        this.toolbar.querySelectorAll('button').forEach((b) => { b.tabIndex = b === active ? 0 : -1; });
    }

    runCommand(def) {
        if (def.special === 'link') return this.openLinkBar();
        if (def.special === 'source') return this.toggleSource();
        return def.run(this.editor.chain().focus()).run();
    }

    refreshButtons() {
        const { editor } = this;
        if (!editor) return;
        this.buttons.forEach(({ button, def }) => {
            if (def.active) button.setAttribute('aria-pressed', String(!this.sourceMode && def.active(editor)));
            if (def.can) button.disabled = this.sourceMode || !def.can(editor);
            else if (def.special !== 'source') button.disabled = this.sourceMode;
        });
        this.ensureRovingStop();
    }

    // A disabled button cannot hold the tab stop, so hand it to the first usable one.
    ensureRovingStop() {
        const stop = this.toolbar.querySelector('button[tabindex="0"]');
        if (stop && !stop.disabled) return;
        this.toolbar.querySelectorAll('button').forEach((b) => { b.tabIndex = -1; });
        const usable = this.toolbar.querySelector('button:not(:disabled)');
        if (usable) usable.tabIndex = 0;
    }

    syncToTextarea() {
        this.textarea.value = this.editor.isEmpty ? '' : this.editor.getHTML();
    }

    flush() {
        if (this.sourceMode) this.editor.commands.setContent(this.textarea.value, { emitUpdate: false });
        else this.syncToTextarea();
    }

    toggleSource() {
        this.sourceMode = !this.sourceMode;
        this.root.classList.toggle('is-source', this.sourceMode);
        this.buttons.get('source').button.setAttribute('aria-pressed', String(this.sourceMode));
        this.closeLinkBar();
        if (this.sourceMode) {
            this.syncToTextarea();
            this.textarea.focus();
        } else {
            this.editor.commands.setContent(this.textarea.value, { emitUpdate: false });
            this.syncToTextarea();
            this.editor.commands.focus();
        }
        this.refreshButtons();
    }

    openLinkBar() {
        if (this.sourceMode) return;
        this.linkInput.value = this.editor.getAttributes('link').href || '';
        this.linkError.textContent = '';
        this.linkBar.hidden = false;
        this.linkInput.focus();
        this.linkInput.select();
    }

    closeLinkBar() {
        if (this.linkBar.hidden) return;
        this.linkBar.hidden = true;
        if (!this.sourceMode) this.editor.commands.focus();
    }

    applyLink(remove = false) {
        const url = remove ? '' : normaliseUrl(this.linkInput.value);
        if (url === null) {
            this.linkError.textContent = 'https://example.com';
            this.linkInput.setAttribute('aria-invalid', 'true');
            return;
        }
        this.linkInput.removeAttribute('aria-invalid');
        const chain = this.editor.chain().focus().extendMarkRange('link');
        if (url === '') {
            chain.unsetLink().run();
        } else {
            const external = isExternal(url);
            chain.setLink({ href: url, target: external ? '_blank' : null, rel: external ? 'noopener noreferrer nofollow' : null }).run();
        }
        this.closeLinkBar();
    }
}

export function initRichEditors(scope = document) {
    scope.querySelectorAll('[data-rich-editor]:not(.is-enhanced)').forEach((root) => new RichEditor(root));
}

initRichEditors();
