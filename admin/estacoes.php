<?php
require '../includes/auth.php';
require '../config/database.php';
exigirAdmin();

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim(val($_POST, 'nome'));
    $tipo = val($_POST, 'tipo');
    $marca = trim(val($_POST, 'marca'));
    $descricao = trim(val($_POST, 'descricao'));
    $valorHora = str_replace(',', '.', trim(val($_POST, 'valor_hora', '0')));

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erro = 'Informe um nome de até 100 caracteres.';
    } elseif (!in_array($tipo, ['computador', 'video game'], true)) {
        $erro = 'Escolha um tipo válido.';
    } elseif (mb_strlen($marca) > 50) {
        $erro = 'A marca pode ter no máximo 50 caracteres.';
    } elseif (mb_strlen($descricao) > 255) {
        $erro = 'A configuração pode ter no máximo 255 caracteres.';
    } elseif (!is_numeric($valorHora) || $valorHora <= 0 || $valorHora > 9999.99) {
        $erro = 'Informe um valor por hora válido, por exemplo 15,00.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO estacoes (nome, tipo, marca, descricao, valor_hora, status) VALUES (?, ?, ?, ?, ?, "disponivel")');
        $stmt->execute([$nome, $tipo, $marca !== '' ? $marca : null, $descricao, $valorHora]);
        header('Location: estacoes.php?criada=1');
        exit;
    }
}

$estacoes = $pdo->query(
    "SELECT e.*, COUNT(r.id) AS total_reservas
     FROM estacoes e
     LEFT JOIN reservas r ON r.estacao_id = e.id
     GROUP BY e.id
     ORDER BY e.status = 'inativa', e.tipo, e.nome"
)->fetchAll();
$ativasPorEstacao = contarReservasAtivasPorEstacao($pdo);

$canceladas = (int)val($_GET, 'canceladas', 0);
$textoCanceladas = $canceladas
    ? ' ' . $canceladas . ($canceladas === 1 ? ' reserva marcada foi cancelada.' : ' reservas marcadas foram canceladas.')
    : '';
$avisos = [
    'disponivel' => 'Estação reativada. Ela já aparece para os clientes.',
    'manutencao' => 'Estação em manutenção.' . $textoCanceladas,
    'inativa' => 'Estação desativada. O histórico de reservas foi mantido.' . $textoCanceladas,
];
$aviso = '';
if (isset($avisos[val($_GET, 'status')])) $aviso = $avisos[val($_GET, 'status')];
if (isset($_GET['editada'])) $aviso = 'Estação atualizada.' . $textoCanceladas;
if (isset($_GET['criada'])) $aviso = 'Estação cadastrada.';
if (isset($_GET['excluida'])) $aviso = 'Estação excluída.';

function avisoCancelamento($ativas, $acao) {
    if (!$ativas) return '';
    $texto = 'Esta estação tem ' . $ativas . ($ativas === 1 ? ' reserva marcada' : ' reservas marcadas')
        . '. ' . $acao . ' vai cancelá-' . ($ativas === 1 ? 'la' : 'las') . '. Continuar?';
    return ' onsubmit="return confirm(\'' . e($texto) . '\');"';
}

$pageTitle = 'Gerenciar estações';
$basePath = '../';
require '../includes/header.php';
?>

<div class="container">
  <header class="lz-page-head lz-reveal">
    <span class="lz-eyebrow"><i class="bi bi-shield-lock"></i> Administração</span>
    <h1 class="mt-3">Estações</h1>
    <p>Cadastre máquinas, edite, coloque em manutenção ou desative.</p>
  </header>

  <?php $abaAtiva = 'estacoes.php'; require '../includes/admin-tabs.php'; ?>

  <?php if ($aviso): ?>
    <div class="lz-alert lz-alert-success" data-autoclose><i class="bi bi-check2-circle"></i><span><?= e($aviso) ?></span></div>
  <?php endif; ?>
  <?php if (val($_GET, 'erro') === 'historico'): ?>
    <div class="lz-alert lz-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Essa estação já tem reservas no histórico, então não pode ser excluída. Use "Desativar": ela some do site e o histórico continua.</span></div>
  <?php endif; ?>

  <div class="lz-panel lz-panel-pad mb-5 lz-reveal" style="--i: 1">
    <h2 class="h5 mb-3"><i class="bi bi-plus-circle text-muted-lz"></i> Nova estação</h2>
    <?php if ($erro): ?>
      <div class="lz-alert lz-alert-error"><i class="bi bi-exclamation-triangle"></i><span><?= e($erro) ?></span></div>
    <?php endif; ?>
    <form method="POST" class="row g-3 align-items-end">
      <?= campoCsrf() ?>
      <div class="col-md-6 col-lg-3">
        <label class="lz-label" for="nome">Nome</label>
        <input type="text" id="nome" name="nome" class="lz-input" maxlength="100" placeholder="PC Gamer 04" required>
      </div>
      <div class="col-md-6 col-lg-2">
        <label class="lz-label" for="tipo">Tipo</label>
        <select id="tipo" name="tipo" class="lz-input" required>
          <option value="computador">Computador</option>
          <option value="video game">Video game</option>
        </select>
      </div>
      <div class="col-md-6 col-lg-2">
        <label class="lz-label" for="marca">Marca</label>
        <input type="text" id="marca" name="marca" class="lz-input" maxlength="50" list="marcas" placeholder="Opcional">
        <datalist id="marcas">
          <?php foreach (marcasSugeridas() as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-md-6 col-lg-2">
        <label class="lz-label" for="descricao">Configuração</label>
        <input type="text" id="descricao" name="descricao" class="lz-input" maxlength="255" placeholder="RTX 4060, 16GB RAM">
      </div>
      <div class="col-8 col-lg-2">
        <label class="lz-label" for="valor_hora">R$/h</label>
        <input type="text" id="valor_hora" name="valor_hora" class="lz-input" inputmode="decimal" placeholder="15,00" required>
      </div>
      <div class="col-4 col-lg-1">
        <button type="submit" class="lz-btn lz-btn-primary lz-btn-block" aria-label="Adicionar estação"><i class="bi bi-plus-lg"></i></button>
      </div>
    </form>
    <p class="text-muted-lz small mt-3 mb-0">Dica: separe a configuração por vírgulas que cada item vira uma etiqueta no card.</p>
  </div>

  <div class="lz-section-head">
    <div>
      <h2>Cadastradas</h2>
      <p><?= count($estacoes) ?> estações · estações com histórico de reservas são desativadas em vez de excluídas</p>
    </div>
  </div>

  <div class="lz-panel lz-reveal" style="--i: 2">
    <div class="lz-table-wrap">
      <table class="lz-table">
        <thead>
          <tr>
            <th>Estação</th>
            <th>Marca</th>
            <th>Valor/hora</th>
            <th>Reservas</th>
            <th>Status</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($estacoes as $est):
            $status = $est['status'];
            $ativas = (int)val($ativasPorEstacao, $est['id'], 0);
            $temHistorico = $est['total_reservas'] > 0;
          ?>
            <tr class="<?= $status === 'inativa' ? 'is-inactive' : '' ?>">
              <td>
                <div class="lz-cell">
                  <span class="lz-stat-icon <?= tomEstacao($est['tipo']) ?>"><i class="bi <?= iconeEstacao($est['tipo'], $est['nome'], $est['marca']) ?>"></i></span>
                  <div class="min-w-0">
                    <?= e($est['nome']) ?>
                    <small><?= rotuloTipo($est['tipo']) ?> · <?= e($est['descricao']) ?></small>
                  </div>
                </div>
              </td>
              <td><?= $est['marca'] ? e($est['marca']) : '<span class="text-muted-lz">—</span>' ?></td>
              <td><?= dinheiro($est['valor_hora']) ?></td>
              <td>
                <?= (int)$est['total_reservas'] ?>
                <?php if ($ativas): ?><small class="d-block text-muted-lz"><?= $ativas ?> marcada<?= $ativas === 1 ? '' : 's' ?></small><?php endif; ?>
              </td>
              <td>
                <?php if ($status === 'disponivel'): ?>
                  <span class="lz-chip lz-chip-ok"><i class="bi bi-check2-circle"></i> Disponível</span>
                <?php elseif ($status === 'manutencao'): ?>
                  <span class="lz-chip lz-chip-warn"><i class="bi bi-tools"></i> Manutenção</span>
                <?php else: ?>
                  <span class="lz-chip lz-chip-muted"><i class="bi bi-archive"></i> Inativa</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex justify-content-end gap-2">
                  <a href="editar_estacao.php?id=<?= (int)$est['id'] ?>" class="lz-btn lz-btn-ghost lz-btn-sm"><i class="bi bi-pencil"></i> Editar</a>

                  <?php if ($status === 'disponivel'): ?>
                    <form method="POST" action="alternar_status.php"<?= avisoCancelamento($ativas, 'Colocar em manutenção') ?>>
                      <?= campoCsrf() ?>
                      <input type="hidden" name="estacao_id" value="<?= (int)$est['id'] ?>">
                      <input type="hidden" name="status" value="manutencao">
                      <button type="submit" class="lz-btn lz-btn-ghost lz-btn-sm"><i class="bi bi-tools"></i> Manutenção</button>
                    </form>
                  <?php else: ?>
                    <form method="POST" action="alternar_status.php">
                      <?= campoCsrf() ?>
                      <input type="hidden" name="estacao_id" value="<?= (int)$est['id'] ?>">
                      <input type="hidden" name="status" value="disponivel">
                      <button type="submit" class="lz-btn lz-btn-ghost lz-btn-sm"><i class="bi bi-play-circle"></i> Reativar</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($temHistorico && $status !== 'inativa'): ?>
                    <form method="POST" action="alternar_status.php"<?= $ativas ? avisoCancelamento($ativas, 'Desativar') : ' onsubmit="return confirm(\'Desativar esta estação? Ela some do site, mas o histórico de reservas é mantido.\');"' ?>>
                      <?= campoCsrf() ?>
                      <input type="hidden" name="estacao_id" value="<?= (int)$est['id'] ?>">
                      <input type="hidden" name="status" value="inativa">
                      <button type="submit" class="lz-btn lz-btn-danger lz-btn-sm" title="Desativar" aria-label="Desativar"><i class="bi bi-archive"></i></button>
                    </form>
                  <?php elseif (!$temHistorico): ?>
                    <form method="POST" action="excluir_estacao.php" onsubmit="return confirm('Excluir esta estação de vez? Ela nunca teve reservas.');">
                      <?= campoCsrf() ?>
                      <input type="hidden" name="estacao_id" value="<?= (int)$est['id'] ?>">
                      <button type="submit" class="lz-btn lz-btn-danger lz-btn-sm" aria-label="Excluir"><i class="bi bi-trash3"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
