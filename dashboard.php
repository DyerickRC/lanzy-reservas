<?php
require 'includes/auth.php';
require 'config/database.php';
exigirLogin();

$filtro = val($_GET, 'tipo', 'todos');
if (!in_array($filtro, ['todos', 'computador', 'video game'], true)) {
    $filtro = 'todos';
}

$sql = "SELECT * FROM estacoes WHERE status <> 'inativa'";
$params = [];
if ($filtro !== 'todos') {
    $sql .= ' AND tipo = ?';
    $params[] = $filtro;
}
$sql .= " ORDER BY status = 'disponivel' DESC, tipo, nome";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$estacoes = $stmt->fetchAll();

$resumo = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(tipo = 'computador') AS pcs,
            SUM(tipo = 'video game') AS consoles
     FROM estacoes WHERE status <> 'inativa'"
)->fetch();

// Estações com alguém jogando neste momento => horário em que liberam
$stmt = $pdo->prepare("SELECT estacao_id, data_reserva, hora_inicio, hora_fim FROM reservas WHERE status = 'confirmada' AND data_reserva >= ?");
$stmt->execute([date('Y-m-d', strtotime('-1 day'))]);
$emUso = [];
foreach ($stmt->fetchAll() as $r) {
    list($ini, $fim) = intervaloReserva($r['data_reserva'], $r['hora_inicio'], $r['hora_fim']);
    if ($ini <= time() && time() < $fim) {
        $emUso[$r['estacao_id']] = $fim;
    }
}

$idsDisponiveis = $pdo->query("SELECT id FROM estacoes WHERE status = 'disponivel'")->fetchAll(PDO::FETCH_COLUMN);
$livresAgora = count(array_diff($idsDisponiveis, array_keys($emUso)));

$stmt = $pdo->prepare(
    "SELECT r.*, e.nome AS estacao_nome, e.tipo AS estacao_tipo, e.marca AS estacao_marca
     FROM reservas r
     JOIN estacoes e ON e.id = r.estacao_id
     WHERE r.usuario_id = ? AND r.status = 'confirmada' AND r.data_reserva >= ?
     ORDER BY r.data_reserva, r.hora_inicio"
);
$stmt->execute([$_SESSION['usuario_id'], date('Y-m-d', strtotime('-1 day'))]);
$minhasAtivas = array_values(array_filter($stmt->fetchAll(), 'reservaAtiva'));
$proxima = $minhasAtivas ? $minhasAtivas[0] : null;

$filtros = [
    'todos' => ['Todas', 'bi-grid', (int)$resumo['total']],
    'computador' => ['PCs', 'bi-pc-display', (int)$resumo['pcs']],
    'video game' => ['Consoles', 'bi-controller', (int)$resumo['consoles']],
];

$pageTitle = 'Estações';
$basePath = '';
require 'includes/header.php';
?>

<section class="lz-hero">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-7 lz-reveal">
        <span class="lz-eyebrow"><span class="lz-pulse"></span> <?= $livresAgora ?> <?= $livresAgora === 1 ? 'estação livre' : 'estações livres' ?> agora</span>
        <h1 class="mt-3 mb-2">Fala, <span class="lz-gradient-text"><?= e(primeiroNome($_SESSION['usuario_nome'])) ?></span>! Bora jogar?</h1>
        <p class="lz-lead mb-0">Escolha um PC gamer ou console, marque seu horário e chegue só pra jogar.</p>
      </div>
      <div class="col-lg-5 lz-reveal" style="--i: 1">
        <div class="lz-next">
          <?php if ($proxima): ?>
            <span class="lz-label">Sua próxima reserva</span>
            <div class="d-flex align-items-center gap-3">
              <span class="lz-stat-icon <?= tomEstacao($proxima['estacao_tipo']) ?>"><i class="bi <?= iconeEstacao($proxima['estacao_tipo'], $proxima['estacao_nome'], $proxima['estacao_marca']) ?>"></i></span>
              <div class="min-w-0">
                <strong class="lz-title d-block fs-5"><?= e($proxima['estacao_nome']) ?></strong>
                <span class="text-muted-lz small">
                  <i class="bi bi-calendar3"></i> <?= rotuloDia($proxima['data_reserva']) ?>
                  · <?= faixaHorario($proxima['hora_inicio'], $proxima['hora_fim']) ?>
                </span>
              </div>
            </div>
            <a href="minhas-reservas.php" class="lz-btn lz-btn-ghost lz-btn-sm mt-3">Ver minhas reservas <i class="bi bi-arrow-right"></i></a>
          <?php else: ?>
            <span class="lz-label">Sua agenda</span>
            <strong class="lz-title d-block fs-5 mb-1">Nenhuma reserva marcada</strong>
            <span class="text-muted-lz small">Escolha uma estação abaixo e garanta seu horário antes que alguém pegue.</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="lz-stats mt-4">
      <div class="lz-panel lz-stat tone-lime lz-reveal" style="--i: 2">
        <span class="lz-stat-icon"><i class="bi bi-lightning-charge"></i></span>
        <div><strong><?= $livresAgora ?></strong><span>Livres agora</span></div>
      </div>
      <div class="lz-panel lz-stat tone-pc lz-reveal" style="--i: 3">
        <span class="lz-stat-icon"><i class="bi bi-pc-display"></i></span>
        <div><strong><?= (int)$resumo['pcs'] ?></strong><span>PCs gamer</span></div>
      </div>
      <div class="lz-panel lz-stat tone-console lz-reveal" style="--i: 4">
        <span class="lz-stat-icon"><i class="bi bi-controller"></i></span>
        <div><strong><?= (int)$resumo['consoles'] ?></strong><span>Consoles</span></div>
      </div>
      <div class="lz-panel lz-stat tone-violet lz-reveal" style="--i: 5">
        <span class="lz-stat-icon"><i class="bi bi-calendar-check"></i></span>
        <div><strong><?= count($minhasAtivas) ?></strong><span>Suas reservas ativas</span></div>
      </div>
    </div>
  </div>
</section>

<section class="container pt-4">
  <div class="lz-section-head">
    <div>
      <h2>Escolha sua estação</h2>
      <p>Preço por hora, sem taxa escondida.</p>
    </div>
    <nav class="lz-segment" aria-label="Filtrar estações">
      <?php foreach ($filtros as $valor => $f): ?>
        <a href="?tipo=<?= urlencode($valor) ?>" class="<?= $filtro === $valor ? 'active' : '' ?>">
          <i class="bi <?= $f[1] ?>"></i> <?= $f[0] ?> <span class="lz-count"><?= $f[2] ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <?php if (!$estacoes): ?>
    <div class="lz-panel lz-empty">
      <i class="bi bi-joystick"></i>
      <h3>Nenhuma estação por aqui</h3>
      <p class="mb-0">Tente outro filtro.</p>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <?php foreach ($estacoes as $i => $est):
      $disponivel = $est['status'] === 'disponivel';
      $liberaAs = isset($emUso[$est['id']]) ? $emUso[$est['id']] : null;
      if (!$disponivel) {
          list($classeStatus, $textoStatus) = [' off', 'Manutenção'];
      } elseif ($liberaAs) {
          list($classeStatus, $textoStatus) = [' busy', 'Em uso até ' . date('H:i', $liberaAs)];
      } else {
          list($classeStatus, $textoStatus) = ['', 'Disponível'];
      }
    ?>
      <div class="col-md-6 col-xl-4 lz-reveal" style="--i: <?= $i ?>">
        <article class="lz-station <?= tomEstacao($est['tipo']) ?><?= $disponivel ? '' : ' is-off' ?>">
          <div class="lz-station-media">
            <span class="lz-tag"><?= e($est['marca'] ?: rotuloTipo($est['tipo'])) ?></span>
            <span class="lz-status<?= $classeStatus ?>"><span class="dot"></span> <?= $textoStatus ?></span>
            <i class="bi <?= iconeEstacao($est['tipo'], $est['nome'], $est['marca']) ?>"></i>
          </div>
          <div class="lz-station-body">
            <h3><?= e($est['nome']) ?></h3>
            <div class="lz-chips">
              <?php foreach (specs($est['descricao']) as $spec): ?>
                <span><?= e($spec) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="lz-station-foot">
              <div class="lz-price">
                <small>por hora</small>
                <strong><?= dinheiro($est['valor_hora']) ?></strong>
              </div>
              <?php if ($disponivel): ?>
                <a href="reservar.php?estacao_id=<?= (int)$est['id'] ?>" class="lz-btn lz-btn-primary lz-btn-sm">Reservar <i class="bi bi-arrow-right"></i></a>
              <?php else: ?>
                <span class="lz-btn lz-btn-ghost lz-btn-sm is-disabled"><i class="bi bi-tools"></i> Indisponível</span>
              <?php endif; ?>
            </div>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
