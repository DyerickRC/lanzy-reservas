<?php
require 'includes/auth.php';
require 'config/database.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservaId = (int)val($_POST, 'reserva_id', 0);

    $stmt = $pdo->prepare('UPDATE reservas SET status = "cancelada" WHERE id = ? AND usuario_id = ? AND status = "confirmada"');
    $stmt->execute([$reservaId, $_SESSION['usuario_id']]);
}

header('Location: minhas-reservas.php?cancelada=1');
exit;
