<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$customers = get_customers($pdo, $tenantId);

if (!$customers) {
    header('Location: customers.php?status=missing');
    exit;
}

$invoiceTypes = [
    'standard' => ['label' => 'Standardrechnung', 'hint' => 'Normale Abrechnung fuer Leistungen oder Produkte.', 'disabled' => false],
    'deposit' => ['label' => 'Anzahlungsrechnung', 'hint' => 'Teilbetrag vor Projektstart abrechnen.', 'disabled' => false],
    'replacement' => ['label' => 'Ersatzrechnung', 'hint' => 'Nur bei Korrekturen frueherer Belege.', 'disabled' => true],
    'cancellation' => ['label' => 'Stornorechnung', 'hint' => 'Ersetzt eine bestehende Rechnung.', 'disabled' => true],
    'template' => ['label' => 'Rechnungsvorlage', 'hint' => 'Als Vorlage speichern.', 'disabled' => true],
];

$paymentTermsOptions = [
    '' => 'Keine Angabe',
    'immediate' => 'Sofort faellig',
    '7d' => '7 Tage',
    '14d' => '14 Tage',
    '30d' => '30 Tage',
    'custom' => 'Individuell vereinbart',
];

$paymentMethodOptions = [
    '' => 'Nicht angegeben',
    'bank_transfer' => 'Bankueberweisung',
    'direct_debit' => 'Lastschrift',
    'cash' => 'Barzahlung',
    'credit_card' => 'Kreditkarte',
];

$bankAccountOptions = [
    '' => 'Kein Konto hinterlegt',
    'main' => 'Hauptkonto (DE89 3704 0044 0532 0130 00)',
    'alt' => 'Nebenkonto (DE12 3456 7890 1234 5678 00)',
];

$documentTemplates = [
    '' => 'Standardvorlage',
    'classic' => 'Klassisch',
    'modern' => 'Modern',
    'minimal' => 'Minimalistisch',
];

$errors = [];
$formError = null;
$values = [
    'customer_id' => '',
    'invoice_type' => 'standard',
    'issue_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+14 days')),
    'tax_rate' => '19',
    'status' => 'open',
    'currency' => 'EUR',
    'payment_terms' => '',
    'payment_method' => '',
    'bank_account' => '',
    'document_template' => '',
    'public_note' => '',
    'private_note' => '',
];

$itemRows = [
    ['description' => '', 'quantity' => '', 'unit_price' => ''],
    ['description' => '', 'quantity' => '', 'unit_price' => ''],
    ['description' => '', 'quantity' => '', 'unit_price' => ''],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }

    if (!array_key_exists($values['invoice_type'], $invoiceTypes)) {
        $values['invoice_type'] = 'standard';
    }

    $descriptions = $_POST['item_description'] ?? [];
    $quantities = $_POST['item_quantity'] ?? [];
    $prices = $_POST['item_unit_price'] ?? [];

    $itemRows = [];
    $maxRows = max(count($descriptions), 1);
    for ($i = 0; $i < $maxRows; $i++) {
        $itemRows[] = [
            'description' => trim((string)($descriptions[$i] ?? '')),
            'quantity' => trim((string)($quantities[$i] ?? '')),
            'unit_price' => trim((string)($prices[$i] ?? '')),
        ];
    }

    if ($values['customer_id'] === '') {
        $errors['customer_id'] = 'Bitte waehlen Sie einen Kunden aus.';
    }

    if ($values['issue_date'] === '') {
        $errors['issue_date'] = 'Bitte geben Sie ein Ausstellungsdatum an.';
    }

    if ($values['due_date'] === '') {
        $errors['due_date'] = 'Bitte geben Sie ein Faelligkeitsdatum an.';
    }

    if (!is_numeric($values['tax_rate'])) {
        $errors['tax_rate'] = 'Bitte geben Sie einen gueltigen Steuersatz an.';
    }

    if (empty($errors)) {
        try {
            $items = [];
            foreach ($itemRows as $row) {
                $items[] = [
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                ];
            }

            $metaNotes = [];
            $publicNote = trim($values['public_note']);

            if ($values['payment_terms'] !== '' && isset($paymentTermsOptions[$values['payment_terms']])) {
                $metaNotes[] = 'Zahlungsbedingungen: ' . $paymentTermsOptions[$values['payment_terms']];
            }
            if ($values['payment_method'] !== '' && isset($paymentMethodOptions[$values['payment_method']])) {
                $metaNotes[] = 'Zahlungsart: ' . $paymentMethodOptions[$values['payment_method']];
            }
            if ($values['bank_account'] !== '' && isset($bankAccountOptions[$values['bank_account']])) {
                $metaNotes[] = 'Bankkonto: ' . $bankAccountOptions[$values['bank_account']];
            }
            if ($values['document_template'] !== '' && isset($documentTemplates[$values['document_template']])) {
                $metaNotes[] = 'Dokumentvorlage: ' . $documentTemplates[$values['document_template']];
            }
            if ($values['invoice_type'] !== '' && isset($invoiceTypes[$values['invoice_type']])) {
                $metaNotes[] = 'Rechnungstyp: ' . $invoiceTypes[$values['invoice_type']]['label'];
            }

            $combinedNotes = $publicNote;
            if (!empty($metaNotes)) {
                $combinedNotes .= ($combinedNotes !== '' ? "\n\n" : '') . implode("\n", $metaNotes);
            }
            if ($values['private_note'] !== '') {
                $combinedNotes .= ($combinedNotes !== '' ? "\n\n" : '') . '[Privat] ' . $values['private_note'];
            }

            $invoiceId = create_invoice($pdo, [
                'customer_id' => $values['customer_id'],
                'issue_date' => $values['issue_date'],
                'due_date' => $values['due_date'],
                'tax_rate' => $values['tax_rate'],
                'status' => $values['status'] ?: 'open',
                'currency' => $values['currency'] ?: 'EUR',
                'notes' => $combinedNotes,
                'tenant_id' => $tenantId,
            ], $items);

            header('Location: invoice-view.php?id=' . $invoiceId . '&status=created');
            exit;
        } catch (InvalidArgumentException $exception) {
            $formError = $exception->getMessage();
        } catch (Throwable $exception) {
            $formError = 'Beim Speichern ist ein Fehler aufgetreten. ' . $exception->getMessage();
        }
    }
}

$summaryMeta = [
    'payment_terms' => $values['payment_terms'] !== '' && isset($paymentTermsOptions[$values['payment_terms']]) ? $paymentTermsOptions[$values['payment_terms']] : 'Keine Angabe',
    'payment_method' => $values['payment_method'] !== '' && isset($paymentMethodOptions[$values['payment_method']]) ? $paymentMethodOptions[$values['payment_method']] : 'Keine Angabe',
    'bank_account' => $values['bank_account'] !== '' && isset($bankAccountOptions[$values['bank_account']]) ? $bankAccountOptions[$values['bank_account']] : 'Kein Konto',
    'document_template' => $values['document_template'] !== '' && isset($documentTemplates[$values['document_template']]) ? $documentTemplates[$values['document_template']] : 'Standard',
    'invoice_type' => $invoiceTypes[$values['invoice_type']]['label'] ?? 'Standardrechnung',
];

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
    <title><?= h(APP_NAME); ?> - Neue Rechnung</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Neue Rechnung erstellen</h1>
            <p>Erfassen Sie Rechnungsdetails, Zahlungsbedingungen und Positionen.</p>
        </div>
    </header>

    <div class="panel">
        <?php if ($formError): ?>
            <p class="alert alert--error"><?= h($formError); ?></p>
        <?php endif; ?>

        <form method="post" class="invoice-form" id="invoice-form">
            <div class="invoice-create__columns">
                <div class="invoice-create__main">
                    <section class="invoice-card">
                        <header class="invoice-card__header">
                            <h2>Rechnungsdetails</h2>
                            <p>Aussteller, Zahlungsbedingungen und Dokumentvorlage.</p>
                        </header>
                        <div class="invoice-card__body">
                            <div class="invoice-grid">
                                <div class="form-field form-field--full">
                                    <label>
                                        <span>Geschaeftspartner *</span>
                                        <select name="customer_id" required>
                                            <option value="">Geschaeftspartner auswaehlen</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?= (int)$customer['id']; ?>" <?= $values['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                                    <?= h($customer['company']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['customer_id'])): ?>
                                            <small class="form-error"><?= h($errors['customer_id']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>

                                <div class="form-field form-field--full">
                                    <span class="field-label">Rechnungstyp</span>
                                    <div class="pill-group">
                                        <?php foreach ($invoiceTypes as $key => $info): ?>
                                            <label class="pill-option <?= $info['disabled'] ? 'pill-option--disabled' : ''; ?>">
                                                <input type="radio" name="invoice_type" value="<?= h($key); ?>" <?= $values['invoice_type'] === $key ? 'checked' : ''; ?> <?= $info['disabled'] ? 'disabled' : ''; ?>>
                                                <span>
                                                    <strong><?= h($info['label']); ?></strong>
                                                    <?php if ($info['hint'] !== ''): ?>
                                                        <small><?= h($info['hint']); ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <span>Rechnungsdatum *</span>
                                        <div class="invoice-date-group">
                                            <input type="date" name="issue_date" value="<?= h($values['issue_date']); ?>" required>
                                            <button type="button" class="link-button" onclick="document.querySelector('[name=\'issue_date\']').value = '<?= date('Y-m-d'); ?>';">Jetzt</button>
                                        </div>
                                        <?php if (isset($errors['issue_date'])): ?>
                                            <small class="form-error"><?= h($errors['issue_date']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <span>Faellig bis *</span>
                                        <input type="date" name="due_date" value="<?= h($values['due_date']); ?>" required>
                                        <?php if (isset($errors['due_date'])): ?>
                                            <small class="form-error"><?= h($errors['due_date']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <span>Zahlungsbedingungen</span>
                                        <select name="payment_terms">
                                            <?php foreach ($paymentTermsOptions as $key => $label): ?>
                                                <option value="<?= h($key); ?>" <?= $values['payment_terms'] === $key ? 'selected' : ''; ?>><?= h($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <span>Zahlungsart</span>
                                        <select name="payment_method">
                                            <?php foreach ($paymentMethodOptions as $key => $label): ?>
                                                <option value="<?= h($key); ?>" <?= $values['payment_method'] === $key ? 'selected' : ''; ?>><?= h($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <span>Bankkonto</span>
                                        <select name="bank_account">
                                            <?php foreach ($bankAccountOptions as $key => $label): ?>
                                                <option value="<?= h($key); ?>" <?= $values['bank_account'] === $key ? 'selected' : ''; ?>><?= h($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <span>Dokumentvorlage</span>
                                        <select name="document_template">
                                            <?php foreach ($documentTemplates as $key => $label): ?>
                                                <option value="<?= h($key); ?>" <?= $values['document_template'] === $key ? 'selected' : ''; ?>><?= h($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <span>Status</span>
                                        <select name="status">
                                            <?php
                                            $statuses = [
                                                'draft' => 'Entwurf',
                                                'open' => 'Offen',
                                                'paid' => 'Bezahlt',
                                                'cancelled' => 'Storniert',
                                            ];
                                            foreach ($statuses as $key => $label): ?>
                                                <option value="<?= h($key); ?>" <?= $values['status'] === $key ? 'selected' : ''; ?>><?= h($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <span>Waehrung</span>
                                        <select name="currency">
                                            <?php foreach (['EUR', 'USD', 'CHF', 'GBP'] as $currency): ?>
                                                <option value="<?= h($currency); ?>" <?= $values['currency'] === $currency ? 'selected' : ''; ?>><?= h($currency); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <span>Steuersatz (%)</span>
                                        <input type="number" name="tax_rate" step="0.1" value="<?= h($values['tax_rate']); ?>">
                                        <?php if (isset($errors['tax_rate'])): ?>
                                            <small class="form-error"><?= h($errors['tax_rate']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="invoice-card">
                        <header class="invoice-card__header">
                            <h2>Rechnungspositionen</h2>
                            <p>Erfassen Sie Leistungen oder Produkte, Mengen und Preise.</p>
                        </header>
                        <div class="invoice-card__body">
                            <div class="table-wrapper table-wrapper--no-scroll">
                                <table class="invoice-items" id="invoice-items">
                                    <thead>
                                    <tr>
                                        <th>Beschreibung *</th>
                                        <th>Menge *</th>
                                        <th>Einzelpreis (<?= h($values['currency']); ?> ) *</th>
                                        <th>Zwischensumme</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($itemRows as $index => $item): ?>
                                        <tr>
                                            <td>
                                                <input type="text" name="item_description[]" value="<?= h($item['description']); ?>" placeholder="Leistung oder Produkt">
                                            </td>
                                            <td>
                                                <input type="number" min="0" step="0.01" name="item_quantity[]" value="<?= h($item['quantity']); ?>">
                                            </td>
                                            <td>
                                                <input type="number" min="0" step="0.01" name="item_unit_price[]" value="<?= h($item['unit_price']); ?>">
                                            </td>
                                            <td class="item-total">EUR 0,00</td>
                                            <td>
                                                <button type="button" class="link-button link-button--danger remove-row" <?= $index < 1 ? 'disabled' : ''; ?>>Entfernen</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="button button--ghost" id="add-item">Weitere Position hinzufuegen</button>
                        </div>
                    </section>
                </div>

                <div class="invoice-create__side">
                    <section class="invoice-summary-card">
                        <header>
                            <h2>Zusammenfassung</h2>
                            <p>Wichtige Eckdaten der Rechnung im Ueberblick.</p>
                        </header>
                        <div class="invoice-summary-card__body">
                            <div class="invoice-summary-card__totals">
                                <div>
                                    <span>Zwischensumme</span>
                                    <strong id="summary-subtotal">EUR 0,00</strong>
                                </div>
                                <div>
                                    <span>Steuer (<span id="summary-tax-rate"><?= h($values['tax_rate']); ?></span> %)</span>
                                    <strong id="summary-tax">EUR 0,00</strong>
                                </div>
                                <div>
                                    <span>Gesamtbetrag</span>
                                    <strong id="summary-total">EUR 0,00</strong>
                                </div>
                            </div>
                            <dl class="invoice-summary-card__meta">
                                <div>
                                    <dt>Rechnungstyp</dt>
                                    <dd><?= h($summaryMeta['invoice_type']); ?></dd>
                                </div>
                                <div>
                                    <dt>Zahlungsbedingungen</dt>
                                    <dd><?= h($summaryMeta['payment_terms']); ?></dd>
                                </div>
                                <div>
                                    <dt>Zahlungsart</dt>
                                    <dd><?= h($summaryMeta['payment_method']); ?></dd>
                                </div>
                                <div>
                                    <dt>Bankkonto</dt>
                                    <dd><?= h($summaryMeta['bank_account']); ?></dd>
                                </div>
                                <div>
                                    <dt>Dokumentvorlage</dt>
                                    <dd><?= h($summaryMeta['document_template']); ?></dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <section class="invoice-card">
                        <header class="invoice-card__header">
                            <h2>Anmerkungen</h2>
                            <p>Oeffentliche und interne Hinweise.</p>
                        </header>
                        <div class="invoice-card__body invoice-card__body--stack">
                            <div class="form-field">
                                <label>
                                    <span>Anmerkung (oeffentlich)</span>
                                    <textarea name="public_note" rows="3" placeholder="Optional fuer den Kunden sichtbar"><?= h($values['public_note']); ?></textarea>
                                </label>
                            </div>
                            <div class="form-field">
                                <label>
                                    <span>Anmerkung (privat)</span>
                                    <textarea name="private_note" rows="3" placeholder="Nur intern sichtbar"><?= h($values['private_note']); ?></textarea>
                                </label>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="form-actions form-actions--right">
                <a class="button button--ghost" href="invoices.php">Abbrechen</a>
                <button class="button" type="submit">Rechnung speichern</button>
            </div>
        </form>
    </div>
</main>
<script src="app.js"></script>
</body>
</html>
