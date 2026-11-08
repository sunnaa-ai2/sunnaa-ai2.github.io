<?php

namespace App\Http\Controllers;

use App\Models\Work;
use App\Support\Files;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkController extends Controller
{
    private function rules(bool $creating): array
    {
        $max = config('spark.max_upload_kb');

        return [
            'exercise_no' => ['required', 'integer', 'between:1,999'],
            'kind' => [$creating ? 'required' : 'nullable', 'in:image,pdf,file,link'],
            'url' => ['required_if:kind,link', 'nullable', 'url:http,https', 'max:2048'],
            'file' => ['required_if:kind,image,pdf,file', 'nullable', 'file', "max:{$max}"],
        ];
    }

    private function checkFileType(Request $request, string $kind): void
    {
        if ($kind === 'image') {
            $request->validate(['file' => ['mimes:jpg,jpeg,png,webp,gif']]);
        } elseif ($kind === 'pdf') {
            $request->validate(['file' => ['mimetypes:application/pdf']]);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(true));
        $this->checkFileType($request, $data['kind']);
        $id = (string) Str::uuid();
        $attrs = ['id' => $id, 'user_id' => $request->user()->id, 'exercise_no' => (int) $data['exercise_no'], 'kind' => $data['kind']];

        if ($data['kind'] === 'link') {
            $attrs['url'] = $data['url'];
        } else {
            [$attrs['file_path'], $attrs['file_name'], $attrs['mime']] = Files::storeWork($id, $request->file('file'));
        }

        return response()->json(Present::work(Work::create($attrs)), 201);
    }

    /** Owner (or admin) can change the exercise number and optionally replace the content. */
    public function update(Request $request, Work $work)
    {
        $user = $request->user();
        abort_unless($work->user_id === $user->id || $user->isAdmin(), 403, 'FORBIDDEN');
        $data = $request->validate($this->rules(false));
        $work->exercise_no = (int) $data['exercise_no'];

        if (! empty($data['kind'])) {
            $this->checkFileType($request, $data['kind']);
            $old = $work->file_path;
            $work->kind = $data['kind'];
            if ($data['kind'] === 'link') {
                $work->fill(['url' => $data['url'], 'file_path' => null, 'file_name' => null, 'mime' => null]);
            } else {
                [$path, $name, $mime] = Files::storeWork($work->id, $request->file('file'));
                $work->fill(['url' => null, 'file_path' => $path, 'file_name' => $name, 'mime' => $mime]);
            }
            $work->save();
            if ($old && $old !== $work->file_path) {
                Files::delete($old);
            }
        } else {
            $work->save();
        }

        return response()->json(Present::work($work->fresh()));
    }

    public function destroy(Request $request, Work $work)
    {
        $user = $request->user();
        abort_unless($work->user_id === $user->id || $user->isAdmin(), 403, 'FORBIDDEN');
        Files::delete($work->file_path);
        $work->delete();

        return response()->json(['ok' => true]);
    }
}
