<?php
require '../includes/auth.php';
require '../config/database.php';
exigirAdmin();

define('LIMITE_SEM_BUSCA', 100);

list($busca, $termo) = termoBusca();

// Indicadores do topo: sempre sobre todas as reservas
$ativas = 0;
$faturamento = 0;
foreach ($pdo->query('SELECT data_reserva, hora_inicio, hora_fim, valor_total, status FROM reservas') as $r) {
    if (reservaAtiva($r)) $ativas++;
    if ($r['status'] !== 'cancelada') $faturamento += $r['valor_total'];
}
$totalReservas = (int)$pdo->query('SELECT COUNT(*) FROM reservas')->fetchColumn();
$totalEstacoes = (int)$pdo->query("SELECT COUNT(*) FROM estacoes WHERE status <> 'inativa'")->fetchColumn();
$totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente'")->fetchColumn();

// Lista: com busca, todas as reservas das pessoas encontradas; sem busca, as mais recentes
$sql = 'SELECT r.*, u.nome AS usuario_nome, u.email AS usuario_email, u.bloqueado AS usuario_bloqueado,
               e.nome AS estacao_nome, e.tipo AS estacao_tipo, e.marca AS estacao_marca
        FROM reservas r
        JOIN usuarios u ON u.id = r.usuario_id
        JOIN estacoes e ON e.id = r.estacao_id';
$params = [];
if ($busca !== '') {
    $sql .= ' WHERE u.nome LIKE ? OR u.email LIKE ?';
    $params = [$termo, $termo];
}
$sql .= ' ORDER BY r.data_reserva DESC, r.hora_inicio DESC';
if ($busca === '') $sql .= ' LIMIT ' . LIMITE_SEM_BUSCA;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservas = $stmt->fetchAll();

// Pessoas encontradas pela busca, com um resumo de cada uma
$encontrados = [];
if ($busca !== '') {
    $stmt = $pdo->prepare('SELECT id, nome, email, tipo, bloqueado, criado_em FROM usuarios WHERE nome LIKE ? OR email LIKE ? ORDER BY nome');
    $stmt->execute([$termo, $termo]);
    foreach ($stmt->fetchAll() as $u) {
        $encontrados[$u['id']] = $u + ['reservas' => 0, 'ativas' => 0, 'canceladas' => 0, 'horas' => 0, 'gasto' => 0];
    }
    foreach ($reservas as $r) {
        $p = &$encontrados[$r['usuario_id']];
        $p['reservas']++;
        if (reservaAtiva($r)) $p['ativas']++;
        if ($r['status'] === 'cancelada') {
            $p['canceladas']++;
        } else {
            $p['horas'] += horasEntre($r['hora_inicio'], $r['hora_fim']);
            $p['gasto'] += $r['valor_total'];
        }
        unset($p);
    }
}

$pageTitle = 'Painel admin';
$basePath = '../';
require '../includes/header.php';
?>

<div class="container">
  <header class="lz-page-head lz-reveal">
    <span class="lz-eyebrow"><i class="bi bi-shield-lock"></i> Administração</span>
    <h1 class="mt-3">Painel da lan house</h1>
    <p>Visão geral das reservas e do movimento.</p>
  </header>

  <?php $abaAtiva = 'index.php'; require '../includes/admin-tabs.php'; ?>

  <?php if (isset($_GET['cancelada'])): ?>
    <div class="lz-alert lz-alert-success" data-autoclose><i class="bi bi-check2-circle"></i><span>Reserva cancelada. O horário ficou livre de novo.</span></div>
  <?php endif; ?>

  <div class="lz-stats mb-5">
    <div class="lz-panel lz-stat tone-pc lz-reveal" style="--i: 1">
      <span class="lz-stat-icon"><i class="bi bi-hdd-stack"></i></span>
      <div><strong><?= $totalEstacoes ?></strong><span>Estações</span></div>
    </div>
    <div class="lz-panel lz-stat tone-lime lz-reveal" style="--i: 2">
      <span class="lz-stat-icon"><i class="bi bi-calendar-check"></i></span>
      <div><strong><?= $ativas ?></strong><span>Reservas ativas</span></div>
    </div>
    <div class="lz-panel lz-stat tone-violet lz-reveal" style="--i: 3">
      <span class="lz-stat-icon"><i class="bi bi-people"></i></span>
      <div><strong><?= $totalClientes ?></strong><span>Clientes</span></div>
    </div>
    <div class="lz-panel lz-stat tone-amber lz-reveal" style="--i: 4">
      <span class="lz-stat-icon"><i class="bi bi-cash-coin"></i></span>
      <div><strong><?= dinheiro($faturamento) ?></strong><span>Faturamento</span></div>
    </div>
  </div>

  <div class="lz-section-head">
    <div>
      <h2><?= $busca !== '' ? 'Reservas encontradas' : 'Reservas' ?></h2>
      <p>
        <?php if ($busca !== ''): ?>
          <?= count($reservas) ?> <?= count($reservas) === 1 ? 'reserva' : 'reservas' ?> de <?= count($encontrados) ?> <?= count($encontrados) === 1 ? 'pessoa' : 'pessoas' ?> para "<?= e($busca) ?>"
        <?php else: ?>
          <?= $totalReservas > LIMITE_SEM_BUSCA ? 'Mostrando as ' . LIMITE_SEM_BUSCA . ' mais recentes de ' . $totalReservas . '. Busque um cliente para ver todas as reservas dele.' : $totalReservas . ' no total' ?>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <?= formBusca($busca, 'Buscar cliente por nome ou e-mail') ?>

  <?php foreach ($encontrados as $p): ?>
    <div class="lz-panel lz-person lz-reveal">
      <div class="lz-cell">
        <span class="lz-avatar"><?= e(iniciais($p['nome'])) ?></span>
        <div>
          <strong><?= e($p['nome']) ?></strong>
          <small><?= e($p['email']) ?> · cliente desde <?= date('d/m/Y', strtotime($p['criado_em'])) ?></small>
        </div>
      </div>
      <div class="lz-person-stats">
        <div><span>Reservas</span><strong><?= $p['reservas'] ?></strong></div>
        <div><span>Ativas</span><strong><?= $p['ativas'] ?></strong></div>
        <div><span>Canceladas</span><strong><?= $p['canceladas'] ?></strong></div>
        <div><span>Horas jogadas</span><strong><?= round($p['horas']) ?> h</strong></div>
        <div><span>Total gasto</span><strong><?= dinheiro($p['gasto']) ?></strong></div>
      </div>
      <?php if ($p['tipo'] === 'admin'): ?>
        <span class="lz-chip lz-chip-night"><i class="bi bi-shield-lock"></i> Admin</span>
      <?php elseif ($p['bloqueado']): ?>
        <span class="lz-chip lz-chip-block"><i class="bi bi-person-slash"></i> Bloqueado</span>
      <?php else: ?>
        <span class="lz-chip lz-chip-ok"><i class="bi bi-person-check"></i> Ativo</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="lz-panel lz-reveal" style="--i: 5">
    <?php if (!$reservas): ?>
      <div class="lz-empty">
        <?php if ($busca !== ''): ?>
          <i class="bi bi-search"></i>
          <h3><?= $encontrados ? 'Essa pessoa ainda não fez reservas' : 'Ninguém encontrado' ?></h3>
          <p class="mb-0"><?= $encontrados ? 'O cadastro existe, mas não há nenhuma reserva.' : 'Confira o nome ou tente parte dele, como só o sobrenome.' ?></p>
        <?php else: ?>
          <i class="bi bi-calendar-x"></i>
          <h3>Nenhuma reserva ainda</h3>
          <p class="mb-0">Assim que os clientes reservarem, elas aparecem aqui.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="lz-table-wrap">
        <table class="lz-table">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Estação</th>
              <th>Data</th>
              <th>Horário</th>
              <th>Valor</th>
              <th>Status</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reservas as $r): ?>
              <tr>
                <td>
                  <div class="lz-cell">
                    <span class="lz-avatar lz-avatar-sm"><?= e(iniciais($r['usuario_nome'])) ?></span>
                    <div>
                      <?= e($r['usuario_nome']) ?>
                      <?php if ($r['usuario_bloqueado']): ?><small class="text-danger">Bloqueado</small><?php endif; ?>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="lz-cell">
                    <span class="lz-stat-icon <?= tomEstacao($r['estacao_tipo']) ?>"><i class="bi <?= iconeEstacao($r['estacao_tipo'], $r['estacao_nome'], $r['estacao_marca']) ?>"></i></span>
                    <div>
                      <?= e($r['estacao_nome']) ?>
                      <small><?= e($r['estacao_marca'] ?: rotuloTipo($r['estacao_tipo'])) ?></small>
                    </div>
                  </div>
                </td>
                <td><?= date('d/m/Y', strtotime($r['data_reserva'])) ?></td>
                <td>
                  <?= faixaHorario($r['hora_inicio'], $r['hora_fim']) ?>
                  <?php if (ehCorujao($r['hora_inicio'], $r['hora_fim'])): ?><div class="mt-1"><?= chipCorujao() ?></div><?php endif; ?>
                </td>
                <td><?= dinheiro($r['valor_total']) ?></td>
                <td><?= chipStatus(statusExibido($r)) ?></td>
                <td class="text-end">
                  <?php if (reservaAtiva($r)): ?>
                    <form method="POST" action="cancelar_reserva.php" onsubmit="return confirm('Cancelar esta reserva? O cliente perde o horário.');">
                      <?= campoCsrf() ?>
                      <input type="hidden" name="reserva_id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="busca" value="<?= e($busca) ?>">
                      <button type="submit" class="lz-btn lz-btn-danger lz-btn-sm"><i class="bi bi-x-lg"></i> Cancelar</button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted-lz small">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
