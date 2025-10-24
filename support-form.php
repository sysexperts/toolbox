<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Support');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$customers = get_customers($pdo, $tenantId);

$errors = [];
$values = [
    'customer_id' => '',
    'subject' => '',
    'status' => 'open',
    'priority' => 'medium',
    'description' => '',
    'assigned_to' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }

    if ($values['subject'] === '') {
        $errors['subject'] = 'Bitte Betreff eintragen.';
    }

    if (empty($errors)) {
        create_support_ticket($pdo, [
            'customer_id' => $values['customer_id'] ?: null,
            'subject' => $values['subject'],
            'status' => $values['status'],
            'priority' => $values['priority'],
            'description' => $values['description'],
            'assigned_to' => $values['assigned_to'],
            'tenant_id' => $tenantId,
        ]);

        header('Location: support.php');
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
    <title><?= h(APP_NAME); ?> - Ticket erfassen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <h1>Support Ticket erfassen</h1>
        <p>Stoerungen, Wartungsaufgaben oder Kundenanfragen dokumentieren.</p>
    </header>

    <div class="panel">
        <form method="post" class="form-grid">
            <div class="form-field">
                <label>
                    <span>Kunde</span>
                    <select name="customer_id">
                        <option value="">-- optional --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= (int)$customer['id']; ?>" <?= $values['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                <?= h($customer['company']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="form-field form-field--full">
                <label>
                    <span>Betreff *</span>
                    <input type="text" name="subject" value="<?= h($values['subject']); ?>" required>
                    <?php if (isset($errors['subject'])): ?>
                        <small class="form-error"><?= h($errors['subject']); ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Status</span>
                    <select name="status">
                        <option value="open" <?= $values['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?= $values['status'] === 'in_progress' ? 'selected' : ''; ?>>Work</option>
                        <option value="waiting" <?= $values['status'] === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                        <option value="done" <?= $values['status'] === 'done' ? 'selected' : ''; ?>>Done</option>
                    </select>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Prioritaet</span>
                    <select name="priority">
                        <option value="low" <?= $values['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?= $values['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?= $values['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?= $values['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <span>Bearbeiter</span>
                    <input type="text" name="assigned_to" value="<?= h($values['assigned_to']); ?>" placeholder="Teammitglied">
                </label>
            </div>

            <div class="form-field form-field--full">
                <label>
                    <span>Beschreibung</span>
                    <textarea name="description" rows="5" placeholder="Fehlerbild, ToDo, Links"><?= h($values['description']); ?></textarea>
                </label>
            </div>

            <div class="form-actions">
                <a class="button button--ghost" href="support.php">Abbrechen</a>
                <button type="submit" class="button">Ticket speichern</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
