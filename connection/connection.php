<?php
// connection.php

$host = "localhost";
$user = "root";
$pass = ""; 
$db   = "db_inverclinik";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// --- SISTEMA DE SEGURIDAD GLOBAL ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function esLector() {
    return isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 2;
}

/**
 * Bloquea la ejecución de scripts que modifican la base de datos
 */
function restringirEscritura() {
    if (esLector()) {
        // Si es una petición AJAX/Fetch, respondemos con JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Acceso denegado: El rol Lector no puede realizar cambios.']);
        } else {
            // Si es una petición normal, mostramos alerta y regresamos
            echo "<script>
                alert('Acceso Denegado: Su usuario es de Solo Lectura.');
                window.history.back();
            </script>";
        }
        exit;
    }
}
?>