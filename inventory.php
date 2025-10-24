<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Lager');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$items = get_inventory($pdo, $tenantId);

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
    <title><?= h(APP_NAME); ?> - Inventar</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Inventar & Ersatzteile</h1>
            <p>Server, Switches oder Mietgeraete immer im Blick behalten.</p>
        </div>
        <a class="button" href="inventory-form.php">Bestand erfassen</a>
    </header>

    <div class="panel">
        <?php if (!$items): ?>
            <p>Noch keine Lagerpositionen angelegt.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Bezeichnung</th>
                        <th>Menge</th>
                        <th>Schwelle</th>
                        <th>EK</th>
                        <th>VK</th>
                        <th>Standort</th>
                        <th>Aktualisiert</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= h($item['sku']); ?></td>
                            <td><?= h($item['name']); ?></td>
                            <td><?= h(number_format((float)$item['quantity'], 0, ',', '.')); ?></td>
                            <td><?= h(number_format((float)$item['reorder_level'], 0, ',', '.')); ?></td>
                            <td>EUR <?= number_format((float)$item['unit_cost'], 2, ',', '.'); ?></td>
                            <td>EUR <?= number_format((float)$item['unit_price'], 2, ',', '.'); ?></td>
                            <td><?= h($item['location'] ?? '-'); ?></td>
                            <td><?= h(date('d.m.Y H:i', strtotime((string)$item['updated_at']))); ?></td>
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
