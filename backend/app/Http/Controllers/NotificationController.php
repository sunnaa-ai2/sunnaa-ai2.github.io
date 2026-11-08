<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Support\Present;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            AppNotification::where('user_id', $request->user()->id)->latest()->limit(60)->get()->map(fn ($n) => Present::notification($n))
        );
    }

    public function read(Request $request)
    {
        AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
