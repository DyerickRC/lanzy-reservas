<?php
require 'includes/auth.php';
header('Location: ' . (usuarioLogado() ? 'dashboard.php' : 'login.php'));
exit;
