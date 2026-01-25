<?php
session_start();
require_once "../connection/connection.php";
header('Content-Type: application/json; charset=utf-8');

try {
    $id = $_SESSION['iduser'] ?? null;
    if (!$id) {
        throw new Exception("Sesión no válida, usuario no identificado");
    }

    $username = $_POST['username'] ?? '';
    $login    = $_POST['login'] ?? '';
    $correo   = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($login) || empty($correo)) {
        throw new Exception("Usuario, login y correo son obligatorios");
    }

    // Validar duplicados
    $sqlCheck = "SELECT id FROM users WHERE (username = ? OR login = ? OR correo = ?) AND id != ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("sssi", $username, $login, $correo, $id);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    if ($resultCheck->num_rows > 0) {
        throw new Exception("El usuario, login o correo ya existe");
    }
    $stmtCheck->close();

    // Actualizar datos
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username=?, login=?, correo=?, password=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $login, $correo, $hash, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, login=?, correo=? WHERE id=?");
        $stmt->bind_param("sssi", $username, $login, $correo, $id);
    }

    if (!$stmt) {
        throw new Exception("Error en prepare: " . $conn->error);
    }

    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Perfil actualizado exitosamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}