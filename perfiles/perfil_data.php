<?php
session_start();
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $esCliente = isset($_SESSION['tipo'], $_SESSION['id_cliente'])
        && $_SESSION['tipo'] === 'cliente'
        && (int) $_SESSION['id_cliente'] > 0;
    $idStaff = isset($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : 0;

    if ($esCliente) {
        $id = (int) $_SESSION['id_cliente'];
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $tipo_documento = trim($_POST['tipo_documento'] ?? '');
        $numero_documento = trim($_POST['numero_documento'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($nombre === '' || $correo === '') {
            throw new Exception("Nombre y correo son obligatorios");
        }

        $stmt = $conn->prepare("SELECT id FROM clientes WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $correo, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            throw new Exception("El correo ya está registrado en otra cuenta");
        }
        $stmt->close();

        if ($numero_documento !== '') {
            $stmt = $conn->prepare("SELECT id FROM clientes WHERE numero_documento = ? AND id != ?");
            $stmt->bind_param("si", $numero_documento, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                throw new Exception("El número de documento ya está en uso");
            }
            $stmt->close();
        }

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE clientes SET nombre=?, email=?, tipo_documento=?, numero_documento=?, telefono=?, direccion=?, password=? WHERE id=?");
            $stmt->bind_param("sssssssi", $nombre, $correo, $tipo_documento, $numero_documento, $telefono, $direccion, $hash, $id);
        } else {
            $stmt = $conn->prepare("UPDATE clientes SET nombre=?, email=?, tipo_documento=?, numero_documento=?, telefono=?, direccion=? WHERE id=?");
            $stmt->bind_param(
                "ssssssi",
                $nombre,
                $correo,
                $tipo_documento,
                $numero_documento,
                $telefono,
                $direccion,
                $id
            );
        }

        if (!$stmt) {
            throw new Exception("Error al preparar la actualización: " . $conn->error);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("No se pudo guardar el perfil: " . $conn->error);
        }
        $stmt->close();

        $_SESSION['nombre_cliente'] = $nombre;

        $msgPerfil = !empty($password)
            ? 'Perfil actualizado y contraseña modificada (cliente id ' . $id . ').'
            : 'Perfil actualizado (cliente id ' . $id . ').';
        Auditoria::registrar($conn, $msgPerfil, 'Perfil');

        echo json_encode(['success' => true, 'message' => 'Perfil actualizado exitosamente']);
    } elseif ($idStaff > 0) {
        $id = $idStaff;
        $username = $_POST['username'] ?? '';
        $correo = $_POST['correo'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($correo)) {
            throw new Exception("Usuario y correo son obligatorios");
        }

        $sqlCheck = "SELECT id FROM users WHERE (username = ? OR correo = ?) AND id != ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("ssi", $username, $correo, $id);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows > 0) {
            $stmtCheck->close();
            throw new Exception("El usuario o correo ya existe");
        }
        $stmtCheck->close();

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, correo=?, password=? WHERE id=?");
            $stmt->bind_param("sssi", $username, $correo, $hash, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, correo=? WHERE id=?");
            $stmt->bind_param("ssi", $username, $correo, $id);
        }

        if (!$stmt) {
            throw new Exception("Error en prepare: " . $conn->error);
        }

        $stmt->execute();
        $stmt->close();

        $msgStaff = !empty($password)
            ? 'Perfil actualizado y contraseña modificada (usuario interno id ' . $id . ').'
            : 'Perfil actualizado (usuario interno id ' . $id . ').';
        Auditoria::registrar($conn, $msgStaff, 'Perfil');

        echo json_encode(['success' => true, 'message' => 'Perfil actualizado exitosamente']);
    } else {
        throw new Exception("Sesión no válida, usuario no identificado");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
