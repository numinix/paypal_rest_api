<?php
/**
 * Ensure a session is available for the login-check AJAX endpoint.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// eof
