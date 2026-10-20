<?php
require 'includes/auth.php';
require 'config/database.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$estacaoId = (int)val($_POST, 'estacao_id', 0);
$data = val($_POST, 'data_reserva');
$horaInicio = val($_POST, 'hora_inicio');
$horaFim = val($_POST, 'hora_fim');
$usuarioId = $_SESSION['usuario_id'];

function voltarComErro($estacaoId, $mensagem) {
    global $pdo;
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['reserva_erro'] = $mensagem;
    $_SESSION['reserva_antigo'] = [
        'data_reserva' => val($_POST, 'data_reserva'),
        'hora_inicio' => val($_POST, 'hora_inicio'),
        'hora_fim' => val($_POST, 'hora_fim'),
    ];
    header('Location: reservar.php?estacao_id=' . $estacaoId);
    exit;
}

if (!$data || !$horaInicio || !$horaFim) {
    voltarComErro($estacaoId, 'Escolha a data, o horário de início e o de fim.');
}

if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $partes) || !checkdate((int)$partes[2], (int)$partes[3], (int)$partes[1])) {
    voltarComErro($estacaoId, 'Data inválida.');
}

$horaCheia = '/^([01]\d|2[0-3]):00$/';
if (!preg_match($horaCheia, $horaInicio) || !preg_match($horaCheia, $horaFim)) {
    voltarComErro($estacaoId, 'Escolha horários cheios, de hora em hora.');
}

if ($data > date('Y-m-d', strtotime('+' . DIAS_ANTECEDENCIA . ' days'))) {
    voltarComErro($estacaoId, 'Dá para reservar com até ' . DIAS_ANTECEDENCIA . ' dias de antecedência.');
}

list($inicioTs, $fimTs) = intervaloReserva($data, $horaInicio, $horaFim);
$horas = ($fimTs - $inicioTs) / 3600;

if ($horas < 1 || $horas > 12) {
    voltarComErro($estacaoId, 'A reserva deve ter entre 1 e 12 horas.');
}

if ($inicioTs <= time()) {
    voltarComErro($estacaoId, 'Esse horário já começou ou já passou. Escolha um horário futuro.');
}

// Trava a estação até o fim: dois pedidos ao mesmo tempo para ela passam um de cada vez
$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT * FROM estacoes WHERE id = ? FOR UPDATE');
$stmt->execute([$estacaoId]);
$estacao = $stmt->fetch();

if (!$estacao || $estacao['status'] !== 'disponivel') {
    voltarComErro($estacaoId, 'Estação indisponível.');
}

// Reservas que podem se sobrepor, incluindo corujões que viram o dia
$stmt = $pdo->prepare(
    "SELECT data_reserva, hora_inicio, hora_fim FROM reservas
     WHERE estacao_id = ? AND status = 'confirmada' AND data_reserva BETWEEN ? AND ?"
);
$stmt->execute([
    $estacaoId,
    date('Y-m-d', strtotime($data . ' -1 day')),
    date('Y-m-d', strtotime($data . ' +1 day')),
]);

foreach ($stmt->fetchAll() as $r) {
    list($outroInicio, $outroFim) = intervaloReserva($r['data_reserva'], $r['hora_inicio'], $r['hora_fim']);
    if ($outroInicio < $fimTs && $inicioTs < $outroFim) {
        voltarComErro($estacaoId, 'Já existe uma reserva para esta estação nesse horário.');
    }
}

$valor = calcularValor($horas, $estacao['valor_hora']);

$stmt = $pdo->prepare(
    'INSERT INTO reservas (usuario_id, estacao_id, data_reserva, hora_inicio, hora_fim, valor_total, status)
     VALUES (?, ?, ?, ?, ?, ?, "confirmada")'
);
$stmt->execute([$usuarioId, $estacaoId, $data, $horaInicio, $horaFim, $valor['total']]);
$pdo->commit();

header('Location: minhas-reservas.php?sucesso=1');
exit;
