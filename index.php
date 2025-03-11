<?php

// Pfad zur config.json Datei
$configFilePath = 'config.json';

// Lese die Datei ein
$configData = file_get_contents( $configFilePath );

// Dekodiere die JSON-Daten
$config = json_decode( $configData, true );

$uuid = $config[ "apikey" ];
$minutes = intval( $config[ "minutes" ] );
$directoryPath = $config[ "imagePath" ];

if( !isset( $_REQUEST[ "apiKey" ] ) || $_REQUEST[ "apiKey" ] != $uuid ) {

    http_response_code( 403 );
    exit();

}

include_once __DIR__ . '\vendor\autoload.php';
include_once "templates/base.php";


function getRandomFileFromFolder( $service, $folderId, $dest ) {

    $optParams = array(
        'q' => "'$folderId' in parents",
        'fields' => 'files(id, name)'
    );
    $results = $service->files->listFiles($optParams);

    if (count($results->files) == 0) {
        throw new Exception('No files found in the specified folder.');
    }

    $randomFile = $results->files[array_rand($results->files)];

    $fileName = $randomFile->name;
    $fileID = $randomFile->id;

    $content = $service->files->get( $fileID, array( 'alt' => 'media' ) );


    file_put_contents( $dest ."/" .$fileName, $content->getBody()->getContents() );

    showFirstImage( $dest ."/" .$fileName );

}


function clearImageFolder( $folderPath ) {

    // Überprüfen, ob der Ordner existiert
    if (!is_dir($folderPath)) {
        throw new Exception("Der Ordner existiert nicht: $folderPath");
    }

    // Alle Dateien im Ordner auflisten
    $files = glob($folderPath . '/*');

    // Jede Datei löschen
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

}


function showFirstImage( $filePath ) {

  // Bestimme den Dateityp anhand der Dateierweiterung
  $fileExtension = pathinfo( $filePath, PATHINFO_EXTENSION );

  // Setze die richtigen Header basierend auf dem Dateityp
  switch ($fileExtension) {
      case 'JPG':
          header('Content-Type: image/jpeg');
          break;
      case 'jpg':
          header('Content-Type: image/jpeg');
          break;
      case 'jpeg':
          header('Content-Type: image/jpeg');
          break;
      case 'png':
          header('Content-Type: image/png');
          break;
      case 'gif':
          header('Content-Type: image/gif');
          break;
      case 'bmp':
          header('Content-Type: image/bmp');
          break;
      case 'webp':
          header('Content-Type: image/webp');
          break;
      default:
          // Fehlerbehandlung für nicht unterstützte Dateitypen
          echo "Nicht unterstützter Dateityp.";
          exit;
  }

  // Setze die Content-Length Header
  header('Content-Length: ' . filesize( $filePath ) );

  // Lese die Datei und liefere sie aus
  readfile($filePath);

}


echo pageHeader();



// Wenn Datei noch aktuell, dann Bild ausliefern
if( !empty( $files ) ) {

  $files = glob($directoryPath . '/*');
  $filePath = $files[0];
  
  $modificationTime = filemtime( $filePath );

  // Erstellen Sie ein DateTime-Objekt für den Timestamp
  $timestampDateTime = new DateTime();
  $timestampDateTime->setTimestamp( $modificationTime );
      
  // Erstellen Sie ein DateTime-Objekt für die aktuelle Zeit
  $currentDateTime = new DateTime();

  // Berechnen Sie den Unterschied zwischen den beiden DateTime-Objekten
  $interval = $currentDateTime->diff( $timestampDateTime );


  // Wenn Datei noch aktuell, dann Bild ausliefern
  if( $interval->i < $minutes ) {

    showFirstImage( $filePath );
    exit();

  }


}



// Ansonsten Bild entfernen und aus Google-Drive neues abholen
clearImageFolder( $directoryPath );

if ( !$oauth_credentials = getOAuthCredentialsFile() ) {

    echo missingOAuth2CredentialsWarning();
    return;

}


$redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] ."?apiKey=" .$uuid;

$client = new Google\Client();
$client->setAuthConfig($oauth_credentials);
$client->setRedirectUri($redirect_uri);
$client->addScope("https://www.googleapis.com/auth/drive");
$service = new Google\Service\Drive($client);

if (isset($_GET['code'])) {

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code'], $_SESSION['code_verifier']);
    $client->setAccessToken($token);

    // store in the session also
    $_SESSION['upload_token'] = $token;

    // redirect back to the example
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));

}

// set the access token as part of the client
if (!empty($_SESSION['upload_token'])) {

    $client->setAccessToken($_SESSION['upload_token']);
    if ($client->isAccessTokenExpired()) {

        unset($_SESSION['upload_token']);

    }

} else {

    $_SESSION['code_verifier'] = $client->getOAuth2Service()->generateCodeVerifier();
    $authUrl = $client->createAuthUrl();

}


if ($client->getAccessToken()) {

    // Check for "Big File" and include the file ID and size
    $tmp = getRandomFileFromFolder( $service, "1-j8dlP6CsHCyibkvtE4_tm-yjKL_rkDj", $directoryPath );

    // var_dump( $files );
    echo "Verbunden...";
    exit();

}


if ( isset( $authUrl ) ) {
  
  echo "<a class='login' href='" .$authUrl ."'>Connect Me!</a>";

}
?>

