<?php
/**
 * Plugin Name:       SOLE Semantic Search
 * Plugin URI:        https://sole.computer/wordpress/semantic-search/
 * Description:       Replaces native WordPress search with SOLE Engine's semantic search.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.3
 * Author:            Sole Computer
 * Author URI:        https://sole.computer/
 * License:           AGPL-3.0-only
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.html
 * Requires Plugins:  sole-engine
 */

declare(strict_types=1);

namespace SoleSemanticSearch;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/core.php';

\add_action('plugins_loaded', function (): void {
    Core_Plugin::bootstrap();
});

\register_activation_hook(__FILE__, static function (bool $networkWide = false): void {
    $recordFirstActivation = static function (): void {
        if (\get_option(ACTIVATED_AT_OPTION, null) === null) {
            \update_option(ACTIVATED_AT_OPTION, \time(), false);
        }
    };

    if ($networkWide && \is_multisite()) {
        foreach (\get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
            \switch_to_blog((int) $siteId);
            $recordFirstActivation();
            \restore_current_blog();
        }
        return;
    }

    $recordFirstActivation();
});

\register_deactivation_hook(__FILE__, static function (bool $networkWide = false): void {
    if ($networkWide && \is_multisite()) {
        foreach (\get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
            \switch_to_blog((int) $siteId);
            \wp_clear_scheduled_hook(TRANSLATION_CRON_HOOK);
            \restore_current_blog();
        }
        return;
    }

    \wp_clear_scheduled_hook(TRANSLATION_CRON_HOOK);
});
