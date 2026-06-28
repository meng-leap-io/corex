<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'timeout' => 'nullable|integer|min:1000|max:30000',
            'silent' => 'nullable|boolean',
            'urgency' => 'nullable|in:normal,critical,low',
        ]);

        try {
            native()->notification()
                ->title($request->input('title'))
                ->message($request->input('message'))
                ->timeout($request->input('timeout', 5000))
                ->silent($request->boolean('silent', false))
                ->urgency($request->input('urgency', 'normal'))
                ->show();

            return response()->json(['sent' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function clear(): JsonResponse
    {
        try {
            native()->notification()->clear();

            return response()->json(['cleared' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
