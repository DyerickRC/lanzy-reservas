<?php
require '../includes/auth.php';
require '../config/database.php';
exigirAdmin();

$id = (int)val($_GET, 'id', val($_POST, 'id', 0));

$stmt = $pdo->prepare('SELECT * FROM estacoes WHERE id = ?');
$stmt->execute([$id]);
$estacao = $stmt->fetch();

if (!$estacao) {
    header('Location: estacoes.php');
    exit;
}

$erro = '';
$dados = $estacao;
$dados['valor_hora'] = number_format($estacao['valor_hora'], 2, ',', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'nome' => trim(val($_POST, 'nome')),
        'tipo' => val($_POST, 'tipo'),
        'marca' => trim(val($_POST, 'marca')),
        'descricao' => trim(val($_POST, 'descricao')),
        'valor_hora' => trim(val($_POST, 'valor_hora')),
        'status' => val($_POST, 'status'),
    ];
    $valorHora = str_replace(',', '.', $dados['valor_hora']);

    if ($dados['nome'] === '' || mb_strlen($dados['nome']) > 100) {
        $erro = 'Informe um nome de até 100 caracteres.';
    } elseif (!in_array($dados['tipo'], ['computador', 'video game'], true)) {
        $erro = 'Escolha um tipo válido.';
    } elseif (mb_strlen($dados['marca']) > 50) {
        $erro = 'A marca pode ter no máximo 50 caracteres.';
    } elseif (mb_strlen($dados['descricao']) > 255) {
        $erro = 'A configuração pode ter no máximo 255 caracteres.';
    } elseif (!is_numeric($valorHora) || $valorHora <= 0 || $valorHora > 9999.99) {
        $erro = 'Informe um valor por hora válido, por exemplo 15,00.';
    } elseif (!in_array($dados['status'], ['disponivel', 'manutencao', 'inativa'], true)) {
        $erro = 'Escolha uma situação válida.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE estacoes SET nome = ?, tipo = ?, marca = ?, descricao = ?, valor_hora = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([
            $dados['nome'],
            $dados['tipo'],
            $dados['marca'] !== '' ? $dados['marca'] : null,
            $dados['descricao'],
            $valorHora,
            $dados['status'],
            $id,
        ]);
        $canceladas = $dados['status'] === 'disponivel' ? 0 : cancelarReservasAtivas($pdo, 'estacao_id', $id);
        header('Location: estacoes.php?editada=1&canceladas=' . $canceladas);
        exit;
    }
}

$ativas = (int)val(contarReservasAtivasPorEstacao($pdo), $id, 0);

$pageTitle = 'Editar ' . $estacao['nome'];
$basePath = '../';
require '../includes/header.php';
?>

<div class="container">
  <header class="lz-page-head lz-reveal">
    <span class="lz-eyebrow"><i class="bi bi-shield-lock"></i> Administração</span>
    <h1 class="mt-3">Editar estação</h1>
    <p><?= e($estacao['nome']) ?></p>
  </header>

  <?php $abaAtiva = 'estacoes.php'; require '../includes/admin-tabs.php'; ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="lz-panel lz-panel-pad lz-reveal" style="--i: 1">
        <?php if ($erro): ?>
          <div class="lz-alert lz-alert-error"><i class="bi bi-exclamation-triangle"></i><span><?= e($erro) ?></span></div>
        <?php endif; ?>

        <form method="POST" class="row g-3">
          <?= campoCsrf() ?>
          <input type="hidden" name="id" value="<?= (int)$estacao['id'] ?>">

          <div class="col-md-8">
            <label class="lz-label" for="nome">Nome</label>
            <input type="text" id="nome" name="nome" class="lz-input" maxlength="100" value="<?= e($dados['nome']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="lz-label" for="tipo">Tipo</label>
            <select id="tipo" name="tipo" class="lz-input" required>
              <option value="computador" <?= $dados['tipo'] === 'computador' ? 'selected' : '' ?>>Computador</option>
              <option value="video game" <?= $dados['tipo'] === 'video game' ? 'selected' : '' ?>>Video game</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="lz-label" for="marca">Marca</label>
            <input type="text" id="marca" name="marca" class="lz-input" maxlength="50" list="marcas" placeholder="Ex.: PlayStation, Xbox, Dell" value="<?= e($dados['marca']) ?>">
            <datalist id="marcas">
              <?php foreach (marcasSugeridas() as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-3">
            <label class="lz-label" for="valor_hora">Valor/hora (R$)</label>
            <input type="text" id="valor_hora" name="valor_hora" class="lz-input" inputmode="decimal" value="<?= e($dados['valor_hora']) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="lz-label" for="status">Situação</label>
            <select id="status" name="status" class="lz-input">
              <option value="disponivel" <?= $dados['status'] === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
              <option value="manutencao" <?= $dados['status'] === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
              <option value="inativa" <?= $dados['status'] === 'inativa' ? 'selected' : '' ?>>Inativa</option>
            </select>
          </div>
          <?php if ($ativas): ?>
            <div class="col-12">
              <div class="lz-alert lz-alert-error mb-0">
                <i class="bi bi-exclamation-triangle"></i>
                <span>Esta estação tem <?= $ativas ?> <?= $ativas === 1 ? 'reserva marcada' : 'reservas marcadas' ?>. Se mudar a situação para Manutenção ou Inativa, <?= $ativas === 1 ? 'ela será cancelada' : 'elas serão canceladas' ?>.</span>
              </div>
            </div>
          <?php endif; ?>

          <div class="col-12">
            <label class="lz-label" for="descricao">Configuração</label>
            <input type="text" id="descricao" name="descricao" class="lz-input" maxlength="255" placeholder="RTX 4060, 16GB RAM, monitor 144Hz" value="<?= e($dados['descricao']) ?>">
            <p class="text-muted-lz small mt-2 mb-0">Separe por vírgulas: cada item vira uma etiqueta no card.</p>
          </div>

          <div class="col-12 d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="lz-btn lz-btn-primary"><i class="bi bi-check2"></i> Salvar alterações</button>
            <a href="estacoes.php" class="lz-btn lz-btn-ghost">Cancelar</a>
          </div>
        </form>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="lz-panel lz-panel-pad lz-reveal" style="--i: 2">
        <span class="lz-label"><i class="bi bi-info-circle"></i> Bom saber</span>
        <p class="text-muted-lz small mb-2">Mudar o valor por hora só vale para reservas novas. As reservas já feitas guardam o valor da época.</p>
        <p class="text-muted-lz small mb-0">Para consoles, a marca define o ícone do card (PlayStation, Xbox ou Nintendo).</p>
      </div>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
