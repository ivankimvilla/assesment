const body = document.body;
const editor = document.getElementById('editor');
const title = document.getElementById('document-title');
const paperSize = document.getElementById('paper-size');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
let saveTimer;

function format(command) {
    document.execCommand(command, false);
    editor?.focus();
    queueSave();
}

function formatBlock(value) {
    if (!value) return;
    document.execCommand('formatBlock', false, value);
    editor?.focus();
    queueSave();
}

function queueSave() {
    if (!editor || !title) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveDocument, 700);
}

async function saveDocument() {
    await fetch(body.dataset.updateUrl, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
            title: title.value.trim(),
            content: editor.innerHTML,
            paper_size: paperSize?.value || 'a4',
        }),
    });
}

function changePaperSize(value) {
    if (!editor) return;
    editor.classList.remove('paper-a4', 'paper-short', 'paper-long');
    editor.classList.add(`paper-${value}`);
    queueSave();
}

function openModal(id) {
    document.getElementById(id)?.removeAttribute('hidden');
}

function closeModal(id) {
    document.getElementById(id)?.setAttribute('hidden', '');
}

function toggleShareMode() {
    const restricted = document.querySelector('input[name="access_mode"]:checked')?.value === 'restricted';
    const fields = document.getElementById('restricted-share-fields');
    const input = fields?.querySelector('input');
    fields?.classList.toggle('is-disabled', !restricted);
    input?.toggleAttribute('disabled', !restricted);
    input?.toggleAttribute('required', restricted);
}

function copyShareLink() {
    const link = document.getElementById('share-link');
    if (!link) return;
    link.select();
    navigator.clipboard?.writeText(link.value);
}

function resetNewMenu() {
    const choice = document.getElementById('new-document-choice');
    const form = document.getElementById('new-document-form');
    const input = document.getElementById('new-document-title');
    if (choice) choice.hidden = false;
    if (form) form.hidden = true;
    if (input) input.value = '';
}

function toggleNewMenu() {
    const menu = document.getElementById('new-menu');
    const trigger = document.querySelector('.new-menu-trigger');
    if (!menu || !trigger) return;
    if (!menu.hidden) resetNewMenu();
    menu.hidden = !menu.hidden;
    trigger.setAttribute('aria-expanded', String(!menu.hidden));
}

function showNewDocumentForm() {
    document.getElementById('new-document-choice').hidden = true;
    document.getElementById('new-menu').hidden = true;
    document.getElementById('new-document-form').hidden = false;
    document.querySelector('.new-menu-trigger').setAttribute('aria-expanded', 'false');
    document.getElementById('new-document-title').focus();
}

function toggleDocumentMenu(event, id) {
    event.stopPropagation();
    document.querySelectorAll('.document-actions-menu').forEach((menu) => { menu.hidden = true; });
    document.getElementById(`document-menu-${id}`)?.removeAttribute('hidden');
}

async function renameDocument(button) {
    const currentTitle = button.dataset.documentTitle;
    const newTitle = window.prompt('Change file name', currentTitle);
    if (!newTitle?.trim() || newTitle.trim() === currentTitle) return;

    const response = await fetch(button.dataset.renameUrl, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ title: newTitle.trim() }),
    });
    if (response.ok) window.location.reload();
    else window.alert('The file name could not be changed.');
}

document.querySelectorAll('.user-switcher select').forEach((select) => select.addEventListener('change', (event) => event.target.form.submit()));
document.querySelector('.new-menu-trigger')?.addEventListener('click', toggleNewMenu);
document.getElementById('new-document-choice')?.addEventListener('click', showNewDocumentForm);
document.getElementById('document-upload')?.addEventListener('change', (event) => event.target.form.submit());
document.querySelectorAll('[data-format]').forEach((button) => button.addEventListener('click', () => format(button.dataset.format)));
document.querySelector('[data-format-block]')?.addEventListener('change', (event) => {
    formatBlock(event.target.value);
    event.target.selectedIndex = 0;
});
paperSize?.addEventListener('change', (event) => changePaperSize(event.target.value));
editor?.addEventListener('input', queueSave);
title?.addEventListener('input', queueSave);
document.querySelectorAll('[data-modal-open]').forEach((button) => button.addEventListener('click', () => openModal(button.dataset.modalOpen)));
document.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', () => closeModal(button.dataset.modalClose)));
document.querySelectorAll('.modal-backdrop').forEach((modal) => modal.addEventListener('click', (event) => {
    if (event.target === modal) modal.hidden = true;
}));
document.querySelectorAll('input[name="access_mode"]').forEach((input) => input.addEventListener('change', toggleShareMode));
document.querySelector('[data-copy-share-link]')?.addEventListener('click', copyShareLink);
document.querySelectorAll('[data-document-menu]').forEach((button) => button.addEventListener('click', (event) => toggleDocumentMenu(event, button.dataset.documentMenu)));
document.querySelectorAll('[data-rename-url]').forEach((button) => button.addEventListener('click', (event) => {
    event.stopPropagation();
    renameDocument(button);
}));
document.querySelectorAll('[data-confirm-delete]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm('Delete this document permanently?')) event.preventDefault();
}));
document.addEventListener('click', (event) => {
    if (!event.target.closest('.document-actions')) document.querySelectorAll('.document-actions-menu').forEach((menu) => { menu.hidden = true; });
    const menu = document.querySelector('.new-doc-menu');
    if (menu && !menu.contains(event.target)) {
        resetNewMenu();
        document.getElementById('new-menu').hidden = true;
        document.querySelector('.new-menu-trigger')?.setAttribute('aria-expanded', 'false');
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        resetNewMenu();
        document.getElementById('new-menu')?.setAttribute('hidden', '');
        document.querySelector('.new-menu-trigger')?.setAttribute('aria-expanded', 'false');
    }
});

if (editor) editor.classList.add(`paper-${editor.dataset.paperSize || 'a4'}`);
if (document.querySelector('input[name="access_mode"]')) toggleShareMode();