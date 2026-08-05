<?php
require_once 'session.php';

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: /humsafar-food-delivery-main/login.php");
        exit();
    }
}