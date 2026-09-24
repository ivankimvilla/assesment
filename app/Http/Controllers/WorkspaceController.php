<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $data = $request->validate([
            'access_mode' => ['required', 'in:restricted,anyone'],
            'user_email' => ['required_if:access_mode,restricted', 'nullable', 'email:rfc', 'regex:/@gmail\.com$/i'],
        ]);

        $document->access_mode = $data['access_mode'];
        if ($data['access_mode'] === 'anyone') {
            $document->share_token ??= Str::random(48);
        } elseif (! empty($data['user_email'])) {
            $email = strtolower(trim($data['user_email']));
            $recipient = User::firstOrCreate(
                ['email' => $email],
                ['name' => Str::headline(str_replace(['.', '_', '-'], ' ', Str::before($email, '@'))), 'password' => Str::random(32)]
            );
            if ($recipient->id !== $document->owner_id) {
                DocumentShare::firstOrCreate(['document_id' => $document->id, 'user_id' => $recipient->id]);
            }
        }
        $document->save();

        return redirect()->route('workspace', ['document' => $document->id])->with('status', $data['access_mode'] === 'anyone' ? 'Anyone with the link can view this document.' : 'Restricted access updated.');
    }

    public function publicDocument(string $token)
    {
        $document = Document::with('owner')->where('share_token', $token)->where('access_mode', 'anyone')->firstOrFail();
        return view('shared-document', compact('document'));
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:txt,md,docx', 'max:2048']]);
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store('document-uploads');
        $extension = strtolower($file->getClientOriginalExtension());
        $rawContent = $extension === 'docx'
            ? $this->extractDocxText($file->getRealPath())
            : file_get_contents($file->getRealPath());
        $content = '<p>'.nl2br(e($rawContent)).'</p>';
        $document = Document::create([
            'owner_id' => $this->currentUser($request)->id,
            'title' => pathinfo($fileName, PATHINFO_FILENAME),
            'content' => $content,
            'source_file_path' => $filePath,
            'source_file_name' => $fileName,
        ]);
        return redirect()->route('workspace', ['document' => $document->id])->with('status', 'File imported as a new document.');
    }

    private function extractDocxText(string $path): string
    {
        abort_unless(class_exists(\ZipArchive::class), 422, 'DOCX import requires the PHP ZIP extension.');
        $archive = new \ZipArchive();
        abort_unless($archive->open($path) === true, 422, 'The DOCX file could not be opened.');
        $xml = $archive->getFromName('word/document.xml');
        $archive->close();
        abort_unless($xml !== false, 422, 'The DOCX document content could not be read.');

        $xml = preg_replace('/<w:tab\s*\/>/i', "\t", $xml);
        $xml = preg_replace('/<\/w:p>/i', "\n", $xml);

        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    public function switchUser(Request $request)
    {
        $request->session()->put('user_id', $request->validate(['user_id' => ['required', 'exists:users,id']])['user_id']);
        return redirect()->route('workspace');
    }
}