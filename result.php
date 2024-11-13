<?php
session_start();
set_time_limit(300); 
header('Content-Type: application/json');

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

  if (count($sources) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Sources cannot be empty.']);
    exit;
  }

  $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) {
        $page = 1; 
    }

  // $sessionKey = md5($keyword . implode(',', array: $sources) . $method);

  // request baru di proses. Lama = skip (session)
  // if (!isset($_SESSION['data'][$sessionKey])) {
  $data = [];
  foreach ($sources as $source) {
    if ($source == 'instagram') {

      $output = shell_exec("python ws-ig.py '" . escapeshellarg($keyword) . "'");
      $result = json_decode($output, true); // Decode JSON from Python
      if ($result !== null) {
        foreach ($result as &$item) {
          $item['source'] = 'Instagram'; 
        }
        unset($item); // Break reference with the last element
        $data = array_merge($data, $result); // Combine with existing data
      } else {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid JSON output from Python script.']);
        exit;
      }
    }
    // }

    // $_SESSION['data'][$sessionKey] = $data;
  }

  // $data = $_SESSION['data'][$sessionKey];

  // // setiap request, direset page = 1
  // if (!isset($_SESSION['last_request']) || $_SESSION['last_request'] !== $sessionKey) {
  //   $page = 1;
  //   $_SESSION['last_request'] = $sessionKey;
  // }

  $response = [
    'keyword' => $keyword,
    'sources' => $sources,
    'method' => $method,
    'data' => $data,
  ];

  echo json_encode($response);
} else {
  echo json_encode(['error' => 'Invalid request method']);
}
