<?php
require '../includes/auth.php';
require '../config/database.php';
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

$usuarioId = (int)val($_POST, 'usuario_id', 0);

$stmt = $pdo->prepare("SELECT id, bloqueado FROM usuarios WHERE id = ? AND tipo = 'cliente'");
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: usuarios.php');
    exit;
}

if ($usuario['bloqueado']) {
    $pdo->prepare('UPDATE usuarios SET bloqueado = 0 WHERE id = ?')->execute([$usuarioId]);
    header('Location: usuarios.php?desbloqueado=1');
    exit;
}

$pdo->prepare('UPDATE usuarios SET bloqueado = 1 WHERE id = ?')->execute([$usuarioId]);

// Libera os horários que o cliente bloqueado ainda ocupava
$canceladas = cancelarReservasAtivas($pdo, 'usuario_id', $usuarioId);

header('Location: usuarios.php?bloqueado=1&canceladas=' . $canceladas);
exit;
