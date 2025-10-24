# Business Manager (XAMPP)

Einfache Kunden- und Rechnungsverwaltung auf Basis von PHP 8 und SQLite. Alle Dateien - inklusive Datenbank - liegen im Projektordner, damit die App sofort in XAMPP lauffaehig ist.

## Struktur

- `app.js` - Dynamik fuer das Rechnungsformular  
- `config.php` - Datenbank, Auth, CRUD-Helfer  
- `customer-delete.php` - Entfernt Kunden (POST)  
- `customer-form.php` - Kunden anlegen/bearbeiten  
- `customers.php` - Kundenliste  
- `index.php` - Dashboard  
- `invoice-create.php` - Neue Rechnung erfassen  
- `invoice-view.php` - Einzelansicht, Zahlungen, PDF-Link  
- `invoice-pdf.php` - Generiert PDF aus Rechnungsdaten  
- `payment-record.php` - Erfasst Zahlungseingaenge  
- `invoice-status.php` - Rechnungsstatus setzen  
- `invoices.php` - Rechnungsuebersicht  
- `services.php` / `service-form.php` / `service-toggle.php` - Leistungskatalog  
- `projects.php` / `project-form.php` / `project-view.php` - Projekte & Zeiten  
- `time-entry-create.php` - Zeiten buchen  
- `expenses.php` / `expense-form.php` - Ausgabenverwaltung  
- `inventory.php` / `inventory-form.php` - Lager & Hardware  
- `support.php` / `support-form.php` / `ticket-status.php` - Tickets & Support  
- `recurring.php` / `recurring-form.php` / `recurring-run.php` - Abonnements  
- `login.php` und `logout.php` - Anmeldung und Abmeldung  
- `partials/sidebar.php` - Navigation  
- `styles.css` - Oberflaeche  
- `data/site.sqlite` - SQLite-Datenbank (wird automatisch erstellt)

## Installation unter Windows (XAMPP)

1. Kopieren Sie den Ordner `xampp-site` nach `C:\xampp\htdocs\`.  
2. Starten Sie Apache im XAMPP-Control-Panel.  
3. Oeffnen Sie `http://localhost/xampp-site/login.php` im Browser.

**Standard-Login**  
E-Mail: `admin@example.com`  
Passwort: `admin123`  
Passen Sie die Werte in `config.php` an (`ADMIN_LOGIN` und `ADMIN_PASSWORD`), falls Sie andere Zugangsdaten wuenschen.

## Funktionen

- Dashboard mit Zahlungsstatus, Projektauslastung und offenen Tickets  
- Kundenverwaltung inkl. Stammdaten und Historie  
- Rechnungen mit Positionen, Steuer, Waehrung, Zahlungsverfolgung und PDF-Export  
- Abonnements fuer Managed Services (monatlich, jaehrlich usw.)  
- Leistungskatalog fuer wiederkehrende Services oder Hardware  
- Projekte inkl. Stundenbudget, Zeitbuchungen und Abrechnungsvorbereitung  
- Ausgabenliste fuer Buchhaltung (Lieferant, Steuer, Zahlungsmittel)  
- Inventarverwaltung fuer Hardware, Testgeraete oder Ersatzteile  
- Supporttickets mit Status, Prioritaet und Zuordnung zum Team  
- SQLite als Datenspeicher, alles verbleibt im Projektordner

## Tipp fuer Backups

Einfach den kompletten Ordner `xampp-site` kopieren. Die Datenbank `data/site.sqlite` enthaelt saemtliche Stamm- und Belegdaten.
