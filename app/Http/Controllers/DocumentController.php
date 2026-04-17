<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(private RagService $rag) {}

    public function index()
    {
        return Document::select('id', 'title')->get();
    }

    public function uploadForm()
    {
        return view('upload');
    }

    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:pdf|max:10240']);

        $file = $request->file('file');
        $path = $file->store('documents');
        $fullPath = Storage::path($path);

        if (! file_exists($fullPath)) {
            abort(500, 'File not found: ' . $fullPath);
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($fullPath);
        $text = $pdf->getText();

        Document::create([
            'title'   => $file->getClientOriginalName(),
            'content' => $text,
        ]);

        return 'Uploaded and parsed!';
    }

    public function chunk(int $id)
    {
        $document = Document::findOrFail($id);
        $chunks = $this->rag->chunkDocument($document);

        return response()->json([
            'document_id'   => $document->id,
            'chunks_created' => count($chunks),
            'sample_chunks' => array_slice($chunks, 0, 3),
        ]);
    }

    public function embed(int $id)
    {
        $document = Document::findOrFail($id);

        return response()->json($this->rag->embedDocument($document));
    }
}
