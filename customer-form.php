<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('CRM');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
}

$isEdit = $customerId !== null;
$errors = [];

$fields = [
    'company' => '',
    'contact_name' => '',
    'email' => '',
    'phone' => '',
    'address_line' => '',
    'postal_code' => '',
    'city' => '',
    'country' => '',
    'status' => 'aktiv',
    'customer_number' => '',
    'notes' => '',
];
$statusOptions = ['aktiv', 'inaktiv', 'archiviert'];

if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $existing = get_customer($pdo, $customerId, $tenantId);
    if (!$existing) {
        header('Location: customers.php?status=missing');
        exit;
    }
    $fields = array_merge($fields, $existing);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $_) {
        $fields[$key] = trim((string)($_POST[$key] ?? ''));
    }
    if (!in_array($fields['status'], $statusOptions, true)) {
        $fields['status'] = 'aktiv';
    }
    $fields['tenant_id'] = $tenantId;

    if ($fields['company'] === '') {
        $errors['company'] = 'Bitte geben Sie den Firmennamen an.';
    }

    if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Bitte geben Sie eine gueltige E-Mail-Adresse an.';
    }

    if (empty($errors)) {
        if ($isEdit) {
            update_customer($pdo, $customerId, $fields);
            header('Location: customers.php?status=updated');
        } else {
            create_customer($pdo, $fields);
            header('Location: customers.php?status=created');
        }
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
    <title><?= h(APP_NAME); ?> - <?= $isEdit ? 'Kunde bearbeiten' : 'Neuer Kunde'; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <h1><?= $isEdit ? 'Kunde bearbeiten' : 'Neuen Kunden anlegen'; ?></h1>
        <p>Verwalten Sie Stammdaten und Kontaktdetails.</p>
    </header>

    <div class="panel">
        <form method="post" class="form-grid">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$customerId; ?>">
            <?php endif; ?>

            <div class="form-field form-field--full">
                <label>
                    <span>Firma *</span>
                    <input type="text" name="company" value="<?= h($fields['company']); ?>" required>
                    <?php if (isset($errors['company'])): ?>
                        <small class="form-error"><?= h($errors['company']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Ansprechpartner</span>
                    <input type="text" name="contact_name" value="<?= h($fields['contact_name']); ?>">
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>E-Mail</span>
                    <input type="email" name="email" value="<?= h($fields['email']); ?>">
                    <?php if (isset($errors['email'])): ?>
                        <small class="form-error"><?= h($errors['email']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Telefon</span>
                    <input type="text" name="phone" value="<?= h($fields['phone']); ?>">
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Status</span>
                    <select name="status">
                        <?php foreach ($statusOptions as $option): ?>
                            <option value="<?= h($option); ?>" <?= $fields['status'] === $option ? 'selected' : ''; ?>><?= h(ucfirst($option)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Kundennummer</span>
                    <input type="text" name="customer_number" value="<?= h($fields['customer_number']); ?>">
                </label>
            </div>

            <div class="form-field form-field--full">
                <label>
                    <span>Strasse und Hausnummer</span>
                    <input type="text" name="address_line" value="<?= h($fields['address_line']); ?>">
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>PLZ</span>
                    <input type="text" name="postal_code" value="<?= h($fields['postal_code']); ?>">
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Ort</span>
                    <input type="text" name="city" value="<?= h($fields['city']); ?>">
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Land</span>
                    <input type="text" name="country" value="<?= h($fields['country']); ?>">
                </label>
            </div>

            <div class="form-field form-field--full">
                <label>
                    <span>Notizen</span>
                    <textarea name="notes" rows="4"><?= h($fields['notes']); ?></textarea>
                </label>
            </div>

            <div class="form-actions">
                <a class="button button--ghost" href="customers.php">Abbrechen</a>
                <button class="button" type="submit"><?= $isEdit ? 'Speichern' : 'Anlegen'; ?></button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
