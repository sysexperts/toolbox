<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_module_access('Projekte');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: projects.php');
    exit;
}

$projectId = (int)($_POST['project_id'] ?? 0);
$userName = trim((string)($_POST['user_name'] ?? ''));
$entryDate = trim((string)($_POST['entry_date'] ?? ''));
$hours = (float)($_POST['hours'] ?? 0);

if ($projectId <= 0 || $userName === '' || $entryDate === '' || $hours <= 0) {
    header('Location: projects.php');
    exit;
}

$pdo = get_database();
$user = current_user();
$returnTo = trim((string)($_POST['return_to'] ?? ''));
$tenantId = $user['tenant_id'] ?? null;
create_time_entry($pdo, [
    'project_id' => $projectId,
    'user_name' => $userName,
    'entry_date' => $entryDate,
    'hours' => $hours,
    'billable' => (int)($_POST['billable'] ?? 1),
    'description' => trim((string)($_POST['description'] ?? '')),
    'tenant_id' => $tenantId,
]);

if ($returnTo !== '') {
    $separator = strpos($returnTo, '?') === false ? '?' : '&';
    header('Location: ' . $returnTo . $separator . 'status=time-created');
    exit;
}

header('Location: project-view.php?id=' . $projectId . '&status=time-created');
exit;
