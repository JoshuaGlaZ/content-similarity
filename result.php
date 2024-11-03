<?php
session_start();
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

  $sessionKey = md5($keyword . implode(',', $sources) . $method);

  // request baru di proses. Lama = skip (session)
  if (!isset($_SESSION['data'][$sessionKey])) {
    sleep(3); // Simulasi delay dari python

    $data = [
      ['source' => 'Source1' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis pretium ornare metus, ultrices tempus nibh molestie quis. Donec quis ultricies nunc. Ut fringilla ullamcorper est, eget hendrerit est. Integer tristique tellus sed ultricies mollis. Fusce non auctor sapien, id convallis nibh. Aenean leo elit, pulvinar vel augue ac, egestas dictum sem. Phasellus lobortis viverra lorem, in pellentesque velit porttitor non. ', 'preprocess-result' => 'Processed Text 1', 'similarity' => 0.85],
      ['source' => 'Source2' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Aliquam auctor dolor sit amet purus elementum, in iaculis enim rhoncus. Nam id nibh malesuada risus convallis laoreet sed sed augue. Aliquam erat volutpat. Aliquam consequat rutrum scelerisque. Praesent massa eros, pretium varius erat eu, eleifend molestie sem. Cras finibus ante odio, eget tempor ligula sodales ut. Vestibulum iaculis malesuada magna, vitae laoreet justo commodo ut. Etiam a lectus in sapien aliquam aliquet. Integer et elit metus. Morbi diam lorem, elementum dapibus dignissim in, aliquam quis dui. Aenean vel velit at lectus sollicitudin semper at a odio. Duis non metus varius, condimentum sapien vel, luctus dui. Cras sollicitudin, mauris ut vehicula lobortis, mauris ante fringilla mi, in pretium dolor diam ac orci. Vivamus a erat tellus. Ut at sapien egestas, sagittis nisi eget, feugiat lacus. ', 'preprocess-result' => 'Processed Text 2', 'similarity' => 0.90],
      ['source' => 'Source3' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Etiam vitae justo ut enim sagittis ultricies. Vivamus hendrerit, magna sed mattis consectetur, nisl orci maximus nunc, eget auctor massa mauris ut eros. Vestibulum massa quam, cursus et rhoncus sed, lobortis vel velit. Curabitur bibendum ornare ligula ac cursus. Aliquam vel purus enim. Cras mauris erat, congue quis commodo eget, cursus a odio. Sed velit nulla, accumsan tincidunt eleifend in, ultricies et libero. Duis in neque rutrum, hendrerit augue quis, condimentum metus. Nunc ipsum quam, blandit vitae interdum id, lobortis sed arcu. Nulla ligula turpis, fringilla a orci eget, imperdiet laoreet odio. ', 'preprocess-result' => 'Processed Text 3', 'similarity' => 1.0],
      ['source' => 'Source4' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Nullam rhoncus vel lacus eget lobortis. Vestibulum sodales et nunc at blandit. Cras et ullamcorper nunc. Donec congue et nulla ornare imperdiet. Fusce sed velit quis enim sollicitudin facilisis a in massa. Etiam at pellentesque turpis. Pellentesque nunc ex, vestibulum eget scelerisque et, ultricies eget tortor. In vel imperdiet augue. Proin fringilla condimentum lacus, sit amet semper neque. Nulla dapibus quam id venenatis bibendum. Vivamus maximus ligula nec elementum dignissim. Integer eleifend purus quis neque bibendum hendrerit. Nullam iaculis nisl non tincidunt sollicitudin. Fusce pharetra arcu non ex malesuada congue. Praesent bibendum diam tortor, quis aliquet odio dictum ac. ', 'preprocess-result' => 'Processed Text 4', 'similarity' => 0.95],
      ['source' => 'Source5' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Fusce convallis ut diam non volutpat. Quisque facilisis erat in laoreet tincidunt. Nunc sed finibus justo, eget lacinia urna. Donec volutpat ut enim lacinia pretium. Quisque pretium mollis sodales. Praesent vel arcu purus. Integer quis lectus vehicula, bibendum mi vitae, sagittis nisi. Nullam non efficitur nisi. Nunc suscipit enim sed nibh eleifend consectetur. In ut velit in sem sollicitudin sollicitudin. Phasellus eu mauris ac nibh accumsan vehicula vel ac nisl. In a maximus neque. Vestibulum at dolor imperdiet, porta odio eu, cursus quam. Nulla vitae consectetur mi, semper sodales mauris. Integer non elit orci. ', 'preprocess-result' => 'Processed Text 5', 'similarity' => 0.75],
      ['source' => 'Source6' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Nulla quis dui eget mauris rhoncus placerat a eget ligula. Nunc luctus velit feugiat ante aliquam lacinia. Cras vehicula nulla eros, quis facilisis quam pharetra eget. Donec convallis quam et tincidunt mollis. Curabitur tempus, risus eu porta sagittis, leo dui mattis leo, sit amet vehicula massa ipsum eu diam. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris vel mattis mauris. Vivamus posuere non ante nec maximus. Fusce nibh libero, pretium vitae lorem in, tempor lobortis nibh. Fusce sit amet suscipit metus. Sed feugiat fermentum rutrum. Fusce ac libero tempus, tempor metus in, ullamcorper lacus. Fusce tempus tincidunt ante at semper. Duis blandit ligula in lacus pellentesque, quis porta felis condimentum. Mauris a sem eu velit lacinia luctus. Mauris dignissim risus purus, non pretium justo scelerisque nec. ', 'preprocess-result' => 'Processed Text 6', 'similarity' => 0.80],
      ['source' => 'Source7' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Nunc at dictum nisl, vitae pulvinar enim. Donec iaculis, erat eget ullamcorper tincidunt, erat nunc ullamcorper massa, vitae hendrerit lorem ex eget nibh. Pellentesque ut risus sit amet urna tristique rutrum id feugiat purus. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Duis et orci nunc. Praesent sem sem, consectetur et massa nec, tempus blandit velit. Aliquam imperdiet id ligula et suscipit. Maecenas vitae accumsan est, id aliquam ante. ', 'preprocess-result' => 'Processed Text 7', 'similarity' => 0.85],
      ['source' => 'Source8' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Praesent eu massa et lectus dictum maximus. Sed efficitur diam ut vehicula luctus. Ut eleifend lectus nec lacus congue pellentesque. Aliquam elit quam, ultrices vel gravida ut, malesuada sed leo. Donec a dui vel metus maximus interdum sed nec ex. Mauris id commodo elit. Curabitur finibus tincidunt tellus ut euismod. Donec tempor nibh sed lacus rhoncus, eget vestibulum purus dapibus. Donec pretium, risus non suscipit fringilla, massa lorem ullamcorper lectus, quis finibus dolor risus vitae eros. Cras id dolor pretium, posuere quam vitae, ornare purus. Cras tincidunt ligula in lacinia pellentesque. Nulla pellentesque imperdiet fermentum. Maecenas sollicitudin nunc ut tortor iaculis, non euismod mi malesuada. Integer blandit, risus sed faucibus ornare, ante massa pharetra libero, id commodo elit augue quis massa. ', 'preprocess-result' => 'Processed Text 8', 'similarity' => 0.90],
      ['source' => 'Source9' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Nunc aliquet non mi et fringilla. In malesuada, quam vitae pretium sodales, est libero tincidunt ex, eu consectetur metus elit id leo. Sed varius nunc ipsum, ornare finibus dolor tincidunt sed. Nullam vitae tempus augue. Fusce euismod justo velit, a imperdiet augue condimentum quis. Nulla facilisi. Vivamus commodo orci ultrices, egestas quam sagittis, luctus odio. Integer sodales ac nibh eu vulputate. Suspendisse eleifend diam leo. Pellentesque vitae ante accumsan, egestas nisl in, placerat metus. Ut fermentum luctus ultricies. Pellentesque ultricies urna sapien, quis hendrerit risus faucibus et. ', 'preprocess-result' => 'Processed Text 9', 'similarity' => 1.0],
      ['source' => 'Source10' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Quisque nec pharetra lacus. Nam ornare ultricies nibh ut tempor. Phasellus massa magna, fringilla id purus nec, consectetur ullamcorper enim. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer interdum lectus sed libero venenatis bibendum. Praesent leo justo, mattis congue est sed, congue finibus magna. Suspendisse in orci ante. ', 'preprocess-result' => 'Processed Text 10', 'similarity' => 0.95],
      ['source' => 'Source11' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Donec dapibus ex quam. Nunc finibus leo vitae lacus feugiat, ac consequat leo accumsan. Donec sit amet justo porttitor, tristique massa id, scelerisque nibh. Cras pulvinar, dui in rhoncus rhoncus, nunc lacus tempor eros, id laoreet eros nibh eget magna. Vestibulum eu nunc maximus, venenatis dolor sit amet, ornare mauris. Proin a ornare justo. Ut vel velit eu nisl interdum aliquam. Nunc quis lectus urna. Ut non urna nunc. Curabitur non arcu elementum, convallis lacus nec, convallis lacus. Nullam posuere purus non ligula pulvinar dictum. Proin porta scelerisque mi. ', 'preprocess-result' => 'Processed Text 11', 'similarity' => 0.75],
      ['source' => 'Source12' . (!empty($sources) ? implode(", ", $sources) : "None") . $method . $keyword, 'original-text' => 'Nullam in laoreet lorem. Aliquam rutrum tincidunt lorem, vitae volutpat nunc elementum malesuada. Maecenas congue id leo eu efficitur. Duis id enim accumsan, rhoncus neque id, hendrerit diam. Duis et faucibus nisl, sit amet convallis felis. Aenean posuere erat in fermentum ultrices. Sed iaculis massa ut suscipit viverra. Etiam in venenatis urna, quis hendrerit erat. ', 'preprocess-result' => 'Processed Text 12', 'similarity' => 0.80],
    ];

    $_SESSION['data'][$sessionKey] = $data;
  }

  $data = $_SESSION['data'][$sessionKey];

  // setiap request, direset page = 1
  if (!isset($_SESSION['last_request']) || $_SESSION['last_request'] !== $sessionKey) {
    $page = 1;
    $_SESSION['last_request'] = $sessionKey;
  }

  $content_per_page = 5;
  $total_content = count($data);
  $total_pages = ceil($total_content / $content_per_page);
  $offset = ($page - 1) * $content_per_page;
  
  if ($offset < 0 || $offset >= $total_content) {
    $offset = 0; // Reset offset if out of bounds
    $page = 1; // Reset to first page
}

  $pageData = array_slice($data, $offset, $content_per_page);

  $response = [
    'keyword' => $keyword,
    'sources' => $sources,
    'method' => $method,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'data' => $pageData,
  ];

  echo json_encode($response);
} else {
  echo json_encode(['error' => 'Invalid request method']);
}
