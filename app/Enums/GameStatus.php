<?php

namespace App\Enums;

enum GameStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Final = 'final';

    /**
     * Map ESPN's `status.type.state` value (pre / in / post) to a game status.
     */
    public static function fromEspnState(string $state): self
    {
        return match ($state) {
            'in' => self::InProgress,
            'post' => self::Final,
            default => self::Scheduled,
        };
    }
}
