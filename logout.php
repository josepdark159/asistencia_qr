<?php
require_once __DIR__ . '/config/funciones.php';

// Solo permite cerrar sesión mediante un formulario POST protegido con CSRF.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php');
}

validarCsrf();
// Limpia los datos de sesión, invalida su cookie y vuelve al inicio de sesión.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();
redirigir('login.php');