<?php
$resumoAuth = $pdo->query(
    "SELECT SUM(tipo = 'computador') AS pcs,
            SUM(tipo = 'video game') AS consoles,
            MIN(valor_hora) AS menor_valor
     FROM estacoes WHERE status = 'disponivel'"
)->fetch();
$destaques = $pdo->query("SELECT * FROM estacoes WHERE status = 'disponivel' ORDER BY valor_hora DESC LIMIT 3")->fetchAll();
?>
<section class="lz-auth-showcase">
  <a href="login.php" class="lz-brand"><span class="lz-brand-icon"><i class="bi bi-controller"></i></span>LANZY</a>

  <div class="lz-reveal">
    <span class="lz-eyebrow"><span class="lz-pulse"></span> Estações online agora</span>
    <h1 class="lz-display mt-4 mb-3">Sua próxima <span class="lz-gradient-text">partida</span> começa aqui.</h1>
    <p class="lz-lead">Reserve PCs gamer e consoles em poucos cliques. Escolha a máquina, marque o horário e só chegue pra jogar.</p>

    <div class="lz-showcase-stats mt-4">
      <div><strong><?= (int)$resumoAuth['pcs'] ?></strong><span>PCs gamer</span></div>
      <div><strong><?= (int)$resumoAuth['consoles'] ?></strong><span>Consoles</span></div>
      <div><strong><?= $resumoAuth['menor_valor'] !== null ? dinheiro($resumoAuth['menor_valor']) : '—' ?></strong><span>A partir de / hora</span></div>
    </div>
  </div>

  <div class="lz-showcase-cards">
    <?php foreach ($destaques as $i => $d): ?>
      <div class="lz-mini <?= tomEstacao($d['tipo']) ?>" style="--i: <?= $i ?>">
        <span class="lz-mini-icon"><i class="bi <?= iconeEstacao($d['tipo'], $d['nome'], $d['marca']) ?>"></i></span>
        <div class="flex-grow-1 min-w-0">
          <strong><?= e($d['nome']) ?></strong>
          <small><?= e(implode(' · ', array_slice(specs($d['descricao']), 0, 2))) ?></small>
        </div>
        <span class="lz-mini-price"><?= dinheiro($d['valor_hora']) ?>/h</span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
