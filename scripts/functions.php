<?php

// Logging Function
function echo_log($publication, $log, $text) {
    
    if ( !file_exists ( $GLOBALS['config']['general']['log_dir'] . $publication ) ) {
        if ( !mkdir ( $GLOBALS['config']['general']['log_dir'] . $publication, 0755, true ) ) {
            echo "FATAL ERROR: Creation of log directory failed for " . $publication . PHP_EOL;
            return;
        }
    }
    
    $entry = "[" . date ( "Y\/m\/d\ H\:i\:s" ) . "] " . $text . PHP_EOL;
    $log_handle = fopen ( $GLOBALS['config']['general']['log_dir'] . "/$publication/$log", 'a' );
    if ( !fwrite($log_handle, $entry ) ) {
        echo "ERROR: Creation of or updating of " . $log . " log file failed for " . $publication . PHP_EOL;
    }
    fclose($log_handle);
}

//Function to use PEAR MP3 ID tag manager to update ID3 tags.
function tag_fil_old($filename, $album, $name, $track, $year, $comment) {
    $mp3 = new MP3_id();
    $mp3->read($filename);
    $mp3->setTag("artists", $GLOBALS['config']['metadata']['artists']);
    $mp3->setTag("genre", $GLOBALS['config']['metadata']['genre']);
    $mp3->setTag("genreno", $GLOBALS['config']['metadata']['genreno']);
    $mp3->setTag("album", $album);
    $mp3->setTag("name", $name);
    $mp3->setTag("track", $track);
    $mp3->setTag("year", $year);
    $mp3->setTag("comment", $comment);
    $mp3->write();
}

//Function to use getID3.php library to update ID3 tags.
function tag_file ( $filename, $album, $name, $track, $year, $comment ) {
    $getID3 = new getID3;
    $getID3->setOption ( array ( 'encoding'=>'UTF-8' ) );
    $tagwriter = new getid3_writetags;
    $tagwriter->filename = $filename;
    $tagwriter->tagformats = array ( 'id3v1', 'id3v2.3' );
    $tagwriter->overwrite_tags    = true;  // if true will erase existing tag data and write only passed data; if false will merge passed data with existing tag data (experimental)
    $tagwriter->remove_other_tags = false; // if true removes other tag formats (e.g. ID3v1, ID3v2, APE, Lyrics3, etc) that may be present in the file and only write the specified tag format(s). If false leaves any unspecified tag formats as-is.
    $tagwriter->tag_encoding      = 'UTF-8';
    $tagwriter->remove_other_tags = true;
    $TagData = array(
	'title'   => array ( $name ),
	'artist'  => array ( $GLOBALS['config']['metadata']['artists'] ),
	'album'   => array ( $album ),
	'year'    => array ( $year ),
	'genre'   => array ( $GLOBALS['config']['metadata']['genre'] ),
        'genreno' => array ( $GLOBALS['config']['metadata']['genreno'] ),
	'comment' => array ( $comment ),
	'track'   => array ( $track )
    );
    $tagwriter->tag_data = $TagData;
    if ( $tagwriter->WriteTags() ) {
	return true;
    } else {
	return serialize ( $tagwriter->errors );
    }   
}

?>