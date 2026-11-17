<?php
require_once __DIR__ . '/config/funciones.php';
requiereAcceso();
$pdo = conectarBaseDatos();
$notice = '';
$noticeType = 'success';
$isEditing = false;
$editId = null;
$formStudent = ['codigo' => '', 'nombres' => '', 'apellidos' => '', 'grado' => '', 'seccion' => ''];

// Procesa el registro, la edición o el cambio de estado de un alumno.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['crear', 'actualizar'], true)) {
        $isEditing = $action === 'actualizar';
        $editId = $isEditing ? filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) : null;
        $student = [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nombres' => trim($_POST['nombres'] ?? ''),
            'apellidos' => trim($_POST['apellidos'] ?? ''),
            'grado' => trim($_POST['grado'] ?? ''),
            'seccion' => trim($_POST['seccion'] ?? ''),
        ];
        $formStudent = $student;

        if ($isEditing && !$editId) {
            $notice = 'No se pudo identificar al alumno que deseas editar.';
            $noticeType = 'error';
        } elseif (in_array('', $student, true)) {
            $notice = 'Completa todos los campos del alumno.';
            $noticeType = 'error';
        } elseif (!in_array($student['grado'], gradosPrimaria(), true)) {
            $notice = 'Selecciona un grado de 1.° a 6.° de primaria.';
            $noticeType = 'error';
        } elseif (!in_array($student['seccion'], seccionesPrimaria(), true)) {
            $notice = 'Selecciona la sección A, B o C.';
            $noticeType = 'error';
        } else {
            try {
                if ($isEditing) {
                    $statement = $pdo->prepare(
                        'UPDATE alumnos SET codigo = :codigo, nombres = :nombres, apellidos = :apellidos, grado = :grado, seccion = :seccion WHERE id = :id'
                    );
                    $statement->execute($student + ['id' => $editId]);
                    redirigir('alumnos.php?actualizado=1');
                } else {
                    $statement = $pdo->prepare(
                        'INSERT INTO alumnos (codigo, nombres, apellidos, grado, seccion, qr_token) VALUES (:codigo, :nombres, :apellidos, :grado, :seccion, :qr_token)'
                    );
                    $statement->execute($student + ['qr_token' => bin2hex(random_bytes(32))]);
                    redirigir('alumnos.php?guardado=1');
                }
            } catch (PDOException $exception) {
                $notice = $exception->getCode() === '23000'
                    ? 'Ese código de alumno ya está registrado.'
                    : 'No se pudo guardar el alumno. Verifica la conexión a la base de datos.';
                $noticeType = 'error';
            }
        }
    }

    if ($action === 'estado') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $state = $_POST['estado'] ?? '';
        if ($id && in_array($state, ['activo', 'inactivo'], true)) {
            $statement = $pdo->prepare('UPDATE alumnos SET estado = :estado WHERE id = :id');
            $statement->execute(['estado' => $state, 'id' => $id]);
        }
        redirigir('alumnos.php');
    }
}

// Carga los datos existentes cuando se solicita editar un alumno.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['editar'])) {
    $editId = filter_var($_GET['editar'], FILTER_VALIDATE_INT);
    if ($editId) {
        $statement = $pdo->prepare('SELECT id, codigo, nombres, apellidos, grado, seccion FROM alumnos WHERE id = :id');
        $statement->execute(['id' => $editId]);
        $formStudent = $statement->fetch();
        if ($formStudent) {
            $isEditing = true;
        } else {
            $notice = 'No se encontró el alumno que deseas editar.';
            $noticeType = 'error';
            $formStudent = ['codigo' => '', 'nombres' => '', 'apellidos' => '', 'grado' => '', 'seccion' => ''];
        }
    } else {
        $notice = 'El identificador del alumno no es válido.';
        $noticeType = 'error';
    }
}

if (isset($_GET['guardado'])) {
    $notice = 'Alumno registrado. Ya puedes imprimir su código QR.';
} elseif (isset($_GET['actualizado'])) {
    $notice = 'Datos del alumno actualizados correctamente.';
}

// Aplica los filtros de búsqueda y estado antes de mostrar el directorio.
$search = trim($_GET['buscar'] ?? '');
$state = $_GET['estado'] ?? 'activo';
$allowedStates = ['activo', 'inactivo', 'todos'];
if (!in_array($state, $allowedStates, true)) {
    $state = 'activo';
}

$conditions = [];
$parameters = [];
if ($state !== 'todos') {
    $conditions[] = 'estado = :estado';
    $parameters['estado'] = $state;
}
if ($search !== '') {
    $conditions[] = '(codigo LIKE :buscar_codigo OR nombres LIKE :buscar_nombres OR apellidos LIKE :buscar_apellidos OR grado LIKE :buscar_grado OR seccion LIKE :buscar_seccion)';
    $term = '%' . $search . '%';
    $parameters += [
        'buscar_codigo' => $term,
        'buscar_nombres' => $term,
        'buscar_apellidos' => $term,
        'buscar_grado' => $term,
        'buscar_seccion' => $term,
    ];
}
$sql = 'SELECT id, codigo, nombres, apellidos, grado, seccion, estado FROM alumnos';
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY grado, seccion, apellidos, nombres';
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$students = $statement->fetchAll();

$activePage = 'alumnos';
$pageTitle = 'Alumnos';
require __DIR__ . '/includes/header.php';
?>
<section class="split-layout">
    <form class="form-panel" method="post">
        <h2><?= $isEditing ? 'Editar alumno' : 'Nuevo alumno' ?></h2>
        <p class="panel-hint"><?= $isEditing ? 'Actualiza los datos del alumno. Su código QR se conservará.' : 'El sistema generará un código QR único.' ?></p>
        <?php if ($notice !== ''): ?>
            <p class="notice <?= escapar($noticeType) ?>" role="status"><?= escapar($notice) ?></p>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
        <input type="hidden" name="action" value="<?= $isEditing ? 'actualizar' : 'crear' ?>">
        <?php if ($isEditing): ?><input type="hidden" name="id" value="<?= (int) $editId ?>"><?php endif; ?>
        <label>Código del alumno<input name="codigo" maxlength="30" required autocomplete="off" value="<?= escapar($formStudent['codigo']) ?>"></label>
        <div class="field-pair">
            <label>Nombres<input name="nombres" maxlength="100" required value="<?= escapar($formStudent['nombres']) ?>"></label>
            <label>Apellidos<input name="apellidos" maxlength="100" required value="<?= escapar($formStudent['apellidos']) ?>"></label>
        </div>
        <div class="field-pair">
            <label>Grado de primaria<select name="grado" required><option value="">Selecciona</option><?php foreach (gradosPrimaria() as $gradeOption): ?><option value="<?= escapar($gradeOption) ?>" <?= $formStudent['grado'] === $gradeOption ? 'selected' : '' ?>><?= escapar($gradeOption) ?></option><?php endforeach; ?></select></label>
            <label>Sección<select name="seccion" required><option value="">Selecciona</option><?php foreach (seccionesPrimaria() as $sectionOption): ?><option value="<?= escapar($sectionOption) ?>" <?= $formStudent['seccion'] === $sectionOption ? 'selected' : '' ?>><?= escapar($sectionOption) ?></option><?php endforeach; ?></select></label>
        </div>
        <button class="button primary" type="submit"><?= $isEditing ? 'Guardar cambios' : 'Registrar alumno' ?></button>
        <?php if ($isEditing): ?> <a class="text-link" href="alumnos.php">Cancelar</a><?php endif; ?>
    </form>

    <section class="list-panel">
        <div class="list-heading">
            <div><h2>Directorio</h2><p class="panel-hint"><?= count($students) ?> resultado(s)</p></div>
            <form class="search-form" method="get">
                <input name="buscar" value="<?= escapar($search) ?>" placeholder="Buscar alumno" aria-label="Buscar alumno">
                <select name="estado" aria-label="Filtrar por estado">
                    <option value="activo" <?= $state === 'activo' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivo" <?= $state === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                    <option value="todos" <?= $state === 'todos' ? 'selected' : '' ?>>Todos</option>
                </select>
                <button class="button secondary" type="submit">Buscar</button>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Código</th><th>Alumno</th><th>Grado de primaria/sección</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= escapar($student['codigo']) ?></td>
                        <td><?= escapar($student['apellidos'] . ', ' . $student['nombres']) ?></td>
                        <td><?= escapar($student['grado'] . ' · ' . $student['seccion']) ?></td>
                        <td><span class="status-pill <?= escapar($student['estado']) ?>"><?= escapar(ucfirst($student['estado'])) ?></span></td>
                        <td class="actions-cell">
                            <a class="text-link" href="alumnos.php?editar=<?= (int) $student['id'] ?>">Editar</a>
                            <a class="text-link" href="qr.php?id=<?= (int) $student['id'] ?>">Ver QR</a>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                                <input type="hidden" name="action" value="estado">
                                <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
                                <input type="hidden" name="estado" value="<?= $student['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                <button class="text-button" type="submit"><?= $student['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$students): ?><tr><td colspan="5" class="empty-state">No hay alumnos para mostrar.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>