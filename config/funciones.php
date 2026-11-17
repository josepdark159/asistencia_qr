<?php
require_once __DIR__ . '/conexion.php';

// Configura y abre la sesión con opciones seguras para la cookie.
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isSecure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Convierte valores a texto HTML seguro para evitar que se interpreten como etiquetas.
function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Crea y conserva en la sesión un token para proteger los formularios contra CSRF.
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Rechaza formularios sin un token CSRF válido.
function validarCsrf(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    if (!is_string($enviado) || !hash_equals(tokenCsrf(), $enviado)) {
        http_response_code(419);
        exit('La sesión del formulario venció. Regresa e inténtalo de nuevo.');
    }
}

// Redirige a la ruta indicada y detiene la ejecución actual.
function redirigir(string $ruta): never
{
    header('Location: ' . $ruta);
    exit;
}

// Exige una sesión iniciada antes de permitir el acceso.
function requiereAcceso(): void
{
    if (empty($_SESSION['usuario_id'])) {
        redirigir('login.php');
    }
}

// Exige que la sesión iniciada pertenezca a un administrador.
function requiereAdministrador(): void
{
    requiereAcceso();
    if (($_SESSION['usuario_rol'] ?? '') !== 'administrador') {
        http_response_code(403);
        exit('No tienes permiso para administrar usuarios.');
    }
}

// Crea la tabla de usuarios si falta y añade la columna de aprobación en instalaciones anteriores.
function asegurarTablaUsuarios(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL,
        usuario VARCHAR(50) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        rol ENUM('administrador', 'usuario') NOT NULL DEFAULT 'usuario',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        aprobado TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY usuario (usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $column = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'aprobado'")->fetch();
    if (!$column) {
        $pdo->exec('ALTER TABLE usuarios ADD aprobado TINYINT(1) NOT NULL DEFAULT 1 AFTER activo');
    }
}

// Comprueba que una fecha corresponda exactamente al formato válido AAAA-MM-DD.
function fechaValida(string $fecha): bool
{
    $objeto = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $objeto !== false && $objeto->format('Y-m-d') === $fecha;
}

// Devuelve los grados disponibles para los filtros y formularios del sistema.
function gradosPrimaria(): array
{
    return [
        '1.° de primaria',
        '2.° de primaria',
        '3.° de primaria',
        '4.° de primaria',
        '5.° de primaria',
        '6.° de primaria',
    ];
}

// Devuelve las secciones permitidas en primaria.
function seccionesPrimaria(): array
{
    return ['A', 'B', 'C'];
}