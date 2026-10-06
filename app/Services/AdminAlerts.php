<?php

namespace App\Services;

use App\Enums\AdminAlert;
use App\Enums\UserRole;
use App\Mail\AdminAlertMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Emails administrators about what happens on the site, each about the
 * kinds they turned on in their notification settings (meant for the
 * testing period). Whoever did the thing is not told about it.
 */
class AdminAlerts
{
    /**
     * @param  array<string, mixed>  $replace  Placeholders for the subject and the lines, see the admin_alerts language files.
     */
    public static function send(AdminAlert $kind, ?User $actor, array $replace, ?string $url = null): void
    {
        $administrators = User::query()
            ->where('role', UserRole::Administrator)
            ->whereNull('suspended_at')
            ->get()
            ->filter(fn (User $administrator) => $administrator->wantsAdminEmail($kind))
            ->reject(fn (User $administrator) => $actor !== null && $administrator->is($actor));

        foreach ($administrators as $administrator) {
            $locale = $administrator->locale ?? 'en';
            $subject = '['.config('app.name').'] '.__("admin_alerts.{$kind->value}.subject", $replace, $locale);
            $lines = array_values(array_filter(array_map(
                fn (string $line) => __($line, $replace, $locale),
                __("admin_alerts.{$kind->value}.lines", [], $locale),
            ), fn (string $line) => trim($line) !== ''));

            Mail::to($administrator->email)
                ->locale($locale)
                ->queue(new AdminAlertMail($subject, $lines, $url));
        }
    }
}
