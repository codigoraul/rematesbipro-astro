# Remates BiPro — Notas del proyecto

> Archivo de contexto compartido. Tú lo puedes abrir y editar cuando quieras;
> Claude lo lee automáticamente al iniciar, así no hay que repetir estos datos.

## Rutas locales (en el computador de Raul)

- **Proyecto Astro (este repo):** `/Users/raulr/Downloads/PARADOCUMENTOS/paginas web2025:26/RematesBipro/remates-bipro`
- **Fotos de vehículos:** `/Users/raulr/Downloads/PARADOCUMENTOS/paginas web2025:26/RematesBipro/fotos vehiculos`
- **Proyecto de referencia (mismo stack, formulario PHP probado):** `/Users/raulr/Downloads/PARADOCUMENTOS/paginas web2025:26/ENTREGADAS 2026/Elquicapital/elquicapital.cl`

## Stack y arquitectura

- Front: **Astro** (sitio estático) → carpeta `dist/`.
- CMS: **WordPress headless** en `https://rematesbipro.cl/admin/wp-admin/` (ACF PRO para los campos de cada vehículo).
- Tipos de contenido WP: **Vehículos**, **Propiedades**, **Otros**.

## Deploy automático

- Plugin WP **"GitHub Auto Deploy"** (activo): al **publicar o editar** contenido en el admin, dispara el **workflow de GitHub Actions** que reconstruye y despliega el sitio Astro.
- También se puede gatillar el build manualmente desde Devin.
- ⚠️ Cada edición vuelve a gatillar build → conviene agrupar cambios y guardar una sola vez.

## Formulario de contacto (`public/mail.php`)

- Handler PHP en `public/mail.php` → Astro lo copia a `dist/mail.php` → queda en `https://rematesbipro.cl/mail.php`.
- Envía correo **HTML formateado** (multipart texto + HTML) con `mail()` de PHP.
- Destinatarios por defecto: `codigoraul@gmail.com`, `contacto@rematesbipro.cl`.
- Campos: `nombre*`, `email*`, `mensaje*` (obligatorios) · `telefono`, `asunto`, `producto`, `origen` (opcionales) · `_gotcha` (honeypot anti-spam).
- Responde **JSON** si la petición es AJAX (`fetch`), o **redirige** a `/contacto?enviado=1|error=1` si es POST de formulario normal.
- **Diagnóstico sin enviar:** `https://rematesbipro.cl/mail.php?debug=1` (muestra la config en JSON).
- Para cambiar destinatarios sin tocar el código: crear `public/mail-config.php` (y agregarlo a `.gitignore`). Ver `public/mail-config.php.example`.
- Requisito del hosting: PHP con `mail()` habilitado. Para no caer en spam: remitente del mismo dominio + SPF/DKIM/DMARC.
