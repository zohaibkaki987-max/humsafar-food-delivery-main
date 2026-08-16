<?php
/**
 * Shared security helpers for forms and output.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function regenerate_session_id(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
?>