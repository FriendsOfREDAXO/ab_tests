<?php

namespace FriendsOfRedaxo\ABTests;

use DateTime;
use Exception;
use rex;
use rex_addon;
use rex_article;
use rex_clang;
use rex_logger;
use rex_login;
use rex_config;
use rex_request;
use rex_sql;
use rex_sql_exception;
use rex_url;

/**
 * A/B Test Helper für REDAXO
 */
class ABTestHelper
{
    /**
     * Datenbank-Tabelle
     * @api Public API für externe AddOns
     */
    /**
     * Tabellenname mit Präfix
     */
    private static function tableEvents(): string
    {
        return rex::getTable('ab_test_events');
    }

    /**
     * Tabellenname der A/B Test Historie
     */
    private static function tableHistory(): string
    {
        return rex::getTable('ab_tests');
    }

    /**
     * Erlaubte Event-Typen
     */
    private const ALLOWED_EVENTS = ['view', 'click', 'conversion', 'exit'];

    /**
     * Maximale Länge für Details-String
     */
    private const MAX_DETAILS_LENGTH = 2000;

    /**
     * Minimale und maximale Session-Dauer für stabile Variantenzuordnung.
     */
    private const MIN_SESSION_DURATION = 300;
    private const MAX_SESSION_DURATION = 2592000;

    /**
     * A/B Test Variante für Artikel ermitteln
     * 
     * @param int $testId Artikel-ID
     * @return string 'a' oder 'b'
     */
    public static function getVariant($testId): string
    {
        // URL-Parameter Override für Testing (z.B. ?ab_force=a oder ?ab_force=b)
        $forceVariant = rex_request('ab_force', 'string');
        
        // Debug-Ausgabe für Force-Parameter
        if (rex::isDebugMode() && $forceVariant !== '') {
            error_log("A/B Test Debug: ab_force={$forceVariant} für Test-ID {$testId}");
        }
        
        if (rex::isDebugMode() && $forceVariant !== '' && in_array($forceVariant, ['a', 'b'], true)) {
            // Force-Parameter nur im Debug-Modus erlauben
            error_log("A/B Test Debug: Verwende ab_force={$forceVariant} für Test-ID {$testId}");
            return $forceVariant;
        }
        
        rex_login::startSession();

        $config = rex_config::get('ab_tests', 'settings', []);
        $splitRatio = (int) ($config['split_ratio'] ?? 50);
        $splitRatio = max(1, min(99, $splitRatio));
        $sessionDuration = (int) ($config['session_duration'] ?? 3600);
        $sessionDuration = max(self::MIN_SESSION_DURATION, min(self::MAX_SESSION_DURATION, $sessionDuration));

        // Session sicher initialisieren - REDAXO Session API
        $sessionKey = 'ab_variant_' . $testId;
        $storedVariant = rex_session($sessionKey, 'string', '');
        $assignedAt = rex_session($sessionKey . '_time', 'int', 0);
        $hasExpired = $assignedAt > 0 && (time() - $assignedAt) > $sessionDuration;

        if (!in_array($storedVariant, ['a', 'b'], true) || $hasExpired) {
            // Beim ersten Besuch - Debug-Modus: Immer zufällig, Produktions-Modus: Session-basiert
            $random = mt_rand(1, 100);
            $variant = ($random <= $splitRatio) ? 'a' : 'b';
            
            if (rex::isDebugMode()) {
                // Debug-Modus: Nicht in Session speichern
                error_log("A/B Test Debug: Zufällige Variante {$variant} (Random: {$random}) für Test-ID {$testId}");
                return $variant;
            } else {
                // Produktions-Modus: Session-basierte Zuordnung für konsistente A/B Tests
                rex_set_session($sessionKey, $variant);
                rex_set_session($sessionKey . '_random', $random);
                rex_set_session($sessionKey . '_time', time());
            }
        }
        
        return rex_session($sessionKey, 'string', 'a');
    }
    
    /**
     * A/B Test Event tracken
     * 
     * @param int $testId Artikel-ID
     * @param string $variant 'a' oder 'b'
     * @param string $event Event-Typ (view, click, conversion, exit)
     * @param string $details Zusätzliche Details
     */
    public static function trackEvent($testId, $variant, $event = 'view', $details = ''): void
    {
        try {
            $config = rex_config::get('ab_tests', 'settings', []);
            $storeIpHash = ($config['store_ip_hash'] ?? false) === true;
            $storeUserAgent = ($config['store_user_agent'] ?? false) === true;
            $storeSessionId = ($config['store_session_id'] ?? false) === true;
            $storeClickData = ($config['store_click_data'] ?? true) === true;
            $storeDetails = ($config['store_details'] ?? true) === true;

            $event = self::normalizeEvent($event);
            if ($event === '') {
                return;
            }

            $details = $storeDetails ? self::sanitizeDetails($details) : '';

            $sql = rex_sql::factory();
            $sql->setTable(self::tableEvents());
            $sql->setValue('test_id', $testId);
            $sql->setValue('variant', $variant);
            $sql->setValue('event', $event);
            $sql->setValue('details', $details);
            if ($event === 'click') {
                $clickUrl = '';
                $clickText = '';
                if ($storeClickData && is_string($details) && $details !== '') {
                    $decoded = json_decode($details, true);
                    if (is_array($decoded)) {
                        $clickUrl = isset($decoded['url']) ? self::sanitizeClickUrl((string) $decoded['url']) : '';
                        $clickText = isset($decoded['text']) ? self::sanitizeClickText((string) $decoded['text']) : '';
                    } else {
                        $clickUrl = self::sanitizeClickUrl($details);
                    }
                }
                $sql->setValue('click_url', $clickUrl);
                $sql->setValue('click_text', $clickText);
            }
            $sql->setValue('ip_hash', $storeIpHash ? hash('sha256', rex_server('REMOTE_ADDR', 'string', '')) : '');
            $sql->setValue('user_agent', $storeUserAgent ? substr(rex_server('HTTP_USER_AGENT', 'string', ''), 0, 255) : '');
            $sessionId = session_id();
            $sql->setValue('session_id', $storeSessionId && $sessionId !== '' ? hash('sha256', $sessionId) : '');
            $sql->setValue('created_at', date('Y-m-d H:i:s'));
            $sql->insert();
        } catch (Exception $e) {
            rex_logger::logException($e);
        }
    }

    /**
     * A/B Test Konfiguration in Historie synchronisieren.
     *
     * @param int $testId Artikel-ID Variante A
     * @param int $variantId Artikel-ID Variante B oder 0 (deaktiviert)
     * @param int $clangId Sprach-ID
     */
    public static function syncTestConfig(int $testId, int $variantId, int $clangId = 1): void
    {
        try {
            $now = date('Y-m-d H:i:s');

            $articleA = rex_article::get($testId, $clangId);
            $articleB = $variantId > 0 ? rex_article::get($variantId, $clangId) : null;
            $testName = $articleA !== null ? (string) $articleA->getName() : 'Artikel #' . $testId;
            $variantName = $articleB !== null ? (string) $articleB->getName() : null;

            $sql = rex_sql::factory();
            $activeRows = $sql->getArray('
                SELECT id, variant_id
                FROM ' . self::tableHistory() . '
                WHERE test_id = ?
                AND is_active = 1
                AND ended_at IS NULL
                ORDER BY id DESC
            ', [$testId]);

            if ($variantId > 0) {
                $foundMatchingActiveRow = false;
                foreach ($activeRows as $row) {
                    $historyId = (int) $row['id'];
                    $historyVariantId = (int) $row['variant_id'];
                    if ($historyVariantId === $variantId) {
                        $updateSql = rex_sql::factory();
                        $updateSql->setQuery('
                            UPDATE ' . self::tableHistory() . '
                            SET test_name = ?, variant_name = ?, clang_id = ?, updated_at = ?
                            WHERE id = ?
                        ', [$testName, $variantName, $clangId, $now, $historyId]);
                        $foundMatchingActiveRow = true;
                    } else {
                        $closeSql = rex_sql::factory();
                        $closeSql->setQuery('
                            UPDATE ' . self::tableHistory() . '
                            SET is_active = 0, ended_at = ?, updated_at = ?
                            WHERE id = ?
                        ', [$now, $now, $historyId]);
                    }
                }

                if (!$foundMatchingActiveRow) {
                    $insertSql = rex_sql::factory();
                    $insertSql->setQuery('
                        INSERT INTO ' . self::tableHistory() . ' (test_id, variant_id, test_name, variant_name, clang_id, started_at, ended_at, is_active, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NULL, 1, ?)
                    ', [$testId, $variantId, $testName, $variantName, $clangId, $now, $now]);
                }
            } else {
                foreach ($activeRows as $row) {
                    $closeSql = rex_sql::factory();
                    $closeSql->setQuery('
                        UPDATE ' . self::tableHistory() . '
                        SET is_active = 0, ended_at = ?, updated_at = ?, test_name = ?
                        WHERE id = ?
                    ', [$now, $now, $testName, (int) $row['id']]);
                }
            }
        } catch (Exception $e) {
            rex_logger::logException($e);
        }
    }

    /**
     * Alle aktuell aktiven Historieneinträge schließen.
     */
    public static function closeAllActiveTests(): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            $sql = rex_sql::factory();
            $sql->setQuery('
                UPDATE ' . self::tableHistory() . '
                SET is_active = 0, ended_at = ?, updated_at = ?
                WHERE is_active = 1
                AND ended_at IS NULL
            ', [$now, $now]);
        } catch (Exception $e) {
            rex_logger::logException($e);
        }
    }

    /**
     * Event-Typ normalisieren und prüfen
     */
    public static function normalizeEvent($event): string
    {
        $event = strtolower(trim((string) $event));
        if ($event === '') {
            return '';
        }

        return in_array($event, self::ALLOWED_EVENTS, true) ? $event : '';
    }

    /**
     * Details auf sinnvolle Länge begrenzen
     */
    private static function sanitizeDetails($details): string
    {
        if (!is_string($details)) {
            return '';
        }

        $details = trim($details);
        if ($details === '') {
            return '';
        }

        return substr($details, 0, self::MAX_DETAILS_LENGTH);
    }

    /**
     * Klick-URL datensparsam speichern: Query/Fragment entfernen
     */
    private static function sanitizeClickUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $path = $parts['path'] ?? '';
        if ($path === '') {
            $path = '/';
        }

        $host = $parts['host'] ?? '';
        if ($host !== '') {
            $scheme = $parts['scheme'] ?? 'https';
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            $url = $scheme . '://' . $host . $port . $path;
        } else {
            $url = $path;
        }

        return substr($url, 0, 500);
    }

    /**
     * Klicktext kurz und export-sicher halten.
     */
    private static function sanitizeClickText(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }

        return substr($text, 0, 255);
    }

    /**
     * Öffentliche Tracking-Requests mit Session-gebundenem Token härten.
     */
    public static function buildTrackingToken(int $testId, string $variant): string
    {
        $sessionId = session_id();
        if ($sessionId === '' || !in_array($variant, ['a', 'b'], true)) {
            return '';
        }

        return hash_hmac('sha256', $testId . '|' . $variant . '|' . $sessionId, self::getTrackingSecret());
    }

    /**
     * Tracking-Token validieren.
     */
    public static function isValidTrackingToken(int $testId, string $variant, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $expectedToken = self::buildTrackingToken($testId, $variant);
        return $expectedToken !== '' && hash_equals($expectedToken, $token);
    }

    /**
     * Nur aktive und korrekt konfigurierte Tests tracken.
     */
    public static function canTrackTest(int $testId): bool
    {
        if ($testId <= 0) {
            return false;
        }

        try {
            $sql = rex_sql::factory();
            $rows = $sql->getArray('
                SELECT 1
                FROM ' . rex::getTable('article') . '
                WHERE id = ?
                AND art_ab_variant IS NOT NULL
                AND art_ab_variant > 0
                LIMIT 1
            ', [$testId]);

            return $rows !== [];
        } catch (Exception $e) {
            rex_logger::logException($e);
            return false;
        }
    }

    /**
     * Tracking-Secret einmalig erzeugen und danach stabil wiederverwenden.
     */
    private static function getTrackingSecret(): string
    {
        $secret = (string) rex_config::get('ab_tests', 'tracking_secret', '');
        if (strlen($secret) >= 32) {
            return $secret;
        }

        $secret = bin2hex(random_bytes(32));
        rex_config::set('ab_tests', 'tracking_secret', $secret);

        return $secret;
    }

    /**
     * Exportwerte gegen Spreadsheet-Formeln härten.
     *
     * @param mixed $value
     */
    public static function sanitizeExportValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (!is_scalar($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = (string) $value;
        if ($value !== '' && preg_match('/^[=\-+@]/', ltrim($value)) === 1) {
            return "'" . $value;
        }

        return $value;
    }
    
    /**
     * A/B Test Statistiken abrufen
     * @api Public API für externe AddOns und Templates
     * 
     * @param int $testId Artikel-ID
     * @return array Statistiken
     */
    public static function getStats($testId): array
    {
        try {
            $sql = rex_sql::factory();
            $sql->setQuery('
                SELECT 
                    variant,
                    event,
                    COUNT(*) as count,
                    COUNT(DISTINCT NULLIF(session_id, "")) as unique_count
                FROM ' . self::tableEvents() . ' 
                WHERE test_id = ?
                GROUP BY variant, event
                ORDER BY variant, event
            ', [$testId]);
            
            $stats = [];
            while ($sql->hasNext()) {
                $variant = $sql->getValue('variant');
                $event = $sql->getValue('event');
                $count = $sql->getValue('count');
                $unique_count = $sql->getValue('unique_count');
                
                $stats[$variant][$event] = [
                    'total' => $count,
                    'unique' => $unique_count
                ];
                $sql->next();
            }
            
            return $stats;
        } catch (Exception $e) {
            rex_logger::logException($e);
            return [];
        }
    }
    
    /**
     * A/B Test für Artikel aktiviert?
     * @api Public API für Templates und externe AddOns
     * 
     * @param int $articleId Artikel-ID
     * @return bool
     */
    public static function isActive($articleId): bool
    {
        $article = rex_article::get($articleId);
        if ($article === null) {
            return false;
        }

        $variantId = (int) $article->getValue('art_ab_variant');
        return $variantId > 0;
    }
    
    /**
     * JavaScript für Analytics Tracking generieren
     * 
     * @param int $testId Artikel-ID
     * @param string $variant Variant
     * @return string JavaScript-Code
     */
    public static function getTrackingScript($testId, $variant): string
    {
        $config = rex_config::get('ab_tests', 'settings', []);
        $trackingEnabled = ($config['tracking_enabled'] ?? true) === true;
        $autoTrackClicks = ($config['auto_track_clicks'] ?? true) === true;
        $autoTrackExits = ($config['auto_track_exits'] ?? true) === true;

        if (!$trackingEnabled) {
            return '';
        }

        rex_login::startSession();

        $token = self::buildTrackingToken((int) $testId, (string) $variant);
        if ($token === '') {
            return '';
        }

        // Frontend-URL für Tracking-Endpoint generieren
        $url = rex_url::frontend() . '?ab_track=1&test_id=' . urlencode((string) $testId) . '&variant=' . urlencode((string) $variant) . '&token=' . urlencode($token);
        $payload = [
            'testId' => (int) $testId,
            'variant' => (string) $variant,
            'trackUrl' => $url,
            'autoTrackClicks' => $autoTrackClicks,
            'autoTrackExits' => $autoTrackExits,
        ];

        return '
        <script>
        window.abTestData = ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';
        
        // Event Tracking Funktionen
        window.abTrack = function(event, details) {
            var payload = "event=" + encodeURIComponent(event) + "&details=" + encodeURIComponent(details || "");
            if (navigator.sendBeacon) {
                var data = new Blob([payload], { type: "application/x-www-form-urlencoded" });
                navigator.sendBeacon(window.abTestData.trackUrl, data);
                return;
            }
            // Fallback: 1x1 Pixel Request (robust bei Navigation)
            var img = new Image();
            img.src = window.abTestData.trackUrl + "&" + payload + "&_ts=" + Date.now();
        };
        
        // Google Analytics Event (falls verfügbar)
        if (typeof gtag !== "undefined") {
            gtag("event", "ab_test_view", {
                "ab_variant": "' . $variant . '",
                "ab_test_id": "' . $testId . '"
            });
        }
        
        // Matomo Event (falls verfügbar) 
        if (typeof _paq !== "undefined") {
            _paq.push(["trackEvent", "A/B Test", "View", "Test ' . $testId . ' - Variant ' . $variant . '"]);
        }
        
        // Exit Intent Tracking
        if (window.abTestData.autoTrackExits) {
            document.addEventListener("mouseleave", function(e) {
                if (e.clientY <= 0) {
                    window.abTrack("exit");
                }
            });
        }
        
        // Link Click Tracking
        document.addEventListener("click", function(e) {
            var target = e.target;
            if (!target || typeof target.closest !== "function") {
                return;
            }

            var trackEl = target.closest("[data-ab-track]");
            var linkEl = target.closest("a");
            var shouldTrack = false;

            if (window.abTestData.autoTrackClicks) {
                shouldTrack = !!linkEl && !!linkEl.href;
            } else if (trackEl && trackEl.getAttribute("data-ab-track") === "click") {
                shouldTrack = true;
            }

            if (!shouldTrack) {
                return;
            }

            var el = linkEl || trackEl || target;
            var href = linkEl && linkEl.href ? linkEl.href : (trackEl ? (trackEl.getAttribute("href") || "") : "");
            var text = "";
            if (el && el.textContent) {
                text = el.textContent.replace(/\\s+/g, " ").trim().substring(0, 255);
            }

            window.abTrack("click", JSON.stringify({
                element: el ? el.tagName : "",
                url: href,
                text: text
            }));
        });
        </script>';
    }
    
    /**
     * Alle aktiven A/B Tests abrufen
     * @api Public API für Dashboard und externe AddOns
     * 
     * @return array Tests
     */
    public static function getActiveTests(): array
    {
        try {
            $clangId = rex_clang::getCurrentId();
            $sql = rex_sql::factory();
            $sql->setQuery('
                SELECT 
                    a.id,
                    a.name,
                    a.art_ab_variant,
                    COUNT(e.id) as total_events
                FROM ' . rex::getTable('article') . ' a
                LEFT JOIN ' . self::tableEvents() . ' e ON a.id = e.test_id
                WHERE a.clang_id = ?
                AND a.art_ab_variant IS NOT NULL
                AND a.art_ab_variant != ""
                GROUP BY a.id, a.name, a.art_ab_variant
                ORDER BY a.name
            ', [$clangId]);
            
            $tests = [];
            while ($sql->hasNext()) {
                $tests[] = [
                    'id' => $sql->getValue('id'),
                    'name' => $sql->getValue('name'),
                    'variant_id' => $sql->getValue('art_ab_variant'),
                    'total_events' => $sql->getValue('total_events')
                ];
                $sql->next();
            }
            
            return $tests;
        } catch (Exception $e) {
            rex_logger::logException($e);
            return [];
        }
    }
}
