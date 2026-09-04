<?php

namespace App\Http\Controllers;

use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ChatbotController extends Controller
{
    public function __invoke(Request $request, AiChatService $chat): JsonResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:10'],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'string', 'max:1500'],
        ]);

        if (last($validated['messages'])['role'] !== 'user') {
            return response()->json(['message' => __('chatbot.invalid_message')], 422);
        }

        try {
            return response()->json([
                'reply' => $chat->reply($validated['messages'], app()->getLocale()),
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => __('chatbot.unavailable')], 503);
        }
    }
}
