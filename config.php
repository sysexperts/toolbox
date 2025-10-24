<?php



declare(strict_types=1);







const DB_PATH = __DIR__ . '/data/site.sqlite';



const ADMIN_LOGIN = 'admin';



const ADMIN_PASSWORD = 'admin';



const APP_NAME = 'Business Manager';







function ensure_session_started(): void



{



    if (session_status() === PHP_SESSION_NONE) {



        session_start();



    }



}







function is_logged_in(): bool



{



    ensure_session_started();



    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);



}







function current_user(): ?array



{



    ensure_session_started();



    if (!isset($_SESSION['user'])) {



        return null;



    }







    if (!isset($_SESSION['user']['refreshed_at']) || (time() - (int)$_SESSION['user']['refreshed_at']) > 60) {



        $pdo = get_database();



        $stmt = $pdo->prepare(



            'SELECT users.*, tenants.name AS tenant_name, tenants.status AS tenant_status



             FROM users



             LEFT JOIN tenants ON tenants.id = users.tenant_id



             WHERE users.id = :id'



        );



        $stmt->execute([':id' => $_SESSION['user']['id']]);



        $fresh = $stmt->fetch();



        if ($fresh) {



            $_SESSION['user'] = array_merge($_SESSION['user'], $fresh, [



                'modules' => get_enabled_modules_for_user($pdo, $fresh),



                'refreshed_at' => time(),



            ]);



            $_SESSION['user']['tenant_id'] = normalize_tenant_id($_SESSION['user']['tenant_id'] ?? null);



        }



    }







    return $_SESSION['user'];



}







function require_login(): void



{



    if (!is_logged_in()) {



        header('Location: login.php');



        exit;



    }







    $user = current_user();



    if ($user === null) {



        logout_user();



        header('Location: login.php');



        exit;



    }







    if (!user_has_role(['superadmin']) && isset($user['tenant_id'])) {



        if (!tenant_has_valid_license((int)$user['tenant_id'])) {



            logout_user();



            header('Location: login.php?license=invalid');



            exit;



        }



    }



}







function normalize_tenant_id(mixed $tenantId): ?int



{



    if ($tenantId === null || $tenantId === '') {



        return null;



    }







    return (int)$tenantId;



}







function login_user(array $user): void



{



    ensure_session_started();



    $_SESSION['user'] = [



        'id' => $user['id'] ?? 0,



        'tenant_id' => normalize_tenant_id($user['tenant_id'] ?? null),



        'email' => $user['email'] ?? '',



        'role' => $user['role'] ?? 'user',



        'name' => $user['name'] ?? null,



        'modules' => $user['modules'] ?? [],



        'refreshed_at' => time(),



    ];



}







function logout_user(): void



{



    ensure_session_started();



    $_SESSION = [];







    if (ini_get('session.use_cookies')) {



        $params = session_get_cookie_params();



        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);



    }







    session_destroy();



}







function attempt_login(string $login, string $password): ?array



{



    $pdo = get_database();



    $stmt = $pdo->prepare(



        'SELECT users.*, tenants.name AS tenant_name, tenants.status AS tenant_status



         FROM users



         LEFT JOIN tenants ON tenants.id = users.tenant_id



         WHERE LOWER(users.email) = LOWER(:login)



         LIMIT 1'



    );



    $stmt->execute([':login' => $login]);



    $user = $stmt->fetch();







    if ($user && isset($user['password']) && password_verify($password, (string)$user['password'])) {



        if (($user['tenant_id'] ?? null) !== null) {



            if (($user['tenant_status'] ?? '') !== 'active') {



                return null;



            }



            if (!tenant_has_valid_license((int)$user['tenant_id'])) {



                return null;



            }



        }



        $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id')->execute([':id' => $user['id']]);



        $user['modules'] = get_enabled_modules_for_user($pdo, $user);



        return $user;



    }







    if (strcasecmp($login, ADMIN_LOGIN) === 0 && hash_equals(ADMIN_PASSWORD, $password)) {



        return [



            'id' => 0,



            'tenant_id' => null,



            'email' => ADMIN_LOGIN,



            'name' => 'Superadmin',



            'role' => 'superadmin',



            'modules' => get_enabled_modules_for_user($pdo, ['id' => 0, 'tenant_id' => null, 'role' => 'superadmin']),



        ];



    }







    return null;



}







function user_has_role(array $roles): bool



{



    $user = current_user();



    if ($user === null) {



        return false;



    }







    return in_array((string)($user['role'] ?? 'user'), $roles, true);



}







function user_has_module(string $moduleName): bool



{



    $user = current_user();



    if ($user === null) {



        return false;



    }







    $modules = $user['modules'] ?? [];



    return in_array(strtolower($moduleName), array_map('strtolower', $modules), true);



}







function tenant_has_valid_license(int $tenantId): bool



{



    $pdo = get_database();



    $stmt = $pdo->prepare(



        'SELECT * FROM licenses



         WHERE tenant_id = :tenant_id



         ORDER BY valid_until DESC



         LIMIT 1'



    );



    $stmt->execute([':tenant_id' => $tenantId]);



    $license = $stmt->fetch();







    if (!$license) {



        return true;



    }







    if ((int)$license['active'] !== 1) {



        return false;



    }







    if (!empty($license['valid_until']) && strtotime((string)$license['valid_until']) < strtotime('today')) {



        return false;



    }







    return true;



}







function tenant_condition(?int $tenantId, string $column = 'tenant_id'): array



{



    if ($tenantId === null) {



        return ['', []];



    }







    return [$column . ' = :tenant_id', [':tenant_id' => $tenantId]];



}







function fetch_column(PDO $pdo, string $sql, array $params = [])



{



    $stmt = $pdo->prepare($sql);



    $stmt->execute($params);







    return $stmt->fetchColumn();



}







function get_enabled_modules_for_user(PDO $pdo, array $user): array
{
    static $cache = [];
    $cacheKey = ($user['id'] ?? 0) . '-' . ($user['tenant_id'] ?? 0);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare('SELECT name FROM modules ORDER BY name');
    $stmt->execute();
    $allModules = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (($user['role'] ?? '') === 'superadmin') {
        $cache[$cacheKey] = $allModules;
        return $cache[$cacheKey];
    }

    $moduleNames = [];

    if (!empty($user['tenant_id'])) {
        $stmt = $pdo->prepare(
            'SELECT modules.name
             FROM tenant_modules
             JOIN modules ON modules.id = tenant_modules.module_id
             WHERE tenant_modules.tenant_id = :tenant_id'
        );
        $stmt->execute([':tenant_id' => $user['tenant_id']]);
        $moduleNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $stmt = $pdo->prepare(
        'SELECT modules.name
         FROM user_modules
         JOIN modules ON modules.id = user_modules.module_id
         WHERE user_modules.user_id = :user_id'
    );
    $stmt->execute([':user_id' => $user['id'] ?? 0]);
    $userModules = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($userModules)) {
        $moduleNames = array_merge($moduleNames, $userModules);
    }

    $moduleNames = array_values(array_unique($moduleNames));

    if (empty($moduleNames)) {
        $cache[$cacheKey] = $allModules;
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = $moduleNames;
    return $cache[$cacheKey];
}

function get_database(): PDO



{



    static $pdo = null;







    if ($pdo instanceof PDO) {



        return $pdo;



    }







    $directory = dirname(DB_PATH);



    if (!is_dir($directory)) {



        mkdir($directory, 0777, true);



    }







    $pdo = new PDO('sqlite:' . DB_PATH);



    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);



    $pdo->exec('PRAGMA foreign_keys = ON');







    initialize_schema($pdo);







    return $pdo;



}







function initialize_schema(PDO $pdo): void



{



    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS tenants (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            name TEXT NOT NULL,



            email TEXT NOT NULL,



            status TEXT NOT NULL DEFAULT "pending",



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS users (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            tenant_id INTEGER,



            email TEXT NOT NULL UNIQUE,



            password TEXT NOT NULL,



            name TEXT,



            role TEXT NOT NULL DEFAULT "user",



            last_login_at TEXT,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS modules (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            name TEXT NOT NULL UNIQUE,



            description TEXT,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS tenant_modules (



            tenant_id INTEGER NOT NULL,



            module_id INTEGER NOT NULL,



            PRIMARY KEY (tenant_id, module_id),



            FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,



            FOREIGN KEY(module_id) REFERENCES modules(id) ON DELETE CASCADE



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS user_modules (



            user_id INTEGER NOT NULL,



            module_id INTEGER NOT NULL,



            PRIMARY KEY (user_id, module_id),



            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,



            FOREIGN KEY(module_id) REFERENCES modules(id) ON DELETE CASCADE



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS licenses (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            tenant_id INTEGER NOT NULL,



            license_key TEXT,



            valid_until TEXT,



            active INTEGER NOT NULL DEFAULT 1,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS admin_notifications (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            tenant_id INTEGER,



            message TEXT NOT NULL,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            seen INTEGER NOT NULL DEFAULT 0,



            FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS customers (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            company TEXT NOT NULL,



            contact_name TEXT,



            email TEXT,



            phone TEXT,



            address_line TEXT,



            postal_code TEXT,



            city TEXT,



            country TEXT,



            notes TEXT,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS invoices (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            sequence INTEGER NOT NULL UNIQUE,



            invoice_number TEXT NOT NULL UNIQUE,



            customer_id INTEGER NOT NULL,



            issue_date TEXT NOT NULL,



            due_date TEXT NOT NULL,



            status TEXT NOT NULL DEFAULT "open",



            currency TEXT NOT NULL DEFAULT "EUR",



            subtotal REAL NOT NULL,



            tax_rate REAL NOT NULL,



            tax_total REAL NOT NULL,



            total REAL NOT NULL,



            notes TEXT,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE



        )'



    );



    ensure_invoice_currency_column($pdo);







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS invoice_items (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            invoice_id INTEGER NOT NULL,



            position INTEGER NOT NULL,



            description TEXT NOT NULL,



            quantity REAL NOT NULL,



            unit_price REAL NOT NULL,



            line_total REAL NOT NULL,



            FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS payments (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            invoice_id INTEGER NOT NULL,



            amount REAL NOT NULL,



            payment_date TEXT NOT NULL,



            method TEXT,



            reference TEXT,



            notes TEXT,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS expenses (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            vendor TEXT NOT NULL,



            category TEXT NOT NULL,



            amount REAL NOT NULL,



            tax_rate REAL NOT NULL DEFAULT 0,



            tax_total REAL NOT NULL DEFAULT 0,



            total REAL NOT NULL,



            expense_date TEXT NOT NULL,



            payment_method TEXT,



            notes TEXT,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS services (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            name TEXT NOT NULL,



            description TEXT,



            unit_price REAL NOT NULL,



            unit_cost REAL,



            billing_type TEXT NOT NULL DEFAULT "fixed",



            active INTEGER NOT NULL DEFAULT 1,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS projects (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            customer_id INTEGER,



            name TEXT NOT NULL,



            status TEXT NOT NULL DEFAULT "planning",



            start_date TEXT,



            due_date TEXT,



            budget_hours REAL,



            hourly_rate REAL,



            service_type TEXT,



            notes TEXT,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS time_entries (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            project_id INTEGER NOT NULL,



            user_name TEXT NOT NULL,



            entry_date TEXT NOT NULL,



            hours REAL NOT NULL,



            billable INTEGER NOT NULL DEFAULT 1,



            description TEXT,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS support_tickets (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            customer_id INTEGER,



            subject TEXT NOT NULL,



            status TEXT NOT NULL DEFAULT "open",



            priority TEXT NOT NULL DEFAULT "medium",



            description TEXT,



            assigned_to TEXT,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS inventory_items (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            sku TEXT NOT NULL,



            name TEXT NOT NULL,



            quantity REAL NOT NULL DEFAULT 0,



            reorder_level REAL NOT NULL DEFAULT 0,



            unit_cost REAL NOT NULL DEFAULT 0,



            unit_price REAL NOT NULL DEFAULT 0,



            location TEXT,



            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP



        )'



    );







    $pdo->exec(



        'CREATE TABLE IF NOT EXISTS recurring_invoices (



            id INTEGER PRIMARY KEY AUTOINCREMENT,



            customer_id INTEGER NOT NULL,



            service_overview TEXT NOT NULL,



            start_date TEXT NOT NULL,



            frequency TEXT NOT NULL,



            occurrences INTEGER,



            subtotal REAL NOT NULL,



            tax_rate REAL NOT NULL,



            tax_total REAL NOT NULL,



            total REAL NOT NULL,



            notes TEXT,



            next_run_at TEXT NOT NULL,



            last_run_at TEXT,



            template_payload TEXT NOT NULL,



            active INTEGER NOT NULL DEFAULT 1,



            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,



            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE



        )'



    );







    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_invoices_customer ON invoices(customer_id)');



    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice ON invoice_items(invoice_id)');



    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_payments_invoice ON payments(invoice_id)');



    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_customer ON projects(customer_id)');



    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_time_entries_project ON time_entries(project_id)');



    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_support_status ON support_tickets(status)');







    ensure_table_has_column($pdo, 'customers', 'tenant_id', 'INTEGER');



    ensure_table_has_column($pdo, 'customers', 'status', 'TEXT DEFAULT "aktiv"');



    ensure_table_has_column($pdo, 'customers', 'customer_number', 'TEXT');



    ensure_table_has_column($pdo, 'customers', 'account_manager', 'TEXT');



    ensure_table_has_column($pdo, 'customers', 'tags', 'TEXT');



    ensure_table_has_column($pdo, 'invoices', 'tenant_id', 'INTEGER');



    ensure_table_has_column($pdo, 'invoice_items', 'tenant_id', 'INTEGER');



    ensure_table_has_column($pdo, 'payments', 'tenant_id', 'INTEGER');



    ensure_table_has_column($pdo, 'expenses', 'tenant_id', 'INTEGER');



    ensure_table_has_column($pdo, 'services', 'tenant_id', 'INTEGER');



    ensure_table_has_column($pdo, 'projects', 'tenant_id', 'INTEGER');
    ensure_table_has_column($pdo, 'projects', 'consumed_hours', 'REAL DEFAULT 0');



    ensure_table_has_column($pdo, 'time_entries', 'tenant_id', 'INTEGER');



    ensure_table_has_column($pdo, 'support_tickets', 'tenant_id', 'INTEGER');



    ensure_table_has_column($pdo, 'inventory_items', 'tenant_id', 'INTEGER');



    ensure_table_has_column($pdo, 'recurring_invoices', 'tenant_id', 'INTEGER');







    ensure_default_modules($pdo);



    ensure_default_superadmin($pdo);



}







function ensure_invoice_currency_column(PDO $pdo): void



{



    $hasCurrency = false;



    $statement = $pdo->query('PRAGMA table_info(invoices)');



    if ($statement !== false) {



        foreach ($statement as $column) {



            if (isset($column['name']) && strcasecmp((string)$column['name'], 'currency') === 0) {



                $hasCurrency = true;



                break;



            }



        }



    }







    if (!$hasCurrency) {



        $pdo->exec('ALTER TABLE invoices ADD COLUMN currency TEXT NOT NULL DEFAULT "EUR"');



        $pdo->exec('UPDATE invoices SET currency = "EUR" WHERE currency IS NULL');



    }



}







function fetch_dashboard_summary(PDO $pdo, ?int $tenantId = null): array



{



    $summary = [];







    [$customerCondition, $customerParams] = tenant_condition($tenantId);



    $customerSql = 'SELECT COUNT(*) FROM customers';



    if ($customerCondition !== '') {



        $customerSql .= ' WHERE ' . $customerCondition;



    }



    $summary['customers'] = (int)fetch_column($pdo, $customerSql, $customerParams);







    [$invoiceCondition, $invoiceParams] = tenant_condition($tenantId, 'invoices.tenant_id');







    $openInvoiceSql = 'SELECT COUNT(*) FROM invoices';



    $openConditions = ['status = "open"'];



    if ($invoiceCondition !== '') {



        $openConditions[] = $invoiceCondition;



    }



    $summary['open_invoices'] = (int)fetch_column(



        $pdo,



        $openInvoiceSql . ' WHERE ' . implode(' AND ', $openConditions),



        $invoiceCondition !== '' ? $invoiceParams : []



    );







    $overdueConditions = ['status = "open"', 'due_date < date("now")'];



    if ($invoiceCondition !== '') {



        $overdueConditions[] = $invoiceCondition;



    }



    $summary['overdue_invoices'] = (int)fetch_column(



        $pdo,



        'SELECT COUNT(*) FROM invoices WHERE ' . implode(' AND ', $overdueConditions),



        $invoiceCondition !== '' ? $invoiceParams : []



    );







    $revenueConditions = ['status = "paid"', 'strftime("%Y", issue_date) = strftime("%Y", "now")'];



    if ($invoiceCondition !== '') {



        $revenueConditions[] = $invoiceCondition;



    }



    $summary['revenue_ytd'] = (float)fetch_column(



        $pdo,



        'SELECT IFNULL(SUM(total), 0) FROM invoices WHERE ' . implode(' AND ', $revenueConditions),



        $invoiceCondition !== '' ? $invoiceParams : []



    );







    [$expenseCondition, $expenseParams] = tenant_condition($tenantId);



    $expenseConditions = ['strftime("%Y", expense_date) = strftime("%Y", "now")'];



    if ($expenseCondition !== '') {



        $expenseConditions[] = $expenseCondition;



    }



    $summary['expenses_ytd'] = (float)fetch_column(



        $pdo,



        'SELECT IFNULL(SUM(total), 0) FROM expenses WHERE ' . implode(' AND ', $expenseConditions),



        $expenseCondition !== '' ? $expenseParams : []



    );







    [$projectCondition, $projectParams] = tenant_condition($tenantId, 'projects.tenant_id');



    $projectConditions = ['status IN ("planning","in_progress")'];



    if ($projectCondition !== '') {



        $projectConditions[] = $projectCondition;



    }



    $summary['active_projects'] = (int)fetch_column(



        $pdo,



        'SELECT COUNT(*) FROM projects WHERE ' . implode(' AND ', $projectConditions),



        $projectCondition !== '' ? $projectParams : []



    );







    [$ticketCondition, $ticketParams] = tenant_condition($tenantId, 'support_tickets.tenant_id');



    $ticketConditions = ['status = "open"'];



    if ($ticketCondition !== '') {



        $ticketConditions[] = $ticketCondition;



    }



    $summary['open_tickets'] = (int)fetch_column(



        $pdo,



        'SELECT COUNT(*) FROM support_tickets WHERE ' . implode(' AND ', $ticketConditions),



        $ticketCondition !== '' ? $ticketParams : []



    );







    $openAmountConditions = ['status IN ("open","draft")'];



    if ($invoiceCondition !== '') {



        $openAmountConditions[] = $invoiceCondition;



    }



    $summary['open_invoice_amount'] = (float)fetch_column(



        $pdo,



        'SELECT IFNULL(SUM(total), 0) FROM invoices WHERE ' . implode(' AND ', $openAmountConditions),



        $invoiceCondition !== '' ? $invoiceParams : []



    );







    $overdueAmountConditions = ['status = "open"', 'due_date < date("now")'];



    if ($invoiceCondition !== '') {



        $overdueAmountConditions[] = $invoiceCondition;



    }



    $summary['overdue_invoice_amount'] = (float)fetch_column(



        $pdo,



        'SELECT IFNULL(SUM(total), 0) FROM invoices WHERE ' . implode(' AND ', $overdueAmountConditions),



        $invoiceCondition !== '' ? $invoiceParams : []



    );







    $summary['cashflow_ytd'] = $summary['revenue_ytd'] - $summary['expenses_ytd'];







    $recentInvoiceSql = 'SELECT invoices.id,



                invoices.invoice_number,



                invoices.issue_date,



                invoices.due_date,



                invoices.total,



                invoices.status,



                invoices.currency,



                customers.company



         FROM invoices



         JOIN customers ON customers.id = invoices.customer_id';



    $recentInvoiceParams = [];



    if ($invoiceCondition !== '') {



        $recentInvoiceSql .= ' WHERE ' . $invoiceCondition;



        $recentInvoiceParams = $invoiceParams;



    }



    $recentInvoiceSql .= ' ORDER BY invoices.created_at DESC LIMIT 5';



    $stmt = $pdo->prepare($recentInvoiceSql);



    $stmt->execute($recentInvoiceParams);



    $summary['recent_invoices'] = $stmt->fetchAll();







    $recentTicketSql = 'SELECT support_tickets.id,



                support_tickets.subject,



                support_tickets.status,



                support_tickets.priority,



                support_tickets.created_at,



                customers.company



         FROM support_tickets



         LEFT JOIN customers ON customers.id = support_tickets.customer_id';



    $recentTicketParams = [];



    if ($ticketCondition !== '') {



        $recentTicketSql .= ' WHERE ' . $ticketCondition;



        $recentTicketParams = $ticketParams;



    }



    $recentTicketSql .= ' ORDER BY support_tickets.created_at DESC LIMIT 5';



    $stmt = $pdo->prepare($recentTicketSql);



    $stmt->execute($recentTicketParams);



    $summary['recent_tickets'] = $stmt->fetchAll();







    $recentExpenseSql = 'SELECT vendor, category, total, expense_date



         FROM expenses';



    $recentExpenseParams = [];



    if ($expenseCondition !== '') {



        $recentExpenseSql .= ' WHERE ' . $expenseCondition;



        $recentExpenseParams = $expenseParams;



    }



    $recentExpenseSql .= ' ORDER BY expense_date DESC LIMIT 4';



    $stmt = $pdo->prepare($recentExpenseSql);



    $stmt->execute($recentExpenseParams);



    $summary['recent_expenses'] = $stmt->fetchAll();







    $recentProjectSql = 'SELECT projects.name,



                projects.status,



                projects.due_date,



                customers.company



         FROM projects



         LEFT JOIN customers ON customers.id = projects.customer_id';



    $recentProjectParams = [];



    if ($projectCondition !== '') {



        $recentProjectSql .= ' WHERE ' . $projectCondition;



        $recentProjectParams = $projectParams;



    }



    $recentProjectSql .= ' ORDER BY projects.due_date IS NULL, projects.due_date ASC, projects.created_at DESC LIMIT 4';



    $stmt = $pdo->prepare($recentProjectSql);



    $stmt->execute($recentProjectParams);



    $summary['recent_projects'] = $stmt->fetchAll();







    $recentPaymentsSql = 'SELECT payments.amount,



                payments.payment_date,



                payments.method,



                invoices.invoice_number



         FROM payments



         JOIN invoices ON invoices.id = payments.invoice_id';



    $recentPaymentParams = [];



    if ($invoiceCondition !== '') {



        $recentPaymentsSql .= ' WHERE ' . $invoiceCondition;



        $recentPaymentParams = $invoiceParams;



    }



    $recentPaymentsSql .= ' ORDER BY payments.payment_date DESC, payments.created_at DESC LIMIT 4';



    $stmt = $pdo->prepare($recentPaymentsSql);



    $stmt->execute($recentPaymentParams);



    $summary['recent_payments'] = $stmt->fetchAll();







    return $summary;



}







function get_customers(PDO $pdo, ?int $tenantId = null): array



{



    $sql = 'SELECT id, company, contact_name, email, phone, city, created_at, tenant_id, status, customer_number, account_manager, tags



             FROM customers';



    [$condition, $params] = tenant_condition($tenantId);



    if ($condition !== '') {



        $sql .= ' WHERE ' . $condition;



    }



    $sql .= ' ORDER BY company COLLATE NOCASE';







    $stmt = $pdo->prepare($sql);



    $stmt->execute($condition !== '' ? $params : []);







    return $stmt->fetchAll();



}







function get_customer(PDO $pdo, int $customerId, ?int $tenantId = null): ?array



{



    $sql = 'SELECT * FROM customers WHERE id = :id';



    $params = [':id' => $customerId];







    if ($tenantId !== null) {



        $sql .= ' AND tenant_id = :tenant_id';



        $params[':tenant_id'] = $tenantId;



    }







    $stmt = $pdo->prepare($sql);



    $stmt->execute($params);



    $customer = $stmt->fetch();







    return $customer ?: null;



}







function create_customer(PDO $pdo, array $data): int



{



    $stmt = $pdo->prepare(



        'INSERT INTO customers (



            company,



            contact_name,



            email,



            phone,



            address_line,



            postal_code,



            city,



            country,



            notes,



            tenant_id,



            status,



            customer_number



        ) VALUES (



            :company,



            :contact_name,



            :email,



            :phone,



            :address_line,



            :postal_code,



            :city,



            :country,



            :notes,



            :tenant_id,



            :status,



            :customer_number



        )'



    );







    $stmt->execute([



        ':company' => $data['company'],



        ':contact_name' => $data['contact_name'] ?? null,



        ':email' => $data['email'] ?? null,



        ':phone' => $data['phone'] ?? null,



        ':address_line' => $data['address_line'] ?? null,



        ':postal_code' => $data['postal_code'] ?? null,



        ':city' => $data['city'] ?? null,



        ':country' => $data['country'] ?? null,



        ':notes' => $data['notes'] ?? null,



        ':tenant_id' => $data['tenant_id'] ?? null,



        ':status' => $data['status'] ?? 'aktiv',



        ':customer_number' => $data['customer_number'] ?? null,



    ]);







    return (int)$pdo->lastInsertId();



}







function update_customer(PDO $pdo, int $customerId, array $data): void



{



    $sql = 'UPDATE customers



         SET company = :company,



             contact_name = :contact_name,



             email = :email,



             phone = :phone,



             address_line = :address_line,



             postal_code = :postal_code,



             city = :city,



             country = :country,



             notes = :notes,



             status = :status,



             customer_number = :customer_number



         WHERE id = :id';







    $params = [



        ':company' => $data['company'],



        ':contact_name' => $data['contact_name'] ?? null,



        ':email' => $data['email'] ?? null,



        ':phone' => $data['phone'] ?? null,



        ':address_line' => $data['address_line'] ?? null,



        ':postal_code' => $data['postal_code'] ?? null,



        ':city' => $data['city'] ?? null,



        ':country' => $data['country'] ?? null,



        ':notes' => $data['notes'] ?? null,



        ':status' => $data['status'] ?? 'aktiv',



        ':customer_number' => $data['customer_number'] ?? null,



        ':id' => $customerId,



    ];







    if (isset($data['tenant_id'])) {



        $sql .= ' AND tenant_id = :tenant_id';



        $params[':tenant_id'] = $data['tenant_id'];



    }







    $stmt = $pdo->prepare($sql);



    $stmt->execute($params);



}







function delete_customer(PDO $pdo, int $customerId, ?int $tenantId = null): void



{



    $sql = 'DELETE FROM customers WHERE id = :id';



    $params = [':id' => $customerId];







    if ($tenantId !== null) {



        $sql .= ' AND tenant_id = :tenant_id';



        $params[':tenant_id'] = $tenantId;



    }







    $stmt = $pdo->prepare($sql);



    $stmt->execute($params);



}







function get_services(PDO $pdo, ?int $tenantId = null): array



{



    $sql = 'SELECT id, name, description, unit_price, unit_cost, billing_type, active, created_at



         FROM services';



    [$condition, $params] = tenant_condition($tenantId);



    if ($condition !== '') {



        $sql .= ' WHERE ' . $condition;



    }



    $sql .= ' ORDER BY active DESC, name COLLATE NOCASE';







    $stmt = $pdo->prepare($sql);



    $stmt->execute($condition !== '' ? $params : []);







    return $stmt->fetchAll();



}







function create_service(PDO $pdo, array $payload): int



{



    $stmt = $pdo->prepare(



        'INSERT INTO services (name, description, unit_price, unit_cost, billing_type, active, tenant_id)



         VALUES (:name, :description, :unit_price, :unit_cost, :billing_type, :active, :tenant_id)'



    );







    $stmt->execute([



        ':name' => $payload['name'],



        ':description' => $payload['description'] ?? null,



        ':unit_price' => $payload['unit_price'],



        ':unit_cost' => $payload['unit_cost'] ?? null,



        ':billing_type' => $payload['billing_type'] ?? 'fixed',



        ':active' => isset($payload['active']) && (int)$payload['active'] ? 1 : 0,



        ':tenant_id' => $payload['tenant_id'] ?? null,



    ]);







    return (int)$pdo->lastInsertId();



}







function toggle_service(PDO $pdo, int $serviceId, bool $active, ?int $tenantId = null): void



{



    $sql = 'UPDATE services SET active = :active WHERE id = :id';



    $params = [



        ':active' => $active ? 1 : 0,



        ':id' => $serviceId,



    ];







    if ($tenantId !== null) {



        $sql .= ' AND tenant_id = :tenant_id';



        $params[':tenant_id'] = $tenantId;



    }







    $stmt = $pdo->prepare($sql);



    $stmt->execute($params);



}







function get_projects(PDO $pdo, ?int $tenantId = null): array



{



    $sql = 'SELECT projects.id,



                projects.name,



                projects.status,



                projects.start_date,



                projects.due_date,



                projects.budget_hours,



                projects.hourly_rate,



                customers.company



         FROM projects



         LEFT JOIN customers ON customers.id = projects.customer_id';



    [$condition, $params] = tenant_condition($tenantId, 'projects.tenant_id');



    if ($condition !== '') {



        $sql .= ' WHERE ' . $condition;



    }



    $sql .= ' ORDER BY projects.created_at DESC';







    $stmt = $pdo->prepare($sql);



    $stmt->execute($condition !== '' ? $params : []);







    return $stmt->fetchAll();



}







function get_project_detail(PDO $pdo, int $projectId, ?int $tenantId = null): ?array



{



    $sql = 'SELECT projects.*,



                customers.company,



                customers.contact_name,



                customers.email



         FROM projects



         LEFT JOIN customers ON customers.id = projects.customer_id



         WHERE projects.id = :id';



    $params = [':id' => $projectId];



    if ($tenantId !== null) {



        $sql .= ' AND projects.tenant_id = :tenant_id';



        $params[':tenant_id'] = $tenantId;



    }



    $projectStmt = $pdo->prepare($sql);



    $projectStmt->execute($params);



    $project = $projectStmt->fetch();







    if (!$project) {



        return null;



    }







    $entriesSql = 'SELECT id, user_name, entry_date, hours, billable, description



         FROM time_entries



         WHERE project_id = :id';



    $entryParams = [':id' => $projectId];



    if ($tenantId !== null) {



        $entriesSql .= ' AND tenant_id = :tenant_id';



        $entryParams[':tenant_id'] = $tenantId;



    }



    $entriesSql .= ' ORDER BY entry_date DESC, created_at DESC';



    $entriesStmt = $pdo->prepare($entriesSql);



    $entriesStmt->execute($entryParams);



    $project['time_entries'] = $entriesStmt->fetchAll();







    $totalsSql = 'SELECT



            IFNULL(SUM(hours), 0) AS total_hours,



            IFNULL(SUM(CASE WHEN billable = 1 THEN hours ELSE 0 END), 0) AS billable_hours



         FROM time_entries



         WHERE project_id = :id';



    $totalParams = [':id' => $projectId];



    if ($tenantId !== null) {



        $totalsSql .= ' AND tenant_id = :tenant_id';



        $totalParams[':tenant_id'] = $tenantId;



    }



    $totalsStmt = $pdo->prepare($totalsSql);



    $totalsStmt->execute($totalParams);



    $project['totals'] = $totalsStmt->fetch();







    return $project;



}







function create_project(PDO $pdo, array $payload): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO projects (customer_id, name, status, start_date, due_date, budget_hours, consumed_hours, hourly_rate, service_type, notes, tenant_id)
         VALUES (:customer_id, :name, :status, :start_date, :due_date, :budget_hours, :consumed_hours, :hourly_rate, :service_type, :notes, :tenant_id)'
    );

    $stmt->execute([
        ':customer_id' => $payload['customer_id'] ?: null,
        ':name' => $payload['name'],
        ':status' => $payload['status'] ?? 'planning',
        ':start_date' => $payload['start_date'] ?: null,
        ':due_date' => $payload['due_date'] ?: null,
        ':budget_hours' => $payload['budget_hours'] !== '' ? $payload['budget_hours'] : null,
        ':consumed_hours' => $payload['consumed_hours'] !== '' ? $payload['consumed_hours'] : 0,
        ':hourly_rate' => $payload['hourly_rate'] !== '' ? $payload['hourly_rate'] : null,
        ':service_type' => $payload['service_type'] ?? null,
        ':notes' => $payload['notes'] ?? null,
        ':tenant_id' => $payload['tenant_id'] ?? null,
    ]);

    return (int)$pdo->lastInsertId();
}








function update_project(PDO $pdo, int $projectId, array $payload): void
{
    $stmt = $pdo->prepare(
        'UPDATE projects
         SET customer_id = :customer_id,
             name = :name,
             status = :status,
             start_date = :start_date,
             due_date = :due_date,
             budget_hours = :budget_hours,
             consumed_hours = :consumed_hours,
             hourly_rate = :hourly_rate,
             service_type = :service_type,
             notes = :notes
         WHERE id = :id'
    );

    $stmt->execute([
        ':customer_id' => $payload['customer_id'] ?: null,
        ':name' => $payload['name'],
        ':status' => $payload['status'] ?? 'planning',
        ':start_date' => $payload['start_date'] ?: null,
        ':due_date' => $payload['due_date'] ?: null,
        ':budget_hours' => $payload['budget_hours'] !== '' ? $payload['budget_hours'] : null,
        ':consumed_hours' => $payload['consumed_hours'] !== '' ? $payload['consumed_hours'] : null,
        ':hourly_rate' => $payload['hourly_rate'] !== '' ? $payload['hourly_rate'] : null,
        ':service_type' => $payload['service_type'] ?? null,
        ':notes' => $payload['notes'] ?? null,
        ':id' => $projectId,
    ]);
}









function create_time_entry(PDO $pdo, array $payload): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO time_entries (project_id, user_name, entry_date, hours, billable, description, tenant_id)
         VALUES (:project_id, :user_name, :entry_date, :hours, :billable, :description, :tenant_id)'
    );

    $stmt->execute([
        ':project_id' => $payload['project_id'],
        ':user_name' => $payload['user_name'],
        ':entry_date' => $payload['entry_date'],
        ':hours' => $payload['hours'],
        ':billable' => isset($payload['billable']) && (int)$payload['billable'] ? 1 : 0,
        ':description' => $payload['description'] ?? null,
        ':tenant_id' => $payload['tenant_id'] ?? null,
    ]);

    $hours = (float)$payload['hours'];
    $projectId = (int)$payload['project_id'];

    $updateStmt = $pdo->prepare(
        'UPDATE projects
         SET consumed_hours = COALESCE(consumed_hours, 0) + :hours
         WHERE id = :id'
    );
    $updateStmt->execute([
        ':hours' => $hours,
        ':id' => $projectId,
    ]);

    return (int)$pdo->lastInsertId();
}








function get_inventory(PDO $pdo, ?int $tenantId = null): array



{



    $sql = 'SELECT id, sku, name, quantity, reorder_level, unit_cost, unit_price, location, updated_at



         FROM inventory_items';



    [$condition, $params] = tenant_condition($tenantId);



    if ($condition !== '') {



        $sql .= ' WHERE ' . $condition;



    }



    $sql .= ' ORDER BY name COLLATE NOCASE';







    $stmt = $pdo->prepare($sql);



    $stmt->execute($condition !== '' ? $params : []);







    return $stmt->fetchAll();



}







function create_inventory_item(PDO $pdo, array $payload): int



{



    $stmt = $pdo->prepare(



        'INSERT INTO inventory_items (sku, name, quantity, reorder_level, unit_cost, unit_price, location, updated_at, tenant_id)



         VALUES (:sku, :name, :quantity, :reorder_level, :unit_cost, :unit_price, :location, CURRENT_TIMESTAMP, :tenant_id)'



    );







    $stmt->execute([



        ':sku' => $payload['sku'],



        ':name' => $payload['name'],



        ':quantity' => $payload['quantity'],



        ':reorder_level' => $payload['reorder_level'] ?? 0,



        ':unit_cost' => $payload['unit_cost'] ?? 0,



        ':unit_price' => $payload['unit_price'] ?? 0,



        ':location' => $payload['location'] ?? null,



        ':tenant_id' => $payload['tenant_id'] ?? null,



    ]);







    return (int)$pdo->lastInsertId();



}







function get_support_tickets(PDO $pdo, ?int $tenantId = null): array



{



    $sql = 'SELECT support_tickets.id,



                support_tickets.subject,



                support_tickets.status,



                support_tickets.priority,



                support_tickets.assigned_to,



                support_tickets.created_at,



                customers.company



         FROM support_tickets



         LEFT JOIN customers ON customers.id = support_tickets.customer_id';



    [$condition, $params] = tenant_condition($tenantId, 'support_tickets.tenant_id');



    if ($condition !== '') {



        $sql .= ' WHERE ' . $condition;



    }



    $sql .= ' ORDER BY support_tickets.created_at DESC';







    $stmt = $pdo->prepare($sql);



    $stmt->execute($condition !== '' ? $params : []);







    return $stmt->fetchAll();



}







function create_support_ticket(PDO $pdo, array $payload): int



{



    $stmt = $pdo->prepare(



        'INSERT INTO support_tickets (customer_id, subject, status, priority, description, assigned_to, created_at, updated_at, tenant_id)



         VALUES (:customer_id, :subject, :status, :priority, :description, :assigned_to, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :tenant_id)'



    );







    $stmt->execute([



        ':customer_id' => $payload['customer_id'] ?: null,



        ':subject' => $payload['subject'],



        ':status' => $payload['status'] ?? 'open',



        ':priority' => $payload['priority'] ?? 'medium',



        ':description' => $payload['description'] ?? null,



        ':assigned_to' => $payload['assigned_to'] ?? null,



        ':tenant_id' => $payload['tenant_id'] ?? null,



    ]);







    return (int)$pdo->lastInsertId();



}







function update_ticket_status(PDO $pdo, int $ticketId, string $status, ?int $tenantId = null): void



{



    $sql = 'UPDATE support_tickets SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id';



    $params = [



        ':status' => $status,



        ':id' => $ticketId,



    ];



    if ($tenantId !== null) {



        $sql .= ' AND tenant_id = :tenant_id';



        $params[':tenant_id'] = $tenantId;



    }







    $stmt = $pdo->prepare($sql);



    $stmt->execute($params);



}







function list_expenses(PDO $pdo, ?int $tenantId = null): array



{



    $sql = 'SELECT id, vendor, category, amount, tax_rate, tax_total, total, expense_date, payment_method, notes



         FROM expenses';



    [$condition, $params] = tenant_condition($tenantId);



    if ($condition !== '') {



        $sql .= ' WHERE ' . $condition;



    }



    $sql .= ' ORDER BY expense_date DESC';







    $stmt = $pdo->prepare($sql);



    $stmt->execute($condition !== '' ? $params : []);







    return $stmt->fetchAll();



}







function create_expense(PDO $pdo, array $payload): int



{



    $amount = (float)$payload['amount'];



    $taxRate = (float)($payload['tax_rate'] ?? 0);



    $taxTotal = round($amount * ($taxRate / 100), 2);



    $total = round($amount + $taxTotal, 2);







    $stmt = $pdo->prepare(



        'INSERT INTO expenses (vendor, category, amount, tax_rate, tax_total, total, expense_date, payment_method, notes, tenant_id)



         VALUES (:vendor, :category, :amount, :tax_rate, :tax_total, :total, :expense_date, :payment_method, :notes, :tenant_id)'



    );







    $stmt->execute([



        ':vendor' => $payload['vendor'],



        ':category' => $payload['category'],



        ':amount' => $amount,



        ':tax_rate' => $taxRate,



        ':tax_total' => $taxTotal,



        ':total' => $total,



        ':expense_date' => $payload['expense_date'],



        ':payment_method' => $payload['payment_method'] ?? null,



        ':notes' => $payload['notes'] ?? null,



        ':tenant_id' => $payload['tenant_id'] ?? null,



    ]);







    return (int)$pdo->lastInsertId();



}







function get_invoices(PDO $pdo, ?int $tenantId = null): array



{



    $sql = 'SELECT invoices.id,



                invoices.invoice_number,



                invoices.issue_date,



                invoices.due_date,



                invoices.total,



                invoices.status,



                invoices.currency,



                customers.company



         FROM invoices



         JOIN customers ON customers.id = invoices.customer_id';



    [$condition, $params] = tenant_condition($tenantId, 'invoices.tenant_id');



    if ($condition !== '') {



        $sql .= ' WHERE ' . $condition;



    }



    $sql .= ' ORDER BY invoices.issue_date DESC';







    $stmt = $pdo->prepare($sql);



    $stmt->execute($condition !== '' ? $params : []);







    return $stmt->fetchAll();



}







function get_invoice(PDO $pdo, int $invoiceId, ?int $tenantId = null): ?array



{



    $sql = 'SELECT invoices.*,



                customers.company,



                customers.contact_name,



                customers.email,



                customers.phone,



                customers.address_line,



                customers.postal_code,



                customers.city,



                customers.country



         FROM invoices



         JOIN customers ON customers.id = invoices.customer_id



         WHERE invoices.id = :id';



    $params = [':id' => $invoiceId];



    if ($tenantId !== null) {



        $sql .= ' AND invoices.tenant_id = :tenant_id';



        $params[':tenant_id'] = $tenantId;



    }



    $stmt = $pdo->prepare($sql);



    $stmt->execute($params);



    $invoice = $stmt->fetch();







    if (!$invoice) {



        return null;



    }







    $itemsSql = 'SELECT position, description, quantity, unit_price, line_total



         FROM invoice_items



         WHERE invoice_id = :id';



    $itemParams = [':id' => $invoiceId];



    if ($tenantId !== null) {



        $itemsSql .= ' AND tenant_id = :tenant_id';



        $itemParams[':tenant_id'] = $tenantId;



    }



    $itemsSql .= ' ORDER BY position';



    $itemsStmt = $pdo->prepare($itemsSql);



    $itemsStmt->execute($itemParams);



    $invoice['items'] = $itemsStmt->fetchAll();







    $paymentsSql = 'SELECT id, amount, payment_date, method, reference, notes



         FROM payments



         WHERE invoice_id = :id';



    $paymentParams = [':id' => $invoiceId];



    if ($tenantId !== null) {



        $paymentsSql .= ' AND tenant_id = :tenant_id';



        $paymentParams[':tenant_id'] = $tenantId;



    }



    $paymentsSql .= ' ORDER BY payment_date DESC, created_at DESC';



    $paymentsStmt = $pdo->prepare($paymentsSql);



    $paymentsStmt->execute($paymentParams);



    $invoice['payments'] = $paymentsStmt->fetchAll();







    $invoice['paid_total'] = array_reduce(



        $invoice['payments'],



        static fn(float $carry, array $payment): float => $carry + (float)$payment['amount'],



        0.0



    );



    $invoice['balance_due'] = round((float)$invoice['total'] - (float)$invoice['paid_total'], 2);







    return $invoice;



}







function generate_next_invoice_sequence(PDO $pdo): int



{



    $sequence = (int)$pdo->query('SELECT IFNULL(MAX(sequence), 0) FROM invoices')->fetchColumn();



    return $sequence + 1;



}







function format_invoice_number(int $sequence): string



{



    $year = date('Y');



    return sprintf('INV-%s-%04d', $year, $sequence);



}







function create_invoice(PDO $pdo, array $invoiceData, array $items): int



{



    if (empty($items)) {



        throw new InvalidArgumentException('Eine Rechnung benoetigt mindestens eine Position.');



    }







    $pdo->beginTransaction();







    try {



        $sequence = generate_next_invoice_sequence($pdo);



        $invoiceNumber = format_invoice_number($sequence);







        $subtotal = 0.0;



        $sanitizedItems = [];







        foreach ($items as $index => $item) {



            $description = trim((string)$item['description']);



            $quantity = max((float)$item['quantity'], 0);



            $unitPrice = max((float)$item['unit_price'], 0);







            if ($description === '' || $quantity <= 0 || $unitPrice <= 0) {



                continue;



            }







            $lineTotal = round($quantity * $unitPrice, 2);



            $subtotal += $lineTotal;







            $sanitizedItems[] = [



                'position' => $index + 1,



                'description' => $description,



                'quantity' => $quantity,



                'unit_price' => $unitPrice,



                'line_total' => $lineTotal,



            ];



        }







        if (empty($sanitizedItems)) {



            throw new InvalidArgumentException('Bitte geben Sie gueltige Rechnungspositionen an.');



        }







        $taxRate = max((float)$invoiceData['tax_rate'], 0);



        $taxTotal = round($subtotal * ($taxRate / 100), 2);



        $total = round($subtotal + $taxTotal, 2);







        $stmt = $pdo->prepare(



            'INSERT INTO invoices (



                sequence,



                invoice_number,



                customer_id,



                issue_date,



                due_date,



                status,



                currency,



                subtotal,



                tax_rate,



                tax_total,



                total,



                notes,



                tenant_id



            ) VALUES (



                :sequence,



                :invoice_number,



                :customer_id,



                :issue_date,



                :due_date,



                :status,



                :currency,



                :subtotal,



                :tax_rate,



                :tax_total,



                :total,



                :notes,



                :tenant_id



            )'



        );







        $stmt->execute([



            ':sequence' => $sequence,



            ':invoice_number' => $invoiceNumber,



            ':customer_id' => (int)$invoiceData['customer_id'],



            ':issue_date' => $invoiceData['issue_date'],



            ':due_date' => $invoiceData['due_date'],



            ':status' => $invoiceData['status'] ?? 'open',



            ':currency' => $invoiceData['currency'] ?? 'EUR',



            ':subtotal' => $subtotal,



            ':tax_rate' => $taxRate,



            ':tax_total' => $taxTotal,



            ':total' => $total,



            ':notes' => $invoiceData['notes'] ?? null,



            ':tenant_id' => $invoiceData['tenant_id'] ?? null,



        ]);







        $invoiceId = (int)$pdo->lastInsertId();







        $itemStmt = $pdo->prepare(



            'INSERT INTO invoice_items (



                invoice_id,



                position,



                description,



                quantity,



                unit_price,



                line_total,



                tenant_id



            ) VALUES (



                :invoice_id,



                :position,



                :description,



                :quantity,



                :unit_price,



                :line_total,



                :tenant_id



            )'



        );







        foreach ($sanitizedItems as $item) {



            $itemStmt->execute([



                ':invoice_id' => $invoiceId,



                ':position' => $item['position'],



                ':description' => $item['description'],



                ':quantity' => $item['quantity'],



                ':unit_price' => $item['unit_price'],



                ':line_total' => $item['line_total'],



                ':tenant_id' => $invoiceData['tenant_id'] ?? null,



            ]);



        }







        $pdo->commit();



        return $invoiceId;



    } catch (Throwable $exception) {



        $pdo->rollBack();



        throw $exception;



    }



}







function update_invoice_status(PDO $pdo, int $invoiceId, string $status, ?int $tenantId = null): void



{



    $allowed = ['draft', 'open', 'paid', 'cancelled'];



    if (!in_array($status, $allowed, true)) {



        throw new InvalidArgumentException('Ungueltiger Rechnungsstatus.');



    }







    $sql = 'UPDATE invoices SET status = :status WHERE id = :id';



    $params = [



        ':status' => $status,



        ':id' => $invoiceId,



    ];



    if ($tenantId !== null) {



        $sql .= ' AND tenant_id = :tenant_id';



        $params[':tenant_id'] = $tenantId;



    }







    $stmt = $pdo->prepare($sql);



    $stmt->execute($params);



}







function record_payment(PDO $pdo, array $payload): int



{



    $stmt = $pdo->prepare(



        'INSERT INTO payments (invoice_id, amount, payment_date, method, reference, notes, tenant_id)



         VALUES (:invoice_id, :amount, :payment_date, :method, :reference, :notes, :tenant_id)'



    );







    $stmt->execute([



        ':invoice_id' => $payload['invoice_id'],



        ':amount' => $payload['amount'],



        ':payment_date' => $payload['payment_date'],



        ':method' => $payload['method'] ?? null,



        ':reference' => $payload['reference'] ?? null,



        ':notes' => $payload['notes'] ?? null,



        ':tenant_id' => $payload['tenant_id'] ?? null,



    ]);







    return (int)$pdo->lastInsertId();



}







function list_recurring_invoices(PDO $pdo, ?int $tenantId = null): array



{



    $sql = 'SELECT recurring_invoices.id,



                recurring_invoices.service_overview,



                recurring_invoices.frequency,



                recurring_invoices.next_run_at,



                recurring_invoices.last_run_at,



                recurring_invoices.total,



                recurring_invoices.active,



                customers.company



         FROM recurring_invoices



         JOIN customers ON customers.id = recurring_invoices.customer_id';



    [$condition, $params] = tenant_condition($tenantId, 'recurring_invoices.tenant_id');



    if ($condition !== '') {



        $sql .= ' WHERE ' . $condition;



    }



    $sql .= ' ORDER BY recurring_invoices.next_run_at ASC';







    $stmt = $pdo->prepare($sql);



    $stmt->execute($condition !== '' ? $params : []);







    return $stmt->fetchAll();



}







function create_recurring_invoice(PDO $pdo, array $payload, array $items): int



{



    if (empty($items)) {



        throw new InvalidArgumentException('Bitte erfassen Sie mindestens eine Position.');



    }







    $subtotal = 0.0;



    foreach ($items as $item) {



        $subtotal += (float)$item['line_total'];



    }







    $taxRate = (float)$payload['tax_rate'];



    $taxTotal = round($subtotal * ($taxRate / 100), 2);



    $total = round($subtotal + $taxTotal, 2);







    $stmt = $pdo->prepare(



        'INSERT INTO recurring_invoices (



            customer_id,



            service_overview,



            start_date,



            frequency,



            occurrences,



            subtotal,



            tax_rate,



            tax_total,



            total,



            notes,



            next_run_at,



            template_payload,



            tenant_id



        ) VALUES (



            :customer_id,



            :service_overview,



            :start_date,



            :frequency,



            :occurrences,



            :subtotal,



            :tax_rate,



            :tax_total,



            :total,



            :notes,



            :next_run_at,



            :template_payload,



            :tenant_id



        )'



    );







    $stmt->execute([



        ':customer_id' => $payload['customer_id'],



        ':service_overview' => $payload['service_overview'],



        ':start_date' => $payload['start_date'],



        ':frequency' => $payload['frequency'],



        ':occurrences' => $payload['occurrences'] ?: null,



        ':subtotal' => $subtotal,



        ':tax_rate' => $taxRate,



        ':tax_total' => $taxTotal,



        ':total' => $total,



        ':notes' => $payload['notes'] ?? null,



        ':next_run_at' => $payload['next_run_at'],



        ':template_payload' => json_encode(['items' => $items], JSON_THROW_ON_ERROR),



        ':tenant_id' => $payload['tenant_id'] ?? null,



    ]);







    return (int)$pdo->lastInsertId();



}







function run_recurring_invoices(PDO $pdo, ?int $tenantId = null): array



{



    $now = date('Y-m-d');



    $sql = 'SELECT * FROM recurring_invoices



         WHERE active = 1 AND date(next_run_at) <= :today';



    $params = [':today' => $now];



    if ($tenantId !== null) {



        $sql .= ' AND tenant_id = :tenant_id';



        $params[':tenant_id'] = $tenantId;



    }



    $stmt = $pdo->prepare($sql);



    $stmt->execute($params);



    $schedules = $stmt->fetchAll();







    $created = [];







    foreach ($schedules as $schedule) {



        $payload = json_decode($schedule['template_payload'], true, 512, JSON_THROW_ON_ERROR);



        $items = $payload['items'] ?? [];







        $invoiceData = [



            'customer_id' => $schedule['customer_id'],



            'issue_date' => $now,



            'due_date' => date('Y-m-d', strtotime('+14 days')),



            'tax_rate' => $schedule['tax_rate'],



            'status' => 'open',



            'currency' => 'EUR',



            'notes' => $schedule['notes'],



            'tenant_id' => $schedule['tenant_id'] ?? null,



        ];







        $invoiceId = create_invoice($pdo, $invoiceData, $items);



        $created[] = $invoiceId;







        $nextRun = calculate_next_run_date($schedule['next_run_at'], $schedule['frequency']);



        $occurrences = $schedule['occurrences'] !== null ? (int)$schedule['occurrences'] - 1 : null;



        $active = $occurrences === null ? 1 : ($occurrences > 0 ? 1 : 0);







        $updateStmt = $pdo->prepare(



            'UPDATE recurring_invoices



             SET last_run_at = :last_run_at,



                 next_run_at = :next_run_at,



                 occurrences = :occurrences,



                 active = :active



             WHERE id = :id'



        );



        $updateParams = [



            ':last_run_at' => $now,



            ':next_run_at' => $nextRun,



            ':occurrences' => $occurrences,



            ':active' => $active,



            ':id' => $schedule['id'],



        ];



        if ($tenantId !== null) {



            $updateStmt = $pdo->prepare(



                'UPDATE recurring_invoices



                 SET last_run_at = :last_run_at,



                     next_run_at = :next_run_at,



                     occurrences = :occurrences,



                     active = :active



                 WHERE id = :id AND tenant_id = :tenant_id'



            );



            $updateParams[':tenant_id'] = $tenantId;



        }



        $updateStmt->execute($updateParams);



    }







    return $created;



}







function calculate_next_run_date(string $current, string $frequency): string



{



    $date = new DateTime($current);







    switch ($frequency) {



        case 'weekly':



            $date->modify('+1 week');



            break;



        case 'biweekly':



            $date->modify('+2 weeks');



            break;



        case 'monthly':



            $date->modify('+1 month');



            break;



        case 'quarterly':



            $date->modify('+3 months');



            break;



        case 'yearly':



            $date->modify('+1 year');



            break;



        default:



            $date->modify('+1 month');



    }







    return $date->format('Y-m-d');



}







function ensure_table_has_column(PDO $pdo, string $table, string $column, string $definition): void



{



    $stmt = $pdo->query("PRAGMA table_info($table)");



    foreach ($stmt as $info) {



        if (strcasecmp((string)$info['name'], $column) === 0) {



            return;



        }



    }







    $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");



}







function ensure_default_modules(PDO $pdo): void



{



    $modules = [



        ['Buchhaltung', 'Rechnungen, Zahlungen und Ausgaben verwalten'],



        ['CRM', 'Kundenkontakte und Leads pflegen'],



        ['Projekte', 'Projekt- und Zeitmanagement'],



        ['Support', 'Tickets und Kundenanfragen'],



        ['Lager', 'Bestaende und Inventar ueberwachen'],



    ];







    $stmt = $pdo->prepare('INSERT OR IGNORE INTO modules (name, description) VALUES (:name, :description)');



    foreach ($modules as [$name, $description]) {



        $stmt->execute([



            ':name' => $name,



            ':description' => $description,



        ]);



    }



}







function ensure_default_superadmin(PDO $pdo): void



{



    $stmt = $pdo->prepare('SELECT id FROM users WHERE role = "superadmin" LIMIT 1');



    $stmt->execute();



    $existing = $stmt->fetch();







    if ($existing) {



        return;



    }







    $hashed = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);



    $stmt = $pdo->prepare(



        'INSERT INTO users (email, password, role, name, created_at)



         VALUES (:email, :password, "superadmin", :name, CURRENT_TIMESTAMP)'



    );



    $stmt->execute([



        ':email' => ADMIN_LOGIN,



        ':password' => $hashed,



        ':name' => 'Superadmin',



    ]);



}







function create_tenant(PDO $pdo, array $payload): int



{



    $stmt = $pdo->prepare(



        'INSERT INTO tenants (name, email, status, created_at)



         VALUES (:name, :email, :status, CURRENT_TIMESTAMP)'



    );



    $stmt->execute([



        ':name' => trim((string)$payload['name']),



        ':email' => trim((string)$payload['email']),



        ':status' => $payload['status'] ?? 'pending',



    ]);







    return (int)$pdo->lastInsertId();



}







function create_user(PDO $pdo, array $payload): int



{



    $stmt = $pdo->prepare(



        'INSERT INTO users (tenant_id, email, password, name, role, created_at)



         VALUES (:tenant_id, :email, :password, :name, :role, CURRENT_TIMESTAMP)'



    );



    $stmt->execute([



        ':tenant_id' => $payload['tenant_id'] ?? null,



        ':email' => trim((string)$payload['email']),



        ':password' => $payload['password'],



        ':name' => $payload['name'] ?? null,



        ':role' => $payload['role'] ?? 'user',



    ]);







    return (int)$pdo->lastInsertId();



}







function assign_modules_to_tenant(PDO $pdo, int $tenantId, array $modules): void



{



    $ids = fetch_module_ids($pdo, $modules);



    $stmt = $pdo->prepare('INSERT OR IGNORE INTO tenant_modules (tenant_id, module_id) VALUES (:tenant_id, :module_id)');



    foreach ($ids as $moduleId) {



        $stmt->execute([



            ':tenant_id' => $tenantId,



            ':module_id' => $moduleId,



        ]);



    }



}







function assign_modules_to_user(PDO $pdo, int $userId, array $modules): void



{



    $ids = fetch_module_ids($pdo, $modules);



    $stmt = $pdo->prepare('INSERT OR IGNORE INTO user_modules (user_id, module_id) VALUES (:user_id, :module_id)');



    foreach ($ids as $moduleId) {



        $stmt->execute([



            ':user_id' => $userId,



            ':module_id' => $moduleId,



        ]);



    }



}







function fetch_module_ids(PDO $pdo, array $moduleNames): array



{



    if (empty($moduleNames)) {



        return [];



    }







    $placeholders = implode(',', array_fill(0, count($moduleNames), '?'));



    $stmt = $pdo->prepare("SELECT id FROM modules WHERE LOWER(name) IN ($placeholders)");



    $stmt->execute(array_map('strtolower', $moduleNames));







    return $stmt->fetchAll(PDO::FETCH_COLUMN);



}







function notify_admin(PDO $pdo, ?int $tenantId, string $message): void



{



    $stmt = $pdo->prepare(



        'INSERT INTO admin_notifications (tenant_id, message, created_at)



         VALUES (:tenant_id, :message, CURRENT_TIMESTAMP)'



    );



    $stmt->execute([



        ':tenant_id' => $tenantId,



        ':message' => $message,



    ]);



}







function get_all_modules(PDO $pdo): array



{



    return $pdo->query('SELECT name, description FROM modules ORDER BY name')->fetchAll();



}







function get_tenants(PDO $pdo): array



{



    return $pdo->query(



        'SELECT tenants.*, (



            SELECT MAX(last_login_at) FROM users WHERE tenant_id = tenants.id



        ) AS last_login_at



         FROM tenants



         ORDER BY created_at DESC'



    )->fetchAll();



}







function get_users_by_tenant(PDO $pdo, int $tenantId): array



{



    $stmt = $pdo->prepare('SELECT id, email, name, role, last_login_at FROM users WHERE tenant_id = :tenant_id ORDER BY email');



    $stmt->execute([':tenant_id' => $tenantId]);



    return $stmt->fetchAll();



}







function set_tenant_status(PDO $pdo, int $tenantId, string $status): void



{



    $stmt = $pdo->prepare('UPDATE tenants SET status = :status WHERE id = :id');



    $stmt->execute([':status' => $status, ':id' => $tenantId]);



}







function get_modules_for_tenant(PDO $pdo, int $tenantId): array



{



    $stmt = $pdo->prepare(



        'SELECT modules.name



         FROM tenant_modules



         JOIN modules ON modules.id = tenant_modules.module_id



         WHERE tenant_modules.tenant_id = :tenant_id'



    );



    $stmt->execute([':tenant_id' => $tenantId]);



    return $stmt->fetchAll(PDO::FETCH_COLUMN);



}







function get_modules_for_user(PDO $pdo, int $userId): array



{



    $stmt = $pdo->prepare(



        'SELECT modules.name



         FROM user_modules



         JOIN modules ON modules.id = user_modules.module_id



         WHERE user_modules.user_id = :user_id'



    );



    $stmt->execute([':user_id' => $userId]);



    return $stmt->fetchAll(PDO::FETCH_COLUMN);



}







function get_admin_notifications(PDO $pdo, bool $onlyUnseen = false): array



{



    $sql = 'SELECT admin_notifications.*,



                   tenants.name AS tenant_name



            FROM admin_notifications



            LEFT JOIN tenants ON tenants.id = admin_notifications.tenant_id';



    if ($onlyUnseen) {



        $sql .= ' WHERE admin_notifications.seen = 0';



    }



    $sql .= ' ORDER BY admin_notifications.created_at DESC LIMIT 20';







    $stmt = $pdo->query($sql);



    return $stmt->fetchAll() ?: [];



}







function mark_admin_notifications_seen(PDO $pdo, array $notificationIds): void



{



    if (empty($notificationIds)) {



        return;



    }







    $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));



    $stmt = $pdo->prepare(



        "UPDATE admin_notifications



         SET seen = 1



         WHERE id IN ($placeholders)"



    );



    $stmt->execute(array_values($notificationIds));



}







function get_latest_license(PDO $pdo, int $tenantId): ?array



{



    $stmt = $pdo->prepare(



        'SELECT *



         FROM licenses



         WHERE tenant_id = :tenant_id



         ORDER BY created_at DESC



         LIMIT 1'



    );



    $stmt->execute([':tenant_id' => $tenantId]);



    $license = $stmt->fetch();







    return $license ?: null;



}







function save_tenant_license(PDO $pdo, int $tenantId, array $data): void



{



    $licenseKey = trim((string)($data['license_key'] ?? ''));



    $validUntil = trim((string)($data['valid_until'] ?? ''));



    $active = isset($data['active']) && (int)$data['active'] === 1 ? 1 : 0;







    $pdo->prepare('UPDATE licenses SET active = 0 WHERE tenant_id = :tenant_id')->execute([':tenant_id' => $tenantId]);







    $stmt = $pdo->prepare(



        'INSERT INTO licenses (tenant_id, license_key, valid_until, active, created_at)



         VALUES (:tenant_id, :license_key, :valid_until, :active, CURRENT_TIMESTAMP)'



    );



    $stmt->execute([



        ':tenant_id' => $tenantId,



        ':license_key' => $licenseKey !== '' ? $licenseKey : null,



        ':valid_until' => $validUntil !== '' ? $validUntil : null,



        ':active' => $active,



    ]);



}











function generate_random_password(int $length = 12): string



{



    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!?@#$%';



    $password = '';



    $maxIndex = strlen($alphabet) - 1;







    if ($length < 8) {



        $length = 8;



    }







    for ($i = 0; $i < $length; $i++) {



        $password .= $alphabet[random_int(0, $maxIndex)];



    }







    return $password;



}







function create_tenant_with_admin(PDO $pdo, array $tenantPayload, array $userPayload, array $modules): array



{



    $pdo->beginTransaction();







    try {



        $tenantData = [



            'name' => trim((string)($tenantPayload['name'] ?? '')),



            'email' => trim((string)($tenantPayload['email'] ?? '')),



            'status' => $tenantPayload['status'] ?? 'active',



        ];







        if ($tenantData['name'] === '' || $tenantData['email'] === '') {



            throw new InvalidArgumentException('Mandantenname und E-Mail sind erforderlich.');



        }







        $tenantId = create_tenant($pdo, $tenantData);







        if (!empty($modules)) {



            assign_modules_to_tenant($pdo, $tenantId, $modules);



        }







        $plainPassword = (string)($userPayload['password'] ?? '');



        $hashedPassword = $plainPassword;







        $info = password_get_info($hashedPassword);



        if (($info['algo'] ?? 0) === 0) {



            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);



        }







        $userId = create_user($pdo, [



            'tenant_id' => $tenantId,



            'email' => trim((string)($userPayload['email'] ?? '')),



            'password' => $hashedPassword,



            'name' => trim((string)($userPayload['name'] ?? '')),



            'role' => $userPayload['role'] ?? 'admin',



        ]);







        if (!empty($modules)) {



            assign_modules_to_user($pdo, $userId, $modules);



        }







        notify_admin(



            $pdo,



            $tenantId,



            sprintf('Mandant %s (%s) wurde erstellt.', $tenantData['name'], $tenantData['email'])



        );







        $pdo->commit();







        return [



            'tenant_id' => $tenantId,



            'user_id' => $userId,



        ];



    } catch (Throwable $exception) {



        $pdo->rollBack();



        throw $exception;



    }



}



