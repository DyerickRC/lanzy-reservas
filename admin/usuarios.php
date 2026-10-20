<?php
require '../includes/auth.php';
require '../config/database.php';
exigirAdmin();

$usuarios = $pdo->query(
    "SELECT u.id, u.nome, u.email, u.tipo, u.bloqueado, u.criado_em,
            COUNT(r.id) AS total_reservas,
            COALESCE(SUM(CASE WHEN r.status <> 'cancelada' THEN r.valor_total ELSE 0 END), 0) AS total_gasto
     FROM usuarios u
     LEFT JOIN reservas r ON r.usuario_id = u.id
     GROUP BY u.id
     ORDER BY u.tipo = 'admin' DESC, u.bloqueado, u.nome"
)->fetchAll();

$totalClientes = 0;
$totalBloqueados = 0;
foreach ($usuarios as $u) {
    if ($u['tipo'] === 'cliente') $totalClientes++;
    if ($u['bloqueado']) $totalBloqueados++;
}

$pageTitle = 'Clientes';
$basePath = '../';
require '../includes/header.php';
?>

<div class="container">
  <header class="lz-page-head lz-reveal">
    <span class="lz-eyebrow"><i class="bi bi-shield-lock"></i> Administração</span>
    <h1 class="mt-3">Clientes</h1>
    <p>Veja quem joga na lan house e bloqueie quem não pode mais reservar.</p>
  </header>

  <?php $abaAtiva = 'usuarios.php'; require '../includes/admin-tabs.php'; ?>

  <?php if (isset($_GET['bloqueado'])):
    $n = (int)val($_GET, 'canceladas', 0);
  ?>
    <div class="lz-alert lz-alert-success" data-autoclose>
      <i class="bi bi-person-slash"></i>
      <span>Cliente bloqueado.<?= $n ? ' ' . $n . ($n === 1 ? ' reserva futura foi cancelada.' : ' reservas futuras foram canceladas.') : '' ?></span>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['desbloqueado'])): ?>
    <div class="lz-alert lz-alert-success" data-autoclose><i class="bi bi-person-check"></i><span>Cliente desbloqueado. Ele já pode entrar e reservar de novo.</span></div>
  <?php endif; ?>

  <div class="lz-section-head">
    <div>
      <h2>Cadastrados</h2>
      <p><?= $totalClientes ?> clientes · <?= $totalBloqueados ?> bloqueado<?= $totalBloqueados === 1 ? '' : 's' ?></p>
    </div>
  </div>

  <div class="lz-panel lz-reveal" style="--i: 1">
    <div class="lz-table-wrap">
      <table class="lz-table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Cliente desde</th>
            <th>Reservas</th>
            <th>Total gasto</th>
            <th>Situação</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td>
                <div class="lz-cell">
                  <span class="lz-avatar lz-avatar-sm"><?= e(iniciais($u['nome'])) ?></span>
                  <div class="min-w-0">
                    <?= e($u['nome']) ?>
                    <small><?= e($u['email']) ?></small>
                  </div>
                </div>
              </td>
              <td><?= date('d/m/Y', strtotime($u['criado_em'])) ?></td>
              <td><?= (int)$u['total_reservas'] ?></td>
              <td><?= dinheiro($u['total_gasto']) ?></td>
              <td>
                <?php if ($u['tipo'] === 'admin'): ?>
                  <span class="lz-chip lz-chip-night"><i class="bi bi-shield-lock"></i> Admin</span>
                <?php elseif ($u['bloqueado']): ?>
                  <span class="lz-chip lz-chip-block"><i class="bi bi-person-slash"></i> Bloqueado</span>
                <?php else: ?>
                  <span class="lz-chip lz-chip-ok"><i class="bi bi-person-check"></i> Ativo</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex justify-content-end">
                  <?php if ($u['tipo'] === 'cliente'): ?>
                    <form method="POST" action="alternar_bloqueio.php"
                          <?php if (!$u['bloqueado']): ?>onsubmit="return confirm('Bloquear este cliente? Ele não vai mais conseguir entrar, e as reservas futuras dele serão canceladas.');"<?php endif; ?>>
                      <?= campoCsrf() ?>
                      <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                      <?php if ($u['bloqueado']): ?>
                        <button type="submit" class="lz-btn lz-btn-ghost lz-btn-sm"><i class="bi bi-unlock"></i> Desbloquear</button>
                      <?php else: ?>
                        <button type="submit" class="lz-btn lz-btn-danger lz-btn-sm"><i class="bi bi-slash-circle"></i> Bloquear</button>
                      <?php endif; ?>
                    </form>
                  <?php else: ?>
                    <span class="text-muted-lz small">—</span>
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
