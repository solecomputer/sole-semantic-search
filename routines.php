<?php

declare(strict_types=1);

namespace SoleSemanticSearch;

interface Routines_DiagnosticOutcomeInterface {
    public function getSiteId(): ?string;
    public function getValidReply(): ?bool;
    public function getCoordinateCount(): ?int;
    public function getElapsed(): float;
    public function getReturned(): int;
    public function fellBack(): bool;
    public function getReasonKey(): string;
                                     
    public function getDiscarded(): array;
    public function getSentQuery(): ?string;
    public function getCredentialBefore(): string;
    public function getCredentialAfter(): ?string;
                                                         
    public function getTraceRows(): array;
}

interface Routines_DiagnosticTraceRowInterface {
    public function getPosition(): int;
    public function getEntityId(): int;
    public function getScore(): ?float;
}

                                                                              
final class Routines_AdminNotice implements Routines_RoutineInterface {
    public function execute(): void {
        \add_action('admin_notices', [$this, 'renderNotice']);
    }

    public function renderNotice(): void {
        if (!\current_user_can('manage_options')) {
            return;
        }

        $screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
        if ($screen instanceof \WP_Screen && $screen->id === 'settings_page_sole-engine-wp-settings') {
            return;
        }

        if (!\function_exists('sole_engine_semantic')) {
            echo '<div class="notice notice-warning"><p>';
            echo \esc_html(Core_Strings::get('requires_engine'));
            echo '</p></div>';
            return;
        }

        $enabled = \get_option('sole_ss_enabled', '1');
        if (\is_string($enabled) && $enabled !== '1') {
            return;
        }

        if (!\function_exists('sole_engine_semantic_enabled')) {
            echo '<div class="notice notice-warning"><p>';
            echo \esc_html(Core_Strings::get('status_engine_update_required'));
            echo '</p></div>';
            return;
        }

        if (!\sole_engine_semantic_enabled()) {
            return;
        }

        $issue = Search_CredentialState::getIssue();
        if (\in_array($issue, [
            Search_FailureReason::CONFIG_MISSING,
            Search_FailureReason::CREDENTIAL_UNVERIFIED,
            Search_FailureReason::USER_KEY_INVALID,
            Search_FailureReason::ENGINE_UPDATE_REQUIRED,
        ], true)) {
            echo '<div class="notice notice-warning"><p>';
            echo \esc_html(Core_Strings::get('status_' . $issue));
            echo '</p></div>';
        }
    }
}

                                                                      
final class Routines_AdminSettings implements Routines_RoutineInterface {
                                                                            
                                                                  
    public const MENU_SLUG = 'sole-semantic-search-settings';
    private const SETTINGS_GROUP = 'sole_semantic_search_settings_group';
    private const SETTINGS_PAGE = 'sole_semantic_search_settings_page';

    private Routines_DiagnosticsPanelInterface $diagnostics;

    public function __construct(?Routines_DiagnosticsPanelInterface $diagnostics = null) {
        $this->diagnostics = $diagnostics ?? new Routines_Diagnostics();
    }

    public function execute(): void {
        \add_action('admin_init', [$this, 'registerSettings']);
        \add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerSettings(): void {
        \register_setting(
            self::SETTINGS_GROUP,
            'sole_ss_enabled',
            [
                'type' => 'boolean',
                'sanitize_callback' => function ($value): string {
                    if (\is_string($value)) {
                        return \trim($value) === '1' ? '1' : '0';
                    }
                    return $value ? '1' : '0';
                },
                'default' => '1',
                'show_in_rest' => false,
            ]
        );

        \register_setting(
            self::SETTINGS_GROUP,
            'sole_ss_results_per_page',
            [
                'type' => 'integer',
                'sanitize_callback' => function ($value): int {
                    if (!\is_numeric($value)) {
                        return 10;
                    }
                    $intValue = (int) $value;
                    if ($intValue < 1) {
                        return 1;
                    }
                    if ($intValue > 50) {
                        return 50;
                    }
                    return $intValue;
                },
                'default' => 10,
                'show_in_rest' => false,
            ]
        );

        \register_setting(
            self::SETTINGS_GROUP,
            'sole_ss_show_scores',
            [
                'type' => 'boolean',
                'sanitize_callback' => function ($value): string {
                    if (\is_string($value)) {
                        return \trim($value) === '1' ? '1' : '0';
                    }
                    return $value ? '1' : '0';
                },
                'default' => '0',
                'show_in_rest' => false,
            ]
        );

    }

    public function registerMenu(): void {
        \add_options_page(
            Core_Strings::get('plugin_name'),
            Core_Strings::get('plugin_name'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void {
        if (!\current_user_can('manage_options')) {
            return;
        }

        $enabled = \get_option('sole_ss_enabled', '1');
        if (!\is_string($enabled)) {
            $enabled = '1';
        }

        $resultsPerPage = \get_option('sole_ss_results_per_page', 10);
        if (!\is_numeric($resultsPerPage)) {
            $resultsPerPage = 10;
        }
        $resultsPerPage = (int) $resultsPerPage;

        echo '<div class="wrap">';
        echo '<h1>' . \esc_html(Core_Strings::get('plugin_name')) . '</h1>';

        $this->renderStatusNotice();

        if ($this->shouldShowFirstRunNotice()) {
            echo '<div class="notice notice-info inline" style="margin:5px 0 15px;">';
            echo '<p>' . \esc_html(Core_Strings::get('indexing_first_run_note')) . '</p>';
            echo '</div>';
        }

        echo '<form method="post" action="options.php">';
        \settings_fields(self::SETTINGS_GROUP);

        echo '<table class="form-table" role="presentation">';

                   
        echo '<tr>';
        echo '<th scope="row">' . \esc_html(Core_Strings::get('enable_label')) . '</th>';
        echo '<td>';
        echo '<label for="sole_ss_enabled">';
                                                                                            
        echo '<input type="checkbox" name="sole_ss_enabled" id="sole_ss_enabled" value="1" ' . \checked('1', $enabled, false) . ' /> ';
        echo \esc_html(Core_Strings::get('enable_description'));
        echo '</label>';
        echo '</td>';
        echo '</tr>';

                            
        echo '<tr>';
        echo '<th scope="row"><label for="sole_ss_results_per_page">' . \esc_html(Core_Strings::get('results_label')) . '</label></th>';
        echo '<td>';
        echo '<input type="number" min="1" max="50" name="sole_ss_results_per_page" id="sole_ss_results_per_page" value="' .
            \esc_attr((string) $resultsPerPage) .
            '" class="small-text" />';
        echo '<p class="description">' . \esc_html(Core_Strings::get('results_description')) . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';

        \submit_button();

        echo '</form>';

        $this->diagnostics->render();

        echo '</div>';
    }

    private function shouldShowFirstRunNotice(): bool {
        $value = \get_option(ACTIVATED_AT_OPTION);
        if (!\is_int($value) && !(\is_string($value) && \ctype_digit($value))) {
            return false;
        }

        $age = \time() - (int) $value;
        return $age >= 0 && $age < FIRST_RUN_NOTICE_LIFETIME_SECONDS;
    }

    private function renderStatusNotice(): void {
        $enabled = \get_option('sole_ss_enabled', '1');
        if (\is_string($enabled) && $enabled !== '1') {
            echo '<div class="notice notice-info inline" style="margin:5px 0 15px;">';
            echo '<p>' . \esc_html(Core_Strings::get('status_plugin_disabled')) . '</p>';
            echo '</div>';
            return;
        }

        if (!\function_exists('sole_engine_semantic')) {
            echo '<div class="notice notice-warning inline" style="margin:5px 0 15px;">';
            echo '<p>' . \esc_html(Core_Strings::get('status_no_engine')) . '</p>';
            echo '</div>';
            return;
        }

        if (!\function_exists('sole_engine_semantic_enabled')) {
            echo '<div class="notice notice-warning inline" style="margin:5px 0 15px;">';
            echo '<p>' . \esc_html(Core_Strings::get('status_engine_update_required')) . '</p>';
            echo '</div>';
            return;
        }

        if (!\sole_engine_semantic_enabled()) {
            echo '<div class="notice notice-warning inline" style="margin:5px 0 15px;">';
            echo '<p>' . \esc_html(Core_Strings::get('status_disabled')) . '</p>';
            echo '</div>';
            return;
        }

        $credentialIssue = Search_CredentialState::getIssue();
        if ($credentialIssue !== null) {
            echo '<div class="notice notice-warning inline" style="margin:5px 0 15px;">';
            echo '<p>' . \esc_html(Core_Strings::get('status_' . $credentialIssue)) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="notice notice-success inline" style="margin:5px 0 15px;">';
        echo '<p>' . \esc_html(Core_Strings::get('status_active')) . '</p>';
        echo '</div>';
    }
}


final class Routines_DiagnosticOutcome implements Routines_DiagnosticOutcomeInterface {
                                                                                                        
    public function __construct(
        private ?string $siteId,
        private ?bool $validReply,
        private ?int $coordinateCount,
        private float $elapsed,
        private int $returned,
        private bool $fellBack,
        private string $reasonKey,
        private array $discarded,
        private ?string $sentQuery,
        private string $credentialBefore,
        private ?string $credentialAfter,
        private array $traceRows
    ) {}

    public function getSiteId(): ?string { return $this->siteId; }
    public function getValidReply(): ?bool { return $this->validReply; }
    public function getCoordinateCount(): ?int { return $this->coordinateCount; }
    public function getElapsed(): float { return $this->elapsed; }
    public function getReturned(): int { return $this->returned; }
    public function fellBack(): bool { return $this->fellBack; }
    public function getReasonKey(): string { return $this->reasonKey; }
    public function getDiscarded(): array { return $this->discarded; }
    public function getSentQuery(): ?string { return $this->sentQuery; }
    public function getCredentialBefore(): string { return $this->credentialBefore; }
    public function getCredentialAfter(): ?string { return $this->credentialAfter; }
    public function getTraceRows(): array { return $this->traceRows; }
}

                                                                        
                                                             
  
                                                                             
                                                                         
                                                                              
                                                                             
                                                                            
                                                               
  
                                                                           
                                                                               
                                                                              
                                                      
final class Routines_Diagnostics implements Routines_DiagnosticsPanelInterface {
    public const NONCE_ACTION = 'sole_ss_self_test';
    public const NONCE_FIELD = 'sole_ss_self_test_nonce';
    public const QUERY_FIELD = 'sole_ss_self_test_query';

    private Search_ServiceInterface $searchService;
    private Routines_SiteIdentityInterface $siteIdentity;

    public function __construct(
        ?Search_ServiceInterface $searchService = null,
        ?Routines_SiteIdentityInterface $siteIdentity = null
    ) {
        $this->searchService = $searchService ?? new Search_Service();
        $this->siteIdentity = $siteIdentity ?? new Routines_EngineSiteIdentity();
    }

    public function render(): void {
        if (!\current_user_can('manage_options')) {
            return;
        }

        echo '<hr />';
        echo '<h2>' . \esc_html(Core_Strings::get('diag_heading')) . '</h2>';
        echo '<p class="description">' . \esc_html(Core_Strings::get('diag_intro')) . '</p>';

        $submittedQuery = $this->submittedQuery();

        echo '<form method="post">';
        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<p>';
        echo '<label for="' . \esc_attr(self::QUERY_FIELD) . '">' .
            \esc_html(Core_Strings::get('diag_query_label')) . ' </label>';
        echo '<input type="text" class="regular-text" name="' . \esc_attr(self::QUERY_FIELD) .
            '" id="' . \esc_attr(self::QUERY_FIELD) . '" value="" /> ';
        echo '<button type="submit" class="button">' .
            \esc_html(Core_Strings::get('diag_run_button')) . '</button>';
        echo '</p>';
        echo '<p class="description">' . \esc_html(Core_Strings::get('diag_refresh_warning')) . '</p>';
        echo '</form>';

        if ($submittedQuery === null) {
            return;
        }

        if (\trim($submittedQuery) === '') {
            echo '<div class="notice notice-warning inline" style="margin:5px 0 15px;"><p>' .
                \esc_html(Core_Strings::get('diag_no_query')) . '</p></div>';
            return;
        }

        $this->renderOutcome($this->runSelfTest(\trim($submittedQuery)));
    }

       
                                                                         
                                                                            
                                                                           
                                  
       
    private function submittedQuery(): ?string {
        if (!isset($_POST[self::QUERY_FIELD], $_POST[self::NONCE_FIELD])) {
            return null;
        }

        if (!\is_string($_POST[self::QUERY_FIELD]) || !\is_string($_POST[self::NONCE_FIELD])) {
            return null;
        }

        $nonce = \sanitize_text_field(\wp_unslash((string) $_POST[self::NONCE_FIELD]));
        if (!\wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return null;
        }

        return \sanitize_text_field(\wp_unslash((string) $_POST[self::QUERY_FIELD]));
    }

       
                                                       
      
                                                                             
                                                                               
                                                                            
                                                                            
                                                                         
      
       
    private function runSelfTest(string $query): Routines_DiagnosticOutcomeInterface {
        $siteId = $this->siteIdentity->resolve();
        $credentialBefore = Search_CredentialState::getState();
        $enabled = \get_option('sole_ss_enabled', '1');
        if (\is_string($enabled) && $enabled !== '1') {
            return $this->outcome($siteId, false, null, 0.0, 0, true, 'diag_reason_off', [], null, $credentialBefore);
        }

        if (!\function_exists('sole_engine_semantic')) {
            return $this->outcome($siteId, false, null, 0.0, 0, true, 'diag_reason_engine', [], null, $credentialBefore);
        }

        if (!\function_exists('sole_engine_semantic_enabled')) {
            return $this->outcome($siteId, false, null, 0.0, 0, true, 'diag_fail_engine_update_required', [], null, $credentialBefore);
        }

        if (!\sole_engine_semantic_enabled()) {
            return $this->outcome($siteId, false, null, 0.0, 0, true, 'diag_reason_disabled', [], null, $credentialBefore);
        }

        $credentialIssue = Search_CredentialState::getIssueForState($credentialBefore);
        if ($credentialIssue === Search_FailureReason::CONFIG_MISSING
            || $credentialIssue === Search_FailureReason::ENGINE_UPDATE_REQUIRED) {
            return $this->outcome($siteId, false, null, 0.0, 0, true, 'diag_fail_' . $credentialIssue, [], null, $credentialBefore);
        }

                                                                              
                                                                              
                                                              
        if ($credentialIssue === null && !$this->searchService->isAvailable()) {
            return $this->outcome($siteId, false, null, 0.0, 0, true, 'diag_reason_disabled', [], null, $credentialBefore);
        }

        $sentQuery = $query;
        $startedAt = \microtime(true);
        $result = $this->searchService->search($sentQuery, 50);
        $elapsed = \microtime(true) - $startedAt;
        $credentialAfter = Search_CredentialState::getState();

        $discarded = $result->getDiscardedHits();
        $validReply = $result instanceof Search_DiagnosticResultInterface
            ? $result->hasValidReply()
            : null;
        $coordinateCount = $result instanceof Search_DiagnosticResultInterface
            ? $result->getCoordinateCount()
            : null;

        if (!$result->isSuccessful()) {
                                                                            
                                                                      
            $reason = $result->getFailureReason();
            $key = $reason === null ? 'diag_reason_failed' : 'diag_fail_' . $reason;

            return $this->outcome(
                $siteId,
                $validReply,
                $coordinateCount,
                $elapsed,
                0,
                true,
                $key,
                $discarded,
                $sentQuery,
                $credentialBefore,
                $credentialAfter
            );
        }

        $postIds = $result->getPostIds();
        if ($postIds === []) {
            return $this->outcome(
                $siteId,
                $validReply,
                $coordinateCount,
                $elapsed,
                0,
                true,
                'diag_reason_empty',
                $discarded,
                $sentQuery,
                $credentialBefore,
                $credentialAfter
            );
        }

        $traceRows = [];
        $position = 1;
        foreach ($postIds as $postId) {
            $score = $result->getScoreForPost($postId);
            $traceRows[] = new Routines_DiagnosticTraceRow(
                $position++,
                $postId,
                \is_finite($score ?? NAN) ? $score : null
            );
        }

        return $this->outcome(
            $siteId,
            $validReply,
            $coordinateCount,
            $elapsed,
            \count($postIds),
            false,
            'diag_ok',
            $discarded,
            $sentQuery,
            $credentialBefore,
            $credentialAfter,
            $traceRows
        );
    }

       
                                           
                                                               
       
    private function outcome(
        ?string $siteId,
        ?bool $validReply,
        ?int $coordinateCount,
        float $elapsed,
        int $returned,
        bool $fellBack,
        string $reasonKey,
        array $discarded,
        ?string $sentQuery,
        string $credentialBefore,
        ?string $credentialAfter = null,
        array $traceRows = []
    ): Routines_DiagnosticOutcomeInterface {
        return new Routines_DiagnosticOutcome(
            $siteId,
            $validReply,
            $coordinateCount,
            $elapsed,
            $returned,
            $fellBack,
            $reasonKey,
            $discarded,
            $sentQuery,
            $credentialBefore,
            $credentialAfter,
            $traceRows
        );
    }

    private function renderOutcome(Routines_DiagnosticOutcomeInterface $outcome): void {
        $noticeClass = $outcome->fellBack() ? 'notice-warning' : 'notice-success';

        echo '<div class="notice ' . \esc_attr($noticeClass) . ' inline" style="margin:5px 0 15px;">';
        echo '<p><strong>' . \esc_html(Core_Strings::get('diag_result_heading')) . '</strong></p>';
        echo '<table class="widefat striped" style="max-width:44em;margin-bottom:10px;"><tbody>';

        $this->renderRow(
            Core_Strings::get('diag_row_site_id'),
            $outcome->getSiteId() ?? Core_Strings::get('diag_site_id_unavailable')
        );
        $this->renderRow(Core_Strings::get('diag_row_credential_before'), $this->credentialLabel($outcome->getCredentialBefore()));
        $this->renderRow(
            Core_Strings::get('diag_row_credential_after'),
            $outcome->getCredentialAfter() === null
                ? Core_Strings::get('diag_not_tested')
                : $this->credentialLabel($outcome->getCredentialAfter())
        );
        $this->renderRow(
            Core_Strings::get('diag_row_sent_query'),
            $outcome->getSentQuery() ?? Core_Strings::get('diag_query_not_sent')
        );
        $this->renderRow(
            Core_Strings::get('diag_row_call_succeeded'),
            $outcome->getValidReply() === null
                ? Core_Strings::get('diag_metadata_unavailable')
                : Core_Strings::get($outcome->getValidReply() ? 'diag_yes' : 'diag_no')
        );
        if ($outcome->getValidReply() === null) {
            $coordinateDescription = Core_Strings::get('diag_metadata_unavailable');
        } elseif (!$outcome->getValidReply()) {
            $coordinateDescription = Core_Strings::get('diag_coordinates_no_valid_reply');
        } elseif ($outcome->getCoordinateCount() === null) {
            $coordinateDescription = Core_Strings::get('diag_coordinates_unavailable');
        } else {
            $coordinateDescription = (string) $outcome->getCoordinateCount();
        }
        $this->renderRow(Core_Strings::get('diag_row_coordinate_count'), $coordinateDescription);
        $this->renderRow(
            Core_Strings::get('diag_row_elapsed'),
            \sprintf(Core_Strings::get('diag_seconds_format'), \number_format($outcome->getElapsed(), 2))
        );
        $this->renderRow(Core_Strings::get('diag_row_returned'), (string) $outcome->getReturned());
        $this->renderRow(
            Core_Strings::get('diag_row_fellback'),
            Core_Strings::get($outcome->fellBack() ? 'diag_yes' : 'diag_no')
        );
        $this->renderRow(Core_Strings::get('diag_row_reason'), Core_Strings::get($outcome->getReasonKey()));
        $this->renderRow(Core_Strings::get('diag_row_discarded'), $this->describeDiscards($outcome->getDiscarded()));

        echo '</tbody></table>';
        $this->renderTrace($outcome->getTraceRows());
        echo '<p class="description">' . \esc_html(Core_Strings::get('diag_engine_facts')) . '</p>';
        echo '</div>';
    }

    private function credentialLabel(string $state): string {
        $key = 'diag_credential_' . $state;
        $label = Core_Strings::get($key);
        return $label === $key ? Core_Strings::get('diag_credential_unrecognized') : $label;
    }

                                                                   
    private function renderTrace(array $traceRows): void {
        if ($traceRows === []) {
            return;
        }

        echo '<h3>' . \esc_html(Core_Strings::get('diag_trace_heading')) . '</h3>';
        echo '<table class="widefat striped" style="max-width:44em;margin-bottom:10px;">';
        echo '<thead><tr><th>' . \esc_html(Core_Strings::get('diag_trace_position')) . '</th>';
        echo '<th>' . \esc_html(Core_Strings::get('diag_trace_entity_id')) . '</th>';
        echo '<th>' . \esc_html(Core_Strings::get('diag_trace_score')) . '</th></tr></thead><tbody>';
        foreach ($traceRows as $row) {
            echo '<tr><td>' . \esc_html((string) $row->getPosition()) . '</td>';
            echo '<td>' . \esc_html((string) $row->getEntityId()) . '</td>';
            echo '<td>' . \esc_html($this->formatScore($row->getScore())) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description">' . \esc_html(Core_Strings::get('diag_trace_caveat')) . '</p>';
        echo '<p class="description">' . \esc_html(Core_Strings::get('diag_refresh_warning')) . '</p>';
    }

    private function formatScore(?float $score): string {
        if ($score === null || !\is_finite($score)) {
            return Core_Strings::get('diag_score_unavailable');
        }

        $encoded = \json_encode($score, JSON_PRESERVE_ZERO_FRACTION);
        return \is_string($encoded) ? $encoded : Core_Strings::get('diag_score_unavailable');
    }

       
                                                                              
                                       
      
                                           
       
    private function describeDiscards(array $discarded): string {
        $discarded = \array_filter($discarded);
        if ($discarded === []) {
            return Core_Strings::get('diag_discarded_none');
        }

        $parts = [];
        foreach ($discarded as $reason => $count) {
            $key = 'diag_discard_' . $reason;
            $label = Core_Strings::get($key);
                                                                          
            $parts[] = $label === $key
                ? \sprintf('%s: %d', $reason, $count)
                : \sprintf($label, $count);
        }

        return \implode(' | ', $parts);
    }

    private function renderRow(string $label, string $value): void {
        echo '<tr><th scope="row" style="width:16em;">' . \esc_html($label) . '</th>';
        echo '<td>' . \esc_html($value) . '</td></tr>';
    }
}

final class Routines_DiagnosticTraceRow implements Routines_DiagnosticTraceRowInterface {
    public function __construct(
        private int $position,
        private int $entityId,
        private ?float $score
    ) {}

    public function getPosition(): int { return $this->position; }
    public function getEntityId(): int { return $this->entityId; }
    public function getScore(): ?float { return $this->score; }
}

final class Routines_EngineSiteIdentity implements Routines_SiteIdentityInterface {
    public function resolve(): ?string {
        if (!\function_exists('sole_engine_semantic')) {
            return null;
        }

        try {
            $semantic = \sole_engine_semantic();
            if (!\method_exists($semantic, 'getSiteId')) {
                return null;
            }

            $siteId = $semantic->{'getSiteId'}();
        } catch (\Throwable $error) {
            return null;
        }

        if (!\is_string($siteId)) {
            return null;
        }

        $siteId = \trim($siteId);
        return $siteId !== '' ? $siteId : null;
    }
}

                                                                             
final class Routines_TranslationMapCodec implements Routines_TranslationMapCodecInterface {
    public function encode(array $strings): array {
        $wire = new \stdClass();
        $keyMap = [];
        $skipped = [];
        $index = 0;

        foreach ($strings as $key => $value) {
            $key = (string) $key;
            if (!\is_string($value) || $value === '' || \trim($value) !== $value) {
                $skipped[] = $key;
                continue;
            }

            $wireKey = (string) $index++;
            $wire->{$wireKey} = $value;
            $keyMap[] = $key;
        }

        $payload = \json_encode($wire, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'payload' => \is_string($payload) ? $payload : '{}',
            'keyMap' => $keyMap,
            'skipped' => $skipped,
        ];
    }

    public function decode(string $reply, array $keyMap, array $sourceStrings): array {
        $translated = [];
        $failed = \array_values($keyMap);
        $decoded = \json_decode($reply);

        if (!$decoded instanceof \stdClass) {
            return ['translated' => [], 'failed' => $failed];
        }

        foreach (\get_object_vars($decoded) as $wireKey => $value) {
            $wireKey = (string) $wireKey;
            if (!\preg_match('/^(0|[1-9][0-9]*)$/D', $wireKey)
                || !\array_key_exists((int) $wireKey, $keyMap)
                || !\is_string($value)) {
                continue;
            }

            $sourceKey = $keyMap[(int) $wireKey];
            $source = $sourceStrings[$sourceKey] ?? null;
            if (!\is_string($source) || !Core_Strings::isTranslationCompatible($source, $value)) {
                continue;
            }

            $translated[$sourceKey] = $value;
            $failed = \array_values(\array_diff($failed, [$sourceKey]));
        }

        return ['translated' => $translated, 'failed' => $failed];
    }
}

                                                                         
final class Routines_AutoTranslate implements Routines_RoutineInterface {
    private const OPTION_KEY = Core_Strings::TRANSLATION_STATE_OPTION;
    public const CRON_HOOK = TRANSLATION_CRON_HOOK;
    private const NEXT_STEP_DELAY = 15;

       
                                                                         
                                                                              
                                                                             
                                                                          
       
    private const MAX_ATTEMPTS = 3;

                                                                               
    private const STALE_AFTER_SECONDS = 600;

    private string $dir;
    private Routines_TranslationMapCodecInterface $codec;

    public function __construct(
        ?string $dir = null,
        ?Routines_TranslationMapCodecInterface $codec = null
    ) {
        $this->dir = $dir ?? __DIR__;
        $this->codec = $codec ?? new Routines_TranslationMapCodec();
    }

    public function execute(): void {
        \add_action('admin_init', [$this, 'maybeTranslate']);
        \add_action(self::CRON_HOOK, [$this, 'runTranslationStep']);
    }

       
                                                                              
      
                                                                            
                                                                         
                                                                            
                                                                        
                                                                          
                                                                           
                                                                
       
    public function maybeTranslate(): void {
        if (!\is_admin() || !\current_user_can('manage_options')) {
            return;
        }

        if ($this->translationNeeded()) {
            $this->scheduleNextStep(1);
        }
    }

                                                                            
    public function runTranslationStep(): void {
        $locale = \get_locale();

        if (\str_starts_with($locale, 'en')) {
            return;
        }

        if (!\function_exists('sole_engine_llm')) {
            return;
        }

        $strings = $this->loadStrings();
        if ($strings === []) {
            return;
        }
        $version = self::versionOf($strings);

        $option = \get_option(self::OPTION_KEY);
        $option = \is_array($option) ? $option : null;

                                                                              
                                                                             
                                                                               
        if ($option !== null
            && (($option['locale'] ?? '') !== $locale || ($option['version'] ?? '') !== $version)) {
            \delete_option(self::OPTION_KEY);
            \delete_option(Core_Strings::TRANSLATED_OPTION);
            $option = null;
        }

        $status = $option['status'] ?? '';

                                                                             
                                                                             
                                                                  
        if ($status === 'complete' || $status === 'given_up') {
            return;
        }

        if ($status === 'pending') {
            if (!$this->hasValidPendingState($option, $strings)) {
                \update_option(self::OPTION_KEY, [
                    'status' => 'partial',
                    'locale' => $locale,
                    'version' => $version,
                    'attempts' => (int) ($option['attempts'] ?? 0),
                    'failed' => \array_keys($strings),
                    'skipped' => $this->stringList($option['skipped'] ?? []),
                ]);
                $this->scheduleNextStep();
                return;
            }

            if ((int) ($option['submitted_at'] ?? 0) > \time() - self::STALE_AFTER_SECONDS) {
                $this->collectTranslations($locale, $version, $strings, $option);
                return;
            }
                                                                            
            $this->submitTranslations($locale, $version, $strings, $option);
            return;
        }

                                                                           
        if ($status === 'partial') {
            $retry = $this->validStringKeys($option['failed'] ?? [], $strings);
            if ($retry === []) {
                $retry = \array_keys($strings);
            }
            $subset = \array_intersect_key($strings, \array_flip($retry));
            $this->submitTranslations($locale, $version, $subset, $option);
            return;
        }

        $this->submitTranslations($locale, $version, $strings, $option);
    }

    private function translationNeeded(): bool {
        $locale = \get_locale();
        if (\str_starts_with($locale, 'en') || !\function_exists('sole_engine_llm')) {
            return false;
        }

        $strings = $this->loadStrings();
        if ($strings === []) {
            return false;
        }

        $option = \get_option(self::OPTION_KEY);
        if (!\is_array($option)) {
            return true;
        }

        if (($option['locale'] ?? '') !== $locale
            || ($option['version'] ?? '') !== self::versionOf($strings)) {
            return true;
        }

        return !\in_array($option['status'] ?? '', ['complete', 'given_up'], true);
    }

    private function scheduleNextStep(int $delay = self::NEXT_STEP_DELAY): void {
        if (\wp_next_scheduled(self::CRON_HOOK) !== false) {
            return;
        }

        \wp_schedule_single_event(\time() + $delay, self::CRON_HOOK);
    }

       
                                          
                                           
       
    private function hasValidPendingState(array $option, array $strings): bool {
        if (!isset($option['job_id'], $option['key_map'])
            || !\is_string($option['job_id'])
            || $option['job_id'] === ''
            || !\is_array($option['key_map'])
            || !\array_is_list($option['key_map'])
            || $option['key_map'] === []) {
            return false;
        }

        return \count($this->validStringKeys($option['key_map'], $strings)) === \count($option['key_map']);
    }

       
                          
                                           
                       
       
    private function validStringKeys($value, array $strings): array {
        if (!\is_array($value)) {
            return [];
        }

        $keys = [];
        foreach ($value as $key) {
            if (\is_string($key) && \array_key_exists($key, $strings)) {
                $keys[] = $key;
            }
        }

        return \array_values(\array_unique($keys));
    }

       
                                       
      
                                                                            
                                                                             
                                                                             
                                                                          
                                             
      
                                            
       
    public static function versionOf(array $strings): string {
        return \md5(\serialize($strings));
    }

                                        
    private function loadStrings(): array {
        $stringsFile = $this->dir . '/strings.php';
        if (!\is_file($stringsFile)) {
            return [];
        }

                                                  
        $strings = (array) include $stringsFile;

        return $strings;
    }

       
                                            
                                                 
       
    private function submitTranslations(string $locale, string $version, array $strings, ?array $previous = null): void {
        $attempts = (int) ($previous['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
                                                                               
                                                                              
            \update_option(self::OPTION_KEY, [
                'status' => 'given_up',
                'locale' => $locale,
                'version' => $version,
                'attempts' => $attempts,
                'failed' => $this->stringList($previous['failed'] ?? []),
                'skipped' => $this->stringList($previous['skipped'] ?? []),
            ]);
            return;
        }

        $lang = \str_replace('_', '-', $locale);
        $encoded = $this->codec->encode($strings);
        $skipped = \array_values(\array_unique(\array_merge(
            $this->stringList($previous['skipped'] ?? []),
            $encoded['skipped']
        )));

        if ($encoded['keyMap'] === []) {
            \update_option(self::OPTION_KEY, [
                'status' => 'complete',
                'locale' => $locale,
                'version' => $version,
                'attempts' => $attempts,
                'skipped' => $skipped,
            ]);
            return;
        }

        try {
            $llm = \sole_engine_llm();
                                                                                     
            $ticket = $llm->submit(
                'sole-semantic-search',
                'translate_batch',
                $encoded['payload'],
                ['targetLanguage' => $lang]
            );
            $jobId = $ticket->getJobId();
        } catch (\Throwable) {
            $this->storeSubmissionFailure(
                $locale,
                $version,
                $attempts + 1,
                \array_values($encoded['keyMap']),
                $skipped
            );
            return;
        }

        if (!$ticket->wasAccepted() || !\is_string($jobId) || $jobId === '') {
            $this->storeSubmissionFailure(
                $locale,
                $version,
                $attempts + 1,
                \array_values($encoded['keyMap']),
                $skipped
            );
            return;
        }

        \update_option(self::OPTION_KEY, [
            'status' => 'pending',
            'locale' => $locale,
            'version' => $version,
            'job_id' => $jobId,
            'key_map' => $encoded['keyMap'],
            'submitted_at' => \time(),
            'attempts' => $attempts + 1,
            'failed' => [],
            'skipped' => $skipped,
        ]);
        $this->scheduleNextStep();
    }

       
                              
                               
       
    private function storeSubmissionFailure(
        string $locale,
        string $version,
        int $attempts,
        array $failed,
        array $skipped
    ): void {
        \update_option(self::OPTION_KEY, [
            'status' => $attempts >= self::MAX_ATTEMPTS ? 'given_up' : 'partial',
            'locale' => $locale,
            'version' => $version,
            'attempts' => $attempts,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);
        if ($attempts < self::MAX_ATTEMPTS) {
            $this->scheduleNextStep();
        }
    }

       
                                           
                                          
       
    private function collectTranslations(string $locale, string $version, array $strings, array $option): void {
                                    
        $keyMap = $option['key_map'];

        try {
            $llm = \sole_engine_llm();
            $reply = $llm->getResult($option['job_id']);
        } catch (\Throwable) {
            $this->storeCollectedResult($locale, $version, $option, [], \array_values($keyMap));
            return;
        }

        if ($reply->isPending()) {
            $this->scheduleNextStep();
            return;
        }

        $decoded = $reply->isSuccessful() && $reply->getReply() !== null
            ? $this->codec->decode($reply->getReply(), $keyMap, $strings)
            : ['translated' => [], 'failed' => \array_values($keyMap)];
        $this->storeCollectedResult(
            $locale,
            $version,
            $option,
            $decoded['translated'],
            $decoded['failed']
        );
    }

       
                                          
                                               
                              
       
    private function storeCollectedResult(
        string $locale,
        string $version,
        array $option,
        array $translated,
        array $failed
    ): void {

                                                                                
                                                                             
        if ($translated !== []) {
            $this->storeTranslations($translated);
            Core_Strings::reset();
            Core_Strings::load($this->dir);
        }

        $previousFailed = $this->stringList($option['failed'] ?? []);
        $failed = \array_values(\array_unique(\array_merge($previousFailed, $failed)));
        $skipped = $this->stringList($option['skipped'] ?? []);
        $attempts = (int) ($option['attempts'] ?? 1);

        if ($failed !== []) {
                                                                           
                                                                           
            \update_option(self::OPTION_KEY, [
                'status' => $attempts >= self::MAX_ATTEMPTS ? 'given_up' : 'partial',
                'locale' => $locale,
                'version' => $version,
                'attempts' => $attempts,
                'failed' => $failed,
                'skipped' => $skipped,
            ]);
            if ($attempts < self::MAX_ATTEMPTS) {
                $this->scheduleNextStep();
            }
            return;
        }

        \update_option(self::OPTION_KEY, [
            'status' => 'complete',
            'locale' => $locale,
            'version' => $version,
            'attempts' => $attempts,
            'skipped' => $skipped,
        ]);
    }

                                               
    private function stringList($value): array {
        return \is_array($value)
            ? \array_values(\array_unique(\array_filter($value, '\is_string')))
            : [];
    }

       
                                                               
      
                                                                          
                                                                             
                                                                            
      
                                               
       
    private function storeTranslations(array $translated): void {
        $existing = \get_option(Core_Strings::TRANSLATED_OPTION);
        $existing = \is_array($existing) ? $existing : [];

        Core_Strings::storeOverlay(\array_merge($existing, $translated));
    }
}

                                                                            
                                             
final class Routines_PluginActionLink implements Routines_RoutineInterface {
    private string $mainFile;

    public function __construct(?string $mainFile = null) {
        $this->mainFile = $mainFile ?? __DIR__ . '/sole-semantic-search.php';
    }

    public function execute(): void {
        \add_filter(
            'plugin_action_links_' . \plugin_basename($this->mainFile),
            [$this, 'addSettingsLink']
        );
    }

       
                                              
                                        
       
    public function addSettingsLink(array $links): array {
        $url = \admin_url('options-general.php?page=' . Routines_AdminSettings::MENU_SLUG);

        \array_unshift(
            $links,
            '<a href="' . \esc_url($url) . '">' . \esc_html(Core_Strings::get('settings_link')) . '</a>'
        );

        return $links;
    }
}

                                                                          
final class Routines_Main implements Routines_RoutineInterface {
    public function execute(): void {
                                                                            
        (new Routines_AdminNotice())->execute();
        (new Routines_AdminSettings())->execute();
        (new Routines_PluginActionLink())->execute();
        (new Routines_AutoTranslate())->execute();

        $enabled = \get_option('sole_ss_enabled', '1');
        if (\is_string($enabled) && $enabled !== '1') {
            return;
        }

        $searchService = new Search_Service();

        if (!$searchService->isAvailable()) {
            return;
        }

        (new Routines_SearchIntercept($searchService))->execute();
    }
}

                                                                      
final class Routines_SearchIntercept implements Routines_RoutineInterface {
    public const STYLE_HANDLE = 'sole-semantic-search-scores';
    public const STYLE_VERSION = '1.0.0';

    private Search_ServiceInterface $searchService;
    private ?Search_ResultInterface $lastResult = null;

    public function __construct(Search_ServiceInterface $searchService) {
        $this->searchService = $searchService;
    }

    public function execute(): void {
        \add_action('pre_get_posts', [$this, 'interceptSearch']);
        \add_filter('posts_search', [$this, 'suppressSearchSql'], 10, 2);
        if ($this->scoresEnabled()) {
            \add_filter('get_the_excerpt', [$this, 'filterExcerpt'], 10, 2);
            \add_action('wp_enqueue_scripts', [$this, 'enqueueScoreStyle']);
        }
    }

       
                                                                     
                                                                             
       
    public function interceptSearch(\WP_Query $query): void {
        if (!$query->is_main_query() || !$query->is_search() || \is_admin()) {
            return;
        }

        $searchTerm = $query->get('s');

        if (!\is_string($searchTerm) || $searchTerm === '' || \trim($searchTerm) === '') {
            return;
        }

        $result = $this->searchService->search($searchTerm, 50);

        if (!$result->isSuccessful() || $result->getPostIds() === []) {
            return;
        }

        $this->lastResult = $result;

        $resultsPerPage = \get_option('sole_ss_results_per_page', 10);
        if (!\is_numeric($resultsPerPage)) {
            $resultsPerPage = 10;
        }

        $query->set('sole_ss_active', true);
        $query->set('post__in', $result->getPostIds());
        $query->set('orderby', 'post__in');
        $query->set('posts_per_page', (int) $resultsPerPage);
    }

       
                                                                        
                                                                         
                                                                
                                                   
       
    public function suppressSearchSql(mixed $search, mixed $query): mixed {
        if (!\is_string($search) || !$query instanceof \WP_Query) {
            return $search;
        }

        if ($query->get('sole_ss_active')) {
            return '';
        }

        return $search;
    }

       
                                                                    
       
    public function filterExcerpt(mixed $excerpt, mixed $post): mixed {
        if (!\is_string($excerpt) || !$post instanceof \WP_Post) {
            return $excerpt;
        }
        if (!\is_search()) {
            return $excerpt;
        }

        if (!$this->scoresEnabled()) {
            return $excerpt;
        }

        if ($this->lastResult === null) {
            return $excerpt;
        }

        $score = $this->lastResult->getScoreForPost($post->ID);

        if ($score === null) {
            return $excerpt;
        }

        $percentage = (int) \round($score * 100);

        return '<span class="sole-ss-score">' .
            \esc_html(\sprintf(Core_Strings::get('relevance_format'), $percentage)) .
            '</span> ' . $excerpt;
    }

       
                                                                
       
    public function enqueueScoreStyle(): void {
        if (!\is_search() || !$this->scoresEnabled()) {
            return;
        }

        \wp_enqueue_style(
            self::STYLE_HANDLE,
            \plugins_url('score.css', __FILE__),
            [],
            self::STYLE_VERSION
        );
    }

    private function scoresEnabled(): bool {
        return \in_array(\get_option('sole_ss_show_scores', '0'), ['1', 1, true], true);
    }
}
