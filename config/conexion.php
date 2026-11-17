<?php

// Crea una conexión PDO a MySQL con errores como excepciones y consultas preparadas reales.
function conectarBaseDatos(): ?PDO
{
    $config = [
        'host' => 'localhost',
        'database' => 'asistencia_qr',
        'username' => 'root',
        'password' => '',
    ];

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['database']
    );

    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}