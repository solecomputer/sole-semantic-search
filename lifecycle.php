<?php

declare(strict_types=1);

namespace SoleSemanticSearch;

if (!defined('ABSPATH')) {
    exit;
}

const TRANSLATION_CRON_HOOK = 'sole_ss_translate_step';
const ACTIVATED_AT_OPTION = 'sole_ss_activated_at';
const FIRST_RUN_NOTICE_LIFETIME_SECONDS = 86400;
