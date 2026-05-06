<?php

$addon = rex_addon::get('ab_tests');

// A/B Test Tabellen löschen
$sql = rex_sql::factory();
$sql->setQuery('DROP TABLE IF EXISTS `' . rex::getTable('ab_test_events') . '`');
$sql->setQuery('DROP TABLE IF EXISTS `' . rex::getTable('ab_tests') . '`');

// Spalte aus rex_article entfernen  
$sql = rex_sql::factory();
$sql->setQuery('SHOW COLUMNS FROM ' . rex::getTable('article') . ' LIKE "art_ab_variant"');

if ($sql->getRows() > 0) {
    $sql = rex_sql::factory();
    $sql->setQuery('ALTER TABLE ' . rex::getTable('article') . ' DROP COLUMN art_ab_variant');
}

// Cache leeren
rex_delete_cache();

echo rex_view::success('A/B Tests Addon wurde erfolgreich deinstalliert.');
