<?php

declare(strict_types=1);

namespace SoleSemanticSearch;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/lifecycle.php';
require_once __DIR__ . '/contracts.php';
require_once __DIR__ . '/search.php';
require_once __DIR__ . '/routines.php';

                                                              
                                                                     
                                                                 
final class Core_Strings {
    public const TRANSLATED_OPTION = 'sole_ss_strings_translated';
    public const TRANSLATION_STATE_OPTION = 'sole_ss_translate';

                                     
    private static array $strings = [];
    private static bool $loaded = false;

    public static function load(string $dir): void {
        if (self::$loaded) {
            return;
        }

        $base = [];
        $source = $dir . '/strings.php';
        if (\is_file($source)) {
            $base = (array) include $source;
        }

        $translationState = \get_option(self::TRANSLATION_STATE_OPTION);
        $translated = \get_option(self::TRANSLATED_OPTION);
        $overlayApplies = \is_array($translationState)
            && ($translationState['locale'] ?? '') === \get_locale()
            && ($translationState['version'] ?? '') === \md5(\serialize($base));

        if ($overlayApplies && \is_array($translated)) {
            foreach ($translated as $key => $value) {
                if (!\is_string($key)
                    || !isset($base[$key])
                    || !\is_string($base[$key])
                    || !\is_string($value)
                    || !self::isTranslationCompatible($base[$key], $value)) {
                    continue;
                }
                $base[$key] = $value;
            }
        }

        self::$strings = $base;
        self::$loaded = true;
    }

    public static function get(string $key): string {
        return self::$strings[$key] ?? $key;
    }

       
                                                                      
                                 
      
                                               
       
    public static function storeOverlay(array $translated): void {
        \update_option(self::TRANSLATED_OPTION, $translated, 'yes');
    }

       
                                                                               
                                                                               
                                                                           
       
    public static function isTranslationCompatible(string $source, string $translation): bool {
        if ($translation === '' || \trim($translation) !== $translation) {
            return false;
        }

        return self::printfPlaceholders($source) === self::printfPlaceholders($translation);
    }

                           
    private static function printfPlaceholders(string $value): array {
        $matches = [];
        \preg_match_all(
            "/%(?:[1-9][0-9]*\\$)?[+\\-]?(?:[ 0]|'.)?[0-9]*(?:\\.[0-9]+)?[bcdeEfFgGosuxX]/",
            $value,
            $matches
        );
        $placeholders = $matches[0] ?? [];
        \sort($placeholders);

        return $placeholders;
    }

                                      
    public static function reset(): void {
        self::$strings = [];
        self::$loaded = false;
    }
}

                         
final class Core_Plugin implements Core_PluginInterface {
    private Routines_RoutineInterface $mainRoutine;

    public function __construct(?Routines_RoutineInterface $mainRoutine = null) {
        $this->mainRoutine = $mainRoutine ?? new Routines_Main();
    }

    public function boot(): void {
        Core_Strings::load(__DIR__);
        $this->mainRoutine->execute();
    }

    public static function bootstrap(): void {
        (new self())->boot();
    }
}
