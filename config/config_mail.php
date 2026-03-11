<?php
/**
 * Configuración para envío de correo (contraseña temporal) por Gmail.
 *
 * Para Gmail:
 * 1. Activa "usar_smtp" => true
 * 2. Pon tu correo en smtp_usuario
 * 3. Usa una "Contraseña de aplicación" de Google (no tu contraseña normal):
 *    - Cuenta Google → Seguridad → Verificación en 2 pasos (activar)
 *    - Contraseñas de aplicaciones → Generar → Copiar y pegar en smtp_clave
 */
return [
    'usar_smtp'    => true,
    'smtp_host'    => 'smtp.gmail.com',
    'smtp_port'    => 587,
    'smtp_seguro'  => 'tls',
    'smtp_usuario' => 'leitogonza1717@gmail.com',
    'smtp_clave'   => 'hggg szug gkhp ldsm',
    'from_email'   => 'tu_correo@gmail.com',
    'from_nombre'  => 'INVERCLINIK',
];
