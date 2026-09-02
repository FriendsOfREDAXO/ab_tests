# A/B Tests mit REDAXO

## Folie 1: Bessere Entscheidungen statt Bauchgefühl

- Zwei Versionen einer Seite treten gegeneinander an.
- Echte Besucher zeigen durch ihr Verhalten, welche Variante besser funktioniert.
- Das AddOn vergleicht Aufrufe, Klicks und optional Exit-Intent-Signale.

**Sprecherhinweis:** Ein A/B-Test beantwortet eine konkrete Frage, zum Beispiel: Führt ein anderer Einstiegstext zu mehr Klicks auf die Anfrage?

---

## Folie 2: Das Prinzip

1. Artikel A ist die bisherige, öffentliche Seite.
2. Artikel B ist die alternative Version.
3. Besucher werden nach einer einstellbaren Quote auf A oder B verteilt.
4. Ein Besucher bleibt für die Dauer seiner Session bei derselben Variante.
5. Die Auswertung vergleicht das messbare Ziel der beiden Varianten.

**Sprecherhinweis:** A und B sind zwei eigenständige REDAXO-Artikel. Nach außen bleibt die URL von Artikel A gleich.

---

## Folie 3: Was wird gemessen?

| Kennzahl | Bedeutung |
| --- | --- |
| Views | Aufrufe je Variante |
| Click-Rate | Klicks geteilt durch Views |
| Exit-Rate | Exit-Intent-Ereignisse geteilt durch Views |
| Click-Details | Klicks auf einzelne Links, wenn deren Speicherung aktiv ist |

Die Click-Rate berechnet sich als $\frac{\text{Klicks}}{\text{Views}} \times 100$.

---

## Folie 4: Ein typisches Testszenario

**Frage:** Erhöht ein konkreter Call-to-Action die Zahl der Kontaktanfragen?

- **A:** Bestehende Seite mit dem Button "Kontakt aufnehmen".
- **B:** Gleicher Seiteninhalt, aber ein früher platzierter Button "Kostenloses Erstgespräch buchen".
- **Ziel:** Klick auf den Kontakt- oder Terminlink.
- **Regel:** Nur diesen einen Unterschied testen.

**Sprecherhinweis:** Werden Text, Bild, Aufbau und Button gleichzeitig geändert, ist ein Ergebnis nicht mehr eindeutig zu erklären.

---

## Folie 5: So startet ein Test

1. Zwei online geschaltete Artikel vorbereiten.
2. In Artikel A in der Content-Sidebar das Feld **A/B Test Variante** auf Artikel B setzen.
3. Unter **AddOns > A/B Tests > Einstellungen** Tracking und Traffic-Aufteilung prüfen.
4. Beide Varianten mit `?ab_force=a` und `?ab_force=b` im Frontend kontrollieren.
5. Test laufen lassen und unter **Statistiken** beobachten.

---

## Folie 6: Faire Verteilung, saubere Vorschau

- Die Standardaufteilung ist 50 % A zu 50 % B.
- Verfügbar sind auch 10/90, 25/75, 75/25 und 90/10.
- Die Session-Dauer bestimmt, wie lange ein Besucher dieselbe Variante sieht.
- Die Parameter `?ab_force=a` und `?ab_force=b` dienen ausschließlich Vorschau, QA und Fehlersuche.

**Sprecherhinweis:** Erzwungene Vorschauen sind kein Ersatz für reale Testdaten.

---

## Folie 7: Klicks als Ziel messen

Für einen einzelnen relevanten Link wird das Attribut `data-ab-track="click"` gesetzt:

```html
<a href="/kontakt/" data-ab-track="click">Erstgespräch buchen</a>
```

- Optional kann das AddOn alle Links automatisch zählen.
- Manuelles Tracking eignet sich für klar definierte Ziele.
- Eigene Conversion-Ereignisse können per JavaScript mit `window.abTrack()` gesendet werden.

---

## Folie 8: Auswertung richtig lesen

- Aktive Tests zeigen in der Übersicht die Daten der letzten 30 Tage.
- Historische, beendete Tests bleiben vollständig vergleichbar.
- Ein Gewinner wird erst ab **100 Views pro Variante** und bei statistischer Signifikanz mit $p < 0{,}05$ ausgewiesen.
- "Zu wenig Daten" oder "Kein signifikanter Unterschied" ist kein Gewinner.

**Sprecherhinweis:** Das AddOn schützt damit vor vorschnellen Entscheidungen aufgrund kleiner Zufallsschwankungen.

---

## Folie 9: Datenschutz und SEO

- Beide Varianten laufen unter derselben URL von Artikel A.
- IP-Adressen, User-Agents und Session-IDs werden nicht gespeichert.
- Klick-URLs werden ohne Query-Parameter und Fragmente abgelegt.
- Linktexte, Klick-URLs und freie Details lassen sich in den Einstellungen bei Bedarf deaktivieren.

**Sprecherhinweis:** Die konkrete datenschutzrechtliche Bewertung bleibt projektspezifisch; das AddOn ersetzt keine Rechtsberatung.

---

## Folie 10: Test beenden und Wissen sichern

1. Ergebnis, Laufzeit und Entscheidung festhalten.
2. Gewinner in Artikel A übernehmen oder Artikel A direkt entsprechend überarbeiten.
3. Die Verknüpfung **A/B Test Variante** in Artikel A entfernen.
4. Die Historie bleibt in den Statistiken erhalten.
5. Erst danach einen neuen Test mit einer neuen Hypothese beginnen.

---

## Folie 11: Die drei Regeln für gute Tests

1. Eine klare Hypothese formulieren.
2. Pro Test nur einen entscheidenden Aspekt verändern.
3. Genug Zeit und Daten abwarten: mindestens zwei Wochen und mindestens 100 Views je Variante.

**Abschlussfrage:** Welche Seite mit ausreichend Traffic hat ein klares, messbares Ziel und eignet sich als erster Test?