<?php

/* Humsafar operates on Pakistan Standard Time (UTC+05:00). */
date_default_timezone_set('Asia/Karachi');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
