<?php

$clangId = rex_clang::getCurrentId();

$sql = rex_sql::factory();
$articles = $sql->getArray('
    SELECT a.id, a.name, a.art_ab_variant, av.id AS variant_exists, av.name as variant_name
    FROM ' . rex::getTable('article') . ' a
    LEFT JOIN ' . rex::getTable('article') . ' av ON a.art_ab_variant = av.id AND av.clang_id = a.clang_id
    WHERE a.art_ab_variant > 0 
    AND a.clang_id = ?
    ORDER BY a.name
', [$clangId]);

if ($articles === []) {
    echo rex_view::info('Noch keine A/B Tests konfiguriert.');
} else {
    $content = '<table class="table table-striped"><thead><tr><th>Original-Artikel</th><th>Variant B</th><th></th></tr></thead><tbody>';
    
    foreach ($articles as $article) {
        $editUrlA = rex_url::backendPage('content/edit', ['article_id' => $article['id'], 'clang' => $clangId]);
        $editUrlB = rex_url::backendPage('content/edit', ['article_id' => $article['art_ab_variant'], 'clang' => $clangId]);
        $statsUrl = rex_url::backendPage('ab_tests/stats', ['article_id' => $article['id'], 'clang' => $clangId]);
        
        $content .= '<tr>';
        $content .= '<td style="padding: 10px;"><a href="' . $editUrlA . '">' . rex_escape($article['name']) . '</a></td>';
        $content .= '<td style="padding: 10px;">' . rex_escape($article['variant_name'] ?? 'Nicht gefunden') . '</td>';
        $content .= '<td class="text-right" style="padding: 10px;">';
        $content .= '<a href="' . $statsUrl . '" class="btn btn-default" style="margin-bottom: 4px;"  title="Statistiken anzeigen"><i class="rex-icon fa-bar-chart"></i> Statistiken</a> ';
        $content .= '<a href="' . $editUrlA . '" class="btn btn-primary" style="margin-bottom: 4px;" title="Variante A bearbeiten"><i class="rex-icon fa-edit"></i> Variante A</a> ';
        if ((int) $article['variant_exists'] > 0) {
            $content .= '<a href="' . $editUrlB . '" class="btn btn-info" style="margin-bottom: 4px;" title="Variante B bearbeiten"><i class="rex-icon fa-edit"></i> Variante B</a>';
        } elseif ($article['art_ab_variant']) {
            $content .= '<a class="btn btn-info disabled" style="margin-bottom: 4px; pointer-events: none; opacity: .65;" title="Variante B nicht mehr vorhanden"><i class="rex-icon fa-edit"></i> Variante B</a>';
        }
        $content .= '</td>';
        $content .= '</tr>';
    }
    
    $content .= '</tbody></table>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'A/B Tests Übersicht');
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
