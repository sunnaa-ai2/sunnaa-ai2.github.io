<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(config('spark.frontend_url')));
