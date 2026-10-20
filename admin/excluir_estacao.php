<?php
require '../includes/auth.php';
require '../config/database.php';
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estacaoId = (int)val($_POST, 'estacao_id', 0);

    // Estação com histórico só pode ser desativada, para não apagar reservas e faturamento
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reservas WHERE estacao_id = ?');
    $stmt->execute([$estacaoId]);
    if ($stmt->fetchColumn() > 0) {
        header('Location: estacoes.php?erro=historico');
        exit;
    }

    $pdo->prepare('DELETE FROM estacoes WHERE id = ?')->execute([$estacaoId]);
    header('Location: estacoes.php?excluida=1');
    exit;
}

header('Location: estacoes.php');
exit;
