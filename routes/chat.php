<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\ConversationMuteController;
use App\Http\Controllers\ConversationParticipantController;
use App\Http\Controllers\ConversationPresenceController;
use App\Http\Controllers\ConversationReadController;
use Illuminate\Support\Facades\Route;

// Messages (messages.*). Required from inside the `auth` group in
// routes/web.php.

Route::get('/messages', [ConversationController::class, 'index'])->name('messages.index');
Route::get('/messages/create', [ConversationController::class, 'create'])->name('messages.create');
Route::post('/messages', [ConversationController::class, 'store'])->name('messages.store');
Route::get('/messages/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
Route::post('/messages/{conversation}/send', [ConversationMessageController::class, 'store'])->name('messages.send');
Route::get('/messages/{conversation}/messages', [ConversationMessageController::class, 'index'])->name('messages.messages.index');
Route::delete('/messages/{conversation}/messages/{message}', [ConversationMessageController::class, 'destroy'])
    ->name('messages.messages.destroy')
    ->scopeBindings();
Route::post('/messages/{conversation}/participants', [ConversationParticipantController::class, 'store'])->name('messages.participants.store');
Route::delete('/messages/{conversation}/participants', [ConversationParticipantController::class, 'destroy'])->name('messages.participants.destroy');
Route::post('/messages/{conversation}/read', [ConversationReadController::class, 'store'])->name('messages.read');
Route::post('/messages/{conversation}/presence', [ConversationPresenceController::class, 'store'])->name('messages.presence');
Route::put('/messages/{conversation}/mute', [ConversationMuteController::class, 'update'])->name('messages.mute');
