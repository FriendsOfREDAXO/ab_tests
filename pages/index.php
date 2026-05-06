<?php

$addon = rex_addon::get('ab_tests');

echo rex_view::title(rex_i18n::msg('ab_tests_title'));

// Include subpage
rex_be_controller::includeCurrentPageSubPath();
