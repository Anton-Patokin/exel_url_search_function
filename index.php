<?php
// Load the Google API PHP Client Library.
require_once __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/excel_reader/read_and_filter_excel.php';

$excel = new PhpExcelReader;
$excel->read('apen_all_pages.xls');
$apenAllPages = sheetData($excel);

$verwijderen = array();
$blijven = array();


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
  // while ($whileLoop) {
  //   $start_index = $count * 10000 + 1;
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

  for ($i=0; $i < 5 ; $i++) { 
    $start_index = $count * 1000 + 1;
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
  
  foreach ($total as $GAPage) {
    $magBlijven = false;
    foreach ($apenAllPages as $apenPage) {
      if ($GAPage[0] == $apenPage) {
        var_dump($apenPage);
        $magblijven = true;
      }
    }
    if ($magBlijven) {
      array_push($blijven, $GAPage[0]);
    }
    else {
      array_push($verwijderen, $GAPage[0]);
    }
  }
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
  $max_results = 1000;
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
  </table>
</body>
</html>