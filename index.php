<?php
require_once __DIR__ . '/config/funciones.php';
requiereAcceso();
$databaseReady = false;
$databaseMessage = 'No se pudo conectar con asistencia_qr. Revisa que MySQL esté encendido en XAMPP.';
$todayStudents = [];

// Comprueba la conexión y carga el resumen de asistencia del día.
try {
    $pdo = conectarBaseDatos();
    $databaseReady = true;
    $databaseMessage = 'Conectado a la base asistencia_qr.';
    $statement = $pdo->query("SELECT al.codigo, al.nombres, al.apellidos, al.grado, al.seccion, a.estado AS asistencia_estado, a.hora FROM alumnos al LEFT JOIN asistencias a ON a.alumno_id = al.id AND a.fecha = CURDATE() WHERE al.estado = 'activo' ORDER BY al.grado, al.seccion, al.apellidos, al.nombres");
    $todayStudents = $statement->fetchAll();
} catch (PDOException $exception) {
    $databaseReady = false;
}

// Separa los alumnos que tienen una falta registrada para el resumen inferior.
$absentStudents = array_values(array_filter(
    $todayStudents,
    static fn(array $student): bool => $student['asistencia_estado'] === 'falta'
));

$activePage = 'inicio';
$pageTitle = 'Panel principal';
require __DIR__ . '/includes/header.php';
?>
<section class="connection <?= $databaseReady ? 'is-connected' : 'needs-setup' ?>" aria-live="polite">
    <span class="connection-dot" aria-hidden="true"></span>
    <div><h2><?= $databaseReady ? 'Base de datos conectada' : 'No hay conexión con la base de datos' ?></h2><p><?= escapar($databaseMessage) ?></p></div>
    <span class="connection-label"><?= $databaseReady ? 'EN LÍNEA' : 'REVISAR XAMPP' ?></span>
</section>

<section class="today-attendance" aria-labelledby="today-attendance-title">
    <div class="today-heading">
        <div><p class="eyebrow">ASISTENCIA DEL DÍA</p><h2 id="today-attendance-title">Alumnos de hoy</h2></div>
        <a class="text-link" href="asistencia.php">Ver y actualizar lista</a>
    </div>
    <div class="table-wrap today-table">
        <table>
            <thead><tr><th>Alumno</th><th>Código</th><th>Grado de primaria</th><th>Sección</th><th>Asistencia</th><th>Hora</th></tr></thead>
            <tbody>
            <?php foreach ($todayStudents as $student): ?>
                <?php $status = $student['asistencia_estado'] ?? 'sin_registro'; ?>
                <tr>
                    <td><?= escapar($student['apellidos'] . ', ' . $student['nombres']) ?></td>
                    <td><?= escapar($student['codigo']) ?></td>
                    <td><?= escapar($student['grado']) ?></td>
                    <td><?= escapar($student['seccion']) ?></td>
                    <td>
                        <?php if ($status === 'presente'): ?>
                            <span class="attendance-status attended"><span class="attendance-icon" aria-hidden="true">&#10003;</span>Presente</span>
                        <?php elseif ($status === 'tardanza'): ?>
                            <span class="attendance-status tardy"><span class="attendance-icon" aria-hidden="true">!</span>Tardanza</span>
                        <?php elseif ($status === 'falta'): ?>
                            <span class="attendance-status absent"><span class="attendance-icon" aria-hidden="true">&#10005;</span>Falta</span>
                        <?php else: ?>
                            <span class="attendance-status unrecorded"><span class="attendance-icon" aria-hidden="true">&#8212;</span>Sin registro</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $student['hora'] && $student['hora'] !== '00:00:00' ? escapar(substr($student['hora'], 0, 5)) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$todayStudents): ?><tr><td colspan="6" class="empty-state">Aún no hay alumnos activos registrados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="today-attendance absent-attendance" aria-labelledby="absent-attendance-title">
    <div class="today-heading">
        <div><p class="eyebrow">INASISTENCIAS</p><h2 id="absent-attendance-title">Alumnos que faltaron</h2></div>
        <span class="absence-count"><?= count($absentStudents) ?> falta(s)</span>
    </div>
    <div class="table-wrap today-table">
        <table>
            <thead><tr><th>Alumno</th><th>Código</th><th>Grado de primaria</th><th>Sección</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($absentStudents as $student): ?>
                <tr>
                    <td><?= escapar($student['apellidos'] . ', ' . $student['nombres']) ?></td>
                    <td><?= escapar($student['codigo']) ?></td>
                    <td><?= escapar($student['grado']) ?></td>
                    <td><?= escapar($student['seccion']) ?></td>
                    <td><span class="attendance-status absent"><span class="attendance-icon" aria-hidden="true">&#10005;</span>Falta</span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$absentStudents): ?><tr><td colspan="5" class="empty-state">No hay alumnos con falta registrada hoy.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="module-section" aria-labelledby="modules-title">
    <div class="section-heading"><div><p class="eyebrow">ACCESO RÁPIDO</p><h2 id="modules-title">Módulos</h2></div></div>
    <div class="module-grid">
        <a class="module-item module-link" href="alumnos.php"><span class="module-number">01</span><div><h3>Alumnos</h3><p>Registrar, buscar e imprimir credenciales QR.</p></div><span class="module-arrow" aria-hidden="true">→</span></a>
        <a class="module-item module-link" href="escanear.php"><span class="module-number">02</span><div><h3>Escanear QR</h3><p>Registrar la entrada desde la cámara o el token.</p></div><span class="module-arrow" aria-hidden="true">→</span></a>
        <a class="module-item module-link" href="asistencia.php"><span class="module-number">03</span><div><h3>Asistencia diaria</h3><p>Revisar la lista y actualizar estados.</p></div><span class="module-arrow" aria-hidden="true">→</span></a>
        <a class="module-item module-link" href="reportes.php"><span class="module-number">04</span><div><h3>Reportes</h3><p>Filtrar por fechas y descargar CSV.</p></div><span class="module-arrow" aria-hidden="true">→</span></a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>