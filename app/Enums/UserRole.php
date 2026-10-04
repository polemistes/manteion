<?php

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Moderator = 'moderator';
    case Administrator = 'administrator';

    /**
     * Whether someone with this role may act on someone with the other one
     * (suspend them, lift a suspension): only on a lower role, so a
     * moderator can't lock out an administrator.
     */
    public function outranks(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Member => 0,
            self::Moderator => 1,
            self::Administrator => 2,
        };
    }
}
