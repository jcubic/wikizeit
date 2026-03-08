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

// Check if mail() can actually send (sendmail binary exists)
function isMailAvailable() {
    $sendmailPath = ini_get('sendmail_path');
    if (empty($sendmailPath)) {
        return false;
    }
    $binary = explode(' ', $sendmailPath)[0];
    return file_exists($binary);
}

// Mock mail: save email to api/mail/index.html for local testing
function mockMail($to, $subject, $message, $extraHeaders = '') {
    $mailDir = __DIR__ . '/../mail';
    if (!is_dir($mailDir)) {
        mkdir($mailDir, 0777, true);
    }
    $bodyContent = (strpos($extraHeaders, 'text/plain') !== false)
        ? '<pre>' . htmlspecialchars($message) . '</pre>'
        : $message;
    $wrapper = '<!DOCTYPE html><html><head><meta charset="utf-8">'
        . '<title>' . htmlspecialchars($subject) . '</title>'
        . '<style>body{font-family:sans-serif;margin:2rem;background:#f5f5f5}'
        . '.meta{background:#fff;padding:1rem;border:1px solid #ddd;margin-bottom:1rem;border-radius:4px}'
        . '.meta strong{display:inline-block;width:80px}'
        . '.email-body{background:#fff;padding:1rem;border:1px solid #ddd;border-radius:4px}'
        . '</style></head><body>'
        . '<div class="meta">'
        . '<p><strong>To:</strong> ' . htmlspecialchars($to) . '</p>'
        . '<p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>'
        . '<p><strong>Date:</strong> ' . date('Y-m-d H:i:s') . '</p>'
        . '<p><strong>Headers:</strong> ' . htmlspecialchars($extraHeaders) . '</p>'
        . '</div>'
        . '<div class="email-body">' . $bodyContent . '</div>'
        . '</body></html>';
    file_put_contents($mailDir . '/index.html', $wrapper);
    return true;
}

// Send HTML email — falls back to file mock when mail is unavailable
function sendEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: WikiZEIT <wikizeit@jcubic.pl>\r\n";
    if (!isMailAvailable()) {
        return mockMail($to, $subject, $message, $headers);
    }
    return mail($to, $subject, $message, $headers);
}

// Send plain text email with Reply-To — for contact form
function sendPlainEmail($to, $subject, $message, $replyTo = '') {
    $headers = "From: jcubic@jcubic.pl\r\n";
    if (!empty($replyTo)) {
        $headers .= "Reply-To: " . $replyTo . "\r\n";
    }
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if (!isMailAvailable()) {
        return mockMail($to, $subject, $message, $headers);
    }
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
    return $redirectUrl . $separator . 'msg=' . urlencode($msg) . (empty($anchor) ? '' : '#' . $anchor);
}
