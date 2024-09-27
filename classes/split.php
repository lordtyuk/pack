<?php

class Split
{
    public function __construct(
        public bool $canCreate
    )
    {
    }

    public function getWordsFromChunk($chars, $chunk)
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

                    $words[] = $word;
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

    public function getLeastSplits($string, $inputStrings, $canCreate = false) {
        // Initialize the Trie for fast substring lookup
        $trie = new Trie();
        $maxWordLength = 0; // Track the max word length to optimize the window size

        foreach ($inputStrings as $input) {
            $trie->insert((string)$input);
            $maxWordLength = max($maxWordLength, strlen($input));
        }

        $length = strlen($string);

        // Array to store the least splits at each position
        $dp = array_fill(0, $length + 1, null);
        $dp[0] = [];  // Base case: Empty string has no splits

        // Queue for BFS (position in the string and the split path so far)
        $queue = [[0, []]];

        while (!empty($queue)) {
            [$start, $path] = array_shift($queue);

            // Early termination if we reached the end of the string
            if ($start == $length) {
                return $path;
            }

            // Get all valid prefixes (substrings) starting from the current position
            $prefixes = $trie->findPrefixesFrom((string)$string, $start);

            foreach ($prefixes as $prefix) {
                $nextPos = $start + strlen($prefix);

                // Skip if already visited with a better or equal split
                if ($dp[$nextPos] !== null && count($dp[$nextPos]) <= count($path) + 1) {
                    continue;
                }

                // Update dp for the new position with the new split path
                $dp[$nextPos] = array_merge($path, [$prefix]);

                // Add the next position to the queue for further exploration
                $queue[] = [$nextPos, $dp[$nextPos]];
            }

            // Handle the case where canCreate is true
            if ($canCreate && $start < $length && empty($prefixes)) {
                // Append the remaining substring if no valid split is found and canCreate is allowed
                $remaining = substr($string, $start);
                return array_merge($path, [$remaining]);
            }
        }

        // If no valid splits found and canCreate is false, return an empty array
        return $canCreate ? [$string] : [];
    }
}

class TrieNode {
    public $children = [];
    public $isEnd = false;
}

class Trie {
    private $root;

    public function __construct() {
        $this->root = new TrieNode();
    }

    public function insert($word) {
        $node = $this->root;
        for ($i = 0; $i < strlen($word); $i++) {
            $char = $word[$i];
            if (!isset($node->children[$char])) {
                $node->children[$char] = new TrieNode();
            }
            $node = $node->children[$char];
        }
        $node->isEnd = true;
    }

    public function findPrefixesFrom($string, $start) {
        $node = $this->root;
        $result = [];
        for ($i = $start; $i < strlen($string); $i++) {
            $char = $string[$i];
            if (!isset($node->children[$char])) {
                break;
            }
            $node = $node->children[$char];
            if ($node->isEnd) {
                $result[] = substr($string, $start, $i - $start + 1);
            }
        }
        return $result;
    }
}
