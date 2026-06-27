<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeepLinkController extends Controller
{
    public function handle(Request $request, string $action): JsonResponse
    {
        $allowedActions = [
            'open', 'new-chat', 'open-project', 'open-file', 'settings',
        ];

        if (! in_array($action, $allowedActions)) {
            return response()->json(['error' => 'Unknown action'], 400);
        }

        $payload = $request->input('payload', []);
        $raw = $request->input('raw', '');

        native()->ipc()->send('main', 'deep-link', [
            'action' => $action,
            'payload' => $payload,
            'raw' => $raw,
        ]);

        return response()->json([
            'handled' => true,
            'action' => $action,
        ]);
    }
}
