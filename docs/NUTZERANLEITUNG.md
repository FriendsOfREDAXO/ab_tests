# Nutzeranleitung: A/B Tests

Mit dem AddOn vergleichst du zwei Versionen eines REDAXO-Artikels. Besucher sehen zufällig Variante A oder B, bleiben innerhalb ihrer Session bei dieser Variante und erzeugen Auswertungsdaten für Aufrufe und Klicks.

## Voraussetzungen

- Das AddOn **A/B Tests** ist installiert und aktiviert.
- Du hast die Berechtigung `ab_test[]`.
- Beide Artikel sind in derselben Sprache angelegt und online.
- Artikel A und Artikel B sind unterschiedliche Artikel.
- Das verwendete Template enthält genau einen eindeutigen Inhaltsbereich. Bei komplexen Templates ist ein Wrapper `<div id="ab_section">...</div>` um die Artikelausgabe empfohlen.

## Vor dem Start

Formuliere die Testfrage in einem Satz. Beispiel: "Ein konkreter Buttontext erhöht die Klickrate auf die Terminseite."

Lege vorab fest:

| Festlegung | Beispiel |
| --- | --- |
| Variante A | Bestehende Seite |
| Variante B | Gleiche Seite mit neuem Buttontext |
| Messziel | Klick auf die Terminseite |
| Laufzeit | Mindestens 14 Tage |
| Entscheidung | Höhere Click-Rate bei ausreichender Datenbasis |

## Test anlegen

1. Erstelle oder prüfe **Artikel A**. Das ist die Originalversion und seine URL bleibt die öffentliche URL des Tests.
2. Erstelle **Artikel B** als alternative Version. Ändere möglichst nur den Aspekt aus deiner Hypothese.
3. Öffne im REDAXO-Backend Artikel A in der Content-Bearbeitung.
4. Klappe in der rechten Content-Sidebar den Bereich **A/B Tests** auf.
5. Wähle im Feld **A/B Test Variante** Artikel B aus.
6. Klicke auf **Speichern**.

Der Test ist danach aktiv. Der Bereich **AddOns > A/B Tests > Test Übersicht** zeigt die eingerichtete Verknüpfung.

## Einstellungen prüfen

Öffne **AddOns > A/B Tests > Einstellungen**.

| Einstellung | Wirkung | Empfehlung fuer den ersten Test |
| --- | --- | --- |
| Tracking aktiviert | Speichert neue Messdaten | Aktivieren |
| Traffic-Aufteilung für Variante A | Anteil der Besucher für A | 50 % A : 50 % B |
| Session-Dauer | Zeitraum mit fester Varianten-Zuordnung | Bestehenden Standardwert beibehalten |
| Alle Klicks automatisch tracken | Zählt jeden Link-Klick, ohne URL und Linktext | Nur bei Bedarf aktivieren |
| Automatisches Exit-Intent Tracking | Zählt, wenn der Mauszeiger das Browserfenster verlässt | Optional |
| Klick-URL und Linktext speichern | Ermöglicht Link-Details in der Auswertung | Nur aktivieren, wenn benötigt |
| Event-Details speichern | Speichert zusätzliche Details eigener Events | Für datensparsame Tests deaktivieren |

Speichere die Einstellungen nach einer Änderung.

## Ziel-Klick markieren

Wenn du nur den entscheidenden Call-to-Action messen moechtest, versiehst du dessen Link oder Button mit `data-ab-track="click"`:

```html
<a href="/termin/" data-ab-track="click">Kostenloses Erstgespraech buchen</a>
```

Bei einem REDAXO-Modul fuegst du das Attribut an dem HTML-Element ein, das den Zielklick ausloest. Wenn **Alle Klicks automatisch tracken** aktiv ist, hat diese globale Einstellung Vorrang und zaehlt alle Links.

Eigene Ereignisse, etwa nach einer erfolgreichen Anmeldung, koennen Entwickler so ausloesen:

```javascript
window.abTrack('conversion', 'newsletter_signup');
```

## Varianten vor der Veröffentlichung prüfen

Rufe die URL von Artikel A im Browser in beiden Varianten auf:

```text
https://deine-domain.tld/deine-seite/?ab_force=a
https://deine-domain.tld/deine-seite/?ab_force=b
```

Prüfe besonders:

- Wird bei A der Inhalt von Artikel A und bei B der Inhalt von Artikel B angezeigt?
- Funktionieren Navigation, Formulare, Links und Medien in beiden Varianten?
- Ist das Ziel-Element sichtbar und korrekt verlinkt?
- Zeigt die B-Variante nicht versehentlich Inhalt aus A und B gleichzeitig?

Erst nach dieser Kontrolle sollte der Test für echten Traffic laufen.

## Ergebnisse auswerten

1. Oeffne **AddOns > A/B Tests > Statistiken**.
2. Suche den Test in der Übersicht.
3. Vergleiche Views, Click-Rate und bei Bedarf Exit-Rate von A und B.
4. Öffne **Statistiken** am jeweiligen Test für Details und einzelne Link-Klicks.

Die Click-Rate ist:

$$
\text{Click-Rate} = \frac{\text{Klicks}}{\text{Views}} \times 100
$$

Das AddOn kennzeichnet eine Variante nur dann als Gewinner, wenn beide Varianten mindestens 100 Views haben und der Unterschied statistisch signifikant ist ($p < 0{,}05$). Aktive Tests zeigen in der Gesamtuebersicht die letzten 30 Tage; bei historischen Tests bleibt der gesamte Verlauf sichtbar.

## Test abschliessen

1. Dokumentiere Hypothese, Zeitraum, Views, Click-Rates und Entscheidung.
2. Uebernimm die Gewinner-Version in Artikel A oder passe Artikel A entsprechend an.
3. Oeffne Artikel A und entferne die Auswahl im Feld **A/B Test Variante**.
4. Speichere den Artikel.

Die Testverknuepfung ist damit beendet. Die Test-Historie bleibt fuer den spaeteren Vergleich in den Statistiken erhalten.

## Daten verwalten

Unter **AddOns > A/B Tests > Einstellungen > Datenbereinigung** kannst du:

- Events aelter als eine festgelegte Zahl von Tagen loeschen. Artikelverknuepfungen bleiben bestehen.
- Alle Event-Daten, die Historie und alle Artikelverknuepfungen vollstaendig zuruecksetzen. Diese Aktion ist unwiderruflich.
- Alle gesammelten Daten als CSV oder JSON exportieren.

## Fehlerbehebung

| Beobachtung | Pruefung |
| --- | --- |
| Der Test erscheint nicht | Ist in Artikel A eine andere, existierende Variante B ausgewählt und wurde gespeichert? |
| Beide Varianten zeigen denselben Inhalt | Beide Vorschau-URLs mit `ab_force` testen; prüfen, ob Artikel B eigenen Inhalt hat. |
| Inhalt von A und B erscheint zugleich | Im Template einen eindeutigen Inhaltscontainer verwenden, bevorzugt `#ab_section`. |
| Statistiken bleiben leer | Tracking aktivieren, echte Frontend-Aufrufe abwarten und JavaScript im Browser erlauben. |
| Besucher sieht wechselnde Varianten | Session-Cookies müssen erlaubt sein; Session-Dauer in den Einstellungen prüfen. |

## Datenschutz

Das AddOn speichert keine IP-Adressen, User-Agents oder Session-IDs. Klick-URLs werden ohne Query-Parameter und Fragmente abgelegt. Entscheide bewusst, ob Linktexte, Klick-URLs oder freie Event-Details fuer deinen Zweck erforderlich sind. Diese Angaben sind technische Informationen und keine Rechtsberatung.