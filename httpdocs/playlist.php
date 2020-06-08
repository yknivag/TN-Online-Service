<?php

//////////////////////////////////
// Automatic Playlist Generator //
// ============================ //
//                              //
// Author: Gavin Smalley        //
// Date:   1st Sept 2017        //
// Version: 1.1                 //
//                              //
//////////////////////////////////

//Parse Config
$config = parse_ini_file( "../../scripts/config-live.ini", true );

//Create log line

$log_line = "\"" . $_SERVER['REQUEST_TIME'] . "\",";
$log_line.= "\"" . date("r", $_SERVER['REQUEST_TIME']) . "\",";
$log_line.= "\"" . $_SERVER['REMOTE_ADDR'] . "\",";
$log_line.= "\"" . $_SERVER['GEOIP_ADDR'] . "\",";
$log_line.= "\"" . $_SERVER['GEOIP_COUNTRY_CODE'] . "\",";
$log_line.= "\"" . $_SERVER['HTTP_USER_AGENT'] . "\",";
$log_line.= "\"" . $_SERVER['SCRIPT_URI'] . "\",";
$log_line.= "\"" . $_GET['source'] . "\",";
$log_line.= "\"" . $_GET['type'] . "\",";
$log_line.= "\"" . $_GET['ref'] . "\",";
$log_line.= "\"" . $_GET['format'] . "\"" . PHP_EOL;

$handle = fopen("../redirect_logs/playlist-access.log", "a");
fwrite($handle, $log_line);
fclose($handle);

//set some basic variables
$base_url = $config['organisation']['mediaURL'];
$supported_formats = array(0=>"pls", 1=>"m3u", 2=>"m3u8", 3=>"asx", 4=>"wpl", 5=>"ram", 6=>"json");
$special_refs = array(0=>"latest", 1=>"prior1", 2=>"prior2", 3=>"prior3", 4=>"prior4", 5=>"prior5");

//check to see if the request is for a supported format
if (!in_array(strtolower($_GET['format']), $supported_formats)) {
    //if not a supported format we return a 404 to make it look as if the file simply wasn't found.
    header("HTTP/1.0 404 Not Found");
    exit("FORMAT_NOT_SUPPORTED");
}

//check to see if the request is for a "special reference" eg "latest".
if (in_array($_GET['ref'], $special_refs)) {
    $special_ref = true;
    $ref = array_search($_GET['ref'], $special_refs);
}
else {
    $special_ref = false;
    $ref = $_GET['ref'];
}

//check type and act accordingly for "edition" and "magazine". Error if neither.
switch (strtolower($_GET['type'])) {
    case "edition":
        //get available editions on server
        $editions = array_reverse(glob('media/[0123456789]*', GLOB_ONLYDIR));
        if ($special_ref) {
            if (array_key_exists($ref, $editions)) {
                $directory = $editions[$ref];
            }
            else {
                header("HTTP/1.0 404 Not Found");
                exit("EDITION_NOT_FOUND_FROM_KEYWORD");
            }
        }
        else {
            foreach($editions as $key => $value){
                if( stristr( $value, $ref ) ){
                    $edition_ref = $key;
                }
            }
            if (isset($edition_ref)) {
                $directory = $editions[$edition_ref];
            }
            else {
                header("HTTP/1.0 404 Not Found");
                exit("EDITION_NOT_FOUND_FROM_REFERENCE");
            }
        }
        $edition_details = explode("_", basename($directory));
        $title = "WTN - Edition " . $edition_details[0] . " - " . date("jS\ F\ Y", strtotime($edition_details[1]));
        break;
    case "magazine":
        //get available magazines on server
        $magazines = array_reverse(glob('media/M*', GLOB_ONLYDIR));
        if ($special_ref) {
            if (array_key_exists($ref, $magazines)) {
                $directory = $magazines[$ref];
            }
            else {
                header("HTTP/1.0 404 Not Found");
                exit("MAGAZINE_NOT_FOUND_FROM_KEYWORD");
            }
        }
        else {
            foreach($magazines as $key => $value){
                if( stristr( $value, $ref ) ){
                    $magazine_ref = $key;
                }
            }
            if (true) {
                $directory = $magazines[$magazine_ref];
            }
            else {
                header("HTTP/1.0 404 Not Found");
                exit("MAGAZINE_NOT_FOUND_FROM_REFERENCE");
            }
        }
        $magazine_details = explode("_", basename($directory));
        $title = "WTN - Magazine " . $magazine_details[0] . " - " . date("jS\ F\ Y", strtotime($magazine_details[1])) . " - " . str_replace("-", " ", $magazine_details[2]) . " - " . str_replace("-", " ", $magazine_details[3]);
        break;
    default:
        header("HTTP/1.1 500 Internal Server Error");
        exit("UNKNOWN_MEDIA_TYPE_REQUESTED");
}

//find file details
$files_to_match = $directory . "/*.mp3";
$files = glob($files_to_match);

//check format and generate appropriate format. Error if neither.
switch (strtolower($_GET['format'])) {
    case "pls":
        //set header to be a playlist
        header('Content-Type: audio/x-scpls');
        echo "[playlist]" . PHP_EOL;
        echo "mode=play" . PHP_EOL;
        $i=1;
        foreach ($files as $file) {
            echo "Title" . $i . "=" . $title . " - Track " . substr(basename($file), 0, -4) . PHP_EOL;
            echo "File" . $i . "=" . $base_url . $file . PHP_EOL;
            $i++;
        }
        echo PHP_EOL;
        echo "NumberOfEntries=" . ($i-1) . PHP_EOL;
        echo "Version=2";
        break;
    case "m3u":
    case "m3u8":
        //set header to be a playlist
        header('Content-Type: audio/mpegurl');
        echo "#EXTM3U" . PHP_EOL;
        echo PHP_EOL;
        foreach ($files as $file) {
            echo "#EXTINF:" . $title . " - Track " . substr(basename($file), 0, -4) . PHP_EOL;
            echo $base_url . $file . PHP_EOL;
        }    
        break;
    case "asx":
        //set header to be a playlist
        header('Content-Type: video/x-ms-asf');
        echo '<ASX Version = "3.0">' . PHP_EOL;
        foreach ($files as $file) {
            echo "<ENTRY>" . PHP_EOL;
            echo "<TITLE>" . $title . "- Track " . substr(basename($file), 0, -4) . "</TITLE>" . PHP_EOL;
            echo "<AUTHOR>Witney Talking News</AUTHOR>" . PHP_EOL;
            echo '<REF HREF = "' . $base_url . $file . '" />' . PHP_EOL;
        }
        echo "</ASX>" . PHP_EOL;
        break;
    case "wpl":
        //set header to be a playlist
        header('Content-Type: application/vnd.ms-wpl');
        echo '<?wpl version="1.0"?>' . PHP_EOL . '<smil>' . PHP_EOL . '<head>' . PHP_EOL;
        echo '<author>Witney Talking News</author>' . PHP_EOL;
        echo "<title>$title</title>" . PHP_EOL;
        echo '<meta name="ItemCount" content="' . count($files) . '"' . PHP_EOL;
        echo '</head>' . PHP_EOL . '<body>' . PHP_EOL . '<seq>' . PHP_EOL;
        foreach ($files as $file) {
            echo '<media src="' . $base_url . $file . '"/>' . PHP_EOL;
        }
        echo '</seq>' . PHP_EOL . '</body>' . PHP_EOL . '</smil>' . PHP_EOL;
        break;
    case "ram":
        //set header to be a playlist
        header('Content-Type: audio/x-pn-realaudio');
        foreach ($files as $file) {
            echo $base_url . $file . PHP_EOL;
        }
        break;
    case "json":
        //set header to be a json
        header('Content-Type: application/json');
        $response = array();
        $j=0;
        foreach ($files as $file) {
            $response[$j]["Track"] = $j+1;
            $response[$j]["Artist"] = "Witney Talking News";
            $response[$j]["Album"] = $title;
            $response[$j]["Title"] = "Track " . substr(basename($file), 0, -4);
            $response[$j]["URL"] = $base_url . $file;
            $j++;
        }
        echo json_encode($response);
        break;
    default:
        header("HTTP/1.0 404 Not Found");
        exit("FORMAT_NOT_SUPPORTED");
}

?>