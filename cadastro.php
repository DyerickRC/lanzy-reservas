<?php
require 'includes/auth.php';
require 'config/database.php';

if (usuarioLogado()) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim(val($_POST, 'nome'));
    $email = trim(val($_POST, 'email'));
    $senha = val($_POST, 'senha');
    $confirmarSenha = val($_POST, 'confirmar_senha');

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (mb_strlen($nome) > 100) {
        $erro = 'O nome pode ter no máximo 100 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erro = 'E-mail inválido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif (strlen($senha) > 72) {
        // O bcrypt ignora o que passa de 72 bytes
        $erro = 'A senha pode ter no máximo 72 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erro = 'Já existe uma conta com este e-mail.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, "cliente")');
            $stmt->execute([$nome, $email, $hash]);

            entrarComo(['id' => $pdo->lastInsertId(), 'nome' => $nome, 'tipo' => 'cliente']);
            header('Location: dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Criar conta';
$basePath = '';
$layoutAuth = true;
require 'includes/header.php';
?>

<div class="lz-auth">
  <?php require 'includes/auth-showcase.php'; ?>

  <section class="lz-auth-panel">
    <div class="lz-auth-box lz-reveal" style="--i: 1">
      <a href="login.php" class="lz-brand lz-auth-mobile-brand mb-4"><span class="lz-brand-icon"><i class="bi bi-controller"></i></span>LANZY</a>
      <h2>Crie sua conta</h2>
      <p class="text-muted-lz mb-4">Leva menos de um minuto. Depois é só escolher a estação.</p>

      <?php if ($erro): ?>
        <div class="lz-alert lz-alert-error"><i class="bi bi-exclamation-triangle"></i><span><?= e($erro) ?></span></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <?= campoCsrf() ?>
        <div class="mb-3">
          <label class="lz-label" for="nome">Nome</label>
          <div class="lz-field">
            <i class="bi bi-person lz-field-icon"></i>
            <input type="text" id="nome" name="nome" class="lz-input" maxlength="100" placeholder="Seu nome completo" value="<?= e(val($_POST, 'nome')) ?>" required autofocus>
          </div>
        </div>
        <div class="mb-3">
          <label class="lz-label" for="email">E-mail</label>
          <div class="lz-field">
            <i class="bi bi-envelope lz-field-icon"></i>
            <input type="email" id="email" name="email" class="lz-input" maxlength="150" placeholder="voce@email.com" value="<?= e(val($_POST, 'email')) ?>" required>
          </div>
        </div>
        <div class="row g-3 mb-4">
          <div class="col-sm-6">
            <label class="lz-label" for="senha">Senha</label>
            <div class="lz-field">
              <i class="bi bi-lock lz-field-icon"></i>
              <input type="password" id="senha" name="senha" class="lz-input has-toggle" placeholder="Mín. 6 caracteres" required minlength="6">
              <button type="button" class="lz-toggle-pass" data-toggle-pass="senha" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <div class="col-sm-6">
            <label class="lz-label" for="confirmar_senha">Confirmar</label>
            <div class="lz-field">
              <i class="bi bi-shield-check lz-field-icon"></i>
              <input type="password" id="confirmar_senha" name="confirmar_senha" class="lz-input has-toggle" placeholder="Repita a senha" required minlength="6">
              <button type="button" class="lz-toggle-pass" data-toggle-pass="confirmar_senha" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
            </div>
          </div>
        </div>
        <button type="submit" class="lz-btn lz-btn-primary lz-btn-block">Criar conta <i class="bi bi-arrow-right"></i></button>
      </form>

      <div class="lz-divider">já tem conta?</div>
      <a href="login.php" class="lz-btn lz-btn-ghost lz-btn-block"><i class="bi bi-box-arrow-in-right"></i> Entrar</a>
    </div>
  </section>
</div>

<?php require 'includes/footer.php'; ?>
