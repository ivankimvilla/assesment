<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Draftroom</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>{!! file_get_contents(resource_path('css/app.css')) !!}</style>
    @endif
</head>
<body data-update-url="{{ $activeDocument ? route('documents.update', $activeDocument) : '' }}">
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="{{ route('workspace') }}"><span class="brand-mark">d</span><span>draftroom</span></a>
        <div class="topbar-document">{{ $activeDocument?->title ?: 'Your workspace' }}</div>
        <div class="topbar-actions">
            <span class="workspace-mode">Saved locally</span>
            <form method="POST" action="{{ route('users.switch') }}" class="user-switcher">
                @csrf
                <span class="avatar avatar-small">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <select name="user_id" aria-label="Switch demo user">
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @foreach ($users as $switchUser)<option value="{{ $switchUser->id }}">{{ $switchUser->name }}</option>@endforeach
                </select>
            </form>
        </div>
    </header>
    <div class="workspace">
        <aside class="sidebar">
            <div class="sidebar-heading"><span>Your workspace</span><span class="count">{{ $documents->count() }}</span></div>
            <div class="new-doc-form new-doc-menu">
                <button class="primary-button new-menu-trigger" type="button" aria-expanded="false" aria-controls="new-menu"><span class="plus">+</span> New</button>
                <div id="new-menu" class="new-menu" hidden>
                    <button type="button" class="new-menu-item" id="new-document-choice"><span class="menu-icon">＋</span><span><strong>New document</strong><small>Start with a blank page</small></span></button>
                    <form method="POST" action="{{ route('documents.import') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="new-menu-item" for="document-upload"><span class="menu-icon">↑</span><span><strong>Upload file</strong><small>.txt, .md, or .docx · 2 MB max</small><input id="document-upload" type="file" name="file" accept=".txt,.md,.docx,text/plain,text/markdown,application/vnd.openxmlformats-officedocument.wordprocessingml.document"></label>
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
                            <button class="document-actions-trigger" type="button" aria-label="Actions for {{ $document->title }}" data-document-menu="{{ $document->id }}">•••</button>
                            <div id="document-menu-{{ $document->id }}" class="document-actions-menu" hidden>
                                <a href="{{ route('workspace', ['document' => $document->id]) }}" class="document-action-item">Edit</a>
                                @if ($document->owner_id === $user->id)
                                    <button type="button" class="document-action-item" data-rename-url="{{ route('documents.rename', $document) }}" data-document-title="{{ $document->title }}">Change file name</button>
                                    <form method="POST" action="{{ route('documents.destroy', $document) }}" data-confirm-delete>
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
                            <button class="outline-button" type="button" data-modal-open="share-modal"><span>↗</span> Share</button>
                        @endif
                        <button class="icon-button" type="button" title="More options">•••</button>
                    </div>
                </section>
                <div class="format-toolbar" role="toolbar" aria-label="Formatting toolbar">
                    <div class="toolbar-group"><button type="button" data-format="undo" title="Undo">↶</button><button type="button" data-format="redo" title="Redo">↷</button></div>
                    <span class="toolbar-divider"></span>
                    <select data-format-block aria-label="Text style"><option value="">Text style</option><option value="p">Paragraph</option><option value="h2">Heading</option><option value="h3">Subheading</option></select>
                    <select id="paper-size" aria-label="Paper size"><option value="a4" @selected($activeDocument->paper_size === 'a4')>A4</option><option value="short" @selected($activeDocument->paper_size === 'short')>Short</option><option value="long" @selected($activeDocument->paper_size === 'long')>Long</option></select>
                    <span class="toolbar-divider"></span>
                    <button type="button" data-format="bold" title="Bold"><b>B</b></button>
                    <button type="button" data-format="italic" title="Italic"><i>I</i></button>
                    <button type="button" data-format="underline" title="Underline"><u>U</u></button>
                    <span class="toolbar-divider"></span>
                    <button type="button" data-format="insertUnorderedList" title="Bulleted list">☷</button>
                    <button type="button" data-format="insertOrderedList" title="Numbered list">Ⅲ</button>
                    <span class="toolbar-divider"></span>
                    <button type="button" data-format="justifyLeft" title="Align left">≡</button>
                    <button type="button" data-format="justifyCenter" title="Align center">≡</button>
                    <button type="button" data-format="removeFormat" title="Clear formatting">Tx</button>
                </div>
                <article id="editor" class="editor" data-paper-size="{{ $activeDocument->paper_size ?: 'a4' }}" contenteditable="true" spellcheck="true">{!! $activeDocument->content !!}</article>
            @else
                <div class="blank-state"><div class="blank-icon"></div><h1>Your workspace is ready.</h1><p>Choose New in the sidebar to create or upload a document.</p></div>
            @endif
        </main>

    </div>
</div>
@if ($activeDocument && $activeDocument->owner_id === $user->id)
<div id="share-modal" class="modal-backdrop" hidden><div class="modal"><button class="close-button" type="button" data-modal-close="share-modal">×</button><div class="section-label">Share document</div><h2>Choose who can access</h2><p>Enter a real Gmail address for restricted access, or generate a link anyone can use to view this document.</p><form method="POST" action="{{ route('documents.share', $activeDocument) }}">@csrf<label class="share-option"><input type="radio" name="access_mode" value="restricted" {{ ($activeDocument->access_mode ?: 'restricted') === 'restricted' ? 'checked' : '' }}><span><strong>Restricted</strong><small>Only the Gmail address you add can access</small></span></label><div id="restricted-share-fields"><input type="email" name="user_email" class="full-select share-email" placeholder="name@gmail.com" pattern="[^@\s]+@gmail\.com" title="Enter a valid Gmail address" required></div><label class="share-option"><input type="radio" name="access_mode" value="anyone" {{ $activeDocument->access_mode === 'anyone' ? 'checked' : '' }}><span><strong>Anyone with the link</strong><small>Anyone with the generated link can view</small></span></label><button class="primary-button full-button" type="submit">Save sharing settings</button></form>@if ($activeDocument->access_mode === 'anyone' && $activeDocument->share_token)<div class="share-link-box"><input id="share-link" value="{{ rtrim(config('app.share_url'), '/') . '/shared/' . $activeDocument->share_token }}" readonly><button type="button" data-copy-share-link>Copy link</button></div>@endif</div></div>
@endif
@if (! file_exists(public_path('build/manifest.json')) && ! file_exists(public_path('hot')))
    <script type="module">{!! file_get_contents(resource_path('js/workspace.js')) !!}</script>
@endif
</body>
</html>
