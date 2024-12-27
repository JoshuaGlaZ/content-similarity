<?php
session_start();
set_time_limit(300); 
header('Content-Type: application/json');

require __DIR__ . '/vendor/autoload.php';

use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\StopWords;

// Predefined stopwords
$stopWords = new StopWords\English();

// Function to preprocess text
function preprocessText($text, $stopWords) {
    // 1. Process hashtags
    $text = preg_replace_callback(
        '/#([a-zA-Z0-9_]+)/',
        function ($matches) {
            $hashtag = $matches[1];
            // Handle underscore separation
            if (strpos($hashtag, '_') !== false) {
                return str_replace('_', ' ', $hashtag);
            }
            // Handle camel case
            if (preg_match('/[A-Z]/', $hashtag)) {
                return preg_replace('/([a-z])([A-Z])/', '$1 $2', $hashtag);
            }
            // Single word or unclear separation
            return $hashtag;
        },
        $text
    );

    // 2. Case folding
    $text = strtolower($text);

    // 3. Remove mentions (@)
    $text = preg_replace('/@[a-zA-Z0-9_]+/', '', $text);

    // 4. Remove links and special symbols
    $text = preg_replace('/https?:\/\/\S+|www\.\S+/', '', $text); // Links
    $text = preg_replace('/[^\w\s]/', '', $text); // Special symbols

    // 5. Tokenize the text
    $tokenizer = new WhitespaceTokenizer();
    $tokens = $tokenizer->tokenize($text);

    // 6. Stopword removal
    $tokens = array_filter($tokens, function ($word) use ($stopWords) {
        return !$stopWords->isStopWord($word);
    });

    // 7. Custom stemming
    $tokens = array_map('simpleStemmer', $tokens);

    // Return the processed text as a string
    return implode(' ', $tokens);
}

// Simple stemming function
function simpleStemmer($word) {
    // Example: remove common suffixes
    $patterns = [
        '/ing$/',   // Remove -ing
        '/ed$/',    // Remove -ed
        '/ly$/',    // Remove -ly
        '/es$/',    // Remove -es
        '/s$/',     // Remove -s
    ];
    $word = preg_replace($patterns, '', $word);
    return $word;
}

// Main script
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['keyword'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Keyword is required.']);
        exit;
    }

    if (empty($_POST['method'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Method is required.']);
        exit;
    }

    // Validate sources
    if (!isset($_POST['source']) || !is_array($_POST['source']) || count($_POST['source']) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'At least one source is required.']);
        exit;
    }

    $keyword = htmlspecialchars($_POST['keyword']);
    $sources = isset($_POST['source']) ? $_POST['source'] : [];
    $method = htmlspecialchars($_POST['method']);
    $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
    $page = max(1, $page);

    $data = [];
    foreach ($sources as $source) {
        if ($source == 'instagram') {
            $output = shell_exec("python ws-ig.py '" . escapeshellarg($keyword) . "'");
            $result = json_decode($output, true); // Decode JSON from Python
            if ($result !== null) {
                foreach ($result as &$item) {
                    $item['source'] = 'Instagram - ' . $item['link'];
                    $item['preprocess-result'] = preprocessText($item['original-text'], $stopWords);
                }
                unset($item); // Break reference with the last element
                $data = array_merge($data, $result);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Invalid JSON output from Python script.']);
                exit;
            }
        }

        if ($source == 'youtube') {
            $json = shell_exec('python youtube.py "' . $keyword . '"');
            $result = json_decode($json, true);
            if ($result !== null) {
                foreach ($result as &$item) {
                    $item['source'] = 'YouTube';
                    $item['preprocess-result'] = preprocessText($item['original-text'], $stopWords);
                }
                unset($item);
                $data = array_merge($data, $result);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Invalid JSON output from Python script.']);
                exit;
            }
        }
    }

    $response = [
        'keyword' => $keyword,
        'sources' => $sources,
        'method' => $method,
        'data' => $data,
        'preprocess-result' => array_map(function ($item) {
            return $item['preprocess-result'];
        }, $data),
    ];

    echo json_encode($response);
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
