<?php
define('DIAS_ANTECEDENCIA', 60);

function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function dinheiro($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function primeiroNome($nome) {
    $partes = preg_split('/\s+/', trim($nome));
    return $partes[0];
}

function iniciais($nome) {
    $partes = preg_split('/\s+/', trim($nome));
    $ini = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) {
        $ini .= mb_substr(end($partes), 0, 1);
    }
    return mb_strtoupper($ini);
}

function tomEstacao($tipo) {
    return $tipo === 'computador' ? 'tone-pc' : 'tone-console';
}

function rotuloTipo($tipo) {
    return $tipo === 'computador' ? 'Computador' : 'Video game';
}

function iconeEstacao($tipo, $nome, $marca = '') {
    if ($tipo === 'computador') return 'bi-pc-display';
    $texto = strtolower($marca . ' ' . $nome);
    if (strpos($texto, 'playstation') !== false || strpos($texto, 'ps5') !== false) return 'bi-playstation';
    if (strpos($texto, 'xbox') !== false) return 'bi-xbox';
    if (strpos($texto, 'switch') !== false || strpos($texto, 'nintendo') !== false) return 'bi-nintendo-switch';
    return 'bi-controller';
}

function marcasSugeridas() {
    return ['PlayStation', 'Xbox', 'Nintendo', 'Asus', 'Acer', 'Dell', 'Lenovo', 'HP', 'Alienware'];
}

function specs($descricao) {
    return array_values(array_filter(array_map('trim', explode(',', (string)$descricao)), 'strlen'));
}

// Quando o fim é menor ou igual ao início, a reserva termina no dia seguinte
function viraODia($inicio, $fim) {
    return strtotime($fim) <= strtotime($inicio);
}

function horasEntre($inicio, $fim) {
    $segundos = strtotime($fim) - strtotime($inicio);
    if ($segundos <= 0) $segundos += 86400;
    return $segundos / 3600;
}

function duracaoTexto($inicio, $fim) {
    $min = (int)round(horasEntre($inicio, $fim) * 60);
    $m = $min % 60;
    return intdiv($min, 60) . 'h' . ($m ? str_pad($m, 2, '0', STR_PAD_LEFT) : '');
}

function faixaHorario($inicio, $fim) {
    $texto = substr($inicio, 0, 5) . ' – ' . substr($fim, 0, 5);
    return viraODia($inicio, $fim) ? $texto . ' (+1 dia)' : $texto;
}

function intervaloReserva($data, $inicio, $fim) {
    $ini = strtotime($data . ' ' . $inicio);
    $fimTs = strtotime($data . ' ' . $fim);
    if ($fimTs <= $ini) {
        $fimTs = strtotime($data . ' ' . $fim . ' +1 day');
    }
    return [$ini, $fimTs];
}

function percentualCorujao($horas) {
    if ($horas >= 12) return 20;
    if ($horas >= 10) return 15;
    if ($horas >= 8) return 10;
    return 0;
}

function calcularValor($horas, $valorHora) {
    $subtotal = round($horas * $valorHora, 2);
    $percentual = percentualCorujao($horas);
    $desconto = round($subtotal * $percentual / 100, 2);
    return [
        'subtotal' => $subtotal,
        'percentual' => $percentual,
        'desconto' => $desconto,
        'total' => $subtotal - $desconto,
    ];
}

function ehCorujao($inicio, $fim) {
    return horasEntre($inicio, $fim) >= 8;
}

// Horas cheias ocupadas, no formato "AAAA-MM-DD HH"
function slotsOcupados($reservas) {
    $slots = [];
    foreach ($reservas as $r) {
        list($ini, $fim) = intervaloReserva($r['data_reserva'], $r['hora_inicio'], $r['hora_fim']);
        for ($t = strtotime(date('Y-m-d H:00:00', $ini)); $t < $fim; $t += 3600) {
            $slots[date('Y-m-d H', $t)] = true;
        }
    }
    return array_keys($slots);
}

function mesCurto($data) {
    $meses = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
    return $meses[(int)date('n', strtotime($data)) - 1];
}

function rotuloDia($data) {
    if ($data === date('Y-m-d')) return 'Hoje';
    if ($data === date('Y-m-d', strtotime('+1 day'))) return 'Amanhã';
    return date('d/m', strtotime($data));
}

function reservaAtiva($r) {
    if ($r['status'] !== 'confirmada') return false;
    list(, $fim) = intervaloReserva($r['data_reserva'], $r['hora_inicio'], $r['hora_fim']);
    return $fim > time();
}

// Cancela as reservas que ainda não terminaram de uma estação ou de um cliente
function cancelarReservasAtivas($pdo, $campo, $id) {
    $campo = $campo === 'usuario_id' ? 'usuario_id' : 'estacao_id';
    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE $campo = ? AND status = 'confirmada' AND data_reserva >= ?");
    $stmt->execute([$id, date('Y-m-d', strtotime('-1 day'))]);

    $cancelar = $pdo->prepare("UPDATE reservas SET status = 'cancelada' WHERE id = ?");
    $total = 0;
    foreach ($stmt->fetchAll() as $r) {
        if (reservaAtiva($r)) {
            $cancelar->execute([$r['id']]);
            $total++;
        }
    }
    return $total;
}

function contarReservasAtivasPorEstacao($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE status = 'confirmada' AND data_reserva >= ?");
    $stmt->execute([date('Y-m-d', strtotime('-1 day'))]);
    $contagem = [];
    foreach ($stmt->fetchAll() as $r) {
        if (reservaAtiva($r)) {
            $contagem[$r['estacao_id']] = val($contagem, $r['estacao_id'], 0) + 1;
        }
    }
    return $contagem;
}

// Reserva confirmada cujo horário já passou é exibida como concluída
function statusExibido($r) {
    if ($r['status'] === 'confirmada' && !reservaAtiva($r)) return 'concluida';
    return $r['status'];
}

function chipStatus($status) {
    $mapa = [
        'confirmada' => ['Confirmada', 'lz-chip-ok', 'bi-check2-circle'],
        'concluida' => ['Concluída', 'lz-chip-done', 'bi-flag'],
        'cancelada' => ['Cancelada', 'lz-chip-cancel', 'bi-x-circle'],
    ];
    list($rotulo, $classe, $icone) = isset($mapa[$status]) ? $mapa[$status] : [ucfirst($status), 'lz-chip-done', 'bi-circle'];
    return '<span class="lz-chip ' . $classe . '"><i class="bi ' . $icone . '"></i> ' . $rotulo . '</span>';
}

// Texto digitado na busca (limitado) e o padrão LIKE correspondente, com % e _ tratados como texto
function termoBusca() {
    $busca = trim(mb_substr((string)(isset($_GET['busca']) ? $_GET['busca'] : ''), 0, 100));
    return [$busca, '%' . addcslashes($busca, '%_\\') . '%'];
}

function formBusca($busca, $placeholder) {
    return '<form method="GET" class="lz-search" role="search">'
        . '<div class="lz-field flex-grow-1">'
        . '<i class="bi bi-search lz-field-icon"></i>'
        . '<input type="search" name="busca" class="lz-input" maxlength="100" placeholder="' . e($placeholder) . '" value="' . e($busca) . '" aria-label="' . e($placeholder) . '">'
        . '</div>'
        . '<button type="submit" class="lz-btn lz-btn-primary">Buscar</button>'
        . ($busca !== '' ? '<a href="?" class="lz-btn lz-btn-ghost">Limpar</a>' : '')
        . '</form>';
}

function chipCorujao() {
    return '<span class="lz-chip lz-chip-night"><i class="bi bi-moon-stars"></i> Corujão</span>';
}
