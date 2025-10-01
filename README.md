# PHP Ladder Webservice

Dieses Verzeichnis enthält einen minimalen Ladder-Endpunkt, der sich auf typischem Webspace mit PHP-Unterstützung betreiben lässt. Der Service benötigt keine zusätzlichen Bibliotheken und speichert eingehende Matches als JSON-Dateien im Unterordner `data/`.

## Deployment
1. Den gesamten Inhalt dieses Ordners (`index.php` und den leeren Ordner `data/`) auf den gewünschten Webspace hochladen.
2. Sicherstellen, dass PHP 8.0 oder neuer aktiviert ist und der Webserver Schreibrechte für den Ordner `data/` besitzt. Bei Bedarf dem Ordner über das Hosting-Panel Schreibrechte gewähren.
3. Nach dem Upload ist die API unter der URL des Webspaces erreichbar, zum Beispiel `https://example.com/ladder/index.php`.

> Hinweis: Viele Hoster setzen `index.php` automatisch als Startdatei. Liegt der Ordner direkt im Document-Root, kann die Basis-URL z. B. `https://example.com/` sein. Sonst ggf. den Unterordner an die URL anhängen.

## API-Übersicht

* **POST `/matches`** – Speichert ein Match. Erwartet das JSON, das der Q3Rally-Server erzeugt (inklusive `matchId`). Bereits vorhandene IDs werden ignoriert und mit HTTP 200 quittiert.
* **GET `/matches`** – Liefert eine Liste aller gespeicherten Matches (neueste zuerst). Optional können `mode`, `limit` und `offset` als Query-Parameter gesetzt werden.
* **GET `/matches/{matchId}`** – Gibt das vollständige JSON zu einer Match-ID zurück.
* **DELETE `/matches/{matchId}`** – Löscht ein Match dauerhaft.
* **GET `/servers`** – Fragt die Masterserver `master.ioquake3.org` und `dpmaster.deathmask.net` nach Q3Rally-Servern ab und liefert Name, Adresse, Spielerlisten, Map, Modus sowie Ping für erreichbare Server.

### Beispiel-Aufrufe
```bash
# Match speichern
curl -X POST https://example.com/ladder/index.php/matches \
     -H "Content-Type: application/json" \
     -d @match.json

# Letzte Matches anzeigen
curl https://example.com/ladder/index.php/matches?limit=10

# Einzelnes Match abrufen
curl https://example.com/ladder/index.php/matches/srv-20240405-183011-42

# Match löschen
curl -X DELETE https://example.com/ladder/index.php/matches/srv-20240405-183011-42
```

## Datenablage
Jedes Match wird als einzelne JSON-Datei unter `data/<matchId>.json` abgelegt. So lässt sich der Ordner bei Bedarf sichern oder in andere Systeme importieren. Der Service fügt automatisch einen Zeitstempel `receivedAt` hinzu, um Listen sortieren zu können.

## Web-Frontend

Die Startseite von `index.php` liefert jetzt ein modernes Dashboard, das die gespeicherten Matches aus dem `data/`-Ordner direkt im Browser aufbereitet. Zusätzlich steht unter `/servers` eine zweite Oberfläche mit einem komfortablen Serverbrowser bereit, der live Daten von `master.ioquake3.org` und `dpmaster.deathmask.net` (gefiltert auf Q3Rally) abfragt. Highlights:

* **Bestzeiten-Ranking** – Ein eigener Tab wertet Renn-Modi aus, erkennt Bestzeiten pro Spieler/Map und sortiert sie automatisch.
* **Filter nach Spielmodus** – Die verfügbaren Modi werden automatisch aus den vorhandenen JSON-Dateien ermittelt.
* **Suche nach Match-ID, Map oder Spielern** – Sofortige Filterung während der Eingabe.
* **Modus-Verteilung & Kennzahlen** – Karten zeigen Gesamtanzahl, letzte Aktualisierung sowie erkannte Spieler.
* **Detailansicht pro Match** – Ein Klick öffnet Metadaten und das vollständige JSON, damit sich Fehler schnell nachvollziehen lassen.
* **Konfigurierbares Lade-Limit** – Über die UI lässt sich bestimmen, wie viele Matches das Frontend auf einmal lädt.
* **Serverbrowser mit Filter & Auto-Refresh** – Fragt beide Masterserver (ioquake3 und dpmaster) nach Q3Rally-Servern ab, zeigt erreichbare Server inklusive Spielerlisten, aktueller Map, Modus und Ping und aktualisiert sich auf Wunsch automatisch.

**Hinweis:** Für die Masterserver-Abfrage muss die PHP-Erweiterung `sockets` aktiviert sein.

Das Frontend greift ausschließlich auf die bestehenden API-Endpunkte zu. Die JSON-Schnittstelle bleibt vollständig kompatibel.

## Backup & Wartung
* Regelmäßig den Ordner `data/` sichern.
* Bei sehr vielen Matches kann die Dateibasis unübersichtlich werden; für große Installationen empfiehlt sich langfristig dennoch eine vollwertige Datenbank.

## Fehlerbehandlung
Fehlerhafte Anfragen werden als JSON im Format `{ "error": "..." }` beantwortet. Stimmt etwas mit den Dateirechten nicht, liefert der Service HTTP-Status 500.

