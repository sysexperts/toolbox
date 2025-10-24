<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

ensure_session_started();

$pdo = get_database();
$modules = get_all_modules($pdo);
$success = false;
$errors = [];
$form = [
    'company' => '',
    'email' => '',
    'name' => '',
    'password' => '',
    'modules' => array_map(static fn (array $module) => $module['name'], $modules),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['company'] = trim((string)($_POST['company'] ?? ''));
    $form['email'] = trim((string)($_POST['email'] ?? ''));
    $form['name'] = trim((string)($_POST['name'] ?? ''));
    $form['password'] = (string)($_POST['password'] ?? '');
    $form['modules'] = isset($_POST['modules']) ? array_map('trim', (array)$_POST['modules']) : [];

    if ($form['company'] === '') {
        $errors['company'] = 'Bitte Firmenname angeben.';
    }

    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Bitte gueltige E-Mail angeben.';
    }

    if ($form['password'] === '') {
        $errors['password'] = 'Bitte Passwort vergeben.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $tenantId = create_tenant($pdo, [
                'name' => $form['company'],
                'email' => $form['email'],
                'status' => 'pending',
            ]);

            assign_modules_to_tenant($pdo, $tenantId, $form['modules']);

            $userId = create_user($pdo, [
                'tenant_id' => $tenantId,
                'email' => $form['email'],
                'password' => password_hash($form['password'], PASSWORD_DEFAULT),
                'name' => $form['name'] ?: $form['company'],
                'role' => 'admin',
            ]);

            assign_modules_to_user($pdo, $userId, $form['modules']);

            notify_admin(
                $pdo,
                $tenantId,
                sprintf('Neuer Mandant registriert: %s (%s)', $form['company'], $form['email'])
            );

            $pdo->commit();
            $success = true;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errors['general'] = 'Registrierung fehlgeschlagen: ' . $exception->getMessage();
        }
    }
}

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
    <title><?= h(APP_NAME); ?> - Registrierung</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth">
    <div class="auth__panel">
        <h1><?= h(APP_NAME); ?> Registrierung</h1>
        <p class="auth__lead">Legen Sie Ihren Mandanten an. Wir pruefen Ihre Angaben und schalten Sie frei.</p>

        <?php if ($success): ?>
            <p class="alert alert--success">Vielen Dank! Wir haben Ihre Anfrage erhalten und melden uns nach Freischaltung.</p>
            <p><a class="button" href="login.php">Zur Anmeldung</a></p>
        <?php else: ?>
            <?php if (isset($errors['general'])): ?>
                <p class="auth__error"><?= h($errors['general']); ?></p>
            <?php endif; ?>

            <form method="post" class="auth__form" autocomplete="off">
                <label>
                    <span>Firmenname *</span>
                    <input type="text" name="company" value="<?= h($form['company']); ?>" required>
                    <?php if (isset($errors['company'])): ?><small class="form-error"><?= h($errors['company']); ?></small><?php endif; ?>
                </label>
                <label>
                    <span>Kontakt-E-Mail *</span>
                    <input type="email" name="email" value="<?= h($form['email']); ?>" required>
                    <?php if (isset($errors['email'])): ?><small class="form-error"><?= h($errors['email']); ?></small><?php endif; ?>
                </label>
                <label>
                    <span>Ihr Name</span>
                    <input type="text" name="name" value="<?= h($form['name']); ?>">
                </label>
                <label>
                    <span>Passwort *</span>
                    <input type="password" name="password" required>
                    <?php if (isset($errors['password'])): ?><small class="form-error"><?= h($errors['password']); ?></small><?php endif; ?>
                </label>

                <fieldset class="auth__form">
                    <legend>Module (optional)</legend>
                    <?php foreach ($modules as $module): ?>
                        <label class="form-check">
                            <input type="checkbox" name="modules[]" value="<?= h($module['name']); ?>" <?= in_array($module['name'], $form['modules'], true) ? 'checked' : ''; ?>>
                            <span><?= h($module['name']); ?> <small><?= h($module['description']); ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <button type="submit" class="button button--primary">Registrierung absenden</button>
                <a class="button button--ghost" href="login.php">Zurueck zum Login</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
