<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$key = (string)($_GET['key'] ?? '');

if ($key !== ADMIN_KEY) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Zugriff verweigert</title>
        <link rel="stylesheet" href="styles.css">
        <style>
            body {
                display: grid;
                place-items: center;
                min-height: 100vh;
                background: linear-gradient(135deg, #0f62fe, #111827);
                color: #fff;
                text-align: center;
            }
            .error {
                background: rgba(0, 0, 0, 0.4);
                padding: 2.5rem;
                border-radius: 20px;
            }
            a {
                color: #fff;
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>Zugriff verweigert</h1>
            <p>Bitte fuegen Sie den Parameter <code>?key=... </code> zur URL hinzu.</p>
            <p>Den Schluessel koennen Sie in <code>config.php</code> (Konstante <code>ADMIN_KEY</code>) festlegen.</p>
            <p><a href="index.php">Zur Startseite</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$pdo = get_database();
$messages = list_contact_messages($pdo);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kontaktanfragen</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            padding: 3rem;
            background: #f3f4f6;
            color: #111827;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        h1 {
            margin-bottom: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(15, 24, 38, 0.08);
        }
        th, td {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #0f62fe;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.8rem;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .meta {
            color: #6b7280;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <h1>Kontaktanfragen</h1>
    <?php if (!$messages): ?>
        <p>Noch keine Nachrichten vorhanden.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Datum</th>
                <th>Kontakt</th>
                <th>Nachricht</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($messages as $message): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($message['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <strong><?= htmlspecialchars($message['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <span class="meta"><?= htmlspecialchars($message['email'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <?php if (!empty($message['phone'])): ?>
                            <span class="meta"><?= htmlspecialchars($message['phone'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= nl2br(htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
