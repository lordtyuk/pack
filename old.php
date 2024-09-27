<?php

function getChars($headerFilePath, $filePath, $forceRecreate = false)
{
    $contents = @file_get_contents($headerFilePath);
    if (!$contents || $forceRecreate) {
        $contents = processFileInChunksForChars($filePath, $headerFilePath);
    } else {
        $contents = json_decode($contents, true);
    }
    return $contents;
}

function getWords($headerFilePath, $filePath, $chars, $forceRecreate = false)
{
    $contents = @file_get_contents($headerFilePath);
    if (!$contents || $forceRecreate) {
        $contents = processFileInChunksForWords($filePath, $headerFilePath, $chars);
    } else {
        $contents = json_decode($contents, true);
    }
    return $contents;
}

function processFileInChunksForChars($filePath, $outputFilePath)
{
    $chunkSize = 1024 * 1024;
    $charCounter = [];

    // Open the file in read mode
    $handle = fopen($filePath, "r");

    if ($handle) {
        while (!feof($handle)) {
            // Read a chunk of the file
            $chunk = fread($handle, $chunkSize);

            for ($i = 0; $i < strlen($chunk); $i++) {
                $char = $chunk[$i];
                if (!isset($charCounter[$char])) {
                    $charCounter[$char] = 1;
                } else {
                    $charCounter[$char]++;
                }
            }
        }

        fclose($handle);
    } else {
        echo "Unable to open the file.";
    }

    // Open output file for writing
    $outputFile = fopen($outputFilePath, 'w');
    if (!$outputFile) {
        echo "Unable to open output file.";
        return;
    }

    // Write dictionary to output file
    arsort($charCounter);

    $charCounter = array_splice($charCounter, 0, 10);
    fwrite($outputFile, json_encode($charCounter));
    fclose($outputFile);

    return $charCounter;
}

function getWordsFromChunk($chars, $chunk, $wordCounts = [])
{

    $charKeys = array_keys($chars);
    $chars = array_splice($charKeys, 0, 7);

    // Create a regex pattern for the characters
    $pattern = '/' . implode('|', array_map('preg_quote', $chars)) . '/';

    $isWithin = false; // Track whether we are between the characters

    $remainder = '';
    $lastPos = 0;
    $words = [];
    // Find matches
    preg_match_all($pattern, $chunk, $matches, PREG_OFFSET_CAPTURE);
    if (!empty($matches[0])) {
        $lastPos = 0;
        foreach ($matches[0] as $match) {
            $currentPos = $match[1];

            // Extract text between the last position and the current match
            if ($isWithin) {
                // If we were inside, check if this is an ending character
                $word = substr($chunk, $lastPos, $currentPos - $lastPos);
                if ($wordCounts) {
                    if (!isset($wordCounts[$word])) {
                        [$_words, $wordCounts] = findSubparts([$word], $wordCounts);
                        foreach ($_words as $__word) {
                            $words[] = $__word;
                        }
                    }
                } else {
                    $words[] = $word;
                }
            }

            // Toggle the state
            $isWithin = !$isWithin;
            $lastPos = $currentPos + strlen($match[0]); // Move past the match
        }

        // Handle remainder if we end in a match
        $remainder = substr($chunk, $lastPos);
    } else {
        $remainder .= substr($chunk, $lastPos); // Update remainder
    }

    return [$words, $remainder];
}

function findSubparts($rares, $wordCounts, $startPos = 2)
{

    $foundParts = [];
    foreach ($rares as $word) {
        if ($startPos > strlen($word)) {
            $foundParts[] = $word;
        }
        $remainder = $word;
        $i = $startPos;
        while ($i <= strlen($remainder)) {
            $part = substr($remainder, 0, $i);
            if (isset($wordCounts[$part])) {
                $wordCounts[$part]++;
                $foundParts[] = $part;
                $remainder = substr($remainder, $i);
                if (!$remainder) {
                    break;
                }
                $i = $startPos;
            } else {
                $i++;
            }
        }
        if ($remainder) {
            if (!isset($wordCounts[$remainder])) {
                $wordCounts[$remainder] = 1;
                if ($remainder !== $word) {
                    unset($wordCounts[$word]);
                }
            } else {
                $wordCounts[$remainder]++;
            }
            $foundParts[] = $remainder;
        }
    }

    return [$foundParts, $wordCounts];
}

// Function to count word occurrences in a file
function processFileInChunksForWords($filePath, $outputFilePath, $chars)
{
    // Open the file for reading
    $handle = fopen($filePath, "rb");
    if (!$handle) {
        echo "Failed to open the file.";
        return;
    }

    $wordCounts = [];
    $bufferSize = 1024*1024; // Size of the chunks to read
    $remainder = ""; // To handle leftovers from the last read

    while (!feof($handle)) {
        // Read a chunk of the file
        $chunk = fread($handle, $bufferSize);
        $chunk = $remainder . $chunk; // Combine with remainder

        [$words, $remainder] = getWordsFromChunk($chars, $chunk);
        foreach ($words as $word) {
            if (!isset($wordCounts[$word])) {
                $wordCounts[$word] = 0;
            }
            $wordCounts[$word]++;
        }
    }

    // Handle the last leftover word (if any)
    if (!empty($remainder)) {
        $remainder = trim($remainder);
        if (isset($wordCounts[$remainder])) {
            $wordCounts[$remainder]++;
        } else {
            $wordCounts[$remainder] = 1;
        }
    }

    fclose($handle);

    // Open output file for writing
    $outputFile = fopen($outputFilePath, 'w');
    if (!$outputFile) {
        echo "Unable to open output file.";
        return;
    }

    // Write dictionary to output file
    arsort($wordCounts);

    $rare = array_filter($wordCounts, function ($count) {
        return $count <= 2;
    });

    $wordCounts = array_filter($wordCounts, function ($count) {
        return $count > 2;
    });

    [, $wordCounts] = findSubparts(array_keys($rare), $wordCounts);

    fwrite($outputFile, json_encode($wordCounts));
    fclose($outputFile);
    return $wordCounts;
}

function showProgress($processedSize, $totalSize)
{
    $progress = ($processedSize / $totalSize) * 100;
    $barLength = 50; // Length of the progress bar
    $filledLength = (int)($barLength * $progress / 100);
    $bar = str_repeat('=', $filledLength) . str_repeat(' ', $barLength - $filledLength);

    // Print progress bar
    printf("\r|%s| %.2f%%", $bar, $progress);
}

function createOutputFile($charCounts, $wordCounts, $filePath, $outputPath)
{
    // Create dictionary for words that occurred at least twice
    $_dictionary = [];
    $dictionary = [];
    $bufferSize = 1024; // Size of the chunks to read

    // Open output file for writing
    $output = fopen($outputPath, 'w');
    if (!$output) {
        echo "Unable to open output file.";
        return;
    }
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        echo "Unable to open input file.";
        return;
    }

    // Calculate total size for progress bar
    $totalSize = filesize($filePath);
    $processedSize = 0;
    $remainder = '';

    foreach ($wordCounts as $word => $count) {
        //if (strlen($word) * $count > 9 * $count) {
        $_dictionary[$word] = strlen($word) * $count;
        //}
    }
    asort($_dictionary);
    $index = 0;
    foreach ($_dictionary as $word => $size) {
        $dictionary[$word] = chr($index % 256);
        $index++;
    }
    $_dictionary = [];
    // Write dictionary to output file
    foreach ($dictionary as $word => $index) {
        fwrite($output, "$word\n");
    }

    while (!feof($handle)) {
        // Read a chunk of the file
        $chunk = fread($handle, $bufferSize);

        // Combine the chunk with any leftover from the previous read
        $chunk = $remainder . $chunk;

        [$words, $remainder] = getWordsFromChunk($charCounts, $chunk, $dictionary);


        // Count occurrences of each word
        foreach ($words as $word) {
            if (!isset($dictionary[$word])) {
                var_dump($word);
                continue;
            }
            fwrite($output, $dictionary[$word]);
        }

        $processedSize += strlen($chunk);
        showProgress($processedSize, $totalSize); // Update progress for writing
    }

    // Handle the last leftover word (if any)
    if (!empty($remainder)) {
        $remainder = (trim($remainder));
        fwrite($output, $dictionary[$remainder]);

    }

    // Close the braces for the output
    fclose($handle);
    fclose($output);
    echo "\nProcessing complete!\n";
}

// Example usage
$test = false;

ini_set('memory_limit', '2048M');
if ($test) {
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'test.txt';
    $headerPath = __DIR__ . DIRECTORY_SEPARATOR . 'testheader';
    $headerWordsPath = __DIR__ . DIRECTORY_SEPARATOR . 'testwords';
    $outputPath = __DIR__ . DIRECTORY_SEPARATOR . 'testoutput';
} else {
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'enwik9';
    $headerPath = __DIR__ . DIRECTORY_SEPARATOR . 'header';
    $headerWordsPath = __DIR__ . DIRECTORY_SEPARATOR . 'words';
    $outputPath = __DIR__ . DIRECTORY_SEPARATOR . 'output';
}
$chars = getChars($headerPath, $filePath, $test);
$words = getWords($headerWordsPath, $filePath, $chars, $test);
createOutputFile($chars, $words, $filePath, $outputPath);

echo "Output file created as 'output.txt'.\n";
