<?php

namespace App\Enums;

/**
 * A spokesperson's standing with an organizer: the main spokesperson
 * invites and removes the others and may hand the role on.
 */
enum SpokespersonRole: string
{
    case Main = 'main';
    case Spokesperson = 'spokesperson';
}
