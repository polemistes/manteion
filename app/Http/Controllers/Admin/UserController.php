<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminEmailRequest;
use App\Http\Requests\StoreSuspensionRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Mail\AdminNotice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_suspended' => $user->isSuspended(),
                'created_at' => $user->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'q' => $search,
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $user->update(['role' => UserRole::from($request->validated('role'))]);

        return back();
    }

    public function suspend(StoreSuspensionRequest $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422);

        $user->update([
            'suspended_at' => now(),
            'suspended_reason' => $request->validated('reason'),
            'suspended_by' => $request->user()->id,
        ]);

        return back();
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $user->update([
            'suspended_at' => null,
            'suspended_reason' => null,
            'suspended_by' => null,
        ]);

        return back();
    }

    public function sendEmail(StoreAdminEmailRequest $request, User $user): RedirectResponse
    {
        Mail::to($user->email)->queue(new AdminNotice(
            $request->validated('subject'),
            $request->validated('body'),
        ));

        return back();
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422);

        $request->session()->put('impersonator_id', $request->user()->id);

        Auth::guard('web')->loginUsingId($user->id);

        return redirect()->route('events.index');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        if ($impersonatorId !== null) {
            Auth::guard('web')->loginUsingId($impersonatorId);
        }

        return redirect()->route('admin.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422);

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        foreach ($user->events as $event) {
            foreach ($event->images as $image) {
                Storage::disk('public')->delete($image->path);
            }
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->delete();

        return redirect()->route('admin.users.index');
    }
}
