<?php

declare(strict_types=1);

namespace App\Livewire\Files;

use App\Models\File;
use App\Services\Supabase\Storage\FileManagementService;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileManager extends Component
{
    use WithFileUploads;

    public string $bucket = 'projects';

    public string $view = 'grid';

    public ?string $search = null;

    public string $filter = 'all';

    public ?string $selectedFileId = null;

    public $upload;

    public bool $showUploadModal = false;

    public bool $showDeleteModal = false;

    public bool $showShareModal = false;

    public string $shareUrl = '';

    public int $shareExpiresIn = 86400;

    protected $listeners = [
        'fileUploaded' => 'refresh',
        'fileDeleted' => 'refresh',
    ];

    protected $rules = [
        'upload' => ['nullable', 'file', 'max:102400'],
        'bucket' => ['required', 'string', 'in:projects,documents,exports'],
    ];

    public function render(): View
    {
        $query = File::byUser(auth()->id())->byBucket($this->bucket);

        if ($this->search) {
            $query->where('original_name', 'ilike', "%{$this->search}%");
        }

        if ($this->filter === 'image') {
            $query->images();
        } elseif ($this->filter === 'document') {
            $query->documents();
        }

        $files = $query->orderBy('created_at', 'desc')->paginate(24);

        $stats = [
            'total' => File::byUser(auth()->id())->byBucket($this->bucket)->count(),
            'images' => File::byUser(auth()->id())->byBucket($this->bucket)->images()->count(),
            'documents' => File::byUser(auth()->id())->byBucket($this->bucket)->documents()->count(),
            'total_size' => File::byUser(auth()->id())->byBucket($this->bucket)->sum('size'),
        ];

        return view('livewire.files.file-manager', [
            'files' => $files,
            'stats' => $stats,
        ]);
    }

    public function uploadFile(): void
    {
        $this->validate();

        if ($this->upload === null) {
            return;
        }

        try {
            $fileManagement = app(FileManagementService::class);

            $fileManagement->upload(
                auth()->user(),
                $this->upload,
                $this->bucket,
                null,
                ['source' => 'file-manager'],
            );

            $this->upload = null;
            $this->showUploadModal = false;

            $this->dispatch('fileUploaded');
            $this->dispatch('notify', message: 'File uploaded successfully.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: 'Upload failed: '.$e->getMessage(), type: 'error');
        }
    }

    public function confirmDelete(string $fileId): void
    {
        $this->selectedFileId = $fileId;
        $this->showDeleteModal = true;
    }

    public function deleteFile(): void
    {
        $file = File::find($this->selectedFileId);

        if ($file && $file->user_id === auth()->id()) {
            try {
                $fileManagement = app(FileManagementService::class);
                $fileManagement->delete($file);

                $this->showDeleteModal = false;
                $this->selectedFileId = null;

                $this->dispatch('fileDeleted');
                $this->dispatch('notify', message: 'File deleted.');
            } catch (\Throwable $e) {
                $this->dispatch('notify', message: 'Delete failed: '.$e->getMessage(), type: 'error');
            }
        }
    }

    public function shareFile(string $fileId): void
    {
        $file = File::find($fileId);

        if ($file && $file->user_id === auth()->id()) {
            try {
                $fileManagement = app(FileManagementService::class);
                $link = $fileManagement->createShareLink($file, $this->shareExpiresIn);

                $this->shareUrl = $link['url'];
                $this->selectedFileId = $fileId;
                $this->showShareModal = true;
            } catch (\Throwable $e) {
                $this->dispatch('notify', message: 'Share link creation failed.', type: 'error');
            }
        }
    }

    public function copyShareUrl(): void
    {
        $this->dispatch('copyToClipboard', text: $this->shareUrl);
        $this->dispatch('notify', message: 'Share URL copied.');
    }

    public function setBucket(string $bucket): void
    {
        $this->bucket = $bucket;
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function toggleView(): void
    {
        $this->view = $this->view === 'grid' ? 'list' : 'grid';
    }

    public function getFormattedSizeProperty(): string
    {
        $size = $this->stats['total_size'] ?? 0;

        if ($size < 1024) {
            return "{$size} B";
        }

        if ($size < 1024 * 1024) {
            return round($size / 1024, 1).' KB';
        }

        return round($size / (1024 * 1024), 1).' MB';
    }
}
