<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// AI Routes
Route::prefix('ai')->group(function () {
    Route::post('/process', [AIController::class, 'processInput']);
    Route::get('/status', [AIController::class, 'getStatus']);
    Route::get('/word-relations', [AIController::class, 'getWordRelations']);
    Route::post('/generate-sentence', [AIController::class, 'generateSentence']);
});
