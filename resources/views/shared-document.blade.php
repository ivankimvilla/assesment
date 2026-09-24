<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->title }} · Draftroom</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>{!! file_get_contents(resource_path('css/app.css')) !!}</style>
    @endif
</head>
<body>
<main class="public-document-page">
    <div class="public-document-meta"><span class="brand-mark">d</span><span>Shared document</span><span class="public-badge">Anyone with the link</span></div>
    <article class="public-document paper-{{ $document->paper_size ?: 'a4' }}">
        <h1>{{ $document->title }}</h1>
        <p class="public-document-owner">Owned by {{ $document->owner->name }}</p>
        <div class="public-document-content">{!! strip_tags($document->content, '<p><br><strong><b><em><i><u><h2><h3><ul><ol><li>') !!}</div>
    </article>
</main>
</body>
</html>