<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Ingresa tu correo electrónico.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'El formato del correo no es válido.']);
    exit;
}

include 'connection/connection.php';

// Buscar en clientes
$stmtC = $conn->prepare("SELECT id, nombre FROM clientes WHERE email = ?");
$stmtC->bind_param("s", $email);
$stmtC->execute();
$resC = $stmtC->get_result();
$stmtC->close();

if ($rowC = $resC->fetch_assoc()) {
    $tipo = 'cliente';
    $id = $rowC['id'];
    $nombre = $rowC['nombre'];
    $tabla = 'clientes';
    $campo_pass = 'password';
} else {
    // Buscar en users
    $stmtU = $conn->prepare("SELECT id, username FROM users WHERE correo = ?");
    $stmtU->bind_param("s", $email);
    $stmtU->execute();
    $resU = $stmtU->get_result();
    $stmtU->close();

    if ($rowU = $resU->fetch_assoc()) {
        $tipo = 'usuario';
        $id = $rowU['id'];
        $nombre = $rowU['username'];
        $tabla = 'users';
        $campo_pass = 'password';
    } else {
        // Por seguridad: mismo mensaje si no existe el correo
        echo json_encode([
            'success' => true,
            'message' => 'Si ese correo está registrado, recibirás una contraseña temporal. Revisa tu bandeja y úsala para iniciar sesión.'
        ]);
        mysqli_close($conn);
        exit;
    }
}

// Generar contraseña temporal (10 caracteres alfanuméricos)
$temp_pass = bin2hex(random_bytes(5));
$hash = password_hash($temp_pass, PASSWORD_DEFAULT);

if ($tabla === 'clientes') {
    $stmt = $conn->prepare("UPDATE clientes SET password = ? WHERE id = ?");
} else {
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
}
$stmt->bind_param("si", $hash, $id);
$stmt->execute();
$stmt->close();

// Enviar correo con la contraseña temporal
$asunto = 'INVERCLINIK - Contraseña temporal';
$cuerpo = "Hola " . htmlspecialchars($nombre) . ",\n\n";
$cuerpo .= "Has solicitado recuperar el acceso a INVERCLINIK.\n\n";
$cuerpo .= "Tu contraseña temporal es: " . $temp_pass . "\n\n";
$cuerpo .= "Usa esta contraseña junto con tu correo para iniciar sesión. Te recomendamos cambiarla desde tu perfil una vez dentro.\n\n";
$cuerpo .= "Si no solicitaste esto, ignora este mensaje.\n\n";
$cuerpo .= "— INVERCLINIK";

$configMail = file_exists(__DIR__ . '/config/config_mail.php') ? include __DIR__ . '/config/config_mail.php' : [];
$enviado = false;

// Intentar envío por Gmail SMTP con PHPMailer si está instalado y configurado
if (!empty($configMail['usar_smtp']) && file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host       = $configMail['smtp_host'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $configMail['smtp_usuario'] ?? '';
            $mail->Password   = $configMail['smtp_clave'] ?? '';
            $mail->SMTPSecure = $configMail['smtp_seguro'] ?? 'tls';
            $mail->Port       = (int)($configMail['smtp_port'] ?? 587);
            $mail->setFrom($configMail['from_email'] ?? '', $configMail['from_nombre'] ?? 'INVERCLINIK');
            $mail->addAddress($email, $nombre);
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpo;
            $mail->isHTML(false);
            $mail->send();
            $enviado = true;
        } catch (Exception $e) {
            // Fallback a mail() más abajo
        }
    }
}

// Si no se usó SMTP o falló, intentar con mail() de PHP
if (!$enviado) {
    $fromNombre = $configMail['from_nombre'] ?? 'INVERCLINIK';
    $fromEmail  = $configMail['from_email'] ?? 'noreply@inverclinik.com';
    $headers = "From: $fromNombre <$fromEmail>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $enviado = @mail($email, $asunto, $cuerpo, $headers);
}

mysqli_close($conn);

if ($enviado) {
    echo json_encode([
        'success' => true,
        'message' => 'Se ha enviado una contraseña temporal a tu correo. Úsala para iniciar sesión.'
    ]);
} else {
    $hint = '';
    if (!empty($configMail['usar_smtp']) && !class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $hint = ' Ejecuta en la carpeta del proyecto: composer require phpmailer/phpmailer';
    } elseif (!empty($configMail['usar_smtp'])) {
        $hint = ' Revisa config/config_mail.php: usa tu Gmail y una Contraseña de aplicación de Google (no la contraseña normal).';
    }
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo enviar el correo.' . $hint
    ]);
}
