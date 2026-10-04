<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class EventCalendarFileController extends Controller
{
    /**
     * The event as an iCalendar file ("Add to calendar"), for whoever may
     * see it. Times are in UTC; calendar apps show them in local time.
     */
    public function __invoke(Event $event): Response
    {
        Gate::authorize('view', $event);

        $ends = $event->ends_at ?? $event->starts_at->addHours(2);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Manteion//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$this->timestamp(now()),
            'DTSTART:'.$this->timestamp($event->starts_at),
            'DTEND:'.$this->timestamp($ends),
            'SUMMARY:'.$this->text($event->name),
            'DESCRIPTION:'.$this->text($event->description),
            ...($event->place !== null ? ['LOCATION:'.$this->text($event->place)] : []),
            ...($event->latitude !== null && $event->longitude !== null
                ? ['GEO:'.$event->latitude.';'.$event->longitude]
                : []),
            'URL:'.route('events.show', $event),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        $body = implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
        $filename = (Str::slug($event->name) ?: 'event').'.ics';

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function timestamp(CarbonInterface $moment): string
    {
        return $moment->utc()->format('Ymd\THis\Z');
    }

    /** Escapes text as RFC 5545 asks: backslash, semicolon, comma, newline. */
    private function text(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $value,
        );
    }

    /** Folds a content line at 75 octets, without splitting a UTF-8 character. */
    private function fold(string $line): string
    {
        $folded = '';
        $current = '';

        foreach (mb_str_split($line) as $character) {
            if (strlen($current.$character) > 75) {
                $folded .= $current."\r\n ";
                $current = '';
            }

            $current .= $character;
        }

        return $folded.$current;
    }
}
