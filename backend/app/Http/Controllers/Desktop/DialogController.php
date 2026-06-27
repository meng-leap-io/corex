<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DialogController extends Controller
{
    public function openFile(Request $request): JsonResponse
    {
        $filters = $request->input('filters', [
            ['name' => 'All Files', 'extensions' => ['*']],
        ]);
        $multi = $request->boolean('multiSelections', false);

        try {
            $result = native()->dialog()->open([
                'title' => $request->input('title', 'Open File'),
                'defaultPath' => $request->input('defaultPath'),
                'filters' => $filters,
                'multiSelections' => $multi,
                'properties' => ['openFile', ...($multi ? ['multiSelections'] : [])],
            ]);

            return response()->json([
                'canceled' => $result->canceled,
                'files' => $result->files ?? [],
                'filePaths' => $result->filePaths ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function saveFile(Request $request): JsonResponse
    {
        $filters = $request->input('filters', [
            ['name' => 'All Files', 'extensions' => ['*']],
        ]);

        try {
            $result = native()->dialog()->save([
                'title' => $request->input('title', 'Save File'),
                'defaultPath' => $request->input('defaultPath'),
                'filters' => $filters,
                'defaultName' => $request->input('defaultName'),
            ]);

            return response()->json([
                'canceled' => $result->canceled,
                'filePath' => $result->filePath ?? $result->file?->path,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function openFolder(Request $request): JsonResponse
    {
        try {
            $result = native()->dialog()->open([
                'title' => $request->input('title', 'Open Folder'),
                'defaultPath' => $request->input('defaultPath'),
                'properties' => ['openDirectory'],
            ]);

            return response()->json([
                'canceled' => $result->canceled,
                'path' => $result->filePaths[0] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function message(Request $request): JsonResponse
    {
        $type = $request->input('type', 'info');

        $validTypes = ['info', 'warning', 'error', 'question'];
        if (! in_array($type, $validTypes)) {
            $type = 'info';
        }

        $buttons = $request->input('buttons', ['OK']);
        $detail = $request->input('detail', '');

        try {
            $result = native()->dialog()->message(
                $request->input('message', ''),
                $type,
                $buttons,
                $request->input('title', 'Corex'),
                $detail
            );

            return response()->json([
                'response' => $result->response,
                'canceled' => $result->canceled,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
