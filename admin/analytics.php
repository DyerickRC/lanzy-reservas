<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/analytics.php';
exigirAdmin();

$periodos = periodosAnalytics();
$periodo = val($_GET, 'periodo', '30');
if (!isset($periodos[$periodo])) $periodo = '30';

$agora = time();
$hoje = strtotime('today');
$todas = carregarReservas($pdo);
list($inicio, $inicioAnterior, $dias) = limitesPeriodo($periodo, $todas);

$atual = reservasEntre($todas, $inicio, $agora);
$anterior = $inicioAnterior !== null ? reservasEntre($todas, $inicioAnterior, $inicio - 1) : null;
$agendadas = array_values(array_filter($todas, function ($r) use ($agora) { return $r['valida'] && $r['ini'] > $agora; }));

$r = resumoReservas($atual);
$ra = $anterior !== null ? resumoReservas($anterior) : null;
$mov = horasComMovimento($atual, $inicio, $agora);
$movAnterior = $anterior !== null ? horasComMovimento($anterior, $inicioAnterior, $inicio) : null;

$estacoes = $pdo->query('SELECT * FROM estacoes ORDER BY tipo, nome')->fetchAll();
$estacoesAtivas = count(array_filter($estacoes, function ($e) { return $e['status'] !== 'inativa'; }));
$ocupacao = $mov['total'] > 0 && $estacoesAtivas ? $mov['horasUsadas'] / ($estacoesAtivas * $mov['total']) * 100 : 0;

// Clientes
$cadastros = $pdo->query("SELECT criado_em FROM usuarios WHERE tipo = 'cliente'")->fetchAll(PDO::FETCH_COLUMN);
$cadastrosTs = array_map('strtotime', $cadastros);
$contarCadastros = function ($de, $ate) use ($cadastrosTs) {
    return count(array_filter($cadastrosTs, function ($t) use ($de, $ate) { return $t >= $de && $t <= $ate; }));
};
$novos = $contarCadastros($inicio, $agora);
$novosAnterior = $inicioAnterior !== null ? $contarCadastros($inicioAnterior, $inicio - 1) : null;
$receitaAgendada = array_sum(array_column($agendadas, 'valor_total'));

// Clientes recorrentes: 2 ou mais reservas no período
$reservasPorCliente = [];
foreach ($atual as $x) {
    if ($x['valida'] && $x['usuario_tipo'] === 'cliente') {
        $reservasPorCliente[$x['usuario_id']] = val($reservasPorCliente, $x['usuario_id'], 0) + 1;
    }
}
$recorrentes = count(array_filter($reservasPorCliente, function ($n) { return $n >= 2; }));

// ---------- Séries dos gráficos ----------
$mensal = $dias > 92;
$chaves = [];
$rotulos = [];
if ($mensal) {
    for ($t = strtotime(date('Y-m-01', $inicio)); $t <= $hoje; $t = strtotime('+1 month', $t)) {
        $chaves[] = date('Y-m', $t);
        $rotulos[] = mesAbrev($t);
    }
} else {
    for ($t = $inicio; $t <= $hoje; $t = strtotime('+1 day', $t)) {
        $chaves[] = date('Y-m-d', $t);
        $rotulos[] = date('d/m', $t);
    }
}
$chaveDe = function ($ts) use ($mensal) { return $mensal ? date('Y-m', $ts) : date('Y-m-d', $ts); };

$fatPc = array_fill_keys($chaves, 0);
$fatConsole = array_fill_keys($chaves, 0);
foreach ($atual as $x) {
    if (!$x['valida']) continue;
    $k = $chaveDe($x['ini']);
    if (!isset($fatPc[$k])) continue;
    if ($x['tipo'] === 'computador') $fatPc[$k] += $x['valor_total']; else $fatConsole[$k] += $x['valor_total'];
}

$horasMov = array_fill_keys($chaves, 0);
foreach ($mov['porDia'] as $dia => $h) {
    $k = $mensal ? substr($dia, 0, 7) : $dia;
    if (isset($horasMov[$k])) $horasMov[$k] += $h;
}
$diasComMovimento = count($mov['porDia']);
$mediaAbertaDia = $diasComMovimento ? $mov['total'] / $diasComMovimento : 0;
$diaMaisLongo = $mov['porDia'] ? array_search(max($mov['porDia']), $mov['porDia']) : null;

// Novos clientes: sempre os últimos 12 meses
$mesesNovos = [];
for ($i = 11; $i >= 0; $i--) {
    $t = strtotime("-{$i} months", strtotime(date('Y-m-01')));
    $mesesNovos[date('Y-m', $t)] = ['rotulo' => mesAbrev($t), 'total' => 0];
}
foreach ($cadastrosTs as $t) {
    $k = date('Y-m', $t);
    if (isset($mesesNovos[$k])) $mesesNovos[$k]['total']++;
}

// Dia da semana e mapa de calor (média de estações em uso em cada hora)
$ocorrencias = array_fill(1, 7, 0);
for ($t = $inicio; $t <= $hoje; $t = strtotime('+1 day', $t)) $ocorrencias[(int)date('N', $t)]++;
$fatSemana = array_fill(1, 7, 0);
$calor = [];
for ($d = 1; $d <= 7; $d++) $calor[$d] = array_fill(0, 24, 0);
foreach ($atual as $x) {
    if (!$x['valida']) continue;
    $fatSemana[(int)date('N', $x['ini'])] += $x['valor_total'];
    $t = $x['ini'];
    $fim = min($x['fim'], $agora);
    while ($t < $fim) {
        $proxima = strtotime(date('Y-m-d H:00:00', $t)) + 3600;
        $c = min($fim, $proxima);
        $calor[(int)date('N', $t)][(int)date('G', $t)] += ($c - $t) / 3600;
        $t = $c;
    }
}
$maxCalor = 0;
$pico = [1, 0, 0];
for ($d = 1; $d <= 7; $d++) {
    for ($h = 0; $h < 24; $h++) {
        $calor[$d][$h] = $ocorrencias[$d] ? $calor[$d][$h] / $ocorrencias[$d] : 0;
        if ($calor[$d][$h] > $maxCalor) {
            $maxCalor = $calor[$d][$h];
            $pico = [$d, $h, $calor[$d][$h]];
        }
    }
}
$mediaSemana = [];
foreach ($fatSemana as $d => $v) $mediaSemana[$d] = $ocorrencias[$d] ? $v / $ocorrencias[$d] : 0;

// Máquinas
$porEstacao = [];
foreach ($estacoes as $e) {
    $porEstacao[$e['id']] = ['estacao' => $e, 'reservas' => 0, 'horas' => 0, 'faturamento' => 0, 'clientes' => [], 'totalGeral' => 0];
}
foreach ($todas as $x) {
    if ($x['valida'] && isset($porEstacao[$x['estacao_id']])) $porEstacao[$x['estacao_id']]['totalGeral']++;
}
foreach ($atual as $x) {
    if (!$x['valida'] || !isset($porEstacao[$x['estacao_id']])) continue;
    $p = &$porEstacao[$x['estacao_id']];
    $p['reservas']++;
    $p['horas'] += $x['horas'];
    $p['faturamento'] += $x['valor_total'];
    $p['clientes'][$x['usuario_id']] = true;
    unset($p);
}
$porEstacao = array_values(array_filter($porEstacao, function ($p) { return $p['estacao']['status'] !== 'inativa' || $p['reservas'] > 0; }));
usort($porEstacao, function ($a, $b) { return $b['faturamento'] <=> $a['faturamento']; });
$maisProcurada = null;
foreach ($porEstacao as $p) {
    if (!$maisProcurada || $p['estacao']['visualizacoes'] > $maisProcurada['estacao']['visualizacoes']) $maisProcurada = $p;
}

$porTipo = ['computador' => ['horas' => 0, 'faturamento' => 0], 'video game' => ['horas' => 0, 'faturamento' => 0]];
foreach ($porEstacao as $p) {
    $porTipo[$p['estacao']['tipo']]['horas'] += $p['horas'];
    $porTipo[$p['estacao']['tipo']]['faturamento'] += $p['faturamento'];
}

// Clientes: ranking e tempo por máquina (contas de admin ficam de fora dos rankings)
$porCliente = [];
$clienteMaquina = [];
foreach ($atual as $x) {
    if (!$x['valida'] || $x['usuario_tipo'] !== 'cliente') continue;
    $u = $x['usuario_id'];
    if (!isset($porCliente[$u])) $porCliente[$u] = ['nome' => $x['cliente'], 'email' => $x['email'], 'reservas' => 0, 'horas' => 0, 'gasto' => 0, 'ultima' => 0, 'maquinas' => []];
    $porCliente[$u]['reservas']++;
    $porCliente[$u]['horas'] += $x['horas'];
    $porCliente[$u]['gasto'] += $x['valor_total'];
    $porCliente[$u]['ultima'] = max($porCliente[$u]['ultima'], $x['ini']);
    $porCliente[$u]['maquinas'][$x['estacao']] = val($porCliente[$u]['maquinas'], $x['estacao'], 0) + $x['horas'];

    $k = $u . '-' . $x['estacao_id'];
    if (!isset($clienteMaquina[$k])) $clienteMaquina[$k] = ['cliente' => $x['cliente'], 'estacao' => $x['estacao'], 'tipo' => $x['tipo'], 'marca' => $x['marca'], 'sessoes' => 0, 'horas' => 0];
    $clienteMaquina[$k]['sessoes']++;
    $clienteMaquina[$k]['horas'] += $x['horas'];
}
usort($porCliente, function ($a, $b) { return $b['gasto'] <=> $a['gasto']; });
$topClientes = array_slice($porCliente, 0, 10);
usort($clienteMaquina, function ($a, $b) { return $b['horas'] <=> $a['horas']; });
$topClienteMaquina = array_slice($clienteMaquina, 0, 15);

// Corujão
$faixas = [
    10 => ['rotulo' => '8 a 9 horas', 'desconto' => '10%', 'sessoes' => 0, 'faturamento' => 0, 'descontoValor' => 0],
    15 => ['rotulo' => '10 a 11 horas', 'desconto' => '15%', 'sessoes' => 0, 'faturamento' => 0, 'descontoValor' => 0],
    20 => ['rotulo' => '12 horas', 'desconto' => '20%', 'sessoes' => 0, 'faturamento' => 0, 'descontoValor' => 0],
];
$corujao = ['sessoes' => 0, 'horas' => 0, 'faturamento' => 0];
foreach ($atual as $x) {
    if (!$x['valida'] || !$x['pct']) continue;
    $faixas[$x['pct']]['sessoes']++;
    $faixas[$x['pct']]['faturamento'] += $x['valor_total'];
    $faixas[$x['pct']]['descontoValor'] += $x['desconto'];
    $corujao['sessoes']++;
    $corujao['horas'] += $x['horas'];
    $corujao['faturamento'] += $x['valor_total'];
}

// ---------- Dados para os gráficos (JS) ----------
$porHoras = $porEstacao;
usort($porHoras, function ($a, $b) { return $b['horas'] <=> $a['horas']; });
$cores =['computador' => '#0fa3c2', 'video game' => '#d93a8c'];
$graficos = [
    'faturamento' => ['rotulos' => $rotulos, 'pc' => array_values($fatPc), 'console' => array_values($fatConsole)],
    'movimento' => ['rotulos' => $rotulos, 'horas' => array_map(function ($h) { return round($h, 1); }, array_values($horasMov))],
    'novos' => ['rotulos' => array_column($mesesNovos, 'rotulo'), 'valores' => array_column($mesesNovos, 'total')],
    'maquinas' => [
        'rotulos' => array_map(function ($p) { return $p['estacao']['nome']; }, $porHoras),
        'horas' => array_map(function ($p) { return round($p['horas'], 1); }, $porHoras),
        'cores' => array_map(function ($p) use ($cores) { return $cores[$p['estacao']['tipo']]; }, $porHoras),
    ],
    'semana' => ['rotulos' => array_values(diasDaSemana()), 'valores' => array_map(function ($v) { return round($v, 2); }, array_values($mediaSemana))],
];

// ---------- Componentes ----------
// $inverso: false = subir é bom; true = subir é ruim; null = neutro (nem bom nem ruim)
function blocoDelta($valor, $inverso = false) {
    if ($valor === null) return '<span class="lz-delta flat">sem base de comparação</span>';
    if (abs($valor) < 0.5) return '<span class="lz-delta flat"><i class="bi bi-dash"></i> estável</span>';
    $subiu = $valor > 0;
    $classe = $inverso === null ? 'flat' : (($inverso ? !$subiu : $subiu) ? 'good' : 'bad');
    return '<span class="lz-delta ' . $classe . '"><i class="bi bi-arrow-' . ($subiu ? 'up' : 'down') . '-right"></i> '
        . numero(abs($valor), abs($valor) < 10 ? 1 : 0) . '% <em>vs período anterior</em></span>';
}

// $delta === false esconde a comparação (período "Tudo" não tem período anterior)
function kpi($icone, $tom, $titulo, $valor, $delta, $inverso = false, $nota = '') {
    return '<div class="lz-panel lz-kpi ' . $tom . '">'
        . '<div class="lz-kpi-top"><span class="lz-stat-icon"><i class="bi ' . $icone . '"></i></span><span class="lz-kpi-title">' . $titulo . '</span></div>'
        . '<strong class="lz-kpi-value">' . $valor . '</strong>'
        . ($nota ? '<span class="lz-kpi-note">' . $nota . '</span>' : '')
        . ($delta !== false ? blocoDelta($delta, $inverso) : '')
        . '</div>';
}

$semComparacao = $ra === null;
$v = function ($chave) use ($r, $ra) { return $ra === null ? false : variacao($r[$chave], $ra[$chave]); };
$deltaMov = $semComparacao ? false : variacao($mov['total'], $movAnterior['total']);
$deltaNovos = $semComparacao ? false : variacao($novos, $novosAnterior);
$rotuloPeriodo = $periodo === 'tudo' ? 'desde ' . date('d/m/Y', $inicio) : 'de ' . date('d/m/Y', $inicio) . ' a ' . date('d/m/Y', $hoje);

$pageTitle = 'Analytics';
$basePath = '../';
$scriptsExtras = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js', '../assets/js/analytics.js?v=1'];
require '../includes/header.php';
?>

<div class="container">
  <header class="lz-page-head lz-reveal">
    <span class="lz-eyebrow"><i class="bi bi-graph-up-arrow"></i> Analytics</span>
    <h1 class="mt-3">Desempenho da lan house</h1>
    <p>Faturamento, movimento, clientes e máquinas, <?= $rotuloPeriodo ?>.</p>
  </header>

  <?php $abaAtiva = 'analytics.php'; require '../includes/admin-tabs.php'; ?>

  <div class="lz-filter-row">
    <nav class="lz-segment" aria-label="Período">
      <?php foreach ($periodos as $valor => $rotulo): ?>
        <a href="?periodo=<?= $valor ?>" class="<?= $periodo === (string)$valor ? 'active' : '' ?>"><?= $rotulo ?></a>
      <?php endforeach; ?>
    </nav>
    <a href="exportar_reservas.php?periodo=<?= $periodo ?>" class="lz-btn lz-btn-ghost lz-btn-sm"><i class="bi bi-download"></i> Exportar reservas (CSV)</a>
  </div>

  <!-- Indicadores principais -->
  <div class="lz-kpis">
    <?= kpi('bi-cash-coin', 'tone-amber', 'Faturamento', dinheiro($r['faturamento']), $v('faturamento')) ?>
    <?= kpi('bi-hourglass-split', 'tone-pc', 'Horas vendidas', horasFmt($r['horas']), $v('horas')) ?>
    <?= kpi('bi-calendar-check', 'tone-lime', 'Reservas', numero($r['reservas']), $v('reservas')) ?>
    <?= kpi('bi-receipt', 'tone-violet', 'Ticket médio', dinheiro($r['ticket']), $v('ticket')) ?>
    <?= kpi('bi-shop', 'tone-pc', 'Horas com movimento', horasFmt($mov['total']), $deltaMov, false, 'tempo com pelo menos 1 máquina em uso') ?>
    <?= kpi('bi-person-plus', 'tone-violet', 'Novos clientes', numero($novos), $deltaNovos) ?>
    <?= kpi('bi-moon-stars', 'tone-console', 'Desconto concedido', dinheiro($r['desconto']), $v('desconto'), null, 'corujão · ' . numero($r['bruto'] ? $r['desconto'] / $r['bruto'] * 100 : 0, 1) . '% do valor bruto') ?>
    <?= kpi('bi-x-circle', 'tone-console', 'Cancelamentos', numero($r['taxaCancelamento'], 1) . '%', $v('taxaCancelamento'), true, numero($r['canceladas']) . ' reservas · ' . dinheiro($r['valorCancelado']) . ' não realizados') ?>
  </div>

  <div class="lz-minis">
    <div><span>Clientes ativos</span><strong><?= numero($r['clientes']) ?></strong></div>
    <div><span>Clientes recorrentes (2+ reservas)</span><strong><?= numero($recorrentes) ?></strong></div>
    <div><span>Sessão média</span><strong><?= duracaoHoras($r['sessaoMedia']) ?></strong></div>
    <div><span>Ocupação no horário com movimento</span><strong><?= numero($ocupacao, 1) ?>%</strong></div>
    <div><span>Receita agendada (futuro)</span><strong><?= dinheiro($receitaAgendada) ?></strong></div>
  </div>

  <!-- Faturamento -->
  <section class="lz-panel lz-chart-card">
    <div class="lz-chart-head">
      <div>
        <h2>Faturamento <?= $mensal ? 'por mês' : 'por dia' ?></h2>
        <p>Receita das reservas não canceladas, separada por tipo de estação.</p>
      </div>
      <div class="lz-legend-inline">
        <span><i style="background:<?= $cores['computador'] ?>"></i> PCs · <?= dinheiro($porTipo['computador']['faturamento']) ?></span>
        <span><i style="background:<?= $cores['video game'] ?>"></i> Consoles · <?= dinheiro($porTipo['video game']['faturamento']) ?></span>
      </div>
    </div>
    <div class="lz-chart"><canvas id="grafico-faturamento" aria-label="Gráfico de faturamento por período" role="img"></canvas></div>
    <details class="lz-dados">
      <summary>Ver dados em tabela</summary>
      <div class="lz-table-wrap"><table class="lz-table lz-table-compact">
        <thead><tr><th><?= $mensal ? 'Mês' : 'Dia' ?></th><th class="text-end">PCs</th><th class="text-end">Consoles</th><th class="text-end">Total</th></tr></thead>
        <tbody>
          <?php foreach ($chaves as $i => $k): ?>
            <tr><td><?= $rotulos[$i] ?></td><td class="text-end"><?= dinheiro($fatPc[$k]) ?></td><td class="text-end"><?= dinheiro($fatConsole[$k]) ?></td><td class="text-end"><?= dinheiro($fatPc[$k] + $fatConsole[$k]) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </details>
  </section>

  <div class="row g-4 mb-4">
    <!-- Loja aberta -->
    <div class="col-xl-7">
      <section class="lz-panel lz-chart-card h-100 mb-0">
        <div class="lz-chart-head">
          <div>
            <h2>Horas com movimento</h2>
            <p>Quanto tempo a loja ficou em uso <?= $mensal ? 'por mês' : 'por dia' ?> (ao menos uma máquina ocupada).</p>
          </div>
        </div>
        <div class="lz-facts">
          <div><span>Média por dia com movimento</span><strong><?= horasFmt($mediaAbertaDia) ?></strong></div>
          <div><span>Dias com movimento</span><strong><?= numero($diasComMovimento) ?> de <?= numero($dias) ?></strong></div>
          <div><span>Dia mais longo</span><strong><?= $diaMaisLongo ? date('d/m', strtotime($diaMaisLongo)) . ' · ' . horasFmt($mov['porDia'][$diaMaisLongo]) : '—' ?></strong></div>
        </div>
        <div class="lz-chart lz-chart-sm"><canvas id="grafico-movimento" aria-label="Horas com movimento" role="img"></canvas></div>
        <details class="lz-dados">
          <summary>Ver dados em tabela</summary>
          <div class="lz-table-wrap"><table class="lz-table lz-table-compact">
            <thead><tr><th><?= $mensal ? 'Mês' : 'Dia' ?></th><th class="text-end">Horas com movimento</th></tr></thead>
            <tbody>
              <?php foreach ($chaves as $i => $k): ?>
                <tr><td><?= $rotulos[$i] ?></td><td class="text-end"><?= horasFmt($horasMov[$k]) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </details>
      </section>
    </div>

    <!-- Novos clientes -->
    <div class="col-xl-5">
      <section class="lz-panel lz-chart-card h-100 mb-0">
        <div class="lz-chart-head">
          <div>
            <h2>Novos clientes por mês</h2>
            <p>Cadastros nos últimos 12 meses · <?= numero(count($cadastrosTs)) ?> clientes no total.</p>
          </div>
        </div>
        <div class="lz-chart lz-chart-sm"><canvas id="grafico-novos" aria-label="Novos clientes por mês" role="img"></canvas></div>
        <details class="lz-dados">
          <summary>Ver dados em tabela</summary>
          <div class="lz-table-wrap"><table class="lz-table lz-table-compact">
            <thead><tr><th>Mês</th><th class="text-end">Novos clientes</th></tr></thead>
            <tbody>
              <?php foreach ($mesesNovos as $m): ?>
                <tr><td><?= $m['rotulo'] ?></td><td class="text-end"><?= $m['total'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </details>
      </section>
    </div>
  </div>

  <!-- Horário de pico -->
  <section class="lz-panel lz-chart-card">
    <div class="lz-chart-head">
      <div>
        <h2>Horário de pico</h2>
        <p>Média de estações em uso em cada hora da semana. <?= $maxCalor > 0 ? 'Pico: <strong>' . diasDaSemana()[$pico[0]] . ' às ' . str_pad($pico[1], 2, '0', STR_PAD_LEFT) . 'h</strong>, com ' . numero($pico[2], 1) . ' de ' . $estacoesAtivas . ' estações em uso.' : '' ?></p>
      </div>
      <div class="lz-scale"><span>Menos</span><i></i><span>Mais</span></div>
    </div>
    <div class="lz-table-wrap">
      <div class="lz-heatmap" role="table" aria-label="Mapa de calor de uso por dia e hora">
        <div class="lz-hm-row lz-hm-head" role="row">
          <span role="columnheader"></span>
          <?php for ($h = 0; $h < 24; $h++): ?><span role="columnheader"><?= $h % 3 === 0 ? str_pad($h, 2, '0', STR_PAD_LEFT) . 'h' : '' ?></span><?php endfor; ?>
        </div>
        <?php foreach (diasDaSemana() as $d => $nomeDia): ?>
          <div class="lz-hm-row" role="row">
            <span role="rowheader"><?= $nomeDia ?></span>
            <?php for ($h = 0; $h < 24; $h++):
              $valor = $calor[$d][$h];
              $nivel = $maxCalor > 0 ? $valor / $maxCalor : 0;
            ?>
              <span role="cell" class="lz-hm-cell" style="--n: <?= round($nivel, 3) ?>" title="<?= $nomeDia ?> <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>h: <?= numero($valor, 1) ?> estações em uso em média"></span>
            <?php endfor; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="row g-4 mb-4">
    <!-- Dia da semana -->
    <div class="col-xl-5">
      <section class="lz-panel lz-chart-card h-100 mb-0">
        <div class="lz-chart-head">
          <div>
            <h2>Faturamento médio por dia da semana</h2>
            <p>Quanto entra, em média, em cada dia da semana.</p>
          </div>
        </div>
        <div class="lz-chart lz-chart-sm"><canvas id="grafico-semana" aria-label="Faturamento médio por dia da semana" role="img"></canvas></div>
        <details class="lz-dados">
          <summary>Ver dados em tabela</summary>
          <div class="lz-table-wrap"><table class="lz-table lz-table-compact">
            <thead><tr><th>Dia</th><th class="text-end">Média</th><th class="text-end">Total no período</th></tr></thead>
            <tbody>
              <?php foreach (diasDaSemana() as $d => $nomeDia): ?>
                <tr><td><?= $nomeDia ?></td><td class="text-end"><?= dinheiro($mediaSemana[$d]) ?></td><td class="text-end"><?= dinheiro($fatSemana[$d]) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </details>
      </section>
    </div>

    <!-- Corujão -->
    <div class="col-xl-7">
      <section class="lz-panel lz-chart-card h-100 mb-0">
        <div class="lz-chart-head">
          <div>
            <h2>Corujão e descontos</h2>
            <p>Sessões de 8 horas ou mais e o desconto concedido em cada faixa.</p>
          </div>
        </div>
        <div class="lz-facts">
          <div><span>Sessões de corujão</span><strong><?= numero($corujao['sessoes']) ?></strong><em><?= numero($r['reservas'] ? $corujao['sessoes'] / $r['reservas'] * 100 : 0, 1) ?>% das reservas</em></div>
          <div><span>Faturamento do corujão</span><strong><?= dinheiro($corujao['faturamento']) ?></strong><em><?= numero($r['faturamento'] ? $corujao['faturamento'] / $r['faturamento'] * 100 : 0, 1) ?>% do total</em></div>
          <div><span>Desconto total</span><strong><?= dinheiro($r['desconto']) ?></strong><em>valor bruto <?= dinheiro($r['bruto']) ?></em></div>
        </div>
        <div class="lz-table-wrap">
          <table class="lz-table">
            <thead><tr><th>Faixa</th><th>Desconto</th><th class="text-end">Sessões</th><th class="text-end">Faturamento</th><th class="text-end">Desconto dado</th></tr></thead>
            <tbody>
              <?php foreach ($faixas as $f): ?>
                <tr>
                  <td><span class="lz-chip lz-chip-night"><i class="bi bi-moon-stars"></i> <?= $f['rotulo'] ?></span></td>
                  <td><?= $f['desconto'] ?></td>
                  <td class="text-end"><?= numero($f['sessoes']) ?></td>
                  <td class="text-end"><?= dinheiro($f['faturamento']) ?></td>
                  <td class="text-end"><?= dinheiro($f['descontoValor']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>

  <!-- Máquinas -->
  <section class="lz-panel lz-chart-card">
    <div class="lz-chart-head">
      <div>
        <h2>Máquinas</h2>
        <p>Uso no período e procura (quantas vezes a página de reserva de cada estação foi aberta).<?= $maisProcurada ? ' Mais procurada: <strong>' . e($maisProcurada['estacao']['nome']) . '</strong>.' : '' ?></p>
      </div>
      <div class="lz-legend-inline">
        <span><i style="background:<?= $cores['computador'] ?>"></i> PCs · <?= horasFmt($porTipo['computador']['horas']) ?></span>
        <span><i style="background:<?= $cores['video game'] ?>"></i> Consoles · <?= horasFmt($porTipo['video game']['horas']) ?></span>
      </div>
    </div>
    <div class="lz-chart" style="height: <?= 70 + 44 * count($porEstacao) ?>px"><canvas id="grafico-maquinas" aria-label="Horas vendidas por máquina" role="img"></canvas></div>
    <div class="lz-table-wrap mt-3">
      <table class="lz-table">
        <thead>
          <tr>
            <th>Estação</th>
            <th class="text-end">Reservas</th>
            <th class="text-end">Horas</th>
            <th class="text-end">Sessão média</th>
            <th class="text-end">Clientes</th>
            <th class="text-end">Faturamento</th>
            <th class="text-end">% do total</th>
            <th class="text-end">Procura</th>
            <th class="text-end">Conversão</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($porEstacao as $p):
            $e = $p['estacao'];
            $conversao = $e['visualizacoes'] > 0 ? min(100, $p['totalGeral'] / $e['visualizacoes'] * 100) : null;
          ?>
            <tr>
              <td>
                <div class="lz-cell">
                  <span class="lz-stat-icon <?= tomEstacao($e['tipo']) ?>"><i class="bi <?= iconeEstacao($e['tipo'], $e['nome'], $e['marca']) ?>"></i></span>
                  <div><?= e($e['nome']) ?><small><?= e($e['marca'] ?: rotuloTipo($e['tipo'])) ?><?= $e['status'] === 'inativa' ? ' · inativa' : '' ?></small></div>
                </div>
              </td>
              <td class="text-end"><?= numero($p['reservas']) ?></td>
              <td class="text-end"><?= horasFmt($p['horas']) ?></td>
              <td class="text-end"><?= $p['reservas'] ? numero($p['horas'] / $p['reservas'], 1) . ' h' : '—' ?></td>
              <td class="text-end"><?= numero(count($p['clientes'])) ?></td>
              <td class="text-end"><?= dinheiro($p['faturamento']) ?></td>
              <td class="text-end"><?= numero($r['faturamento'] ? $p['faturamento'] / $r['faturamento'] * 100 : 0, 1) ?>%</td>
              <td class="text-end"><?= numero($e['visualizacoes']) ?></td>
              <td class="text-end"><?= $conversao === null ? '—' : numero($conversao, 1) . '%' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="lz-footnote">Procura e conversão são acumuladas desde o início da contagem (todas as reservas válidas ÷ visualizações), não só deste período.</p>
  </section>

  <!-- Clientes -->
  <section class="lz-panel lz-chart-card">
    <div class="lz-chart-head">
      <div>
        <h2>Melhores clientes</h2>
        <p>Os 10 clientes que mais gastaram no período, com o tempo médio por sessão e a máquina favorita.</p>
      </div>
    </div>
    <div class="lz-table-wrap">
      <table class="lz-table">
        <thead><tr><th>#</th><th>Cliente</th><th class="text-end">Reservas</th><th class="text-end">Horas</th><th class="text-end">Tempo médio</th><th>Máquina favorita</th><th class="text-end">Gasto</th><th class="text-end">Última vez</th></tr></thead>
        <tbody>
          <?php foreach ($topClientes as $i => $c):
            arsort($c['maquinas']);
            $favorita = key($c['maquinas']);
          ?>
            <tr>
              <td class="text-muted-lz"><?= $i + 1 ?></td>
              <td><div class="lz-cell"><span class="lz-avatar lz-avatar-sm"><?= e(iniciais($c['nome'])) ?></span><div><?= e($c['nome']) ?><small><?= e($c['email']) ?></small></div></div></td>
              <td class="text-end"><?= numero($c['reservas']) ?></td>
              <td class="text-end"><?= horasFmt($c['horas']) ?></td>
              <td class="text-end"><?= numero($c['horas'] / $c['reservas'], 1) ?> h</td>
              <td><?= e($favorita) ?></td>
              <td class="text-end"><strong><?= dinheiro($c['gasto']) ?></strong></td>
              <td class="text-end"><?= date('d/m', $c['ultima']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$topClientes): ?><tr><td colspan="8" class="text-muted-lz">Nenhuma reserva no período.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="lz-panel lz-chart-card">
    <div class="lz-chart-head">
      <div>
        <h2>Tempo de cada cliente por máquina</h2>
        <p>As 15 combinações de cliente e máquina com mais horas no período.</p>
      </div>
    </div>
    <div class="lz-table-wrap">
      <table class="lz-table">
        <thead><tr><th>Cliente</th><th>Máquina</th><th class="text-end">Sessões</th><th class="text-end">Horas no total</th><th class="text-end">Média por sessão</th></tr></thead>
        <tbody>
          <?php foreach ($topClienteMaquina as $cm): ?>
            <tr>
              <td><?= e($cm['cliente']) ?></td>
              <td><div class="lz-cell"><span class="lz-stat-icon <?= tomEstacao($cm['tipo']) ?>"><i class="bi <?= iconeEstacao($cm['tipo'], $cm['estacao'], $cm['marca']) ?>"></i></span><?= e($cm['estacao']) ?></div></td>
              <td class="text-end"><?= numero($cm['sessoes']) ?></td>
              <td class="text-end"><?= horasFmt($cm['horas']) ?></td>
              <td class="text-end"><?= numero($cm['horas'] / $cm['sessoes'], 1) ?> h</td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$topClienteMaquina): ?><tr><td colspan="5" class="text-muted-lz">Nenhuma reserva no período.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script type="application/json" id="dados-analytics"><?= json_encode($graficos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

<?php require '../includes/footer.php'; ?>
