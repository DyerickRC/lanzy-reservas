<?php
$abasAdmin = [
    'index.php' => ['Visão geral', 'bi-speedometer2'],
    'analytics.php' => ['Analytics', 'bi-graph-up-arrow'],
    'estacoes.php' => ['Estações', 'bi-pc-display'],
    'usuarios.php' => ['Clientes', 'bi-people'],
];
?>
<nav class="lz-tabs">
  <?php foreach ($abasAdmin as $arquivo => $aba): ?>
    <a href="<?= $arquivo ?>" class="<?= $abaAtiva === $arquivo ? 'active' : '' ?>"><i class="bi <?= $aba[1] ?>"></i> <?= $aba[0] ?></a>
  <?php endforeach; ?>
</nav>
