<?php

use App\Http\Controllers\TccUnifastSyncController;
use App\Http\Controllers\TccUnifastStudentsController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/n8n/tcc-unifast/sync', TccUnifastSyncController::class)
    ->middleware('throttle:30,1')
    ->name('integrations.n8n.tcc-unifast.sync');

Route::get('/integrations/n8n/tcc-unifast/students', TccUnifastStudentsController::class)
    ->middleware('throttle:120,1')
    ->name('integrations.n8n.tcc-unifast.students');
