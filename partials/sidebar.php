<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$current = basename($scriptPath);
$user = current_user();

if (!function_exists('render_sidebar_link')) {
    function render_sidebar_link(string $href, string $label, string $current, string $scriptPath): string
    {
        $hrefBasename = basename($href);
        $active = ($hrefBasename === $current && str_contains($scriptPath, trim($hrefBasename, '/')))
            ? ' sidebar__link--active'
            : '';

        return sprintf(
            '<a href="%s" class="sidebar__link%s">%s</a>',
            htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $active,
            htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}
?>
<aside class="sidebar">
    <div class="sidebar__brand">
        <?php $basePath = str_starts_with($scriptPath, '/admin/') ? '../' : ''; ?>
        <img class="sidebar__logo" src="<?= htmlspecialchars($basePath . 'sys-expertslogo.png', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="sys experts">
    </div>
    <nav class="sidebar__nav">
        <?= render_sidebar_link($basePath . 'index.php', 'Dashboard', $current, $scriptPath); ?>
        <?php
        $menu = [
            ['href' => 'customers.php', 'label' => 'Kunden', 'module' => 'CRM'],
            ['href' => 'services.php', 'label' => 'Leistungen', 'module' => 'CRM'],
            ['href' => 'projects.php', 'label' => 'Projekte', 'module' => 'Projekte'],
            ['href' => 'invoices.php', 'label' => 'Rechnungen', 'module' => 'Buchhaltung'],
            ['href' => 'expenses.php', 'label' => 'Ausgaben', 'module' => 'Buchhaltung'],
            ['href' => 'inventory.php', 'label' => 'Inventar', 'module' => 'Lager'],
            ['href' => 'support.php', 'label' => 'Support', 'module' => 'Support'],
            ['href' => 'recurring.php', 'label' => 'Abos', 'module' => 'Buchhaltung'],
        ];

        foreach ($menu as $entry) {
            if ($user === null || ($entry['module'] !== null && !user_has_module($entry['module']))) {
                continue;
            }
            echo render_sidebar_link($basePath . $entry['href'], $entry['label'], $current, $scriptPath);
        }

        if ($user !== null && user_has_role(['superadmin'])) {
            echo render_sidebar_link($basePath . 'admin/index.php', 'Admin', $current, $scriptPath);
        }
        ?>
    </nav>
    <div class="sidebar__footer">
        <?php if ($user !== null): ?>
            <small><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small>
        <?php endif; ?>
        <a class="sidebar__logout" href="<?= htmlspecialchars($basePath . 'logout.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Logout</a>
    </div>
</aside>

