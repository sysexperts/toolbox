<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: invoices.php');
    exit;
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$paymentDate = trim((string)($_POST['payment_date'] ?? ''));

if ($invoiceId <= 0 || $amount <= 0 || $paymentDate === '') {
    header('Location: invoice-view.php?id=' . $invoiceId . '&status=payment-error');
    exit;
}

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;

record_payment($pdo, [
    'invoice_id' => $invoiceId,
    'amount' => $amount,
    'payment_date' => $paymentDate,
    'method' => trim((string)($_POST['method'] ?? '')),
    'reference' => trim((string)($_POST['reference'] ?? '')),
    'notes' => trim((string)($_POST['notes'] ?? '')),
    'tenant_id' => $tenantId,
]);

header('Location: invoice-view.php?id=' . $invoiceId . '&status=payment-recorded');
exit;
