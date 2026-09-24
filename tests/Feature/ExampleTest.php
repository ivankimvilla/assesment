<?php

use App\Models\Document;
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

test('an authenticated user can log out and return to login', function () {
    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
});
