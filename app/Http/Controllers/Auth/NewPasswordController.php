<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * The token alone identifies the account, so this doesn't hard-fail on
     * an invalid/expired token — it just can't show a username. The real
     * rejection happens on submit.
     */
    public function create(string $token): Response
    {
        return Inertia::render('auth/ResetPassword', [
            'token' => $token,
            'username' => $this->resolveRequest($token)?->user->username,
        ]);
    }

    public function store(Request $request, ResetUserPassword $resetUserPassword): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $resetRequest = $this->resolveRequest($request->string('token')->value());

        if ($resetRequest === null) {
            throw ValidationException::withMessages(['token' => [__('passwords.token')]]);
        }

        $resetUserPassword->reset($resetRequest->user, $request->only('password', 'password_confirmation'));

        $resetRequest->delete();

        return redirect()->route('login')->with('status', __('passwords.reset'));
    }

    private function resolveRequest(string $token): ?PasswordResetRequest
    {
        $resetRequest = PasswordResetRequest::query()
            ->with('user')
            ->where('token', hash('sha256', $token))
            ->first();

        if ($resetRequest === null) {
            return null;
        }

        $expiresAt = $resetRequest->created_at->addMinutes(config('auth.passwords.users.expire', 60));

        return $expiresAt->isFuture() ? $resetRequest : null;
    }
}
