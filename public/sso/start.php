<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/SsoClient.php';
sso_session_start();
if (!empty($_SESSION['user'])) { header('Location: ../index.php?page=employees'); exit; }
$state = bin2hex(random_bytes(32)); $_SESSION['sso_state'] = $state;
$portal = rtrim(sso_env_value('PORTAL_SSO_URL', 'https://portal.metalrubber.cl'), '/');
header('Location: ' . $portal . '/sso/authorize.php?client_id=payroll&state=' . rawurlencode($state)); exit;
