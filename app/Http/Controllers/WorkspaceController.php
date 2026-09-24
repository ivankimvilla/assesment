<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    private function currentUser(Request $request): User
    {
        return Auth::user();
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
            'documents' => $documents,
            'activeDocument' => $documents->firstWhere('id', $request->integer('document')) ?: $documents->first(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $document = Document::create(['owner_id' => $this->currentUser($request)->id, 'title' => trim($data['title']), 'content' => '<p><br></p>', 'paper_size' => 'a4']);
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

    public function downloadWord(Request $request, Document $document)
    {
        $user = $this->currentUser($request);
        abort_unless($document->owner_id === $user->id || $document->shares()->where('user_id', $user->id)->exists(), 403);
        abort_unless(class_exists(\ZipArchive::class), 422, 'Word download requires the PHP ZIP extension.');

        $path = tempnam(sys_get_temp_dir(), 'draftroom-word-');
        $archive = new \ZipArchive();
        $archive->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $archive->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $archive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $text = strip_tags($document->content);
        $paragraphs = collect(preg_split('/\r\n|\r|\n/', trim($text)) ?: [''])
            ->map(fn ($paragraph) => '<w:p><w:r><w:t xml:space="preserve">'.e($paragraph).'</w:t></w:r></w:p>')
            ->implode('');
        $archive->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$paragraphs.'<w:sectPr/></w:body></w:document>');
        $archive->close();

        return response()->download($path, $document->title.'.docx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->deleteFileAfterSend(true);
    }

    public function downloadPdf(Request $request, Document $document)
    {
        $user = $this->currentUser($request);
        abort_unless($document->owner_id === $user->id || $document->shares()->where('user_id', $user->id)->exists(), 403);

        $text = preg_replace('/<(br|\/p|\/h[1-6]|\/li|\/div|\/blockquote)\b[^>]*>/i', "\n", $document->content);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($text)) ?: [''] as $line) {
            $line = trim($line);
            if ($line !== '') $lines = array_merge($lines, explode("\n", wordwrap($line, 92, "\n", true)));
        }
        $lines = $lines ?: [' '];
        $pages = array_chunk($lines, 45);
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [PAGE_KIDS] /Count PAGE_COUNT >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $pageNumbers = [];
        foreach ($pages as $pageLines) {
            $content = "BT\n/F1 11 Tf\n50 760 Td\n";
            foreach ($pageLines as $line) {
                $safeLine = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], preg_replace('/[^\x20-\x7E]/', '', $line));
                $content .= "(".$safeLine.") Tj\n0 -16 Td\n";
            }
            $content .= "ET";
            $contentNumber = count($objects) + 1;
            $objects[] = '<< /Length '.strlen($content).' >>\nstream\n'.$content.'\nendstream';
            $pageNumber = count($objects) + 1;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentNumber.' 0 R >>';
            $pageNumbers[] = $pageNumber;
        }
        $objects[1] = str_replace(['PAGE_KIDS', 'PAGE_COUNT'], [implode(' 0 R ', $pageNumbers).' 0 R', (string) count($pageNumbers)], $objects[1]);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) { $offsets[] = strlen($pdf); $pdf .= ($number + 1)." 0 obj\n".$object."\nendobj\n"; }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($index = 1; $index < count($offsets); $index++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $document->title).'.pdf"']);
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
            'link_role' => ['nullable', 'in:viewer,commenter,editor'],
            'user_email' => ['nullable', 'email:rfc', 'regex:/@gmail\.com$/i'],
        ]);

        $document->access_mode = $data['access_mode'];
        $document->link_role = $data['link_role'] ?? $document->link_role ?? 'viewer';
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

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => true,
                'share_url' => $document->access_mode === 'anyone'
                    ? rtrim(config('app.share_url'), '/') . '/shared/' . $document->share_token
                    : null,
            ]);
        }

        return redirect()->route('workspace', ['document' => $document->id])->with('status', $data['access_mode'] === 'anyone' ? 'Anyone with the link can view this document.' : 'Restricted access updated.');
    }

    public function publicDocument(string $token)
    {
        $document = Document::with('owner')->where('share_token', $token)->where('access_mode', 'anyone')->firstOrFail();
        return view('shared-document', compact('document'));
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:2048']]);
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['txt', 'md', 'docx'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Only .txt, .md, and valid Word .docx files can be uploaded.',
            ]);
        }
        try {
            $rawContent = $extension === 'docx'
                ? $this->extractDocxText($file->getRealPath())
                : file_get_contents($file->getRealPath());
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'file' => 'This DOCX file could not be opened. Please upload a valid Word .docx file.',
            ]);
        }
        $filePath = $file->store('document-uploads');
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
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP ZIP extension is not available.');
        }
        $archive = new \ZipArchive();
        $openResult = $archive->open($path);
        if ($openResult !== true && $openResult !== 0) {
            throw new \RuntimeException('The DOCX file is not a readable archive.');
        }
        $xml = $archive->getFromName('word/document.xml');
        $archive->close();
        if ($xml === false) {
            throw new \RuntimeException('The DOCX document content is missing.');
        }

        $xml = preg_replace('/<w:tab\s*\/>/i', "\t", $xml);
        $xml = preg_replace('/<\/w:p>/i', "\n", $xml);

        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

}