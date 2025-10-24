<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$invoices = get_invoices($pdo, $tenantId);
$status = (string)($_GET['status'] ?? '');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME); ?> - Rechnungen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Rechnungen</h1>
            <p>Ueberblick ueber offene und abgeschlossene Vorgaenge.</p>
        </div>
        <a class="button" href="invoice-create.php">Neue Rechnung</a>
    </header>

    <?php if ($status === 'created'): ?>
        <p class="alert alert--success">Rechnung wurde erstellt.</p>
    <?php elseif ($status === 'updated'): ?>
        <p class="alert alert--success">Rechnung wurde aktualisiert.</p>
    <?php endif; ?>

    <div class="panel">
        <?php if (!$invoices): ?>
            <p>Noch keine Rechnungen vorhanden.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Rechnungsnr.</th>
                        <th>Kunde</th>
                        <th>Ausstellungsdatum</th>
                        <th>Faellig bis</th>
                        <th>Summe</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?= h($invoice['invoice_number']); ?></td>
                            <td><?= h($invoice['company']); ?></td>
                            <td><?= h(date('d.m.Y', strtotime((string)$invoice['issue_date']))); ?></td>
                            <td><?= h(date('d.m.Y', strtotime((string)$invoice['due_date']))); ?></td>
                            <td><?= h($invoice['currency']); ?> <?= number_format((float)$invoice['total'], 2, ',', '.'); ?></td>
                            <td><span class="badge badge--<?= h($invoice['status']); ?>"><?= h($invoice['status']); ?></span></td>
                            <td class="table-actions">
                                <a class="table-link" href="invoice-pdf.php?id=<?= (int)$invoice['id']; ?>" target="_blank" rel="noopener noreferrer">PDF</a>
                                <a class="table-link" href="invoice-view.php?id=<?= (int)$invoice['id']; ?>">Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
