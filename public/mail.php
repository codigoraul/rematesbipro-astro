<?php

declare(strict_types=1);

/**
 * mail.php — Formulario de contacto de Remates BiPro.
 * Envía un correo HTML bien formateado (multipart texto + HTML).
 *
 * Ubicación: public/mail.php  → Astro lo copia a dist/mail.php en el build
 *            → queda servido en https://rematesbipro.cl/mail.php
 *
 * Responde JSON si la petición es AJAX (fetch), o redirige a /contacto si es
 * un POST de formulario tradicional. Patrón basado en elquicapital.cl.
 */

$SITE_URL = (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '')
  ? ('https://' . $_SERVER['HTTP_HOST'])
  : 'https://rematesbipro.cl';

// Destinatarios por defecto (la prueba llega a tu Gmail).
$TO_EMAILS_BASE = 'codigoraul@gmail.com, contacto@rematesbipro.cl';
$TO_EMAIL   = $TO_EMAILS_BASE;
$FROM_EMAIL = 'contacto@rematesbipro.cl'; // mismo dominio = menos spam
$FROM_NAME  = 'Remates BiPro';
$BCC_EMAILS = '';

$CONFIG_USED_PATH = '';

// --- Overrides por variables de entorno (opcional) ---
foreach ([
  'SITE_URL'           => 'SITE_URL',
  'CONTACT_FROM_EMAIL' => 'FROM_EMAIL',
  'CONTACT_FROM_NAME'  => 'FROM_NAME',
  'CONTACT_BCC_EMAILS' => 'BCC_EMAILS',
] as $env => $var) {
  $v = getenv($env);
  if ($v !== false && $v !== '') ${$var} = $v;
}
$envTo = getenv('CONTACT_TO_EMAIL');
if ($envTo !== false && $envTo !== '') $TO_EMAIL = $TO_EMAILS_BASE . ', ' . $envTo;

// --- Overrides por archivo mail-config.php (recomendado, NO subir a git) ---
foreach ([__DIR__ . '/mail-config.php', dirname(__DIR__) . '/mail-config.php'] as $configPath) {
  if (is_file($configPath)) {
    $config = include $configPath;
    if (is_array($config)) {
      if (isset($config['SITE_URL']))    $SITE_URL   = (string)$config['SITE_URL'];
      if (isset($config['TO_EMAIL']))    $TO_EMAIL   = $TO_EMAILS_BASE . ', ' . (string)$config['TO_EMAIL'];
      if (isset($config['FROM_EMAIL']))  $FROM_EMAIL = (string)$config['FROM_EMAIL'];
      if (isset($config['FROM_NAME']))   $FROM_NAME  = (string)$config['FROM_NAME'];
      if (isset($config['BCC_EMAILS']))  $BCC_EMAILS = (string)$config['BCC_EMAILS'];
    }
    $CONFIG_USED_PATH = $configPath;
    break;
  }
}

// ¿La petición espera JSON? (fetch/AJAX) o ¿es un POST de formulario normal?
$wantsJson = (
  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
  || (isset($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

function json_out(array $data): void {
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
function respond(bool $ok, string $message, bool $wantsJson, string $siteUrl): void {
  if ($wantsJson) {
    json_out(['success' => $ok, 'message' => $message]);
  }
  $status = $ok ? 'enviado=1' : 'error=1';
  header('Location: ' . rtrim($siteUrl, '/') . '/contacto?' . $status . '#contacto', true, 303);
  exit;
}

// --- GET: diagnóstico (?debug=1) sin enviar correo ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    json_out([
      'handler'         => 'mail.php',
      'site_url'        => $SITE_URL,
      'to_email'        => $TO_EMAIL,
      'from_email'      => $FROM_EMAIL,
      'from_name'       => $FROM_NAME,
      'bcc_emails'      => $BCC_EMAILS !== '' ? $BCC_EMAILS : null,
      'config_used'     => $CONFIG_USED_PATH !== '' ? basename($CONFIG_USED_PATH) : null,
      'php_mail_exists' => function_exists('mail'),
    ]);
  }
  header('Location: ' . rtrim($SITE_URL, '/') . '/contacto#contacto', true, 303);
  exit;
}

// --- Honeypot anti-spam ---
if (trim((string)($_POST['_gotcha'] ?? '')) !== '') {
  respond(true, 'Mensaje enviado.', $wantsJson, $SITE_URL);
}

// --- Campos del formulario ---
$nombre   = trim((string)($_POST['nombre'] ?? ''));
$email    = trim((string)($_POST['email'] ?? ''));
$telefono = trim((string)($_POST['telefono'] ?? ''));
$asunto   = trim((string)($_POST['asunto'] ?? ''));
$mensaje  = trim((string)($_POST['mensaje'] ?? ''));
$producto = trim((string)($_POST['producto'] ?? '')); // nombre del vehículo/producto consultado
$origen   = trim((string)($_POST['origen'] ?? ''));    // URL del producto

if ($nombre === '' || $email === '' || $mensaje === '') {
  respond(false, 'Por favor completa nombre, email y mensaje.', $wantsJson, $SITE_URL);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(false, 'El correo electrónico ingresado no es válido.', $wantsJson, $SITE_URL);
}

$subject = 'Nueva consulta desde rematesbipro.cl';
if ($asunto !== '')   $subject = 'Consulta: ' . $asunto;
if ($producto !== '') $subject .= ' — ' . $producto;

$escape = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$sanitizeHeader = static fn (string $v): string => trim(str_replace(["\r", "\n"], ' ', $v));
$encodeName = static function (string $v) use ($sanitizeHeader): string {
  $v = $sanitizeHeader($v);
  return $v === '' ? '' : '=?UTF-8?B?' . base64_encode($v) . '?=';
};
$parseList = static function (string $v) use ($sanitizeHeader): array {
  $v = $sanitizeHeader($v);
  if ($v === '') return [];
  $parts = preg_split('/[\s,;]+/', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $out = [];
  foreach ($parts as $p) if (filter_var($p, FILTER_VALIDATE_EMAIL)) $out[] = $p;
  return array_values(array_unique($out));
};

// --- Cuerpo HTML (tabla) ---
$rowHtml = static function (string $label, string $value) use ($escape): string {
  return '<tr>'
    . '<td style="padding:10px 12px; border:1px solid #E5E7EB; background:#F9FAFB; font-weight:700; width:170px; vertical-align:top;">' . $escape($label) . '</td>'
    . '<td style="padding:10px 12px; border:1px solid #E5E7EB; vertical-align:top;">' . $value . '</td>'
    . '</tr>';
};

$productoRow = $producto !== ''
  ? $rowHtml('Vehículo / producto', $escape($producto) . ($origen !== '' ? '<br><a href="' . $escape($origen) . '" style="color:#2563EB;">' . $escape($origen) . '</a>' : ''))
  : '';

$bodyHtml = '<!doctype html><html><head><meta charset="UTF-8"></head>'
  . '<body style="margin:0; padding:24px; background:#F3F4F6; font-family:Arial,Helvetica,sans-serif; color:#111827;">'
  . '<div style="max-width:640px; margin:0 auto; background:#FFFFFF; border:1px solid #E5E7EB; border-radius:10px; overflow:hidden;">'
  . '<div style="background:#111827; color:#FFFFFF; padding:18px 22px;">'
  . '<h2 style="margin:0; font-size:18px;">Nueva consulta — Remates BiPro</h2>'
  . '<p style="margin:6px 0 0; font-size:13px; color:#9CA3AF;">Recibida desde el formulario de rematesbipro.cl</p>'
  . '</div>'
  . '<div style="padding:22px;">'
  . '<table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; width:100%;"><tbody>'
  . $rowHtml('Nombre', $escape($nombre))
  . $rowHtml('Email', '<a href="mailto:' . $escape($email) . '" style="color:#2563EB;">' . $escape($email) . '</a>')
  . $rowHtml('Teléfono', $telefono !== '' ? $escape($telefono) : '—')
  . $rowHtml('Asunto', $asunto !== '' ? $escape($asunto) : '—')
  . $productoRow
  . $rowHtml('Mensaje', nl2br($escape($mensaje)))
  . '</tbody></table>'
  . '<p style="margin:18px 0 0; font-size:12px; color:#6B7280;">Enviado el ' . date('d-m-Y H:i') . ' hrs.</p>'
  . '</div></div></body></html>';

$bodyText = "Nueva consulta desde Remates BiPro\n\n"
  . "Nombre: {$nombre}\n"
  . "Email: {$email}\n"
  . "Teléfono: " . ($telefono !== '' ? $telefono : '-') . "\n"
  . "Asunto: " . ($asunto !== '' ? $asunto : '-') . "\n"
  . ($producto !== '' ? "Vehículo/producto: {$producto}\n" : '')
  . ($origen !== '' ? "URL: {$origen}\n" : '')
  . "\nMensaje:\n{$mensaje}\n";

// --- MIME multipart/alternative ---
$boundary = 'rematesbipro_' . bin2hex(random_bytes(12));
$body = "--{$boundary}\r\n"
  . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
  . $bodyText . "\r\n\r\n"
  . "--{$boundary}\r\n"
  . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
  . $bodyHtml . "\r\n\r\n"
  . "--{$boundary}--\r\n";

$host = parse_url($SITE_URL, PHP_URL_HOST);
if (!is_string($host) || $host === '') $host = 'rematesbipro.cl';

$headers = [
  'MIME-Version: 1.0',
  'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
  'Date: ' . date(DATE_RFC2822),
  'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $host . '>',
  'From: ' . $encodeName($FROM_NAME) . ' <' . $sanitizeHeader($FROM_EMAIL) . '>',
  'Reply-To: ' . ($encodeName($nombre) !== '' ? ($encodeName($nombre) . ' ') : '') . '<' . $sanitizeHeader($email) . '>',
];

$toEmails = $parseList($TO_EMAIL);
$toHeader = $toEmails !== [] ? implode(', ', $toEmails) : $sanitizeHeader($TO_EMAIL);

$bccEmails = $parseList($BCC_EMAILS);
if ($bccEmails !== []) $headers[] = 'Bcc: ' . implode(', ', $bccEmails);

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$params = '-f ' . $sanitizeHeader($FROM_EMAIL);

$ok = @mail($toHeader, $encodedSubject, $body, implode("\r\n", $headers), $params);
if (!$ok) {
  $ok = @mail($toHeader, $encodedSubject, $body, implode("\r\n", $headers)); // reintento sin -f
}

respond(
  (bool)$ok,
  $ok ? '¡Mensaje enviado! Te contactaremos a la brevedad.'
      : 'No se pudo enviar el mensaje. Intenta nuevamente.',
  $wantsJson,
  $SITE_URL
);
