<?php
/**
 * Flash Message System
 *
 * Handles setting and displaying of session-based messages.
 */

// Ensure session is active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sets a flash message for the next or current request.
 *
 * @param string $type The type of message (e.g., 'success', 'error', 'info').
 * @param string $message The message content.
 * @param string $mode 'next' for the next request (after redirect), 'now' for the current request.
 */
function flash(string $type, string $message, string $mode = 'next'): void
{
    $key = ($mode === 'now') ? 'flash_now' : 'flash_next';
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key][] = ['type' => $type, 'message' => $message];
}

/**
 * Displays all flash messages (both 'now' and 'next').
 * This function should be called in your layout/template where you want messages to appear.
 */
function display_flash_messages(): void
{
    // Combine messages for the current request ('now') and the next request ('next')
    $messages = array_merge(
        $_SESSION['flash_now'] ?? [],
        $_SESSION['flash_next'] ?? []
    );

    // Clear the session variables immediately after retrieving them
    unset($_SESSION['flash_now'], $_SESSION['flash_next']);

    if (empty($messages)) {
        return;
    }

    echo '<div class="flash-messages-container" style="position: fixed; top: 1rem; right: 1rem; z-index: 1080;">';

    $alert_classes = [
        'error'   => 'alert-danger',
        'success' => 'alert-success',
        'info'    => 'alert-info',
        'warning' => 'alert-warning'
    ];

    foreach ($messages as $key => $msg) {
        $type = htmlspecialchars($msg['type']);
        $message = nl2br(htmlspecialchars($msg['message']));
        $alert_class = $alert_classes[$type] ?? 'alert-secondary';

        echo "
        <div class='alert {$alert_class} alert-dismissible fade show shadow-sm' role='alert' id='flash-msg-{$key}'>
            {$message}
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
        ";
    }

    echo '</div>';
}