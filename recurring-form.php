<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$customers = get_customers($pdo, $tenantId);
$services = get_services($pdo, $tenantId);

$errors = [];
$values = [
    'customer_id' => '',
    'service_overview' => '',
    'start_date' => date('Y-m-d'),
    'next_run_at' => date('Y-m-d'),
    'frequency' => 'monthly',
    'occurrences' => '',
    'tax_rate' => '19',
    'notes' => '',
];

$itemRows = [
    ['description' => '', 'quantity' => '1', 'unit_price' => '0'],
    ['description' => '', 'quantity' => '', 'unit_price' => ''],
    ['description' => '', 'quantity' => '', 'unit_price' => ''],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
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
        $errors['customer_id'] = 'Bitte Kunde waehlen.';
    }

    if ($values['service_overview'] === '') {
        $errors['service_overview'] = 'Bitte Beschreibung angeben.';
    }

    if ($values['tax_rate'] !== '' && !is_numeric($values['tax_rate'])) {
        $errors['tax_rate'] = 'Bitte gueltigen Steuersatz eingeben.';
    }

    if (empty($errors)) {
        try {
            $items = [];
            foreach ($itemRows as $row) {
                $quantity = (float)($row['quantity'] ?: 0);
                $unitPrice = (float)($row['unit_price'] ?: 0);
                $description = trim($row['description']);
                if ($description === '' || $quantity <= 0 || $unitPrice <= 0) {
                    continue;
                }
                $items[] = [
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                ];
            }

            if (empty($items)) {
                throw new InvalidArgumentException('Bitte gueltige Positionen angeben.');
            }

            create_recurring_invoice($pdo, [
                'customer_id' => (int)$values['customer_id'],
                'service_overview' => $values['service_overview'],
                'start_date' => $values['start_date'],
                'frequency' => $values['frequency'],
                'occurrences' => $values['occurrences'],
                'tax_rate' => (float)$values['tax_rate'],
                'notes' => $values['notes'],
                'next_run_at' => $values['next_run_at'],
                'tenant_id' => $tenantId,
            ], $items);

            header('Location: recurring.php?status=created');
            exit;
        } catch (Throwable $exception) {
            $errors['form'] = $exception->getMessage();
        }
    }
}

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
    <title><?= h(APP_NAME); ?> - Abo anlegen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Abo / Managed Service</h1>
            <p>Definieren Sie wiederkehrende Leistungen fuer automatische Rechnungen.</p>
        </div>
    </header>

    <div class="panel">
        <?php if (isset($errors['form'])): ?>
            <p class="alert alert--error"><?= h($errors['form']); ?></p>
        <?php endif; ?>

        <form method="post" class="invoice-form" id="invoice-form">
            <div class="form-grid">
                <div class="form-field">
                    <label>
                        <span>Kunde *</span>
                        <select name="customer_id" required>
                            <option value="">-- Waehlen --</option>
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
                    <label>
                        <span>Kurze Leistungsbeschreibung *</span>
                        <input type="text" name="service_overview" value="<?= h($values['service_overview']); ?>" list="service-templates" required>
                        <?php if (isset($errors['service_overview'])): ?>
                            <small class="form-error"><?= h($errors['service_overview']); ?></small>
                        <?php endif; ?>
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Startdatum</span>
                        <input type="date" name="start_date" value="<?= h($values['start_date']); ?>">
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Naechste Faelligkeit</span>
                        <input type="date" name="next_run_at" value="<?= h($values['next_run_at']); ?>">
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Intervall</span>
                        <select name="frequency">
                            <option value="monthly" <?= $values['frequency'] === 'monthly' ? 'selected' : ''; ?>>Monatlich</option>
                            <option value="quarterly" <?= $values['frequency'] === 'quarterly' ? 'selected' : ''; ?>>Quartal</option>
                            <option value="biweekly" <?= $values['frequency'] === 'biweekly' ? 'selected' : ''; ?>>Alle 2 Wochen</option>
                            <option value="weekly" <?= $values['frequency'] === 'weekly' ? 'selected' : ''; ?>>Woechentlich</option>
                            <option value="yearly" <?= $values['frequency'] === 'yearly' ? 'selected' : ''; ?>>Jaehrlich</option>
                        </select>
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Anzahl Durchlaeufe</span>
                        <input type="number" name="occurrences" min="1" value="<?= h($values['occurrences']); ?>" placeholder="Leer = unbegrenzt">
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Steuersatz (%)</span>
                        <input type="number" step="0.1" name="tax_rate" value="<?= h($values['tax_rate']); ?>">
                        <?php if (isset($errors['tax_rate'])): ?>
                            <small class="form-error"><?= h($errors['tax_rate']); ?></small>
                        <?php endif; ?>
                    </label>
                </div>
            </div>

            <h2 class="section-title">Positionen</h2>
            <div class="table-wrapper table-wrapper--no-scroll">
                <table class="invoice-items" id="invoice-items">
                    <thead>
                    <tr>
                        <th>Beschreibung *</th>
                        <th>Menge *</th>
                        <th>Einzelpreis (EUR) *</th>
                        <th>Zwischensumme</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($itemRows as $index => $item): ?>
                        <tr>
                            <td>
                                <input type="text" name="item_description[]" value="<?= h($item['description']); ?>" placeholder="Service / Produkt">
                            </td>
                            <td>
                                <input type="number" min="0" step="0.01" name="item_quantity[]" value="<?= h($item['quantity']); ?>">
                            </td>
                            <td>
                                <input type="number" min="0" step="0.01" name="item_unit_price[]" value="<?= h($item['unit_price']); ?>">
                            </td>
                            <td class="item-total">EUR 0,00</td>
                            <td>
                                <button type="button" class="link-button link-button--danger remove-row" <?= $index === 0 ? 'disabled' : ''; ?>>Entfernen</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="button button--ghost" id="add-item">Weitere Position</button>

            <div class="invoice-summary">
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

            <div class="form-field form-field--full">
                <label>
                    <span>Internes Memo</span>
                    <textarea name="notes" rows="4" placeholder="Optionale Hinweise fuer eigene Ablage"><?= h($values['notes']); ?></textarea>
                </label>
            </div>

            <div class="form-actions">
                <a class="button button--ghost" href="recurring.php">Abbrechen</a>
                <button class="button" type="submit">Abo speichern</button>
            </div>
        </form>
    </div>
</main>
<datalist id="service-templates">
    <?php foreach ($services as $service): ?>
        <option value="<?= h($service['name']); ?>">
    <?php endforeach; ?>
</datalist>
<script src="app.js"></script>
</body>
</html>
