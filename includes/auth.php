<?php
date_default_timezone_set('America/Sao_Paulo');

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
session_start();

require_once __DIR__ . '/helpers.php';

// Compatível com PHP 5.6 (sem operador ??)
function val($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

function usuarioLogado() {
    return isset($_SESSION['usuario_id']);
}

function raizSite() {
    return basename(dirname($_SERVER['PHP_SELF'])) === 'admin' ? '../' : '';
}

function paginaDeAviso($titulo, $texto) {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . e($titulo) . ' · Lanzy</title></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;padding:1.5rem;'
        . 'background:#07080f;color:#eef0fa;font-family:system-ui,sans-serif;text-align:center">'
        . '<div><h1 style="font-size:1.6rem;margin:0 0 .5rem">' . e($titulo) . '</h1>'
        . '<p style="color:#8b91ad;margin:0 0 1.5rem">' . e($texto) . '</p>'
        . '<a href="' . raizSite() . 'index.php" style="color:#2ee6ff">Voltar ao início</a></div>'
        . '</body></html>';
}

set_exception_handler(function ($erro) {
    error_log('Lanzy: ' . $erro);
    http_response_code(500);
    if (ini_get('display_errors')) {
        echo '<pre>' . e($erro) . '</pre>';
        return;
    }
    paginaDeAviso('Algo deu errado', 'Tivemos um problema ao processar seu pedido. Tente novamente em instantes.');
});

function tokenCsrf() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campoCsrf() {
    return '<input type="hidden" name="csrf" value="' . tokenCsrf() . '">';
}

function entrarComo($usuario) {
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_tipo'] = $usuario['tipo'];
}

function exigirLogin() {
    global $pdo;

    if (!usuarioLogado()) {
        header('Location: ' . raizSite() . 'login.php');
        exit;
    }

    if (isset($pdo)) {
        $stmt = $pdo->prepare('SELECT bloqueado FROM usuarios WHERE id = ?');
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch();
        if (!$usuario || $usuario['bloqueado']) {
            $_SESSION = [];
            session_destroy();
            header('Location: ' . raizSite() . 'login.php?bloqueado=1');
            exit;
        }
    }
}

function exigirAdmin() {
    exigirLogin();
    if ($_SESSION['usuario_tipo'] !== 'admin') {
        header('Location: ../dashboard.php');
        exit;
    }
}

// Todo POST precisa trazer o token da sessão; senão pode ter vindo de outro site
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(tokenCsrf(), (string)val($_POST, 'csrf'))) {
    http_response_code(400);
    paginaDeAviso('Sessão expirada', 'Por segurança, recarregue a página e tente de novo.');
    exit;
}
