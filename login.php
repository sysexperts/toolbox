<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;
$loginValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginValue = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($loginValue === '' || $password === '') {
        $error = 'Bitte geben Sie Benutzername und Passwort ein.';
    } else {
        $user = attempt_login($loginValue, $password);
        if ($user !== null) {
            login_user($user);
            header('Location: index.php');
            exit;
        }
        $error = 'Anmeldung fehlgeschlagen. Bitte pruefen Sie Ihre Eingaben.';
    }
}

if ($error === null && isset($_GET['license']) && $_GET['license'] === 'invalid') {
    $error = 'Ihr Mandant ist nicht freigeschaltet oder die Lizenz ist nicht gueltig.';
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
    <title><?= h(APP_NAME); ?> - Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth">
    <div class="auth__panel">
        <div class="auth__logo">BM</div>
        <h1><?= h(APP_NAME); ?></h1>
        <p class="auth__lead">Melden Sie sich an, um Kunden und Rechnungen zu verwalten.</p>

        <?php if ($error): ?>
            <p class="auth__error"><?= h($error); ?></p>
        <?php endif; ?>

        <form method="post" class="auth__form" autocomplete="off">
            <label>
                <span>Benutzername</span>
                <input type="text" name="login" value="<?= h($loginValue); ?>" required>
            </label>
            <label>
                <span>Passwort</span>
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="button button--primary">Anmelden</button>
        </form>

        <p class="auth__hint">
            Noch kein Zugang? <a href="register.php">Mandanten-Registrierung</a><br>
            Standard-Login (Superadmin): <code><?= h(ADMIN_LOGIN); ?></code> / <code><?= h(ADMIN_PASSWORD); ?></code>
        </p>
    </div>
</body>
</html>
