# A/B Tests AddOn für REDAXO

Ein elegantes und SEO-freundliches A/B Testing System für REDAXO CMS.

## Features

- **Session-basierte Varianten-Zuordnung** mit konfigurierbarem Split
- **SEO-freundlich**: Gleiche URLs für beide Varianten
- **Automatisches Tracking** von Views (Klicks/Exit optional als Zählung)
- **Detaillierte Statistiken** und Performance-Vergleich
- **Dauerhafte Test-Historie**: Inaktive Tests bleiben vergleichbar
- **Sichere Backend-Links**: Varianten-Buttons werden bei fehlenden Artikeln automatisch deaktiviert
- **Metafeld-Integration** für einfache Verwaltung
- **Backend-Dashboard** mit Live-Statistiken

## Technische Funktionsweise

### **Frontend-Prozess:**
1. **Seitenladen** - User ruft Artikel A auf
2. **Varianten-Zuteilung** - Session-basiert mit konfigurierbarem Split und Laufzeit  
3. **Content-Austausch** - Bei Variante B: HTML-Content von Artikel B lädt dynamisch
4. **Event-Tracking** - Views automatisch erfasst (Klick/Exit optional)

### **Backend-Integration:**
1. **Artikel-Editor** - REX_LINK_WIDGET in Content Sidebar 
2. **Datenbank** - `art_ab_variant` Spalte speichert Verknüpfung A→B
3. **Statistiken** - `rex_ab_test_events` für Events und `rex_ab_tests` für dauerhafte Test-Historie

### **Session-Logik:**
```
User besucht Artikel 15:
├─ Session leer/abgelaufen? → Zufallszuteilung nach `split_ratio` → Session speichern
├─ Session vorhanden? → Gleiche Variante anzeigen  
└─ ?ab_force=b Parameter? → Override für Testing
```

### **Content-Replacement:**
```
Wenn Variante B:
├─ Artikel B Content laden
├─ Direkte Content-Ersetzung versuchen
├─ Fallback 0: <div id="ab_section"> Container austauschen
├─ Fallback 1: <main> Container austauschen  
├─ Fallback 2: <div id="content"> Container austauschen
└─ Robustes mehrstufiges Fallback-System
```

**Wichtig:** Stelle sicher, dass der Content-Container eindeutig ist.  
Empfohlen ist genau ein `<main>` **oder** genau ein `<div id="content">` im Seiten-HTML.  
Wenn mehrere Container vorhanden sind oder Inhalte doppelt gerendert werden (z. B. durch `REX_ARTICLE` im Template), kann es zu A+B-Ausgaben kommen.

**Optional (empfohlen bei komplexen Templates):** Einen eindeutigen Wrapper definieren, z. B.:
```html
<div id="ab_section">
  <!-- REX_ARTICLE / REX_ARTICLE_CONTENT -->
</div>
```
Dann kann der Austausch gezielt nur diesen Bereich betreffen, ohne Verwechslungen mit anderen `<main>`/`#content`-Elementen.

**Performance:** Nur bei A/B-Artikeln aktiv, Session-Cache verhindert wiederholte Zuordnung, minimaler Overhead durch gezielten OUTPUT_FILTER.

**Resultat:** SEO-freundliche A/B Tests ohne URL-Parameter, transparent für User und Suchmaschinen.

## Installation

1. AddOn im REDAXO Backend aktivieren
2. Installation läuft automatisch ab
3. Erstellt benötigte Tabellen und Metafeld

## Setup eines A/B Tests

### Schritt 1: Artikel erstellen
- **Artikel A** erstellen (Original-Version)
- **Artikel B** erstellen (Test-Version)

### Schritt 2: Verknüpfung
- In **Artikel A** das Metafeld **"A/B Test Variante"** auf **Artikel B** verlinken

### Schritt 3: Test läuft
- Traffic wird gemäß konfiguriertem Split aufgeteilt
- Beide Artikel haben die **gleiche URL** (von Artikel A)
- Sessions bestimmen, welche Variante gezeigt wird

## Tracking & Statistiken

### **Automatisches Tracking:**
- **Views** - Seitenaufrufe beider Varianten (automatisch)
- **Exit-Intent** - optional, nur als Zählung

### **Click-Tracking (3 Methoden):**

#### **1. Spezifische Links tracken:**
```html
<a href="/ziel/" data-ab-track="click">Hier klicken</a>
<button data-ab-track="click">Jetzt kaufen</button>
```

#### **2. Alle Links automatisch tracken:**
- In den AddOn-Einstellungen **"Alle Klicks tracken"** aktivieren
- Trackt automatisch alle `<a>` Links auf der Seite
- Speichert je nach Einstellung auch bereinigte Ziel-URLs und Link-Texte

#### **3. Custom Events via Java-Script:** 


```javascript
// Einfacher Event
window.abTrack('conversion', 'newsletter_signup');

// Event mit Details (Object wird als JSON-String übergeben)
window.abTrack('click', JSON.stringify({
    element: 'header_logo',
    url: '/startseite/',
    text: 'Logo'
}));
```

### **Was wird getrackt:**
- **Event-Typ**: view, click, exit, conversion
- **Variante**: a oder b
- **Zeitstempel**: Für zeitliche Analysen
- **Keine personenbezogenen Daten**: IP/User-Agent/Session-IDs werden nicht gespeichert
- **Optionale Detaildaten**: Klick-URLs, Link-Texte und freie Event-Details sind konfigurierbar

### **Tracking-Daten anzeigen:**
- **Backend**: AddOns > A/B Tests > Statistiken
- **Detailansicht**: Click auf Button bei jedem Test
- **Übersicht**: Alle Tests mit Performance-Vergleich

## Manuelle Tests

Für Tests und Debugging kannst du spezifische Varianten erzwingen:

```
https://deine-seite.local/artikel/?ab_force=a  # Original (A)
https://deine-seite.local/artikel/?ab_force=b  # Variante (B)
```

**Anwendungsfälle:**
- Tests während der Entwicklung
- Präsentation bestimmter Varianten
- Quality Assurance und Debugging

## Auswertung

### Backend-Dashboard
- **AddOns > A/B Tests** aufrufen
- Übersicht aller aktiven Tests
- Quick-Stats auf der Startseite

### Detaillierte Statistiken
- Test-spezifische Auswertungen
- Click-Through-Raten
- Exit-Intent-Analysen
- Zeitliche Verläufe
- Link-Buttons zu Varianten werden automatisch deaktiviert, wenn Zielartikel nicht mehr existieren

## Datenbankstruktur

### `rex_ab_test_events`
Speichert alle getrackte Events:
- `test_id` - ID des Original-Artikels
- `variant` - a oder b
- `event` - view, click, exit, conversion
- `details` - Event-Details (optional)
- `click_url` - Klick-URL ohne Query/Fragment
- `click_text` - Link-Text (optional)
- `ip_hash` - leer (nicht gespeichert)
- `user_agent` - leer (nicht gespeichert)
- `session_id` - leer (nicht gespeichert)
- `created_at` - Zeitstempel

### `rex_ab_tests`
Speichert die Test-Konfiguration als Historie:
- `test_id` - ID des Original-Artikels
- `variant_id` - ID der Variante B (oder `NULL`)
- `test_name` - Name von Artikel A zum Zeitpunkt der Speicherung
- `variant_name` - Name von Artikel B zum Zeitpunkt der Speicherung
- `started_at` - Start der Konfiguration
- `ended_at` - Ende der Konfiguration (bei inaktiven Tests gesetzt)
- `is_active` - `1` aktiv, `0` historisch/inaktiv
- `updated_at` - Letzte Aktualisierung

## Datenschutz

- **Ohne personenbezogene Daten:** Es werden **keine** IP-Adressen, User-Agents oder Session-IDs gespeichert.
- **Klick-URLs werden bereinigt:** Query-Parameter und Fragmente werden entfernt, um personenbezogene Daten zu vermeiden.
- **Details und Klickdaten sind konfigurierbar:** Linktexte, Ziel-URLs und freie Details lassen sich im Backend datensparsam deaktivieren.

> Hinweis: Dies ist keine Rechtsberatung. Bei Unsicherheiten bitte Datenschutzberatung einholen.

## Sicherheit

- **Client ist nicht vertrauenswürdig:** Tracking-Requests werden zusätzlich per Session-Token validiert, bleiben aber bewusst leichtgewichtig.
- **Server bestimmt Variante:** Die Variantenzuordnung erfolgt serverseitig (Manipulation der URL beeinflusst die Statistik nicht).
- **Event-Whitelist:** Nur bekannte Event-Typen werden akzeptiert.
- **Backend-Aktionen sind CSRF-geschützt:** Speichern, Reset und Export verlangen gültige Sicherheits-Tokens.

## Konfiguration

### Datenbereinigung
Im Backend unter **Einstellungen** → **Datenbereinigung**:

**Events älter als X Tage löschen**  
- Zeitbasierte Bereinigung alter Tracking-Daten
- Eingabe: Anzahl Tage (1-365)
- Artikel-Verknüpfungen bleiben erhalten

**Komplette Datenbank zurücksetzen**  
- Löscht ALLE A/B-Test Events
- Löscht die komplette Test-Historie
- Entfernt ALLE Artikel-Verknüpfungen (`art_ab_variant`)
- Leert Cache automatisch
- Doppelte Bestätigung erforderlich

**Export der Statistiken**  
- **CSV-Export**: Für Excel/LibreOffice
- **JSON-Export**: Für externe APIs/Tools
- Vollständige Event-Daten mit Zeitstempel

## Best Practices

### 1. Test-Dauer
- Mindestens 2 Wochen laufen lassen
- Statistisch relevante Stichprobengröße abwarten

### 2. Nur Eine Änderung
- Pro Test nur **einen Aspekt** ändern
- Klare Hypothese formulieren

### 3. Baseline etablieren
- Original-Version als Kontrollgruppe nutzen
- Signifikante Verbesserung anstreben

### 4. Tracking optimieren
- Wichtige Conversions mit `data-ab-track="click"` markieren
- Exit-Intent für Engagement-Messungen nutzen

## Technische Details

- **PHP 8.3+** kompatibel
- **MySQL 5.7+** erforderlich
- **Session-Cookies** für Varianten-Persistierung
- **JavaScript** für Exit-Intent und Click-Tracking

## Troubleshooting

### Tests werden nicht angezeigt?
1. Metafeld "A/B Test Variante" gesetzt?
2. Ziel-Artikel existiert und ist online?
3. Browser-Cache leeren
4. Nach Update auf diese Version einmal `update.php` ausführen (im Backend AddOn aktualisieren)

### Tracking funktioniert nicht?
1. JavaScript aktiviert?
2. Cookies erlaubt? (Session für Varianten-Zuordnung)
3. `data-ab-track` Attribute gesetzt?

### Statistiken bleiben leer?
1. Tests aktiv und live?
2. Ausreichend Traffic vorhanden?
3. Events in Datenbank-Tabelle vorhanden?

## Entwickelt für

- **REDAXO 5.15+**
- **Moderne Browser**
- **Performance-orientierte Websites**
- **SEO-bewusste Projekte**

---

*Entwickelt für bessere Conversion-Optimierung in REDAXO*
