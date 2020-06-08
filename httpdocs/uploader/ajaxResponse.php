<?php
session_start();
$config = parse_ini_file( "../../scripts/config-live.ini", true );
$authorised = false;
header('Content-type:application/json;charset=utf-8');
//Check if there is still someone logged in
if(isset($_SESSION['username']) && $_SESSION['username'] == $config['uploader']['username']) {
    $authorised = true;
    if(isset($_SESSION['foldername'])) {
        $foldername = $_SESSION['foldername'];
    }
    else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'NO FOLDER'
        ]);
    }
}
else {
    $_SESSION['username'] = "";
    $authorised = false;
    session_destroy();
}

if($authorised) {
    
    try {
        if (
            !isset($_FILES['file']['error']) ||
            is_array($_FILES['file']['error'])
        ) {
            throw new RuntimeException('Invalid parameters.');
        }

        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No file sent.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('Exceeded filesize limit.');
            default:
                throw new RuntimeException('Unknown errors.');
        }
        //$folder = "../../uploads/" . $foldername;
        $folder = $config['uploader']['upload_dir'] . $foldername;
        if (!file_exists($folder)) {
            if (!mkdir($folder)) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unable to create folder'
                ]);
            }
        }
        $filepath = $folder . "/" . $_FILES['file']['name'];

        if (!move_uploaded_file(
            $_FILES['file']['tmp_name'],
            $filepath
        )) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        // All good, send the response
        echo json_encode([
            'status' => 'ok',
            'path' => $filepath
        ]);

    } catch (RuntimeException $e) {
            // Something went wrong, send the err message as JSON
            http_response_code(500);
            echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
            ]);
    }
    
} else {
    http_response_code(403);
    echo json_encode([
                    'status' => 'error',
                    'message' => 'NOT AUTHORISED'
            ]);
}
?>