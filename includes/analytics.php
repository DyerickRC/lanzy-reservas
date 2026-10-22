<?php
// Cálculos da aba Analytics. Tudo é derivado das 3 tabelas existentes.

function periodosAnalytics() {
    return ['7' => '7 dias', '30' => '30 dias', '90' => '90 dias', '365' => '12 meses', 'tudo' => 'Tudo'];
}

// Todas as reservas, já com início/fim em timestamp, horas, desconto e valor bruto
function carregarReservas($pdo) {
    $lista = $pdo->query(
        "SELECT r.id, r.usuario_id, r.estacao_id, r.data_reserva, r.hora_inicio, r.hora_fim, r.valor_total, r.status,
                u.nome AS cliente, u.email, u.tipo AS usuario_tipo, e.nome AS estacao, e.tipo, e.marca
         FROM reservas r
         JOIN usuarios u ON u.id = r.usuario_id
         JOIN estacoes e ON e.id = r.estacao_id"
    )->fetchAll();

    foreach ($lista as &$r) {
        list($r['ini'], $r['fim']) = intervaloReserva($r['data_reserva'], $r['hora_inicio'], $r['hora_fim']);
        $r['horas'] = ($r['fim'] - $r['ini']) / 3600;
        $r['pct'] = percentualCorujao($r['horas']);
        $r['valor_total'] = (float)$r['valor_total'];
        $r['bruto'] = $r['pct'] ? $r['valor_total'] / (1 - $r['pct'] / 100) : $r['valor_total'];
        $r['desconto'] = $r['bruto'] - $r['valor_total'];
        $r['valida'] = $r['status'] !== 'cancelada';
    }
    unset($r);
    return $lista;
}

// Limites do período escolhido: o atual termina agora; o anterior tem o mesmo tamanho, logo antes
function limitesPeriodo($periodo, $reservas) {
    $hoje = strtotime('today');
    if ($periodo === 'tudo') {
        $inicio = $reservas ? strtotime('today', min(array_column($reservas, 'ini'))) : $hoje;
        $dias = max(1, (int)round(($hoje - $inicio) / 86400) + 1);
        return [$inicio, null, $dias];
    }
    $dias = (int)$periodo;
    $inicio = strtotime('-' . ($dias - 1) . ' days', $hoje);
    return [$inicio, strtotime("-{$dias} days", $inicio), $dias];
}

function reservasEntre($reservas, $de, $ate) {
    return array_values(array_filter($reservas, function ($r) use ($de, $ate) {
        return $r['ini'] >= $de && $r['ini'] <= $ate;
    }));
}

function resumoReservas($lista) {
    $s = ['faturamento' => 0, 'bruto' => 0, 'desconto' => 0, 'horas' => 0, 'reservas' => 0, 'canceladas' => 0, 'valorCancelado' => 0, 'total' => count($lista), 'clientes' => []];
    foreach ($lista as $r) {
        if (!$r['valida']) {
            $s['canceladas']++;
            $s['valorCancelado'] += $r['valor_total'];
            continue;
        }
        $s['faturamento'] += $r['valor_total'];
        $s['bruto'] += $r['bruto'];
        $s['desconto'] += $r['desconto'];
        $s['horas'] += $r['horas'];
        $s['reservas']++;
        if ($r['usuario_tipo'] === 'cliente') $s['clientes'][$r['usuario_id']] = true;
    }
    $s['clientes'] = count($s['clientes']);
    $s['ticket'] = $s['reservas'] ? $s['faturamento'] / $s['reservas'] : 0;
    $s['sessaoMedia'] = $s['reservas'] ? $s['horas'] / $s['reservas'] : 0;
    $s['taxaCancelamento'] = $s['total'] ? $s['canceladas'] / $s['total'] * 100 : 0;
    return $s;
}

// Horas em que pelo menos uma máquina estava em uso (intervalos sobrepostos são unidos)
function horasComMovimento($lista, $de, $ate) {
    $intervalos = [];
    $horasUsadas = 0;
    foreach ($lista as $r) {
        if (!$r['valida']) continue;
        $a = max($r['ini'], $de);
        $b = min($r['fim'], $ate);
        if ($b > $a) {
            $intervalos[] = [$a, $b];
            $horasUsadas += ($b - $a) / 3600;
        }
    }
    usort($intervalos, function ($x, $y) { return $x[0] - $y[0]; });

    $unidos = [];
    foreach ($intervalos as $i) {
        $n = count($unidos);
        if ($n && $i[0] <= $unidos[$n - 1][1]) {
            $unidos[$n - 1][1] = max($unidos[$n - 1][1], $i[1]);
        } else {
            $unidos[] = $i;
        }
    }

    $total = 0;
    $porDia = [];
    foreach ($unidos as $i) {
        list($a, $b) = $i;
        $total += ($b - $a) / 3600;
        while ($a < $b) {
            $c = min($b, strtotime('tomorrow', $a));
            $dia = date('Y-m-d', $a);
            $porDia[$dia] = val($porDia, $dia, 0) + ($c - $a) / 3600;
            $a = $c;
        }
    }
    return ['total' => $total, 'porDia' => $porDia, 'horasUsadas' => $horasUsadas];
}

function variacao($atual, $anterior) {
    if ($anterior === null) return null;
    if ($anterior == 0) return $atual == 0 ? 0.0 : null;
    return ($atual - $anterior) / $anterior * 100;
}

function numero($valor, $casas = 0) {
    return number_format((float)$valor, $casas, ',', '.');
}

function horasFmt($horas) {
    return numero($horas, $horas < 10 && floor($horas) != $horas ? 1 : 0) . ' h';
}

function duracaoHoras($horas) {
    $min = (int)round($horas * 60);
    return intdiv($min, 60) . 'h' . ($min % 60 ? str_pad($min % 60, 2, '0', STR_PAD_LEFT) : '');
}

function mesAbrev($ts) {
    $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    return $meses[(int)date('n', $ts) - 1] . '/' . date('y', $ts);
}

function diasDaSemana() {
    return [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
}
