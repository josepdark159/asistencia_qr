# Sistema de registro de asistencias QR

Aplicación web en PHP para registrar asistencias mediante códigos QR en la institución Fe y Alegría N.° 41.

## Requisitos

- XAMPP con Apache y MySQL/MariaDB
- PHP 8.1 o posterior
- Extensión `pdo_mysql`

## Instalación

1. Copia el proyecto dentro de `htdocs`.
2. Crea una base de datos MySQL llamada `asistencia_qr`.
3. Actualiza las credenciales locales en `config/conexion.php` si son diferentes.
4. Inicia Apache y MySQL desde el panel de XAMPP.
5. Abre `http://localhost/asistencia_qr/setup_usuario.php` para crear la cuenta administradora inicial.

La aplicación requiere PHP con soporte para sesiones y PDO MySQL. No subas contraseñas reales ni respaldos de la base de datos al repositorio.
