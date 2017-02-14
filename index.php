<?php
ini_set('max_execution_time', 300);
// Load the Google API PHP Client Library.
require_once __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/excel_reader/read_and_filter_excel.php';
require_once __DIR__ . '/excel_export/Classes/PHPExcel.php';

$excel = new PhpExcelReader;
$excel->read('apen_all_pages.xls');
$apenAllPages = sheetData($excel);

$verwijderen = array();
$blijven = array();
$brokenUrls = array();




// Start a session to persist credentials.
session_start();



// Create the client object and set the authorization configuration
// from the client_secretes.json you downloaded from the developer console.
$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/client_secrets.json');
$client->addScope(Google_Service_Analytics::ANALYTICS_READONLY);

// If the user has already authorized this app then get an access token
// else redirect to ask the user to authorize access to Google Analytics.
if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
  // Set the access token on the client.
  $client->setAccessToken($_SESSION['access_token']);

  // Create an authorized analytics service object.
  $analytics = new Google_Service_Analytics($client);

  // Get the first view (profile) id for the authorized user.
  $profile = getFirstProfileId($analytics);

  $whileLoop = true;
  $count = 0;
  $total = array();
  while ($whileLoop) {
    $start_index = $count * 10000 + 1;
    $results = getResults($analytics, $profile, $start_index);
    $results = $results->getRows();
    var_dump($start_index);
    if ($results == NULL || count($results) < 1) {
      $whileLoop = false;
    }
    else {
      $total = array_merge($total, $results);
      $count = $count + 1;
    } 
  }
  // for ($i=0; $i < 5 ; $i++) { 
  //   $start_index = $count * 1000 + 1;
  //   $results = getResults($analytics, $profile, $start_index);
  //   $results = $results->getRows();
  //   var_dump($start_index);
  //   if ($results == NULL || count($results) < 1) {
  //     $whileLoop = false;
  //   }
  //   else {
  //     $total = array_merge($total, $results);
  //     $count = $count + 1;
  //   } 
  // }
  
  $patterns = array('#\?#', '#\/edit#', '#\/delete\/#', '#\/search\/#', '#\/webform\/#', '#\/users\/#', '#\/user\/#', '#\/delete#', '#\/repeats#', '#\/attach\/#', '#\/attachcomplete#', '#\/batch#', '#\/comment\/#', '#\/abuse\/#', '#\/category\/#');
  foreach ($total as $key=>$row) {
    $pregMatch = false;
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $row[0])) {
        $pregMatch = true;
      }
    }
    if ($pregMatch) {
      unset($total[$key]);
    }
  }
  $total = array_values($total);
  foreach ($total as $GAIndex=>$GAPage) {
    $magBlijven = false;
    foreach ($apenAllPages as $apenIndex=>$apenPage) {
      if ($GAPage[0] == $apenPage) {
        // var_dump($apenPage);
        // var_dump($GAPage[0]);
        $magBlijven = true;
      }
    }
    if ($magBlijven) {
      array_push($blijven, $GAPage[0]);
    }
    else {
      array_push($verwijderen, $GAPage[0]);
    }
  }
  $brokenPatterns = array('# -#', '#\+#', '#- #');
  foreach ($verwijderen as $key=>$value) {
    $brokenUrl = false;
    foreach ($brokenPatterns as $pattern) {
      if (preg_match($pattern, $value)) {
        $brokenUrl = true;
      }
    }
    if ($brokenUrl) {
      unset($verwijderen[$key]);
    }
  }
  $verwijderen = array_values($verwijderen);

  // $filename = 'webdata_' . date('Ymd') . '.csv';

  // header("Content-Disposition: attachment; filename=\"$filename\"");
  // header("Content-Type: application/octet-stream"); 
  // // that indicates it is binary so the OS won't mess with the filename
  // // should work for all attachments, not just excel

  // $out = fopen("php://output", 'w');  // write directly to php output, not to a file
  // fputcsv($out, $verwijderen);
  // fclose($out);

// Create new PHPExcel object
$objPHPExcel = new PHPExcel();
// Set document properties
$objPHPExcel->getProperties()->setCreator("Maarten Balliauw")
               ->setLastModifiedBy("Maarten Balliauw")
               ->setTitle("Office 2007 XLSX Test Document")
               ->setSubject("Office 2007 XLSX Test Document")
               ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
               ->setKeywords("office 2007 openxml php")
               ->setCategory("Test result file");

  foreach ($verwijderen as $key=>$value) {
    $rownumber = $key+1;
    $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A' . $rownumber, $value);
  }
  
 // Redirect output to a client’s web browser (Excel2007)
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Type: text/html; charset=UTF-8');
  header('Content-Disposition: attachment;filename="01simple.xlsx"');
  header('Cache-Control: max-age=0');
  // If you're serving to IE 9, then the following may be needed
  header('Cache-Control: max-age=1');
  // If you're serving to IE over SSL, then the following may be needed
  header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
  header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
  header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
  header ('Pragma: public'); // HTTP/1.0
  $objPHPExcel = mb_convert_encoding($objPHPExcel,'UTF-8');
  $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
  $objWriter->save('php://output');

  // $rows = $total; array_push($verwijderen, $apenPage);
} else {
  $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/apen.be/test/oauth2callback.php';
  header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}


function getFirstProfileId($analytics) {
  // Get the user's first view (profile) ID.

  // Get the list of accounts for the authorized user.
  $accounts = $analytics->management_accounts->listManagementAccounts();

  if (count($accounts->getItems()) > 0) {
    $items = $accounts->getItems();
    $firstAccountId = $items[0]->getId();

    // Get the list of properties for the authorized user.
    $properties = $analytics->management_webproperties
        ->listManagementWebproperties($firstAccountId);

    if (count($properties->getItems()) > 0) {
      $items = $properties->getItems();
      $firstPropertyId = $items[0]->getId();

      // Get the list of views (profiles) for the authorized user.
      $profiles = $analytics->management_profiles
          ->listManagementProfiles($firstAccountId, $firstPropertyId);

      if (count($profiles->getItems()) > 0) {
        $items = $profiles->getItems();

        // Return the first view (profile) ID.
        return $items[0]->getId();

      } else {
        throw new Exception('No views (profiles) found for this user.');
      }
    } else {
      throw new Exception('No properties found for this user.');
    }
  } else {
    throw new Exception('No accounts found for this user.');
  }
}

function getResults($analytics, $profileId, $start_index) {

  $metrics = 'ga:pageviews';
  $dimensions = 'ga:pagePath';
  $sort = 'ga:pageviews';
  $max_results = 10000;
  $start_index = $start_index;
  return $analytics->data_ga->get(
        'ga:'.$profileId,
        '365daysAgo',
        'today',
        $metrics, 
        array('dimensions' => $dimensions,'sort'=>$sort, 'max-results'=>$max_results, 'start-index'=>$start_index));
}

?>

<!DOCTYPE html>
<html>
<head>
  <title>
  </title>
</head>
<body>
  <h1>BLIJVEN</h1>
  <table>
    <thead>
      <tr>
        <th>Number</th>
        <th>Paginas</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($blijven as $key=>$row): ?>
        <tr>
            <td><?= $key ?></td>
            <td><?= $row ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <h2>VERWIJDEREN</h2>
  <table>
    <thead>
      <tr>
        <th>Number</th>
        <th>Paginas</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($verwijderen as $key=>$row): ?>
        <tr>
            <td><?= $key ?></td>
            <td><?= $row ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
<!--   </table>
  <h1>ALLE GApagina's</h1>
  <table>
    <thead>
      <tr>
        <th>Number</th>
        <th>Paginas</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($test as $key=>$row): ?>
        <tr>
            <td><?= $key ?></td>
            <td><?= $row[0] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <h1>ALLE ONLINE PAGINA'S</h1>
  <table>
    <thead>
      <tr>
        <th>Number</th>
        <th>Paginas</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($apenAllPages as $key=>$row): ?>
        <tr>
            <td><?= $key ?></td>
            <td><?= $row ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table> -->
</body>
</html>