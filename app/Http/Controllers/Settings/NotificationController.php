<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\DeviceLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * The push switch of every category, the message-preview choice, and
     * the devices that get the pushes (one per push subscription).
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Notifications', [
            'notificationSettings' => $user->notificationSettings(),
            'categories' => array_map(fn (NotificationCategory $category) => [
                'value' => $category->value,
                'push_default' => $category->pushDefault(),
            ], NotificationCategory::cases()),
            'devices' => PushSubscription::query()->whereMorphedTo('subscribable', $user)->latest('id')->get()
                ->map(fn (PushSubscription $subscription) => [
                    'id' => $subscription->id,
                    'label' => DeviceLabel::for($subscription->user_agent),
                    'created_at' => $subscription->created_at->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    /**
     * Merge the changed settings into the stored ones. Only explicit choices
     * are stored, so a category the user never touched keeps following its
     * default. The row is re-read under a lock because toggles save one at
     * a time, and two in quick succession must not undo each other.
     */
    public function update(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $changes = array_filter($request->validated(), fn (mixed $value) => $value !== []);

        if ($changes !== []) {
            DB::transaction(function () use ($request, $changes): void {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

                $user->update(['notification_settings' => self::merged($user->notification_settings, $changes)]);
            });
        }

        return back();
    }

    /**
     * @param  array{push?: array<string, bool>, message_previews?: bool}  $changes
     * @return array<string, mixed>
     */
    private static function merged(mixed $stored, array $changes): array
    {
        $settings = is_array($stored) ? $stored : [];

        if (isset($changes['push'])) {
            $storedPush = is_array($settings['push'] ?? null) ? $settings['push'] : [];
            $settings['push'] = [...$storedPush, ...$changes['push']];
        }

        if (array_key_exists('message_previews', $changes)) {
            $settings['message_previews'] = $changes['message_previews'];
        }

        return $settings;
    }
}
