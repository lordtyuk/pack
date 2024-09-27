<?php

class Reduce {
// Function to check if a string can be formed by concatenating other strings from the set
    public function canBeFormedFast($string, $set) {
        $len = strlen($string);
        if ($len == 0) return false;

        $dp = array_fill(0, $len + 1, false);
        $dp[0] = true;

        for ($i = 1; $i <= $len; $i++) {
            for ($j = 0; $j < $i; $j++) {
                // Extract the substring and check if it exists in the set
                $substring = substr($string, $j, $i - $j);
                if ($dp[$j] && isset($set[$substring])) {
                    $dp[$i] = true;
                    break;
                }
            }
        }

        return $dp[$len];
    }

    public function getMinimalSetOptimized($strings) {
        // Sort strings by length to process smaller strings first
        usort($strings, function($a, $b) {
            return strlen($a) - strlen($b);
        });

        $minimalSet = [];
        $hashSet = [];

        foreach ($strings as $str) {
            // Check if the current string can be formed from the strings in the minimal set
            if (!$this->canBeFormedFast($str, $hashSet)) {
                // Add the string to the minimal set and update the hashSet for quick lookup
                $minimalSet[] = $str;
                $hashSet[$str] = true; // Store string in hash set for O(1) lookup
            }
        }

        return $minimalSet;
    }

}
