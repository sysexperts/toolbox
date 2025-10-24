<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Support');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$tickets = get_support_tickets($pdo, $tenantId);
$customers = get_customers($pdo, $tenantId);
$statusFilter = (string)($_GET['status'] ?? '');

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
    <title><?= h(APP_NAME); ?> - Support</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Support & Tickets</h1>
            <p>Incidents, Change Requests und kleine Aufgaben sauber tracken.</p>
        </div>
        <a class="button" href="support-form.php">Ticket erfassen</a>
    </header>

    <div class="panel">
        <?php if (!$tickets): ?>
            <p>Noch keine Tickets vorhanden.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Kunde</th>
                        <th>Status</th>
                        <th>Prioritaet</th>
                        <th>Bearbeiter</th>
                        <th>Aktualisiert</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php if ($statusFilter && $ticket['status'] !== $statusFilter) { continue; } ?>
                        <tr>
                            <td><?= h($ticket['subject']); ?></td>
                            <td><?= h($ticket['company'] ?? '-'); ?></td>
                            <td><span class="badge badge--<?= h($ticket['status']); ?>"><?= h($ticket['status']); ?></span></td>
                            <td><span class="badge badge--<?= h($ticket['priority']); ?>"><?= h($ticket['priority']); ?></span></td>
                            <td><?= h($ticket['assigned_to'] ?? '-'); ?></td>
                            <td><?= h(date('d.m.Y H:i', strtotime((string)$ticket['created_at']))); ?></td>
                            <td>
                                <form action="ticket-status.php" method="post" class="inline-form">
                                    <input type="hidden" name="id" value="<?= (int)$ticket['id']; ?>">
                                    <select name="status">
                                        <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>Work</option>
                                        <option value="waiting" <?= $ticket['status'] === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                                        <option value="done" <?= $ticket['status'] === 'done' ? 'selected' : ''; ?>>Done</option>
                                    </select>
                                    <button type="submit" class="link-button">Update</button>
                                </form>
                            </td>
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
