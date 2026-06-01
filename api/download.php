<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://tyr.digital');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Honeypot — bot preenche, humano não
if (!empty($_POST['website'])) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

function sanitize(string $v, int $max = 200): string {
    return substr(strip_tags(trim($v)), 0, $max);
}

$nome      = sanitize($_POST['nome']      ?? '');
$sobrenome = sanitize($_POST['sobrenome'] ?? '');
$empresa   = sanitize($_POST['empresa']   ?? '');
$email     = sanitize($_POST['email']     ?? '', 254);
$tel       = sanitize($_POST['tel']       ?? '', 30);

$allowed = ['download-form-mdr', 'mini-form-mdr', 'mini-lead-form'];
$source  = in_array($_POST['source'] ?? '', $allowed) ? $_POST['source'] : 'download-form-mdr';

if (!$nome || !$email || !$empresa) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}

$blocked_domains = [
    'gmail.com','gmail.com.br','googlemail.com',
    'hotmail.com','hotmail.com.br','hotmail.co.uk',
    'outlook.com','outlook.com.br','live.com','live.com.br',
    'yahoo.com','yahoo.com.br',
    'bol.com.br','ig.com.br','uol.com.br','terra.com.br',
    'icloud.com','me.com','mac.com',
    'protonmail.com','proton.me',
    'yandex.com','mail.ru','msn.com','zoho.com',
    'aol.com','gmx.com','zipmail.com.br','globo.com','r7.com',
];
$domain = strtolower(substr(strrchr($email, '@'), 1));
if (in_array($domain, $blocked_domains)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Personal email not allowed']);
    exit;
}

// Proteção contra header injection no Reply-To
$email_header = str_replace(["\r", "\n"], '', $email);

// Log CSV
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) @mkdir($log_dir, 0750, true);
$csv = $log_dir . '/leads.csv';
$new = !file_exists($csv);
$fp  = fopen($csv, 'a');
if ($fp) {
    if ($new) fputcsv($fp, ['data', 'nome', 'sobrenome', 'empresa', 'email', 'telefone', 'source']);
    fputcsv($fp, [date('Y-m-d H:i:s'), $nome, $sobrenome, $empresa, $email, $tel, $source]);
    fclose($fp);
}

$to      = 'contato@tyr.com.br';
$subject = "=?UTF-8?B?" . base64_encode("Lead — MDR — $nome $sobrenome | " . ($empresa ?: $email)) . "?=";
$body    = "Novo lead da Landing Page MDR:\n\n"
         . "Nome:     $nome $sobrenome\n"
         . "Empresa:  $empresa\n"
         . "E-mail:   $email\n"
         . "Telefone: " . ($tel ?: '(não informado)') . "\n"
         . "Origem:   $source\n\n"
         . "Data/Hora: " . date('d/m/Y H:i:s') . " (UTC)\n"
         . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido') . "\n\n"
         . "---\n"
         . "TYR — Landing Page MDR\n"
         . "https://tyr.digital/lp/mdr/";

$headers  = "From: Site TYR <contato.tyr@vivasol.com.br>\r\n";
$headers .= "Reply-To: $email_header\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

mail($to, $subject, $body, $headers);

echo json_encode(['ok' => true, 'message' => 'Lead registered successfully']);
