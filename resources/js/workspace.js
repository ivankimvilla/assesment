const body = document.body;
const editor = document.getElementById('editor');
const title = document.getElementById('document-title');
const paperSize = document.getElementById('paper-size');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const shareForm = document.querySelector('.share-form');
let saveTimer;
let shareSaveTimer;
let pendingShareSaves = 0;
const minimumShareSavingTime = 700;

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
    const anyone = document.querySelector('select[name="access_mode"]')?.value === 'anyone';
    const role = document.querySelector('.link-role-select');
    const linkBox = document.querySelector('.share-link-box');
    const title = document.querySelector('[data-access-title]');
    const description = document.querySelector('[data-access-description]');
    role?.toggleAttribute('hidden', !anyone);
    linkBox?.toggleAttribute('hidden', !anyone || !document.getElementById('share-link')?.value);
    if (title) title.textContent = anyone ? 'Anyone with the link' : 'Restricted';
    if (description) description.textContent = anyone
        ? 'Anyone on the Internet with the link can view'
        : 'Only people with access can open with the link';
}

async function saveShareForm(form) {
    if (!form) return;
    const doneButton = document.querySelector('[data-share-done]');
    const savingStartedAt = performance.now();
    pendingShareSaves += 1;
    if (doneButton) {
        doneButton.textContent = 'Saving...';
        doneButton.disabled = true;
    }

    const data = new FormData(form);
    const email = data.get('user_email')?.toString().trim() || '';
    if (email && !/^[^@\s]+@gmail\.com$/i.test(email)) data.delete('user_email');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: data,
        });
        if (!response.ok) return;

        const result = await response.json();
        const link = document.getElementById('share-link');
        const linkBox = document.querySelector('.share-link-box');
        if (result.share_url && link && linkBox) {
            link.value = result.share_url;
            linkBox.hidden = false;
        } else if (!result.share_url && linkBox) {
            linkBox.hidden = true;
        }
        if (email) document.getElementById('share-email').value = '';
    } finally {
        const remainingSavingTime = minimumShareSavingTime - (performance.now() - savingStartedAt);
        if (remainingSavingTime > 0) await new Promise((resolve) => setTimeout(resolve, remainingSavingTime));
        pendingShareSaves -= 1;
        if (pendingShareSaves === 0 && doneButton) {
            doneButton.textContent = 'Done';
            doneButton.disabled = false;
        }
    }
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
const downloadTrigger = document.querySelector('.download-trigger');
const downloadOptions = document.querySelector('.download-options');
downloadTrigger?.addEventListener('click', (event) => {
    event.stopPropagation();
    downloadOptions.hidden = !downloadOptions.hidden;
    downloadTrigger.setAttribute('aria-expanded', String(!downloadOptions.hidden));
});
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
    event.stopPropagation();
}));
document.querySelectorAll('select[name="access_mode"]').forEach((input) => input.addEventListener('change', () => {
    toggleShareMode();
    if (input.form) saveShareForm(input.form);
}));
document.querySelectorAll('select[name="link_role"]').forEach((input) => input.addEventListener('change', () => {
    if (input.form) saveShareForm(input.form);
}));
shareForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    saveShareForm(shareForm);
});
document.getElementById('share-email')?.addEventListener('input', () => {
    clearTimeout(shareSaveTimer);
    const input = document.getElementById('share-email');
    if (!input || !/^[^@\s]+@gmail\.com$/i.test(input.value.trim())) return;
    shareSaveTimer = setTimeout(() => saveShareForm(shareForm), 500);
});
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
    if (!event.target.closest('.download-menu')) {
        downloadOptions?.setAttribute('hidden', '');
        downloadTrigger?.setAttribute('aria-expanded', 'false');
    }
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
if (document.querySelector('select[name="access_mode"]')) toggleShareMode();