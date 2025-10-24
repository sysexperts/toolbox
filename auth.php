<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

require_login();

if (!function_exists('require_role')) {
    function require_role(string ...$roles): void
    {
        if (!user_has_role($roles)) {
            $target = 'index.php?status=forbidden';

            if (!headers_sent()) {
                header('Location: ' . $target);
            } else {
                echo '<p>Zugriff verweigert. <a href="' . htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Zurueck zum Dashboard</a></p>';
            }

            exit;
        }
    }
}

if (!function_exists('require_module_access')) {
    function require_module_access(string $moduleName): void
    {
        if (!user_has_module($moduleName)) {
            http_response_code(403);
            echo 'Dieses Modul ist nicht aktiviert.';
            exit;
        }
    }
}

