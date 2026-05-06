<?php

$csrfToken = rex_csrf_token::factory('ab_tests_settings');
$csrfHiddenField = $csrfToken->getHiddenField();
$exportError = '';

// Export verarbeiten - muss als erstes stehen, bevor HTML ausgegeben wird!
$exportFormat = rex_get('export', 'string', '');
if ($exportFormat !== '') {
    if (!$csrfToken->isValid()) {
        $exportError = rex_view::error('Ungültiger Sicherheits-Token für den Export.');
    } else {
        $sql = rex_sql::factory();
        $format = $exportFormat;

        if ($format === 'csv' || $format === 'json') {
        // Output-Buffer löschen falls bereits gestartet
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $data = $sql->getArray('SELECT * FROM ' . rex::getTable('ab_test_events') . ' ORDER BY created_at DESC');

            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=ab_test_statistics_' . date('Y-m-d_H-i') . '.csv');
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: 0');

                $output = fopen('php://output', 'w');
                if ($data !== []) {
                    fputcsv($output, array_keys($data[0]));
                    foreach ($data as $row) {
                        $safeRow = [];
                        foreach ($row as $key => $value) {
                            $safeRow[$key] = \FriendsOfRedaxo\ABTests\ABTestHelper::sanitizeExportValue($value);
                        }
                        fputcsv($output, $safeRow);
                    }
                }
                fclose($output);
                exit;
            } else {
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename=ab_test_statistics_' . date('Y-m-d_H-i') . '.json');
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: 0');

                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
}

$config = rex_config::get('ab_tests', 'settings', []);

echo '
<style>
:root {
    --ab-panel-bg: #222B34;
    --ab-panel-heading-bg: #2D3844;
    --ab-panel-text: #ecf0f1;
    --ab-panel-muted: #95a5a6;
    --ab-card-bg: #2D3844;
    --ab-input-bg: #222B34;
    --ab-input-text: #ecf0f1;
    --ab-panel-border: transparent;
}
body.rex-theme-light {
    --ab-panel-bg: #ffffff;
    --ab-panel-heading-bg: #eef2f5;
    --ab-panel-text: #2b3a4a;
    --ab-panel-muted: #6b7a8a;
    --ab-card-bg: #f7f9fb;
    --ab-input-bg: #ffffff;
    --ab-input-text: #2b3a4a;
    --ab-panel-border: #d9e0e7;
}
</style>
';

if ($exportError !== '') {
    echo $exportError;
}

// Einstellungen speichern
if (rex_post('save', 'boolean')) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error('Ungültiger Sicherheits-Token. Bitte Seite neu laden und erneut versuchen.');
    } else {
    // Checkboxen: explizit prüfen ob Parameter existiert, da nicht angehakte Checkboxen keinen Wert senden
        $config['tracking_enabled'] = rex_post('tracking_enabled', 'boolean') ? true : false;
        $config['split_ratio'] = max(1, min(99, rex_post('split_ratio', 'int', 50)));
        $config['session_duration'] = max(300, min(2592000, rex_post('session_duration', 'int', 3600)));
        $config['auto_track_clicks'] = rex_post('auto_track_clicks', 'boolean') ? true : false;
        $config['auto_track_exits'] = rex_post('auto_track_exits', 'boolean') ? true : false;
        $config['store_click_data'] = rex_post('store_click_data', 'boolean') ? true : false;
        $config['store_details'] = rex_post('store_details', 'boolean') ? true : false;

        rex_config::set('ab_tests', 'settings', $config);

        echo rex_view::success('Einstellungen gespeichert.');
    }
}

// Datenbereinigung verarbeiten
$cleanupAction = rex_post('cleanup_action', 'string', '');
if ($cleanupAction !== '') {
    if (!$csrfToken->isValid()) {
        echo rex_view::error('Ungültiger Sicherheits-Token. Bitte Seite neu laden und erneut versuchen.');
    } else {
        $sql = rex_sql::factory();

        switch ($cleanupAction) {
            case 'delete_old':
                $days = rex_post('delete_days', 'int', 30);
                if ($days > 0) {
                    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                    $sql->setQuery('DELETE FROM ' . rex::getTable('ab_test_events') . ' WHERE created_at < ?', [$cutoffDate]);
                    $deletedRows = $sql->getRows();
                    echo rex_view::success("Events älter als {$days} Tage wurden gelöscht. ({$deletedRows} Einträge entfernt)");
                }
                break;

            case 'reset_all':
                if (rex_post('confirm_reset') === '1') {
                    $sql->setQuery('TRUNCATE TABLE ' . rex::getTable('ab_test_events'));
                    $sql->setQuery('TRUNCATE TABLE ' . rex::getTable('ab_tests'));
                    $sql->setQuery('UPDATE ' . rex::getTable('article') . ' SET art_ab_variant = NULL');
                    rex_delete_cache();
                    echo rex_view::success('Komplette Datenbank wurde zurückgesetzt. Events, Historie und Artikel-Verknüpfungen wurden entfernt.');
                } else {
                    echo rex_view::error('Bitte bestätigen Sie das Zurücksetzen der Datenbank.');
                }
                break;
        }
    }
}

// Optisch verbessertes Form-Design
$form = '
<div class="panel panel-primary abtests-panel" style="border: 1px solid var(--ab-panel-border); background: var(--ab-panel-bg);">
    <div class="panel-heading" style="background: var(--ab-panel-heading-bg); border: 1px solid var(--ab-panel-border); border-bottom: none; color: var(--ab-panel-text);">
        <h4 class="panel-title" style="font-weight: 500;">
            <i class="rex-icon fa-cogs" style="margin-right: 8px;"></i> 
            Konfiguration
        </h4>
    </div>
    <div class="panel-body" style="background: var(--ab-panel-bg); padding: 25px; color: var(--ab-panel-text);">
        <form method="post">
            ' . $csrfHiddenField . '
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group" style="margin-bottom: 25px;">
                        <div style="background: var(--ab-card-bg); padding: 15px; border-radius: 4px; border-left: 3px solid #27ae60;">
                            <label class="control-label" style="font-weight: 500; color: var(--ab-panel-text); margin: 0;">
                                <input type="checkbox" name="tracking_enabled" value="1"' . (($config['tracking_enabled'] ?? true) === true ? ' checked' : '') . ' style="margin-right: 8px;">
                                <i class="rex-icon fa-line-chart" style="margin-right: 5px; color: #27ae60;"></i>
                                Tracking aktiviert
                            </label>
                            <p class="help-block" style="margin: 8px 0 0 0; color: var(--ab-panel-muted); font-size: 13px;">Deaktivieren Sie diese Option, um das Sammeln von Daten zu stoppen.</p>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 25px;">
                        <div style="background: var(--ab-card-bg); padding: 15px; border-radius: 4px; border-left: 3px solid #3498db;">
                            <label class="control-label" style="font-weight: 500; color: var(--ab-panel-text); margin-bottom: 10px; display: block;">
                                <i class="rex-icon fa-balance-scale" style="margin-right: 5px; color: #3498db;"></i>
                                Traffic-Aufteilung für Variante A
                            </label>
                            <select name="split_ratio" class="form-control" style="background: var(--ab-input-bg); border: 1px solid var(--ab-panel-border); color: var(--ab-input-text);">
                                <option value="10"' . (($config['split_ratio'] ?? 50) === 10 ? ' selected' : '') . '>10% (A) : 90% (B)</option>
                                <option value="25"' . (($config['split_ratio'] ?? 50) === 25 ? ' selected' : '') . '>25% (A) : 75% (B)</option>
                                <option value="50"' . (($config['split_ratio'] ?? 50) === 50 ? ' selected' : '') . '>50% (A) : 50% (B)</option>
                                <option value="75"' . (($config['split_ratio'] ?? 50) === 75 ? ' selected' : '') . '>75% (A) : 25% (B)</option>
                                <option value="90"' . (($config['split_ratio'] ?? 50) === 90 ? ' selected' : '') . '>90% (A) : 10% (B)</option>
                            </select>
                            <p class="help-block" style="margin: 8px 0 0 0; color: var(--ab-panel-muted); font-size: 13px;">Prozent der Besucher, die Variante A sehen (Rest sieht Variante B).</p>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 25px;">
                        <div style="background: var(--ab-card-bg); padding: 15px; border-radius: 4px; border-left: 3px solid #9b59b6;">
                            <label class="control-label" style="font-weight: 500; color: var(--ab-panel-text); margin-bottom: 10px; display: block;">
                                <i class="rex-icon fa-clock-o" style="margin-right: 5px; color: #9b59b6;"></i>
                                Session-Dauer (Sekunden)
                            </label>
                            <input type="number" name="session_duration" value="' . ($config['session_duration'] ?? 3600) . '" class="form-control" style="background: var(--ab-input-bg); border: 1px solid var(--ab-panel-border); color: var(--ab-input-text);">
                            <p class="help-block" style="margin: 8px 0 0 0; color: var(--ab-panel-muted); font-size: 13px;">Wie lange ein Besucher der gleichen Variante zugeordnet bleibt.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group" style="margin-bottom: 25px;">
                        <div style="background: var(--ab-card-bg); padding: 15px; border-radius: 4px; border-left: 3px solid #f39c12;">
                            <label class="control-label" style="font-weight: 500; color: var(--ab-panel-text); margin: 0;">
                                <input type="checkbox" name="auto_track_clicks" value="1"' . (($config['auto_track_clicks'] ?? true) === true ? ' checked' : '') . ' style="margin-right: 8px;">
                                <i class="rex-icon fa-mouse-pointer" style="margin-right: 5px; color: #f39c12;"></i>
                                Alle Klicks automatisch tracken
                            </label>
                            <p class="help-block" style="margin: 8px 0 0 0; color: var(--ab-panel-muted); font-size: 13px;">Trackt automatisch alle Link-Klicks als Zählung (ohne URL/Link-Text). Überschreibt manuelle data-ab-track Attribute.</p>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 25px;">
                        <div style="background: var(--ab-card-bg); padding: 15px; border-radius: 4px; border-left: 3px solid #e74c3c;">
                            <label class="control-label" style="font-weight: 500; color: var(--ab-panel-text); margin: 0;">
                                <input type="checkbox" name="auto_track_exits" value="1"' . (($config['auto_track_exits'] ?? true) === true ? ' checked' : '') . ' style="margin-right: 8px;">
                                <i class="rex-icon fa-sign-out" style="margin-right: 5px; color: #e74c3c;"></i>
                                Automatisches Exit-Intent Tracking
                            </label>
                            <p class="help-block" style="margin: 8px 0 0 0; color: var(--ab-panel-muted); font-size: 13px;">Verfolgt Exit-Intent Events (Maus verlässt Browser-Fenster).</p>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <div style="background: var(--ab-card-bg); padding: 15px; border-radius: 4px; border-left: 3px solid #16a085;">
                            <label class="control-label" style="font-weight: 500; color: var(--ab-panel-text); margin: 0;">
                                <input type="checkbox" name="store_click_data" value="1"' . (($config['store_click_data'] ?? true) === true ? ' checked' : '') . ' style="margin-right: 8px;">
                                <i class="rex-icon fa-link" style="margin-right: 5px; color: #16a085;"></i>
                                Klick-URL und Linktext speichern
                            </label>
                            <p class="help-block" style="margin: 8px 0 0 0; color: var(--ab-panel-muted); font-size: 13px;">Nur aktivieren, wenn diese Detailtiefe für die Auswertung wirklich gebraucht wird.</p>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <div style="background: var(--ab-card-bg); padding: 15px; border-radius: 4px; border-left: 3px solid #8e44ad;">
                            <label class="control-label" style="font-weight: 500; color: var(--ab-panel-text); margin: 0;">
                                <input type="checkbox" name="store_details" value="1"' . (($config['store_details'] ?? true) === true ? ' checked' : '') . ' style="margin-right: 8px;">
                                <i class="rex-icon fa-file-text-o" style="margin-right: 5px; color: #8e44ad;"></i>
                                Event-Details speichern
                            </label>
                            <p class="help-block" style="margin: 8px 0 0 0; color: var(--ab-panel-muted); font-size: 13px;">Für datensparsame Setups besser deaktivieren, wenn keine freien Event-Details nötig sind.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center" style="margin-top: 20px; padding-top: 15px;">
                <button type="submit" name="save" value="1" class="btn btn-primary" style="padding: 8px 20px; background: #3498db; border: none;">
                    <i class="rex-icon fa-save"></i> Einstellungen speichern
                </button>
            </div>
        </form>
    </div>
</div>';

echo $form;

// Datenbereinigung Panel
$cleanupPanel = '
<div class="panel panel-warning abtests-panel" style="border: 1px solid var(--ab-panel-border); background: var(--ab-panel-bg); margin-top: 25px;">
    <div class="panel-heading" style="background: var(--ab-panel-heading-bg); border: 1px solid var(--ab-panel-border); border-bottom: none; color: var(--ab-panel-text);">
        <h4 class="panel-title" style="font-weight: 500;">
            <i class="rex-icon fa-broom" style="margin-right: 8px;"></i> 
            Datenbereinigung
        </h4>
    </div>
    <div class="panel-body" style="background: var(--ab-panel-bg); padding: 25px; color: var(--ab-panel-text);">
        <div class="row">
            <div class="col-md-4">
                <form method="post" style="background: var(--ab-card-bg); padding: 20px; border-radius: 4px; border-left: 3px solid #f39c12;">
                    ' . $csrfHiddenField . '
                    <h5 style="margin-top: 0; color: var(--ab-panel-text); font-weight: 500;">
                        <i class="rex-icon fa-calendar" style="margin-right: 8px; color: #f39c12;"></i>
                        Alte Events löschen
                    </h5>
                    <div class="form-group">
                        <label class="control-label" style="color: var(--ab-panel-text);">Events älter als (Tage):</label>
                        <input type="number" name="delete_days" value="30" min="1" max="365" class="form-control" style="background: var(--ab-input-bg); border: 1px solid var(--ab-panel-border); color: var(--ab-input-text);" required>
                    </div>
                    <button type="submit" name="cleanup_action" value="delete_old" class="btn btn-warning btn-block" 
                        onclick="return confirm(\'Events älter als \' + document.getElementsByName(\'delete_days\')[0].value + \' Tage wirklich löschen?\')"
                        style="background: #f39c12; border: none;">
                        <i class="rex-icon fa-trash"></i> Alte Events löschen
                    </button>
                </form>
            </div>
            
            <div class="col-md-4">
                <form method="post" style="background: var(--ab-card-bg); padding: 20px; border-radius: 4px; border-left: 3px solid #e74c3c;">
                    ' . $csrfHiddenField . '
                    <h5 style="margin-top: 0; color: var(--ab-panel-text); font-weight: 500;">
                        <i class="rex-icon fa-refresh" style="margin-right: 8px; color: #e74c3c;"></i>
                        Komplettes Reset
                    </h5>
                    <p style="color: var(--ab-panel-muted); font-size: 13px; margin-bottom: 15px;">
                        Löscht alle Events, die Historie und entfernt alle A/B-Test Verknüpfungen aus den Artikeln.
                    </p>
                    <div class="checkbox" style="margin-bottom: 15px;">
                        <label style="color: var(--ab-panel-text);">
                            <input type="checkbox" name="confirm_reset" value="1" required>
                            Ich bestätige das komplette Zurücksetzen
                        </label>
                    </div>
                    <button type="submit" name="cleanup_action" value="reset_all" class="btn btn-danger btn-block"
                        onclick="return confirm(\'ACHTUNG: Alle A/B-Test Daten und Artikel-Verknüpfungen werden unwiderruflich gelöscht! Fortfahren?\')"
                        style="background: #e74c3c; border: none;">
                        <i class="rex-icon fa-exclamation-triangle"></i> Alles zurücksetzen
                    </button>
                </form>
            </div>
            
            <div class="col-md-4">
                <div style="background: var(--ab-card-bg); padding: 20px; border-radius: 4px; border-left: 3px solid #27ae60;">
                    <h5 style="margin-top: 0; color: var(--ab-panel-text); font-weight: 500;">
                        <i class="rex-icon fa-download" style="margin-right: 8px; color: #27ae60;"></i>
                        Export der Statistiken
                    </h5>
                    <p style="color: var(--ab-panel-muted); font-size: 13px; margin-bottom: 15px;">
                        Exportiert alle gesammelten A/B-Test Daten für externe Analyse.
                    </p>
                    <div style="margin-bottom: 10px;">
                        <a href="' . rex_url::backendPage('ab_tests/settings', array_merge(['export' => 'csv'], $csrfToken->getUrlParams())) . '" class="btn btn-success btn-block" style="background: #27ae60; border: none; margin-bottom: 8px;">
                            <i class="rex-icon fa-file-text-o"></i> Als CSV exportieren
                        </a>
                        <a href="' . rex_url::backendPage('ab_tests/settings', array_merge(['export' => 'json'], $csrfToken->getUrlParams())) . '" class="btn btn-info btn-block" style="background: #3498db; border: none;">
                            <i class="rex-icon fa-code"></i> Als JSON exportieren
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info" style="margin-top: 25px; background: rgba(52, 152, 219, 0.1); border: 1px solid #3498db; color: var(--ab-panel-text);">
            <i class="rex-icon fa-info-circle" style="margin-right: 8px;"></i>
            <strong>Hinweis:</strong> Die Datenbereinigung betrifft nur die Event-Tabelle. Artikel-Inhalte und A/B-Test Verknüpfungen bleiben bei der zeitbasierten Löschung erhalten.
        </div>
    </div>
</div>';

echo $cleanupPanel;
