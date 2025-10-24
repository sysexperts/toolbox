<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Projekte');

$pdo = get_database();
$user = current_user();
$tenantId = isset($user['tenant_id']) ? (int)$user['tenant_id'] : null;
$projects = get_projects($pdo, $tenantId);
$status = (string)($_GET['status'] ?? '');

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'status' => (string)($_GET['filter_status'] ?? ''),
    'client' => (string)($_GET['client'] ?? ''),
    'timeline' => (string)($_GET['timeline'] ?? ''),
];

$statusLabels = [
    'planning' => 'Planung',
    'in_progress' => 'In Arbeit',
    'paused' => 'Pausiert',
    'completed' => 'Abgeschlossen',
    'cancelled' => 'Storniert',
];

$clientOptions = [];
foreach ($projects as $project) {
    $company = trim((string)($project['company'] ?? ''));
    if ($company !== '') {
        $clientOptions[$company] = true;
    }
}
$clientOptions = array_keys($clientOptions);
sort($clientOptions, SORT_NATURAL | SORT_FLAG_CASE);

$hasFilters = array_reduce($filters, static function (bool $carry, string $value): bool {
    return $carry || $value !== '';
}, false);

$metrics = [
    'total' => count($projects),
    'active' => 0,
    'overdue' => 0,
    'upcoming' => 0,
    'avg_budget' => 0.0,
    'avg_rate' => 0.0,
];

$statusBreakdown = array_fill_keys(array_keys($statusLabels), 0);

$today = new DateTimeImmutable('today');
$upcomingThreshold = $today->modify('+7 days');
$nextDeadline = null;
$nextDeadlineProject = null;

$budgetSum = 0.0;
$budgetCount = 0;
$rateSum = 0.0;
$rateCount = 0;

foreach ($projects as $project) {
    $statusKey = (string)($project['status'] ?? '');
    if (!isset($statusBreakdown[$statusKey])) {
        $statusBreakdown[$statusKey] = 0;
    }
    $statusBreakdown[$statusKey]++;

    $isClosed = in_array($statusKey, ['completed', 'cancelled'], true);
    if (!$isClosed) {
        $metrics['active']++;
    }

    $dueDate = null;
    if (!empty($project['due_date'])) {
        try {
            $dueDate = new DateTimeImmutable((string)$project['due_date']);
        } catch (Throwable $exception) {
            $dueDate = null;
        }
    }

    if ($dueDate !== null && !$isClosed) {
        if ($dueDate < $today) {
            $metrics['overdue']++;
        } elseif ($dueDate <= $upcomingThreshold) {
            $metrics['upcoming']++;
        }

        if ($dueDate >= $today && ($nextDeadline === null || $dueDate < $nextDeadline)) {
            $nextDeadline = $dueDate;
            $nextDeadlineProject = (string)$project['name'];
        }
    }

    $budgetValue = $project['budget_hours'];
    if ($budgetValue !== null && $budgetValue !== '') {
        $budgetSum += (float)$budgetValue;
        $budgetCount++;
    }

    $rateValue = $project['hourly_rate'];
    if ($rateValue !== null && $rateValue !== '') {
        $rateSum += (float)$rateValue;
        $rateCount++;
    }
}

if ($budgetCount > 0) {
    $metrics['avg_budget'] = $budgetSum / $budgetCount;
}

if ($rateCount > 0) {
    $metrics['avg_rate'] = $rateSum / $rateCount;
}

$deadlineLabel = $nextDeadline ? $nextDeadline->format('d.m.Y') : 'Keine Termine';
$deadlineProject = $nextDeadlineProject;

$filteredProjects = array_values(array_filter(
    $projects,
    static function (array $project) use ($filters, $today, $upcomingThreshold): bool {
        $statusKey = (string)($project['status'] ?? '');
        $company = trim((string)($project['company'] ?? ''));
        $isClosed = in_array($statusKey, ['completed', 'cancelled'], true);

        if ($filters['status'] !== '' && strcasecmp($statusKey, $filters['status']) !== 0) {
            return false;
        }

        if ($filters['client'] !== '' && strcasecmp($company, $filters['client']) !== 0) {
            return false;
        }

        $dueDate = null;
        if (!empty($project['due_date'])) {
            try {
                $dueDate = new DateTimeImmutable((string)$project['due_date']);
            } catch (Throwable $exception) {
                $dueDate = null;
            }
        }

        if ($filters['timeline'] === 'overdue') {
            if ($dueDate === null || $dueDate >= $today || $isClosed) {
                return false;
            }
        } elseif ($filters['timeline'] === 'next7') {
            if ($dueDate === null || $dueDate < $today || $dueDate > $upcomingThreshold || $isClosed) {
                return false;
            }
        } elseif ($filters['timeline'] === 'no_due') {
            if ($dueDate !== null) {
                return false;
            }
        }

        if ($filters['q'] !== '') {
            $needle = mb_strtolower($filters['q']);
            $haystack = mb_strtolower(
                (string)$project['name'] . ' ' .
                $company . ' ' .
                $statusKey . ' ' .
                (string)($project['service_type'] ?? '')
            );
            if (mb_strpos($haystack, $needle) === false) {
                return false;
            }
        }

        return true;
    }
));

usort(
    $filteredProjects,
    static function (array $a, array $b): int {
        $dueA = !empty($a['due_date']) ? strtotime((string)$a['due_date']) : null;
        $dueB = !empty($b['due_date']) ? strtotime((string)$b['due_date']) : null;

        if ($dueA === $dueB) {
            return strcasecmp((string)$a['name'], (string)$b['name']);
        }

        if ($dueA === null) {
            return 1;
        }
        if ($dueB === null) {
            return -1;
        }

        return $dueA <=> $dueB;
    }
);

$projectMap = [];
foreach ($filteredProjects as $project) {
    $projectMap[$project['id']] = $project;
}

$activities = [];
if ($projectMap) {
    $placeholders = implode(',', array_fill(0, count($projectMap), '?'));

    $activitiesSql = "SELECT project_id, user_name, entry_date, hours FROM time_entries WHERE project_id IN ($placeholders)";
    $activitiesStmt = $pdo->prepare($activitiesSql);
    $activitiesStmt->execute(array_keys($projectMap));
    $activityRows = $activitiesStmt->fetchAll();

    foreach ($activityRows as $row) {
        $projectId = (int)$row['project_id'];
        if (!isset($activities[$projectId])) {
            $activities[$projectId] = [
                'recent' => [],
                'total_hours' => 0.0,
            ];
        }

        $activities[$projectId]['total_hours'] += (float)$row['hours'];

        $activities[$projectId]['recent'][] = [
            'user' => $row['user_name'],
            'date' => $row['entry_date'],
            'hours' => (float)$row['hours'],
        ];
    }

    foreach ($activities as &$activity) {
        usort($activity['recent'], static function (array $a, array $b): int {
            return strtotime((string)$b['date']) <=> strtotime((string)$a['date']);
        });
        $activity['recent'] = array_slice($activity['recent'], 0, 3);
    }
    unset($activity);
}

$defaultEntryName = trim((string)($user['name'] ?? ''));
if ($defaultEntryName === '') {
    $defaultEntryName = trim((string)($user['email'] ?? 'Mitarbeiter'));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_money(float $value): string
{
    return number_format($value, 2, ',', '.') . ' EUR';
}

function format_hours(?float $value): string
{
    return $value !== null ? number_format($value, 1, ',', '.') . ' Std' : '-';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME); ?> - Projekte</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Projekte & Services</h1>
            <p>Behalten Sie Fortschritt, Budget und Deadlines aller Kundenprojekte im Blick.</p>
        </div>
        <a class="button" href="project-form.php">Projekt anlegen</a>
    </header>

    <?php if ($status === 'created'): ?>
        <p class="alert alert--success">Projekt wurde angelegt.</p>
    <?php elseif ($status === 'updated'): ?>
        <p class="alert alert--success">Projekt wurde aktualisiert.</p>
    <?php endif; ?>

    <section class="cards cards--grid-4 cards--stretch">
        <article class="stat-card">
            <span class="stat-card__label">Projekte gesamt</span>
            <span class="stat-card__value"><?= number_format($metrics['total'], 0, ',', '.'); ?></span>
            <span class="stat-card__hint text-muted">Aktive: <?= number_format($metrics['active'], 0, ',', '.'); ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Ueberfaellige Projekte</span>
            <span class="stat-card__value <?= $metrics['overdue'] > 0 ? 'text-negative' : 'text-positive'; ?>"><?= number_format($metrics['overdue'], 0, ',', '.'); ?></span>
            <span class="stat-card__hint text-muted">Faellig in 7 Tagen: <?= number_format($metrics['upcoming'], 0, ',', '.'); ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Durchschnitt Budget (Std)</span>
            <span class="stat-card__value"><?= number_format($metrics['avg_budget'], 1, ',', '.'); ?></span>
            <span class="stat-card__hint text-muted">Durchschnittsrate: <?= format_money($metrics['avg_rate']); ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Naechste Deadline</span>
            <span class="stat-card__value <?= $nextDeadline ? 'text-warning' : 'text-muted'; ?>"><?= h($deadlineLabel); ?></span>
            <?php if ($deadlineProject): ?>
                <span class="stat-card__hint text-muted">Projekt: <?= h($deadlineProject); ?></span>
            <?php else: ?>
                <span class="stat-card__hint text-muted">Keine bevorstehenden Termine</span>
            <?php endif; ?>
        </article>
    </section>

    <section class="panel projects-filters">
        <form method="get" class="projects-filters__grid">
            <label>
                <span>Suche</span>
                <input type="search" name="q" placeholder="Projekt, Kunde oder Stichwort" value="<?= h($filters['q']); ?>">
            </label>
            <label>
                <span>Status</span>
                <select name="filter_status">
                    <option value="">Alle</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= h($value); ?>" <?= $filters['status'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Kunde</span>
                <select name="client">
                    <option value="">Alle</option>
                    <?php foreach ($clientOptions as $client): ?>
                        <option value="<?= h($client); ?>" <?= strcasecmp($filters['client'], $client) === 0 ? 'selected' : ''; ?>><?= h($client); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Termine</span>
                <select name="timeline">
                    <option value="">Alle</option>
                    <option value="overdue" <?= $filters['timeline'] === 'overdue' ? 'selected' : ''; ?>>Ueberfaellig</option>
                    <option value="next7" <?= $filters['timeline'] === 'next7' ? 'selected' : ''; ?>>Faellig in 7 Tagen</option>
                    <option value="no_due" <?= $filters['timeline'] === 'no_due' ? 'selected' : ''; ?>>Ohne Termin</option>
                </select>
            </label>
            <div class="projects-filters__actions">
                <button class="button button--primary" type="submit">Filter anwenden</button>
                <?php if ($hasFilters): ?>
                    <a class="button button--ghost" href="projects.php">Zuruecksetzen</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($hasFilters): ?>
            <p class="projects-filters__result text-muted">
                Gefiltert: <?= count($filteredProjects); ?> von <?= $metrics['total']; ?> Projekten
            </p>
        <?php endif; ?>
    </section>

    <section class="panel projects-status">
        <h2>Projektstatus</h2>
        <ul class="projects-status__list">
            <?php foreach ($statusLabels as $key => $label): ?>
                <li class="projects-status__item">
                    <span class="projects-status__label"><?= h($label); ?></span>
                    <span><?= number_format($statusBreakdown[$key] ?? 0, 0, ',', '.'); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="panel">
        <?php if (!$projects): ?>
            <p>Noch keine Projekte erfasst.</p>
        <?php elseif (!$filteredProjects): ?>
            <p>Keine Projekte entsprechen den aktuellen Filtern.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="projects-table">
                    <thead>
                    <tr>
                        <th>Projekt</th>
                        <th>Kunde</th>
                        <th>Status</th>
                        <th>Zeitraum</th>
                        <th>Budget & Rate</th>
                        <th>Verbrauch</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filteredProjects as $project): ?>
                        <?php
                        $projectId = (int)$project['id'];
                        $statusKey = (string)($project['status'] ?? '');
                        $statusLabel = $statusLabels[$statusKey] ?? ucfirst($statusKey);
                        $isClosed = in_array($statusKey, ['completed', 'cancelled'], true);

                        $startLabel = '-';
                        if (!empty($project['start_date'])) {
                            $startLabel = date('d.m.Y', strtotime((string)$project['start_date']));
                        }

                        $dueLabel = '-';
                        $dueClass = '';
                        if (!empty($project['due_date'])) {
                            $dueDate = new DateTimeImmutable((string)$project['due_date']);
                            $dueLabel = $dueDate->format('d.m.Y');
                            if (!$isClosed) {
                                if ($dueDate < $today) {
                                    $dueClass = 'due-overdue';
                                } elseif ($dueDate <= $upcomingThreshold) {
                                    $dueClass = 'due-upcoming';
                                }
                            }
                        }

                        $company = trim((string)($project['company'] ?? 'Intern'));
                        $serviceType = trim((string)($project['service_type'] ?? ''));
                        $budgetHours = $project['budget_hours'] !== null && $project['budget_hours'] !== '' ? (float)$project['budget_hours'] : null;
                        $consumedHours = (float)($project['consumed_hours'] ?? 0);
                        $activity = $activities[$projectId] ?? ['recent' => [], 'total_hours' => 0.0];
                        $loggedHours = max($activity['total_hours'], $consumedHours);
                        $remainingHours = $budgetHours !== null ? max(0, $budgetHours - $loggedHours) : null;
                        $hourlyRate = $project['hourly_rate'] !== null && $project['hourly_rate'] !== '' ? (float)$project['hourly_rate'] : null;
                        $recentEntries = $activity['recent'];
                        ?>
                        <tr>
                            <td>
                                <div class="projects-table__name">
                                    <strong><?= h($project['name']); ?></strong>
                                    <?php if ($serviceType !== ''): ?>
                                        <span class="projects-table__service">Service: <?= h($serviceType); ?></span>
                                    <?php endif; ?>
                                    <span class="projects-table__meta">ID: <?= $projectId; ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="projects-table__name">
                                    <strong><?= h($company !== '' ? $company : 'Intern'); ?></strong>
                                    <?php if (!empty($project['customer_id'])): ?>
                                        <span class="projects-table__meta">Kunden-ID: <?= (int)$project['customer_id']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge--<?= h($statusKey); ?>"><?= h($statusLabel); ?></span>
                            </td>
                            <td>
                                <div class="projects-table__period">
                                    <span><strong>Start:</strong> <?= h($startLabel); ?></span>
                                    <span class="<?= h($dueClass); ?>"><strong>Faellig:</strong> <?= h($dueLabel); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="projects-table__budget">
                                    <span><strong>Budget:</strong> <?= format_hours($budgetHours); ?></span>
                                    <span class="text-muted"><strong>Rate:</strong> <?= $hourlyRate !== null ? format_money($hourlyRate) : '-'; ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="projects-table__budget">
                                    <span><strong>Verbraucht:</strong> <?= format_hours($loggedHours); ?></span>
                                    <span class="text-muted"><strong>Rest:</strong> <?= format_hours($remainingHours); ?></span>
                                </div>
                            </td>
                            <td class="projects-table__actions">
                                <a class="table-link" href="project-view.php?id=<?= $projectId; ?>">Details</a>
                                <a class="table-link" href="project-form.php?id=<?= $projectId; ?>">Bearbeiten</a>
                            </td>
                        </tr>
                        <?php if ($recentEntries): ?>
                            <tr class="projects-table__log">
                                <td colspan="7">
                                    <strong>Letzte Buchungen:</strong>
                                    <ul class="projects-log">
                                        <?php foreach ($recentEntries as $entry): ?>
                                            <li><?= h(date('d.m.', strtotime((string)$entry['date']))); ?> - <?= h($entry['user']); ?> (<?= h(number_format((float)$entry['hours'], 1, ',', '.')); ?> Std)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr class="projects-table__booking">
                            <td colspan="7">
                                <form action="time-entry-create.php" method="post" class="projects-booking-form">
                                    <input type="hidden" name="project_id" value="<?= $projectId; ?>">
                                    <input type="hidden" name="billable" value="1">
                                    <input type="hidden" name="return_to" value="projects.php">
                                    <label>
                                        <span>Datum</span>
                                        <input type="date" name="entry_date" value="<?= date('Y-m-d'); ?>" required>
                                    </label>
                                    <label>
                                        <span>Stunden</span>
                                        <input type="number" step="0.1" min="0.1" name="hours" required>
                                    </label>
                                    <label>
                                        <span>Mitarbeiter</span>
                                        <input type="text" name="user_name" value="<?= h($defaultEntryName); ?>" required>
                                    </label>
                                    <label class="projects-booking-form__description">
                                        <span>Notiz</span>
                                        <input type="text" name="description" placeholder="optional">
                                    </label>
                                    <button class="button button--ghost" type="submit">Stunden buchen</button>
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
