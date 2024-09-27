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

    public function search($word) {
        $node = $this->root;
        for ($i = 0; $i < strlen($word); $i++) {
            $char = $word[$i];
            if (!isset($node->children[$char])) {
                return false;
            }
            $node = $node->children[$char];
        }
        return $node->isEnd;
    }

    public function findLongestPrefix($string, $start) {
        $node = $this->root;
        $longest = '';
        $current = '';
        for ($i = $start; $i < strlen($string); $i++) {
            $char = $string[$i];
            if (!isset($node->children[$char])) {
                break;
            }
            $node = $node->children[$char];
            $current .= $char;
            if ($node->isEnd) {
                $longest = $current;
            }
        }
        return $longest;
    }
}

function getLeastSplits($string, $inputStrings, $canCreate = false) {
    // Initialize the Trie for fast substring lookup
    $trie = new Trie();
    foreach ($inputStrings as $input) {
        $trie->insert($input);
    }

    // Memoization cache to store results for substrings
    $cache = [];

    // Dynamic programming function with memoization
    function dp($string, $trie, &$cache, $canCreate) {
        if (isset($cache[$string])) {
            return $cache[$string];
        }

        $result = null;

        // Try to split the string at every valid substring from the start
        for ($i = 0; $i < strlen($string); $i++) {
            $longestPrefix = $trie->findLongestPrefix($string, $i);

            if ($longestPrefix !== '') {
                $remaining = substr($string, strlen($longestPrefix));
                $splits = dp($remaining, $trie, $cache, $canCreate);

                if ($splits !== null) {
                    $currentSplit = array_merge([$longestPrefix], $splits);

                    // If we don't have a result yet or found a smaller split, update result
                    if ($result === null || count($currentSplit) < count($result)) {
                        $result = $currentSplit;
                    }
                }
            }
        }

        // If we found a valid split, cache and return it
        if ($result !== null) {
            $cache[$string] = $result;
            return $result;
        }

        // If canCreate is true and no valid split was found, add the remaining substring
        if ($canCreate) {
            $cache[$string] = [$string];
            return [$string];
        }

        // If we can't create and no valid split, return null (indicating no split is possible)
        $cache[$string] = null;
        return null;
    }

    // Run dynamic programming starting from the full string
    $result = dp($string, $trie, $cache, $canCreate);

    // If no result and canCreate is false, return an empty array
    return $result !== null ? $result : [];
}

// Example Test
$inputStrings = ["apple", "pie", "applepie", "ap", "ple"];
$testString = "bananapie";
$canCreate = true;

// Get the least number of splits
$leastSplit = getLeastSplits($testString, $inputStrings, $canCreate);

print_r($leastSplit);
