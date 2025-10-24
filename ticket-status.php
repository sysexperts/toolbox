<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Support');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: support.php');
    exit;
}

$ticketId = (int)($_POST['id'] ?? 0);
$status = trim((string)($_POST['status'] ?? ''));

if ($ticketId <= 0 || $status === '') {
    header('Location: support.php');
    exit;
}

$allowed = ['open', 'in_progress', 'waiting', 'done'];
if (!in_array($status, $allowed, true)) {
    header('Location: support.php');
    exit;
}

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
update_ticket_status($pdo, $ticketId, $status, $tenantId);

header('Location: support.php');
exit;
