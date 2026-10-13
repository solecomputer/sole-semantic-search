<?php

declare(strict_types=1);

namespace SoleSemanticSearch;

                       
interface Core_PluginInterface {
    public function boot(): void;
}

                                       
interface Search_ResultInterface {
    public function isSuccessful(): bool;

                        
    public function getPostIds(): array;

    public function getScoreForPost(int $postId): ?float;

       
                                                                         
      
                                                                           
                                                                              
                                                                            
                                   
      
                                           
       
    public function getFailureReason(): ?string;

       
                                                                        
                                                
      
                                                                            
                                                               
      
                                 
       
    public function getDiscardedHits(): array;
}

                                                                      
                                                                            
                                            
interface Search_DiagnosticResultInterface {
    public function hasValidReply(): bool;

    public function getCoordinateCount(): ?int;
}

                           
interface Search_ServiceInterface {
    public function isAvailable(): bool;

    public function search(string $query, int $topK): Search_ResultInterface;
}

                                             
interface Routines_RoutineInterface {
    public function execute(): void;
}

                                                                            
                                                                           
                                                                            
                         
interface Routines_DiagnosticsPanelInterface {
    public function render(): void;
}

interface Routines_SiteIdentityInterface {
    public function resolve(): ?string;
}

                                                                             
interface Routines_TranslationMapCodecInterface {
       
                                           
                                                                          
       
    public function encode(array $strings): array;

       
                              
                                                 
                                                                         
       
    public function decode(string $reply, array $keyMap, array $sourceStrings): array;
}
