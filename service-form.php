<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('CRM');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;

$errors = [];
$values = [
    'name' => '',
    'description' => '',
    'unit_price' => '0.00',
    'unit_cost' => '',
    'billing_type' => 'fixed',
    'active' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }

    if ($values['name'] === '') {
        $errors['name'] = 'Bitte Name angeben.';
    }

    if (!is_numeric($values['unit_price'])) {
        $errors['unit_price'] = 'Bitte gueltigen Preis eintragen.';
    }

    if ($values['unit_cost'] !== '' && !is_numeric($values['unit_cost'])) {
        $errors['unit_cost'] = 'Bitte gueltige Kosten eintragen.';
    }

    if (empty($errors)) {
        create_service($pdo, [
            'name' => $values['name'],
            'description' => $values['description'] ?: null,
            'unit_price' => (float)$values['unit_price'],
            'unit_cost' => $values['unit_cost'] !== '' ? (float)$values['unit_cost'] : null,
            'billing_type' => $values['billing_type'],
            'active' => $values['active'] === '1' ? 1 : 0,
            'tenant_id' => $tenantId,
        ]);

        header('Location: services.php?status=created');
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
    <title><?= h(APP_NAME); ?> - Leistung anlegen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <h1>Leistung anlegen</h1>
        <p>Definieren Sie wiederkehrende Services fuer Angebote und Rechnungen.</p>
    </header>

    <div class="panel">
        <form method="post" class="form-grid">
            <div class="form-field form-field--full">
                <label>
                    <span>Name *</span>
                    <input type="text" name="name" value="<?= h($values['name']); ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <small class="form-error"><?= h($errors['name']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field form-field--full">
                <label>
                    <span>Beschreibung</span>
                    <textarea name="description" rows="4"><?= h($values['description']); ?></textarea>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Abrechnung</span>
                    <select name="billing_type">
                        <option value="fixed" <?= $values['billing_type'] === 'fixed' ? 'selected' : ''; ?>>Pauschal</option>
                        <option value="hourly" <?= $values['billing_type'] === 'hourly' ? 'selected' : ''; ?>>Stundensatz</option>
                        <option value="subscription" <?= $values['billing_type'] === 'subscription' ? 'selected' : ''; ?>>Abo</option>
                    </select>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Verkaufspreis (EUR) *</span>
                    <input type="number" step="0.01" name="unit_price" value="<?= h($values['unit_price']); ?>" required>
                    <?php if (isset($errors['unit_price'])): ?>
                        <small class="form-error"><?= h($errors['unit_price']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Einstandspreis (EUR)</span>
                    <input type="number" step="0.01" name="unit_cost" value="<?= h($values['unit_cost']); ?>">
                    <?php if (isset($errors['unit_cost'])): ?>
                        <small class="form-error"><?= h($errors['unit_cost']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Status</span>
                    <select name="active">
                        <option value="1" <?= $values['active'] === '1' ? 'selected' : ''; ?>>Aktiv</option>
                        <option value="0" <?= $values['active'] === '0' ? 'selected' : ''; ?>>Inaktiv</option>
                    </select>
                </label>
            </div>

            <div class="form-actions">
                <a class="button button--ghost" href="services.php">Abbrechen</a>
                <button type="submit" class="button">Speichern</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
