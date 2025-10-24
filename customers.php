<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

$pdo = get_database();
$user = current_user();
$tenantId = $user['tenant_id'] ?? null;
require_module_access('CRM');

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'status' => strtolower((string)($_GET['status'] ?? '')),
    'tag' => trim((string)($_GET['tag'] ?? '')),
    'manager' => trim((string)($_GET['manager'] ?? '')),
];

$customers = get_customers($pdo, $tenantId);

$filteredCustomers = array_filter($customers, static function (array $customer) use ($filters): bool {
    if ($filters['q'] !== '') {
        $haystack = strtolower(
            ($customer['company'] ?? '') . ' ' .
            ($customer['contact_name'] ?? '') . ' ' .
            ($customer['email'] ?? '')
        );
        if (strpos($haystack, strtolower($filters['q'])) === false) {
            return false;
        }
    }

    if ($filters['status'] !== '') {
        $status = strtolower((string)($customer['status'] ?? ''));
        if ($status !== $filters['status']) {
            return false;
        }
    }

    if ($filters['manager'] !== '') {
        $manager = strtolower((string)($customer['account_manager'] ?? ''));
        if ($manager === '' || strpos($manager, strtolower($filters['manager'])) === false) {
            return false;
        }
    }

    if ($filters['tag'] !== '') {
        $tags = strtolower((string)($customer['tags'] ?? ''));
        if ($tags === '' || strpos($tags, strtolower($filters['tag'])) === false) {
            return false;
        }
    }

    return true;
});

$metrics = [
    'total' => count($customers),
    'active' => 0,
    'inactive' => 0,
];

foreach ($customers as $customer) {
    $statusValue = strtolower((string)($customer['status'] ?? ''));
    if ($statusValue === 'aktiv') {
        $metrics['active']++;
    }
    if ($statusValue === 'inaktiv') {
        $metrics['inactive']++;
    }
}

$statusMessage = (string)($_GET['status'] ?? '');

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
    <title><?= h(APP_NAME); ?> - Kunden</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="content">
    <section class="customers-header">
        <div class="customers-header__info">
            <h1>Kunden</h1>
            <p>Verwalten, durchsuchen und exportieren Sie Ihre Kundenstammdaten.</p>
        </div>
        <div class="customers-header__actions">
            <a class="button button--ghost" href="#">
                <span class="btn-icon">&#8593;</span>
                <span>Importieren</span>
            </a>
            <a class="button button--ghost" href="#">
                <span class="btn-icon">&#8595;</span>
                <span>Exportieren</span>
            </a>
            <a class="button" href="customer-form.php">
                <span class="btn-icon">+</span>
                <span>Neuer Kunde</span>
            </a>
        </div>
    </section>

    <section class="customers-filters">
        <form method="get" class="customers-filters__form">
            <div class="customers-filters__grid">
                <label>
                    <span>Suche</span>
                    <input type="text" name="q" placeholder="Firma, Ansprechpartner oder E-Mail" value="<?= h($filters['q']); ?>">
                </label>
                <label>
                    <span>Status</span>
                    <select name="status">
                        <?php foreach (['' => 'Alle', 'aktiv' => 'Aktiv', 'inaktiv' => 'Inaktiv', 'archiviert' => 'Archiviert'] as $value => $label): ?>
                            <option value="<?= h($value); ?>" <?= $filters['status'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Kategorie / Tags</span>
                    <input type="text" name="tag" placeholder="z.B. Premium" value="<?= h($filters['tag']); ?>">
                </label>
                <label>
                    <span>Account Manager</span>
                    <input type="text" name="manager" placeholder="Name" value="<?= h($filters['manager']); ?>">
                </label>
            </div>
            <div class="customers-filters__actions">
                <button class="button button--ghost" type="submit">Filter anwenden</button>
                <a class="customers-filters__reset" href="customers.php">Zuruecksetzen</a>
            </div>
        </form>
    </section>

    <section class="customers-metrics">
        <article class="customers-metrics__card">
            <span class="customers-metrics__label">Gesamt</span>
            <span class="customers-metrics__value"><?= h((string)$metrics['total']); ?></span>
        </article>
        <article class="customers-metrics__card">
            <span class="customers-metrics__label">Aktiv</span>
            <span class="customers-metrics__value customers-metrics__value--positive"><?= h((string)$metrics['active']); ?></span>
        </article>
        <article class="customers-metrics__card">
            <span class="customers-metrics__label">Inaktiv</span>
            <span class="customers-metrics__value customers-metrics__value--neutral"><?= h((string)$metrics['inactive']); ?></span>
        </article>
    </section>

    <?php if ($statusMessage === 'created'): ?>
        <p class="alert alert--success">Kunde wurde erfolgreich angelegt.</p>
    <?php elseif ($statusMessage === 'updated'): ?>
        <p class="alert alert--success">Kundendaten wurden aktualisiert.</p>
    <?php elseif ($statusMessage === 'deleted'): ?>
        <p class="alert alert--success">Kunde wurde geloescht.</p>
    <?php elseif ($statusMessage === 'missing'): ?>
        <p class="alert alert--error">Bitte legen Sie zunaechst einen Kunden an, bevor Sie Rechnungen erstellen.</p>
    <?php endif; ?>

    <div class="panel">
        <?php if (!$filteredCustomers): ?>
            <p>Noch keine Kunden erfasst.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Firma</th>
                        <th>Ansprechpartner</th>
                        <th>E-Mail</th>
                        <th>Status</th>
                        <th>Kundennummer</th>
                        <th>Telefon</th>
                        <th>Ort</th>
                        <th>Angelegt</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filteredCustomers as $customer): ?>
                        <tr>
                            <td><?= h($customer['company']); ?></td>
                            <td><?= h($customer['contact_name'] ?? '-'); ?></td>
                            <td><?= h($customer['email'] ?? '-'); ?></td>
                            <td>
                                <?php $badge = strtolower((string)($customer['status'] ?? 'unbekannt')); ?>
                                <span class="badge badge--<?= h($badge); ?>"><?= h(strtoupper($customer['status'] ?? 'n/a')); ?></span>
                            </td>
                            <td><?= h($customer['customer_number'] ?? '-'); ?></td>
                            <td><?= h($customer['phone'] ?? '-'); ?></td>
                            <td><?= h($customer['city'] ?? '-'); ?></td>
                            <td><?= h(date('d.m.Y', strtotime((string)$customer['created_at']))); ?></td>
                            <td class="table-actions">
                                <a class="table-link" href="customer-form.php?id=<?= (int)$customer['id']; ?>">Bearbeiten</a>
                                <form method="post" action="customer-delete.php" onsubmit="return confirm('Kunde wirklich loeschen? Alle zugehoerigen Rechnungen werden ebenfalls entfernt.');">
                                    <input type="hidden" name="id" value="<?= (int)$customer['id']; ?>">
                                    <button type="submit" class="link-button">Loeschen</button>
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
