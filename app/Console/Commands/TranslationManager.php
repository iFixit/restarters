<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class TranslationManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:manage 
                            {action : The action to perform (extract, translate)}
                            {--api-key= : Your DeepL API key (required for translate action)}
                            {--instance=base : The instance to use (base, fixitclinic, etc.)}
                            {--source-locale=en : The source locale for extraction}
                            {--target-locales= : Comma-separated target locales (e.g., fr,es,de)}
                            {--free : Use the free DeepL API endpoint}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage translation files - extract, translate via DeepL, and generate locale files';

    private $baseLocalePath;
    private $instanceName;
    private $htmlTagPlaceholders = [];
    private $placeholderCounter = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->instanceName = $this->option('instance');
        $this->baseLocalePath = base_path('lang/instances/' . $this->instanceName);
        
        $this->line("<info>🌐 Translation Manager</info> <comment>({$this->instanceName})</comment>");

        if (!is_dir($this->baseLocalePath)) {
            $this->error("Instance directory does not exist: {$this->baseLocalePath}");
            return 1;
        }

        $action = $this->argument('action');

        try {
            switch ($action) {
                case 'extract':
                    return $this->handleExtract();
                    
                case 'translate':
                    return $this->handleTranslate();
                default:
                    $this->error("Unknown action: {$action}");
                    $this->info("Available actions: extract, translate");
                    return 1;
            }
        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }

    private function handleExtract(): int
    {
        $locale = $this->option('source-locale');
        
        $this->line("<info>📁 Extracting translations</info> <comment>({$locale})</comment>");
        
        $translations = $this->extractTranslations($locale);
        $filename = 'extracted_translations.json';
        
        file_put_contents($filename, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->line("<info>✅ Extracted " . count($translations) . " files</info>");
        
        return 0;
    }

    private function handleTranslate(): int
    {
        $apiKey = $this->option('api-key');
        if (!$apiKey) {
            $this->error("API key is required for translate action");
            return 1;
        }

        $targetLocalesString = $this->option('target-locales');
        if (!$targetLocalesString) {
            $this->error("--target-locales option is required (e.g., --target-locales=fr,es,de)");
            return 1;
        }

        $targetLocales = array_map('trim', explode(',', $targetLocalesString));
        $sourceLocale = $this->option('source-locale');
        $useFreeApi = $this->option('free');

        $this->line("<info>🚀 Translation Pipeline</info> <comment>{$sourceLocale} → " . implode(', ', $targetLocales) . "</comment>");

        // Step 1: Extract translations
        $exitCode = $this->handleExtractStep();
        if ($exitCode !== 0) {
            return $exitCode;
        }

        // Show summary
        if (file_exists('extracted_translations.json')) {
            $extractedData = json_decode(file_get_contents('extracted_translations.json'), true);
            $totalTranslations = 0;
            foreach ($extractedData as $fileTranslations) {
                $totalTranslations += $this->countFlatTranslations($fileTranslations);
            }
            $this->line("<comment>📊 {$totalTranslations} strings from " . count($extractedData) . " files</comment>");
        }

        // Step 2: Translation processing
        $this->line("<info>🔄 Translating locales</info>");
        
        // Create progress bars
        $overallProgress = $this->output->createProgressBar(count($targetLocales));
        $overallProgress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $overallProgress->setMessage('Initializing...');
        $overallProgress->start();

        // Process each target locale
        foreach ($targetLocales as $targetLocale) {
            $overallProgress->setMessage("Processing {$targetLocale}");
            $overallProgress->display();
            
            // Export to DeepL format
            $inputFile = "deepl_input_{$targetLocale}.json";
            if (!$this->exportForDeepLToFile($inputFile)) {
                $overallProgress->clear();
                $this->error("Failed to export for DeepL");
                return 1;
            }

            // Translate with DeepL (silent mode)
            $outputFile = "deepl_output_{$targetLocale}.json";
            
            $exitCode = $this->call('translations:deepl', [
                'action' => 'translate',
                '--api-key' => $apiKey,
                '--input-file' => $inputFile,
                '--output-file' => $outputFile,
                '--target-lang' => strtoupper($targetLocale),
                '--source-lang' => strtoupper($sourceLocale),
                '--free' => $useFreeApi,
            ]);

            if ($exitCode !== 0) {
                $overallProgress->clear();
                $this->error("Failed to translate to {$targetLocale}");
                return 1;
            }

            // Import translated content
            if (!$this->importFromDeepLFile($outputFile, $targetLocale)) {
                $overallProgress->clear();
                $this->error("Failed to import translations for {$targetLocale}");
                return 1;
            }

            // Cleanup temp files for this locale
            @unlink($inputFile);
            @unlink($outputFile);

            $overallProgress->setMessage("Completed {$targetLocale}");
            $overallProgress->advance();
        }

        $overallProgress->setMessage('Complete');
        $overallProgress->finish();
        $this->line("");

        // Cleanup main temp file
        @unlink('extracted_translations.json');

        $this->line("<info>🎉 Translation completed</info> <comment>" . implode(', ', $targetLocales) . "</comment>");

        return 0;
    }

    private function handleExtractStep(): int
    {
        $locale = $this->option('source-locale');
        
        $translations = $this->extractTranslations($locale);
        $filename = 'extracted_translations.json';
        
        file_put_contents($filename, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return 0;
    }

    /**
     * Extract translations from PHP files
     */
    public function extractTranslations($locale = 'en')
    {
        $sourcePath = $this->baseLocalePath . '/' . $locale;
        
        if (!is_dir($sourcePath)) {
            throw new Exception("Source locale directory not found: {$sourcePath}");
        }
        
        $translations = [];
        $files = scandir($sourcePath);
        
        // Get PHP files for progress tracking
        $phpFiles = array_filter($files, function($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'php';
        });
        
        if (!empty($phpFiles)) {
            foreach ($phpFiles as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                
                $filePath = $sourcePath . '/' . $file;
                $fileTranslations = include $filePath;
                
                if (is_array($fileTranslations)) {
                    $translations[$filename] = $this->processTranslationsForDeepL($fileTranslations);
                }
            }
        }
        
        return $translations;
    }

    /**
     * Process translations for DeepL - replace HTML tags with placeholders
     */
    private function processTranslationsForDeepL($translations, $prefix = '')
    {
        $processed = [];
        
        foreach ($translations as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                // Recursively process nested arrays
                $processed[$key] = $this->processTranslationsForDeepL($value, $fullKey);
            } else if (is_string($value)) {
                $processed[$key] = $this->processSingleTranslation($value);
            } else {
                // Keep non-string values as-is
                $processed[$key] = $value;
            }
        }
        
        return $processed;
    }

    /**
     * Process a single translation string
     */
    private function processSingleTranslation($text)
    {
        $originalText = $text;
        $htmlPlaceholders = [];
        $laravelPlaceholders = [];
        
        // Find Laravel placeholders like :name, :count
        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $text, $laravelMatches);
        $laravelPlaceholders = array_unique($laravelMatches[0]);
        
        // Find HTML tags
        preg_match_all('/<[^>]+>/', $text, $htmlMatches);
        $uniqueHtmlTags = array_unique($htmlMatches[0]);
        
        // Replace HTML tags with placeholders
        foreach ($uniqueHtmlTags as $htmlTag) {
            $placeholder = 'HTMLTAG_' . $this->placeholderCounter++;
            $htmlPlaceholders[$placeholder] = $htmlTag;
            
            // Add spaces around placeholder when adjacent to word characters for DeepL compatibility
            $placeholderPattern = '{{' . $placeholder . '}}';
            $replacement = $placeholderPattern;
            
            // Check if the HTML tag is adjacent to word characters and add spaces
            $text = preg_replace_callback(
                '/' . preg_quote($htmlTag, '/') . '/',
                function($matches) use ($placeholderPattern, $text, $htmlTag) {
                    $pos = strpos($text, $htmlTag);
                    $beforeChar = $pos > 0 ? $text[$pos - 1] : '';
                    $afterChar = $pos + strlen($htmlTag) < strlen($text) ? $text[$pos + strlen($htmlTag)] : '';
                    
                    $replacement = $placeholderPattern;
                    
                    // Add space before if preceded by word character
                    if (ctype_alnum($beforeChar)) {
                        $replacement = ' ' . $replacement;
                    }
                    
                    // Add space after if followed by word character
                    if (ctype_alnum($afterChar)) {
                        $replacement = $replacement . ' ';
                    }
                    
                    return $replacement;
                },
                $text,
                1 // Only replace first occurrence
            );
        }
        
        return [
            'original' => $originalText,
            'for_translation' => $text,
            'html_placeholders' => $htmlPlaceholders,
            'laravel_placeholders' => $laravelPlaceholders
        ];
    }

    /**
     * Export processed translations for DeepL API
     */
    public function exportForDeepL($translations, $filename)
    {
        $exportData = [];
        
        foreach ($translations as $file => $fileTranslations) {
            $this->flattenForDeepLExport($fileTranslations, $file, $exportData);
        }
        
        file_put_contents($filename, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Export to DeepL format using extracted translations file
     */
    private function exportForDeepLToFile($filename): bool
    {
        if (!file_exists('extracted_translations.json')) {
            $this->error("No extracted translations found. Run extract first.");
            return false;
        }

        $translations = json_decode(file_get_contents('extracted_translations.json'), true);
        if (!$translations) {
            $this->error("Invalid extracted translations file");
            return false;
        }

        $this->exportForDeepL($translations, $filename);
        return true;
    }

    /**
     * Import from DeepL file and generate locale files
     */
    private function importFromDeepLFile($filename, $targetLocale): bool
    {
        if (!file_exists($filename)) {
            $this->error("Translated file not found: {$filename}");
            return false;
        }

        $translatedData = json_decode(file_get_contents($filename), true);
        if (!$translatedData) {
            $this->error("Invalid JSON in translated file");
            return false;
        }

        $processedTranslations = $this->importFromDeepL($translatedData);
        $this->generateLocaleFiles($processedTranslations, $targetLocale);
        
        return true;
    }

    /**
     * Recursively flatten translations for DeepL export
     */
    private function flattenForDeepLExport($translations, $prefix, &$exportData)
    {
        foreach ($translations as $key => $data) {
            $identifier = $prefix . '.' . $key;
            
            if (is_array($data) && isset($data['for_translation'])) {
                $exportData[$identifier] = [
                    'text' => $data['for_translation'],
                    'html_placeholders' => $data['html_placeholders'] ?? [],
                    'laravel_placeholders' => $data['laravel_placeholders'] ?? []
                ];
            } elseif (is_array($data)) {
                $this->flattenForDeepLExport($data, $identifier, $exportData);
            }
        }
    }

    /**
     * Import translated content from DeepL format
     */
    public function importFromDeepL($translatedData)
    {
        $processedTranslations = [];
        
        foreach ($translatedData as $identifier => $data) {
            $parts = explode('.', $identifier);
            $filename = array_shift($parts);
            $key = implode('.', $parts);
            
            if (!isset($processedTranslations[$filename])) {
                $processedTranslations[$filename] = [];
            }
            
            // Restore HTML tags in translated text
            $translatedText = $data['translated_text'] ?? $data['text'];
            
            if (isset($data['html_placeholders'])) {
                foreach ($data['html_placeholders'] as $placeholder => $htmlTag) {
                    $placeholderPattern = "{{" . $placeholder . "}}";
                    
                    // Handle placeholders with spaces around them (added for DeepL compatibility)
                    $translatedText = str_replace(" " . $placeholderPattern . " ", $htmlTag, $translatedText);
                    $translatedText = str_replace(" " . $placeholderPattern, $htmlTag, $translatedText);
                    $translatedText = str_replace($placeholderPattern . " ", $htmlTag, $translatedText);
                    $translatedText = str_replace($placeholderPattern, $htmlTag, $translatedText);
                }
            }
            
            $this->setNestedArrayValue($processedTranslations[$filename], $key, $translatedText);
        }
        
        return $processedTranslations;
    }

    /**
     * Set value in nested array using dot notation
     */
    private function setNestedArrayValue(&$array, $key, $value)
    {
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }

    /**
     * Generate locale files for a target language
     */
    public function generateLocaleFiles($translations, $targetLocale, $outputDir = null)
    {
        // Output to the same instance folder structure
        $outputDir = $outputDir ?: base_path('lang/instances/' . $this->instanceName . '/' . $targetLocale);
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        foreach ($translations as $filename => $fileTranslations) {
            $outputFile = $outputDir . '/' . $filename . '.php';
            $this->generateSingleLocaleFile($fileTranslations, $outputFile);
        }
    }

    /**
     * Generate a single locale file
     */
    private function generateSingleLocaleFile($translations, $outputFile)
    {
        $content = "<?php\n\nreturn [\n";
        $content .= $this->arrayToPhpString($translations, 1);
        $content .= "];\n";
        
        file_put_contents($outputFile, $content);
    }

    /**
     * Convert array to PHP string representation
     */
    private function arrayToPhpString($array, $indent = 0)
    {
        $result = '';
        $indentStr = str_repeat('  ', $indent);
        
        foreach ($array as $key => $value) {
            $escapedKey = addslashes($key);
            
            if (is_array($value)) {
                $result .= "{$indentStr}'{$escapedKey}' => [\n";
                $result .= $this->arrayToPhpString($value, $indent + 1);
                $result .= "{$indentStr}],\n";
            } else {
                $escapedValue = addslashes($value);
                $result .= "{$indentStr}'{$escapedKey}' => '{$escapedValue}',\n";
            }
        }
        
        return $result;
    }

    /**
     * Count total flat translations in a nested array
     */
    private function countFlatTranslations($translations)
    {
        $count = 0;
        foreach ($translations as $value) {
            if (is_array($value) && isset($value['for_translation'])) {
                $count++;
            } elseif (is_array($value)) {
                $count += $this->countFlatTranslations($value);
            }
        }
        return $count;
    }
} 