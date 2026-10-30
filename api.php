<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/github-data.php';
function fail_api($message, $status = 400) { http_response_code($status); echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE); exit; }
$payload = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals(PUBLIC_APP_KEY, (string)($payload['app_key'] ?? ''))) fail_api('Chave do aplicativo inválida.', 401);
if (($payload['action'] ?? '') !== 'device_status') fail_api('Ação inválida.');
$deviceId = trim((string)($payload['device_id'] ?? ''));
if ($deviceId === '' || strlen($deviceId) > 180) fail_api('Identificador do aparelho inválido.');
try {
    $settingsFile = __DIR__ . '/data/settings.json';
    $settings = is_file($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
    if (!is_array($settings)) $settings = [];
    $data = github_devices_read(); $items = $data['items']; $record = null;
    foreach ($items as $item) if (($item['device_id'] ?? '') === $deviceId) { $record = $item; break; }
    $record = $record ?: ['device_id' => $deviceId, 'blocked' => false, 'message' => ''];
    // O repositório é público: não grava IMEI.
    $record['model'] = trim((string)($payload['model'] ?? ''));
    $record['android_version'] = trim((string)($payload['android_version'] ?? ''));
    $record['last_seen'] = date('c');
    $newItems = [];
    foreach ($items as $item) if (($item['device_id'] ?? '') !== $deviceId) $newItems[] = $item;
    $newItems[] = $record;
    github_devices_write($newItems, $data['sha']);
    $blocked = !empty($record['blocked']);
    $message = $blocked && !empty($record['message']) ? $record['message'] : (string)($settings['lock_message'] ?? '');
    $link = github_link_read();
    echo json_encode(['ok' => true, 'plan_active' => !empty($settings['plan_active']), 'device_blocked' => $blocked, 'message' => $message, 'dialog' => (string)($settings['dialog_message'] ?? ''), 'unitv_url' => $link['url']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { fail_api($e->getMessage(), 502); }
