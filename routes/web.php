<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AIController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\AIChatController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\SearchController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    if (auth()->guard('admin')->check()) {
            return redirect()->route('back.pages.index');
        }
        return redirect()->route('admin.login');
});

Route::prefix('admin')->group(function () {
    Route::get('/', function () {
        if (auth()->guard('admin')->check()) {
            return redirect()->route('back.pages.index');
        }
        return redirect()->route('admin.login');
    });

    Route::get('login', [AdminController::class, 'showLoginForm'])->name('admin.login')->middleware('guest:admin');
    Route::post('login', [AdminController::class, 'login'])->name('handle-login');

    Route::middleware('auth:admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');

        Route::get('profile', function () {
            return view('back.admin.profile');
        })->name('admin.profile');

        Route::post('logout', [AdminController::class, 'logout'])->name('admin.logout');

        // AI Modelleri için rotalar
        Route::prefix('ai')->name('admin.ai.')->group(function () {
            Route::get('/', [AIController::class, 'index'])->name('index');
            Route::get('/create', [AIController::class, 'create'])->name('create');
            Route::post('/', [AIController::class, 'store'])->name('store');
            Route::get('/{aiModel}', [AIController::class, 'show'])->name('show');
            Route::get('/{aiModel}/edit', [AIController::class, 'edit'])->name('edit');
            Route::put('/{aiModel}', [AIController::class, 'update'])->name('update');
            Route::delete('/{aiModel}', [AIController::class, 'destroy'])->name('destroy');
            Route::post('/{aiModel}/train', [AIController::class, 'train'])->name('train');
            Route::post('/{aiModel}/generate-response', [AIController::class, 'generateResponse'])->name('generate-response');
        });

        // AI Sohbet için rotalar
        Route::prefix('chat')->name('back.chat.')->group(function () {
            Route::get('/', [AIChatController::class, 'index'])->name('index');
            Route::get('/create', [AIChatController::class, 'create'])->name('create');
            Route::post('/', [AIChatController::class, 'store'])->name('store');
            Route::get('/{conversation}', [AIChatController::class, 'show'])->name('show');
            Route::post('/{conversation}/send', [AIChatController::class, 'sendMessage'])->name('send-message');
        });

        // Sayfalar için rotalar
        Route::prefix('pages')->name('back.pages.')->group(function () {
            Route::get('/', [PageController::class, 'index'])->name('index');
            Route::get('/create', [PageController::class, 'create'])->name('create');
            Route::post('/', [PageController::class, 'store'])->name('store');
            Route::get('/{page}/edit', [PageController::class, 'edit'])->name('edit');
            Route::put('/{page}', [PageController::class, 'update'])->name('update');
            Route::delete('/{page}', [PageController::class, 'destroy'])->name('destroy');
        });
    });
});

Route::get('/', [ChatController::class, 'index'])->name('chat');

// Yönetim Paneli Routes
Route::prefix('manage')->name('manage.')->group(function () {
    Route::get('/', [ManageController::class, 'index'])->name('index');
    
    // Ayarlar ve eğitim
    Route::post('/update-settings', [ManageController::class, 'updateSettings']);
    Route::post('/train', [ManageController::class, 'trainModel']);
    Route::get('/train-status', [ManageController::class, 'getSystemStatus']);
    
    // Otomatik eğitim sistemi
    Route::post('/automated-learning', [ManageController::class, 'startAutomatedLearning']);
    
    // Yeni eğitim ve öğrenme sistemi
    Route::post('/training/start', [ManageController::class, 'startTrainingProcess']);
    Route::get('/training/status', [ManageController::class, 'getTrainingStatus']);
    Route::post('/learning/start', [ManageController::class, 'startLearningProcess']);
    Route::get('/learning/status', [ManageController::class, 'getLearningStatus']);
    Route::get('/learning/progress', [ManageController::class, 'getLearningProgress']);
    Route::get('/learning/stats', [ManageController::class, 'getLearningSystemStats']);
    
    // Kelime öğrenme
    Route::post('/word/learn', [ManageController::class, 'learnWord']);
    Route::get('/word/search', [ManageController::class, 'searchWord']);
    
    // Veritabanı bakımı
    Route::post('/learning/clear', [ManageController::class, 'clearLearningSystem']);
});

// AI API rota tanımlamaları
Route::prefix('api/ai')->group(function () {
    Route::post('/chat', [AIController::class, 'chat']);
    Route::get('/word/{word}', [AIController::class, 'getWordInfo']);
    Route::get('/search', [AIController::class, 'searchWords']);
    Route::get('/status', [AIController::class, 'getStatus']);
    Route::get('/learning-status', [AIController::class, 'getLearningStatus']);
    Route::get('/chat/{chat_id}', [AIController::class, 'getChatHistory']);
    Route::get('/chats', [AIController::class, 'getUserChats']);
});

// APIController için API route
Route::post('/api/ai/process', [ChatController::class, 'sendMessage']);

// Arama API rotaları
Route::prefix('api/search')->group(function () {
    Route::get('/', [SearchController::class, 'search']);
    Route::get('/ai', [SearchController::class, 'aiSearch']);
});

// Arama sonuç sayfası rotası (HTML görünümü için)
Route::get('/search', [SearchController::class, 'search'])->name('search');
