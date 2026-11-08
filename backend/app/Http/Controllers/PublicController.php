<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Profile;
use App\Models\Work;
use App\Support\Present;

/** Everything a visitor can see without signing in. */
class PublicController extends Controller
{
    public function profiles()
    {
        return response()->json(Profile::orderBy('created_at')->get()->map(fn ($p) => Present::profile($p)));
    }

    public function works()
    {
        return response()->json(Work::orderBy('created_at')->get()->map(fn ($w) => Present::work($w)));
    }

    public function exercises()
    {
        return response()->json(Exercise::orderBy('no')->get()->map(fn ($e) => Present::exercise($e)));
    }
}
