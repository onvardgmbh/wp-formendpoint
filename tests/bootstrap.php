<?php

require_once 'vendor/autoload.php';

if (file_exists('.env')) {
    $dotenv = Dotenv\Dotenv::create(__DIR__.'/../');
    $dotenv->load();
}

require_once $_SERVER['WP_DIR'].'/wp-load.php';
