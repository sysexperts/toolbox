<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

$invoiceId = (int)($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
    header('Location: invoices.php');
    exit;
}

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$invoice = get_invoice($pdo, $invoiceId, $tenantId);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

$statusMessage = (string)($_GET['status'] ?? '');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_money(float $value, string $currency = 'EUR'): string
{
    return $currency . ' ' . number_format($value, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME); ?> - Rechnung <?= h($invoice['invoice_number']); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Rechnung <?= h($invoice['invoice_number']); ?></h1>
            <p>Erstellt am <?= h(date('d.m.Y', strtotime((string)$invoice['issue_date']))); ?>, faellig am <?= h(date('d.m.Y', strtotime((string)$invoice['due_date']))); ?>.</p>
        </div>
        <div class="header-actions">
            <a class="button button--ghost" href="invoice-pdf.php?id=<?= (int)$invoice['id']; ?>" target="_blank" rel="noopener noreferrer">Als PDF</a>
            <a class="button button--ghost" href="invoices.php">Zur Uebersicht</a>
        </div>
    </header>

    <?php if ($statusMessage === 'created'): ?>
        <p class="alert alert--success">Rechnung wurde angelegt.</p>
    <?php elseif ($statusMessage === 'updated'): ?>
        <p class="alert alert--success">Rechnungsstatus wurde aktualisiert.</p>
    <?php elseif ($statusMessage === 'payment-recorded'): ?>
        <p class="alert alert--success">Zahlung wurde erfasst.</p>
    <?php endif; ?>

    <div class="invoice-detail">
        <section class="invoice-detail__section">
            <h2>Rechnungsdetails</h2>
            <div class="detail-grid">
                <div>
                    <span class="detail-label">Status</span>
                    <span class="badge badge--<?= h($invoice['status']); ?>"><?= h($invoice['status']); ?></span>
                </div>
                <div>
                    <span class="detail-label">Ausstellungsdatum</span>
                    <strong><?= h(date('d.m.Y', strtotime((string)$invoice['issue_date']))); ?></strong>
                </div>
                <div>
                    <span class="detail-label">Faelligkeitsdatum</span>
                    <strong><?= h(date('d.m.Y', strtotime((string)$invoice['due_date']))); ?></strong>
                </div>
                <div>
                    <span class="detail-label">Steuersatz</span>
                    <strong><?= h(number_format((float)$invoice['tax_rate'], 2, ',', '.')); ?> %</strong>
                </div>
            </div>
        </section>

        <section class="invoice-detail__section">
            <h2>Kunde</h2>
            <div class="detail-grid detail-grid--two-cols">
                <div>
                    <span class="detail-label">Firma</span>
                    <strong><?= h($invoice['company']); ?></strong>
                    <?php if ($invoice['contact_name']): ?>
                        <p><?= h($invoice['contact_name']); ?></p>
                    <?php endif; ?>
                    <?php if ($invoice['email']): ?>
                        <p><a href="mailto:<?= h($invoice['email']); ?>"><?= h($invoice['email']); ?></a></p>
                    <?php endif; ?>
                    <?php if ($invoice['phone']): ?>
                        <p><a href="tel:<?= h($invoice['phone']); ?>"><?= h($invoice['phone']); ?></a></p>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="detail-label">Adresse</span>
                    <p>
                        <?= h($invoice['address_line'] ?? ''); ?><br>
                        <?= h(trim(($invoice['postal_code'] ?? '') . ' ' . ($invoice['city'] ?? ''))); ?><br>
                        <?= h($invoice['country'] ?? ''); ?>
                    </p>
                </div>
            </div>
        </section>

        <section class="invoice-detail__section">
            <h2>Positionen</h2>
            <div class="table-wrapper table-wrapper--no-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Beschreibung</th>
                        <th>Menge</th>
                        <th>Einzelpreis</th>
                        <th>Gesamt</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoice['items'] as $item): ?>
                        <tr>
                            <td><?= (int)$item['position']; ?></td>
                            <td><?= h($item['description']); ?></td>
                            <td><?= h(number_format((float)$item['quantity'], 2, ',', '.')); ?></td>
                            <td><?= format_money((float)$item['unit_price']); ?></td>
                            <td><?= format_money((float)$item['line_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="invoice-detail__section invoice-detail__section--summary">
            <div class="summary-row">
                <span>Zwischensumme</span>
                <strong><?= format_money((float)$invoice['subtotal'], $invoice['currency']); ?></strong>
            </div>
            <div class="summary-row">
                <span>Steuer (<?= h(number_format((float)$invoice['tax_rate'], 2, ',', '.')); ?> %)</span>
                <strong><?= format_money((float)$invoice['tax_total'], $invoice['currency']); ?></strong>
            </div>
            <div class="summary-row summary-row--total">
                <span>Gesamtbetrag</span>
                <strong><?= format_money((float)$invoice['total'], $invoice['currency']); ?></strong>
            </div>
            <div class="summary-row">
                <span>Bisher bezahlt</span>
                <strong><?= format_money((float)$invoice['paid_total'], $invoice['currency']); ?></strong>
            </div>
            <div class="summary-row">
                <span>Offener Betrag</span>
                <strong><?= format_money((float)$invoice['balance_due'], $invoice['currency']); ?></strong>
            </div>
        </section>

        <?php if (!empty($invoice['notes'])): ?>
            <section class="invoice-detail__section">
                <h2>Hinweise</h2>
                <p><?= nl2br(h($invoice['notes'])); ?></p>
            </section>
        <?php endif; ?>

        <section class="invoice-detail__section">
            <h2>Zahlungen</h2>
            <?php if (empty($invoice['payments'])): ?>
                <p>Es wurden noch keine Zahlungen erfasst.</p>
            <?php else: ?>
                <div class="table-wrapper table-wrapper--no-scroll">
                    <table>
                        <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Betrag</th>
                            <th>Methode</th>
                            <th>Referenz</th>
                            <th>Notiz</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($invoice['payments'] as $payment): ?>
                            <tr>
                                <td><?= h(date('d.m.Y', strtotime((string)$payment['payment_date']))); ?></td>
                                <td><?= format_money((float)$payment['amount'], $invoice['currency']); ?></td>
                                <td><?= h($payment['method'] ?? '-'); ?></td>
                                <td><?= h($payment['reference'] ?? '-'); ?></td>
                                <td><?= h($payment['notes'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <form action="payment-record.php" method="post" class="inline-form payment-form">
                <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id']; ?>">
                <label>
                    <span>Betrag</span>
                    <input type="number" name="amount" step="0.01" min="0" value="<?= h(number_format((float)$invoice['balance_due'], 2, '.', '')); ?>">
                </label>
                <label>
                    <span>Datum</span>
                    <input type="date" name="payment_date" value="<?= h(date('Y-m-d')); ?>">
                </label>
                <label>
                    <span>Methode</span>
                    <input type="text" name="method" placeholder="Ueberweisung / Karte / Bar">
                </label>
                <label>
                    <span>Referenz</span>
                    <input type="text" name="reference" placeholder="Transaktions-ID">
                </label>
                <label class="payment-form__notes">
                    <span>Notiz</span>
                    <input type="text" name="notes" placeholder="Optional">
                </label>
                <button type="submit" class="button">Zahlung speichern</button>
            </form>
        </section>

        <section class="invoice-detail__section">
            <h2>Status aktualisieren</h2>
            <form action="invoice-status.php" method="post" class="inline-form">
                <input type="hidden" name="id" value="<?= (int)$invoice['id']; ?>">
                <select name="status">
                    <?php
                    $statuses = ['draft' => 'Entwurf', 'open' => 'Offen', 'paid' => 'Bezahlt', 'cancelled' => 'Storniert'];
                    foreach ($statuses as $key => $label):
                        ?>
                        <option value="<?= h($key); ?>" <?= $invoice['status'] === $key ? 'selected' : ''; ?>>
                            <?= h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Speichern</button>
            </form>
        </section>
    </div>
</main>
</body>
</html>
