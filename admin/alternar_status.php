<?php
require '../includes/auth.php';
require '../config/database.php';
exigirAdmin();

$estacaoId = (int)val($_POST, 'estacao_id', 0);
$novoStatus = val($_POST, 'status');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($novoStatus, ['disponivel', 'manutencao', 'inativa'], true)) {
    header('Location: estacoes.php');
    exit;
}

$pdo->prepare('UPDATE estacoes SET status = ? WHERE id = ?')->execute([$novoStatus, $estacaoId]);

// Estação parada não pode manter clientes agendados
$canceladas = $novoStatus === 'disponivel' ? 0 : cancelarReservasAtivas($pdo, 'estacao_id', $estacaoId);

header('Location: estacoes.php?status=' . $novoStatus . '&canceladas=' . $canceladas);
exit;
