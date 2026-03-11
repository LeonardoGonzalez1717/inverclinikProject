<?php
session_start();

if (!isset($_SESSION['id_cliente']) || $_SESSION['tipo'] !== 'cliente') {
    session_destroy();
    header("Location: ../index.php?error=acceso_denegado");
    exit();
}

$idCliente = $_SESSION['id_cliente'];
$nombreCliente = $_SESSION['nombre_cliente'];
?>