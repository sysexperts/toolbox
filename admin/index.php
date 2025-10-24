<?php
declare(strict_types=1);

require __DIR__ . '/../auth.php';

require_role('superadmin');

$pdo = get_database();

$modules = get_all_modules($pdo);
$allModuleNames = array_map(static fn(array $module): string => (string)$module['name'], $modules);

$tenants = get_tenants($pdo);
$notifications = get_admin_notifications($pdo);

$flash = null;
$flashDetails = null;
$errors = [
    'tenant' => [],
    'user' => [],
];

$createTenantForm = [
    'tenant_name' => '',
    'tenant_email' => '',
    'admin_name' => '',
    'admin_email' => '',
    'admin_password' => '',
    'status' => 'active',
    'modules' => $allModuleNames,
];

$createUserForm = [
    'tenant_id' => '',
    'name' => '',
    'email' => '',
    'role' => 'user',
    'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tenantId = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;

    if ($action === 'create_tenant') {
        $createTenantForm['tenant_name'] = trim((string)($_POST['tenant_name'] ?? ''));
        $createTenantForm['tenant_email'] = trim((string)($_POST['tenant_email'] ?? ''));
        $createTenantForm['admin_name'] = trim((string)($_POST['admin_name'] ?? ''));
        $createTenantForm['admin_email'] = trim((string)($_POST['admin_email'] ?? $createTenantForm['tenant_email']));
        $createTenantForm['admin_password'] = (string)($_POST['admin_password'] ?? '');
        $createTenantForm['status'] = (string)($_POST['status'] ?? 'active');
        $selectedModules = isset($_POST['modules']) ? array_map('trim', (array)$_POST['modules']) : [];
        $selectedModules = array_values(array_intersect($selectedModules, $allModuleNames));
        if (!$selectedModules) {
            $selectedModules = $allModuleNames;
        }
        $createTenantForm['modules'] = $selectedModules;

        if ($createTenantForm['tenant_name'] === '') {
            $errors['tenant']['tenant_name'] = 'Bitte Mandantennamen angeben.';
        }

        if ($createTenantForm['tenant_email'] === '' || !filter_var($createTenantForm['tenant_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['tenant']['tenant_email'] = 'Bitte gueltige Mandanten-E-Mail angeben.';
        }

        if ($createTenantForm['admin_email'] === '' || !filter_var($createTenantForm['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['tenant']['admin_email'] = 'Bitte gueltige Admin-E-Mail angeben.';
        }

        if (!$errors['tenant']) {
            $plainPassword = $createTenantForm['admin_password'] !== '' ? $createTenantForm['admin_password'] : generate_random_password();

            try {
                create_tenant_with_admin(
                    $pdo,
                    [
                        'name' => $createTenantForm['tenant_name'],
                        'email' => $createTenantForm['tenant_email'],
                        'status' => $createTenantForm['status'],
                    ],
                    [
                        'name' => $createTenantForm['admin_name'] !== '' ? $createTenantForm['admin_name'] : $createTenantForm['tenant_name'] . ' Admin',
                        'email' => $createTenantForm['admin_email'],
                        'password' => $plainPassword,
                        'role' => 'admin',
                    ],
                    $selectedModules
                );

                $flash = 'Mandant wurde angelegt.';
                $flashDetails = sprintf('Admin Login: %s / %s', $createTenantForm['admin_email'], $plainPassword);

                $createTenantForm = [
                    'tenant_name' => '',
                    'tenant_email' => '',
                    'admin_name' => '',
                    'admin_email' => '',
                    'admin_password' => '',
                    'status' => 'active',
                    'modules' => $allModuleNames,
                ];
            } catch (Throwable $exception) {
                $errors['tenant']['general'] = 'Mandant konnte nicht angelegt werden: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'create_user') {
        $createUserForm['tenant_id'] = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
        $createUserForm['name'] = trim((string)($_POST['name'] ?? ''));
        $createUserForm['email'] = trim((string)($_POST['email'] ?? ''));
        $createUserForm['role'] = (string)($_POST['role'] ?? 'user');
        $createUserForm['password'] = (string)($_POST['password'] ?? '');

        if ($createUserForm['tenant_id'] <= 0) {
            $errors['user']['tenant_id'] = 'Bitte Mandanten auswaehlen.';
        }

        if ($createUserForm['email'] === '' || !filter_var($createUserForm['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['user']['email'] = 'Bitte gueltige E-Mail angeben.';
        }

        if ($createUserForm['name'] === '') {
            $errors['user']['name'] = 'Bitte Namen angeben.';
        }

        if (!in_array($createUserForm['role'], ['user', 'admin', 'manager', 'owner'], true)) {
            $createUserForm['role'] = 'user';
        }

        if (!$errors['user']) {
            $plainPassword = $createUserForm['password'] !== '' ? $createUserForm['password'] : generate_random_password();
            $tenantModules = $createUserForm['tenant_id'] > 0 ? get_modules_for_tenant($pdo, $createUserForm['tenant_id']) : [];
            if (!$tenantModules) {
                $tenantModules = $allModuleNames;
            }

            try {
                $userId = create_user($pdo, [
                    'tenant_id' => $createUserForm['tenant_id'],
                    'email' => $createUserForm['email'],
                    'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
                    'name' => $createUserForm['name'],
                    'role' => $createUserForm['role'],
                ]);

                assign_modules_to_user($pdo, $userId, $tenantModules);

                $flash = 'Benutzer wurde angelegt.';
                $flashDetails = sprintf('Login: %s / %s', $createUserForm['email'], $plainPassword);

                $createUserForm = [
                    'tenant_id' => '',
                    'name' => '',
                    'email' => '',
                    'role' => 'user',
                    'password' => '',
                ];
            } catch (Throwable $exception) {
                $errors['user']['general'] = 'Benutzer konnte nicht angelegt werden: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'status' && $tenantId) {
        $status = (string)($_POST['status'] ?? 'pending');
        set_tenant_status($pdo, $tenantId, $status);
        $flash = 'Status wurde aktualisiert.';
    } elseif ($action === 'modules' && $tenantId) {
        $selected = isset($_POST['modules']) ? array_map('trim', (array)$_POST['modules']) : [];
        $pdo->prepare('DELETE FROM tenant_modules WHERE tenant_id = :tenant_id')->execute([':tenant_id' => $tenantId]);
        assign_modules_to_tenant($pdo, $tenantId, $selected);
        $flash = 'Module wurden aktualisiert.';
    } elseif ($action === 'user_modules') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        if ($userId) {
            $selected = isset($_POST['modules']) ? array_map('trim', (array)$_POST['modules']) : [];
            $pdo->prepare('DELETE FROM user_modules WHERE user_id = :user_id')->execute([':user_id' => $userId]);
            assign_modules_to_user($pdo, $userId, $selected);
            $flash = 'Module fuer Benutzer wurden aktualisiert.';
        }
    } elseif ($action === 'license' && $tenantId) {
        $payload = [
            'license_key' => $_POST['license_key'] ?? '',
            'valid_until' => $_POST['valid_until'] ?? '',
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        save_tenant_license($pdo, $tenantId, $payload);
        $flash = 'Lizenz wurde aktualisiert.';
    } elseif ($action === 'notifications_seen') {
        $ids = isset($_POST['notification_ids']) ? array_map('intval', (array)$_POST['notification_ids']) : [];
        $ids = array_filter($ids);
        if ($ids) {
            mark_admin_notifications_seen($pdo, $ids);
            $flash = 'Benachrichtigungen wurden aktualisiert.';
        }
    }

    $tenants = get_tenants($pdo);
    $notifications = get_admin_notifications($pdo);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$hasUnseenNotifications = array_reduce(
    $notifications,
    static function (bool $carry, array $notification): bool {
        return $carry || ((int)($notification['seen'] ?? 0) === 0);
    },
    false
);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Admin - <?= h(APP_NAME); ?></title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="layout">
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<main class="content">
    <header class="content__header">
        <div>
            <h1>Mandantenverwaltung</h1>
            <p>Verwalten Sie registrierte Kunden, Module und Lizenzen.</p>
        </div>
    </header>

    <?php if ($flash): ?>
        <p class="alert alert--success"><?= h($flash); ?></p>
    <?php endif; ?>
    <?php if ($flashDetails): ?>
        <p class="alert alert--info"><?= h($flashDetails); ?></p>
    <?php endif; ?>

    <section class="panel admin-forms">
        <div class="admin-forms__column">
            <h2>Neuen Mandanten anlegen</h2>
            <p>Mandanten mit eigenem Admin-Zugang und Modulen anlegen.</p>
            <?php if ($errors['tenant']): ?>
                <p class="alert alert--error"><?= h(implode(' ', array_filter($errors['tenant']))); ?></p>
            <?php endif; ?>
            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="create_tenant">

                <div class="form-field">
                    <label>
                        <span>Mandantenname *</span>
                        <input type="text" name="tenant_name" value="<?= h($createTenantForm['tenant_name']); ?>" required>
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Mandanten-E-Mail *</span>
                        <input type="email" name="tenant_email" value="<?= h($createTenantForm['tenant_email']); ?>" required>
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Admin-Name</span>
                        <input type="text" name="admin_name" value="<?= h($createTenantForm['admin_name']); ?>">
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Admin-E-Mail *</span>
                        <input type="email" name="admin_email" value="<?= h($createTenantForm['admin_email']); ?>" required>
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Admin-Passwort</span>
                        <input type="text" name="admin_password" value="<?= h($createTenantForm['admin_password']); ?>" placeholder="Leer lassen f&uuml;r Zufallspasswort">
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Status</span>
                        <select name="status">
                            <?php foreach (['active' => 'Aktiv', 'pending' => 'Wartend', 'suspended' => 'Gesperrt'] as $value => $label): ?>
                                <option value="<?= h($value); ?>" <?= $createTenantForm['status'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <fieldset class="form-field form-field--full">
                    <legend>Module</legend>
                    <div class="admin-modules">
                        <?php foreach ($modules as $module): ?>
                            <label class="form-check">
                                <input type="checkbox" name="modules[]" value="<?= h($module['name']); ?>" <?= in_array($module['name'], $createTenantForm['modules'], true) ? 'checked' : ''; ?>>
                                <span>
                                    <?= h($module['name']); ?>
                                    <?php if (!empty($module['description'])): ?>
                                        <small><?= h($module['description']); ?></small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button class="button" type="submit">Mandant anlegen</button>
                </div>
            </form>
        </div>

        <div class="admin-forms__column">
            <h2>Benutzer anlegen</h2>
            <p>Zus&auml;tzliche Nutzer f&uuml;r Mandanten erstellen.</p>
            <?php if ($errors['user']): ?>
                <p class="alert alert--error"><?= h(implode(' ', array_filter($errors['user']))); ?></p>
            <?php endif; ?>
            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="create_user">

                <div class="form-field">
                    <label>
                        <span>Mandant *</span>
                        <select name="tenant_id" required>
                            <option value="">-- Auswahl --</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?= (int)$tenant['id']; ?>" <?= (string)$createUserForm['tenant_id'] === (string)$tenant['id'] ? 'selected' : ''; ?>>
                                    <?= h($tenant['name']); ?> (<?= h($tenant['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Name *</span>
                        <input type="text" name="name" value="<?= h($createUserForm['name']); ?>" required>
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>E-Mail *</span>
                        <input type="email" name="email" value="<?= h($createUserForm['email']); ?>" required>
                    </label>
                </div>

                <div class="form-field">
                    <label>
                        <span>Rolle</span>
                        <select name="role">
                            <?php foreach (['user' => 'Benutzer', 'admin' => 'Admin', 'manager' => 'Manager', 'owner' => 'Owner'] as $value => $label): ?>
                                <option value="<?= h($value); ?>" <?= $createUserForm['role'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="form-field form-field--full">
                    <label>
                        <span>Passwort</span>
                        <input type="text" name="password" value="<?= h($createUserForm['password']); ?>" placeholder="Leer lassen f&uuml;r Zufallspasswort">
                    </label>
                </div>

                <div class="form-actions">
                    <button class="button" type="submit">Benutzer anlegen</button>
                </div>
            </form>
        </div>
    </section>

    <?php if ($notifications): ?>
        <section class="panel">
            <div class="panel__header">
                <div>
                    <h2>Benachrichtigungen</h2>
                    <p>Neueste Registrierungen und Systemmeldungen.</p>
                </div>
                <?php if ($hasUnseenNotifications): ?>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="action" value="notifications_seen">
                        <?php foreach ($notifications as $note): ?>
                            <?php if ((int)($note['seen'] ?? 0) === 0): ?>
                                <input type="hidden" name="notification_ids[]" value="<?= (int)$note['id']; ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <button class="button button--ghost" type="submit">Als gelesen markieren</button>
                    </form>
                <?php endif; ?>
            </div>
            <ul class="admin-notifications">
                <?php foreach ($notifications as $note): ?>
                    <li class="admin-notifications__item<?= (int)($note['seen'] ?? 0) === 0 ? ' admin-notifications__item--new' : ''; ?>">
                        <div>
                            <strong><?= h($note['tenant_name'] ?? 'System'); ?></strong>
                            <span class="admin-notifications__message"><?= h($note['message']); ?></span>
                        </div>
                        <div class="admin-notifications__meta">
                            <small><?= h(date('d.m.Y H:i', strtotime((string)$note['created_at']))); ?></small>
                            <?php if ((int)($note['seen'] ?? 0) === 0): ?>
                                <span class="badge badge--open">Neu</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="panel">
        <div class="table-wrapper">
            <table>
                <thead>
                <tr>
                    <th>Kunde</th>
                    <th>E-Mail</th>
                    <th>Status</th>
                    <th>Lizenz</th>
                    <th>Letzter Login</th>
                    <th>Module</th>
                    <th>Benutzer & Rechte</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tenants as $tenant): ?>
                    <?php
                    $tenantIdInt = (int)$tenant['id'];
                    $tenantModules = get_modules_for_tenant($pdo, $tenantIdInt);
                    $tenantUsers = get_users_by_tenant($pdo, $tenantIdInt);
                    $latestLicense = get_latest_license($pdo, $tenantIdInt);
                    $licenseActive = $latestLicense && (int)($latestLicense['active'] ?? 0) === 1;
                    ?>
                    <tr>
                        <td><?= h($tenant['name']); ?></td>
                        <td><?= h($tenant['email']); ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="tenant_id" value="<?= $tenantIdInt; ?>">
                                <input type="hidden" name="action" value="status">
                                <select name="status">
                                    <?php foreach (['pending', 'active', 'suspended'] as $status): ?>
                                        <option value="<?= h($status); ?>" <?= $tenant['status'] === $status ? 'selected' : ''; ?>><?= h(ucfirst($status)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button button--ghost" type="submit">Speichern</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="inline-form admin-license-form">
                                <input type="hidden" name="tenant_id" value="<?= $tenantIdInt; ?>">
                                <input type="hidden" name="action" value="license">
                                <input type="text" name="license_key" placeholder="Lizenzschluessel" value="<?= h((string)($latestLicense['license_key'] ?? '')); ?>">
                                <input type="date" name="valid_until" value="<?= h((string)($latestLicense['valid_until'] ?? '')); ?>">
                                <label class="form-check">
                                    <input type="checkbox" name="active" value="1" <?= $licenseActive ? 'checked' : ''; ?>>
                                    <span>Aktiv</span>
                                </label>
                                <button class="button button--ghost" type="submit">Speichern</button>
                            </form>
                            <?php if ($latestLicense): ?>
                                <small class="admin-license-meta">
                                    Gueltig bis: <?= h($latestLicense['valid_until'] ?: 'nicht gesetzt'); ?>,
                                    aktualisiert am <?= h(date('d.m.Y', strtotime((string)$latestLicense['created_at']))); ?>
                                </small>
                            <?php else: ?>
                                <small class="admin-license-meta">Keine Lizenz hinterlegt.</small>
                            <?php endif; ?>
                        </td>
                        <td><?= h($tenant['last_login_at'] ?? 'n/a'); ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="tenant_id" value="<?= $tenantIdInt; ?>">
                                <input type="hidden" name="action" value="modules">
                                <select name="modules[]" multiple size="3">
                                    <?php foreach ($modules as $module): ?>
                                        <option value="<?= h($module['name']); ?>" <?= in_array($module['name'], $tenantModules, true) ? 'selected' : ''; ?>>
                                            <?= h($module['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button button--ghost" type="submit">Aktualisieren</button>
                            </form>
                        </td>
                        <td>
                            <?php if ($tenantUsers): ?>
                                <details>
                                    <summary>Mitarbeiter</summary>
                                    <ul class="admin-users">
                                        <?php foreach ($tenantUsers as $userRow): ?>
                                            <?php $userModules = get_modules_for_user($pdo, (int)$userRow['id']); ?>
                                            <li>
                                                <form method="post" class="inline-form admin-user-form">
                                                    <input type="hidden" name="action" value="user_modules">
                                                    <input type="hidden" name="tenant_id" value="<?= $tenantIdInt; ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int)$userRow['id']; ?>">
                                                    <div class="admin-user-form__header">
                                                        <strong><?= h($userRow['email']); ?></strong>
                                                        <small><?= h($userRow['role']); ?><?= $userRow['last_login_at'] ? ' | ' . h(date('d.m.Y H:i', strtotime((string)$userRow['last_login_at']))) : ''; ?></small>
                                                    </div>
                                                    <?php if ($tenantModules): ?>
                                                        <select name="modules[]" multiple size="3">
                                                            <?php foreach ($tenantModules as $moduleName): ?>
                                                                <option value="<?= h($moduleName); ?>" <?= in_array($moduleName, $userModules, true) ? 'selected' : ''; ?>>
                                                                    <?= h($moduleName); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button class="button button--ghost" type="submit">Aktualisieren</button>
                                                    <?php else: ?>
                                                        <small>Keine Module freigeschaltet.</small>
                                                    <?php endif; ?>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php else: ?>
                                <em>Keine Nutzer</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>