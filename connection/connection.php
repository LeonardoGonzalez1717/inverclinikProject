<?php
// connection.php

$host = "localhost";
$user = "root";
$pass = ""; 
$db   = "db_inverclinik";

$conn = @new mysqli($host, $user, $pass, $db);
$db_disponible = true;

if ($conn->connect_error) {
    // Si la BD no existe, permitimos conexión al servidor para recuperar respaldos/restaurar.
    if ((int) $conn->connect_errno === 1049) {
        $conn = @new mysqli($host, $user, $pass);
        if ($conn->connect_error) {
            die("Conexión fallida: " . $conn->connect_error);
        }
        $db_disponible = false;
    } else {
        die("Conexión fallida: " . $conn->connect_error);
    }
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
            $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
            $projRoot = str_replace('\\', '/', dirname(__DIR__));
            $swalJsUrl = (strpos($projRoot, $docRoot) === 0)
                ? str_replace($docRoot, '', $projRoot) . '/assets/js/sweetalert2.all.min.js'
                : '/assets/js/sweetalert2.all.min.js';
            $swalJsUrl = htmlspecialchars($swalJsUrl, ENT_QUOTES, 'UTF-8');
            echo "<script>
                (function(){
                    function mostrar() {
                        Swal.fire({ icon: 'warning', text: 'Acceso Denegado: Su usuario es de Solo Lectura.' }).then(function(){ window.history.back(); });
                    }
                    if (typeof Swal !== 'undefined') { mostrar(); return; }
                    var s = document.createElement('script');
                    s.src = '{$swalJsUrl}';
                    s.onload = function(){ window.Swal = Swal.mixin({ confirmButtonText: 'Aceptar' }); mostrar(); };
                    document.head.appendChild(s);
                })();
            </script>";
        }
        exit;
    }
}
?>