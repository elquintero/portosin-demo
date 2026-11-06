<?php
// CONFIGURACIÓN PRIVADA. Cambia la contraseña antes de publicar.
// Genera un hash con: php -r "echo password_hash('TU_CONTRASEÑA_LARGA', PASSWORD_DEFAULT), PHP_EOL;"
return [
    'admin_password_hash' => '$2y$12$4iNR9Sx6CPIxy3.y3Wog0.52hrSOgsCPAL2u8wTkaAJ2yfel36zZu', // cambiar antes de subir
    'database' => __DIR__ . '/storage/portosin.sqlite',
    'images' => dirname(__DIR__) . '/imagenes',
    'max_image_bytes' => 8000000,
];
