<?php

namespace App\Http\Middleware;

use App\Models\ConversationParticipant;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'unreadNotificationsCount' => $user?->unreadNotifications()->count() ?? 0,
            'unreadMessagesCount' => $user === null ? 0 : $this->unreadMessagesCount($user),
            'pendingContactRequestsCount' => $user === null ? 0 : $this->pendingContactRequestsCount($user),
            'locale' => app()->getLocale(),
            'availableLocales' => config('app.supported_locales'),
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'impersonating' => $request->session()->has('impersonator_id'),
        ];
    }

    private function unreadMessagesCount(User $user): int
    {
        return ConversationParticipant::query()
            ->where('conversation_participants.user_id', $user->id)
            ->join('messages', 'messages.conversation_id', '=', 'conversation_participants.conversation_id')
            ->where('messages.user_id', '!=', $user->id)
            ->where(function ($query) {
                $query->whereNull('conversation_participants.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_participants.last_read_at');
            })
            ->distinct('conversation_participants.conversation_id')
            ->count('conversation_participants.conversation_id');
    }

    private function pendingContactRequestsCount(User $user): int
    {
        $pendingFollowerRequests = Follow::query()
            ->where('followed_id', $user->id)
            ->pending()
            ->count();

        return $user->pendingFriendRequests()->count() + $pendingFollowerRequests;
    }
}
