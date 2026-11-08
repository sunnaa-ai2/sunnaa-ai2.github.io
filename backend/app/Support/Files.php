<?php

namespace App\Support;

use App\Models\Profile;
use App\Models\Work;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Files
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('spark.disk'));
    }

    /** Stores a work upload under works/{id}/ and returns [path, original name, mime]. */
    public static function storeWork(string $workId, UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $path = $file->storeAs("works/{$workId}", Str::limit($base, 60, '').'-'.Str::random(6).'.'.$ext, ['disk' => config('spark.disk')]);
        $name = Str::limit(basename($file->getClientOriginalName()), 150, '') ?: "file.{$ext}";

        return [$path, $name, $file->getMimeType() ?: 'application/octet-stream'];
    }

    public static function storeAvatar(string $userId, UploadedFile $file): string
    {
        $ext = in_array($file->guessExtension(), ['png', 'webp', 'gif'], true) ? $file->guessExtension() : 'jpg';

        return $file->storeAs("avatars/{$userId}", 'avatar-'.now()->timestamp.'.'.$ext, ['disk' => config('spark.disk')]);
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            self::disk()->delete($path);
        }
    }

    /** Deletes every file that belongs to a member (works + avatar). */
    public static function purgeMember(Profile $profile): void
    {
        Work::where('user_id', $profile->user_id)->whereNotNull('file_path')->pluck('file_path')->each(fn ($p) => self::delete($p));
        self::delete($profile->avatar_path);
    }

    /** Images and PDFs open in the browser; everything else downloads, so an uploaded page can never run. */
    public static function isInline(?string $mime): bool
    {
        return $mime === 'application/pdf' || (is_string($mime) && str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml');
    }
}
