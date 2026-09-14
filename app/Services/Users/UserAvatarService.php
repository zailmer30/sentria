<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserAvatarService
{
    public const DISK = 'public';

    public const DIRECTORY = 'avatars';

    public function store(User $user, UploadedFile $file): void
    {
        $previous = $user->getAttributes()['avatar_path'] ?? null;
        $extension = $this->extension($file);
        $filename = $user->getKey().'.'.$extension;
        $path = $file->storeAs(self::DIRECTORY, $filename, self::DISK);

        $user->forceFill(['avatar_path' => $path])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }
    }

    public function remove(User $user): void
    {
        $previous = $user->getAttributes()['avatar_path'] ?? null;

        if ($previous === null || $previous === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($previous);
        $user->forceFill(['avatar_path' => null])->save();
    }

    private function extension(UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg'));

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        if (! in_array($extension, ['jpg', 'png', 'webp'], true)) {
            return 'jpg';
        }

        return $extension;
    }
}
