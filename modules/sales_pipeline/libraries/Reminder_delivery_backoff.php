<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_backoff
{
    private $randomizer;

    public function __construct(?callable $randomizer = null)
    {
        $this->randomizer = $randomizer ?: function ($min, $max) {
            return random_int($min, $max);
        };
    }

    public function nextRetryAt($attemptCount, DateTimeImmutable $now)
    {
        $schedule = [60, 300, 900, 3600, 21600];
        $index = min(count($schedule) - 1, max(0, (int) $attemptCount - 1));
        $baseSeconds = $schedule[$index];
        $jitter = (int) call_user_func($this->randomizer, 0, (int) floor($baseSeconds * 0.2));

        return $now->modify('+' . ($baseSeconds + $jitter) . ' seconds');
    }
}
