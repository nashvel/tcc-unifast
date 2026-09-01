<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::get('/{path?}', function (?string $path = null) {
    $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
    $target = $frontendUrl.($path ? '/'.ltrim($path, '/') : '/');

    if (request()->getQueryString()) {
        $target .= '?'.request()->getQueryString();
    }

    return redirect()->away($target);
})
    ->where('path', '^(?!api(?:/|$)|health$).*$');
