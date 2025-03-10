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

    require 'vendor/autoload.php';

    use Google\Client;
    use Google\Service\Drive;

    
    function getClient() {

        $client = new Client();
        $client->setApplicationName('Google Drive API PHP Quickstart');
        $client->setScopes(Drive::DRIVE_READONLY);
        $client->setAuthConfig('credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        $tokenPath = 'token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($accessToken));
        }
        return $client;
        
    }


    $client = getClient();
    $service = new Drive($client);

    // Get the list of files in the specified folder.
    $response = $service->files->listFiles([
        'q' => "'$folderId' in parents and mimeType contains 'image/'",
        'fields' => 'files(id, name)'
    ]);

    exit();


    // Pfad zum Ordner 'image'
    $directoryPath = 'image';

    // Hole die erste Datei im Ordner
    $files = glob($directoryPath . '/*');
    if (!empty( $files ) ) {
        
        $filePath = $files[0];

        // Bestimme den Dateityp anhand der Dateierweiterung
        $fileExtension = pathinfo( $filePath, PATHINFO_EXTENSION );
        // var_dump( $fileExtension );
        // var_dump( filesize( $filePath ) );
        // exit();

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
    } else {
        // Fehlerbehandlung, falls keine Datei im Ordner vorhanden ist
        echo "Keine Datei im Ordner gefunden.";
    }

?>