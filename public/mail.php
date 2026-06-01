<?php
// BIPRO Remates — Formulario de contacto
// Coloca este archivo en la raíz del servidor junto al sitio Astro

$destinatarios = ['contacto@rematesbipro.cl', 'codigoraul@gmail.com'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contacto');
    exit;
}

// Sanitizar entradas
$nombre  = htmlspecialchars(strip_tags(trim($_POST['nombre']  ?? '')));
$email   = filter_var(trim($_POST['email']   ?? ''), FILTER_SANITIZE_EMAIL);
$telefono= htmlspecialchars(strip_tags(trim($_POST['telefono'] ?? '')));
$asunto  = htmlspecialchars(strip_tags(trim($_POST['asunto']  ?? 'Consulta general')));
$mensaje = htmlspecialchars(strip_tags(trim($_POST['mensaje'] ?? '')));
$origen  = htmlspecialchars(strip_tags(trim($_POST['origen']  ?? ''))); // URL del producto
$producto= htmlspecialchars(strip_tags(trim($_POST['producto'] ?? ''))); // Nombre del producto

// Validación básica
if (empty($nombre) || empty($mensaje) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /contacto?error=1');
    exit;
}

// Asunto del email
$subject = "[BIPRO Remates] $asunto" . ($producto ? " — $producto" : '');

// Cuerpo del email
$body = "
=== NUEVO MENSAJE DESDE REMATESBIPRO.CL ===

Nombre:    $nombre
Email:     $email
Teléfono:  " . ($telefono ?: 'No indicado') . "
Asunto:    $asunto
" . ($producto ? "Producto:  $producto\nURL:       $origen\n" : '') . "

Mensaje:
$mensaje

---
Enviado desde rematesbipro.cl
";

// Headers
$headers  = "From: noreply@rematesbipro.cl\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Enviar a cada destinatario
$ok = true;
foreach ($destinatarios as $dest) {
    if (!mail($dest, $subject, $body, $headers)) {
        $ok = false;
    }
}

header('Location: /contacto?' . ($ok ? 'enviado=1' : 'error=1'));
exit;
