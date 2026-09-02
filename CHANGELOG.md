# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/).

## [1.6.2] - 2026-09-02

### Hinzugefügt
- **Nutzeranleitung**: Schritt-für-Schritt-Anleitung für Vorbereitung, Anlage, Vorschau, Auswertung und Abschluss eines A/B-Tests ergänzt.
- **Präsentation**: Präsentationsvorlage mit Sprecherhinweisen zur Funktionsweise und zum redaktionellen Einsatz ergänzt.
- **Backend-Info**: Nutzeranleitung und Präsentation sind im AddOn unter `Info` direkt erreichbar.

## [1.6.0] - 2026-05-06

### Hinzugefuegt
- **CSRF-Schutz**: Backend-Formulare fuer Einstellungen, Datenbereinigung und Reset sind jetzt mit Sicherheits-Token abgesichert
- **Export-Schutz**: CSV-Exporte haerten Werte gegen Spreadsheet-/Formula-Injection
- **Datenschutz-Optionen**: Speicherung von Klickdaten und freien Event-Details ist jetzt im Backend konfigurierbar
- **Tracking-Token**: Frontend-Tracking nutzt sessiongebundene Token zur Validierung eingehender Requests

### Geaendert
- **Variantenlogik**: `split_ratio` und `session_duration` aus den Einstellungen werden jetzt im Laufzeitcode tatsaechlich ausgewertet
- **Frontend-Ausgabe**: Variante B wird mit aktuellem `clang` geladen, damit mehrsprachige Inhalte konsistenter ausgespielt werden
- **Tracking-Injektion**: Script- und Debug-Injektion in `head`/`body` erfolgt robuster und faellt nicht mehr nur bei exakter Kleinschreibung der Tags an
- **Reset-Verhalten**: Komplett-Reset entfernt jetzt Events, Historie und Artikel-Verknuepfungen konsistent
- **Sidebar-Handling**: Die A/B-Konfiguration im Content-Sidebar-Bereich ist auf Nutzer mit `ab_test[]` begrenzt und verhindert Selbstverlinkungen eines Artikels auf sich selbst
- **Tests/Statistik**: Backend-Links beruecksichtigen den aktuellen Sprachkontext besser und behandeln fehlende Varianten konsistenter
- **Kompatibilitaet**: Weitere SQL-Zugriffe wurden auf `rex::getTable()` umgestellt
- **Dokumentation**: README und Release-Infos an den aktuellen Sicherheits-, Datenschutz- und Tracking-Stand angepasst

### Behoben
- **Prefix-Problem**: Harte `rex_article`-Tabellennamen in Reset-, Uninstall- und Query-Pfaden ersetzt
- **Mehrsprachigkeitsfehler**: Sprachkontext bei Variantenausgabe und mehreren Backend-Abfragen korrigiert
- **Irrefuehrende Konfiguration**: Einstellwerte fuer Split und Session-Laufzeit wirkten vorher nicht, jetzt greifen sie
- **Offener Tracking-Endpoint**: Tracking wird nur noch fuer valide Tests mit passendem Variant-/Token-Kontext verarbeitet
- **Inkonsistenter Export**: Untrusted Tracking-Daten werden vor CSV-Ausgabe sicherer behandelt

## [1.5.0] - 2026-02-23

### Geaendert
- **Click-Details Tabelle**: Gruppierung nach Link mit A/B/Gesamt-Spalten
- **Summenzeile**: Gesamtwerte am Tabellenende
- **Optik**: Padding, Alignment, A/B-Farbkennzeichnung und Gewinner-Highlighting

## [1.4.9] - 2026-02-17

### Geaendert
- **Statistik-Buttons**: Vor dem Rendern wird geprueft, ob Artikel A/B noch existieren
- **Button-Verhalten**: Links zu Variante A/B und Detailstatistik werden bei fehlenden Artikeln deaktiviert statt ins Leere zu zeigen
- **Historien-Fall**: Variante-B-Existenz wird auch fuer historische Datensaetze korrekt geprueft

## [1.4.8] - 2026-02-17

### Hinzugefuegt
- **Historien-Tabelle `rex_ab_tests`**: Dauerhafte Speicherung von Test-Konfigurationen (auch ohne Event-Daten)
- **Update-Migration**: Bestehende Installationen erhalten die Historie automatisch über `update.php`

### Geaendert
- **Statistik-Übersicht**: Zeigt jetzt auch inaktive/historische Tests aus der Historie an
- **Zeitraum-Logik**: Aktive Tests bleiben auf 30 Tage, historische Tests werden über den gesamten Verlauf ausgewertet
- **Status-Anzeige**: Historische Tests werden als `Inaktiv (historisch)` markiert
- **Sidebar-Speichern**: Änderungen an `art_ab_variant` werden sofort in die Historie synchronisiert

### Behoben
- **Persistenz-Lücke**: Inaktive Tests verschwinden nicht mehr aus der Statistik, nur weil keine aktive Verknüpfung mehr besteht
- **Uninstall-Skript**: Bereinigt jetzt konsistent `rex_ab_test_events` und `rex_ab_tests`

## [1.4.7] - 2026-02-09

### Geaendert
- **Statistik-Gewinner**: Mindest-Views pro Variante und Signifikanztest (z-Test, p < 0,05)
- **Verbesserungsanzeige**: Prozentwerte korrekt relativ zur schlechteren Variante berechnet

## [1.4.6] - 2026-02-06

### Geaendert
- **Tracking-Endpoint**: Session wird im Tracking-Request sauber gestartet
- **Tracking-Events**: Details und Klick-Daten werden wieder gespeichert
- **Datenschutz**: Klick-URLs werden ohne Query/Fragment gespeichert (bereinigt)
- **DSGVO-Tracking**: Details/Klick-URLs werden wieder gespeichert (ohne IP/User-Agent/Session)
- **Tabellenzugriff**: Überall `rex::getTable()` fuer korrekten Prefix
- **Logging**: Fehler werden korrekt via `rex_logger::logException()` geloggt

## [1.4.5] - 2026-02-06

### Geaendert
- **DSGVO-Tracking**: Keine personenbezogenen Daten mehr gespeichert (IP/User-Agent/Session/Details/Klick-URLs)
- **Tracking-Sicherheit**: Variante wird serverseitig bestimmt, Event-Typen werden validiert
- **Frontend-Tracking**: `variant` entfernt, Klick/Exit-Tracking nur als Zaehlung
- **Einstellungen**: Consent-/Datenschutz-Optionen entfernt, Fokus auf minimale Datenerhebung
- **Rexstan**: Findings behoben, Baseline bereinigt
- **CI**: Rexstan GitHub Action hinzugefuegt

## [1.4.4] - 2026-02-06

### Geaendert
- **Content-Replacement**: Eindeutiger Wrapper `#ab_section` hat Prioritaet vor `<main>` und `#content`
- **Debug-Ausgabe**: Gewaehlter Container wird im Debug-Modus explizit ausgegeben

## [1.4.3] - 2026-02-06

### Geändert
- **CSS**: Light-Mode Farben auf REDAXO-Look angepasst (Dark Mode unverändert)

## [1.4.2] - 2026-02-06

### Behoben
- **Click-Tracking**: Frontend-Endpoint akzeptiert jetzt `ab_track=1` Parameter
- **Event-Name**: Klick-Events werden als `click` statt `link_click` gespeichert
- **Click-Details**: `click_url` und `click_text` werden beim Klick-Tracking korrekt befüllt
- **Einstellungen**: `tracking_enabled`, `auto_track_clicks`, `auto_track_exits` werden korrekt berücksichtigt

## [1.4.1] - 2026-02-06

### Geändert
- **Struktur**: Content Sidebar Fragment von `pages/content.ab_tests.php` nach `fragments/content_sidebar.php` verschoben (korrekte REDAXO-Konventionen)
- **Code-Organisation**: Bessere Einhaltung der REDAXO AddOn Strukturrichtlinien

## [1.4.0] - 2026-02-06

### Geändert
- **Breaking Change**: Namespace von `ABTests` auf `FriendsOfRedaxo\ABTests` umgestellt
- Alle Klassenverwendungen auf neuen Namespace aktualisiert

### Behoben
- **Settings**: Checkbox-Bug behoben - Checkboxen für "Tracking aktiviert", "Alle Klicks automatisch tracken" und "Automatisches Exit-Intent Tracking" lassen sich jetzt korrekt deaktivieren
- **UI**: Unnötiger "System Status" Panel in den Einstellungen entfernt
- **PHP Standards**: 42+ PHP Coding Standards Violations behoben:
  - Session API Migration: Vollständige Umstellung von `$_SESSION` auf `rex_session()` API
  - Server API Migration: `$_SERVER` durch `rex_server()` ersetzt
  - Strict Comparisons: Alle loose comparisons (`==`, `!=`) durch strict (`===`, `!==`) ersetzt
  - Type Safety: Explizite Typ-Checks und null coalesce operators korrigiert
  - Array Handling: SQL result arrays korrekt behandelt
  - Redundante null checks entfernt wo APIs garantiert non-null zurückgeben

### Dokumentiert
- **API**: Alle öffentlichen Methoden in ABTestHelper mit @api annotations markiert
- **Changelog**: Vollständige Dokumentation aller Änderungen seit Version 1.2.0
- **Versioning**: package.yml und CHANGELOG.md synchronisiert

## [1.3.4] - 2026-02-06

### Geändert
- **API-Dokumentation**: Öffentliche AddOn-API mit @api Annotationen markiert
- **Code Quality**: "Unused" Linter-Warnungen für öffentliche API-Methoden behoben
- **Externe Kompatibilität**: `getStats()`, `isActive()`, `getActiveTests()` als öffentliche API definiert

### Behoben
- **False Positive** Linter-Warnungen für API-Methoden eliminiert
- **Entwickler-Erfahrung** verbessert durch klare API-Definition

## [1.3.3] - 2026-02-06

### Behoben
- **PHP Coding Standards** in ABTestHelper.php komplett überarbeitet
- **Session-API Migration**: Alle direkten `$_SESSION` Zugriffe durch REDAXO Session-API ersetzt  
- **Server-API**: `$_SERVER` durch `rex_server()` ersetzt für bessere Kompatibilität
- **Strict Comparisons**: `empty()` durch explizite Null/Empty-Checks ersetzt
- **Useless Casts**: Überflüssige `(int)` Casts entfernt
- **Unused Constants**: Nicht verwendete `TABLE_TESTS` Konstante entfernt
- **Array Search**: `in_array()` mit strict mode für bessere Type Safety

### Geändert
- **Session-Handling** verbessert durch konsistente REDAXO-API Nutzung
- **Debug-Modus** optimiert: Session wird nur im Produktions-Modus gespeichert
- **Code Quality** auf 100% PSR-12 Standard angehoben

## [1.3.2] - 2026-02-06

### Behoben
- **PHP Coding Standards** in allen Backend-Dateien korrigiert
- **Mixed Boolean Conditions** in settings.php behoben
- **Loose Comparisons** durch strict comparisons (`===`) ersetzt
- **Empty() Konstrukte** durch explizite Type-Checks ersetzt  
- **Short Ternary Operators** durch null coalesce operator (`??`) ersetzt
- **Useless Type Casts** entfernt für bessere Performance

### Geändert
- **Code Quality** massiv verbessert für alle PHP-Dateien
- **Type Safety** durch strikte Vergleiche erhöht

## [1.3.1] - 2026-02-06

### Entfernt
- **System Status Panel** aus Einstellungsseite (war überflüssig)
- **Redundante Event-Statistiken** die keinen praktischen Nutzen hatten
- **Top-Klicks Anzeige** aus Settings-Bereich entfernt

### Geändert
- **Einstellungsseite** ist jetzt aufgeräumter und fokussierter
- **UI/UX** verbessert durch Entfernung unnötiger Informationen

## [1.3.0] - 2026-02-06

### Hinzugefügt
- **Datenbereinigungsfunktionen** in Einstellungsseite implementiert
- **Events älter als X Tage löschen** mit konfigurierbarem Zeitraum
- **Komplettes System-Reset** mit Bestätigungsdialog
- **Export-Funktionen** für Statistiken (CSV und JSON Format)

### Behoben  
- **Checkbox-Einstellungen Speicherbug**: Tracking-Optionen ließen sich nicht deaktivieren
- **Export-Bug**: HTML wurde mit in Export-Dateien geschrieben
- **Boolean-Verarbeitung** für alle Checkbox-Werte korrigiert

### Geändert
- **Export-Logik** an Dateianfang verschoben für saubere Downloads
- **Einstellungsformular** robuster durch korrekte Checkbox-Behandlung

## [1.2.0] - 2026-02-06

### Hinzugefügt
- **Automatisches Event-Tracking** für View-Events beim Seitenaufruf
- **Tracking-Script** wird automatisch vor `</body>` eingefügt
- **Session-sichere View-Events** (nur einmal pro Session getrackt)
- **Verbesserte URL-Generierung** für Frontend-Tracking-Endpoint

### Behoben  
- **Fehlende Datenbank-Einträge** in `rex_ab_test_events` Tabelle (Hauptproblem)
- **Session-Initialisierung** in `trackEvent()` Methode
- **Frontend-Endpoint** Parameter-Abruf mit korrektem `rex_get()`
- **Statistiken funktionieren** jetzt durch korrekte Event-Erfassung

### Geändert
- **Performance-Optimierung**: View-Events werden nur einmal pro Session getrackt
- **Robustere Session-Handling** für alle Tracking-Funktionen

## [1.1.0] - 2026-02-05

### Verbessert
- **Content-Ersetzung funktioniert jetzt korrekt** - Fallback-System für verschiedene Container
- **Erweiterte Debug-Funktionen** mit detaillierter Ausgabe der Content-Ersetzung
- **Template-Kompatibilität** für `<div id="content">` Container
- **Mehrstufiges Fallback-System**: Direkte Content-Ersetzung → Main-Container → Content-Div

### Behoben
- A/B Test Variante B zeigt jetzt korrekt den alternativen Artikel-Content
- Force-Parameter `?ab_force=b` funktioniert vollständig
- Content-Ersetzung berücksichtigt Template-Struktur

## [1.0.0] - 2026-02-05

### Hinzugefügt
- **Session-basierte A/B Tests** mit 50/50 Split
- **SEO-freundliche Implementation** (gleiche URLs für beide Varianten)
- **REX_LINK_WIDGET Integration** für einfache Backend-Bedienung
- **Automatisches Event-Tracking** (Views, Clicks, Conversions, Exit-Intent)
- **Content Sidebar** mit YForm für Artikel-Bearbeitung
- **Detaillierte Statistiken** mit Performance-Vergleich
- **Backend-Dashboard** mit Test-Übersicht
- **Anonymisiertes Tracking** mit IP-Hashing
- **URL-Parameter Override** für Testing (?ab_force=a/b)
- **Debug-Mode Support** mit erweiterten Informationen
- **Vollständige deutsche Sprachdatei**
- **Umfassende Dokumentation** (README.md)
- **Automatische Installation/Deinstallation**

### Technische Details
- Namespace: `ABTests`
- REDAXO Extension Points: `STRUCTURE_CONTENT_SIDEBAR`, `OUTPUT_FILTER`
- Datenbank-Tabellen: `rex_ab_test_events`
- Artikel-Erweiterung: `art_ab_variant` Spalte
- YForm Integration für Backend-UI
- Exception Handling mit `rex_logger`
- Performance-optimierte Content-Ersetzung
- Session-sichere Varianten-Zuordnung

### Sicherheit
- IP-Adressen werden gehasht (SHA-256)
- Session-IDs werden anonymisiert
- SQL-Injection-Schutz durch rex_sql
- XSS-Schutz durch rex_escape

### Kompatibilität
- REDAXO 5.15+
- PHP 8.0+
- Metainfo AddOn 2.8+
