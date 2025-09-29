<?php
// connection.php

$host = "localhost";
$user = "root";
$pass = ""; // Cambia si tienes contraseña
$db   = "db_inverclinik";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>