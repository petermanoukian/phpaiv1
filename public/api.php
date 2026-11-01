<?php
// public/api.php

// Define Application Root Directory
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}


// Autoload Composer Dependencies & Classes
require_once APP_ROOT . '/vendor/autoload.php';

// Delegate execution safely to your routes file
require_once APP_ROOT . '/routes/api.php';