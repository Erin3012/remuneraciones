<?php
declare(strict_types=1);

function sso_env_value(string $key, string $default = ''): string
{
    static $values;
    if ($values === null) {
        $values = [];
        $file = dirname(__DIR__) . '/.env';
        if (is_readable($file)) foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line); if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$name, $value] = explode('=', $line, 2); $values[trim($name)] = trim($value, " \t\"'");
        }
    }
    return ($values[$key] ?? '') !== '' ? (string)$values[$key] : $default;
}
function sso_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']); session_start();
}
function sso_exchange(string $code, string $clientId): array
{
    $url = rtrim(sso_env_value('PORTAL_SSO_URL', 'https://portal.metalrubber.cl'), '/') . '/sso/exchange.php';
    $fields = http_build_query(['code' => $code, 'client_id' => $clientId, 'client_secret' => sso_env_value('PORTAL_SSO_SECRET')]);
    if (function_exists('curl_init')) { $curl = curl_init($url); curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']]); $body = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl); }
    else { $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $fields, 'timeout' => 10, 'ignore_errors' => true]]); $body = @file_get_contents($url, false, $context); $status = 0; foreach (($http_response_header ?? []) as $header) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) { $status = (int)$match[1]; break; } }
    $data = is_string($body) ? json_decode($body, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['email'])) throw new RuntimeException('No se pudo validar el acceso central.');
    return $data;
}
