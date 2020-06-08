<?php

//////////////////////////////////
// Upload Processor Stage 1     //
// ========================     //
//                              //
// Author: Gavin Smalley        //
// Date:   11th Nov 2017        //
// Version: 1.0                 //
//                              //
//////////////////////////////////
//                              //
// Stage 1 of a 3 stage process //
//                              //
// See README.md in root dir    //
//   for full details.          //
//                              //
//////////////////////////////////

//Load Config

$config = parse_ini_file( "config-live.ini", true );

//Load Required Libraries

ini_set ( "include_path", '/home/witneytn/php:' . ini_get ( "include_path" ) );
require_once ( "MP3/Id.php" );
require_once ( "ID3/getid3/getid3.php" );
require_once('ID3/getid3/write.php');
require_once ( "functions.php" );
require_once ( 'transloadit/vendor/autoload.php' );
use transloadit\Transloadit;

//Log Function Useage
//echo_log($publication, $log, $text)
//appends "[DATE] - $text" to the file "$config['general']['log_dir']/$publication/$log"

//Build lists of publications to process

$publications_on_device = glob($config['general']['upload_dir'] . $config['glob_patterns']['publications'], GLOB_ONLYDIR);

//Iterate through publications one by one.

foreach ( $publications_on_device as $pub_dir ) {
    $publication = basename ( $pub_dir );
    $publication_data = explode ( "_", $publication );
    //Check if log folder exists, if it doesn't this is a new upload and we can process it as such
    //by checking the date format and creating a log folder.
    if ( !file_exists ( $config['general']['log_dir'] . $publication ) ) {
        //Create the log folder and loop to the next to process if that isn't possible.
        if ( !mkdir ( $config['general']['log_dir'] . $publication, 0755, true ) ) {
            echo "FATAL ERROR: Creation of log directory failed for " . $publication;
            continue;
        }
        //Log some basic details
        echo_log ( $publication, "general.log", "Working on: " . $pub_dir );
        echo_log ( $publication, "general.log", "Name of publication: " . $publication );
        //Before we do anything more we check that the date is in a valid format!
        echo_log ( $publication, "general.log", "Raw Date: " . $publication_data[1] );
        if ( strtotime ( $publication_data[1] ) <= strtotime ( $config['organisation']['firstPub'] ) ) {
            //The date portion of the filename is not in a format that PHP can understand.
            //If date invalid and we cannot correct it, place a dummy entries in the log file to stop it being processed.
            //This prevents invalid posts.  Once the date is corrected a new log folder with the corrent name will be
            //created and it will be processed properly.
            echo_log ( $publication, "general.log", "It was not possible to correct the date format" );
            touch ( $config['general']['log_dir'] . $publication . '/DATE_INVALID' );
            touch ( $config['general']['log_dir'] . $publication . '/transloadit_request.log' );
            touch ( $config['general']['log_dir'] . $publication . '/transloadit_response.log' );
            touch ( $config['general']['log_dir'] . $publication . '/.copied' );
            touch ( $config['general']['log_dir'] . $publication . '/.tagged' );
            touch ( $config['general']['log_dir'] . $publication . '/.uploaded' );
            touch ( $config['general']['log_dir'] . $publication . '/.posted' );
            touch ( $config['general']['log_dir'] . $publication . '/.corrected' );
            touch ( $config['general']['log_dir'] . $publication . '/.emailed' );
            continue;
        }
        else {
            //The date portion of the filename was recognisable as a date in PHP but we still need to check it
            //is in the correct format before proceeding.
            $publication_data[1] = date ( "d\-m\-Y", strtotime ( $publication_data[1] ) );
            $pub_test = implode ( "_", $publication_data );
            if ( $publication !== $pub_test ) {
                //The date wasn't in the correct format in the filename so we correct it before proceeding.
                echo_log ( $publication, "general.log", "Date format valid but incorrect." );
                echo_log ( $publication, "general.log", "Changing From: " . $publication . ". To: ". $pub_test );
                rename ( $pub_dir, dirname ( $pub_dir ) . $pub_test );
                rename ( $config['general']['log_dir'] . $publication, $config['general']['log_dir'] . $pub_test );
                $publication = $pub_test;
                $publication_data = explode ("_", $publication );
            }
        }
    }
    //Determine type (Edition|Magazine) as some things are done differently.
    if ( substr ( $publication, 0, 1 ) === $config['glob_patterns']['magazine_prefix'] ) {
        $type = "Magazine";
        $comment = date ( "d\-m\-Y", strtotime ( $publication_data[1] ) ) . " - " . str_replace ( "-", " ", $publication_data[2] );
    } else {
        $type = "Edition";
        $comment = date ( "d\-m\-Y", strtotime ( $publication_data[1] ) );
    }
    echo_log ( $publication, "general.log", "Identified as: " . $type );
    //Set up metadata
    $album = $config['organisation']['org'] . " - " . $type . " " . $publication_data[0];
    $year  = date ( "Y", strtotime ( $publication_data[1] ) );
    $name_prefix = $type . " " . $publication_data[0] . " - Track ";
    //Initiate transloadit if it hasn't been done already
    if ( !file_exists ( $config['general']['log_dir'] . $publication . '/transloadit_response.log' ) ) {
        $transloadit = new Transloadit ( array (
            "key"    => $config['transloadit']['key'],
            "secret" => $config['transloadit']['secret'],
        ) );
        $request = array (
            "params" => array (
                "steps" => array (
                    "imported" => array (
                        "robot"    => "/ftp/import",
                        "host"     => $config['transloadit']['host'],
                        "user"     => $config['transloadit']['username'],
                        "password" => $config['transloadit']['password'],
                        "path"     => $config['transloadit']['import_path'] . $publication . "/"
                    ),
                    "concatenated" => array(
                        "robot"   => "/audio/concat",
                        "use"     => array ( 
                            "steps" => array ( "imported" ),
                            "bundle_steps" => "true" ),
                        "preset"  => "mp3",
                        "bitrate" => $config['transloadit']['bitrate'],
                        "result"  => true,
                    ),
                    "exported" => array (
                        "use"      => array ( "concatenated" ),
                        "robot"    => "/ftp/store",
                        "host"     => $config['transloadit']['host'],
                        "user"     => $config['transloadit']['username'],
                        "password" => $config['transloadit']['password'],
                        "path"     => $config['transloadit']['export_path'] . $publication . ".mp3",
                    ),
                )
            )
        );
        echo_log ( $publication, "transloadit_request.log", json_encode ( $request ) );
        $response = $transloadit->createAssembly( $request );
        echo_log ( $publication, "transloadit_response.log", json_encode ( $response ) );
        echo_log ( $publication, "general.log", "Transloadit Request sent, see separate logs." );
    }
    //Copy files to media folder for streaming playlists
    if ( !file_exists ( $config['general']['log_dir'] . $publication . '/.copied' ) ) {
        echo_log ( $publication, "general.log", "Copying & Tagging Commenced." );
        $new_dir = $config['general']['media_dir'] . $publication;
        mkdir( $new_dir, 0755, true );
        $files = glob( $pub_dir . "/*.mp3" );
        foreach ( $files as $file ) {
            $new_file = $new_dir . "/" . substr(basename($file), 0, 3) . ".mp3";
            if ( copy ( $file, $new_file ) ) {
                echo_log ( $publication, ".copied", "SUCCESS: " . $file . " -> " . $new_file );
                chmod($new_file, 0664);
                //Correct ID3 tags.
                $track = substr ( basename ( $new_file ), 0, 3 );
                $name = $name_prefix . $track;
                tag_file($new_file, $album, $name, $track, $year, $comment);
                $tag_data = "Album: " . $album . "|Name: " . $name . "|Track: " . $track . "|Year: " . $year . "|Comment: " . $comment;
                echo_log ( $publication, ".tagged", "[" . $new_file . "] - " . $tag_data );
            } else {
                echo_log ( $publication, ".copied", "ERROR: " . $file . " -> " . $new_file );
                echo_log ( $publication, "general.log", "ERROR: Error copying " . $file . " -> " . $new_file );
            }
        }
        echo_log ( $publication, "general.log", "Copying & Tagging Complete." );
    }
}

//This is as far as we can go in this stage as we need to wait now for transloadit.
//stage2.php will take the result from transloadit, upload it to archive.org and
//post to Wordpress.
//stage3.php will then run to correct the 'eclosure' field in WP.

?>