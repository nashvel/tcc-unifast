<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::get('/{path?}', fn () => view('app'))
    ->where('path', '^(?!api(?:/|$)|health$).*$');
