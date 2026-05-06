<?php

/**
 * A/B Tests Content Sidebar Fragment
 *
 * @var rex_addon $this
 * @var array{article_id: int, clang: int, ctype: int} $params
 */

$content = '';
$addon = rex_addon::get('ab_tests');

if (rex::getUser() === null || !rex::getUser()->hasPerm('ab_test[]')) {
    return '';
}

$article_id = $params['article_id'];
$clang = $params['clang'];
$ctype = $params['ctype'];

$yform = new rex_yform();
$yform->setObjectparams('form_action', rex_url::backendController(['page' => 'content/edit', 'article_id' => $article_id, 'clang' => $clang, 'ctype' => $ctype], false));
$yform->setObjectparams('form_name', 'ab-tests');
$yform->setHiddenField('abtests_func', 'save');

$yform->setObjectparams('form_showformafterupdate', 1);

$yform->setObjectparams('main_table', rex::getTable('article'));
$yform->setObjectparams('main_id', $article_id);
$yform->setObjectparams('main_where', 'id='.$article_id.' and clang_id='.$clang);
$yform->setObjectparams('getdata', true);

// A/B Test Variante (REX_LINK_WIDGET)
$yform->setValueField('be_link', [
    'art_ab_variant', 
    'A/B Test Variante', 
    '0' // multiple = '0' (single link)
]);

$yform->setActionField('db', [rex::getTable('article'), 'id=' . $article_id.' and clang_id='.$clang]);
$yform->setObjectparams('submit_btn_label', 'Speichern');
$form = $yform->getForm();

if ($yform->objparams['actions_executed']) {
    $article = rex_article::get($article_id, $clang);
    $variantId = $article !== null ? (int) $article->getValue('art_ab_variant') : 0;
    if ($variantId === (int) $article_id) {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'UPDATE ' . rex::getTable('article') . ' SET art_ab_variant = NULL WHERE id = ? AND clang_id = ?',
            [(int) $article_id, (int) $clang]
        );
        \FriendsOfRedaxo\ABTests\ABTestHelper::syncTestConfig((int) $article_id, 0, (int) $clang);
        $form = rex_view::error(rex_i18n::msg('ab_tests_error_same_article')) . $form;
    } else {
        \FriendsOfRedaxo\ABTests\ABTestHelper::syncTestConfig((int) $article_id, $variantId, (int) $clang);
        $form = rex_view::success('A/B Test Einstellungen wurden gespeichert.') . $form;
    }

    rex_article_cache::delete($article_id, $clang);
}

$form = '<section id="rex-page-sidebar-ab-tests" data-pjax-container="#rex-page-sidebar-ab-tests" data-pjax-no-history="1">'.$form.'</section>';

return $form;
