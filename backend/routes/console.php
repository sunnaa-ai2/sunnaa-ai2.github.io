<?php

use App\Models\LoginToken;
use Illuminate\Support\Facades\Schedule;

// Old login links are useless after they expire; keep the table small.
Schedule::call(fn () => LoginToken::where('expires_at', '<', now()->subDay())->delete())->daily();
