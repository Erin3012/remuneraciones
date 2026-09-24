<?php
declare(strict_types=1);
$message = ($_GET['code'] ?? '') === 'unlinked' ? 'Tu usuario central no tiene una cuenta activa en Remuneraciones.' : 'No se pudo validar el acceso central. Intenta nuevamente.';
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acceso no disponible</title><link rel="stylesheet" href="../style.css"></head><body><main class="card narrow"><h1>Acceso no disponible</h1><p class="error"><?=htmlspecialchars($message, ENT_QUOTES, 'UTF-8')?></p><a href="../index.php?page=login">Ingresar con cuenta local</a></main></body></html>
