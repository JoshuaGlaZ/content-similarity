<?php
session_start();
set_time_limit(600);
header('Content-Type: application/json');

require __DIR__ . '/vendor/autoload.php';

use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\FeatureExtraction\TokenCountVectorizer;
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

// Fungsi untuk menghitung Term Frequency (TF)
function calculateTF($document)
{
    $tf = [];
    $totalTerms = count($document);

    foreach ($document as $term) {
        if (!isset($tf[$term])) {
            $tf[$term] = 0;
        }
        $tf[$term]++;
    }

    // Normalisasi TF
    foreach ($tf as $term => $count) {
        $tf[$term] = $count / $totalTerms;
    }

    return $tf;
}

// Fungsi untuk menghitung Inverse Document Frequency (IDF)
function calculateIDF($documents)
{
    $idf = [];
    $totalDocuments = count($documents);

    foreach ($documents as $document) {
        $uniqueTerms = array_unique($document);
        foreach ($uniqueTerms as $term) {
            if (!isset($idf[$term])) {
                $idf[$term] = 0;
            }
            $idf[$term]++;
        }
    }

    // Hitung IDF
    foreach ($idf as $term => $docCount) {
        $idf[$term] = log($totalDocuments / $docCount, 10);
    }

    return $idf;
}

// Fungsi untuk menghitung TF-IDF
function calculateTFIDF($documents)
{
    $tfidf = [];
    $tfValues = [];
    $tokenizedDocuments = [];

    // Tokenisasi dokumen
    foreach ($documents as $document) {
        $tokenizedDocuments[] = explode(' ', $document);
    }

    // Hitung TF untuk setiap dokumen
    foreach ($tokenizedDocuments as $document) {
        $tfValues[] = calculateTF($document);
    }

    // Hitung IDF untuk semua dokumen
    $idf = calculateIDF($tokenizedDocuments);

    // Hitung TF-IDF
    foreach ($tfValues as $docIndex => $tf) {
        $tfidf[$docIndex] = [];
        foreach ($tf as $term => $tfValue) {
            $tfidf[$docIndex][$term] = $tfValue * ($idf[$term] ?? 0);
        }
    }

    return $tfidf;
}

// Fungsi untuk menghitung similaritas Jaccard
function calculateJaccardSimilarity($vector1, $vector2)
{
    $intersection = 0;
    $union = 0;

    foreach (array_keys($vector1 + $vector2) as $key) {
        $val1 = $vector1[$key] ?? 0;
        $val2 = $vector2[$key] ?? 0;
        $intersection += min($val1, $val2);
        $union += max($val1, $val2);
    }

    return $union == 0 ? 0 : $intersection / $union;
}

// Fungsi untuk menghitung similaritas Overlap
function calculateOverlapSimilarity($vector1, $vector2)
{
    $intersection = 0;
    $minValue = 0;

    foreach (array_keys($vector1 + $vector2) as $key) {
        $val1 = $vector1[$key] ?? 0;
        $val2 = $vector2[$key] ?? 0;
        $intersection += min($val1, $val2);
        $minValue += $val1;
    }

    return $minValue == 0 ? 0 : $intersection / $minValue;
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
                    error_log(message: print_r($item, true));
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

        if ($source == 'x') {
            $json = shell_exec('python twitter_scrapper.py "' . $keyword . '"');
            error_log("Raw Output: " . $json); // Log raw output for debugging
            $result = json_decode($json, true);
            if ($result !== null && isset($result['tweets'])) {
                // Process the tweets array directly
                foreach ($result['tweets'] as $tweet) {
                    $processedTweet = [
                        'link' => $tweet['username'],
                        'original-text' => $tweet['original-text'],
                        'source' => 'X',
                        'preprocess-result' => preprocessText($tweet['original-text'])
                    ];
                    $data[] = $processedTweet;
                }
                unset($item);
                $data = array_merge($data, $result);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Invalid JSON output from Python script.']);
                echo json_encode(['error' => 'Invalid JSON output from Twitter Python script.']);
                exit;
            }
        }
    }

    // Hitung TF-IDF
    $preprocessedData = array_map(function ($item) {
        return $item['preprocess-result'];
    }, $data);
    $tfidfResults = calculateTFIDF($preprocessedData);

    if (empty($preprocessedData)) {
        error_log("Data setelah preprocessing kosong.");
        echo json_encode(['error' => 'Preprocessed data is empty.']);
        exit;
    }

    $similarity = [];
    foreach ($tfidfResults as $index => $tfidfVector) {
        $docVector = array_combine(array_keys($tfidfVector), array_values($tfidfVector));
        $processedKeyword = preprocessText($keyword);
        $keywordVector = calculateTF(explode(' ', $processedKeyword));
        if($method == 'method_1'){
            $similarity = calculateJaccardSimilarity($keywordVector, $docVector);
        }
        else{
            $similarity = calculateOverlapSimilarity($keywordVector, $docVector);
        }
    }

    usort($similarity, function ($a, $b) {
        return $b['best_similarity'] <=> $a['best_similarity'];
    });

    $response = [
        'keyword' => $keyword,
        'sources' => $sources,
        'method' => $method,
        'data' => $data,
        'tfidf-results' => $tfidfResults,
        'similarity' => $similarity
    ];

    echo json_encode($response);
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
