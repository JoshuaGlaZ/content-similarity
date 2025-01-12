<?php
session_start();
set_time_limit(600);
header('Content-Type: application/json');

require __DIR__ . '/vendor/autoload.php';

use Phpml\Tokenization\WhitespaceTokenizer;
use Sastrawi\StopWordRemover\StopWordRemoverFactory;
use Sastrawi\Stemmer\StemmerFactory;

// Fungsi untuk memproses teks
function preprocessText($text)
{
    // 1. Proses hashtag
    $text = preg_replace_callback(
        '/#([a-zA-Z0-9]+)/',
        function ($matches) {
            $hashtag = $matches[1];
            // Prioritizing underscore separation
            if (strpos($hashtag, '_') !== false) {
                $hashtag = str_replace('', ' ', $hashtag);
            } else {
                // Handle camel case only if no underscore is present
                if (preg_match('/[A-Z]/', $hashtag)) {
                    $hashtag = preg_replace('/([a-z])([A-Z])/', '$1 $2', $hashtag);
                }
            }
            // Convert everything to lowercase
            return $hashtag;
        },
        $text
    );

    // 2. Case folding (mengubah ke huruf kecil)
    $text = strtolower($text);

    // 3. Hapus mention (@)
    $text = preg_replace('/@[a-zA-Z0-9_]+/', '', $text);

    // 4. Hapus link dan simbol spesial
    $text = preg_replace('/https?:\/\/\S+|www\.\S+/', '', $text); // Hapus link
    $text = preg_replace('/[^\w\s]/', '', $text); // Hapus simbol spesial

    // 5. Tokenisasi menggunakan Sastrawi
    $tokenizer = new WhitespaceTokenizer();
    $tokens = $tokenizer->tokenize($text);

    // 6. Stopword removal menggunakan Sastrawi
    $stopWordRemoverFactory = new StopWordRemoverFactory();
    $stopWordRemover = $stopWordRemoverFactory->createStopWordRemover();

    // Filter stopword per token
    $tokens = array_filter($tokens, function ($word) use ($stopWordRemover) {
        return $stopWordRemover->remove($word) !== ''; // Jika bukan stopword
    });

    // 7. Stemming sederhana (opsional)
    $stemmerFactory = new StemmerFactory();
    $stemmer = $stemmerFactory->createStemmer();

    $tokens = array_map(function ($word) use ($stemmer) {
        return $stemmer->stem($word);
    }, $tokens);

    // Gabungkan kembali token yang telah diproses
    return implode(' ', $tokens);
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
                    $item['source'] = 'Instagram';
                    $item['preprocess-result'] = preprocessText($item['original-text']);
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
                    $item['preprocess-result'] = preprocessText($item['original-text']);
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
