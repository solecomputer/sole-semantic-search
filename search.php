<?php

declare(strict_types=1);

namespace SoleSemanticSearch;

                                                                       
                                                                            
                                                                             
                                                                          
                                
final class Search_FailureReason {
    public const EXCEPTION = 'exception';
    public const CONFIG_MISSING = 'config_missing';
    public const CREDENTIAL_UNVERIFIED = 'credential_unverified';
    public const USER_KEY_INVALID = 'user_key_invalid';
    public const ENGINE_UPDATE_REQUIRED = 'engine_update_required';
    public const SUBMIT_REJECTED = 'submit_rejected';
    public const POLL_TIMEOUT = 'poll_timeout';
    public const ENGINE_ERROR = 'engine_error';
    public const EMPTY_REPLY = 'empty_reply';
    public const MALFORMED_REPLY = 'malformed_reply';
    public const NO_USABLE_HITS = 'no_usable_hits';
}

                                                                               
                                                                               
                                                                  
final class Search_CredentialState {
    public const STATE_MISSING = 'missing';
    public const STATE_UNVERIFIED = 'unverified';
    public const STATE_INVALID = 'invalid';
    public const STATE_VERIFIED_READY = 'verified_ready';
    public const STATE_VERIFIED_NOT_READY = 'verified_not_ready';
    public const STATE_UNRECOGNIZED = 'unrecognized';
    public const STATE_UNAVAILABLE = 'unavailable';

    private const ENGINE_STATE_VERIFIED = 'verified';

    public static function getIssue(): ?string {
        return self::getIssueForState(self::getState());
    }

    public static function getIssueForState(string $state): ?string {
        if ($state === self::STATE_MISSING) {
            return Search_FailureReason::CONFIG_MISSING;
        }
        if ($state === self::STATE_UNVERIFIED) {
            return Search_FailureReason::CREDENTIAL_UNVERIFIED;
        }
        if ($state === self::STATE_INVALID) {
            return Search_FailureReason::USER_KEY_INVALID;
        }

        return $state === self::STATE_VERIFIED_READY
            ? null
            : Search_FailureReason::ENGINE_UPDATE_REQUIRED;
    }

    public static function getState(): string {
        $readiness = self::readiness();
        if ($readiness === null) {
            return self::STATE_UNAVAILABLE;
        }

        try {
            $state = $readiness->getState();
            $ready = $readiness->isReady();
        } catch (\Throwable $error) {
            return self::STATE_UNAVAILABLE;
        }

        if ($state === self::STATE_MISSING) {
            return self::STATE_MISSING;
        }
        if ($state === self::STATE_UNVERIFIED) {
            return self::STATE_UNVERIFIED;
        }
        if ($state === self::STATE_INVALID) {
            return self::STATE_INVALID;
        }
        if ($state === self::ENGINE_STATE_VERIFIED) {
            return $ready
                ? self::STATE_VERIFIED_READY
                : self::STATE_VERIFIED_NOT_READY;
        }

        return self::STATE_UNRECOGNIZED;
    }

    public static function isReady(): bool {
        return self::getIssue() === null;
    }

    private static function readiness(): ?\SoleEngineCredentialReadinessInterface {
        if (!\function_exists('sole_engine_credential_readiness')
            || !\interface_exists('SoleEngineCredentialReadinessInterface')) {
            return null;
        }

        try {
            $readiness = \sole_engine_credential_readiness();
        } catch (\Throwable $error) {
            return null;
        }

        return $readiness instanceof \SoleEngineCredentialReadinessInterface
            ? $readiness
            : null;
    }
}

                                                                       
                                                                        
final class Search_DiscardReason {
       
                                                   
      
                                                                      
                                                                     
                                                                            
                                                                             
                                                                             
                                                                               
                                                
       
    public const MISSING_POST_ID = 'missing_post_id';

                                                              
    public const REPEATED_PAGE = 'repeated_page';

       
                                                                             
      
                                                                           
                                                                        
                                                                           
                                                                                
                                                                        
                                                                        
       
    public const UNACCOUNTED = 'unaccounted';
}

                                       
final class Search_Result implements Search_ResultInterface, Search_DiagnosticResultInterface {
    private bool $successful;

                     
    private array $postIds;

                                 
    private array $scoreMap;

                                            
    private ?string $failureReason;

                                  
    private array $discardedHits;

    private bool $validReply;

    private ?int $coordinateCount;

       
                            
                                         
                                                         
                                               
       
    public function __construct(
        bool $successful,
        array $postIds,
        array $scoreMap,
        ?string $failureReason = null,
        array $discardedHits = [],
        ?bool $validReply = null,
        ?int $coordinateCount = null
    ) {
        $validReply = $validReply ?? $successful;

                                                                             
                                                                             
                                                                                
        if ($successful && $postIds === []) {
            throw new \InvalidArgumentException('A successful search result must contain a post ID.');
        }
        if ($successful && !$validReply) {
            throw new \InvalidArgumentException('A successful search result must come from a valid reply.');
        }
        if (!$validReply && $coordinateCount !== null) {
            throw new \InvalidArgumentException('An invalid reply cannot carry a coordinate count.');
        }
        if ($coordinateCount !== null && $coordinateCount < 0) {
            throw new \InvalidArgumentException('Coordinate count cannot be negative.');
        }

        $this->successful = $successful;
        $this->postIds = $postIds;
        $this->scoreMap = $scoreMap;
        $this->failureReason = $failureReason;
        $this->discardedHits = $discardedHits;
        $this->validReply = $validReply;
        $this->coordinateCount = $coordinateCount;
    }

    public function isSuccessful(): bool {
        return $this->successful;
    }

                        
    public function getPostIds(): array {
        return $this->postIds;
    }

    public function getScoreForPost(int $postId): ?float {
        return $this->scoreMap[$postId] ?? null;
    }

                                               
    public function getFailureReason(): ?string {
        return $this->failureReason;
    }

                                     
    public function getDiscardedHits(): array {
        return $this->discardedHits;
    }

    public function hasValidReply(): bool {
        return $this->validReply;
    }

    public function getCoordinateCount(): ?int {
        return $this->coordinateCount;
    }
}

                                                    
final class Search_Service implements Search_ServiceInterface {
    private const MAX_POLL_ATTEMPTS = 12;

       
                                             
      
                 
       
    private const POLL_DELAYS_US = [
        0,                              
        200000,                     
        200000,                     
        500000,                     
        500000,                     
        1000000,                     
        1000000,                     
        1000000,                     
        1000000,                     
        1000000,                      
        1000000,                      
        1000000,                      
    ];

    public function isAvailable(): bool {
        return \function_exists('sole_engine_semantic')
            && \function_exists('sole_engine_semantic_enabled')
            && \sole_engine_semantic_enabled()
            && Search_CredentialState::isReady();
    }

    public function search(string $query, int $topK): Search_ResultInterface {
                                                                  
        try {
            return $this->doSearch($query, $topK);
        } catch (\Throwable $e) {
            return new Search_Result(false, [], [], Search_FailureReason::EXCEPTION);
        }
    }

    private function doSearch(string $query, int $topK): Search_ResultInterface {
        $payload = \json_encode(['query' => $query, 'top_k' => $topK]);

        $ticket = \sole_engine_semantic()->submit('sole-semantic-search', 'search', $payload);

        if (!$ticket->wasAccepted()) {
            $reason = $this->failureReasonForEngineError($ticket->getError());
            return new Search_Result(false, [], [], $reason);
        }

        $jobId = $ticket->getJobId();
        $result = $this->poll($jobId);

        if ($result === null) {
            return new Search_Result(false, [], [], Search_FailureReason::POLL_TIMEOUT);
        }

        if (!$result->isSuccessful()) {
                                                                               
                                                                            
                                                
            $reason = $result->isPending()
                ? Search_FailureReason::POLL_TIMEOUT
                : $this->failureReasonForEngineError($result->getError(), Search_FailureReason::ENGINE_ERROR);

            return new Search_Result(false, [], [], $reason);
        }

        return $this->parseReply($result->getReply());
    }

       
                                              
                                      
       
    private function failureReasonForEngineError(
        ?string $errorCode,
        string $default = Search_FailureReason::SUBMIT_REJECTED
    ): string {
        if ($errorCode === Search_FailureReason::CONFIG_MISSING
            || $errorCode === Search_FailureReason::USER_KEY_INVALID) {
            return $errorCode;
        }

        return $default;
    }

       
                                                   
      
                                                   
       
    private function poll(string $jobId) {
        $semantic = \sole_engine_semantic();
        $lastResult = null;

        for ($i = 0; $i < self::MAX_POLL_ATTEMPTS; $i++) {
            if (self::POLL_DELAYS_US[$i] > 0) {
                \usleep(self::POLL_DELAYS_US[$i]);
            }

            $lastResult = $semantic->getResult($jobId);

            if (!$lastResult->isPending()) {
                return $lastResult;
            }
        }

        return $lastResult;
    }

    private function parseReply(?string $reply): Search_ResultInterface {
        if ($reply === null || $reply === '') {
            return new Search_Result(false, [], [], Search_FailureReason::EMPTY_REPLY);
        }

        $data = \json_decode($reply, true);

        if (!\is_array($data) || !isset($data['hits']) || !\is_array($data['hits'])) {
            return new Search_Result(false, [], [], Search_FailureReason::MALFORMED_REPLY);
        }

        $coordinateCount = isset($data['coordinate_count'])
            && \is_int($data['coordinate_count'])
            && $data['coordinate_count'] >= 0
                ? $data['coordinate_count']
                : null;

        $postIds = [];
        $scoreMap = [];
        $seenPostIds = [];
        $discarded = [];

        foreach ($data['hits'] as $hit) {
            if (!isset($hit['post_id'])) {
                $discarded[Search_DiscardReason::MISSING_POST_ID] =
                    ($discarded[Search_DiscardReason::MISSING_POST_ID] ?? 0) + 1;
                continue;
            }
            $postId = (int) $hit['post_id'];

                                                                              
                                                                              
                                                                             
                        
              
                                                                         
                                                                             
                                                                      
                                                                               
                                                                            
                                                                           
                                                                            
                                                                             
                                                                              
                                                               
              
                                                                             
                                                                               
                                                                            
                                           
            if (isset($seenPostIds[$postId])) {
                $discarded[Search_DiscardReason::REPEATED_PAGE] =
                    ($discarded[Search_DiscardReason::REPEATED_PAGE] ?? 0) + 1;
                continue;
            }
            $seenPostIds[$postId] = true;

            $postIds[] = $postId;
            if (isset($hit['score'])) {
                $scoreMap[$postId] = (float) $hit['score'];
            }
        }

                                                                        
                                                                        
                                                  
        $arrived = \count($data['hits']);
        $accounted = \count($postIds) + \array_sum($discarded);
        if ($accounted < $arrived) {
            $discarded[Search_DiscardReason::UNACCOUNTED] = $arrived - $accounted;
        }

        if ($postIds === []) {
            return new Search_Result(
                false,
                [],
                [],
                Search_FailureReason::NO_USABLE_HITS,
                $discarded,
                true,
                $coordinateCount
            );
        }

        return new Search_Result(true, $postIds, $scoreMap, null, $discarded, true, $coordinateCount);
    }
}
