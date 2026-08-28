<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetRequested;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/ForgotPassword');
    }

    /**
     * Emails could match several accounts now that email isn't unique, so
     * this generates one reset token per matching account and sends a
     * single email listing all of them. Always responds the same way
     * whether or not the email matched anything, so the response can't be
     * used to check which emails have accounts.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $users = User::query()->where('email', $request->string('email')->value())->get();

        $links = $users->map(function (User $user) {
            PasswordResetRequest::query()->where('user_id', $user->id)->delete();

            $token = Str::random(64);

            PasswordResetRequest::query()->create([
                'user_id' => $user->id,
                'token' => hash('sha256', $token),
            ]);

            return [
                'user' => $user,
                'url' => route('password.reset', $token),
            ];
        });

        if ($links->isNotEmpty()) {
            Mail::to($request->string('email')->value())
                ->locale($users->first()->locale ?? 'en')
                ->send(new PasswordResetRequested($links->values()->all()));
        }

        return back()->with('status', __('passwords.sent'));
    }
}
