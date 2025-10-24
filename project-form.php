<?php

declare(strict_types=1);



require __DIR__ . '/auth.php';

require_module_access('Projekte');



$pdo = get_database();

$user = current_user();

$tenantId = $user['tenant_id'] ?? null;

$customers = get_customers($pdo, $tenantId);



$projectId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $projectId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

}



$isEdit = $projectId !== null;

$errors = [];

$values = [

    'customer_id' => '',

    'name' => '',

    'status' => 'planning',

    'start_date' => '',

    'due_date' => '',

    'budget_hours' => '',

    'consumed_hours' => '0',

    'hourly_rate' => '',

    'service_type' => '',

    'notes' => '',

];



if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {

    $project = get_project_detail($pdo, $projectId, $tenantId);

    if (!$project) {

        header('Location: projects.php');

        exit;

    }

    foreach ($values as $key => $_) {

        $values[$key] = (string)($project[$key] ?? '');

    }

}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($values as $key => $_) {

        $values[$key] = trim((string)($_POST[$key] ?? ''));

    }



    if ($values['name'] === '') {

        $errors['name'] = 'Bitte Projektnamen angeben.';

    }



    if ($values['customer_id'] !== '' && !ctype_digit($values['customer_id'])) {

        $errors['customer_id'] = 'Ungueltiger Kunde.';

    }



    if ($values['budget_hours'] !== '' && !is_numeric($values['budget_hours'])) {

        $errors['budget_hours'] = 'Bitte gueltige Zeit eintragen.';

    }



    if ($values['hourly_rate'] !== '' && !is_numeric($values['hourly_rate'])) {

        $errors['hourly_rate'] = 'Bitte gueltigen Stundensatz eintragen.';

    }



    if (empty($errors)) {

        $payload = $values;

        $payload['tenant_id'] = $tenantId;

        if ($isEdit) {

            update_project($pdo, $projectId, $payload);

            header('Location: projects.php?status=updated');

        } else {

            create_project($pdo, $payload);

            header('Location: projects.php?status=created');

        }

        exit;

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

    <title><?= h(APP_NAME); ?> - <?= $isEdit ? 'Projekt bearbeiten' : 'Projekt anlegen'; ?></title>

    <link rel="stylesheet" href="styles.css">

</head>

<body class="layout">

<?php include __DIR__ . '/partials/sidebar.php'; ?>



<main class="content">

    <header class="content__header">

        <h1><?= $isEdit ? 'Projekt bearbeiten' : 'Projekt anlegen'; ?></h1>

        <p>Planen Sie Projektumfang, Termine und Budget.</p>

    </header>



    <div class="panel">

        <form method="post" class="form-grid">

            <?php if ($isEdit): ?>

                <input type="hidden" name="id" value="<?= (int)$projectId; ?>">

            <?php endif; ?>



            <div class="form-field">

                <label>

                    <span>Kunde</span>

                    <select name="customer_id">

                        <option value="">-- optional --</option>

                        <?php foreach ($customers as $customer): ?>

                            <option value="<?= (int)$customer['id']; ?>" <?= $values['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>

                                <?= h($customer['company']); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <?php if (isset($errors['customer_id'])): ?>

                        <small class="form-error"><?= h($errors['customer_id']); ?></small>

                    <?php endif; ?>

                </label>

            </div>



            <div class="form-field form-field--full">

                <label>

                    <span>Projektname *</span>

                    <input type="text" name="name" value="<?= h($values['name']); ?>" required>

                    <?php if (isset($errors['name'])): ?>

                        <small class="form-error"><?= h($errors['name']); ?></small>

                    <?php endif; ?>

                </label>

            </div>



            <div class="form-field">

                <label>

                    <span>Status</span>

                    <select name="status">

                        <?php

                        $statuses = [

                            'planning' => 'Planung',

                            'in_progress' => 'In Arbeit',

                            'paused' => 'Pausiert',

                            'completed' => 'Abgeschlossen',

                            'cancelled' => 'Storniert',

                        ];

                        foreach ($statuses as $key => $label):

                            ?>

                            <option value="<?= h($key); ?>" <?= $values['status'] === $key ? 'selected' : ''; ?>><?= h($label); ?></option>

                        <?php endforeach; ?>

                    </select>

                </label>

            </div>



            <div class="form-field">

                <label>

                    <span>Startdatum</span>

                    <input type="date" name="start_date" value="<?= h($values['start_date']); ?>">

                </label>

            </div>



            <div class="form-field">

                <label>

                    <span>Faellig bis</span>

                    <input type="date" name="due_date" value="<?= h($values['due_date']); ?>">

                </label>

            </div>



            <div class="form-field">

                <label>

                    <span>Budget Stunden</span>

                    <input type="number" step="0.1" name="budget_hours" value="<?= h($values['budget_hours']); ?>">

                    <?php if (isset($errors['budget_hours'])): ?>

                        <small class="form-error"><?= h($errors['budget_hours']); ?></small>

                    <?php endif; ?>

                </label>

            </div>



            <div class="form-field">

                <label>

                    <span>Bereits gebuchte Stunden</span>

                    <input type="number" step="0.1" name="consumed_hours" value="<?= h($values['consumed_hours']); ?>">

                </label>

            </div>



            <div class="form-field">

                <label>

                    <span>Stundensatz EUR</span>

                    <input type="number" step="0.01" name="hourly_rate" value="<?= h($values['hourly_rate']); ?>">

                    <?php if (isset($errors['hourly_rate'])): ?>

                        <small class="form-error"><?= h($errors['hourly_rate']); ?></small>

                    <?php endif; ?>

                </label>

            </div>



            <div class="form-field">

                <label>

                    <span>Service Typ</span>

                    <input type="text" name="service_type" value="<?= h($values['service_type']); ?>" placeholder="Supportvertrag, Entwicklung, Workshop">

                </label>

            </div>



            <div class="form-field form-field--full">

                <label>

                    <span>Notizen</span>

                    <textarea name="notes" rows="4"><?= h($values['notes']); ?></textarea>

                </label>

            </div>



            <div class="form-actions">

                <a class="button button--ghost" href="projects.php">Abbrechen</a>

                <button class="button" type="submit"><?= $isEdit ? 'Speichern' : 'Projekt anlegen'; ?></button>

            </div>

        </form>

    </div>

</main>

</body>

</html>

