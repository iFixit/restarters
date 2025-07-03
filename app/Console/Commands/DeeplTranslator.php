<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class DeeplTranslator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:deepl
                            {action : The action to perform (translate, languages, usage, test)}
                            {--api-key= : Your DeepL API key}
                            {--input-file= : Input JSON file with texts to translate}
                            {--output-file= : Output JSON file for translated results}
                            {--target-lang= : Target language code (e.g., FR, ES, DE)}
                            {--source-lang=EN : Source language code (default: EN)}
                            {--free : Use the free DeepL API endpoint}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simple DeepL API wrapper for file-based translation';

    private $deeplTranslator;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $apiKey = $this->option('api-key');
        $useFreeApi = $this->option('free');

        try {
            // Initialize DeepL translator
            $this->deeplTranslator = new DeeplApiTranslator($apiKey, $useFreeApi, $this);

            switch ($action) {
                case 'translate':
                    return $this->handleTranslate();
                    
                case 'languages':
                    return $this->handleLanguages();
                    
                case 'usage':
                    return $this->handleUsage();
                    
                case 'test':
                    return $this->handleTest();
                    
                default:
                    $this->error("Unknown action: {$action}");
                    $this->error("Available actions: translate, languages, usage, test");
                    return 1;
            }
        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }

    private function handleTranslate(): int
    {
        $inputFile = $this->option('input-file');
        $outputFile = $this->option('output-file');
        $targetLanguage = $this->option('target-lang');
        $sourceLanguage = $this->option('source-lang');

        // Validate required options
        if (!$inputFile || !$outputFile || !$targetLanguage) {
            $this->error("--input-file, --output-file, and --target-lang options are required for translate action");
            return 1;
        }

        if (!file_exists($inputFile)) {
            $this->error("Input file not found: {$inputFile}");
            return 1;
        }

        try {
            // Load input data
            $exportData = json_decode(file_get_contents($inputFile), true);
            if (!$exportData) {
                $this->error("Invalid JSON in input file");
                return 1;
            }

            // Extract texts to translate
            $textsToTranslate = [];
            foreach ($exportData as $identifier => $data) {
                $textsToTranslate[$identifier] = $data['text'];
            }

            // Translate with DeepL
            $translatedTexts = $this->deeplTranslator->translateTexts(
                $textsToTranslate, 
                $targetLanguage, 
                $sourceLanguage
            );

            // Combine original data with translations
            $result = [];
            foreach ($exportData as $identifier => $originalData) {
                $result[$identifier] = $originalData;
                $result[$identifier]['translated_text'] = $translatedTexts[$identifier] ?? $originalData['text'];
            }

            // Save results
            file_put_contents($outputFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            return 0;
        } catch (Exception $e) {
            $this->error("Translation failed: " . $e->getMessage());
            return 1;
        }
    }

    private function handleLanguages(): int
    {
        try {
            $languages = $this->deeplTranslator->getAvailableLanguages();
            
            $this->info("Available DeepL languages:");
            $this->line("");
            
            foreach ($languages as $lang) {
                $this->line("  {$lang['language']} - {$lang['name']}");
            }
            
            return 0;
        } catch (Exception $e) {
            $this->error("Failed to get languages: " . $e->getMessage());
            return 1;
        }
    }

    private function handleUsage(): int
    {
        try {
            $usage = $this->deeplTranslator->getUsage();
            
            $this->info("DeepL API Usage:");
            $this->line("");
            $this->line("  Characters used: " . number_format($usage['character_count']));
            $this->line("  Characters limit: " . number_format($usage['character_limit']));
            $this->line("  Usage percentage: " . round(($usage['character_count'] / $usage['character_limit']) * 100, 2) . "%");
            
            return 0;
        } catch (Exception $e) {
            $this->error("Failed to get usage: " . $e->getMessage());
            return 1;
        }
    }

    private function handleTest(): int
    {
        $this->info("Testing DeepL API connection...");
        
        try {
            $result = $this->deeplTranslator->translateText(['Hello world', 'How are you?'], 'FR');
            
            if ($result) {
                $this->info("✅ Test successful!");
                foreach ($result as $i => $translation) {
                    $this->line("  Translation " . ($i + 1) . ": " . $translation);
                }
                return 0;
            } else {
                $this->error("❌ Test failed - no results returned");
                return 1;
            }
        } catch (Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            return 1;
        }
    }
}

/**
 * DeepL API Translator class
 */
class DeeplApiTranslator
{
    private $apiKey;
    private $apiUrl;
    private $command;

    public function __construct($apiKey, $useFreeApi = false, $command = null)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $useFreeApi ? 
            'https://api-free.deepl.com/v2/translate' : 
            'https://api.deepl.com/v2/translate';
        $this->command = $command;
    }

    /**
     * Translate multiple texts
     */
    public function translateTexts($texts, $targetLanguage, $sourceLanguage = 'EN')
    {
        if (empty($texts)) {
            throw new Exception("No texts provided for translation");
        }

        $batches = $this->createBatches($texts);
        $results = [];

        // Create progress bar for batch processing only if not in silent mode
        $batchProgress = null;
        if ($this->command) {
            $this->command->getOutput()->newLine();
            $batchProgress = $this->command->getOutput()->createProgressBar(count($batches));
            $batchProgress->setFormat('%current%/%max% [%bar%] %percent:3s%% %message%');
            $batchProgress->setMessage('Processing batches...');
            $batchProgress->start();
        }

        foreach ($batches as $i => $batch) {
            try {
                $batchResults = $this->translateBatch($batch, $targetLanguage, $sourceLanguage);
                $results = array_merge($results, $batchResults);
                
                if ($batchProgress) {
                    $batchProgress->setMessage("Batch " . ($i + 1) . "/" . count($batches) . " completed");
                    $batchProgress->advance();
                }
                
                // Rate limiting - wait between batches
                if ($i < count($batches) - 1) {
                    sleep(1);
                }
            } catch (Exception $e) {
                if ($batchProgress) {
                    $batchProgress->finish();
                    $batchProgress->clear();
                }
       
                throw $e;
            }
        }

        if ($batchProgress) {
            $batchProgress->setMessage('All batches completed!');
            $batchProgress->finish();
            $batchProgress->clear();
        }

        return $results;
    }

    /**
     * Translate single text for testing
     */
    public function translateText($texts, $targetLanguage, $sourceLanguage = 'EN')
    {
        $data = [
            'text' => $texts,
            'target_lang' => $targetLanguage,
            'source_lang' => $sourceLanguage,
            'preserve_formatting' => '1'
        ];

        $response = $this->makeApiCall($data);
        
        if (!isset($response['translations'])) {
            throw new Exception("Invalid response from DeepL API");
        }

        $results = [];
        foreach ($response['translations'] as $translation) {
            $results[] = $translation['text'];
        }

        return $results;
    }

    /**
     * Create batches to respect API limits
     */
    private function createBatches($texts)
    {
        $batches = [];
        $currentBatch = [];
        $currentLength = 0;
        $maxBatchSize = 50; // DeepL allows up to 50 texts per request
        $maxCharacters = 100000; // Conservative character limit

        foreach ($texts as $key => $text) {
            $textLength = strlen($text);
            
            if ((count($currentBatch) >= $maxBatchSize) || 
                ($currentLength + $textLength > $maxCharacters && !empty($currentBatch))) {
                
                $batches[] = $currentBatch;
                $currentBatch = [];
                $currentLength = 0;
            }
            
            $currentBatch[$key] = $text;
            $currentLength += $textLength;
        }

        if (!empty($currentBatch)) {
            $batches[] = $currentBatch;
        }

        return $batches;
    }

    /**
     * Translate a single batch
     */
    private function translateBatch($batch, $targetLanguage, $sourceLanguage)
    {
        $data = [
            'text' => array_values($batch),
            'target_lang' => $targetLanguage,
            'source_lang' => $sourceLanguage,
            'preserve_formatting' => '1'
        ];

        $response = $this->makeApiCall($data);
        
        if (!isset($response['translations'])) {
            throw new Exception("Failed to get translations from DeepL API");
        }

        $results = [];
        $keys = array_keys($batch);
        
        foreach ($response['translations'] as $index => $translation) {
            $originalKey = $keys[$index];
            $results[$originalKey] = $translation['text'];
        }

        return $results;
    }

    /**
     * Make API call to DeepL
     */
    private function makeApiCall($data)
    {
        $postData = $this->buildDeepLQuery($data);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: DeepL-Auth-Key ' . $this->apiKey,
                    'Content-Type: application/x-www-form-urlencoded',
                    'Content-Length: ' . strlen($postData),
                    'User-Agent: Laravel DeepL Translator/1.0'
                ],
                'content' => $postData,
                'timeout' => 60
            ]
        ]);

        $response = file_get_contents($this->apiUrl, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception("API call failed: " . $error['message']);
        }

        $httpCode = $this->getHttpResponseCode($http_response_header);
        if ($httpCode !== 200) {
            switch ($httpCode) {
                case 403:
                    throw new Exception("HTTP 403 Forbidden - Check your API key and endpoint (free vs pro)");
                case 413:
                    throw new Exception("HTTP 413 Request too large - Reduce batch size");
                case 429:
                    throw new Exception("HTTP 429 Too many requests - Rate limit exceeded");
                case 456:
                    throw new Exception("HTTP 456 Quota exceeded - Monthly character limit reached");
                default:
                    throw new Exception("API returned HTTP {$httpCode}: {$response}");
            }
        }

        return json_decode($response, true);
    }

    /**
     * Build query string for DeepL API
     */
    private function buildDeepLQuery($data)
    {
        $parts = [];
        
        foreach ($data as $key => $value) {
            if ($key === 'text' && is_array($value)) {
                foreach ($value as $text) {
                    $parts[] = 'text=' . urlencode($text);
                }
            } else {
                $parts[] = urlencode($key) . '=' . urlencode($value);
            }
        }
        
        return implode('&', $parts);
    }

    /**
     * Extract HTTP response code from headers
     */
    private function getHttpResponseCode($headers)
    {
        foreach ($headers as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                $parts = explode(' ', $header);
                return intval($parts[1]);
            }
        }
        return 0;
    }

    /**
     * Get available languages from DeepL
     */
    public function getAvailableLanguages()
    {
        $languageUrl = str_replace('/translate', '/languages', $this->apiUrl);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: DeepL-Auth-Key ' . $this->apiKey
            ]
        ]);
        
        $response = file_get_contents($languageUrl, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to get available languages");
        }
        
        return json_decode($response, true);
    }

    /**
     * Get usage information
     */
    public function getUsage()
    {
        $usageUrl = str_replace('/translate', '/usage', $this->apiUrl);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: DeepL-Auth-Key ' . $this->apiKey
            ]
        ]);
        
        $response = file_get_contents($usageUrl, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to get usage information");
        }
        
        return json_decode($response, true);
    }
}