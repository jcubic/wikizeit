<?php

define('WIKIZEIT_URL', 'https://jcubic.pl/wikizeit/');
define('WIKIZEIT_PATH', '/wikizeit/');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize Mustache
$mustache = new Mustache_Engine([
    'loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/../templates', [
        'extension' => '.mustache'
    ]),
]);

// Database initialization
$db = new SQLite3(__DIR__ . '/../subscribers.db');
$db->exec('CREATE TABLE IF NOT EXISTS subscribers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    token TEXT NOT NULL,
    verified INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Generate random token
function generateToken() {
    return bin2hex(random_bytes(32));
}

// Send email function
function sendEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: WikiZEIT <wikizeit@jcubic.pl>\r\n";
    return mail($to, $subject, $message, $headers);
}

// Build redirect URL with message parameter
function buildRedirectUrl($redirectUrl, $msg, $anchor = 'subscribe') {
    // Default to home page
    if (empty($redirectUrl)) {
        $redirectUrl = WIKIZEIT_PATH;
    }

    // Add query parameter
    $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
    return $redirectUrl . $separator . 'msg=' . urlencode($msg) . '#' . $anchor;
}
