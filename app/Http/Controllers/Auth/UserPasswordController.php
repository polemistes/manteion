<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserPasswordController extends Controller
{
    /**
     * Update the authenticated user's own password (profile settings) —
     * replaces Fortify's stock PasswordController, which unconditionally
     * deletes a token from the (no longer existing) password_reset_tokens
     * table after a successful update.
     */
    public function update(Request $request, UpdateUserPassword $updater): RedirectResponse
    {
        $updater->update($request->user(), $request->all());

        PasswordResetRequest::query()->where('user_id', $request->user()->id)->delete();

        return back();
    }
}
