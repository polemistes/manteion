<?php

namespace App\Enums;

enum LeafletVisibility: string
{
    case Official = 'official';
    case FriendsAndFollowers = 'friends_and_followers';
    case Friends = 'friends';
    case Private = 'private';
}
