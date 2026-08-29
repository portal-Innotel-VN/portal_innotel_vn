<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_policy
{
    public function expiresAt(DateTimeImmutable $createdAt, $maxValidAgeHours)
    {
        $hours = max(1, (int) $maxValidAgeHours);
        return $createdAt->modify('+' . $hours . ' hours');
    }

    public function isExpired(DateTimeImmutable $expiresAt, DateTimeImmutable $now)
    {
        return $expiresAt <= $now;
    }
}
