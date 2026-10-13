<?php

   
                                              
  
                                                                       
                                                                    
                                                      
   

declare(strict_types=1);

namespace SoleSemanticSearch;

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/lifecycle.php';

   
                                                                       
                                                                       
                              
  
                
   
$sole_ss_option_keys = [
    'sole_ss_enabled',
    'sole_ss_results_per_page',
    'sole_ss_show_scores',
    'sole_ss_activated_at',
    'sole_ss_translate',
    'sole_ss_strings_translated',
];

$sole_ss_delete_options = static function () use ($sole_ss_option_keys): void {
    \wp_clear_scheduled_hook(TRANSLATION_CRON_HOOK);
    foreach ($sole_ss_option_keys as $key) {
        \delete_option($key);
    }
};

if (\is_multisite()) {
    $sole_ss_sites = \get_sites(['fields' => 'ids', 'number' => 0]);
    foreach ($sole_ss_sites as $sole_ss_site_id) {
        \switch_to_blog((int) $sole_ss_site_id);
        $sole_ss_delete_options();
        \restore_current_blog();
    }
} else {
    $sole_ss_delete_options();
}
