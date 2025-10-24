<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$expenses = list_expenses($pdo, $tenantId);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$total = array_reduce(
    $expenses,
    static fn(float $carry, array $expense): float => $carry + (float)$expense['total'],
    0.0
);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME); ?> - Ausgaben</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Ausgaben & Beschaffung</h1>
            <p>Verfolgen Sie laufende Kosten fuer Lizenzen, Hardware oder Subunternehmer.</p>
        </div>
        <a class="button" href="expense-form.php">Ausgabe erfassen</a>
    </header>

    <section class="panel">
        <div class="summary-row summary-row--total">
            <span>Gesamtausgaben</span>
            <strong>EUR <?= number_format($total, 2, ',', '.'); ?></strong>
        </div>
    </section>

    <div class="panel">
        <?php if (!$expenses): ?>
            <p>Noch keine Ausgaben erfasst.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Lieferant</th>
                        <th>Kategorie</th>
                        <th>Betrag</th>
                        <th>Steuer</th>
                        <th>Gesamt</th>
                        <th>Methode</th>
                        <th>Notiz</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?= h(date('d.m.Y', strtotime((string)$expense['expense_date']))); ?></td>
                            <td><?= h($expense['vendor']); ?></td>
                            <td><?= h($expense['category']); ?></td>
                            <td>EUR <?= number_format((float)$expense['amount'], 2, ',', '.'); ?></td>
                            <td><?= h(number_format((float)$expense['tax_total'], 2, ',', '.')); ?> (<?= h(number_format((float)$expense['tax_rate'], 2, ',', '.')); ?> %)</td>
                            <td>EUR <?= number_format((float)$expense['total'], 2, ',', '.'); ?></td>
                            <td><?= h($expense['payment_method'] ?? '-'); ?></td>
                            <td><?= h($expense['notes'] ?? '-'); ?></td>
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
