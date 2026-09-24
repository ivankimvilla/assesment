<?php

use App\Models\Document;
use App\Models\DocumentShare;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('a text upload becomes an editable document with its real content', function () {
    Storage::fake('local');
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/import', [
        'file' => UploadedFile::fake()->createWithContent('meeting-notes.txt', "Project update\nNext steps"),
    ]);

    $response->assertRedirect();
    $document = Document::latest('id')->first();
    expect($document->title)->toBe('meeting-notes')
        ->and($document->content)->toContain('Project update')
        ->and($document->content)->toContain('Next steps');
});

test('a valid docx upload becomes an editable document with extracted content', function () {
    Storage::fake('local');
    $user = \App\Models\User::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'draftroom-docx-');
    $archive = new \ZipArchive();
    $archive->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $archive->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>DOCX project update</w:t></w:r></w:p></w:body></w:document>');
    $archive->close();

    $response = $this->actingAs($user)->post('/import', [
        'file' => UploadedFile::fake()->createWithContent('project-update.docx', file_get_contents($path)),
    ]);

    $response->assertRedirect();
    expect(Document::latest('id')->first()->content)->toContain('DOCX project update');
    unlink($path);
});

test('a document PDF download includes its text', function () {
    $user = \App\Models\User::factory()->create();
    $document = Document::create([
        'owner_id' => $user->id,
        'title' => 'PDF notes',
        'content' => '<p>Project update</p><p>Next steps</p>',
        'paper_size' => 'a4',
    ]);

    $response = $this->actingAs($user)->get(route('documents.download.pdf', $document));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="PDF notes.pdf"');
    expect($response->getContent())->toContain('(Project update)')
        ->and($response->getContent())->toContain('(Next steps)');
});

test('share changes can be saved without redirecting', function () {
    $owner = \App\Models\User::factory()->create();
    $document = Document::create([
        'owner_id' => $owner->id,
        'title' => 'Automatic sharing',
        'content' => '<p>Content</p>',
        'paper_size' => 'a4',
    ]);

    $response = $this->actingAs($owner)->postJson(route('documents.share', $document), [
        'access_mode' => 'anyone',
        'link_role' => 'commenter',
    ]);

    $response->assertOk()->assertJson(['saved' => true]);
    expect($document->refresh()->access_mode)->toBe('anyone')
        ->and($document->link_role)->toBe('commenter');
});

test('an authenticated user can log out and return to login', function () {
    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('a document can be shared with a Gmail address', function () {
    $owner = \App\Models\User::factory()->create();
    $document = Document::create([
        'owner_id' => $owner->id,
        'title' => 'Shared notes',
        'content' => '<p>Shared content</p>',
        'paper_size' => 'a4',
    ]);

    $response = $this->actingAs($owner)->post(route('documents.share', $document), [
        'access_mode' => 'restricted',
        'user_email' => 'teammate@gmail.com',
    ]);

    $response->assertRedirect(route('workspace', ['document' => $document->id]));
    expect(DocumentShare::where('document_id', $document->id)->exists())->toBeTrue();
});

test('anyone sharing generates a public read-only link', function () {
    $owner = \App\Models\User::factory()->create();
    $document = Document::create([
        'owner_id' => $owner->id,
        'title' => 'Public notes',
        'content' => '<p>Public content</p>',
        'paper_size' => 'a4',
    ]);

    $this->actingAs($owner)->post(route('documents.share', $document), ['access_mode' => 'anyone']);
    $document->refresh();

    expect($document->share_token)->not->toBeNull()
        ->and($document->link_role)->toBe('viewer');
    $this->get(route('documents.public', $document->share_token))
        ->assertOk()
        ->assertSee('Public content', false)
        ->assertSee('Anyone with the link · Viewer', false);
});
