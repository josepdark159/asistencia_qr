<?php
require_once __DIR__ . '/config/funciones.php';
requiereAcceso();
$pdo = conectarBaseDatos();
$date = $_GET['fecha'] ?? date('Y-m-d');
// Usa la fecha actual si el valor recibido no tiene un formato válido.
if (!is_string($date) || !fechaValida($date)) {
    $date = date('Y-m-d');
}

// Valida y guarda la asistencia diaria, actualizando el registro si ya existía.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $studentId = filter_var($_POST['alumno_id'] ?? null, FILTER_VALIDATE_INT);
    $postedDate = $_POST['fecha'] ?? '';
    $state = $_POST['estado'] ?? '';
    if ($studentId && is_string($postedDate) && fechaValida($postedDate) && in_array($state, ['presente', 'tardanza', 'falta'], true)) {
        $time = $state === 'falta' ? "'00:00:00'" : 'CURTIME()';
        $sql = "INSERT INTO asistencias (alumno_id, fecha, hora, estado) VALUES (:alumno_id, :fecha, $time, :estado) ON DUPLICATE KEY UPDATE hora = $time, estado = VALUES(estado)";
        $statement = $pdo->prepare($sql);
        $statement->execute(['alumno_id' => $studentId, 'fecha' => $postedDate, 'estado' => $state]);
        redirigir('asistencia.php?fecha=' . rawurlencode($postedDate) . '&actualizado=1');
    }
    redirigir('asistencia.php?fecha=' . rawurlencode($date) . '&error=1');
}

// Prepara los filtros y consulta los alumnos activos junto con su asistencia del día.
$grade = trim($_GET['grado'] ?? '');
$section = trim($_GET['seccion'] ?? '');
if (!in_array($section, seccionesPrimaria(), true)) {
    $section = '';
}
$filters = ['fecha' => $date];
$where = "al.estado = 'activo'";
if ($grade !== '') {
    $where .= ' AND al.grado = :grado';
    $filters['grado'] = $grade;
}
if ($section !== '') {
    $where .= ' AND al.seccion = :seccion';
    $filters['seccion'] = $section;
}
$statement = $pdo->prepare("SELECT al.id, al.codigo, al.nombres, al.apellidos, al.grado, al.seccion, a.estado AS asistencia_estado, a.hora FROM alumnos al LEFT JOIN asistencias a ON a.alumno_id = al.id AND a.fecha = :fecha WHERE $where ORDER BY al.grado, al.seccion, al.apellidos, al.nombres");
$statement->execute($filters);
$students = $statement->fetchAll();
$grades = gradosPrimaria();
$sections = seccionesPrimaria();

$activePage = 'asistencia';
$pageTitle = 'Asistencia diaria';
require __DIR__ . '/includes/header.php';
?>
<?php if (isset($_GET['actualizado'])): ?><p class="notice success" role="status">Registro de asistencia actualizado.</p><?php endif; ?>
<?php if (isset($_GET['error'])): ?><p class="notice error" role="alert">No se pudo guardar. Revisa los datos e inténtalo de nuevo.</p><?php endif; ?>
<section class="list-panel">
    <div class="list-heading attendance-heading">
        <div><h2>Lista del día</h2><p class="panel-hint">Marca presente, tardanza o falta. Guardar actualiza el registro de esa fecha.</p></div>
        <form class="search-form" method="get">
            <label>Fecha<input type="date" name="fecha" value="<?= escapar($date) ?>"></label>
            <label>Grado de primaria<select name="grado"><option value="">Todos</option><?php foreach ($grades as $item): ?><option value="<?= escapar($item) ?>" <?= $grade === $item ? 'selected' : '' ?>><?= escapar($item) ?></option><?php endforeach; ?></select></label>
            <label>Sección<select name="seccion"><option value="">Todas</option><?php foreach ($sections as $item): ?><option value="<?= escapar($item) ?>" <?= $section === $item ? 'selected' : '' ?>><?= escapar($item) ?></option><?php endforeach; ?></select></label>
            <button class="button secondary" type="submit">Filtrar</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Código</th><th>Alumno</th><th>Grado de primaria/sección</th><th>Estado del día</th><th>Hora</th><th>Actualizar</th></tr></thead>
            <tbody>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= escapar($student['codigo']) ?></td>
                    <td><?= escapar($student['apellidos'] . ', ' . $student['nombres']) ?></td>
                    <td><?= escapar($student['grado'] . ' · ' . $student['seccion']) ?></td>
                    <td><?php if ($student['asistencia_estado']): ?><span class="status-pill <?= escapar($student['asistencia_estado']) ?>"><?= escapar(ucfirst($student['asistencia_estado'])) ?></span><?php else: ?><span class="status-pill sin-registro">Sin registro</span><?php endif; ?></td>
                    <td><?= $student['hora'] && $student['hora'] !== '00:00:00' ? escapar(substr($student['hora'], 0, 5)) : '—' ?></td>
                    <td>
                        <form class="inline-form" method="post">
                            <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                            <input type="hidden" name="alumno_id" value="<?= (int) $student['id'] ?>">
                            <input type="hidden" name="fecha" value="<?= escapar($date) ?>">
                            <select name="estado" aria-label="Estado de <?= escapar($student['nombres']) ?>">
                                <?php foreach (['presente' => 'Presente', 'tardanza' => 'Tardanza', 'falta' => 'Falta'] as $value => $label): ?>
                                    <option value="<?= escapar($value) ?>" <?= $student['asistencia_estado'] === $value ? 'selected' : '' ?>><?= escapar($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button small secondary" type="submit">Guardar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$students): ?><tr><td colspan="6" class="empty-state">No hay alumnos activos para este filtro.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>