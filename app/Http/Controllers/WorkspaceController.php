<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkspaceController extends Controller
{
    private function currentUser(Request $request): User
    {
        $user = User::find($request->session()->get('user_id'));
        if (! $user) {
            $user = User::firstOrCreate(['email' => 'alex@example.com'], ['name' => 'Alex Morgan', 'password' => 'password']);
            $request->session()->put('user_id', $user->id);
        }
        return $user;
    }

    public function index(Request $request)
    {
        $user = $this->currentUser($request);
        $documents = Document::with(['owner', 'shares.user'])
            ->where('owner_id', $user->id)
            ->orWhereHas('shares', fn ($query) => $query->where('user_id', $user->id))
            ->latest('updated_at')->get();

        return view('workspace', [
            'user' => $user,
            'users' => User::whereKeyNot($user->id)->orderBy('name')->get(),
            'documents' => $documents,
            'activeDocument' => $documents->firstWhere('id', $request->integer('document')) ?: $documents->first(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $document = Document::create(['owner_id' => $this->currentUser($request)->id, 'title' => trim($data['title']), 'content' => '<p>Start writing here...</p>', 'paper_size' => 'a4']);
        return redirect()->route('workspace', ['document' => $document->id]);
    }

    public function update(Request $request, Document $document)
    {
        $user = $this->currentUser($request);
        abort_unless($document->owner_id === $user->id || $document->shares()->where('user_id', $user->id)->exists(), 403);
        $document->update($request->validate([
            'title' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string'],
            'paper_size' => ['required', 'in:a4,short,long'],
        ]));
        return response()->json(['saved' => true, 'updated_at' => $document->updated_at->diffForHumans()]);
    }

    public function rename(Request $request, Document $document)
    {
        $user = $this->currentUser($request);
        abort_unless($document->owner_id === $user->id, 403);
        $document->update($request->validate(['title' => ['required', 'string', 'max:120']]));

        return response()->json(['renamed' => true, 'title' => $document->title]);
    }

    public function destroy(Request $request, Document $document)
    {
        $user = $this->currentUser($request);
        abort_unless($document->owner_id === $user->id, 403);
        if ($document->source_file_path) Storage::delete($document->source_file_path);
        $document->delete();

        return redirect()->route('workspace')->with('status', 'Document deleted.');
    }

    public function share(Request $request, Document $document)
    {
        $user = $this->currentUser($request);
        abort_unless($document->owner_id === $user->id, 403);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);
        if ((int) $data['user_id'] !== $document->owner_id) DocumentShare::firstOrCreate(['document_id' => $document->id, 'user_id' => $data['user_id']]);
        return redirect()->route('workspace', ['document' => $document->id])->with('status', 'Access granted.');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:2048']]);
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store('document-uploads');
        $mimeType = (string) $file->getMimeType();
        $isText = str_starts_with($mimeType, 'text/') || in_array(strtolower($file->getClientOriginalExtension()), ['txt', 'md', 'csv', 'json', 'xml', 'html', 'css', 'js'], true);
        $content = $isText
            ? '<p>'.nl2br(e(file_get_contents($file->getRealPath()))).'</p>'
            : '<p>Uploaded file: <strong>'.e($fileName).'</strong></p><p>This file is available as a document record. Its binary contents are not rendered in the text editor.</p>';
        $document = Document::create([
            'owner_id' => $this->currentUser($request)->id,
            'title' => pathinfo($fileName, PATHINFO_FILENAME),
            'content' => $content,
            'source_file_path' => $filePath,
            'source_file_name' => $fileName,
        ]);
        return redirect()->route('workspace', ['document' => $document->id])->with('status', 'File imported as a new document.');
    }

    public function switchUser(Request $request)
    {
        $request->session()->put('user_id', $request->validate(['user_id' => ['required', 'exists:users,id']])['user_id']);
        return redirect()->route('workspace');
    }
}