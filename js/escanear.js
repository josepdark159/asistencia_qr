const startButton = document.getElementById('start-scanner');
const scanForm = document.getElementById('scan-form');
const tokenInput = document.getElementById('qr-token');
const readerElement = document.getElementById('qr-reader');

// Inicia la cámara tras la acción del usuario y envía el token leído al formulario.
if (startButton && window.Html5Qrcode) {
    const scanner = new Html5Qrcode('qr-reader');
    startButton.addEventListener('click', async () => {
        startButton.disabled = true;
        try {
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 230, height: 230 } },
                async (decodedText) => {
                    tokenInput.value = decodedText;
                    await scanner.stop();
                    scanForm.requestSubmit();
                }
            );
        } catch (error) {
            readerElement.textContent = 'No se pudo abrir la cámara. Verifica los permisos o ingresa el código manualmente.';
            startButton.disabled = false;
        }
    });
// Informa si la librería del lector QR no pudo cargarse y deja disponible el ingreso manual.
} else if (startButton) {
    startButton.disabled = true;
    readerElement.textContent = 'No se pudo cargar el lector QR. Puedes ingresar el token manualmente.';
}