<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Services\Windows\FileAssociationService;
use App\Services\Windows\JumpListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortcutController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:1000',
            'target' => 'required|string|max:1000',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:1000',
            'working_dir' => 'nullable|string|max:1000',
            'arguments' => 'nullable|string|max:1000',
        ]);

        $created = \App\Services\Windows\ComService::createShortcut(
            $validated['path'],
            $validated['target'],
            $validated['description'] ?? '',
            $validated['icon'] ?? '',
            $validated['working_dir'] ?? '',
            $validated['arguments'] ?? ''
        );

        return response()->json(['created' => $created]);
    }

    public function registerProtocol(Request $request): JsonResponse
    {
        $scheme = $request->input('scheme', 'corex');
        return response()->json([
            'registered' => FileAssociationService::registerProtocol($scheme),
        ]);
    }

    public function unregisterProtocol(Request $request): JsonResponse
    {
        $scheme = $request->input('scheme', 'corex');
        return response()->json([
            'unregistered' => FileAssociationService::unregisterProtocol($scheme),
        ]);
    }

    public function registerAssociation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'extension' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:1000',
        ]);

        return response()->json([
            'registered' => FileAssociationService::registerFileAssociation(
                $validated['extension'],
                null,
                $validated['description'] ?? null,
                $validated['icon'] ?? null
            ),
        ]);
    }

    public function unregisterAssociation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'extension' => 'required|string|max:50',
        ]);

        return response()->json([
            'unregistered' => FileAssociationService::unregisterFileAssociation($validated['extension']),
        ]);
    }

    public function registerContextMenu(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'extension' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'command' => 'required|string|max:2000',
            'icon' => 'nullable|string|max:1000',
        ]);

        return response()->json([
            'registered' => FileAssociationService::registerContextMenu(
                $validated['extension'],
                $validated['label'],
                $validated['command'],
                $validated['icon'] ?? null
            ),
        ]);
    }

    public function registerExplorerMenu(): JsonResponse
    {
        return response()->json([
            'registered' => FileAssociationService::registerExplorerContextMenu(),
        ]);
    }

    public function unregisterExplorerMenu(): JsonResponse
    {
        return response()->json([
            'unregistered' => FileAssociationService::unregisterExplorerContextMenu(),
        ]);
    }

    public function sendTo(): JsonResponse
    {
        return response()->json([
            'registered' => FileAssociationService::registerSendTo(),
        ]);
    }

    public function jumpListAddRecent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:1000',
            'title' => 'nullable|string|max:255',
        ]);

        return response()->json([
            'added' => JumpListService::addRecent($validated['path'], $validated['title'] ?? null),
        ]);
    }

    public function jumpListAddTask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'path' => 'required|string|max:1000',
            'icon' => 'nullable|string|max:1000',
            'args' => 'nullable|string|max:500',
        ]);

        return response()->json([
            'added' => JumpListService::addTask(
                $validated['title'],
                $validated['path'],
                $validated['icon'] ?? null,
                $validated['args'] ?? null
            ),
        ]);
    }

    public function jumpListClear(): JsonResponse
    {
        JumpListService::clear();
        return response()->json(['cleared' => true]);
    }

    public function jumpListRecent(): JsonResponse
    {
        return response()->json([
            'files' => JumpListService::getRecentFiles(20),
        ]);
    }

    public function associations(): JsonResponse
    {
        return response()->json([
            'extensions' => FileAssociationService::getRegisteredExtensions(),
        ]);
    }

    public function refreshExplorer(): JsonResponse
    {
        FileAssociationService::refreshExplorer();
        return response()->json(['refreshed' => true]);
    }
}
