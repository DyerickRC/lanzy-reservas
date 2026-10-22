<?php
$paginaAtual = basename($_SERVER['PHP_SELF']);
$naAdmin = basename(dirname($_SERVER['PHP_SELF'])) === 'admin';
$logado = usuarioLogado();
$ehAdmin = val($_SESSION, 'usuario_tipo') === 'admin';
$layoutAuth = !empty($layoutAuth);

function navAtiva($condicao) {
    return $condicao ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' · Lanzy' : 'Lanzy · Reserve. Jogue. Aproveite.' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= $basePath ?>assets/css/style.css?v=9" rel="stylesheet">
</head>
<body>
<?php if (!$layoutAuth): ?>
<nav class="navbar navbar-expand-lg lz-nav sticky-top">
  <div class="container">
    <a class="lz-brand" href="<?= $basePath ?><?= $logado ? 'dashboard.php' : 'login.php' ?>">
      <span class="lz-brand-icon"><i class="bi bi-controller"></i></span>LANZY
    </a>
    <?php if ($logado): ?>
    <button class="navbar-toggler lz-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-label="Abrir menu">
      <i class="bi bi-list"></i>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav mx-auto lz-nav-links">
        <li class="nav-item">
          <a class="nav-link<?= navAtiva(!$naAdmin && in_array($paginaAtual, ['dashboard.php', 'reservar.php'], true)) ?>" href="<?= $basePath ?>dashboard.php"><i class="bi bi-grid-1x2"></i> Estações</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= navAtiva($paginaAtual === 'minhas-reservas.php') ?>" href="<?= $basePath ?>minhas-reservas.php"><i class="bi bi-calendar-check"></i> Minhas reservas</a>
        </li>
        <?php if ($ehAdmin): ?>
        <li class="nav-item">
          <a class="nav-link<?= navAtiva($naAdmin) ?>" href="<?= $basePath ?>admin/index.php"><i class="bi bi-shield-lock"></i> Painel admin</a>
        </li>
        <?php endif; ?>
      </ul>
      <div class="lz-user">
        <span class="lz-avatar"><?= e(iniciais($_SESSION['usuario_nome'])) ?></span>
        <div class="lz-user-info pe-1">
          <strong><?= e(primeiroNome($_SESSION['usuario_nome'])) ?></strong>
          <small><?= $ehAdmin ? 'Administrador' : 'Player' ?></small>
        </div>
        <a class="lz-icon-btn" href="<?= $basePath ?>logout.php" title="Sair" aria-label="Sair"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</nav>
<?php endif; ?>
<main class="lz-main">
