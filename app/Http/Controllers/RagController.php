<?php

namespace App\Http\Controllers;

use App\Services\RagService;
use Illuminate\Http\Request;

class RagController extends Controller
{
    public function __construct(private RagService $rag) {}

    public function ask(Request $request)
    {
        $question = trim((string) $request->input('question', ''));

        if ($question === '') {
            return response()->json(['error' => 'Question is required'], 422);
        }

        try {
            return $this->rag->stream($question);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
