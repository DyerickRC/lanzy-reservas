<?php
require 'includes/auth.php';
require 'config/database.php';

if (usuarioLogado()) {
    header('Location: dashboard.php');
    exit;
}

$msgBloqueado = 'Sua conta está bloqueada. Fale com a recepção da lan house.';
$erro = isset($_GET['bloqueado']) ? $msgBloqueado : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(val($_POST, 'email'));
    $senha = val($_POST, 'senha');

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    $senhaCerta = $usuario && password_verify($senha, $usuario['senha']);

    if ($senhaCerta && $usuario['bloqueado']) {
        $erro = $msgBloqueado;
    } elseif ($senhaCerta) {
        entrarComo($usuario);
        header('Location: dashboard.php');
        exit;
    } else {
        $erro = 'E-mail ou senha incorretos.';
    }
}

$pageTitle = 'Entrar';
$basePath = '';
$layoutAuth = true;
require 'includes/header.php';
?>

<div class="lz-auth">
  <?php require 'includes/auth-showcase.php'; ?>

  <section class="lz-auth-panel">
    <div class="lz-auth-box lz-reveal" style="--i: 1">
      <a href="login.php" class="lz-brand lz-auth-mobile-brand mb-4"><span class="lz-brand-icon"><i class="bi bi-controller"></i></span>LANZY</a>
      <h2>Bem-vindo de volta</h2>
      <p class="text-muted-lz mb-4">Entre na sua conta para reservar sua estação.</p>

      <?php if ($erro): ?>
        <div class="lz-alert lz-alert-error"><i class="bi bi-exclamation-triangle"></i><span><?= e($erro) ?></span></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <?= campoCsrf() ?>
        <div class="mb-3">
          <label class="lz-label" for="email">E-mail</label>
          <div class="lz-field">
            <i class="bi bi-envelope lz-field-icon"></i>
            <input type="email" id="email" name="email" class="lz-input" placeholder="voce@email.com" value="<?= e(val($_POST, 'email')) ?>" required autofocus>
          </div>
        </div>
        <div class="mb-4">
          <label class="lz-label" for="senha">Senha</label>
          <div class="lz-field">
            <i class="bi bi-lock lz-field-icon"></i>
            <input type="password" id="senha" name="senha" class="lz-input has-toggle" placeholder="Sua senha" required>
            <button type="button" class="lz-toggle-pass" data-toggle-pass="senha" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="lz-btn lz-btn-primary lz-btn-block">Entrar <i class="bi bi-arrow-right"></i></button>
      </form>

      <div class="lz-divider">ainda não joga com a gente?</div>
      <a href="cadastro.php" class="lz-btn lz-btn-ghost lz-btn-block"><i class="bi bi-person-plus"></i> Criar minha conta</a>
    </div>
  </section>
</div>

<?php require 'includes/footer.php'; ?>
