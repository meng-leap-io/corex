<?php

declare(strict_types=1);

namespace App\Livewire\Files;

use App\Services\Supabase\Storage\FileManagementService;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class AvatarUpload extends Component
{
    use WithFileUploads;

    public $avatar;

    public bool $uploading = false;

    public ?string $previewUrl = null;

    protected $rules = [
        'avatar' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:2048'],
    ];

    public function mount(): void
    {
        $this->previewUrl = auth()->user()?->avatar;
    }

    public function updatedAvatar(): void
    {
        $this->validate();

        $this->previewUrl = $this->avatar->temporaryUrl();
    }

    public function save(): void
    {
        $this->validate();

        $this->uploading = true;

        try {
            $fileManagement = app(FileManagementService::class);
            $file = $fileManagement->uploadAvatar(auth()->user(), $this->avatar);

            $this->previewUrl = $file->url;
            $this->avatar = null;

            $this->dispatch('avatarUpdated', url: $file->url);
            $this->dispatch('notify', message: 'Avatar updated successfully.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: 'Avatar update failed: ' . $e->getMessage(), type: 'error');
        } finally {
            $this->uploading = false;
        }
    }

    public function remove(): void
    {
        $user = auth()->user();

        $existingAvatars = \App\Models\File::where('user_id', $user->id)
            ->where('bucket', 'avatars')
            ->where('metadata->type', 'avatar')
            ->get();

        try {
            $fileManagement = app(FileManagementService::class);

            foreach ($existingAvatars as $file) {
                $fileManagement->delete($file);
            }

            $user->update(['avatar' => null]);
            $this->previewUrl = null;

            $this->dispatch('avatarUpdated', url: null);
            $this->dispatch('notify', message: 'Avatar removed.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: 'Failed to remove avatar.', type: 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.files.avatar-upload');
    }
}
