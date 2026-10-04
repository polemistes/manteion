<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\PrivacyController;
use App\Http\Controllers\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

// Settings pages (settings.*). Required from inside the `auth` group in
// routes/web.php. The account page saves through Fortify's
// PUT /user/profile-information, PUT /user/password and DELETE /profile.

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::get('/settings/account', [AccountController::class, 'edit'])->name('settings.account');
Route::get('/settings/privacy', [PrivacyController::class, 'edit'])->name('settings.privacy');
Route::put('/settings/privacy', [PrivacyController::class, 'update'])->name('settings.privacy.update');
Route::get('/settings/notifications', [NotificationController::class, 'edit'])->name('settings.notifications');
Route::put('/settings/notifications', [NotificationController::class, 'update'])->name('settings.notifications.update');
Route::post('/settings/notifications/test', [NotificationController::class, 'test'])->middleware('throttle:6,1')->name('settings.notifications.test');
Route::get('/settings/appearance', [AppearanceController::class, 'edit'])->name('settings.appearance');
Route::put('/settings/appearance', [AppearanceController::class, 'update'])->name('settings.appearance.update');
Route::get('/settings/language', [SettingsController::class, 'language'])->name('settings.language');

// A device listed in the notification settings, removed by id (the other
// push-subscription routes, by endpoint, are in routes/web.php).
Route::delete('/push-subscriptions/{subscription}', [PushSubscriptionController::class, 'destroyDevice'])->name('push-subscriptions.destroy-device');
