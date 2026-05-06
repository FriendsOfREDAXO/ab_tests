<?php

namespace FriendsOfRedaxo\ABTests;

use Exception;
use rex;
use rex_addon;
use rex_article;
use rex_clang;
use rex_config;
use rex_extension;
use rex_extension_point;
use rex_fragment;
use rex_i18n;
use rex_logger;
use rex_login;
use rex_path;
use rex_sql;
use rex_url;
use rex_var_article;
use rex_var_link;

$addon = rex_addon::get('ab_tests');

$hasAbTestsPermission = static function (): bool {
    $user = rex::getUser();
    return $user !== null && $user->hasPerm('ab_test[]');
};

$injectBeforeClosingTag = static function (string $content, string $tag, string $insertion): string {
    $closingTag = '</' . $tag . '>';
    $position = stripos($content, $closingTag);
    if ($position === false) {
        return $content . $insertion;
    }

    return substr_replace($content, $insertion . $closingTag, $position, strlen($closingTag));
};

$injectAfterOpeningTag = static function (string $content, string $tag, string $insertion): string {
    if (preg_match('/<' . preg_quote($tag, '/') . '\b[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        return $content;
    }

    $match = $matches[0][0];
    $offset = $matches[0][1] + strlen($match);

    return substr_replace($content, $insertion, $offset, 0);
};

// A/B Testing - Funktionierende Version aus base2026-1!
rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep) use ($injectAfterOpeningTag, $injectBeforeClosingTag) {
    if (rex::isFrontend()) {
        // Tracking-Endpunkt nicht anfassen (sonst 500er möglich)
        if (rex_request('ab_track', 'int') === 1 || rex_request('page', 'string') === 'ab_tests/track') {
            return $ep->getSubject();
        }
        $content = $ep->getSubject();
        
        // Debug-Kommentar nur bei aktivem Debug-Mode
        if (rex::isDebugMode()) {
            $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Test AddOn AKTIV (Debug Mode) -->');
        }
        
        try {
            $articleId = rex_article::getCurrentId();
            $article = rex_article::getCurrent();
            $clangId = rex_clang::getCurrentId();
            
            if ($article !== null) {
                $variantId = (int) $article->getValue('art_ab_variant');
                
                // Debug-Info nur bei Debug-Mode
                if (rex::isDebugMode()) {
                    $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: Article=' . $articleId . ', VariantField=' . $variantId . ' -->');
                }
                
                if ($variantId > 0) {
                    // Session starten falls nicht vorhanden
                    if (session_status() === PHP_SESSION_NONE) {
                        rex_login::startSession();
                    }
                    
                    $variant = \FriendsOfRedaxo\ABTests\ABTestHelper::getVariant($articleId);
                    $config = rex_config::get('ab_tests', 'settings', []);
                    $trackingEnabled = ($config['tracking_enabled'] ?? true) === true;
                    
                    // Debug-Info nur bei aktivem Debug-Modus
                    if (rex::isDebugMode()) {
                        $sessionKey = 'ab_variant_' . $articleId;
                        $sessionValue = rex_session($sessionKey, 'string', 'not_set');
                        $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: SessionKey=' . $sessionKey . ', SessionValue=' . $sessionValue . ', Assigned=' . $variant . ' -->');
                    }
                    
                    if ($trackingEnabled) {
                        // Automatisches Event-Tracking für View-Events (nur einmal pro Session)
                        $viewSessionKey = 'ab_view_tracked_' . $articleId . '_' . $variant;
                        if (!rex_session($viewSessionKey, 'boolean', false)) {
                            \FriendsOfRedaxo\ABTests\ABTestHelper::trackEvent($articleId, $variant, 'view');
                            rex_set_session($viewSessionKey, true);
                        }
                        
                        // Tracking-Script automatisch einfügen
                        $trackingScript = \FriendsOfRedaxo\ABTests\ABTestHelper::getTrackingScript($articleId, $variant);
                        if ($trackingScript !== '') {
                            $content = $injectBeforeClosingTag($content, 'body', $trackingScript);
                        }
                    }
                    
                    if ($variant === 'b') {
                        // Performance-optimierte Content-Ersetzung mit aktuellem Sprachkontext
                        $articleB = new \rex_article_content($variantId, $clangId);
                        $contentB = $articleB->getArticle();
                        
                        if (rex::isDebugMode()) {
                            $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: Loading variant B content -->');
                        }
                        
                        if ($contentB !== '') {
                            // Zuerst versuchen Content direkt zu ersetzen
                            $articleA = new \rex_article_content($articleId, $clangId);
                            $contentA = $articleA->getArticle();
                            
                            if (rex::isDebugMode()) {
                                $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: ContentA length=' . strlen($contentA) . ', ContentB length=' . strlen($contentB) . ' -->');
                            }
                            
                            if ($contentA !== '' && strpos($content, $contentA) !== false) {
                                $content = str_replace($contentA, $contentB, $content);
                                if (rex::isDebugMode()) {
                                    $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: Direct content replacement SUCCESS! -->');
                                }
                            } else {
                                if (rex::isDebugMode()) {
                                    $contentFound = ($contentA !== '') ? 'yes' : 'no';
                                    $contentInHtml = ($contentA !== '' && strpos($content, $contentA) !== false) ? 'yes' : 'no';
                                    $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: Direct replacement FAILED - ContentA exists=' . $contentFound . ', ContentA in HTML=' . $contentInHtml . ' -->');
                                }
                                
                                $replaced = false;
                                $selectedContainer = '';
                                $replaceLargestContainer = function (string $pattern, string $closingTag, string $label) use (&$content, $contentB, &$selectedContainer, $injectAfterOpeningTag): bool {
                                    if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                                        return false;
                                    }

                                    $best = null;
                                    foreach ($matches as $match) {
                                        $full = $match[0][0];
                                        $offset = $match[0][1];
                                        $inner = $match[1][0] ?? '';
                                        $len = strlen($inner);
                                        if ($best === null || $len > $best['len']) {
                                            $best = [
                                                'full' => $full,
                                                'offset' => $offset,
                                                'len' => $len,
                                            ];
                                        }
                                    }

                                    if ($best === null) {
                                        return false;
                                    }

                                    $openEnd = strpos($best['full'], '>');
                                    if ($openEnd === false) {
                                        return false;
                                    }

                                    $openTag = substr($best['full'], 0, $openEnd + 1);
                                    $newContent = $openTag . $contentB . $closingTag;
                                    $content = substr_replace($content, $newContent, $best['offset'], strlen($best['full']));
                                    $selectedContainer = $label;

                                    if (rex::isDebugMode()) {
                                        $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: ' . $label . ' replacement SUCCESS! matches=' . count($matches) . ', chosen_len=' . $best['len'] . ' -->');
                                    }

                                    return true;
                                };

                                // Fallback 0: Eindeutiger Wrapper fuer A/B Content
                                if ($replaceLargestContainer('/<div[^>]*id=["\']ab_section["\'][^>]*>(.*?)<\/div>/s', '</div>', 'AB section')) {
                                    $replaced = true;
                                }
                                // Fallback 1: Main-Container ersetzen (groesster Treffer)
                                elseif ($replaceLargestContainer('/<main[^>]*>(.*?)<\/main>/s', '</main>', 'Main container')) {
                                    $replaced = true;
                                }
                                // Fallback 2: Content-Container mit ID="content" (groesster Treffer)
                                elseif ($replaceLargestContainer('/<div[^>]*id=["\']content["\'][^>]*>(.*?)<\/div>/s', '</div>', 'Content div')) {
                                    $replaced = true;
                                }

                                if (!$replaced) {
                                    if (rex::isDebugMode()) {
                                        $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: All container replacements FAILED - no main tag or id="content" found -->');
                                    }
                                } elseif (rex::isDebugMode() && $selectedContainer !== '') {
                                    $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: Selected container=' . $selectedContainer . ' -->');
                                }
                            }
                        } else {
                            if (rex::isDebugMode()) {
                                $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: ContentB is empty! -->');
                            }
                        }
                    }
                } else {
                    if (rex::isDebugMode()) {
                        $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: No variant configured for this article -->');
                    }
                }
            } else {
                if (rex::isDebugMode()) {
                    $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Debug: Could not load current article -->');
                }
            }
        } catch (Exception $e) {
            if (rex::isDebugMode()) {
                $content = $injectAfterOpeningTag($content, 'head', '<!-- A/B Error: ' . $e->getMessage() . ' -->');
            }
        }
        
        return $content;
    }
    
    return $ep->getSubject();
});

// Backend Content Sidebar
if (rex::isBackend() && $hasAbTestsPermission()) {
    // A/B Tests Content Sidebar registrieren
    rex_extension::register('STRUCTURE_CONTENT_SIDEBAR', function (rex_extension_point $ep) {
        $params = $ep->getParams();
        $subject = $ep->getSubject();

        $panel = include rex_path::addon('ab_tests', 'fragments/content_sidebar.php');

        $fragment = new rex_fragment();
        $fragment->setVar('title', '<i class="rex-icon fa-flask"></i> ' . rex_i18n::msg('ab_tests_content'), false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('article_id', $params['article_id'], false);
        $fragment->setVar('clang', $params['clang'], false);
        $fragment->setVar('ctype', $params['ctype'], false);
        $fragment->setVar('collapse', true);
        $fragment->setVar('collapsed', false);
        $content = $fragment->parse('core/page/section.php');

        return $subject . $content;
    });
}

// Frontend Tracking Endpoint
if (rex::isFrontend() && (rex_request('page', 'string') === 'ab_tests/track' || rex_request('ab_track', 'int') === 1)) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            rex_login::startSession();
        }
        $testId = rex_request('test_id', 'int', 0);
        $variant = rex_request('variant', 'string', '');
        $token = rex_request('token', 'string', '');
        $event = rex_request('event', 'string', 'view');  
        $details = rex_request('details', 'string', '');
        
        $config = rex_config::get('ab_tests', 'settings', []);
        $trackingEnabled = ($config['tracking_enabled'] ?? true) === true;

        if (
            $trackingEnabled
            && $testId > 0
            && in_array($variant, ['a', 'b'], true)
            && \FriendsOfRedaxo\ABTests\ABTestHelper::canTrackTest($testId)
            && \FriendsOfRedaxo\ABTests\ABTestHelper::isValidTrackingToken($testId, $variant, $token)
        ) {
            $event = \FriendsOfRedaxo\ABTests\ABTestHelper::normalizeEvent($event);
            if ($event !== '') {
                \FriendsOfRedaxo\ABTests\ABTestHelper::trackEvent($testId, $variant, $event, $details);
            }
        }
    } catch (Exception $e) {
        // Tracking darf niemals das Frontend blockieren
        rex_logger::logException($e);
    }

    // 1x1 Pixel zurückgeben
    header('Content-Type: image/gif');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
    exit;
}
