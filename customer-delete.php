<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('CRM');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: customers.php');
    exit;
}

$customerId = (int)($_POST['id'] ?? 0);

if ($customerId > 0) {
    $pdo = get_database();
    $user = current_user();
    $tenantId = $user['tenant_id'] ?? null;
    delete_customer($pdo, $customerId, $tenantId);
    header('Location: customers.php?status=deleted');
    exit;
}

header('Location: customers.php');
exit;
