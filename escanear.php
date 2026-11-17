<?php
require_once __DIR__ . '/config/funciones.php';
requiereAcceso();
$pdo = conectarBaseDatos();
$notice = '';
$noticeType = 'success';
$scannedStudent = null;

// Busca al alumno identificado por el QR y registra su asistencia del día.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $qrToken = trim($_POST['qr_token'] ?? '');
    $state = $_POST['estado'] ?? 'presente';
    if (!in_array($state, ['presente', 'tardanza'], true)) {
        $state = 'presente';
    }

    $statement = $pdo->prepare("SELECT id, codigo, nombres, apellidos, grado, seccion FROM alumnos WHERE qr_token = :qr_token AND estado = 'activo'");
    $statement->execute(['qr_token' => $qrToken]);
    $scannedStudent = $statement->fetch();

    if (!$scannedStudent) {
        $notice = 'No se encontró un alumno activo con ese código QR.';
        $noticeType = 'error';
    } else {
        try {
            $statement = $pdo->prepare('INSERT INTO asistencias (alumno_id, fecha, hora, estado) VALUES (:alumno_id, CURDATE(), CURTIME(), :estado)');
            $statement->execute(['alumno_id' => $scannedStudent['id'], 'estado' => $state]);
            $notice = 'Asistencia registrada para ' . $scannedStudent['nombres'] . ' ' . $scannedStudent['apellidos'] . '.';
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $notice = 'Este alumno ya tiene asistencia registrada para hoy.';
                $noticeType = 'warning';
            } else {
                $notice = 'No se pudo guardar la asistencia. Revisa la conexión a la base de datos.';
                $noticeType = 'error';
            }
        }
    }
}

// Carga los diez registros más recientes para mostrarlos en la página.
$statement = $pdo->query("SELECT a.hora, a.estado, al.codigo, al.nombres, al.apellidos, al.grado, al.seccion FROM asistencias a INNER JOIN alumnos al ON al.id = a.alumno_id WHERE a.fecha = CURDATE() ORDER BY a.hora DESC LIMIT 10");
$recentAttendance = $statement->fetchAll();
$activePage = 'escanear';
$pageTitle = 'Escanear asistencia';
require __DIR__ . '/includes/header.php';
?>
<section class="split-layout scan-layout">
    <div class="form-panel scan-panel">
        <h2>Registrar entrada</h2>
        <p class="panel-hint">Permite el acceso a la cámara o ingresa el código QR manualmente.</p>
        <?php if ($notice !== ''): ?><p class="notice <?= escapar($noticeType) ?>" role="status"><?= escapar($notice) ?></p><?php endif; ?>
        <div id="qr-reader" class="qr-reader"></div>
        <button class="button secondary" id="start-scanner" type="button">Activar cámara</button>
        <form id="scan-form" method="post" class="scan-form">
            <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
            <label>Código QR<input id="qr-token" name="qr_token" maxlength="64" required autocomplete="off" placeholder="Token de 64 caracteres"></label>
            <label>Estado
                <select name="estado">
                    <option value="presente">Presente</option>
                    <option value="tardanza">Tardanza</option>
                </select>
            </label>
            <button class="button primary" type="submit">Registrar asistencia</button>
        </form>
        <p class="small-note">La cámara funciona en localhost o en una conexión HTTPS. Un alumno solo puede tener un registro por día.</p>
    </div>
    <section class="list-panel">
        <div class="list-heading"><div><h2>Últimos registros de hoy</h2><p class="panel-hint"><?= count($recentAttendance) ?> registro(s)</p></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Hora</th><th>Alumno</th><th>Grado de primaria</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($recentAttendance as $record): ?>
                    <tr>
                        <td><?= escapar(substr($record['hora'], 0, 5)) ?></td>
                        <td><?= escapar($record['apellidos'] . ', ' . $record['nombres']) ?></td>
                        <td><?= escapar($record['grado'] . ' ' . $record['seccion']) ?></td>
                        <td><span class="status-pill <?= escapar($record['estado']) ?>"><?= escapar(ucfirst($record['estado'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentAttendance): ?><tr><td colspan="4" class="empty-state">Aún no hay registros hoy.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="js/escanear.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>