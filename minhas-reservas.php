<?php
require 'includes/auth.php';
require 'config/database.php';
exigirLogin();

$stmt = $pdo->prepare(
    'SELECT r.*, e.nome AS estacao_nome, e.tipo AS estacao_tipo, e.marca AS estacao_marca
     FROM reservas r
     JOIN estacoes e ON e.id = r.estacao_id
     WHERE r.usuario_id = ?
     ORDER BY r.data_reserva DESC, r.hora_inicio DESC'
);
$stmt->execute([$_SESSION['usuario_id']]);
$reservas = $stmt->fetchAll();

$ativas = [];
$historico = [];
$realizadas = 0;
$investido = 0;

foreach ($reservas as $r) {
    if (reservaAtiva($r)) {
        $ativas[] = $r;
    } else {
        $historico[] = $r;
        if ($r['status'] !== 'cancelada') $realizadas++;
    }
    if ($r['status'] !== 'cancelada') $investido += $r['valor_total'];
}
$ativas = array_reverse($ativas);

function linhaReserva($r, $ativa) {
    $status = statusExibido($r);
    ?>
    <div class="lz-booking<?= $ativa ? '' : ' is-past' ?>">
      <div class="lz-date">
        <span><?= date('d', strtotime($r['data_reserva'])) ?></span>
        <small><?= mesCurto($r['data_reserva']) ?></small>
      </div>
      <span class="lz-stat-icon <?= tomEstacao($r['estacao_tipo']) ?>"><i class="bi <?= iconeEstacao($r['estacao_tipo'], $r['estacao_nome'], $r['estacao_marca']) ?>"></i></span>
      <div class="lz-booking-main min-w-0">
        <strong><?= e($r['estacao_nome']) ?></strong>
        <span><i class="bi bi-clock"></i> <?= faixaHorario($r['hora_inicio'], $r['hora_fim']) ?> · <?= duracaoTexto($r['hora_inicio'], $r['hora_fim']) ?></span>
      </div>
      <div class="lz-booking-side">
        <?php if (ehCorujao($r['hora_inicio'], $r['hora_fim'])) echo chipCorujao(); ?>
        <span class="lz-booking-total"><?= dinheiro($r['valor_total']) ?></span>
        <?= chipStatus($status) ?>
        <?php if ($ativa): ?>
          <form method="POST" action="cancelar_reserva.php" onsubmit="return confirm('Cancelar esta reserva?');">
            <?= campoCsrf() ?>
            <input type="hidden" name="reserva_id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="lz-btn lz-btn-danger lz-btn-sm"><i class="bi bi-x-lg"></i> Cancelar</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

$pageTitle = 'Minhas reservas';
$basePath = '';
require 'includes/header.php';
?>

<div class="container">
  <header class="lz-page-head d-flex flex-wrap justify-content-between align-items-end gap-3 lz-reveal">
    <div>
      <span class="lz-eyebrow"><i class="bi bi-calendar-check"></i> Sua agenda</span>
      <h1 class="mt-3">Minhas reservas</h1>
      <p>Acompanhe seus horários e cancele quando precisar.</p>
    </div>
    <a href="dashboard.php" class="lz-btn lz-btn-primary"><i class="bi bi-plus-lg"></i> Nova reserva</a>
  </header>

  <?php if (isset($_GET['sucesso'])): ?>
    <div class="lz-alert lz-alert-success" data-autoclose><i class="bi bi-check2-circle"></i><span>Reserva confirmada! Te esperamos lá.</span></div>
  <?php endif; ?>
  <?php if (isset($_GET['cancelada'])): ?>
    <div class="lz-alert lz-alert-success" data-autoclose><i class="bi bi-info-circle"></i><span>Reserva cancelada.</span></div>
  <?php endif; ?>

  <div class="lz-stats lz-stats-3 mb-5">
    <div class="lz-panel lz-stat tone-lime lz-reveal" style="--i: 1">
      <span class="lz-stat-icon"><i class="bi bi-hourglass-split"></i></span>
      <div><strong><?= count($ativas) ?></strong><span>Próximas</span></div>
    </div>
    <div class="lz-panel lz-stat tone-pc lz-reveal" style="--i: 2">
      <span class="lz-stat-icon"><i class="bi bi-trophy"></i></span>
      <div><strong><?= $realizadas ?></strong><span>Sessões jogadas</span></div>
    </div>
    <div class="lz-panel lz-stat tone-amber lz-reveal" style="--i: 3">
      <span class="lz-stat-icon"><i class="bi bi-wallet2"></i></span>
      <div><strong><?= dinheiro($investido) ?></strong><span>Total em reservas</span></div>
    </div>
  </div>

  <?php if (!$reservas): ?>
    <div class="lz-panel lz-empty">
      <i class="bi bi-joystick"></i>
      <h3>Nenhuma reserva ainda</h3>
      <p>Que tal marcar sua primeira sessão?</p>
      <a href="dashboard.php" class="lz-btn lz-btn-primary">Ver estações <i class="bi bi-arrow-right"></i></a>
    </div>
  <?php else: ?>
    <section class="mb-5">
      <div class="lz-section-head"><h2>Próximas</h2></div>
      <?php if ($ativas): ?>
        <?php foreach ($ativas as $r) linhaReserva($r, true); ?>
      <?php else: ?>
        <p class="text-muted-lz">Nenhuma reserva futura. <a href="dashboard.php">Reservar agora</a></p>
      <?php endif; ?>
    </section>

    <?php if ($historico): ?>
      <section>
        <div class="lz-section-head"><h2>Histórico</h2></div>
        <?php foreach ($historico as $r) linhaReserva($r, false); ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
