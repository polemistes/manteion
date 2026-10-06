<?php

namespace App\Http\Controllers;

use App\Enums\AdminAlert;
use App\Services\AdminAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * The profile page became the settings pages; old links to it land on
     * their hub.
     */
    public function edit(): RedirectResponse
    {
        return redirect()->route('settings.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        foreach ($user->events as $event) {
            foreach ($event->images as $image) {
                Storage::disk('public')->delete($image->path);
            }
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        Auth::guard('web')->logout();

        $user->delete();

        AdminAlerts::send(AdminAlert::AccountDeleted, $user, [
            'name' => $user->name,
            'username' => $user->username,
            'by' => __('admin_alerts.deleted_by_self'),
        ], route('admin.users.index'));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
