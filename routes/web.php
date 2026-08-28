<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ContactGroupController;
use App\Http\Controllers\ContactGroupMemberController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\ContactTagPreferenceController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\ConversationParticipantController;
use App\Http\Controllers\EventAttendanceController;
use App\Http\Controllers\EventCommentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventImageController;
use App\Http\Controllers\EventInvitationController;
use App\Http\Controllers\EventSearchabilityController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Moderation\ReportController as ModerationReportController;
use App\Http\Controllers\Moderation\StaffConversationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        return redirect()->route('events.index');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::put('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    Route::get('/users/{user}', [UserProfileController::class, 'show'])->name('users.show');

    Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/search', [ContactsController::class, 'search'])->name('contacts.search');
    Route::put('/contacts/{contact}/tag-preferences', [ContactTagPreferenceController::class, 'update'])->name('contacts.tag-preferences.update');

    Route::post('/friendships', [FriendshipController::class, 'store'])->name('friendships.store');
    Route::patch('/friendships/{friendship}', [FriendshipController::class, 'update'])->name('friendships.update');
    Route::delete('/friendships/{friendship}', [FriendshipController::class, 'destroy'])->name('friendships.destroy');

    Route::post('/follows', [FollowController::class, 'store'])->name('follows.store');
    Route::patch('/follows/{follow}', [FollowController::class, 'update'])->name('follows.update');
    Route::delete('/follows/{follow}', [FollowController::class, 'destroy'])->name('follows.destroy');

    Route::resource('contact-groups', ContactGroupController::class)->only(['store', 'update', 'destroy']);
    Route::post('/contact-groups/{contact_group}/members', [ContactGroupMemberController::class, 'store'])->name('contact-groups.members.store');
    Route::delete('/contact-groups/{contact_group}/members/{member}', [ContactGroupMemberController::class, 'destroy'])->name('contact-groups.members.destroy');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/search', [EventController::class, 'search'])->name('events.search');
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::put('/events/{event}/attendance', [EventAttendanceController::class, 'update'])->name('events.attendance.update');
    Route::post('/events/{event}/invitations', [EventInvitationController::class, 'store'])->name('events.invitations.store');
    Route::post('/events/{event}/comments', [EventCommentController::class, 'store'])->name('events.comments.store');
    Route::delete('/events/{event}/comments/{comment}', [EventCommentController::class, 'destroy'])
        ->name('events.comments.destroy')
        ->scopeBindings();
    Route::delete('/events/{event}/images/{image}', [EventImageController::class, 'destroy'])
        ->name('events.images.destroy')
        ->scopeBindings();
    Route::post('/events/{event}/searchable', [EventSearchabilityController::class, 'store'])->name('events.searchable.store');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'update'])->name('notifications.read');

    Route::get('/messages', [ConversationController::class, 'index'])->name('messages.index');
    Route::get('/messages/create', [ConversationController::class, 'create'])->name('messages.create');
    Route::post('/messages', [ConversationController::class, 'store'])->name('messages.store');
    Route::get('/messages/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}/send', [ConversationMessageController::class, 'store'])->name('messages.send');
    Route::delete('/messages/{conversation}/messages/{message}', [ConversationMessageController::class, 'destroy'])
        ->name('messages.messages.destroy')
        ->scopeBindings();
    Route::post('/messages/{conversation}/participants', [ConversationParticipantController::class, 'store'])->name('messages.participants.store');
    Route::delete('/messages/{conversation}/participants', [ConversationParticipantController::class, 'destroy'])->name('messages.participants.destroy');

    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

    // Reachable only while impersonating (see RestrictImpersonatedRequests) —
    // the acting guard user at that point is the impersonated member, not an
    // administrator, so this must sit outside the `administrator` group.
    Route::post('/admin/impersonate/exit', [AdminUserController::class, 'stopImpersonating'])->name('admin.impersonate.exit');

    Route::middleware('staff')->prefix('admin/users/{user}')->name('admin.users.')->group(function () {
        Route::post('/suspension', [AdminUserController::class, 'suspend'])->name('suspend');
        Route::delete('/suspension', [AdminUserController::class, 'unsuspend'])->name('unsuspend');
    });

    Route::middleware('staff')->prefix('moderation')->name('moderation.')->group(function () {
        Route::get('/reports', [ModerationReportController::class, 'index'])->name('reports.index');
        Route::patch('/reports/{report}', [ModerationReportController::class, 'update'])->name('reports.update');
        Route::post('/users/{user}/message', [StaffConversationController::class, 'store'])->name('users.message');
    });

    Route::middleware('administrator')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::patch('/{user}/role', [AdminUserController::class, 'updateRole'])->name('role.update');
        Route::post('/{user}/email', [AdminUserController::class, 'sendEmail'])->name('email.store');
        Route::post('/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('impersonate');
        Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
    });
});
