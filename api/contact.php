<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$nome      = trim($_POST['nome']      ?? '');
$sobrenome = trim($_POST['sobrenome'] ?? '');
$email     = trim($_POST['email']     ?? '');
$tel       = trim($_POST['tel']       ?? '');
$mensagem  = trim($_POST['mensagem']  ?? '');
$source    = trim($_POST['source']    ?? 'contact-form-mdr');

if (!$nome || !$email || !$mensagem) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Missing required fields']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Invalid email']); exit; }

$nome      = htmlspecialchars($nome,      ENT_QUOTES, 'UTF-8');
$sobrenome = htmlspecialchars($sobrenome, ENT_QUOTES, 'UTF-8');
$tel       = htmlspecialchars($tel,       ENT_QUOTES, 'UTF-8');
$mensagem  = htmlspecialchars($mensagem,  ENT_QUOTES, 'UTF-8');

$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$csv = $log_dir . '/contacts.csv';
$new = !file_exists($csv);
$fp  = fopen($csv, 'a');
if ($new) fputcsv($fp, ['data','nome','sobrenome','email','telefone','mensagem','source']);
fputcsv($fp, [date('Y-m-d H:i:s'), $nome, $sobrenome, $email, $tel, $mensagem, $source]);
fclose($fp);

$to      = 'contato@tyr.com.br';
$subject = "=?UTF-8?B?" . base64_encode("Fale Conosco — MDR — $nome $sobrenome | $email") . "?=";
$body    = "Nova mensagem de contato — Landing Page MDR:\n\n"
         . "Nome:     $nome $sobrenome\n"
         . "E-mail:   $email\n"
         . "Telefone: " . ($tel ?: '(não informado)') . "\n"
         . "Origem:   $source\n\n"
         . "Mensagem:\n$mensagem\n\n"
         . "Data/Hora: " . date('d/m/Y H:i:s') . " (UTC)\n"
         . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido') . "\n\n"
         . "---\n"
         . "TYR — Landing Page MDR\n"
         . "https://tyr.digital/lp/mdr/";

$headers  = "From: Site TYR <contato.tyr@vivasol.com.br>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

mail($to, $subject, $body, $headers);

echo json_encode(['ok'=>true,'message'=>'Message sent successfully']);
