<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Work;
use App\Support\Files;

class FileController extends Controller
{
    public function work(Work $work)
    {
        abort_unless($work->file_path && Files::disk()->exists($work->file_path), 404);
        $inline = Files::isInline($work->mime);

        return Files::disk()->response($work->file_path, $work->file_name, [
            'Content-Type' => $inline ? $work->mime : 'application/octet-stream',
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
