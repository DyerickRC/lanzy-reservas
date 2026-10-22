<?php
require 'includes/auth.php';
require 'config/database.php';
exigirLogin();

$estacaoId = (int)val($_GET, 'estacao_id', 0);

$stmt = $pdo->prepare('SELECT * FROM estacoes WHERE id = ?');
$stmt->execute([$estacaoId]);
$estacao = $stmt->fetch();

if (!$estacao || $estacao['status'] !== 'disponivel') {
    header('Location: dashboard.php');
    exit;
}

// Procura: conta uma visualização por sessão e estação (o admin não conta)
if (val($_SESSION, 'usuario_tipo') !== 'admin' && empty($_SESSION['vistas'][$estacao['id']])) {
    $_SESSION['vistas'][$estacao['id']] = true;
    $pdo->prepare('UPDATE estacoes SET visualizacoes = visualizacoes + 1 WHERE id = ?')->execute([$estacao['id']]);
}

$erro = val($_SESSION, 'reserva_erro');
$antigo = val($_SESSION, 'reserva_antigo', []);
unset($_SESSION['reserva_erro'], $_SESSION['reserva_antigo']);

$stmt = $pdo->prepare(
    "SELECT data_reserva, hora_inicio, hora_fim, status FROM reservas
     WHERE estacao_id = ? AND status = 'confirmada' AND data_reserva >= ?
     ORDER BY data_reserva, hora_inicio"
);
$stmt->execute([$estacao['id'], date('Y-m-d', strtotime('-1 day'))]);
$futuras = array_values(array_filter($stmt->fetchAll(), 'reservaAtiva'));
$ocupados = array_slice($futuras, 0, 6);

$hoje = date('Y-m-d');
$tom = tomEstacao($estacao['tipo']);

$pageTitle = 'Reservar ' . $estacao['nome'];
$basePath = '';
require 'includes/header.php';
?>

<div class="container">
  <a href="dashboard.php" class="lz-back"><i class="bi bi-arrow-left"></i> Voltar para estações</a>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="lz-panel overflow-hidden <?= $tom ?> lz-reveal">
        <div class="lz-station-media lz-station-media-lg">
          <span class="lz-tag"><?= e($estacao['marca'] ?: rotuloTipo($estacao['tipo'])) ?></span>
          <span class="lz-status"><span class="dot"></span> Disponível</span>
          <i class="bi <?= iconeEstacao($estacao['tipo'], $estacao['nome'], $estacao['marca']) ?>"></i>
        </div>
        <div class="lz-panel-pad">
          <h1 class="h3 mb-3"><?= e($estacao['nome']) ?></h1>
          <div class="lz-chips">
            <?php foreach (specs($estacao['descricao']) as $spec): ?>
              <span><?= e($spec) ?></span>
            <?php endforeach; ?>
          </div>
          <ul class="lz-info-list">
            <li><span>Valor por hora</span><strong><?= dinheiro($estacao['valor_hora']) ?></strong></li>
            <li><span>Tipo</span><strong><?= rotuloTipo($estacao['tipo']) ?></strong></li>
            <?php if ($estacao['marca']): ?>
              <li><span>Marca</span><strong><?= e($estacao['marca']) ?></strong></li>
            <?php endif; ?>
          </ul>

          <span class="lz-label mt-4">Horários já ocupados</span>
          <?php if ($ocupados): ?>
            <div class="lz-slots">
              <?php foreach ($ocupados as $o): ?>
                <div class="lz-slot">
                  <span><i class="bi bi-calendar3"></i> <?= rotuloDia($o['data_reserva']) ?></span>
                  <strong><?= faixaHorario($o['hora_inicio'], $o['hora_fim']) ?></strong>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted-lz small mb-0"><i class="bi bi-stars"></i> Agenda livre, nenhum horário ocupado.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="lz-panel lz-panel-pad lz-reveal" style="--i: 1">
        <h2 class="h4 mb-1">Monte sua reserva</h2>
        <p class="text-muted-lz mb-4">Escolha o dia, a hora de início e até que horas vai jogar.</p>

        <?php if ($erro): ?>
          <div class="lz-alert lz-alert-error"><i class="bi bi-exclamation-triangle"></i><span><?= e($erro) ?></span></div>
        <?php endif; ?>

        <form method="POST" action="processa_reserva.php" id="form-reserva"
              data-valor-hora="<?= (float)$estacao['valor_hora'] ?>"
              data-agora="<?= date('Y-m-d H') ?>"
              data-ocupados="<?= e(json_encode(slotsOcupados($futuras))) ?>">
          <?= campoCsrf() ?>
          <input type="hidden" name="estacao_id" value="<?= (int)$estacao['id'] ?>">
          <input type="hidden" id="hora_inicio" name="hora_inicio" value="<?= e(val($antigo, 'hora_inicio')) ?>">

          <div class="mb-4">
            <label class="lz-label" for="data_reserva">Data</label>
            <div class="lz-field">
              <i class="bi bi-calendar3 lz-field-icon"></i>
              <input type="date" id="data_reserva" name="data_reserva" class="lz-input" min="<?= $hoje ?>" max="<?= date('Y-m-d', strtotime('+' . DIAS_ANTECEDENCIA . ' days')) ?>" value="<?= e(val($antigo, 'data_reserva', $hoje)) ?>" required>
            </div>
          </div>

          <div class="mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
              <span class="lz-label mb-0">Início</span>
              <div class="lz-legend">
                <span><i class="is-free"></i> Livre</span>
                <span><i class="is-busy"></i> Ocupado</span>
                <span><i class="is-active"></i> Escolhido</span>
              </div>
            </div>
            <div class="lz-hours" id="grade-horas" role="group" aria-label="Hora de início"></div>
          </div>

          <div class="mb-4">
            <label class="lz-label" for="hora_fim">Fim</label>
            <div class="lz-field">
              <i class="bi bi-clock-history lz-field-icon"></i>
              <select id="hora_fim" name="hora_fim" class="lz-input" data-antigo="<?= e(val($antigo, 'hora_fim')) ?>" required disabled>
                <option value="">Escolha o início primeiro</option>
              </select>
            </div>
          </div>

          <div class="lz-corujao mb-4">
            <div class="d-flex align-items-center gap-3 mb-3">
              <span class="lz-stat-icon tone-violet"><i class="bi bi-moon-stars"></i></span>
              <div>
                <strong class="lz-title d-block">Corujão</strong>
                <span class="text-muted-lz small">De 8 a 12 horas seguidas com desconto. Sem início escolhido, começa às 22:00.</span>
              </div>
            </div>
            <div class="lz-quick">
              <button type="button" data-corujao="8">8 horas <span class="lz-off">−10%</span></button>
              <button type="button" data-corujao="10">10 horas <span class="lz-off">−15%</span></button>
              <button type="button" data-corujao="12">12 horas <span class="lz-off">−20%</span></button>
            </div>
          </div>

          <div class="lz-alert lz-alert-error" id="aviso-horario" hidden><i class="bi bi-exclamation-triangle"></i><span></span></div>

          <div class="lz-summary mb-4">
            <div class="lz-summary-row"><span>Duração</span><strong id="resumo-duracao">—</strong></div>
            <div class="lz-summary-row"><span>Valor por hora</span><strong><?= dinheiro($estacao['valor_hora']) ?></strong></div>
            <div class="lz-summary-row"><span>Subtotal</span><strong id="resumo-subtotal">R$ 0,00</strong></div>
            <div class="lz-summary-row is-discount" id="linha-desconto" hidden>
              <span><i class="bi bi-moon-stars"></i> Desconto corujão (<span id="resumo-pct"></span>)</span>
              <strong id="resumo-desconto"></strong>
            </div>
            <div class="lz-summary-total">
              <span class="text-muted-lz">Total</span>
              <strong class="lz-gradient-text" id="resumo-total">R$ 0,00</strong>
            </div>
          </div>

          <button type="submit" class="lz-btn lz-btn-primary lz-btn-block"><i class="bi bi-check2-circle"></i> Confirmar reserva</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
