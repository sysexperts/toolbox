<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Projekte');

$projectId = (int)($_GET['id'] ?? 0);
if ($projectId <= 0) {
    header('Location: projects.php');
    exit;
}

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$project = get_project_detail($pdo, $projectId, $tenantId);

if (!$project) {
    header('Location: projects.php');
    exit;
}

$timeStatus = (string)($_GET['status'] ?? '');

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
    <title><?= h(APP_NAME); ?> - Projekt <?= h($project['name']); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1><?= h($project['name']); ?></h1>
            <p>Status: <?= h($project['status']); ?> <?= $project['company'] ? '- Kunde: ' . h($project['company']) : ''; ?></p>
        </div>
        <div class="header-actions">
            <a class="button button--ghost" href="project-form.php?id=<?= (int)$project['id']; ?>">Bearbeiten</a>
            <a class="button button--ghost" href="projects.php">Zurueck</a>
        </div>
    </header>

    <?php if ($timeStatus === 'time-created'): ?>
        <p class="alert alert--success">Zeiterfassung gespeichert.</p>
    <?php endif; ?>

    <section class="panel">
        <div class="project-meta">
            <div>
                <span class="detail-label">Start</span>
                <strong><?= $project['start_date'] ? h(date('d.m.Y', strtotime((string)$project['start_date']))) : 'offen'; ?></strong>
            </div>
            <div>
                <span class="detail-label">Faellig</span>
                <strong><?= $project['due_date'] ? h(date('d.m.Y', strtotime((string)$project['due_date']))) : 'offen'; ?></strong>
            </div>
            <div>
                <span class="detail-label">Budget Stunden</span>
                <strong><?= $project['budget_hours'] !== null ? h(number_format((float)$project['budget_hours'], 2, ',', '.')) : 'n/a'; ?></strong>
            </div>
            <div>
                <span class="detail-label">Gebucht</span>
                <strong><?= h(number_format((float)$project['totals']['total_hours'], 2, ',', '.')); ?> h</strong>
            </div>
            <div>
                <span class="detail-label">Billable</span>
                <strong><?= h(number_format((float)$project['totals']['billable_hours'], 2, ',', '.')); ?> h</strong>
            </div>
            <div>
                <span class="detail-label">Stundensatz</span>
                <strong><?= $project['hourly_rate'] !== null ? 'EUR ' . h(number_format((float)$project['hourly_rate'], 2, ',', '.')) : 'n/a'; ?></strong>
            </div>
        </div>
        <?php if (!empty($project['notes'])): ?>
            <p class="project-notes"><?= nl2br(h($project['notes'])); ?></p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel__header">
            <div>
                <h2>Zeiterfassung</h2>
                <p>Dokumentieren Sie erbrachte Leistungen.</p>
            </div>
        </div>

        <form action="time-entry-create.php" method="post" class="form-grid form-grid--compact">
            <input type="hidden" name="project_id" value="<?= (int)$project['id']; ?>">
            <div class="form-field">
                <label>
                    <span>Mitarbeiter</span>
                    <input type="text" name="user_name" placeholder="Techniker" required>
                </label>
            </div>
            <div class="form-field">
                <label>
                    <span>Datum</span>
                    <input type="date" name="entry_date" value="<?= h(date('Y-m-d')); ?>" required>
                </label>
            </div>
            <div class="form-field">
                <label>
                    <span>Stunden</span>
                    <input type="number" name="hours" step="0.25" min="0.25" value="1" required>
                </label>
            </div>
            <div class="form-field">
                <label>
                    <span>Billable</span>
                    <select name="billable">
                        <option value="1">Abrechenbar</option>
                        <option value="0">Intern</option>
                    </select>
                </label>
            </div>
            <div class="form-field form-field--full">
                <label>
                    <span>Beschreibung</span>
                    <input type="text" name="description" placeholder="Arbeiten oder Ticket Referenz">
                </label>
            </div>
            <div class="form-actions">
                <button class="button" type="submit">Zeit erfassen</button>
            </div>
        </form>

        <?php if (empty($project['time_entries'])): ?>
            <p>Noch keine Zeiten erfasst.</p>
        <?php else: ?>
            <div class="table-wrapper table-wrapper--no-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Mitarbeiter</th>
                        <th>Stunden</th>
                        <th>Typ</th>
                        <th>Beschreibung</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($project['time_entries'] as $entry): ?>
                        <tr>
                            <td><?= h(date('d.m.Y', strtotime((string)$entry['entry_date']))); ?></td>
                            <td><?= h($entry['user_name']); ?></td>
                            <td><?= h(number_format((float)$entry['hours'], 2, ',', '.')); ?></td>
                            <td><span class="badge badge--<?= $entry['billable'] ? 'paid' : 'draft'; ?>"><?= $entry['billable'] ? 'Billable' : 'Intern'; ?></span></td>
                            <td><?= h($entry['description'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
