<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_login();

$pdo = get_database();
$user = current_user();
$tenantId = isset($user['tenant_id']) ? (int)$user['tenant_id'] : null;
$summary = fetch_dashboard_summary($pdo, $tenantId);
$moduleAccess = [
    'crm' => user_has_module('CRM'),
    'accounting' => user_has_module('Buchhaltung'),
    'projects' => user_has_module('Projekte'),
    'support' => user_has_module('Support'),
];

$statusMessage = (string)($_GET['status'] ?? '');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function euro(float $value): string
{
    return 'EUR ' . number_format($value, 2, ',', '.');
}

$statCards = [];
if ($moduleAccess['crm']) {
    $statCards[] = [
        'label' => 'Kunden',
        'value' => number_format((int)$summary['customers'], 0, ',', '.'),
    ];
}
if ($moduleAccess['accounting']) {
    $statCards[] = [
        'label' => 'Offene Rechnungen',
        'value' => number_format((int)$summary['open_invoices'], 0, ',', '.'),
    ];
    $statCards[] = [
        'label' => 'Ueberfaellige Rechnungen',
        'value' => number_format((int)$summary['overdue_invoices'], 0, ',', '.'),
    ];
    $statCards[] = [
        'label' => 'Umsatz (Jahr)',
        'value' => euro((float)$summary['revenue_ytd']),
    ];
    $statCards[] = [
        'label' => 'Ausgaben (Jahr)',
        'value' => euro((float)$summary['expenses_ytd']),
    ];
}
if ($moduleAccess['projects']) {
    $statCards[] = [
        'label' => 'Aktive Projekte',
        'value' => number_format((int)$summary['active_projects'], 0, ',', '.'),
    ];
}
if ($moduleAccess['support']) {
    $statCards[] = [
        'label' => 'Offene Tickets',
        'value' => number_format((int)$summary['open_tickets'], 0, ',', '.'),
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME); ?> - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header content__header--compact">
        <div>
            <h1>Dashboard</h1>
            <p>Schneller Blick auf Finanzen, Projekte und Support.</p>
        </div>
        <div class="header-meta">
            <span>Stand: <?= h(date('d.m.Y H:i')); ?></span>
        </div>
    </header>

    <?php if ($statusMessage === 'forbidden'): ?>
        <p class="alert alert--error">Sie besitzen keine Berechtigung fuer den Adminbereich.</p>
    <?php endif; ?>

    <?php if (!empty($statCards)): ?>
        <section class="cards cards--grid-4">
            <?php foreach ($statCards as $card): ?>
                <article class="stat-card">
                    <span class="stat-card__label"><?= h($card['label']); ?></span>
                    <span class="stat-card__value"><?= h($card['value']); ?></span>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="dashboard-grid__column">
            <?php if ($moduleAccess['accounting']): ?>
            <section class="panel">
                <div class="panel__header">
                    <div>
                        <h2>Letzte Rechnungen</h2>
                        <p>Neu erstellte Rechnungen inklusive Status.</p>
                    </div>
                    <a class="button" href="invoice-create.php">Neue Rechnung</a>
                </div>
                <?php if (empty($summary['recent_invoices'])): ?>
                    <p>Noch keine Rechnungen vorhanden.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table table--compact">
                            <thead>
                            <tr>
                                <th>Rechnung</th>
                                <th>Kunde</th>
                                <th>Ausgestellt</th>
                                <th>Faellig</th>
                                <th>Summe</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($summary['recent_invoices'] as $invoice): ?>
                                <tr>
                                    <td><a class="table-link" href="invoice-view.php?id=<?= (int)$invoice['id']; ?>"><?= h($invoice['invoice_number']); ?></a></td>
                                    <td><?= h($invoice['company']); ?></td>
                                    <td><?= h(date('d.m.Y', strtotime((string)$invoice['issue_date']))); ?></td>
                                    <td><?= h(date('d.m.Y', strtotime((string)$invoice['due_date']))); ?></td>
                                    <td><?= euro((float)$invoice['total']); ?></td>
                                    <td><span class="badge badge--<?= h($invoice['status']); ?>"><?= h($invoice['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($moduleAccess['support']): ?>
            <section class="panel">
                <div class="panel__header">
                    <div>
                        <h2>Aktuelle Support Tickets</h2>
                        <p>Schneller Blick auf neue oder ungeplante Anfragen.</p>
                    </div>
                    <a class="button button--ghost" href="support-form.php">Ticket erfassen</a>
                </div>
                <?php if (empty($summary['recent_tickets'])): ?>
                    <p>Momentan sind keine Tickets erfasst.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table table--compact">
                            <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Kunde</th>
                                <th>Prioritaet</th>
                                <th>Status</th>
                                <th>Erstellt</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($summary['recent_tickets'] as $ticket): ?>
                                <tr>
                                    <td><?= h($ticket['subject']); ?></td>
                                    <td><?= h($ticket['company'] ?? 'Unbekannt'); ?></td>
                                    <td><span class="badge badge--<?= h($ticket['priority']); ?>"><?= h($ticket['priority']); ?></span></td>
                                    <td><span class="badge badge--<?= h($ticket['status']); ?>"><?= h($ticket['status']); ?></span></td>
                                    <td><?= h(date('d.m.Y H:i', strtotime((string)$ticket['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>

        <div class="dashboard-grid__column">
            <?php if ($moduleAccess['accounting']): ?>
            <section class="panel">
                <div class="panel__header">
                    <div>
                        <h2>Neueste Ausgaben</h2>
                        <p>Zuletzt gebuchte Kostenpositionen.</p>
                    </div>
                </div>
                <?php if (empty($summary['recent_expenses'])): ?>
                    <p>Keine Ausgaben erfasst.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table table--compact">
                            <thead>
                            <tr>
                                <th>Lieferant</th>
                                <th>Kategorie</th>
                                <th>Betrag</th>
                                <th>Datum</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($summary['recent_expenses'] as $expense): ?>
                                <tr>
                                    <td><?= h($expense['vendor']); ?></td>
                                    <td><?= h($expense['category']); ?></td>
                                    <td><?= euro((float)$expense['total']); ?></td>
                                    <td><?= h(date('d.m.Y', strtotime((string)$expense['expense_date']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($moduleAccess['projects']): ?>
            <section class="panel">
                <div class="panel__header">
                    <div>
                        <h2>Projektstatus</h2>
                        <p>Anstehende Deadlines und offene Arbeiten.</p>
                    </div>
                    <a class="button button--ghost" href="projects.php">Alle Projekte</a>
                </div>
                <?php if (empty($summary['recent_projects'])): ?>
                    <p>Noch keine Projekte angelegt.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table table--compact">
                            <thead>
                            <tr>
                                <th>Projekt</th>
                                <th>Kunde</th>
                                <th>Status</th>
                                <th>Faellig</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($summary['recent_projects'] as $project): ?>
                                <tr>
                                    <td><?= h($project['name']); ?></td>
                                    <td><?= h($project['company'] ?? 'Intern'); ?></td>
                                    <td><span class="badge badge--<?= h($project['status']); ?>"><?= h($project['status']); ?></span></td>
                                    <td><?= $project['due_date'] ? h(date('d.m.Y', strtotime((string)$project['due_date']))) : 'Offen'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($moduleAccess['accounting']): ?>
            <section class="panel">
                <div class="panel__header">
                    <div>
                        <h2>Aktuelle Zahlungen</h2>
                        <p>Zuletzt eingegangene Kundenzahlungen.</p>
                    </div>
                </div>
                <?php if (empty($summary['recent_payments'])): ?>
                    <p>Keine Zahlungen erfasst.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table table--compact">
                            <thead>
                            <tr>
                                <th>Betrag</th>
                                <th>Datum</th>
                                <th>Methode</th>
                                <th>Rechnung</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($summary['recent_payments'] as $payment): ?>
                                <tr>
                                    <td><?= euro((float)$payment['amount']); ?></td>
                                    <td><?= h(date('d.m.Y', strtotime((string)$payment['payment_date']))); ?></td>
                                    <td><?= h($payment['method'] ?? '-'); ?></td>
                                    <td><?= h($payment['invoice_number']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
