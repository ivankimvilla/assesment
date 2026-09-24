<?php

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('a text upload becomes an editable document with its real content', function () {
    Storage::fake('local');

    $response = $this->post('/import', [
        'file' => UploadedFile::fake()->createWithContent('meeting-notes.txt', "Project update\nNext steps"),
    ]);

    $response->assertRedirect();
    $document = Document::latest('id')->first();
    expect($document->title)->toBe('meeting-notes')
        ->and($document->content)->toContain('Project update')
        ->and($document->content)->toContain('Next steps');
});
