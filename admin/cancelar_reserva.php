<?php
require '../includes/auth.php';
require '../config/database.php';
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservaId = (int)val($_POST, 'reserva_id', 0);
    $stmt = $pdo->prepare('UPDATE reservas SET status = "cancelada" WHERE id = ? AND status = "confirmada"');
    $stmt->execute([$reservaId]);
}

$busca = trim((string)val($_POST, 'busca'));
header('Location: index.php?cancelada=1' . ($busca !== '' ? '&busca=' . urlencode($busca) : ''));
exit;
