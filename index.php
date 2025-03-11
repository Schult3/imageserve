<?php

// Pfad zur config.json Datei
$configFilePath = 'config.json';

// Lese die Datei ein
$configData = file_get_contents( $configFilePath );

// Dekodiere die JSON-Daten
$config = json_decode( $configData, true );

$uuid = $config[ "apikey" ];

if( !isset( $_REQUEST[ "apiKey" ] ) || $_REQUEST[ "apiKey" ] != $uuid ) {

    http_response_code( 403 );
    exit();

}


include_once __DIR__ . '\vendor\autoload.php';
include_once "templates/base.php";


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
    $files = $service->files->listFiles([
        'q' => "name='Big File'",
        'fields' => 'files(id,size)'
    ]);

    if (count($files) == 0) {
        echo "
      <h3 class='warn'>
        Before you can use this sample, you need to
        <a href='/large-file-upload.php'>upload a large file to Drive</a>.
      </h3>";
        return;
    }

    // If this is a POST, download the file
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Determine the file's size and ID
        $fileId = $files[0]->id;
        $fileSize = intval($files[0]->size);

        // Get the authorized Guzzle HTTP client
        $http = $client->authorize();

        // Open a file for writing
        $fp = fopen('Big File (downloaded)', 'w');

        // Download in 1 MB chunks
        $chunkSizeBytes = 1 * 1024 * 1024;
        $chunkStart = 0;

        // Iterate over each chunk and write it to our file
        while ($chunkStart < $fileSize) {
            $chunkEnd = $chunkStart + $chunkSizeBytes;
            $response = $http->request(
                'GET',
                sprintf('/drive/v3/files/%s', $fileId),
                [
                'query' => ['alt' => 'media'],
                'headers' => [
                'Range' => sprintf('bytes=%s-%s', $chunkStart, $chunkEnd)
                ]
                ]
            );
            $chunkStart = $chunkEnd + 1;
            fwrite($fp, $response->getBody()->getContents());
        }
        // close the file pointer
        fclose($fp);

        // redirect back to this example
        header('Location: ' . filter_var($redirect_uri . '?downloaded', FILTER_SANITIZE_URL));
    }

}

var_dump( $redirect_uri );
echo "<br><br>";

var_dump( $authUrl );
echo "<br><br>";

if ( isset( $authUrl ) ) {
  
  echo "<a class='login' href='" .$authUrl ."'>Connect Me!</a>";

} else {

  echo "Verbunden...";

}
?>

