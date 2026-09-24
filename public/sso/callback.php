<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/SsoClient.php';
sso_session_start();
if (!empty($_SESSION['user'])) { header('Location: ../index.php?page=employees'); exit; }
$state = (string)($_GET['state'] ?? ''); $expected = (string)($_SESSION['sso_state'] ?? ''); unset($_SESSION['sso_state']); $code = (string)($_GET['code'] ?? '');
if ($expected === '' || $state === '' || !hash_equals($expected, $state) || !preg_match('/^[A-Za-z0-9_-]{40,64}$/', $code)) { header('Location: error.php?code=invalid'); exit; }
try {
    $identity = sso_exchange($code, 'payroll'); require_once __DIR__ . '/../../app/Database.php'; $pdo = Database::connection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email=? AND active=1 LIMIT 1'); $stmt->execute([strtolower(trim((string)$identity['email']))]); $local = $stmt->fetch();
    if (!$local) { header('Location: error.php?code=unlinked'); exit; }
    session_regenerate_id(true); $_SESSION['user'] = $local; header('Location: ../index.php?page=employees'); exit;
} catch (Throwable $e) { error_log($e->getMessage()); header('Location: error.php?code=invalid'); exit; }
