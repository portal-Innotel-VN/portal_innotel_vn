<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_error_sanitizer
{
    public function sanitize($rawError)
    {
        $safe = str_replace("\0", '', (string) $rawError);
        $safe = preg_replace('/^(to|cc|bcc|from|reply-to|subject|authorization):.*$/mi', '$1: [redacted]', $safe);
        $safe = preg_replace('/\bAUTH\s+(LOGIN|PLAIN|XOAUTH2)\b.*$/mi', 'AUTH $1 [redacted]', $safe);
        $safe = preg_replace('/\b(password|passwd|credential|token|secret)\s*[:=]\s*\S+/i', '$1=[redacted]', $safe);
        $safe = preg_replace('/\b[A-Za-z0-9+\/]{16,}={0,2}/', '[redacted]', $safe);
        $safe = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $safe);
        $safe = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip]', $safe);
        $safe = preg_replace('#\b(?:smtp|smtps)://[^\s]+#i', '[smtp-endpoint]', $safe);
        $safe = preg_replace('/[\r\n\t ]+/', ' ', $safe);
        $safe = trim((string) $safe);

        return substr($safe !== '' ? $safe : 'SMTP delivery failed without diagnostic details', 0, 500);
    }
}
