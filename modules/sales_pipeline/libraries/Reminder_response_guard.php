<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_response_guard
{
    public function check(array $reminder)
    {
        if (isset($reminder['response_required']) && (int) $reminder['response_required'] !== 1) {
            return 'response_not_required';
        }
        if (empty($reminder['sent_at']) || empty($reminder['response_due_at'])) {
            return 'delivery_pending';
        }
        if (($reminder['staff_response'] ?? null) !== null) {
            return 'already_responded';
        }

        return 'allowed';
    }
}
