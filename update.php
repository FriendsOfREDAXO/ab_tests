<?php

// A/B Test Historie (dauerhaft, auch ohne Events)
$table = rex_sql_table::get(rex::getTable('ab_tests'));
$table
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('test_id', 'int(11)'))
    ->ensureColumn(new rex_sql_column('variant_id', 'int(11)', true))
    ->ensureColumn(new rex_sql_column('test_name', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('variant_name', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('clang_id', 'int(11)', false, '1'))
    ->ensureColumn(new rex_sql_column('started_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('ended_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('is_active', 'tinyint(1)', false, '1'))
    ->ensureColumn(new rex_sql_column('updated_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('idx_test_active', ['test_id', 'is_active']))
    ->ensureIndex(new rex_sql_index('idx_variant', ['variant_id']))
    ->ensure();

if ((string) rex_config::get('ab_tests', 'tracking_secret', '') === '') {
    rex_config::set('ab_tests', 'tracking_secret', bin2hex(random_bytes(32)));
}

$sql = rex_sql::factory();

// Aktive Konfigurationen in Historie übernehmen, falls noch nicht vorhanden
$sql->setQuery('
    INSERT INTO ' . rex::getTable('ab_tests') . ' (test_id, variant_id, test_name, variant_name, clang_id, started_at, ended_at, is_active, updated_at)
    SELECT 
        a.id,
        NULLIF(a.art_ab_variant, 0) AS variant_id,
        a.name AS test_name,
        av.name AS variant_name,
        a.clang_id,
        NOW() AS started_at,
        CASE WHEN a.art_ab_variant > 0 THEN NULL ELSE NOW() END AS ended_at,
        CASE WHEN a.art_ab_variant > 0 THEN 1 ELSE 0 END AS is_active,
        NOW() AS updated_at
    FROM ' . rex::getTable('article') . ' a
    LEFT JOIN ' . rex::getTable('article') . ' av ON av.id = a.art_ab_variant AND av.clang_id = a.clang_id
    WHERE (
        a.art_ab_variant > 0
        OR EXISTS (
            SELECT 1
            FROM ' . rex::getTable('ab_test_events') . ' e
            WHERE e.test_id = a.id
        )
    )
    AND NOT EXISTS (
        SELECT 1
        FROM ' . rex::getTable('ab_tests') . ' h
        WHERE h.test_id = a.id
        AND h.clang_id = a.clang_id
    )
');

// Tests, die nur noch in Events existieren (Artikel ggf. gelöscht), als historische Datensätze anlegen
$sql->setQuery('
    INSERT INTO ' . rex::getTable('ab_tests') . ' (test_id, variant_id, test_name, variant_name, clang_id, started_at, ended_at, is_active, updated_at)
    SELECT
        e.test_id,
        NULL,
        CONCAT("Artikel #", e.test_id) AS test_name,
        NULL AS variant_name,
        0 AS clang_id,
        MIN(e.created_at) AS started_at,
        NOW() AS ended_at,
        0 AS is_active,
        NOW() AS updated_at
    FROM ' . rex::getTable('ab_test_events') . ' e
    LEFT JOIN ' . rex::getTable('ab_tests') . ' h ON h.test_id = e.test_id
    WHERE h.id IS NULL
    GROUP BY e.test_id
');

rex_delete_cache();
