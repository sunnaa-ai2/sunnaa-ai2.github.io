<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Work;
use App\Models\WorkFile;
use App\Support\Files;

class FileController extends Controller
{
    /** First file of a work (kept for links made before works had several files). */
    public function work(Work $work)
    {
        $file = $work->files()->first();
        abort_unless($file, 404);

        return $this->serve($file);
    }

    public function workFile(WorkFile $file)
    {
        return $this->serve($file);
    }

    private function serve(WorkFile $file)
    {
        abort_unless(Files::disk()->exists($file->path), 404);
        $inline = Files::isInline($file->mime);

        return Files::disk()->response($file->path, $file->name, [
            'Content-Type' => $inline ? $file->mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=604800',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }

    public function avatar(Profile $profile)
    {
        abort_unless($profile->avatar_path && Files::disk()->exists($profile->avatar_path), 404);

        return Files::disk()->response($profile->avatar_path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
