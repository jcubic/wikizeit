<?php

define('WIKIZEIT_URL', 'https://jcubic.pl/wikizeit/');

session_start();

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Initialize Mustache
$mustache = new Mustache_Engine([
    'loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/templates', [
        'extension' => '.mustache'
    ]),
]);

// Database initialization
$db = new SQLite3('subscribers.db');
$db->exec('CREATE TABLE IF NOT EXISTS subscribers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    token TEXT NOT NULL,
    verified INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$person = json_decode(file_get_contents('./szkolenia/person.json'), true);

$person_id = 'https://jakub.jankiewicz.org';

$graph = [
    '@context' => 'https://schema.org',
    '@graph' => [
        $person,

        [
            '@type' => 'WebPage',
            '@id' => WIKIZEIT_URL . '#webpage',
            'url' => WIKIZEIT_URL,
            'isPartOf' => [ '@id' => 'https://jcubic.pl#website' ],
            'breadcrumb' => [ '@id' => WIKIZEIT_URL . '#breadcrumbs' ]
        ],

        [
            '@type' => 'EducationalOrganization',
            '@id' => WIKIZEIT_URL,
            'name' => 'WikiZEIT',
            'alternateName' => 'Projekt WikiZEIT',
            'url' => WIKIZEIT_URL,
            'logo' => WIKIZEIT_URL . 'img/logo.svg',
            'description' => 'Projekt edukacyjny poświęcony etycznemu SEO, danym strukturalnym i profesjonalnej edycji Wikipedii.',
            'founder' => ['@id' => $person_id],
            'foundingDate' => '2026-03-05',
            'knowsAbout' => [
                'Search Engine Optimization',
                'SEO',
                'GEO',
                'AIO',
                'Wikipedia',
                'Entity SEO',
                'Wikipedia',
                'Traning',
                'Teaching',
                'Consulting',
                'Wikidata',
                'Open Source'
            ],
            'sameAs' => [
                //'https://www.wikidata.org[TWOJE_Q_WIKIDATA]', // Link do elementu, który stworzysz
                'https://commons.wikimedia.org/wiki/Category:WikiZEIT',
                'https://www.youtube.com/@WikiZEIT',
                'https://github.com/WikiZEIT'
            ],
            'mainEntityOfPage' => [
                '@id' => WIKIZEIT_URL . '#webpage'
            ]
        ],
        [
            '@type' => 'WebSite',
            '@id' => 'https://jcubic.pl#website',
            'url' => 'https://jcubic.pl',
            'name' => 'jcubic.pl'
        ],

        [
            '@type' => 'BreadcrumbList',
            '@id' => WIKIZEIT_URL . '#breadcrumb',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'item' => [
                        '@id' => 'https://jcubic.pl#webpage',
                        'name' => 'Głównie JavaScript'
                    ]
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'item' => [
                        '@id' => WIKIZEIT_URL . '#webpage',
                        'name' => 'WikiZEIT'
                    ]
                ]
            ]
        ],
        [
            '@type' => 'SoftwareSourceCode',
            'name' => 'WikiZEIT Blog Source Code',
            'codeRepository' => 'https://github.com/WikiZEIT/blog',
            'programmingLanguage' => 'PHP',
            'author' => ['@id' => $person_id]
        ]
    ]
];



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

// Initialize spam protection token
if (!isset($_SESSION['spam_token'])) {
    $_SESSION['spam_token'] = generateToken();
}

$message = '';
$message_type = '';

// Handle email verification
if (isset($_GET['verify'])) {
    $token = $_GET['verify'];
    $stmt = $db->prepare('SELECT email, verified, created_at FROM subscribers WHERE token = :token');
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row && $row['verified'] == 0) {
        // Check if token has expired (30 days)
        $createdAt = strtotime($row['created_at']);
        $expirationTime = $createdAt + (30 * 24 * 60 * 60); // 30 days in seconds
        $currentTime = time();

        if ($currentTime > $expirationTime) {
            // Token expired - delete the entry
            $stmt = $db->prepare('DELETE FROM subscribers WHERE token = :token');
            $stmt->bindValue(':token', $token, SQLITE3_TEXT);
            $stmt->execute();

            $message = 'Link weryfikacyjny wygasł (ważny przez 30 dni). Proszę zapisać się ponownie.';
            $message_type = 'error';
        } else {
            // Token valid - verify email
            $email = $row['email'];
            $stmt = $db->prepare('UPDATE subscribers SET verified = 1 WHERE token = :token');
            $stmt->bindValue(':token', $token, SQLITE3_TEXT);
            $stmt->execute();

            // Send notification to owner
            $notifyMessage = $mustache->render('email-notification', [
                'email' => htmlspecialchars($email),
                'verificationDate' => date('d.m.Y H:i:s')
            ]);
            sendEmail('jcubic@jcubic.pl', 'Nowy subskrybent WikiZEIT', $notifyMessage);

            $message = 'Dziękujemy! Twój adres email został zweryfikowany. Będziesz otrzymywać aktualizacje o projekcie WikiZEIT.';
            $message_type = 'success';
        }
    } elseif ($row && $row['verified'] == 1) {
        $message = 'Ten adres email został już wcześniej zweryfikowany.';
        $message_type = 'info';
    } else {
        $message = 'Nieprawidłowy lub wygasły link weryfikacyjny.';
        $message_type = 'error';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    // Check honeypot field
    if (!empty($_POST['email_confirmation'])) {
        $message = 'Wygląda na to że nie jesteś człowiekiem!';
        $message_type = 'error';
    } else {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

        if (!$email) {
            $message = 'Proszę podać prawidłowy adres email.';
            $message_type = 'error';
        } else {
            // Check if email already exists
            $stmt = $db->prepare('SELECT verified FROM subscribers WHERE email = :email');
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $result = $stmt->execute();
            $existing = $result->fetchArray(SQLITE3_ASSOC);

            if ($existing) {
                if ($existing['verified'] == 1) {
                    $message = 'Ten adres email jest już zapisany na liście subskrybentów.';
                    $message_type = 'info';
                } else {
                    $message = 'Na ten adres email wysłano już wcześniej link weryfikacyjny. Sprawdź swoją skrzynkę.';
                    $message_type = 'info';
                }
            } else {
                // Generate token and save to database
                $token = generateToken();
                $stmt = $db->prepare('INSERT INTO subscribers (email, token) VALUES (:email, :token)');
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $stmt->bindValue(':token', $token, SQLITE3_TEXT);

                if ($stmt->execute()) {
                    // Send confirmation email
                    $verifyUrl = WIKIZEIT_URL . "?verify={$token}#subscribe";

                    $emailMessage = $mustache->render('email-confirmation', [
                        'verifyUrl' => $verifyUrl,
                        'year' => date('Y')
                    ]);

                    if (sendEmail($email, 'Potwierdź subskrypcję WikiZEIT', $emailMessage)) {
                        $message = 'Dziękujemy! Sprawdź swoją skrzynkę email i kliknij link weryfikacyjny.';
                        $message_type = 'success';
                    } else {
                        $message = 'Wystąpił błąd podczas wysyłania emaila. Spróbuj ponownie później.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Wystąpił błąd. Spróbuj ponownie później.';
                    $message_type = 'error';
                }
            }
        }
    }
}
?><html class="dark" lang="pl">
  <head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>WikiZEIT - Edukacyjny projekt o Wikipedii i SEO</title>
    <meta name="description" content="WikiZeit: Projekt edukacyjny o Wikipedii i etycznym SEO Jakuba T. Janiewicza. Poznaj mechanizmy największej encyklopedii świata i zadbaj o widoczność swojej marki.">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/wikizeit/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/wikizeit/favicon/favicon.svg" />
    <link rel="shortcut icon" href="/wikizeit/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/wikizeit/favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="WikiZEIT" />
    <link rel="manifest" href="/wikizeit/favicon/site.webmanifest" />
    <!-- Facebook Meta Tags -->
    <meta property="og:url" content="<?= WIKIZEIT_URL ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="WikiZEIT - Edukacyjny projekt o Wikipedii i SEO" />
    <meta property="og:description" content="Odkryj wpływ Wikipedii na Twoją markę. Profesjonalna wiedza o edycji i SEO od eksperta z 15-letnim doświadczeniem w Open Source. Sprawdź projekt WikiZeit!" />
    <meta property="og:image" content="<?= WIKIZEIT_URL ?>img/social-card.png" />
    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="<?= WIKIZEIT_URL ?>" />
    <meta name="twitter:title" content="WikiZEIT - Edukacyjny projekt o Wikipedii i SEO" />
    <meta name="twitter:description" content="Odkryj wpływ Wikipedii na Twoją markę. Profesjonalna wiedza o edycji i SEO od eksperta z 15-letnim doświadczeniem w Open Source. Sprawdź projekt WikiZeit!" />
    <meta name="twitter:image" content="<?= WIKIZEIT_URL ?>img/social-card.png" />
    <!-- Meta Tags Generated via https://www.opengraph.io -->
    <link rel="canonical" href="<?= WIKIZEIT_URL ?>" />
    <script type="application/ld+json">
    <?php
    echo json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
    </script>
    <script>
     (function() {
         document.documentElement.classList.add('icons-hidden');

         function icons_ready() {
             document.documentElement.classList.remove('icons-hidden');
         }

         if (document.fonts && document.fonts.ready) {
             const font = '24px "Material Symbols Outlined"';
             document.fonts.load(font).then(icons_ready);
         } else {
             icons_ready();
         }
     })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=block" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet"/>
    <style>
/* Reset and base styles */
* {
    box-sizing: border-box;
}

.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    display: inline-block;
    white-space: nowrap;
    word-wrap: normal;
    direction: ltr;
    -webkit-font-feature-settings: 'liga';
    -webkit-font-smoothing: antialiased;
    overflow: hidden;
    width: 1em;
}
.icons-hidden .material-symbols-outlined {
    visibility: hidden;
}

/* CSS Variables */
:root {
    --color-primary: #b42104;
    --color-background-light: #f8f6f5;
    --color-background-dark: #1a0e0c;
    --color-slate-50: #f8fafc;
    --color-slate-100: #f1f5f9;
    --color-slate-400: #94a3b8;
    --color-slate-500: #64748b;
    --color-slate-600: #475569;
    --color-slate-700: #334155;
    --color-slate-800: #1e293b;
    --color-slate-900: #0f172a;
    --color-white: #ffffff;
    --font-display: 'Inter', sans-serif;
    --border-radius: 0.25rem;
    --border-radius-lg: 0.5rem;
    --border-radius-xl: 0.75rem;
    --border-radius-2xl: 1rem;
    --border-radius-3xl: 1.5rem;
    --border-radius-full: 9999px;
}

/* Body and root layout */
body {
    margin: 0;
    padding: 0;
    font-family: var(--font-display);
    background-color: var(--color-background-dark);
    color: var(--color-slate-100);
    line-height: 1.5;
    min-height: max(884px, 100dvh);
}

html.dark body {
    background-color: var(--color-background-dark);
    color: var(--color-slate-100);
}

::selection {
    background-color: var(--color-primary);
    color: var(--color-white);
}

/* Navigation */
nav {
    position: sticky;
    top: 0;
    z-index: 50;
    border-bottom: 1px solid rgba(180, 33, 4, 0.2);
    background-color: rgba(26, 14, 12, 0.8);
    backdrop-filter: blur(12px);
}

.nav-container {
    max-width: 80rem;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
}

.nav-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.logo-image {
    height: 2.5rem;
    width: auto;
}

.nav-links {
    display: none;
    align-items: center;
    gap: 2rem;
}

@media (min-width: 768px) {
    .nav-links {
        display: flex;
    }
}

.nav-link {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-slate-400);
    text-decoration: none;
    transition: color 0.2s;
}

.nav-link:hover {
    color: var(--color-primary);
}

.nav-cta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: var(--border-radius-lg);
    background-color: rgba(180, 33, 4, 0.1);
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--color-primary);
    text-decoration: none;
    transition: all 0.2s;
}

.nav-cta:hover {
    background-color: var(--color-primary);
    color: var(--color-white);
}

.menu-button {
    display: block;
    background: none;
    border: none;
    color: var(--color-slate-100);
    cursor: pointer;
    font-family: var(--font-display);
}

@media (min-width: 768px) {
    .menu-button {
        display: none;
    }
}

/* Hero Section */
header {
    position: relative;
    overflow: hidden;
    padding-top: 4rem;
    padding-bottom: 6rem;
}

@media (min-width: 768px) {
    header {
        padding-top: 8rem;
        padding-bottom: 10rem;
    }
}

.hero-bg-blur-1 {
    position: absolute;
    right: -6rem;
    top: -6rem;
    width: 24rem;
    height: 24rem;
    border-radius: var(--border-radius-full);
    background-color: rgba(180, 33, 4, 0.1);
    filter: blur(100px);
}

.hero-bg-blur-2 {
    position: absolute;
    left: -6rem;
    top: 50%;
    width: 16rem;
    height: 16rem;
    border-radius: var(--border-radius-full);
    background-color: rgba(180, 33, 4, 0.05);
    filter: blur(80px);
}

.hero-grid {
    max-width: 80rem;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr;
    align-items: center;
    gap: 3rem;
    padding: 0 1.5rem;
}

@media (min-width: 1024px) {
    .hero-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.hero-content {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.hero-badge {
    display: inline-flex;
    width: fit-content;
    align-items: center;
    gap: 0.5rem;
    border-radius: var(--border-radius-full);
    border: 1px solid rgba(180, 33, 4, 0.2);
    background-color: rgba(180, 33, 4, 0.05);
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-primary);
}

.pulse-container {
    position: relative;
    display: flex;
    width: 0.5rem;
    height: 0.5rem;
}

.pulse-ring {
    position: absolute;
    display: inline-flex;
    width: 100%;
    height: 100%;
    border-radius: var(--border-radius-full);
    background-color: var(--color-primary);
    opacity: 0.75;
    animation: ping 1s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes ping {
    75%, 100% {
        transform: scale(2);
        opacity: 0;
    }
}

.pulse-dot {
    position: relative;
    display: inline-flex;
    width: 0.5rem;
    height: 0.5rem;
    border-radius: var(--border-radius-full);
    background-color: var(--color-primary);
}

.hero-title {
    font-size: 3rem;
    font-weight: 900;
    line-height: 1.1;
    letter-spacing: -0.025em;
    color: var(--color-slate-100);
    margin: 0;
}

@media (min-width: 768px) {
    .hero-title {
        font-size: 4.5rem;
    }
}

.hero-title-highlight {
    color: var(--color-primary);
}

.hero-subtitle {
    max-width: 42rem;
    font-size: 1.125rem;
    line-height: 1.75;
    color: var(--color-slate-400);
    margin: 0;
}

@media (min-width: 768px) {
    .hero-subtitle {
        font-size: 1.25rem;
    }
}

.hero-subtitle a {
    color: var(--color-primary);
    text-decoration: underline;
    text-underline-offset: 2px;
    transition: opacity 0.2s;
}

.hero-subtitle a:hover {
    opacity: 0.8;
}

.hero-buttons {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

@media (min-width: 640px) {
    .hero-buttons {
        flex-direction: row;
    }
}

.btn-primary {
    display: flex;
    height: 3.5rem;
    align-items: center;
    justify-content: center;
    border-radius: var(--border-radius-xl);
    background-color: var(--color-primary);
    padding: 0 2rem;
    font-family: var(--font-display);
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-white);
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary:hover {
    transform: scale(1.02);
    box-shadow: 0 10px 15px -3px rgba(180, 33, 4, 0.2);
}

.btn-primary:active {
    transform: scale(0.95);
}

.btn-secondary {
    display: flex;
    height: 3.5rem;
    align-items: center;
    justify-content: center;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--color-slate-700);
    background-color: transparent;
    padding: 0 2rem;
    font-family: var(--font-display);
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-slate-100);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background-color: var(--color-slate-800);
}

/* Hero Image Card */
.hero-image-container {
    position: relative;
    display: flex;
    justify-content: center;
}

@media (min-width: 1024px) {
    .hero-image-container {
        justify-content: flex-end;
    }
}

.hero-card {
    position: relative;
    aspect-ratio: 1;
    width: 100%;
    max-width: 28rem;
    overflow: hidden;
    border-radius: var(--border-radius-3xl);
    border: 1px solid rgba(180, 33, 4, 0.2);
    background: linear-gradient(to bottom right, var(--color-background-dark), rgba(180, 33, 4, 0.1));
    padding: 1px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.hero-card-inner {
    display: flex;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    border-radius: 1.4rem;
    background-color: rgba(26, 14, 12, 0.5);
    backdrop-filter: blur(24px);
}

.hero-card-icon {
    font-size: 160px;
    color: rgba(180, 33, 4, 0.4);
}

.hero-card-badge {
    position: absolute;
    bottom: 2rem;
    right: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: var(--border-radius-2xl);
    border: 1px solid var(--color-slate-700);
    background-color: rgba(26, 14, 12, 0.8);
    padding: 1rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(12px);
}

.badge-icon-container {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--border-radius-full);
    background-color: rgba(180, 33, 4, 0.2);
    padding: 0.5rem;
    color: var(--color-primary);
}

.badge-text-small {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--color-slate-400);
    margin: 0;
}

.badge-text-large {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--color-slate-100);
    margin: 0;
}

/* About Section */
.section-about {
    border-top: 1px solid rgba(180, 33, 4, 0.1);
    border-bottom: 1px solid rgba(180, 33, 4, 0.1);
    background-color: rgba(180, 33, 4, 0.05);
    padding: 6rem 0;
}

.section-content-center {
    max-width: 56rem;
    margin: 0 auto;
    padding: 0 1.5rem;
    text-align: center;
}

.section-title {
    margin-bottom: 2rem;
    font-size: 1.875rem;
    font-weight: 700;
    letter-spacing: -0.025em;
    color: var(--color-slate-100);
    margin-top: 0;
}

@media (min-width: 768px) {
    .section-title {
        font-size: 2.25rem;
    }
}

.section-text-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    font-size: 1.125rem;
    line-height: 1.75;
    color: var(--color-slate-400);
}

.section-text-container p {
    margin: 0;
}

.text-highlight {
    color: var(--color-slate-100);
    font-weight: 500;
}

/* Status Section */
.section-status {
    padding: 6rem 0;
}

.section-container {
    max-width: 80rem;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.status-card {
    position: relative;
    overflow: hidden;
    border-radius: var(--border-radius-3xl);
    border: 1px solid rgba(180, 33, 4, 0.2);
    background: linear-gradient(to right, var(--color-background-dark), rgba(180, 33, 4, 0.2));
    padding: 2rem;
}

@media (min-width: 768px) {
    .status-card {
        padding: 3rem;
    }
}

.status-content {
    position: relative;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
}

@media (min-width: 768px) {
    .status-content {
        flex-direction: row;
    }
}

.status-text-container {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.status-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.status-header .material-symbols-outlined {
    color: var(--color-primary);
}

.status-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-slate-100);
    margin: 0;
}

.status-description {
    color: var(--color-slate-400);
    margin: 0;
}

/* Email Form */
.email-form-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.status-text-container, .email-form-container {
    flex: 1;
}

.email-form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
    box-sizing: content-box;
    align-items: flex-start;
}

@media (min-width: 640px) {
    .email-form {
        flex-direction: row;
    }
}

.email-input {
    flex: 1;
    height: 3.5rem;
    padding: 0 1rem;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--color-slate-700);
    background-color: rgba(26, 14, 12, 0.5);
    color: var(--color-slate-100);
    font-size: 1rem;
    font-family: var(--font-display);
    transition: all 0.2s;
}

.email-input::placeholder {
    color: var(--color-slate-500);
}

.email-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(180, 33, 4, 0.1);
}

.email-submit {
    gap: 0.5rem;
    white-space: nowrap;
}
.email-input {
    flex: 1;
}

.email-submit .material-symbols-outlined {
    transition: transform 0.2s;
}

.email-submit:hover .material-symbols-outlined {
    transform: translateX(0.25rem);
}

.form-privacy-text {
    font-size: 0.75rem;
    color: var(--color-slate-500);
    text-align: center;
    margin: 0;
}

.form-privacy-text a {
    color: var(--color-primary);
    text-decoration: underline;
}

.form-privacy-text a:hover {
    opacity: 0.8;
}

/* Honeypot field - hidden from users but visible to bots */
.email-confirmation-field {
    position: absolute;
    left: -9999px;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

/* Form message styles */
.form-message {
    padding: 1rem;
    border-radius: var(--border-radius-lg);
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.form-message-success {
    background-color: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.form-message-error {
    background-color: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.form-message-info {
    background-color: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #93c5fd;
}

.status-bg-decoration {
    position: absolute;
    right: -5rem;
    top: -5rem;
    opacity: 0.1;
}

.status-bg-decoration .material-symbols-outlined {
    font-size: 200px;
    color: var(--color-white);
}

/* Footer */
footer {
    border-top: 1px solid rgba(180, 33, 4, 0.1);
    background-color: var(--color-background-dark);
    padding: 4rem 0;
}

.footer-container {
    max-width: 80rem;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 3rem;
}

@media (min-width: 768px) {
    .footer-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.footer-brand {
    grid-column: span 1;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

@media (min-width: 768px) {
    .footer-brand {
        grid-column: span 2;
    }
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.footer-logo-image {
    height: 2rem;
    width: auto;
}

.footer-description {
    max-width: 20rem;
    font-size: 0.875rem;
    line-height: 1.75;
    color: var(--color-slate-500);
    margin: 0;
}

.footer-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.footer-section-title {
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-slate-100);
    margin: 0;
}

.footer-links-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.footer-links-list li {
    font-size: 0.875rem;
    color: var(--color-slate-500);
}

.footer-links-list a {
    color: var(--color-slate-500);
    text-decoration: none;
    transition: color 0.2s;
}

.footer-links-list a:hover {
    color: var(--color-primary);
}

.footer-contact-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.footer-contact-item .material-symbols-outlined {
    font-size: 0.75rem;
}

.footer-bottom {
    margin-top: 4rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    border-top: 1px solid rgba(180, 33, 4, 0.05);
    padding-top: 2rem;
}

@media (min-width: 768px) {
    .footer-bottom {
        flex-direction: row;
    }
}

.footer-copyright {
    font-size: 0.75rem;
    color: var(--color-slate-600);
}

.footer-copyright a {
    color: inherit;
    text-decoration: underline;
}

.footer-copyright a:hover {
    color: var(--color-primary);
}

.footer-social {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.footer-social a {
    color: var(--color-slate-600);
    transition: color 0.2s;
}

.footer-social a:hover {
    color: var(--color-primary);
}

.footer-social .material-symbols-outlined {
    font-size: 1.25rem;
}

/* Mobile Navigation */
.mobile-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 50;
    display: flex;
}

@media (min-width: 768px) {
    .mobile-nav {
        display: none;
    }
}

.mobile-nav-container {
    display: flex;
    height: 4rem;
    width: 100%;
    align-items: center;
    justify-content: space-around;
    border-top: 1px solid rgba(180, 33, 4, 0.2);
    background-color: rgba(26, 14, 12, 0.9);
    padding: 0 1rem;
    backdrop-filter: blur(16px);
}

.mobile-nav-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    color: var(--color-slate-500);
    text-decoration: none;
}

.mobile-nav-link.active {
    color: var(--color-primary);
}

.mobile-nav-text {
    font-size: 10px;
    font-weight: 500;
}

.mobile-nav-link.active .mobile-nav-text {
    font-weight: 700;
}
    </style>
  </head>
  <body>
    <!-- Main Navigation -->
    <nav>
      <div class="nav-container">
        <div class="nav-logo">
          <a href="/wikizeit"><img src="/wikizeit/img/logo.svg" alt="WikiZEIT logo" class="logo-image" /></a>
        </div>
        <div class="nav-links">
          <!--
          <a class="nav-link" href="#">O projekcie</a>
          <a class="nav-link" href="#">Edukacja</a>
          <a class="nav-link" href="#">SEO</a>
          -->
          <a class="nav-link" href="/wikizeit/szkolenia/">Szkolenia i Konsultacje</a>
          <a class="nav-cta" href="#">
            Kontakt
          </a>
        </div>
        <button class="menu-button">
          <span class="material-symbols-outlined">menu</span>
        </button>
      </div>
    </nav>
    <!-- Hero Section -->
    <header>
      <div class="hero-bg-blur-1"></div>
      <div class="hero-bg-blur-2"></div>
      <div class="hero-grid">
        <div class="hero-content">
          <div class="hero-badge">
            <span class="pulse-container">
              <span class="pulse-ring"></span>
              <span class="pulse-dot"></span>
            </span>
            Projekt w trakcie wdrażania
          </div>
          <h1 class="hero-title">
            WikiZEIT – Nadchodzi nowa era <span class="hero-title-highlight">edukacji</span> o Wikipedii
          </h1>
          <p class="hero-subtitle">
            Edukacyjny projekt o Wikipedii i etycznym SEO autorstwa <a href="https://jakub.jankiewicz.org/pl/">Jakuba T. Janiewicza</a>. Odkryj mechanizmy największej encyklopedii świata i jej wpływu na twoją markę.
          </p>
          <div class="hero-buttons">
            <button class="btn-primary">
              Dowiedz się więcej
            </button>
            <button class="btn-secondary">
              Dokumentacja
            </button>
          </div>
        </div>
        <div class="hero-image-container">
          <div class="hero-card">
            <div class="hero-card-inner">
              <span class="material-symbols-outlined hero-card-icon">auto_stories</span>
            </div>
            <!-- Decorative UI elements -->
            <div class="hero-card-badge">
              <div class="badge-icon-container">
                <span class="material-symbols-outlined">verified</span>
              </div>
              <div>
                <p class="badge-text-small">Status</p>
                <p class="badge-text-large">Weryfikacja wiedzy</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>
    <!-- About Section -->
    <section class="section-about">
      <div class="section-content-center">
        <h2 class="section-title">O projekcie</h2>
        <div class="section-text-container">
          <p>
            Projekt WikiZEIT wyrasta z ducha <span class="text-highlight">Open Source</span> oraz wieloletniego doświadczenia w ekosystemie Wikipedii. Skupiamy się na dostarczaniu rzetelnej wiedzy o mechanizmach wolnej encyklopedii oraz promowaniu etycznych praktyk w pozycjonowaniu stron.
          </p>
          <p>
            Naszą misją jest demistyfikacja procesów redakcyjnych i technicznych, które stoją za najbardziej zaufanym źródłem informacji w internecie, przy jednoczesnym zachowaniu najwyższych standardów SEO.
          </p>
        </div>
      </div>
    </section>
    <!-- Status & CTA Section -->
    <section class="section-status" id="subscribe">
      <div class="section-container">
        <div class="status-card">
          <div class="status-content">
            <div class="status-text-container">
              <div class="status-header">
                <span class="material-symbols-outlined">construction</span>
                <h3 class="status-title">Projekt w trakcie wdrażania</h3>
              </div>
              <p class="status-description">Dołącz do naszej społeczności i bądź na bieżąco z każdą aktualizacją.</p>
            </div>
            <div class="email-form-container">
              <?php if ($message): ?>
              <div class="form-message form-message-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
              </div>
              <?php endif; ?>
              <form class="email-form" action="#subscribe" method="POST">
                <input
                  type="email"
                  name="email_confirmation"
                  class="email-confirmation-field"
                  placeholder="Email confirmation"
                  tabindex="-1"
                  autocomplete="off"
                  aria-hidden="true"
                />
                <input
                  type="email"
                  name="email"
                  class="email-input"
                  placeholder="Twój adres email"
                  required
                  aria-label="Adres email"
                />
                <button type="submit" class="btn-primary email-submit">
                  Śledź postępy
                  <span class="material-symbols-outlined">arrow_forward</span>
                </button>
              </form>
              <!--
              <p class="form-privacy-text">
                Zapisując się, akceptujesz naszą <a href="#">politykę prywatności</a>
              </p>
               -->
            </div>
          </div>
          <!-- Decorative bg -->
          <div class="status-bg-decoration">
            <span class="material-symbols-outlined">code</span>
          </div>
        </div>
      </div>
    </section>
    <!-- Footer -->
    <footer>
      <div class="footer-container">
        <div class="footer-grid">
          <div class="footer-brand">
            <div class="footer-logo">
              <a href="/wikizeit/"><img src="/wikizeit/img/logo.svg" alt="WikiZEIT logo" class="footer-logo-image" /></a>
            </div>
            <p class="footer-description">
              Edukacyjny projekt dla specjalistów od etycznego SEO w Wikipedii.
            </p>
          </div>
          <div class="footer-section">
            <h4 class="footer-section-title">Nawigacja</h4>
            <ul class="footer-links-list">
              <!--
              <li><a href="#">Start</a></li>
              <li><a href="#">O projekcie</a></li>
              <li><a href="#">Blog</a></li>
              <li><a href="#">Zasoby</a></li>
               -->
              <li><a href="/">Głównie JavaScript</a></li>
            </ul>
          </div>
          <div class="footer-section">
            <h4 class="footer-section-title">Kontakt</h4>
            <ul class="footer-links-list">
              <li class="footer-contact-item">
                <span class="material-symbols-outlined">mail</span>
                wikizeit [@] jcubic [.] pl
              </li>
              <li class="footer-contact-item">
                <span class="material-symbols-outlined">person</span>
                <a href="https://jakub.jankiewicz.org/pl/">Jakub T. Janiewicz</a>
              </li>
            </ul>
          </div>
        </div>
        <div class="footer-bottom">
          <p class="footer-copyright">
            © 2024 WikiZEIT. Treść dostępna na licencji <a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY-SA 4.0</a>.
          </p>
          <div class="footer-social">
            <!--
            <a href="#">
              <span class="material-symbols-outlined">hub</span>
            </a>
            -->
            <a href="https://github.com/WikiZEIT/blog">
              <span class="material-symbols-outlined">link</span>
            </a>
          </div>
        </div>
      </div>
    </footer>
    <!-- Mobile Nav Placeholder for platform specific feel -->
    <div class="mobile-nav">
      <div class="mobile-nav-container">
        <a class="mobile-nav-link active" href="#">
          <span class="material-symbols-outlined">home</span>
          <span class="mobile-nav-text">Start</span>
        </a>
        <a class="mobile-nav-link" href="#">
          <span class="material-symbols-outlined">query_stats</span>
          <span class="mobile-nav-text">Status</span>
        </a>
        <a class="mobile-nav-link" href="#">
          <span class="material-symbols-outlined">mail</span>
          <span class="mobile-nav-text">Kontakt</span>
        </a>
      </div>
    </div>
  </body>
</html>
