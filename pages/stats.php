<?php

$articleId = rex_request('article_id', 'int', 0);
$clangId = rex_request('clang', 'int', rex_clang::getCurrentId());

// Stats thresholds and significance helpers (applied to overview "Beste Variante")
$minViewsPerVariant = 100;
$alpha = 0.05;

function abtests_normal_cdf(float $z): float
{
    $sqrt2 = sqrt(2.0);
    if (function_exists('erf')) {
        return 0.5 * (1.0 + erf($z / $sqrt2));
    }

    // Fallback approximation (Abramowitz & Stegun 7.1.26)
    $t = 1.0 / (1.0 + 0.2316419 * abs($z));
    $d = 0.3989423 * exp(-0.5 * $z * $z);
    $prob = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));
    return $z >= 0 ? 1.0 - $prob : $prob;
}

function abtests_two_proportion_p_value(int $aClicks, int $aViews, int $bClicks, int $bViews): float
{
    if ($aViews <= 0 || $bViews <= 0) {
        return 1.0;
    }
    $p1 = $aClicks / $aViews;
    $p2 = $bClicks / $bViews;
    $pPool = ($aClicks + $bClicks) / ($aViews + $bViews);
    $denom = sqrt(max($pPool * (1.0 - $pPool) * (1.0 / $aViews + 1.0 / $bViews), 0.0));
    if ($denom <= 0.0) {
        return 1.0;
    }
    $z = ($p2 - $p1) / $denom;
    $p = 2.0 * (1.0 - abtests_normal_cdf(abs($z)));
    return max(min($p, 1.0), 0.0);
}

function abtests_format_runtime($createdAt): array
{
    $timestamp = null;
    if (is_numeric($createdAt)) {
        $timestamp = (int) $createdAt;
    } elseif (is_string($createdAt) && $createdAt !== '') {
        $parsed = strtotime($createdAt);
        if ($parsed !== false) {
            $timestamp = $parsed;
        }
    }

    if ($timestamp === null || $timestamp <= 0) {
        return ['setupDate' => '-', 'runtime' => '-'];
    }

    $now = time();
    $days = (int) floor(max(0, $now - $timestamp) / 86400);
    if ($days < 30) {
        $runtime = $days . ' ' . ($days === 1 ? 'Tag' : 'Tage');
    } else {
        $months = (int) floor($days / 30.44);
        if ($months < 12) {
            $runtime = $months . ' ' . ($months === 1 ? 'Monat' : 'Monate');
        } else {
            $years = (int) floor($months / 12);
            $remainingMonths = $months % 12;
            $runtime = $years . ' ' . ($years === 1 ? 'Jahr' : 'Jahre');
            if ($remainingMonths > 0) {
                $runtime .= ' ' . $remainingMonths . ' ' . ($remainingMonths === 1 ? 'Monat' : 'Monate');
            }
        }
    }

    return [
        'setupDate' => date('d.m.Y', $timestamp),
        'runtime' => $runtime,
    ];
}

echo '
<style>
:root {
    --ab-panel-bg: #222B34;
    --ab-panel-heading-bg: #2D3844;
    --ab-panel-text: #ecf0f1;
    --ab-panel-muted: #95a5a6;
    --ab-card-bg: #2D3844;
    --ab-panel-border: transparent;
    --ab-code-bg: #222B34;
}
body.rex-theme-light {
    --ab-panel-bg: #ffffff;
    --ab-panel-heading-bg: #eef2f5;
    --ab-panel-text: #2b3a4a;
    --ab-panel-muted: #6b7a8a;
    --ab-card-bg: #f7f9fb;
    --ab-panel-border: #d9e0e7;
    --ab-code-bg: #f1f3f5;
}
</style>
';

if ($articleId > 0) {
    // Einzelner Test
    $article = rex_article::get($articleId, $clangId);
    if ($article !== null) {
        echo '<h3>Test: ' . rex_escape($article->getName()) . '</h3>';
        
        // Events aus Datenbank holen
        $sql = rex_sql::factory();
        $stats = $sql->getArray('
            SELECT 
                variant,
                event,
                COUNT(*) as count,
                DATE(created_at) as date
            FROM ' . rex::getTable('ab_test_events') . ' 
            WHERE test_id = ?
            GROUP BY variant, event, DATE(created_at)
            ORDER BY date DESC, variant, event
        ', [$articleId]);
        
        if ($stats === []) {
            // Auch wenn keine Events da sind, zeige die Konfiguration
            echo rex_view::info('
                <h4><i class="rex-icon fa-info-circle"></i> Test ist konfiguriert, aber noch keine Daten vorhanden</h4>
                <p><strong>Test-Setup:</strong></p>
                <ul>
                    <li><strong>Original-Artikel:</strong> ' . rex_escape($article->getName()) . ' (ID: ' . $articleId . ')</li>
                    <li><strong>Test-Variante:</strong> Konfiguriert</li>
                    <li><strong>Status:</strong> <span class="label label-warning">Wartend auf Traffic</span></li>
                </ul>
                
                <h5>Nächste Schritte:</h5>
                <ol>
                    <li>Besuchen Sie die Seite im Frontend: <a href="' . rex_getUrl($articleId, $clangId) . '" target="_blank">' . rex_getUrl($articleId, $clangId) . '</a></li>
                    <li>Testen Sie beide Varianten mit <code>?ab_force=a</code> und <code>?ab_force=b</code></li>
                    <li>Tracking wird automatisch gestartet sobald Benutzer die Seite aufrufen</li>
                </ol>
                
                <div class="alert alert-info" style="margin-top: 15px;">
                    <strong>Tipp:</strong> Verwenden Sie <code>data-ab-track="click"</code> Attribute für Link-Tracking!
                </div>
            ');
        } else {
            // Zusammenfassung
            $summary = [];
            foreach ($stats as $stat) {
                $summary[$stat['variant']][$stat['event']] = 
                    ($summary[$stat['variant']][$stat['event']] ?? 0) + $stat['count'];
            }
            
            $summaryContent = '<style>
                .ab-variant-badge{display:inline-block;padding:2px 6px;border-radius:3px;font-weight:600;line-height:1;}
                .ab-variant-a{background:#2f4a66;color:#e8f0ff;}
                .ab-variant-b{background:#5a4b2f;color:#fff1d9;}
                .ab-click-details-table th,.ab-click-details-table td{padding:8px 10px !important; line-height:1.35 !important;}
                .ab-click-details-table td{font-size:16px;}
                .ab-click-details-table td small{font-size:12px;}
                .ab-click-details-table th.ab-col-total,.ab-click-details-table td.ab-col-total{text-align:right;}
                .ab-click-details-table th.ab-col-a,.ab-click-details-table th.ab-col-b{text-align:right;}
                .ab-click-details-table td.ab-num{text-align:right;}
                .ab-click-details-table th.ab-col-a,.ab-click-details-table th.ab-col-b{color:#e5eaf0;}
                .ab-click-details-table th.ab-col-a > span,.ab-click-details-table th.ab-col-b > span{display:block;width:100%;text-align:right;}
                .ab-click-details-table .ab-num-wrap{display:inline-block;min-width:2.6em;text-align:right;padding:2px 6px;border-radius:3px;font-variant-numeric:tabular-nums;}
                .ab-click-details-table .ab-num-wrap.ab-winner{background:#2f3b46;color:#e8f0ff;}
                </style><div class="row">';
            foreach (['a', 'b'] as $variant) {
                $views = $summary[$variant]['view'] ?? 0;
                $clicks = $summary[$variant]['click'] ?? 0;
                $exits = $summary[$variant]['exit'] ?? 0;
                $conversionRate = $views > 0 ? round(($clicks / $views) * 100, 2) : 0;
                $exitRate = $views > 0 ? round(($exits / $views) * 100, 2) : 0;
                
                $summaryContent .= '
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Variante <span class="ab-variant-badge ab-variant-' . $variant . '">' . strtoupper($variant) . '</span></h3>
                        </div>
                        <div class="panel-body">
                            <table class="table table-condensed">
                                <tr><th>Views:</th><td>' . number_format($views) . '</td></tr>
                                <tr><th>Clicks:</th><td>' . number_format($clicks) . '</td></tr>
                                <tr><th>Click-Rate:</th><td>' . $conversionRate . '%</td></tr>
                                <tr><th>Exit Rate:</th><td>' . $exitRate . '%</td></tr>
                            </table>
                        </div>
                    </div>
                </div>';
            }
            $summaryContent .= '</div>';
            
            echo '<h3>Übersicht</h3>' . $summaryContent;
            
            // Click-Details anzeigen wenn vorhanden
            $clickDetails = $sql->getArray('
                SELECT click_url, click_text, COUNT(*) as clicks, variant
                FROM ' . rex::getTable('ab_test_events') . ' 
                WHERE test_id = ? AND event = "click" 
                AND (click_url IS NOT NULL OR click_text IS NOT NULL)
                GROUP BY click_url, click_text, variant 
                ORDER BY clicks DESC
                LIMIT 200
            ', [$articleId]);
            
            if ($clickDetails !== []) {
                $clickRows = [];
                foreach ($clickDetails as $detail) {
                    $linkText = (string) ($detail['click_text'] ?? '');
                    $linkUrl = (string) ($detail['click_url'] ?? '');
                    $key = $linkUrl . '|' . $linkText;

                    if (!isset($clickRows[$key])) {
                        $clickRows[$key] = [
                            'text' => $linkText,
                            'url' => $linkUrl,
                            'a' => 0,
                            'b' => 0,
                            'total' => 0,
                        ];
                    }

                    if ($detail['variant'] === 'a') {
                        $clickRows[$key]['a'] += (int) $detail['clicks'];
                    } elseif ($detail['variant'] === 'b') {
                        $clickRows[$key]['b'] += (int) $detail['clicks'];
                    }

                    $clickRows[$key]['total'] = $clickRows[$key]['a'] + $clickRows[$key]['b'];
                }

                $clickRows = array_values($clickRows);
                usort($clickRows, static function (array $left, array $right): int {
                    return $right['total'] <=> $left['total'];
                });
                $clickRows = array_slice($clickRows, 0, 20);

                $clickContent = '<div class="row"><div class="col-md-12">';
                $clickContent .= '<table class="table table-striped ab-click-details-table">';
                $clickContent .= '<thead><tr><th style="padding: 4px;">Link</th><th class="ab-col-a" style="padding: 4px;"><span class="ab-variant-badge ab-variant-a">A</span></th><th class="ab-col-b" style="padding: 4px;"><span class="ab-variant-badge ab-variant-b">B</span></th><th class="ab-col-total" style="padding: 4px;">Gesamt</th></tr></thead><tbody>';
                
                $sumA = 0;
                $sumB = 0;
                $sumTotal = 0;
                foreach ($clickRows as $detail) {
                    $linkText = $detail['text'];
                    $linkUrl = $detail['url'];
                    $linkHeadline = trim($linkText) !== '' ? $linkText : ($linkUrl !== '' ? $linkUrl : '-');
                    $sumA += (int) $detail['a'];
                    $sumB += (int) $detail['b'];
                    $sumTotal += (int) $detail['total'];
                    
                    $clickContent .= '<tr>';
                    $clickContent .= '<td style="padding: 4px;"><strong>' . rex_escape($linkHeadline) . '</strong>';
                    if (trim($linkUrl) !== '' && $linkHeadline !== $linkUrl) {
                        $clickContent .= '<br><small class="text-muted">' . rex_escape($linkUrl) . '</small>';
                    }
                    $clickContent .= '</td>';
                    $aVal = (int) $detail['a'];
                    $bVal = (int) $detail['b'];
                    $aOut = '<span class="ab-num-wrap' . ($aVal > $bVal ? ' ab-winner' : '') . '">' . number_format($aVal) . '</span>';
                    $bOut = '<span class="ab-num-wrap' . ($bVal > $aVal ? ' ab-winner' : '') . '">' . number_format($bVal) . '</span>';
                    $clickContent .= '<td class="ab-num" style="padding: 4px;">' . $aOut . '</td>';
                    $clickContent .= '<td class="ab-num" style="padding: 4px;">' . $bOut . '</td>';
                    $clickContent .= '<td class="ab-col-total ab-num" style="padding: 4px;"><span class="ab-num-wrap">' . number_format($detail['total']) . '</span></td>';
                    $clickContent .= '</tr>';
                }

                $clickContent .= '<tr>';
                $clickContent .= '<td style="padding: 4px;"><strong>Gesamt</strong></td>';
                $sumAOut = $sumA > $sumB ? '<span class="ab-num-wrap ab-winner"><strong>' . number_format($sumA) . '</strong></span>' : '<span class="ab-num-wrap"><strong>' . number_format($sumA) . '</strong></span>';
                $sumBOut = $sumB > $sumA ? '<span class="ab-num-wrap ab-winner"><strong>' . number_format($sumB) . '</strong></span>' : '<span class="ab-num-wrap"><strong>' . number_format($sumB) . '</strong></span>';
                $clickContent .= '<td class="ab-num" style="padding: 4px;">' . $sumAOut . '</td>';
                $clickContent .= '<td class="ab-num" style="padding: 4px;">' . $sumBOut . '</td>';
                $clickContent .= '<td class="ab-col-total ab-num" style="padding: 4px;"><span class="ab-num-wrap"><strong>' . number_format($sumTotal) . '</strong></span></td>';
                $clickContent .= '</tr>';
                
                $clickContent .= '</tbody></table></div></div>';
                echo '<h4 style="margin-top: 30px;"><i class="rex-icon fa-mouse-pointer"></i> Click-Details</h4>' . $clickContent;
            }
        }
    } else {
        echo rex_view::error('Artikel nicht gefunden.');
    }
} else {
    // Alle Tests Übersicht mit detaillierten Statistiken
    $sql = rex_sql::factory();
    
    // Historie-Tabelle ist ggf. noch nicht vorhanden (wenn update.php noch nicht gelaufen ist).
    $historyTableAvailable = true;
    try {
        $sql->getArray('SELECT 1 FROM ' . rex::getTable('ab_tests') . ' LIMIT 1');
    } catch (Exception $e) {
        $historyTableAvailable = false;
    }

    // Alle Tests mit ihren Statistiken holen (aktive + historische/inaktive aus Events/Historie)
    if ($historyTableAvailable) {
        $tests = $sql->getArray('
            SELECT 
                t.id,
                COALESCE(a.name, h.test_name, CONCAT("Artikel #", t.id, " (nicht mehr vorhanden)")) AS name,
                COALESCE(av.name, avh.name, h.variant_name) as variant_name,
                COALESCE(av.id, avh.id, h.variant_id) as variant_id,
                CASE WHEN a.id IS NULL THEN 0 ELSE 1 END AS article_exists,
                CASE WHEN COALESCE(av.id, avh.id) IS NULL THEN 0 ELSE 1 END AS variant_exists,
                CASE WHEN a.id IS NOT NULL AND a.art_ab_variant > 0 THEN 1 ELSE 0 END AS is_active_config
            FROM (
                SELECT DISTINCT e.test_id AS id
                FROM ' . rex::getTable('ab_test_events') . ' e
                UNION
                SELECT DISTINCT h0.test_id AS id
                FROM ' . rex::getTable('ab_tests') . ' h0
                UNION
                SELECT a2.id
                FROM ' . rex::getTable('article') . ' a2
                WHERE a2.art_ab_variant > 0
                AND a2.clang_id = ?
            ) t
            LEFT JOIN ' . rex::getTable('article') . ' a ON a.id = t.id AND a.clang_id = ?
            LEFT JOIN (
                SELECT h1.*
                FROM ' . rex::getTable('ab_tests') . ' h1
                INNER JOIN (
                    SELECT test_id, MAX(id) AS max_id
                    FROM ' . rex::getTable('ab_tests') . '
                    WHERE clang_id IN (?, 0)
                    GROUP BY test_id
                ) h2 ON h2.max_id = h1.id
            ) h ON h.test_id = t.id
            LEFT JOIN ' . rex::getTable('article') . ' av ON a.art_ab_variant = av.id AND av.clang_id = ?
            LEFT JOIN ' . rex::getTable('article') . ' avh ON h.variant_id = avh.id AND avh.clang_id = ?
            ORDER BY name
        ', [$clangId, $clangId, $clangId, $clangId, $clangId]);
    } else {
        $tests = $sql->getArray('
            SELECT 
                t.id,
                COALESCE(a.name, CONCAT("Artikel #", t.id, " (nicht mehr vorhanden)")) AS name,
                av.name as variant_name,
                av.id as variant_id,
                CASE WHEN a.id IS NULL THEN 0 ELSE 1 END AS article_exists,
                CASE WHEN av.id IS NULL THEN 0 ELSE 1 END AS variant_exists,
                CASE WHEN a.id IS NOT NULL AND a.art_ab_variant > 0 THEN 1 ELSE 0 END AS is_active_config
            FROM (
                SELECT DISTINCT e.test_id AS id
                FROM ' . rex::getTable('ab_test_events') . ' e
                UNION
                SELECT a2.id
                FROM ' . rex::getTable('article') . ' a2
                WHERE a2.art_ab_variant > 0
                AND a2.clang_id = ?
            ) t
            LEFT JOIN ' . rex::getTable('article') . ' a ON a.id = t.id AND a.clang_id = ?
            LEFT JOIN ' . rex::getTable('article') . ' av ON a.art_ab_variant = av.id AND av.clang_id = ?
            ORDER BY name
        ', [$clangId, $clangId, $clangId]);
    }
    
    if ($tests === []) {
        echo rex_view::info('
            <h4><i class="rex-icon fa-info-circle"></i> Keine aktiven oder historischen A/B Tests gefunden</h4>
            <p>Um A/B Tests zu starten, erstellen Sie zwei Artikel und verknüpfen Sie diese:</p>
            <ol>
                <li>Erstellen Sie <strong>Artikel A</strong> (Original-Version)</li>
                <li>Erstellen Sie <strong>Artikel B</strong> (Test-Version)</li>  
                <li>In Artikel A setzen Sie das Metafeld <code>A/B Test Variante</code> auf Artikel B</li>
                <li>Traffic wird gemäß konfiguriertem Split automatisch aufgeteilt</li>
            </ol>
            <p><a href="' . rex_url::backendPage('structure') . '" class="btn btn-primary">Zur Struktur-Verwaltung</a></p>
        ');
    } else {
        // Frühestes bekanntes Event je Test als Startdatum (kein separates Setup-Datum vorhanden)
        $testStartRows = $sql->getArray('
            SELECT test_id, MIN(created_at) AS started_at
            FROM ' . rex::getTable('ab_test_events') . '
            GROUP BY test_id
        ');
        $testStarts = [];
        foreach ($testStartRows as $row) {
            $testStarts[(int) $row['test_id']] = $row['started_at'];
        }

        // Statistiken für alle Tests laden
        $allStats = [];
        foreach ($tests as $test) {
            $isActiveConfig = ((int) $test['is_active_config']) === 1;
            $testStatsQuery = '
                SELECT 
                    variant,
                    event,
                    COUNT(*) as count
                FROM ' . rex::getTable('ab_test_events') . ' 
                WHERE test_id = ?
            ';

            // Aktive Tests zeigen die letzten 30 Tage, historische/inaktive den kompletten Verlauf.
            if ($isActiveConfig) {
                $testStatsQuery .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            }

            $testStatsQuery .= ' GROUP BY variant, event';
            $testStats = $sql->getArray($testStatsQuery, [$test['id']]);
            
            $summary = ['a' => ['view' => 0, 'click' => 0, 'exit' => 0], 'b' => ['view' => 0, 'click' => 0, 'exit' => 0]];
            foreach ($testStats as $stat) {
                $summary[$stat['variant']][$stat['event']] = intval($stat['count']);
            }
            $allStats[$test['id']] = $summary;
        }
        
        // Übersichts-Tabelle
        $content = '
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">A/B Tests Übersicht (aktiv: 30 Tage, historisch: gesamt)</h4>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="table table-striped" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="width: 25%; padding: 10px;">Test</th>
                            <th style="width: 15%; padding: 10px;">Laufzeit</th>
                            <th style="width: 12%; padding: 10px;">Status</th>
                            <th style="width: 28%; padding: 10px;">Performance Vergleich</th>
                            <th style="width: 20%; padding: 10px;">Beste Variante</th>
                            <th style="width: 10%; padding: 10px;"></th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($tests as $test) {
            $stats = $allStats[$test['id']];
            $runtimeInfo = abtests_format_runtime($testStarts[(int) $test['id']] ?? null);
            
            // Performance-Berechnung
            $aViews = $stats['a']['view'];
            $aClicks = $stats['a']['click'];
            $aExits = $stats['a']['exit'];
            $aClickRate = $aViews > 0 ? round(($aClicks / $aViews) * 100, 2) : 0;
            $aExitRate = $aViews > 0 ? round(($aExits / $aViews) * 100, 2) : 0;
            
            $bViews = $stats['b']['view'];
            $bClicks = $stats['b']['click'];
            $bExits = $stats['b']['exit'];
            $bClickRate = $bViews > 0 ? round(($bClicks / $bViews) * 100, 2) : 0;
            $bExitRate = $bViews > 0 ? round(($bExits / $bViews) * 100, 2) : 0;
            
            $totalViews = $aViews + $bViews;
            $totalClicks = $aClicks + $bClicks;
            
            // Gewinner ermitteln (mit Mindest-Stichprobe & Signifikanz)
            $winner = '';
            $winnerClass = '';
            $improvement = 0;
            if ($aViews >= $minViewsPerVariant && $bViews >= $minViewsPerVariant) {
                $pValue = abtests_two_proportion_p_value($aClicks, $aViews, $bClicks, $bViews);
                if ($pValue < $alpha) {
                    if ($aClickRate > $bClickRate) {
                        $winner = 'Original (A)';
                        $winnerClass = 'label-success';
                        $improvement = $bClickRate > 0 ? round((($aClickRate - $bClickRate) / $bClickRate) * 100, 1) : 0;
                    } elseif ($bClickRate > $aClickRate) {
                        $winner = 'Variante (B)';
                        $winnerClass = 'label-info';
                        $improvement = $aClickRate > 0 ? round((($bClickRate - $aClickRate) / $aClickRate) * 100, 1) : 0;
                    } else {
                        $winner = 'Gleichstand';
                        $winnerClass = 'label-default';
                    }
                } else {
                    $winner = 'Kein signifikanter Unterschied';
                    $winnerClass = 'label-default';
                }
            } else {
                $winner = 'Zu wenig Daten';
                $winnerClass = 'label-default';
            }
            
            // Status
            $hasArticle = ((int) $test['article_exists']) === 1;
            $hasVariantArticle = ((int) $test['variant_exists']) === 1;
            $isActiveConfig = ((int) $test['is_active_config']) === 1;
            if ($isActiveConfig) {
                $status = $totalViews > 100 ? 'Aktiv' : 'Wenig Traffic';
                $statusClass = $totalViews > 100 ? 'label-success' : 'label-warning';
            } else {
                $status = 'Inaktiv (historisch)';
                $statusClass = 'label-default';
            }
            
            $editUrl = rex_url::backendPage('content/edit', ['article_id' => $test['id'], 'clang' => $clangId]);
            $editUrlB = rex_url::backendPage('content/edit', ['article_id' => $test['variant_id'], 'clang' => $clangId]);
            $detailUrl = rex_url::backendPage('ab_tests/stats', ['article_id' => $test['id'], 'clang' => $clangId]);
            
            $content .= '
                <tr>
                    <td style="padding: 10px;">
                        <strong>' . rex_escape($test['name']) . '</strong><br>
                        <small class="text-muted">vs. ' . rex_escape($test['variant_name'] ?? 'Nicht gefunden') . '</small>';

            if (!$isActiveConfig) {
                $content .= '<br><small class="text-muted">nicht mehr aktiv konfiguriert</small>';
            }

            $content .= '
                    </td>
                    <td style="padding: 10px;">
                        <small class="text-muted">Einrichtung: ' . rex_escape($runtimeInfo['setupDate']) . '</small><br>
                        <small>Laufzeit: ' . rex_escape($runtimeInfo['runtime']) . '</small>
                    </td>
                    <td style="padding: 10px;">
                        <span class="label ' . $statusClass . '">' . $status . '</span><br>
                        <small class="text-muted">' . number_format($totalViews) . ' Views</small>
                    </td>
                    <td style="padding: 10px;">
                        <div class="row" style="margin: 0;">
                            <div class="col-xs-6" style="padding-right: 5px;">
                                <small><strong>Original (A)</strong></small><br>
                                <small>Click-Rate: <strong>' . $aClickRate . '%</strong></small><br>
                                <small>Exit-Rate: <strong>' . $aExitRate . '%</strong></small><br>
                                <small class="text-muted">' . number_format($aViews) . ' Views</small>
                            </div>
                            <div class="col-xs-6" style="padding-left: 5px;">
                                <small><strong>Variante (B)</strong></small><br>
                                <small>Click-Rate: <strong>' . $bClickRate . '%</strong></small><br>
                                <small>Exit-Rate: <strong>' . $bExitRate . '%</strong></small><br>
                                <small class="text-muted">' . number_format($bViews) . ' Views</small>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 10px;">
                        <span class="label ' . $winnerClass . '">' . $winner . '</span>';
            
            if ($improvement > 0) {
                $content .= '<br><p class="text-success">+' . $improvement . '% besser</p>';
            }
            
            $content .= '
                    </td>
                    <td class="text-right" style="padding: 10px;">';

            if ($hasArticle) {
                $content .= '
                        <a href="' . $detailUrl . '" class="btn btn-default" style="margin-bottom: 4px;" title="Statistiken anzeigen">
                            <i class="rex-icon fa-bar-chart"></i> Statistiken
                        </a>
                        <a href="' . $editUrl . '" class="btn btn-primary" style="margin-bottom: 4px;"  title="Variante A bearbeiten">
                            <i class="rex-icon fa-edit"></i> Variante A
                        </a>';
            } else {
                $content .= '
                        <a class="btn btn-default disabled" style="margin-bottom: 4px; pointer-events: none; opacity: .65;" title="Artikel nicht mehr vorhanden">
                            <i class="rex-icon fa-bar-chart"></i> Statistiken
                        </a>
                        <a class="btn btn-primary disabled" style="margin-bottom: 4px; pointer-events: none; opacity: .65;" title="Artikel A nicht mehr vorhanden">
                            <i class="rex-icon fa-edit"></i> Variante A
                        </a>';
            }
            
            if ($test['variant_id'] && $hasVariantArticle) {
                $content .= '
                        <a href="' . $editUrlB . '" class="btn  btn-info" style="margin-bottom: 4px;"  title="Variante B bearbeiten">
                            <i class="rex-icon fa-edit"></i> Variante B
                        </a>';
            } elseif ($test['variant_id']) {
                $content .= '
                        <a class="btn btn-info disabled" style="margin-bottom: 4px; pointer-events: none; opacity: .65;" title="Variante B nicht mehr vorhanden">
                            <i class="rex-icon fa-edit"></i> Variante B
                        </a>';
            }
            
            $content .= '
                    </td>
                </tr>';
        }
        
        $content .= '</tbody></table></div></div>';
        
        // Zusätzliche Insights - REDAXO Dark Mode Design
        $insights = '
        <div class="row" style="margin-top: 30px;">
            <div class="col-md-6">
                <div class="panel panel-default abtests-panel" style="border: 1px solid var(--ab-panel-border); background: var(--ab-panel-bg);">
                    <div class="panel-heading" style="background: var(--ab-panel-heading-bg); border: 1px solid var(--ab-panel-border); border-bottom: none; color: var(--ab-panel-text);">
                        <h4 class="panel-title" style="font-weight: 500;"><i class="rex-icon fa-line-chart" style="margin-right: 8px;"></i> Performance Insights</h4>
                    </div>
                    <div class="panel-body" style="background: var(--ab-panel-bg); padding: 20px; color: var(--ab-panel-text);">
                        <div style="space-y: 15px;">';
        
        // Top-Performer finden
        $bestTest = null;
        $bestRate = 0;
        foreach ($tests as $test) {
            $stats = $allStats[$test['id']];
            $totalViews = $stats['a']['view'] + $stats['b']['view'];
            $bestVariantRate = max(
                $stats['a']['view'] > 0 ? ($stats['a']['click'] / $stats['a']['view']) * 100 : 0,
                $stats['b']['view'] > 0 ? ($stats['b']['click'] / $stats['b']['view']) * 100 : 0
            );
            
            if ($bestVariantRate > $bestRate && $totalViews > 50) {
                $bestRate = $bestVariantRate;
                $bestTest = $test;
            }
        }
        
        if ($bestTest !== null) {
            $insights .= '
                            <div style="display: flex; align-items: center; margin-bottom: 15px; padding: 12px; background: var(--ab-card-bg); border-left: 3px solid #f39c12; border-radius: 4px;">
                                <i class="rex-icon fa-trophy" style="color: #f39c12; font-size: 20px; margin-right: 12px; min-width: 20px;"></i>
                                <div>
                                    <strong style="color: var(--ab-panel-text);">Top-Performer:</strong>
                                    <br><span style="color: var(--ab-panel-muted);">' . rex_escape($bestTest['name']) . '</span>
                                    <span class="label label-warning" style="margin-left: 8px;">' . round($bestRate, 1) . '% Click-Rate</span>
                                </div>
                            </div>';
        }
        
        // Durchschnittliche Exit-Rate
        $totalViews = 0;
        $totalExits = 0;
        foreach ($allStats as $stats) {
            $totalViews += $stats['a']['view'] + $stats['b']['view'];
            $totalExits += $stats['a']['exit'] + $stats['b']['exit'];
        }
        $avgExitRate = $totalViews > 0 ? round(($totalExits / $totalViews) * 100, 1) : 0;
        
        $insights .= '
                            <div style="display: flex; align-items: center; margin-bottom: 15px; padding: 12px; background: var(--ab-card-bg); border-left: 3px solid #e74c3c; border-radius: 4px;">
                                <i class="rex-icon fa-sign-out" style="color: #e74c3c; font-size: 20px; margin-right: 12px; min-width: 20px;"></i>
                                <div>
                                    <strong style="color: var(--ab-panel-text);">Ø Exit-Rate:</strong>
                                    <span class="label label-danger" style="margin-left: 8px;">' . $avgExitRate . '%</span>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; margin-bottom: 0; padding: 12px; background: var(--ab-card-bg); border-left: 3px solid #3498db; border-radius: 4px;">
                                <i class="rex-icon fa-users" style="color: #3498db; font-size: 20px; margin-right: 12px; min-width: 20px;"></i>
                                <div>
                                    <strong style="color: var(--ab-panel-text);">Gesamt Tests:</strong>
                                    <span class="label label-primary" style="margin-left: 8px;">' . count($tests) . '</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
            <div class="col-md-6">
                <div class="panel panel-default abtests-panel" style="border: 1px solid var(--ab-panel-border); background: var(--ab-panel-bg);">
                    <div class="panel-heading" style="background: var(--ab-panel-heading-bg); border: 1px solid var(--ab-panel-border); border-bottom: none; color: var(--ab-panel-text);">
                        <h4 class="panel-title" style="font-weight: 500;"><i class="rex-icon fa-lightbulb-o" style="margin-right: 8px;"></i> Empfehlungen</h4>
                    </div>
                    <div class="panel-body" style="background: var(--ab-panel-bg); padding: 20px; color: var(--ab-panel-text);">
                        <div style="space-y: 15px;">
                            <div style="display: flex; align-items: center; margin-bottom: 15px; padding: 12px; background: var(--ab-card-bg); border-left: 3px solid #3498db; border-radius: 4px;">
                                <i class="rex-icon fa-clock-o" style="color: #3498db; font-size: 18px; margin-right: 12px; min-width: 18px;"></i>
                                <div style="color: var(--ab-panel-text);">Tests mindestens <strong>2 Wochen</strong> laufen lassen</div>
                            </div>
                            <div style="display: flex; align-items: center; margin-bottom: 15px; padding: 12px; background: var(--ab-card-bg); border-left: 3px solid #27ae60; border-radius: 4px;">
                                <i class="rex-icon fa-bar-chart" style="color: #27ae60; font-size: 18px; margin-right: 12px; min-width: 18px;"></i>
                                <div style="color: var(--ab-panel-text);">Signifikanz ab <strong>100+ Views</strong> pro Variante</div>
                            </div>
                            <div style="display: flex; align-items: center; margin-bottom: 15px; padding: 12px; background: var(--ab-card-bg); border-left: 3px solid #f39c12; border-radius: 4px;">
                                <i class="rex-icon fa-mouse-pointer" style="color: #f39c12; font-size: 18px; margin-right: 12px; min-width: 18px;"></i>
                                <div style="color: var(--ab-panel-text);">Click-Tracking mit <code style="background: var(--ab-code-bg); padding: 2px 6px; border-radius: 3px; color: #e74c3c;">data-ab-track="click"</code></div>
                            </div>
                            <div style="display: flex; align-items: center; margin-bottom: 0; padding: 12px; background: var(--ab-card-bg); border-left: 3px solid #9b59b6; border-radius: 4px;">
                                <i class="rex-icon fa-refresh" style="color: #9b59b6; font-size: 18px; margin-right: 12px; min-width: 18px;"></i>
                                <div style="color: var(--ab-panel-text);">Regelmäßige <strong>Analyse</strong> für Optimierungen</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        echo $content . $insights;
    }
}
