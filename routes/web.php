<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\BotReplyController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// Telegram webhook (public, protected by the secret token header)
Route::post('/telegram/webhook/{bot}', TelegramWebhookController::class)->name('telegram.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [AuthController::class, 'home'])->name('home');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Superadmin
    Route::middleware('role:superadmin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('companies', CompanyController::class)->except('show')->parameters(['companies' => 'company']);
    });

    // Company
    Route::middleware('role:company')->group(function () {
        Route::get('/bots', [BotController::class, 'index'])->name('bots.index');
        Route::post('/bots', [BotController::class, 'store'])->name('bots.store');
        Route::get('/bots/{bot}', [BotController::class, 'show'])->name('bots.show');
        Route::post('/bots/{bot}/webhook', [BotController::class, 'webhook'])->name('bots.webhook');
        Route::post('/bots/{bot}/toggle', [BotController::class, 'toggle'])->name('bots.toggle');
        Route::delete('/bots/{bot}', [BotController::class, 'destroy'])->name('bots.destroy');

        Route::scopeBindings()->group(function () {
            Route::resource('bots.replies', BotReplyController::class)->except(['index', 'show']);

            Route::get('/bots/{bot}/chats', [ChatController::class, 'index'])->name('chats.index');
            Route::get('/bots/{bot}/broadcast', [ChatController::class, 'broadcastForm'])->name('chats.broadcast');
            Route::post('/bots/{bot}/broadcast', [ChatController::class, 'broadcast'])->name('chats.broadcast.send');
            Route::get('/bots/{bot}/chats/{telegramUser}', [ChatController::class, 'show'])->name('chats.show');
            Route::post('/bots/{bot}/chats/{telegramUser}', [ChatController::class, 'send'])->name('chats.send');
        });
    });
});
