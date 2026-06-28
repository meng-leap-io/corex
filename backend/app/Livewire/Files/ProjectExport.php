<?php

declare(strict_types=1);

namespace App\Livewire\Files;

use App\Models\File;
use App\Models\Project;
use App\Services\Supabase\Storage\FileManagementService;
use Illuminate\View\View;
use Livewire\Component;

class ProjectExport extends Component
{
    public ?string $projectId = null;

    public array $selectedFiles = [];

    public bool $exporting = false;

    public bool $importing = false;

    public ?string $exportPath = null;

    public array $importedFiles = [];

    public ?string $importToken = null;

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    public function mount(?string $projectId = null): void
    {
        $this->projectId = $projectId;
    }

    public function getProjectFilesProperty()
    {
        if ($this->projectId === null) {
            return collect();
        }

        return File::byUser(auth()->id())
            ->where('project_id', $this->projectId)
            ->orWhere(function ($query) {
                $query->byBucket(FileManagementService::BUCKET_PROJECTS)
                    ->byUser(auth()->id());
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getProjectsProperty()
    {
        return Project::byUser(auth()->id())->active()->get();
    }

    public function toggleFile(string $fileId): void
    {
        $idx = array_search($fileId, $this->selectedFiles, true);

        if ($idx !== false) {
            unset($this->selectedFiles[$idx]);
        } else {
            $this->selectedFiles[] = $fileId;
        }

        $this->selectedFiles = array_values($this->selectedFiles);
    }

    public function selectAll(): void
    {
        $this->selectedFiles = $this->projectFiles->pluck('id')->toArray();
    }

    public function deselectAll(): void
    {
        $this->selectedFiles = [];
    }

    public function export(): void
    {
        if (empty($this->selectedFiles)) {
            $this->dispatch('notify', message: 'Select files to export.', type: 'warning');

            return;
        }

        $this->exporting = true;

        try {
            $fileManagement = app(FileManagementService::class);
            $files = File::whereIn('id', $this->selectedFiles)->get();

            $project = $this->projectId ? Project::find($this->projectId) : null;
            $projectName = $project?->name ?? 'project-export';

            $result = $fileManagement->exportProject($files->all(), $projectName);

            $this->exportPath = $result['path'];
            $this->exporting = false;

            $this->dispatch('notify', message: "Exported {$result['file_count']} files.");
        } catch (\Throwable $e) {
            $this->exporting = false;
            $this->dispatch('notify', message: 'Export failed: ' . $e->getMessage(), type: 'error');
        }
    }

    public function import(): void
    {
        if (!$this->importToken) {
            $this->dispatch('notify', message: 'Provide an export path or token.', type: 'warning');

            return;
        }

        $this->importing = true;

        try {
            $fileManagement = app(FileManagementService::class);
            $files = $fileManagement->importProject(
                $this->importToken,
                auth()->user(),
                $this->projectId,
            );

            $this->importedFiles = $files;
            $this->importing = false;
            $this->importToken = null;

            $this->dispatch('notify', message: 'Imported ' . count($files) . ' files.');
            $this->dispatch('refresh');
        } catch (\Throwable $e) {
            $this->importing = false;
            $this->dispatch('notify', message: 'Import failed: ' . $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.files.project-export');
    }
}
