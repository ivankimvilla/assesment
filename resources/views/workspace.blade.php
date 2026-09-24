<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Draftroom</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>{!! file_get_contents(resource_path('css/app.css')) !!}</style>
    @endif
</head>
<body>
<div class="app-shell">
    <div class="workspace">
        <aside class="sidebar">
            <div class="sidebar-heading"><span>Your workspace</span><span class="count">{{ $documents->count() }}</span></div>
            <div class="new-doc-form new-doc-menu">
                <button class="primary-button new-menu-trigger" type="button" aria-expanded="false" aria-controls="new-menu" onclick="toggleNewMenu()"><span class="plus">+</span> New</button>
                <div id="new-menu" class="new-menu" hidden>
                    <button type="button" class="new-menu-item" id="new-document-choice" onclick="showNewDocumentForm()"><span class="menu-icon">＋</span><span><strong>New document</strong><small>Start with a blank page</small></span></button>
                    <form method="POST" action="{{ route('documents.import') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="new-menu-item" for="document-upload"><span class="menu-icon">↑</span><span><strong>Upload file</strong><small>Any file type · 2 MB max</small></span><input id="document-upload" type="file" name="file" accept="*/*" onchange="this.form.submit()"></label>
                    </form>
                </div>
                <form method="POST" action="{{ route('documents.store') }}" id="new-document-form" hidden>
                    @csrf
                    <div class="new-menu-create">
                        <span class="menu-icon">＋</span>
                        <label for="new-document-title"><strong>Name your document</strong><small>Enter a file name to continue</small></label>
                        <input id="new-document-title" class="new-document-title" type="text" name="title" placeholder="File name" maxlength="120" required>
                        <button type="submit" class="create-menu-button" title="Create document">Create</button>
                    </div>
                </form>
            </div>
            <div class="document-list">
                @forelse ($documents as $document)
                    <div class="document-item {{ $activeDocument?->id === $document->id ? 'active' : '' }}">
                        <a class="document-open" href="{{ route('workspace', ['document' => $document->id]) }}">
                            <span class="document-icon">▤</span>
                            <span class="document-summary"><strong>{{ $document->title }}</strong><small>{{ $document->owner_id === $user->id ? 'Owned by you' : 'Shared with you' }}</small></span>
                        </a>
                        <div class="document-actions">
                            <button class="document-actions-trigger" type="button" aria-label="Actions for {{ $document->title }}" onclick="toggleDocumentMenu(event, {{ $document->id }})">•••</button>
                            <div id="document-menu-{{ $document->id }}" class="document-actions-menu" hidden>
                                <a href="{{ route('workspace', ['document' => $document->id]) }}" class="document-action-item" onclick="event.stopPropagation()">Edit</a>
                                @if ($document->owner_id === $user->id)
                                    <button type="button" class="document-action-item" data-rename-url="{{ route('documents.rename', $document) }}" data-document-title="{{ $document->title }}" onclick="renameDocument(event, this)">Change file name</button>
                                    <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Delete this document permanently?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="document-action-item danger">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="empty-list">Your documents will appear here.</div>
                @endforelse
            </div>
            <div class="sidebar-foot"><span class="status-dot"></span> Local workspace</div>
        </aside>

        <main class="editor-area">
            @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
            @if ($activeDocument)
                <section class="editor-header">
                    <div class="title-block">
                        <input id="document-title" class="document-title" value="{{ $activeDocument->title }}" aria-label="Document title">
                        <div class="document-meta">{{ $activeDocument->owner_id === $user->id ? 'Owned by you' : 'Shared with you' }} · Last edited {{ $activeDocument->updated_at->diffForHumans() }}</div>
                    </div>
                    <div class="header-actions">
                        @if ($activeDocument->owner_id === $user->id)
                            <button class="outline-button" type="button" onclick="openModal('share-modal')"><span>↗</span> Share</button>
                        @endif
                        <button class="icon-button" type="button" title="More options">•••</button>
                    </div>
                </section>
                <div class="format-toolbar" role="toolbar" aria-label="Formatting toolbar">
                    <select onchange="formatBlock(this.value); this.selectedIndex=0" aria-label="Text style"><option value="">Text style</option><option value="p">Paragraph</option><option value="h2">Heading</option><option value="h3">Subheading</option></select>
                    <select id="paper-size" onchange="changePaperSize(this.value)" aria-label="Paper size"><option value="a4" @selected($activeDocument->paper_size === 'a4')>A4</option><option value="short" @selected($activeDocument->paper_size === 'short')>Short</option><option value="long" @selected($activeDocument->paper_size === 'long')>Long</option></select>
                    <span class="toolbar-divider"></span>
                    <button type="button" onclick="format('bold')" title="Bold"><b>B</b></button>
                    <button type="button" onclick="format('italic')" title="Italic"><i>I</i></button>
                    <button type="button" onclick="format('underline')" title="Underline"><u>U</u></button>
                    <span class="toolbar-divider"></span>
                    <button type="button" onclick="format('insertUnorderedList')" title="Bulleted list">☷</button>
                    <button type="button" onclick="format('insertOrderedList')" title="Numbered list">Ⅲ</button>
                    <span class="toolbar-divider"></span>
                    <button type="button" onclick="format('justifyLeft')" title="Align left">≡</button>
                    <button type="button" onclick="format('removeFormat')" title="Clear formatting">Tx</button>
                </div>
                <article id="editor" class="editor" contenteditable="true" spellcheck="true">{!! $activeDocument->content !!}</article>
            @else
                <div class="blank-state"><div class="blank-icon">✦</div><h1>Your workspace is ready.</h1><p>Create a document or import a text file to get started.</p><form method="POST" action="{{ route('documents.store') }}" class="blank-create-form">@csrf<input type="text" name="title" placeholder="File name" maxlength="120" required><button class="primary-button" type="submit">Create document</button></form></div>
            @endif
        </main>

    </div>
</div>
@if ($activeDocument && $activeDocument->owner_id === $user->id)
<div id="share-modal" class="modal-backdrop" hidden><div class="modal"><button class="close-button" onclick="closeModal('share-modal')">×</button><div class="section-label">Share document</div><h2>Invite a teammate</h2><p>Choose a demo user to grant them editing access.</p><form method="POST" action="{{ route('documents.share', $activeDocument) }}">@csrf<select name="user_id" class="full-select">@foreach ($users as $shareUser)<option value="{{ $shareUser->id }}">{{ $shareUser->name }} · {{ $shareUser->email }}</option>@endforeach</select><button class="primary-button full-button" type="submit">Give access</button></form></div></div>
@endif
<script>
const editor = document.getElementById('editor');
const title = document.getElementById('document-title');
let saveTimer;
function format(command) { document.execCommand(command, false); editor?.focus(); queueSave(); }
function formatBlock(value) { if (value) document.execCommand('formatBlock', false, value); editor?.focus(); queueSave(); }
function queueSave() { if (!editor || !title) return; clearTimeout(saveTimer); saveTimer = setTimeout(saveDocument, 700); }
async function saveDocument() { await fetch('{{ $activeDocument ? route('documents.update', $activeDocument) : '' }}', { method: 'PATCH', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'}, body: JSON.stringify({title: title.value.trim(), content: editor.innerHTML, paper_size: document.getElementById('paper-size')?.value || 'a4'}) }); }
function changePaperSize(value) { editor?.classList.remove('paper-a4', 'paper-short', 'paper-long'); editor?.classList.add(`paper-${value}`); queueSave(); }
editor?.classList.add(`paper-{{ $activeDocument?->paper_size ?: 'a4' }}`);
editor?.addEventListener('input', queueSave); title?.addEventListener('input', queueSave);
function openModal(id) { document.getElementById(id).hidden = false; }
function closeModal(id) { document.getElementById(id).hidden = true; }
document.querySelectorAll('.modal-backdrop').forEach(modal => modal.addEventListener('click', event => { if (event.target === modal) modal.hidden = true; }));
function toggleDocumentMenu(event, id) { event.stopPropagation(); document.querySelectorAll('.document-actions-menu').forEach(menu => { menu.hidden = true; }); document.getElementById(`document-menu-${id}`).hidden = false; }
async function renameDocument(event, button) { event.stopPropagation(); const currentTitle = button.dataset.documentTitle; const newTitle = window.prompt('Change file name', currentTitle); if (!newTitle || !newTitle.trim() || newTitle.trim() === currentTitle) return; const response = await fetch(button.dataset.renameUrl, { method: 'PATCH', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'}, body: JSON.stringify({title: newTitle.trim()}) }); if (response.ok) window.location.reload(); else window.alert('The file name could not be changed.'); }
document.addEventListener('click', event => { if (!event.target.closest('.document-actions')) document.querySelectorAll('.document-actions-menu').forEach(menu => { menu.hidden = true; }); });
function resetNewMenu() { document.getElementById('new-document-choice').hidden = false; document.getElementById('new-document-form').hidden = true; document.getElementById('new-document-title').value = ''; }
function toggleNewMenu() { const menu = document.getElementById('new-menu'); const trigger = document.querySelector('.new-menu-trigger'); if (!menu.hidden) resetNewMenu(); menu.hidden = !menu.hidden; trigger.setAttribute('aria-expanded', String(!menu.hidden)); }
function showNewDocumentForm() { document.getElementById('new-document-choice').hidden = true; document.getElementById('new-menu').hidden = true; document.getElementById('new-document-form').hidden = false; document.querySelector('.new-menu-trigger').setAttribute('aria-expanded', 'false'); document.getElementById('new-document-title').focus(); }
document.addEventListener('click', event => { const menu = document.querySelector('.new-doc-menu'); if (menu && !menu.contains(event.target)) { resetNewMenu(); document.getElementById('new-menu').hidden = true; document.querySelector('.new-menu-trigger').setAttribute('aria-expanded', 'false'); } });
document.addEventListener('keydown', event => { if (event.key === 'Escape') { resetNewMenu(); document.getElementById('new-menu')?.setAttribute('hidden', ''); document.querySelector('.new-menu-trigger')?.setAttribute('aria-expanded', 'false'); } });
</script>
</body>
</html>
