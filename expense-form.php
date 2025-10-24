<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;

$errors = [];
$values = [
    'vendor' => '',
    'category' => 'Software',
    'amount' => '0.00',
    'tax_rate' => '19',
    'expense_date' => date('Y-m-d'),
    'payment_method' => '',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }

    if ($values['vendor'] === '') {
        $errors['vendor'] = 'Bitte Lieferanten angeben.';
    }

    if (!is_numeric($values['amount'])) {
        $errors['amount'] = 'Bitte gueltigen Betrag erfassen.';
    }

    if ($values['tax_rate'] !== '' && !is_numeric($values['tax_rate'])) {
        $errors['tax_rate'] = 'Bitte gueltigen Steuersatz eingeben.';
    }

    if (empty($errors)) {
        create_expense($pdo, [
            'vendor' => $values['vendor'],
            'category' => $values['category'] ?: 'Allgemein',
            'amount' => $values['amount'],
            'tax_rate' => $values['tax_rate'] ?: 0,
            'expense_date' => $values['expense_date'],
            'payment_method' => $values['payment_method'] ?: null,
            'notes' => $values['notes'] ?: null,
            'tenant_id' => $tenantId,
        ]);

        header('Location: expenses.php');
        exit;
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
    <title><?= h(APP_NAME); ?> - Ausgabe erfassen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <h1>Ausgabe erfassen</h1>
        <p>Lizenzverlaengerungen, Hardware-Kaeufe oder Reisekosten festhalten.</p>
    </header>

    <div class="panel">
        <form method="post" class="form-grid">
            <div class="form-field form-field--full">
                <label>
                    <span>Lieferant *</span>
                    <input type="text" name="vendor" value="<?= h($values['vendor']); ?>" required>
                    <?php if (isset($errors['vendor'])): ?>
                        <small class="form-error"><?= h($errors['vendor']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Kategorie</span>
                    <input type="text" name="category" value="<?= h($values['category']); ?>">
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Betrag netto (EUR) *</span>
                    <input type="number" name="amount" step="0.01" value="<?= h($values['amount']); ?>" required>
                    <?php if (isset($errors['amount'])): ?>
                        <small class="form-error"><?= h($errors['amount']); ?></small>
                    <?php endif; ?>
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

            <div class="form-field">
                <label>
                    <span>Datum</span>
                    <input type="date" name="expense_date" value="<?= h($values['expense_date']); ?>" required>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Zahlungsart</span>
                    <input type="text" name="payment_method" value="<?= h($values['payment_method']); ?>" placeholder="Karte, SEPA, PayPal">
                </label>
            </div>

            <div class="form-field form-field--full">
                <label>
                    <span>Notiz</span>
                    <textarea name="notes" rows="3"><?= h($values['notes']); ?></textarea>
                </label>
            </div>

            <div class="form-actions">
                <a class="button button--ghost" href="expenses.php">Abbrechen</a>
                <button type="submit" class="button">Speichern</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
