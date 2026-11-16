<?php

use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NoteSearchController;
use App\Http\Controllers\Api\NoteSummaryController;
use App\Http\Controllers\Api\NoteSummaryStatusController;
use App\Http\Controllers\Api\QueueNoteSummaryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\NoteStatsController;
use Illuminate\Support\Facades\Route;

Route::get('/notes/search', NoteSearchController::class)
    ->middleware('throttle:semantic-search');

Route::middleware('throttle:api')->group(function () {
    Route::get('/user', UserController::class)->middleware('auth:sanctum');

    Route::get('/notes/stats', NoteStatsController::class);
    Route::apiResource('notes', NoteController::class);
});

Route::post('/notes/{note}/summary', NoteSummaryController::class)
    ->middleware('throttle:ai-summary');

Route::post('/notes/{note}/summary/async', QueueNoteSummaryController::class)
    ->middleware('throttle:ai-summary');

Route::get('/notes/{note}/summary/status', NoteSummaryStatusController::class)
    ->middleware('throttle:api');
