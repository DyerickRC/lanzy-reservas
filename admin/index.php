<?php
require '../includes/auth.php';
require '../config/database.php';
exigirAdmin();

$reservas = $pdo->query(
    'SELECT r.*, u.nome AS usuario_nome, u.bloqueado AS usuario_bloqueado,
            e.nome AS estacao_nome, e.tipo AS estacao_tipo, e.marca AS estacao_marca
     FROM reservas r
     JOIN usuarios u ON u.id = r.usuario_id
     JOIN estacoes e ON e.id = r.estacao_id
     ORDER BY r.data_reserva DESC, r.hora_inicio DESC'
)->fetchAll();

$totalEstacoes = (int)$pdo->query("SELECT COUNT(*) FROM estacoes WHERE status <> 'inativa'")->fetchColumn();
$totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente'")->fetchColumn();

$ativas = 0;
$faturamento = 0;
foreach ($reservas as $r) {
    if (reservaAtiva($r)) $ativas++;
    if ($r['status'] !== 'cancelada') $faturamento += $r['valor_total'];
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
      <h2>Todas as reservas</h2>
      <p><?= count($reservas) ?> no total</p>
    </div>
  </div>

  <div class="lz-panel lz-reveal" style="--i: 5">
    <?php if (!$reservas): ?>
      <div class="lz-empty">
        <i class="bi bi-calendar-x"></i>
        <h3>Nenhuma reserva ainda</h3>
        <p class="mb-0">Assim que os clientes reservarem, elas aparecem aqui.</p>
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
