<?php

namespace App\Http\Controllers;

use App\Models\Work;
use App\Models\WorkFile;
use App\Support\Files;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * One work = one exercise submission: either 1 to 8 files (any type) or a link.
 * Requests send the files as files[]; "image" / "pdf" / "file" with a single "file" are the
 * older form and still accepted (they mean "files").
 */
class WorkController extends Controller
{
    private function rules(bool $creating): array
    {
        return [
            'exercise_no' => ['required', 'integer', 'between:1,999'],
            'kind' => [$creating ? 'required' : 'nullable', 'in:files,link,image,pdf,file'],
            'url' => ['required_if:kind,link', 'nullable', 'url:http,https', 'max:2048'],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['string'],
        ];
    }

    /** The uploaded files of the request, validated one by one and together. */
    private function uploads(Request $request): array
    {
        $files = $request->file('files') ?? ($request->file('file') ? [$request->file('file')] : []);
        $files = is_array($files) ? array_values($files) : [$files];
        $max = config('spark.max_upload_kb');

        Validator::make(['files' => $files], [
            'files' => ['array', 'max:'.config('spark.max_files')],
            'files.*' => ['file', "max:{$max}"],
        ], ['files.max' => 'TOO_MANY_FILES', 'files.*.max' => 'FILE_TOO_LARGE', 'files.*.file' => 'UPLOAD_FAILED'])->validate();

        $total = array_sum(array_map(fn (UploadedFile $f) => $f->getSize(), $files));
        abort_if($total > $max * 1024, 422, 'FILES_TOO_LARGE');

        return $files;
    }

    /** Stores uploads at the end of the work's list. */
    private function addFiles(Work $work, array $uploads): void
    {
        $pos = (int) ($work->files()->max('position') ?? -1) + 1;
        foreach ($uploads as $upload) {
            [$path, $name, $mime] = Files::storeWork($work->id, $upload);
            WorkFile::create([
                'work_id' => $work->id,
                'position' => $pos++,
                'kind' => Files::kindOf($mime),
                'path' => $path,
                'name' => $name,
                'mime' => $mime,
                'size' => (int) $upload->getSize(),
            ]);
        }
    }

    private static function isLink(?string $kind): bool
    {
        return $kind === 'link';
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(true));
        $link = self::isLink($data['kind']);
        $uploads = $link ? [] : $this->uploads($request);
        abort_if(! $link && ! $uploads, 422, 'FILES_REQUIRED');

        $work = new Work([
            'id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'exercise_no' => (int) $data['exercise_no'],
            'kind' => $link ? 'link' : 'files',
            'url' => $link ? $data['url'] : null,
        ]);

        try {
            DB::transaction(function () use ($work, $uploads) {
                $work->save();
                $this->addFiles($work, $uploads);
            });
        } catch (\Throwable $e) {
            Files::deleteWork($work->id);
            throw $e;
        }

        return response()->json(Present::work($work->load('files')), 201);
    }

    /**
     * Owner (or admin) can change the exercise number, remove some files (remove[] = file ids),
     * add files (files[]), or turn the work into a link / back into files.
     */
    public function update(Request $request, Work $work)
    {
        $user = $request->user();
        abort_unless($work->user_id === $user->id || $user->isAdmin(), 403, 'FORBIDDEN');
        $data = $request->validate($this->rules(false));
        $kind = $data['kind'] ?? null;
        $uploads = self::isLink($kind) ? [] : $this->uploads($request);
        // the older form (one "file") replaced the content instead of adding to it
        $replaceAll = $request->hasFile('file') && ! $request->hasFile('files');

        $work->exercise_no = (int) $data['exercise_no'];

        if (self::isLink($kind)) {
            $old = $work->files()->get();
            $work->fill(['kind' => 'link', 'url' => $data['url']])->save();
            $old->each(fn ($f) => $f->delete());
            Files::deleteWork($work->id);
        } elseif ($kind || $uploads || ! empty($data['remove'])) {
            $wasLink = $work->kind === 'link';
            $remove = $wasLink ? collect() : ($replaceAll ? $work->files()->get() : $work->files()->whereIn('id', $data['remove'] ?? [])->get());
            $left = ($wasLink ? 0 : $work->files()->count()) - $remove->count() + count($uploads);
            abort_if($left > config('spark.max_files'), 422, 'TOO_MANY_FILES');
            abort_if($left < 1, 422, 'FILES_REQUIRED');

            $work->fill(['kind' => 'files', 'url' => null])->save();
            foreach ($remove as $f) {
                Files::delete($f->path);
                $f->delete();
            }
            $this->addFiles($work, $uploads);
            $work->touch();
        } else {
            $work->save();
        }

        return response()->json(Present::work($work->fresh('files')));
    }

    public function destroy(Request $request, Work $work)
    {
        $user = $request->user();
        abort_unless($work->user_id === $user->id || $user->isAdmin(), 403, 'FORBIDDEN');
        $work->delete();   // work_files rows go with it
        Files::deleteWork($work->id);

        return response()->json(['ok' => true]);
    }
}
