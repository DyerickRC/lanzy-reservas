<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/analytics.php';
exigirAdmin();

$periodo = val($_GET, 'periodo', '30');
if (!isset(periodosAnalytics()[$periodo])) $periodo = '30';

$todas = carregarReservas($pdo);
list($inicio) = limitesPeriodo($periodo, $todas);
$lista = reservasEntre($todas, $inicio, time());
usort($lista, function ($a, $b) { return $a['ini'] - $b['ini']; });

$rotulosStatus = ['confirmada' => 'Confirmada', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'];
$dinheiroCsv = function ($v) { return number_format($v, 2, ',', ''); };

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="lanzy-reservas-' . date('Y-m-d') . '.csv"');

$saida = fopen('php://output', 'w');
fwrite($saida, "\xEF\xBB\xBF"); // BOM para o Excel abrir os acentos corretamente
fputcsv($saida, ['ID', 'Data', 'Início', 'Fim', 'Duração (h)', 'Cliente', 'E-mail', 'Estação', 'Tipo', 'Valor bruto (R$)', 'Desconto (R$)', 'Valor pago (R$)', 'Status'], ';');
foreach ($lista as $r) {
    fputcsv($saida, [
        $r['id'],
        date('d/m/Y', $r['ini']),
        date('H:i', $r['ini']),
        date('H:i', $r['fim']),
        number_format($r['horas'], 1, ',', ''),
        $r['cliente'],
        $r['email'],
        $r['estacao'],
        rotuloTipo($r['tipo']),
        $dinheiroCsv($r['bruto']),
        $dinheiroCsv($r['desconto']),
        $dinheiroCsv($r['valor_total']),
        $rotulosStatus[statusExibido($r)],
    ], ';');
}
fclose($saida);
