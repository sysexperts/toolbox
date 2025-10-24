<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');
require __DIR__ . '/lib/simple_pdf.php';

$invoiceId = (int)($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
    http_response_code(404);
    exit('Invoice not found.');
}

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$invoice = get_invoice($pdo, $invoiceId, $tenantId);

if (!$invoice) {
    http_response_code(404);
    exit('Invoice not found.');
}

$pdf = new SimplePDF();
$pdf->add_page();

$top = 60;
$pdf->text(40, $top, APP_NAME, 18);
$pdf->text(40, $top + 20, 'Invoice ' . $invoice['invoice_number'], 12);
$pdf->text(40, $top + 38, 'Issue Date: ' . date('d.m.Y', strtotime($invoice['issue_date'])));
$pdf->text(40, $top + 52, 'Due Date: ' . date('d.m.Y', strtotime($invoice['due_date'])));

$clientLines = array_filter([
    $invoice['company'],
    $invoice['contact_name'] ?? null,
    $invoice['address_line'] ?? null,
    trim(($invoice['postal_code'] ?? '') . ' ' . ($invoice['city'] ?? '')),
    $invoice['country'] ?? null,
    $invoice['email'] ?? null,
    $invoice['phone'] ?? null,
]);
$pdf->text(350, $top, 'Bill To:', 12);
$pdf->multi_text(350, $top + 16, 14, $clientLines);

$pdf->line(40, $top + 70, 555, $top + 70, 1.2);

$tableHeaders = ['Position', 'Description', 'Qty', 'Unit', 'Total'];
$tableRows = [];
foreach ($invoice['items'] as $item) {
    $tableRows[] = [
        (string)$item['position'],
        substr((string)$item['description'], 0, 60),
        number_format((float)$item['quantity'], 2, ',', '.'),
        number_format((float)$item['unit_price'], 2, ',', '.') . ' ' . $invoice['currency'],
        number_format((float)$item['line_total'], 2, ',', '.') . ' ' . $invoice['currency'],
    ];
}
$pdf->table(40, $top + 90, 515, $tableHeaders, $tableRows);

$summaryY = $top + 90 + (count($tableRows) + 2) * 18 + 10;
$pdf->text(350, $summaryY, 'Subtotal: ' . number_format((float)$invoice['subtotal'], 2, ',', '.') . ' ' . $invoice['currency']);
$pdf->text(350, $summaryY + 16, 'Tax (' . number_format((float)$invoice['tax_rate'], 2, ',', '.') . '%): ' . number_format((float)$invoice['tax_total'], 2, ',', '.') . ' ' . $invoice['currency']);
$pdf->text(350, $summaryY + 32, 'Total: ' . number_format((float)$invoice['total'], 2, ',', '.') . ' ' . $invoice['currency'], 12);
$pdf->text(350, $summaryY + 52, 'Paid: ' . number_format((float)$invoice['paid_total'], 2, ',', '.') . ' ' . $invoice['currency']);
$pdf->text(350, $summaryY + 68, 'Balance: ' . number_format((float)$invoice['balance_due'], 2, ',', '.') . ' ' . $invoice['currency'], 12);

if (!empty($invoice['notes'])) {
    $pdf->text(40, $summaryY + 16, 'Notes:', 11);
    $pdf->multi_text(40, $summaryY + 32, 14, explode("\n", (string)$invoice['notes']), 10);
}

$pdf->output('invoice-' . $invoice['invoice_number'] . '.pdf');
