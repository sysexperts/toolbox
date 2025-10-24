<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: recurring.php');
    exit;
}

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
run_recurring_invoices($pdo, $tenantId);

header('Location: recurring.php?status=run');
exit;
