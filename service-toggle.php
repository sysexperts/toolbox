<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('CRM');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: services.php');
    exit;
}

$serviceId = (int)($_POST['id'] ?? 0);
$active = (int)($_POST['active'] ?? 0) === 1;

if ($serviceId <= 0) {
    header('Location: services.php');
    exit;
}

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
toggle_service($pdo, $serviceId, $active, $tenantId);

header('Location: services.php?status=toggled');
exit;
