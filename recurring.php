<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Buchhaltung');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$schedules = list_recurring_invoices($pdo, $tenantId);
$status = (string)($_GET['status'] ?? '');

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
    <title><?= h(APP_NAME); ?> - Abonnements</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Wiederkehrende Rechnungen</h1>
            <p>Maintenance, Hosting oder Managed Services automatisch abrechnen.</p>
        </div>
        <a class="button" href="recurring-form.php">Abo anlegen</a>
    </header>

    <?php if ($status === 'run'): ?>
        <p class="alert alert--success">Faellige Abonnements wurden verarbeitet.</p>
    <?php elseif ($status === 'created'): ?>
        <p class="alert alert--success">Abo wurde gespeichert.</p>
    <?php endif; ?>

    <div class="panel">
        <?php if (!$schedules): ?>
            <p>Keine Abonnements angelegt.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Leistung</th>
                        <th>Kunde</th>
                        <th>Intervall</th>
                        <th>Naechste Rechnung</th>
                        <th>Letzte Rechnung</th>
                        <th>Betrag</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td><?= h($schedule['service_overview']); ?></td>
                            <td><?= h($schedule['company']); ?></td>
                            <td><?= h($schedule['frequency']); ?></td>
                            <td><?= h(date('d.m.Y', strtotime((string)$schedule['next_run_at']))); ?></td>
                            <td><?= $schedule['last_run_at'] ? h(date('d.m.Y', strtotime((string)$schedule['last_run_at']))) : '-'; ?></td>
                            <td>EUR <?= number_format((float)$schedule['total'], 2, ',', '.'); ?></td>
                            <td><span class="badge badge--<?= $schedule['active'] ? 'paid' : 'draft'; ?>"><?= $schedule['active'] ? 'Aktiv' : 'Inaktiv'; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <form action="recurring-run.php" method="post" class="inline-form">
            <button type="submit" class="button">Faellige Rechnungen erzeugen</button>
        </form>
    </div>
</main>
</body>
</html>
