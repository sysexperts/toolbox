<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('CRM');

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
$services = get_services($pdo, $tenantId);
$status = (string)($_GET['status'] ?? '');

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'billing' => (string)($_GET['billing'] ?? ''),
    'status' => (string)($_GET['filter_status'] ?? ''),
];

$billingLabels = [
    'fixed' => 'Pauschal',
    'hourly' => 'Stundensatz',
    'subscription' => 'Abonnement',
];

$hasFilters = $filters['q'] !== '' || $filters['billing'] !== '' || $filters['status'] !== '';

$filteredServices = array_values(array_filter(
    $services,
    static function (array $service) use ($filters): bool {
        if ($filters['status'] === 'active' && !(int)$service['active']) {
            return false;
        }
        if ($filters['status'] === 'inactive' && (int)$service['active']) {
            return false;
        }

        if ($filters['billing'] !== '' && strcasecmp((string)$service['billing_type'], $filters['billing']) !== 0) {
            return false;
        }

        if ($filters['q'] !== '') {
            $needle = mb_strtolower($filters['q']);
            $haystack = mb_strtolower(
                (string)$service['name'] . ' ' . (string)($service['description'] ?? '') . ' ' . (string)$service['billing_type']
            );
            if (mb_strpos($haystack, $needle) === false) {
                return false;
            }
        }

        return true;
    }
));

$metrics = [
    'total' => count($services),
    'active' => 0,
    'inactive' => 0,
    'subscription' => 0,
    'average_price' => 0.0,
    'average_margin' => 0.0,
];

$totalPrice = 0.0;
$totalMargin = 0.0;

foreach ($services as $service) {
    $isActive = (int)($service['active'] ?? 0) === 1;
    $metrics[$isActive ? 'active' : 'inactive']++;

    $unitPrice = (float)($service['unit_price'] ?? 0);
    $unitCost = (float)($service['unit_cost'] ?? 0);

    $totalPrice += $unitPrice;

    $margin = $unitPrice - $unitCost;
    $totalMargin += $margin;

    if (strcasecmp((string)$service['billing_type'], 'subscription') === 0) {
        $metrics['subscription']++;
    }
}

if ($metrics['total'] > 0) {
    $metrics['average_price'] = $totalPrice / $metrics['total'];
    $metrics['average_margin'] = $totalMargin / $metrics['total'];
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_money(float $value): string
{
    return number_format($value, 2, ',', '.') . ' EUR';
}

function billing_label(array $labels, string $value): string
{
    return $labels[$value] ?? ucfirst($value);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME); ?> - Leistungen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <header class="content__header">
        <div>
            <h1>Leistungen & Produkte</h1>
            <p>Verwalten Sie wiederkehrende Services, Hosting-Pakete oder Hardware-Bundles.</p>
        </div>
        <a class="button" href="service-form.php">Leistung anlegen</a>
    </header>

    <?php if ($status === 'created'): ?>
        <p class="alert alert--success">Leistung wurde gespeichert.</p>
    <?php elseif ($status === 'toggled'): ?>
        <p class="alert alert--success">Leistung wurde aktualisiert.</p>
    <?php endif; ?>

    <section class="cards cards--grid-4 cards--stretch">
        <article class="stat-card">
            <span class="stat-card__label">Gesamtleistungen</span>
            <span class="stat-card__value"><?= number_format($metrics['total'], 0, ',', '.'); ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Aktiv</span>
            <span class="stat-card__value"><?= number_format($metrics['active'], 0, ',', '.'); ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Abonnements</span>
            <span class="stat-card__value"><?= number_format($metrics['subscription'], 0, ',', '.'); ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Durchschnitt Preis</span>
            <span class="stat-card__value"><?= format_money($metrics['average_price']); ?></span>
            <span class="stat-card__hint text-muted">Durchschnitt Marge <?= format_money($metrics['average_margin']); ?></span>
        </article>
    </section>

    <section class="panel services-filters">
        <form method="get" class="services-filters__grid">
            <label>
                <span>Suche</span>
                <input type="search" name="q" placeholder="Name, Beschreibung, Typ" value="<?= h($filters['q']); ?>">
            </label>
            <label>
                <span>Verrechnung</span>
                <select name="billing">
                    <option value="">Alle Typen</option>
                    <?php foreach ($billingLabels as $value => $label): ?>
                        <option value="<?= h($value); ?>" <?= $filters['billing'] === $value ? 'selected' : ''; ?>>
                            <?= h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Status</span>
                <select name="filter_status">
                    <option value="">Alle</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : ''; ?>>Aktive</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Deaktivierte</option>
                </select>
            </label>
            <div class="services-filters__actions">
                <button class="button button--primary" type="submit">Filter anwenden</button>
                <?php if ($hasFilters): ?>
                    <a class="button button--ghost" href="services.php">Zuruecksetzen</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($hasFilters): ?>
            <p class="text-muted services-filters__result">
                Gefiltert: <?= count($filteredServices); ?> von <?= $metrics['total']; ?> Leistungen
            </p>
        <?php endif; ?>
    </section>

    <div class="panel">
        <?php if (!$services): ?>
            <p>Noch keine Leistungen erfasst.</p>
        <?php elseif (!$filteredServices): ?>
            <p>Keine Leistungen entsprechen den aktuellen Filtern.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="services-table">
                    <thead>
                    <tr>
                        <th>Leistung</th>
                        <th>Verrechnung</th>
                        <th>VK</th>
                        <th>Marge</th>
                        <th>Status</th>
                        <th>Aktualisiert</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filteredServices as $service): ?>
                        <?php
                        $unitPrice = (float)($service['unit_price'] ?? 0);
                        $unitCost = (float)($service['unit_cost'] ?? 0);
                        $margin = $unitPrice - $unitCost;
                        $marginPercent = $unitPrice > 0 ? ($margin / $unitPrice * 100) : 0;
                        $marginClass = $margin >= 0 ? 'text-positive' : 'text-negative';
                        ?>
                        <tr>
                            <td>
                                <div class="services-table__name">
                                    <strong><?= h($service['name']); ?></strong>
                                    <?php if (!empty($service['description'])): ?>
                                        <p class="services-table__description text-muted"><?= h($service['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="services-table__meta text-muted">
                                    ID: <?= (int)$service['id']; ?>
                                </div>
                            </td>
                            <td><?= h(billing_label($billingLabels, (string)$service['billing_type'])); ?></td>
                            <td><?= format_money($unitPrice); ?></td>
                            <td>
                                <span class="<?= $marginClass; ?>">
                                    <?= format_money($margin); ?> (<?= number_format($marginPercent, 1, ',', '.'); ?>%)
                                </span>
                            </td>
                            <td>
                                <span class="badge badge--<?= (int)$service['active'] === 1 ? 'paid' : 'cancelled'; ?>">
                                    <?= (int)$service['active'] === 1 ? 'Aktiv' : 'Deaktiviert'; ?>
                                </span>
                            </td>
                            <td class="text-muted">
                                <?= !empty($service['created_at']) ? h(date('d.m.Y', strtotime((string)$service['created_at']))) : '-'; ?>
                            </td>
                            <td class="services-table__actions">
                                <form action="service-toggle.php" method="post" class="inline-form">
                                    <input type="hidden" name="id" value="<?= (int)$service['id']; ?>">
                                    <input type="hidden" name="active" value="<?= (int)$service['active'] === 1 ? '0' : '1'; ?>">
                                    <button type="submit" class="link-button">
                                        <?= (int)$service['active'] === 1 ? 'Deaktivieren' : 'Aktivieren'; ?>
                                    </button>
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
