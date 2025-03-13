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
$token_file = 'refresh-token.json';

if( !isset( $_REQUEST[ "apiKey" ] ) || $_REQUEST[ "apiKey" ] != $uuid ) {

    http_response_code( 403 );
    exit();

}

include_once __DIR__ . '/vendor/autoload.php';
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

    resizeImage( rotateImage( $dest ."/" .$fileName ) );
    showFirstImage( $dest ."/tmp.jpg" );

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

    global $minutes;

    header('Content-Type: image/jpeg');
    // Setze die Content-Length Header
    header('Content-Length: ' . filesize( $filePath ) );
    header('Cache-Control: no-cache, must-revalidate');

    // Aktuelle Zeit
    $dt = new DateTime();
    $interval = new DateInterval('PT' .$minutes .'M');
    $dt->add( $interval );

    // Erstelle ein DateTimeZone-Objekt für GMT
    $gmtTimezone = new DateTimeZone('GMT');

    // Setze die Zeitzone des DateTime-Objekts auf GMT
    $dt->setTimezone($gmtTimezone);

    $expiresHeader = $dt->format( 'D, d M Y H:i:s' );

    // Setzen des Expires-Headers
    header('Expires: ' . $expiresHeader ." GMT");
    header('Pragma: no-cache');

    // Lese die Datei und liefere sie aus
    readfile( $filePath );

}



function rotateImage( $filename ) {

    global $directoryPath;

    $exif = exif_read_data($filename);

    // Load
    $source = imagecreatefromjpeg($filename);

    if ( !empty( $exif[ 'Orientation' ] ) ) {

        switch ( $exif[ 'Orientation' ] ) {

            case 3:
                $source = imagerotate($source, 180, 0);
                break;
            case 6:
                $source = imagerotate($source, -90, 0);
                break;
            case 8:
                $source = imagerotate($source, 90, 0);
                break;

        }

    }

    // Gedrehtes Bild speichern
    imagejpeg( $source, $directoryPath .'/tmp.jpg' );

    return $directoryPath .'/tmp.jpg';

}


function resizeImage( $filename ) {

    // Get new sizes
    list($width, $height) = getimagesize($filename);

    $newwidth = $width;
    $newheight = $height;

    $maxWidth = 1280;

    if( $width >= $maxWidth ) {

        $rel = $maxWidth / $width;
        $newwidth = $maxWidth;
        $newheight = $height * $rel;
    
    }

    // Load
    $source = imagecreatefromjpeg($filename);

    $thumb = imagecreatetruecolor($newwidth, $newheight);
    
    // Resize
    imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

    // Output
    imagejpeg( $thumb, $filename );

}


echo pageHeader();


$filePath = $directoryPath ."/tmp.jpg";

if( file_exists( $filePath ) ) {

    $modificationTime = filemtime( $filePath );

    // Erstellen Sie ein DateTime-Objekt für den Timestamp
    $timestampDateTime = new DateTime();
    $timestampDateTime->setTimestamp( $modificationTime );
        
    // Erstellen Sie ein DateTime-Objekt für die aktuelle Zeit
    $currentDateTime = new DateTime();

    // Berechnen Sie den Unterschied zwischen den beiden DateTime-Objekten
    $interval = $currentDateTime->diff( $timestampDateTime );


    // Wenn Datei noch aktuell, dann Bild ausliefern
    if( $interval->h <= 0 && $interval->i < $minutes ) {

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

// $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] ."?apiKey=" .$uuid;
$redirect_uri = 'https://stefan-schulte.com/imageserve/index.php?apiKey=' .$uuid;

$client = new Google\Client();
$client->setAuthConfig( $oauth_credentials );
$client->setRedirectUri( $redirect_uri );
$client->addScope("https://www.googleapis.com/auth/drive");
$service = new Google\Service\Drive( $client );



// Token aus der Datei lesen
if ( file_exists( $token_file ) ) {

    $token = json_decode( file_get_contents( $token_file ), true )[ "refresh-token" ];
    $client->fetchAccessTokenWithRefreshToken( $token );

} else if (isset( $_GET[ 'code' ] ) ) {

    $token = $client->fetchAccessTokenWithAuthCode( $_GET['code'], $_SESSION[ "code_verifier" ] );

    $client->setAccessToken( $token );
    $accessToken = $client->getAccessToken();

    // redirect back to the example
    header( 'Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL) );

} else {

    $_SESSION['code_verifier'] = $client->getOAuth2Service()->generateCodeVerifier();
    $authUrl = $client->createAuthUrl();

}


if ($client->getAccessToken()) {

    // Check for "Big File" and include the file ID and size
    $tmp = getRandomFileFromFolder( $service, "1lzj5Co-lKvDH-8yB8NOz0MWXZuoePVvB", $directoryPath );
    exit();

}


if ( isset( $authUrl ) ) {

    // header( 'Location: ' . filter_var( $authUrl, FILTER_SANITIZE_URL ) );
    echo "<a class='login' href='" .$authUrl ."'>Connect Me!</a>";

}
?>

