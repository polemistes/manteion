<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class RejectSuspendedUsers
{
    public function __construct(protected StatefulGuard $guard) {}

    /**
     * Runs right after `AttemptToAuthenticate`, which has already logged
     * the user in by this point — log them back out and fail the login the
     * same way a bad password would if their account is suspended.
     *
     * @param  callable  $next
     * @return mixed
     *
     * @throws ValidationException
     */
    public function handle(Request $request, $next)
    {
        if ($request->user()?->isSuspended()) {
            $this->guard->logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                Fortify::username() => [trans('auth.suspended')],
            ]);
        }

        return $next($request);
    }
}
