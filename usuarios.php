<?php
require_once __DIR__ . '/config/funciones.php';
requiereAdministrador();
$pdo = conectarBaseDatos();
$notice = '';
$noticeType = 'success';

// Procesa las acciones del formulario y verifica que la solicitud incluya un token CSRF válido.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $action = $_POST['action'] ?? '';

    // Crea una cuenta de usuario después de validar el nombre, el usuario y la contraseña.
    if ($action === 'crear') {
        $name = trim($_POST['nombre'] ?? '');
        $username = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            $notice = 'Ingresa un nombre y un usuario válido de 3 a 50 caracteres.';
            $noticeType = 'error';
        } elseif (strlen($password) < 10) {
            $notice = 'La contraseña debe tener al menos 10 caracteres.';
            $noticeType = 'error';
        } else {
            $statement = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol, aprobado) VALUES (:nombre, :usuario, :password_hash, 'usuario', 1)");
            try {
                $statement->execute([
                    'nombre' => $name,
                    'usuario' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                redirigir('usuarios.php?creado=1');
            } catch (PDOException $exception) {
                $notice = 'No se pudo crear la cuenta. Puede que ese usuario ya exista.';
                $noticeType = 'error';
            }
        }
    }

    // Activa o desactiva una cuenta sin permitir desactivar la propia ni al último administrador activo.
    if ($action === 'estado') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $active = ($_POST['activo'] ?? '') === '1' ? 1 : 0;
        if ($id && $id !== (int) $_SESSION['usuario_id']) {
            $targetQuery = $pdo->prepare('SELECT rol FROM usuarios WHERE id = :id');
            $targetQuery->execute(['id' => $id]);
            $targetRole = $targetQuery->fetchColumn();
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador' AND activo = 1")->fetchColumn();
            if (!($active === 0 && $targetRole === 'administrador' && $adminCount <= 1)) {
                $statement = $pdo->prepare('UPDATE usuarios SET activo = :activo WHERE id = :id');
                $statement->execute(['activo' => $active, 'id' => $id]);
            } else {
                $notice = 'Debe quedar al menos un administrador activo.';
                $noticeType = 'error';
            }
        }
    }

    // Confirma la identidad del administrador antes de aceptar o rechazar una solicitud pendiente.
    if ($action === 'resolver_solicitud') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $decision = $_POST['decision'] ?? '';
        $adminUsername = trim($_POST['usuario_admin'] ?? '');
        $adminPassword = $_POST['password_admin'] ?? '';
        $adminQuery = $pdo->prepare("SELECT id, password_hash FROM usuarios WHERE usuario = :usuario AND rol = 'administrador' AND activo = 1 AND aprobado = 1");
        $adminQuery->execute(['usuario' => $adminUsername]);
        $admin = $adminQuery->fetch();

        if (!$admin || (int) $admin['id'] !== (int) $_SESSION['usuario_id'] || !password_verify($adminPassword, $admin['password_hash'])) {
            $notice = 'No se pudo confirmar la cuenta administradora. Revisa tu usuario y contraseña.';
            $noticeType = 'error';
        } elseif (!$id || !in_array($decision, ['aceptar', 'rechazar'], true)) {
            $notice = 'La solicitud o la decisión no son válidas.';
            $noticeType = 'error';
        } else {
            $pendingQuery = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND rol = 'usuario' AND aprobado = 0");
            $pendingQuery->execute(['id' => $id]);
            if (!$pendingQuery->fetch()) {
                $notice = 'La solicitud ya fue resuelta o no existe.';
                $noticeType = 'error';
            } elseif ($decision === 'aceptar') {
                $statement = $pdo->prepare("UPDATE usuarios SET aprobado = 1, activo = 1 WHERE id = :id AND rol = 'usuario' AND aprobado = 0");
                $statement->execute(['id' => $id]);
                redirigir('usuarios.php?aprobado=1');
            } else {
                $statement = $pdo->prepare("DELETE FROM usuarios WHERE id = :id AND rol = 'usuario' AND aprobado = 0");
                $statement->execute(['id' => $id]);
                redirigir('usuarios.php?rechazado=1');
            }
        }
    }
}

// Recupera los mensajes que se muestran después de redirigir tras una acción exitosa.
if (isset($_GET['creado'])) {
    $notice = 'Usuario creado. Ya puede iniciar sesión.';
}
if (isset($_GET['aprobado'])) {
    $notice = 'Cuenta aprobada. El usuario ya puede iniciar sesión.';
}
if (isset($_GET['rechazado'])) {
    $notice = 'Solicitud rechazada y eliminada.';
}

// Obtiene las cuentas aprobadas y las solicitudes pendientes para mostrarlas en las tablas.
$users = $pdo->query('SELECT id, nombre, usuario, rol, activo, aprobado, creado_en FROM usuarios WHERE aprobado = 1 ORDER BY nombre')->fetchAll();
$pendingRequests = $pdo->query("SELECT id, nombre, usuario, creado_en FROM usuarios WHERE rol = 'usuario' AND aprobado = 0 ORDER BY creado_en")->fetchAll();
$activePage = 'usuarios';
$pageTitle = 'Administrar usuarios';
require __DIR__ . '/includes/header.php';
?>
<section class="split-layout">
    <form class="form-panel" method="post">
        <h2>Agregar cuenta</h2>
        <p class="panel-hint">La persona usará estas credenciales para entrar al sistema.</p>
        <?php if ($notice !== ''): ?><p class="notice <?= escapar($noticeType) ?>" role="status"><?= escapar($notice) ?></p><?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
        <input type="hidden" name="action" value="crear">
        <label>Nombre completo<input name="nombre" maxlength="100" required></label>
        <label>Usuario<input name="usuario" minlength="3" maxlength="50" required autocomplete="off"></label>
        <label>Contraseña temporal<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
        <button class="button primary" type="submit">Crear usuario</button>
    </form>

    <section class="list-panel">
        <div class="list-heading"><div><h2>Cuentas del sistema</h2><p class="panel-hint"><?= count($users) ?> usuario(s)</p></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nombre</th><th>Usuario</th><th>Permiso</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= escapar($user['nombre']) ?><?= (int) $user['id'] === (int) $_SESSION['usuario_id'] ? ' (tú)' : '' ?></td>
                        <td><?= escapar($user['usuario']) ?></td>
                        <td><?= $user['rol'] === 'administrador' ? 'Administrador' : 'Usuario' ?></td>
                        <td><span class="status-pill <?= !$user['aprobado'] ? 'pendiente' : ($user['activo'] ? 'activo' : 'inactivo') ?>"><?= !$user['aprobado'] ? 'Pendiente' : ($user['activo'] ? 'Activo' : 'Inactivo') ?></span></td>
                        <td>
                            <?php if ((int) $user['id'] !== (int) $_SESSION['usuario_id']): ?>
                                <form class="inline-form" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                                    <input type="hidden" name="action" value="estado">
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <input type="hidden" name="activo" value="<?= $user['activo'] ? '0' : '1' ?>">
                                    <button class="text-button" type="submit"><?= $user['activo'] ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <section class="requests-section" aria-labelledby="requests-title">
            <div class="list-heading"><div><h2 id="requests-title">Solicitudes pendientes</h2><p class="panel-hint"><?= count($pendingRequests) ?> solicitud(es) por revisar</p></div></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nombre</th><th>Usuario solicitado</th><th>Fecha</th><th>Decisión</th></tr></thead>
                    <tbody>
                    <?php foreach ($pendingRequests as $request): ?>
                        <tr>
                            <td><?= escapar($request['nombre']) ?></td>
                            <td><?= escapar($request['usuario']) ?></td>
                            <td><?= escapar(date('d/m/Y', strtotime($request['creado_en']))) ?></td>
                            <td>
                                <details class="approval-details">
                                    <summary>Revisar solicitud</summary>
                                    <form class="approval-form" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                                        <input type="hidden" name="action" value="resolver_solicitud">
                                        <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
                                        <label>Tu usuario<input name="usuario_admin" required autocomplete="username"></label>
                                        <label>Tu contraseña<input type="password" name="password_admin" required autocomplete="current-password"></label>
                                        <button class="button small primary" type="submit" name="decision" value="aceptar">Aceptar</button>
                                        <button class="button small reject-button" type="submit" name="decision" value="rechazar">Rechazar</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pendingRequests): ?><tr><td colspan="4" class="empty-state">No hay solicitudes pendientes.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>