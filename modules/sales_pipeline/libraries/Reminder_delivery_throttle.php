<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_throttle
{
    private $clock;
    private $sleeper;

    public function __construct(?callable $clock = null, ?callable $sleeper = null)
    {
        $this->clock = $clock ?: function () {
            return microtime(true);
        };
        $this->sleeper = $sleeper ?: function ($microseconds) {
            usleep((int) $microseconds);
        };
    }

    public function waitUntilAllowed($lastAttemptStartedAt, $minIntervalMs)
    {
        $now = (float) call_user_func($this->clock);
        if ($lastAttemptStartedAt === null) {
            return $now;
        }

        $minimumSeconds = max(0, (int) $minIntervalMs) / 1000;
        $elapsedSeconds = max(0, $now - (float) $lastAttemptStartedAt);
        $remainingMicroseconds = (int) ceil(max(0, $minimumSeconds - $elapsedSeconds) * 1000000);
        if ($remainingMicroseconds > 0) {
            call_user_func($this->sleeper, $remainingMicroseconds);
        }

        return (float) call_user_func($this->clock);
    }
}
