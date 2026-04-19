<?php
/**
 * PRESTIGE DRIVE — api/send.php
 * Traitement du formulaire de réservation via SMTP Hostinger
 */

// ─── ERROR LOG ───────────────────────────────────────────
$logFile = __DIR__ . '/../tmp/smtp_debug.log';
function smtpLog(string $msg): void {
    global $logFile;
    $dir = dirname($logFile);
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    @file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND | LOCK_EX);
}

// ─── CONFIGURATION SMTP ──────────────────────────────────
// Load .env file (located OUTSIDE public_html for security)
$envPaths = [
    __DIR__ . '/../../.env',
    $_SERVER['DOCUMENT_ROOT'] . '/../.env',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/.env',
];
$envFile = null;
foreach ($envPaths as $path) {
    if (file_exists($path)) { $envFile = $path; break; }
}
if ($envFile) {
    smtpLog('ENV loaded from: ' . realpath($envFile));
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
} else {
    smtpLog('WARNING: .env NOT FOUND. Tried: ' . implode(', ', $envPaths));
}

define('SMTP_HOST',     $_ENV['SMTP_HOST'] ?? 'smtp.hostinger.com');
define('SMTP_PORT',     (int)($_ENV['SMTP_PORT'] ?? 465));
define('SMTP_USER',     $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS',     $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM',     $_ENV['SMTP_FROM'] ?? '');
define('SMTP_FROM_NAME',$_ENV['SMTP_FROM_NAME'] ?? 'Prestige Drive');
define('RECIPIENT',     $_ENV['RECIPIENT'] ?? '');
define('SITE_NAME',     'Prestige Drive');

define('RATE_LIMIT_MAX',  10);
define('RATE_LIMIT_FILE', __DIR__ . '/../tmp/rate_limit.json');

// ─── HEADERS ─────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// ─── HONEYPOT ────────────────────────────────────────────
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Votre demande a été envoyée avec succès.']);
    exit;
}

// ─── RATE LIMITING ───────────────────────────────────────
function checkRateLimit(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $window = 3600;
    $dir = dirname(RATE_LIMIT_FILE);
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $data = [];
    if (file_exists(RATE_LIMIT_FILE)) {
        $data = json_decode(file_get_contents(RATE_LIMIT_FILE), true) ?: [];
    }
    foreach ($data as $key => $entries) {
        $data[$key] = array_filter($entries, fn($ts) => ($now - $ts) < $window);
        if (empty($data[$key])) unset($data[$key]);
    }
    if (count($data[$ip] ?? []) >= RATE_LIMIT_MAX) return false;
    $data[$ip][] = $now;
    @file_put_contents(RATE_LIMIT_FILE, json_encode($data), LOCK_EX);
    return true;
}

if (!checkRateLimit()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Trop de demandes. Réessayez dans une heure.']);
    exit;
}

// ─── VALIDATION ──────────────────────────────────────────
function clean(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

$errors = [];
$name        = clean($_POST['name'] ?? '');
$phone       = clean($_POST['phone'] ?? '');
$email       = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$service     = clean($_POST['service'] ?? '');
$datetime    = clean($_POST['datetime'] ?? '');
$pickup      = clean($_POST['pickup'] ?? '');
$destination = clean($_POST['destination'] ?? '');
$message     = clean($_POST['message'] ?? '');

if (empty($name) || mb_strlen($name) < 2) $errors[] = 'Le nom est obligatoire (2 caractères min).';
if (empty($phone) || strlen(preg_replace('/[\s\-\.()]+/', '', $phone)) < 10) $errors[] = 'Numéro de téléphone invalide.';
if (!$email) $errors[] = 'Adresse email invalide.';
if (empty($service)) $errors[] = 'Veuillez sélectionner un type de prestation.';
if (empty($datetime)) $errors[] = 'Veuillez indiquer la date et l\'heure.';
if (empty($pickup)) $errors[] = 'Veuillez indiquer le lieu de prise en charge.';
if (mb_strlen($message) > 2000) $errors[] = 'Le message est trop long (2000 car. max).';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ─── ENVOI SMTP ──────────────────────────────────────────
function smtpSend(string $to, string $subject, string $body, string $replyTo = ''): bool {
    smtpLog("--- Début envoi vers: $to ---");
    smtpLog('SMTP_HOST=' . SMTP_HOST . ' PORT=' . SMTP_PORT . ' USER=' . SMTP_USER . ' FROM=' . SMTP_FROM);

    if (empty(SMTP_USER) || empty(SMTP_PASS)) {
        smtpLog('ERREUR: SMTP_USER ou SMTP_PASS vide — .env non chargé ?');
        return false;
    }

    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
    ]);
    $sock = @stream_socket_client(
        'ssl://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$sock) {
        smtpLog("ERREUR connexion: [$errno] $errstr");
        return false;
    }
    smtpLog('Connexion établie');

    $read = function() use ($sock) {
        $r = ''; 
        while ($line = fgets($sock, 512)) {
            $r .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $r;
    };
    $send = function(string $cmd, string $label = '') use ($sock, $read) {
        fwrite($sock, $cmd . "\r\n");
        $resp = $read();
        if ($label) smtpLog("$label => " . trim($resp));
        return $resp;
    };

    $read(); // greeting
    $send('EHLO prestigedrive-paris.fr', 'EHLO');
    $send('AUTH LOGIN', 'AUTH');
    $send(base64_encode(SMTP_USER), 'USER');
    $resp = $send(base64_encode(SMTP_PASS), 'PASS');
    if (strpos($resp, '235') === false) {
        smtpLog('ERREUR AUTH: réponse = ' . trim($resp));
        fclose($sock);
        return false;
    }

    $send('MAIL FROM:<' . SMTP_FROM . '>', 'MAIL FROM');
    $send('RCPT TO:<' . $to . '>', 'RCPT TO');
    $send('DATA', 'DATA');

    $headers  = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    if ($replyTo) $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $body = str_replace("\n.", "\n..", $body);
    fwrite($sock, $headers . "\r\n" . $body . "\r\n.\r\n");
    $resp = $read();
    smtpLog('DATA response => ' . trim($resp));
    $send('QUIT', 'QUIT');
    fclose($sock);

    return strpos($resp, '250') !== false;
}

// ─── CONSTRUCTION EMAIL ──────────────────────────────────
$subject = "Nouvelle reservation - {$service} - {$name}";
$body = "
====================================
  NOUVELLE DEMANDE DE RESERVATION
  " . SITE_NAME . "
====================================

Client : {$name}
Telephone : {$phone}
Email : {$email}

Prestation : {$service}
Date / Heure : {$datetime}
Prise en charge : {$pickup}
Destination : {$destination}

Message :
{$message}

====================================
IP : {$_SERVER['REMOTE_ADDR']}
Date : " . date('d/m/Y H:i:s') . "
====================================
";

$sent = smtpSend(RECIPIENT, $subject, $body, "{$name} <{$email}>");

if ($sent) {
    // Confirmation au client
    $clientBody = "Bonjour {$name},

Nous avons bien recu votre demande de reservation :
- Prestation : {$service}
- Date : {$datetime}
- Lieu : {$pickup}

Nous vous contacterons sous 30 minutes pour confirmer votre trajet.

Cordialement,
L'equipe Prestige Drive
Tel : 07 67 50 01 01
";
    smtpSend($email, "Confirmation de votre demande - Prestige Drive", $clientBody);

    echo json_encode([
        'success' => true,
        'message' => 'Votre demande a été envoyée avec succès ! Nous vous contacterons sous 30 minutes.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'envoi. Veuillez nous contacter par téléphone au 07 67 50 01 01.'
    ]);
}
