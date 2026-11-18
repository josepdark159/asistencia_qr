<?php
require_once __DIR__ . '/config/funciones.php';
requiereAcceso();
$pdo = conectarBaseDatos();
$today = date('Y-m-d');
$firstDay = date('Y-m-01');
$from = $_GET['desde'] ?? $firstDay;
$to = $_GET['hasta'] ?? $today;
// Corrige fechas inválidas o rangos invertidos para mantener una consulta válida.
if (!is_string($from) || !fechaValida($from)) {
    $from = $firstDay;
}
if (!is_string($to) || !fechaValida($to) || $to < $from) {
    $to = $today >= $from ? $today : $from;
}
$grade = trim($_GET['grado'] ?? '');
$section = trim($_GET['seccion'] ?? '');
if (!in_array($section, seccionesPrimaria(), true)) {
    $section = '';
}

// Consulta las asistencias del periodo aplicando los filtros seleccionados.
$where = 'a.fecha BETWEEN :desde AND :hasta';
$parameters = ['desde' => $from, 'hasta' => $to];
if ($grade !== '') {
    $where .= ' AND al.grado = :grado';
    $parameters['grado'] = $grade;
}
if ($section !== '') {
    $where .= ' AND al.seccion = :seccion';
    $parameters['seccion'] = $section;
}
$sql = "SELECT a.fecha, a.hora, a.estado, al.codigo, al.nombres, al.apellidos, al.grado, al.seccion FROM asistencias a INNER JOIN alumnos al ON al.id = a.alumno_id WHERE $where ORDER BY a.fecha DESC, al.grado, al.seccion, al.apellidos, al.nombres";
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$records = $statement->fetchAll();

// Exporta el reporte como hoja compatible con Microsoft Excel.
if (isset($_GET['excel']) || isset($_GET['csv'])) {
    $excelEscape = static function ($value): string {
        $value = (string) $value;
        if (preg_match('/^[=+@-]/', $value)) {
            $value = "'" . $value;
        }
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="asistencias_' . $from . '_' . $to . '.xls"');
    header('Cache-Control: private, max-age=0');

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<Worksheet ss:Name="Asistencias"><Table>';
    echo '<Row>';
    foreach (['Fecha', 'Hora', 'Código', 'Apellidos', 'Nombres', 'Grado de primaria', 'Sección', 'Estado'] as $heading) {
        echo '<Cell><Data ss:Type="String">' . $excelEscape($heading) . '</Data></Cell>';
    }
    echo '</Row>';

    foreach ($records as $record) {
        echo '<Row>';
        foreach ([
            $record['fecha'],
            $record['hora'],
            $record['codigo'],
            $record['apellidos'],
            $record['nombres'],
            $record['grado'],
            $record['seccion'],
            $record['estado'],
        ] as $value) {
            echo '<Cell><Data ss:Type="String">' . $excelEscape($value) . '</Data></Cell>';
        }
        echo '</Row>';
    }

    echo '</Table></Worksheet></Workbook>';
    exit;
}

// Cuenta los registros por estado para mostrar el resumen del reporte.
$summary = ['presente' => 0, 'tardanza' => 0, 'falta' => 0];
foreach ($records as $record) {
    $summary[$record['estado']]++;
}
$grades = gradosPrimaria();
$sections = seccionesPrimaria();
$activePage = 'reportes';
$pageTitle = 'Reportes';
require __DIR__ . '/includes/header.php';
?>
<section class="list-panel">
    <div class="list-heading attendance-heading">
        <div><h2>Registros de asistencia</h2><p class="panel-hint">Consulta por periodo, grado de primaria y sección.</p></div>
        <form class="search-form" method="get">
            <label>Desde<input type="date" name="desde" value="<?= escapar($from) ?>" required></label>
            <label>Hasta<input type="date" name="hasta" value="<?= escapar($to) ?>" required></label>
            <label>Grado de primaria<select name="grado"><option value="">Todos</option><?php foreach ($grades as $item): ?><option value="<?= escapar($item) ?>" <?= $grade === $item ? 'selected' : '' ?>><?= escapar($item) ?></option><?php endforeach; ?></select></label>
            <label>Sección<select name="seccion"><option value="">Todas</option><?php foreach ($sections as $item): ?><option value="<?= escapar($item) ?>" <?= $section === $item ? 'selected' : '' ?>><?= escapar($item) ?></option><?php endforeach; ?></select></label>
            <button class="button secondary" type="submit">Consultar</button>
            <button class="button primary" type="submit" name="excel" value="1">Exportar Excel</button>
        </form>
    </div>
    <div class="summary-grid">
        <div class="summary-item"><span>Presentes</span><strong><?= $summary['presente'] ?></strong></div>
        <div class="summary-item"><span>Tardanzas</span><strong><?= $summary['tardanza'] ?></strong></div>
        <div class="summary-item"><span>Faltas registradas</span><strong><?= $summary['falta'] ?></strong></div>
        <div class="summary-item"><span>Total de registros</span><strong><?= count($records) ?></strong></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fecha</th><th>Hora</th><th>Código</th><th>Alumno</th><th>Grado de primaria/sección</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($records as $record): ?>
                <tr>
                    <td><?= escapar(date('d/m/Y', strtotime($record['fecha']))) ?></td>
                    <td><?= escapar(substr($record['hora'], 0, 5)) ?></td>
                    <td><?= escapar($record['codigo']) ?></td>
                    <td><?= escapar($record['apellidos'] . ', ' . $record['nombres']) ?></td>
                    <td><?= escapar($record['grado'] . ' · ' . $record['seccion']) ?></td>
                    <td><span class="status-pill <?= escapar($record['estado']) ?>"><?= escapar(ucfirst($record['estado'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?><tr><td colspan="6" class="empty-state">No hay registros con estos filtros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>