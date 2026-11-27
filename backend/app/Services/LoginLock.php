<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Stops password guessing in the driver app.
 *
 * Each round allows 4 wrong passwords. After that the app must wait, and every
 * new lock is longer: 1 minute, then 2 minutes, then 5 minutes (and 5 minutes after that).
 * A correct password clears everything. Counted per email and per phone's internet address.
 */
class LoginLock
{
    public const TRIES_PER_ROUND = 4;
    public const LOCK_SECONDS = [60, 120, 300];
    private const REMEMBER_SECONDS = 3600; // forget old wrong tries after 1 hour

    private string $key;

    public function __construct(string $email, string $ip)
    {
        $this->key = 'api-login|' . Str::lower($email) . '|' . $ip;
    }

    /** Seconds left before the next try is allowed (0 = not locked). */
    public function secondsLeft(): int
    {
        $until = Cache::get("{$this->key}|until");

        return $until ? max(0, $until - now()->timestamp) : 0;
    }

    /**
     * Counts one wrong password. Returns the tries left in this round,
     * or 0 when this wrong try started a lock.
     */
    public function failed(): int
    {
        $wrong = (int) Cache::get("{$this->key}|wrong", 0) + 1;

        if ($wrong < self::TRIES_PER_ROUND) {
            Cache::put("{$this->key}|wrong", $wrong, self::REMEMBER_SECONDS);

            return self::TRIES_PER_ROUND - $wrong;
        }

        // Round used up: lock, and make the next lock longer.
        $level = (int) Cache::get("{$this->key}|level", 0);
        $seconds = self::LOCK_SECONDS[min($level, count(self::LOCK_SECONDS) - 1)];

        Cache::put("{$this->key}|until", now()->timestamp + $seconds, $seconds);
        Cache::put("{$this->key}|level", $level + 1, self::REMEMBER_SECONDS);
        Cache::forget("{$this->key}|wrong"); // a new round starts after the wait

        return 0;
    }

    /** Correct password: start from zero again. */
    public function clear(): void
    {
        Cache::forget("{$this->key}|wrong");
        Cache::forget("{$this->key}|until");
        Cache::forget("{$this->key}|level");
    }

    /** "1 minute", "2 minutes", "45 seconds". */
    public static function words(int $seconds): string
    {
        return $seconds % 60 === 0
            ? ($seconds / 60) . ' ' . Str::plural('minute', $seconds / 60)
            : $seconds . ' ' . Str::plural('second', $seconds);
    }
}