<?php
require_once __DIR__ . '/config/funciones.php';
requiereAcceso();
$pdo = conectarBaseDatos();
// Busca los datos del alumno para crear su credencial imprimible.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT codigo, nombres, apellidos, grado, seccion, qr_token, estado FROM alumnos WHERE id = :id');
$statement->execute(['id' => $id ?: 0]);
$student = $statement->fetch();

if (!$student) {
    http_response_code(404);
    exit('No se encontró el alumno.');
}

$activePage = 'alumnos';
$pageTitle = 'Credencial QR';
require __DIR__ . '/includes/header.php';
?>
<section class="qr-page">
    <div class="qr-card">
        <p class="eyebrow">CREDENCIAL DE ASISTENCIA</p>
        <h2>Fe y Alegría N.° 41</h2>
        <div id="student-qr" class="qr-image" aria-label="Código QR del alumno"></div>
        <h3><?= escapar($student['apellidos'] . ', ' . $student['nombres']) ?></h3>
        <p><?= escapar($student['codigo']) ?> · <?= escapar($student['grado'] . ' ' . $student['seccion']) ?></p>
        <span class="status-pill <?= escapar($student['estado']) ?>"><?= escapar(ucfirst($student['estado'])) ?></span>
        <div class="qr-actions"><button class="button primary" type="button" onclick="window.print()">Imprimir credencial</button><a class="text-link" href="alumnos.php">Volver a alumnos</a></div>
    </div>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    // Genera el QR con el token del alumno, codificado de forma segura para JavaScript.
    new QRCode(document.getElementById('student-qr'), {
        text: <?= json_encode($student['qr_token'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        width: 220,
        height: 220,
        colorDark: '#1d302d',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>