<?php
// Global Time Zone
date_default_timezone_set('Asia/Colombo');

// Load headers and routing logic
require_once 'includes/headers.php';
require_once 'includes/router.php';

// Parse URL and method
$url    = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';
$method = $_SERVER['REQUEST_METHOD'];

// Handle the request
handleRequest($url, $method);
