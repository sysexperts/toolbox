# LedgerCare Buchhaltungssoftware (Demo)

Diese Demo-Anwendung stellt den Login- und Adminbereich einer DSGVO-bewussten Buchhaltungssoftware bereit. Sie dient
als Ausgangspunkt für weitere Funktionen wie Mandantenverwaltung oder Finanzberichte.

## Features

- Sichere Login-Seite mit CSRF-Schutz und serverseitiger Validierung
- Demo-Zugangsdaten (`admin` / `admin`) für den administrativen Bereich
- Übersichtlich gestalteter Adminbereich als Ausgangspunkt für zukünftige Funktionen
- Transparente Datenschutzhinweise entsprechend DSGVO-Grundprinzipien

## Lokale Entwicklung

1. Python 3.11+ installieren.
2. Abhängigkeiten installieren:

   ```bash
   python -m venv .venv
   source .venv/bin/activate
   pip install -r requirements.txt
   ```

3. Anwendung starten:

   ```bash
   flask --app wsgi run --debug
   ```

4. Die Anwendung ist anschließend unter <http://127.0.0.1:5000> erreichbar.

## Datenschutz-Hinweise

- Die Anwendung speichert keine personenbezogenen Daten dauerhaft.
- Session-Cookies sind auf HTTPOnly und SameSite=Lax gesetzt.
- Für einen produktiven Einsatz sollte eine HTTPS-Absicherung erfolgen und die Datenschutzhinweise juristisch geprüft
  werden.
