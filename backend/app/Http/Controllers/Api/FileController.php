<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\ShareFileRequest;
use App\Http\Requests\Storage\UploadFileRequest;
use App\Models\File;
use App\Services\Supabase\Storage\FileManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(
        private readonly FileManagementService $fileManagement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'bucket' => 'nullable|string|in:projects,avatars,documents,exports',
            'type' => 'nullable|string|in:image,document,all',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $bucket = $request->input('bucket');
        $type = $request->input('type', 'all');
        $limit = $request->input('limit', 50);

        $query = File::byUser($request->user()->id)->recent($limit);

        if ($bucket) {
            $query->byBucket($bucket);
        }

        if ($type === 'image') {
            $query->images();
        } elseif ($type === 'document') {
            $query->documents();
        }

        $files = $query->get();

        return new JsonResponse([
            'data' => $files,
            'meta' => [
                'total' => $files->count(),
                'bucket' => $bucket,
                'type' => $type,
            ],
        ]);
    }

    public function store(UploadFileRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $bucket = $request->input('bucket', 'projects');
        $directory = $request->input('directory');
        $optimize = $request->boolean('optimize', true);

        try {
            $fileModel = $this->fileManagement->upload(
                $request->user(),
                $file,
                $bucket,
                $directory,
                ['optimize' => $optimize, 'source' => 'api'],
            );

            return new JsonResponse([
                'data' => $fileModel,
                'message' => 'File uploaded successfully.',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'message' => 'File upload failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(File $file): JsonResponse
    {
        if ($file->user_id !== auth()->id()) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        return new JsonResponse(['data' => $file]);
    }

    public function destroy(File $file): JsonResponse
    {
        if ($file->user_id !== auth()->id()) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        try {
            $this->fileManagement->delete($file);

            return new JsonResponse(['message' => 'File deleted.'], 200);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'message' => 'File deletion failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function download(File $file): Response|StreamedResponse
    {
        if ($file->user_id !== auth()->id()) {
            abort(403, 'Forbidden.');
        }

        $localPath = $this->fileManagement->download($file);

        if ($localPath === null) {
            abort(404, 'File not found in storage.');
        }

        return response()->stream(function () use ($localPath) {
            readfile($localPath);
        }, 200, [
            'Content-Type' => $file->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$file->original_name.'"',
            'Content-Length' => filesize($localPath),
        ]);
    }

    public function share(File $file, ShareFileRequest $request): JsonResponse
    {
        if ($file->user_id !== auth()->id()) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        $expiresIn = $request->input('expires_in', 86400);

        $link = $this->fileManagement->createShareLink($file, $expiresIn);

        return new JsonResponse([
            'data' => $link,
            'message' => 'Share link created.',
        ]);
    }

    public function shared(string $token): JsonResponse
    {
        $file = $this->fileManagement->getSharedFile($token);

        if ($file === null) {
            return new JsonResponse(['message' => 'Share link is invalid or expired.'], 404);
        }

        $url = $this->fileManagement->getSignedDownloadUrl($file, 3600);

        return new JsonResponse([
            'data' => [
                'file' => $file,
                'download_url' => $url,
            ],
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:2048'],
        ]);

        try {
            $file = $this->fileManagement->uploadAvatar(
                $request->user(),
                $request->file('avatar'),
            );

            return new JsonResponse([
                'data' => $file,
                'avatar_url' => $file->url,
                'message' => 'Avatar updated.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'Avatar upload failed.'], 500);
        }
    }

    public function buckets(): JsonResponse
    {
        return new JsonResponse([
            'data' => config('supabase.storage.buckets', []),
        ]);
    }

    public function listRemote(Request $request): JsonResponse
    {
        $request->validate([
            'bucket' => 'required|string',
            'directory' => 'nullable|string',
        ]);

        $files = $this->fileManagement->listRemoteFiles(
            $request->input('bucket'),
            $request->input('directory', ''),
        );

        return new JsonResponse(['data' => $files]);
    }
}
