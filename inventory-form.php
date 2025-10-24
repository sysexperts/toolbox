<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Lager');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;

$errors = [];
$values = [
    'sku' => '',
    'name' => '',
    'quantity' => '1',
    'reorder_level' => '0',
    'unit_cost' => '0.00',
    'unit_price' => '0.00',
    'location' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }

    if ($values['sku'] === '') {
        $errors['sku'] = 'Bitte SKU angeben.';
    }

    if ($values['name'] === '') {
        $errors['name'] = 'Bitte Bezeichnung angeben.';
    }

    if (!ctype_digit((string)$values['quantity']) && !is_numeric($values['quantity'])) {
        $errors['quantity'] = 'Bitte gueltige Menge eintragen.';
    }

    if ($values['unit_cost'] !== '' && !is_numeric($values['unit_cost'])) {
        $errors['unit_cost'] = 'Bitte gueltige Kosten eintragen.';
    }

    if ($values['unit_price'] !== '' && !is_numeric($values['unit_price'])) {
        $errors['unit_price'] = 'Bitte gueltigen Verkaufspreis eintragen.';
    }

    if (empty($errors)) {
        create_inventory_item($pdo, [
            'sku' => $values['sku'],
            'name' => $values['name'],
            'quantity' => (float)$values['quantity'],
            'reorder_level' => $values['reorder_level'] !== '' ? (float)$values['reorder_level'] : 0,
            'unit_cost' => $values['unit_cost'] !== '' ? (float)$values['unit_cost'] : 0,
            'unit_price' => $values['unit_price'] !== '' ? (float)$values['unit_price'] : 0,
            'location' => $values['location'] ?: null,
            'tenant_id' => $tenantId,
        ]);

        header('Location: inventory.php');
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
    <title><?= h(APP_NAME); ?> - Inventar erfassen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <h1>Inventarposition anlegen</h1>
        <p>Hardware, Mietgeraete oder Ersatzteile erfassen.</p>
    </header>

    <div class="panel">
        <form method="post" class="form-grid">
            <div class="form-field">
                <label>
                    <span>SKU *</span>
                    <input type="text" name="sku" value="<?= h($values['sku']); ?>" required>
                    <?php if (isset($errors['sku'])): ?>
                        <small class="form-error"><?= h($errors['sku']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field form-field--full">
                <label>
                    <span>Bezeichnung *</span>
                    <input type="text" name="name" value="<?= h($values['name']); ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <small class="form-error"><?= h($errors['name']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Menge</span>
                    <input type="number" step="1" name="quantity" value="<?= h($values['quantity']); ?>">
                    <?php if (isset($errors['quantity'])): ?>
                        <small class="form-error"><?= h($errors['quantity']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Meldebestand</span>
                    <input type="number" step="1" name="reorder_level" value="<?= h($values['reorder_level']); ?>">
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
                    <span>Verkaufspreis (EUR)</span>
                    <input type="number" step="0.01" name="unit_price" value="<?= h($values['unit_price']); ?>">
                    <?php if (isset($errors['unit_price'])): ?>
                        <small class="form-error"><?= h($errors['unit_price']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field form-field--full">
                <label>
                    <span>Standort</span>
                    <input type="text" name="location" value="<?= h($values['location']); ?>" placeholder="Lager 1 / Regal A">
                </label>
            </div>

            <div class="form-actions">
                <a class="button button--ghost" href="inventory.php">Abbrechen</a>
                <button type="submit" class="button">Speichern</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
