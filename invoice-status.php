<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: invoices.php');
    exit;
}

$invoiceId = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? 'open');

if ($invoiceId > 0) {
    $pdo = get_database();
    $user = current_user();
    $tenantId = $user['tenant_id'] ?? null;
    try {
        update_invoice_status($pdo, $invoiceId, $status, $tenantId);
        header('Location: invoice-view.php?id=' . $invoiceId . '&status=updated');
        exit;
    } catch (InvalidArgumentException $exception) {
        header('Location: invoice-view.php?id=' . $invoiceId . '&status=error&message=' . urlencode($exception->getMessage()));
        exit;
    }
}

header('Location: invoices.php');
exit;
