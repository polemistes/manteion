<?php

namespace App\Enums;

enum EventVisibility: string
{
    case Private = 'private';
    case Public = 'public';
    case Searchable = 'searchable';
}
