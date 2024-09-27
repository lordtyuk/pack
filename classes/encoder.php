<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "pack.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "config.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "reduce.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "split.php";


class Encoder
{

    private Pack $pack;
    private Reduce $reduce;
    private Split $split;
    private $sourceFile;
    private $charactersFile;
    private $wordsFile;
    private $outputFile;
    private $decodedFile;

    private $words = null;
    private $characters = null;

    public function __construct(
        private Config $config,
        private int    $chunk = 1024 * 1024
    )
    {
        $this->pack = new Pack();
        $this->reduce = new Reduce();
        $this->split = new Split(true);

        $this->sourceFile = fopen($this->config->filePath, 'r');
        $charactersFileName = $this->config->filePath . '.characters';
        $wordsFileName = $this->config->filePath . '.words';

        $words = @file_get_contents($wordsFileName);
        $characters = @file_get_contents($charactersFileName);

        if ($words) {
            $this->words = json_decode($words, true);
        }
        if ($characters) {
            $this->characters = json_decode($characters, true);
        }
    }

    public function getCharacters($force = false)
    {
        if (!$this->characters || $force) {
            $charactersFileName = $this->config->filePath . '.characters';
            $this->charactersFile = fopen($charactersFileName, 'w');

            $charCounter = [];
            while (!feof($this->sourceFile)) {
                // Read a chunk of the file
                $chunk = fread($this->sourceFile, $this->chunk);

                for ($i = 0; $i < strlen($chunk); $i++) {
                    $char = $chunk[$i];
                    if (!isset($charCounter[$char])) {
                        $charCounter[$char] = 1;
                    } else {
                        $charCounter[$char]++;
                    }
                }
            }

            fclose($this->sourceFile);
            $this->sourceFile = fopen($this->config->filePath, 'r');
            arsort($charCounter);
            $charCounter = array_splice($charCounter, 0, 20);
            fwrite($this->charactersFile, json_encode($charCounter));
            $this->characters = $charCounter;
        }

        return $this->characters;
    }

    public function getWords($force = false)
    {
        if (!$this->words || $force) {
            $wordsFileName = $this->config->filePath . '.words';
            $this->wordsFile = fopen($wordsFileName, 'w');

            $remainder = ""; // To handle leftovers from the last read

            while (!feof($this->sourceFile)) {
                // Read a chunk of the file
                $chunk = fread($this->sourceFile, $this->chunk);
                $chunk = $remainder . $chunk; // Combine with remainder

                [$words, $remainder] = $this->split->getWordsFromChunk($this->characters, $chunk);
                foreach ($words as $word) {
                    if (!isset($wordCounts[(string)$word])) {
                        $wordCounts[(string)$word] = 0;
                    }
                    $wordCounts[(string)$word]++;
                }
            }

            // Handle the last leftover word (if any)
            if (!empty($remainder)) {
                $remainder = (string)($remainder);
                if (isset($wordCounts[$remainder])) {
                    $wordCounts[$remainder]++;
                } else {
                    $wordCounts[$remainder] = 1;
                }
            }

            fclose($this->sourceFile);
            // Write dictionary to output file
            arsort($wordCounts);

            $rares = array_filter($wordCounts, function ($count) {
                return $count <= 2;
            });

            $wordCounts = array_filter($wordCounts, function ($count) {
                return $count > 2;
            });

            foreach($rares as $rare) {
                $this->split->canCreate = true;
                $replace = $this->split->getLeastSplits($rare, array_keys($wordCounts), true);
                foreach($replace as $item) {
                    if(isset($wordCounts[(string)$item])) {
                        $wordCounts[(string)$item]++;
                    } else {
                        $wordCounts[(string)$item] = 1;
                    }
                }
            }

            $wordCounts = $this->reduce->getMinimalSetOptimized(array_keys($wordCounts));
            fwrite($this->wordsFile, json_encode($wordCounts));
            $this->sourceFile = fopen($this->config->filePath, 'r');

            $this->words = $wordCounts;
        }

        return $this->words;
    }

    public function encode(): void
    {

        $this->outputFile = fopen($this->config->filePath . '.encoded', 'w');

        echo "\nProcessing characters...\n";
        $this->getCharacters($this->config->prefix === 'test');
        echo "\nProcessing words...\n";
        $this->getWords($this->config->prefix === 'test');
        echo "\nEncoding...\n";

        // Calculate total size for progress bar
        $totalSize = filesize($this->config->filePath);
        $processedSize = 0;
        $remainder = '';

        // Write dictionary to output file
        fwrite($this->outputFile, pack('C', ceil(log(count($this->words), 2))));
        foreach ($this->words as $word) {
            fwrite($this->outputFile, "$word\n");
        }

        $wordsDict = array_flip($this->words);

        while (!feof($this->sourceFile)) {
            // Read a chunk of the file
            echo "\nProcessing chunk...\n";

            $chunk = fread($this->sourceFile, $this->chunk);

            // Combine the chunk with any leftover from the previous read
            $chunk = $remainder . $chunk;

            [$words, $remainder] = $this->split->getWordsFromChunk($this->characters, $chunk);

            echo "\nWriting data...\n";

            // Count occurrences of each word
            foreach ($words as $word) {
                if (isset($wordsDict[$word])) {
                    fwrite($this->outputFile, $wordsDict[$word]);
                } else {
                    $_store = $this->split->getLeastSplits($word, $this->words, false);
                    foreach($_store as $__word) {
                        fwrite($this->outputFile, $wordsDict[$__word]);
                    }
                }

            }

            $processedSize += strlen($chunk);
            $this->showProgress($processedSize, $totalSize); // Update progress for writing
        }

        // Handle the last leftover word (if any)
        if (!empty($remainder)) {
            $_store = $this->split->getLeastSplits($remainder, $this->words, false);

            foreach($_store as $__word) {
                fwrite($this->outputFile, $wordsDict[$__word]);
            }
        }

        // Close the braces for the output
        fclose($this->sourceFile);
        fclose($this->outputFile);
        echo "\nProcessing complete!\n";
    }

    private function getStoredWordsSize()
    {
        $char = fread($this->outputFile, 1);
        return unpack('C', $char)[1];
    }

    public function decode(): void
    {

        $this->outputFile = fopen($this->config->filePath . '.encoded', 'r');
        $this->decodedFile = fopen($this->config->filePath . '.decoded', 'w');

        var_dump($this->getStoredWordsSize());
        echo "\nProcessing complete!\n";
    }


    private function showProgress($processedSize, $totalSize)
    {
        $progress = ($processedSize / $totalSize) * 100;
        $barLength = 50; // Length of the progress bar
        $filledLength = (int)($barLength * $progress / 100);
        $bar = str_repeat('=', $filledLength) . str_repeat(' ', $barLength - $filledLength);

        // Print progress bar
        printf("\r|%s| %.2f%%", $bar, $progress);
    }

}
