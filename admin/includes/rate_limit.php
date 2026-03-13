<?php
/**
 * Simple file-based rate limiting for login attempts.
 * Limits failed attempts per IP; locks out for LOCKOUT_MINUTES after MAX_ATTEMPTS.
 */
define('RATE_LIMIT_FILE', sys_get_temp_dir() . '/mechanics_tracer_login_attempts.json');
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_LOCKOUT_MINUTES', 15);

function rate_limit_check(): ?string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $data = [];

    if (file_exists(RATE_LIMIT_FILE)) {
        $raw = @file_get_contents(RATE_LIMIT_FILE);
        if ($raw) {
            $data = json_decode($raw, true) ?: [];
        }
    }

    $key = preg_replace('/[^a-f0-9.:]/', '', $ip);
    $entry = $data[$key] ?? ['attempts' => 0, 'locked_until' => 0];

    if ($entry['locked_until'] > $now) {
        $mins = (int)ceil(($entry['locked_until'] - $now) / 60);
        return "Too many failed attempts. Try again in {$mins} minutes.";
    }

    return null;
}

function rate_limit_record_failure(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $data = [];

    if (file_exists(RATE_LIMIT_FILE)) {
        $raw = @file_get_contents(RATE_LIMIT_FILE);
        if ($raw) {
            $data = json_decode($raw, true) ?: [];
        }
    }

    $key = preg_replace('/[^a-f0-9.:]/', '', $ip);
    $entry = $data[$key] ?? ['attempts' => 0, 'locked_until' => 0];

    if ($entry['locked_until'] > $now) {
        return; // already locked
    }

    $entry['attempts']++;
    if ($entry['attempts'] >= RATE_LIMIT_MAX_ATTEMPTS) {
        $entry['locked_until'] = $now + (RATE_LIMIT_LOCKOUT_MINUTES * 60);
        $entry['attempts'] = 0;
    }
    $data[$key] = $entry;

    @file_put_contents(RATE_LIMIT_FILE, json_encode($data), LOCK_EX);
}

function rate_limit_clear_success(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $data = [];

    if (file_exists(RATE_LIMIT_FILE)) {
        $raw = @file_get_contents(RATE_LIMIT_FILE);
        if ($raw) {
            $data = json_decode($raw, true) ?: [];
        }
    }

    $key = preg_replace('/[^a-f0-9.:]/', '', $ip);
    unset($data[$key]);
    @file_put_contents(RATE_LIMIT_FILE, json_encode($data), LOCK_EX);
}
