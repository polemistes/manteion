<?php

namespace App\Enums;

/**
 * Where an event's own time zone comes from: the place on the map (or,
 * without one, the host's own zone), a zone the host chose by hand, or
 * none at all, for an event that happens nowhere in particular (an online
 * one), which then shows no local time.
 */
enum EventTimeZoneMode: string
{
    case Automatic = 'auto';
    case Chosen = 'chosen';
    case None = 'none';
}
