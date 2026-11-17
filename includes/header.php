<?php
// Usa valores predeterminados si la página que incluye esta plantilla no los definió.
$activePage = $activePage ?? '';
$pageTitle = $pageTitle ?? 'Sistema de asistencia';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapar($pageTitle) ?> | Fe y Alegría N.° 41</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
    <!-- Encabezado compartido: navegación principal, cuenta activa y cierre de sesión. -->
    <header class="topbar">
        <a class="brand" href="index.php">
            <img class="brand-mark" src="img/escudo-fe-alegria.svg" alt="">
            <span>Fe y Alegría <span class="brand-number">N.° 41</span></span>
        </a>
        <nav class="main-nav" aria-label="Navegación principal">
            <a class="<?= $activePage === 'inicio' ? 'active' : '' ?>" href="index.php">Inicio</a>
            <a class="<?= $activePage === 'alumnos' ? 'active' : '' ?>" href="alumnos.php">Alumnos</a>
            <a class="<?= $activePage === 'escanear' ? 'active' : '' ?>" href="escanear.php">Escanear QR</a>
            <a class="<?= $activePage === 'asistencia' ? 'active' : '' ?>" href="asistencia.php">Asistencia</a>
            <a class="<?= $activePage === 'reportes' ? 'active' : '' ?>" href="reportes.php">Reportes</a>
            <?php if (($_SESSION['usuario_rol'] ?? '') === 'administrador'): ?>
                <a class="<?= $activePage === 'usuarios' ? 'active' : '' ?>" href="usuarios.php">Usuarios</a>
            <?php endif; ?>
        </nav>
        <div class="nav-account">
            <span><?= escapar($_SESSION['usuario_nombre'] ?? '') ?></span>
            <form action="logout.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                <button class="logout-button" type="submit">Salir</button>
            </form>
        </div>
    </header>
    <main class="page-shell app-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">FE Y ALEGRÍA N.° 41</p>
                <h1><?= escapar($pageTitle) ?></h1>
            </div>
            <span class="today"><?= escapar(date('d/m/Y')) ?></span>
        </div>