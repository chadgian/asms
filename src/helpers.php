<?php

declare(strict_types=1);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('M d, Y h:i A', strtotime($value));
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null && $message !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'approved' => 'success',
        'for_review' => 'info',
        'for_compliance' => 'warning',
        default => 'muted',
    };
}

function status_label(string $status): string
{
    return match ($status) {
        'approved' => 'Approved',
        'for_review' => 'For Review',
        'for_compliance' => 'For Compliance',
        'not_submitted' => 'Not Yet Submitted',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}
