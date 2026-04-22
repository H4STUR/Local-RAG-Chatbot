<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(private RagService $rag) {}

    public function index()
    {
        $docs = DB::select(
            "SELECT d.id, d.title,
                COUNT(c.id) AS chunks_count,
                COUNT(CASE WHEN c.embedding IS NOT NULL THEN 1 END) AS embedded_count
             FROM documents d
             LEFT JOIN document_chunks c ON c.document_id = d.id
             GROUP BY d.id, d.title
             ORDER BY d.id"
        );

        return response()->json($docs);
    }

    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:pdf|max:10240']);

        $file     = $request->file('file');
        $path     = $file->store('documents');
        $fullPath = Storage::path($path);

        if (! file_exists($fullPath)) {
            abort(500, 'Stored file not found: ' . $fullPath);
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($fullPath);
        $text   = $pdf->getText();

        $doc = Document::create([
            'title'   => $file->getClientOriginalName(),
            'content' => $text,
        ]);

        return response()->json($doc, 201);
    }

    public function chunk(int $id)
    {
        $document = Document::findOrFail($id);
        $chunks   = $this->rag->chunkDocument($document);

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

    /** Chunk + embed in one request — the normal "process" flow. */
    public function process(int $id)
    {
        $document = Document::findOrFail($id);
        $chunks   = $this->rag->chunkDocument($document);
        $result   = $this->rag->embedDocument($document);

        return response()->json([
            'document_id'     => $document->id,
            'chunks_created'  => count($chunks),
            'chunks_embedded' => $result['chunks_embedded'],
            'errors'          => $result['errors'],
        ]);
    }

    public function delete(int $id)
    {
        $doc = Document::findOrFail($id);
        $doc->delete(); // cascades to document_chunks via FK

        return response()->json(['deleted' => $id]);
    }

    public function deleteAll()
    {
        DocumentChunk::truncate();
        Document::truncate();

        return response()->json(['deleted' => 'all']);
    }
}
