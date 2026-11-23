<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatbotAskRequest;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;

/** Public FAQ chatbot API — red flags always intercepted before retrieval. */
class ChatbotController extends Controller
{
    public function __construct(private readonly ChatbotService $chatbot) {}

    public function __invoke(ChatbotAskRequest $request): JsonResponse
    {
        $reply = $this->chatbot->reply((string) $request->validated('message'), $request);

        return response()->json($reply)
            ->header('Cache-Control', 'no-store');
    }
}
