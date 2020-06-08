<?php
//////////////////////////////////
// Automatic Link Generator     //
// ========================     //
//                              //
// Author: Gavin Smalley        //
// Date:   22nd Nov 2017        //
// Version: 1.0                 //
//                              //
//////////////////////////////////

//Parse Config
$config = parse_ini_file( "../../scripts/config-live.ini", true );

//set some basic variables
$base_url = $config['organisation']['baseURL'];
$supported_formats = array ( 0=>"mp3", 1=>"html", 2=>"htm" );
$base_urls = array ( "mp3"=>"https://archive.org/download/", "html"=> $base_url, "htm"=> $base_url );
$special_refs = array ( 0=>"latest", 1=>"prior1", 2=>"prior2", 3=>"prior3", 4=>"prior4", 5=>"prior5" );

//check to see if the request is for a supported format
if ( !in_array(strtolower($_GET['format']), $supported_formats ) ) {
    //if not a supported format we return a 404 to make it look as if the file simply wasn't found.
    header ( "HTTP/1.0 404 Not Found" );
    exit ( "FORMAT_NOT_SUPPORTED" );
}

//check to see if the request is for a "special reference" eg "latest".
if ( in_array ( $_GET['ref'], $special_refs ) ) {
    $special_ref = true;
    $ref = array_search($_GET['ref'], $special_refs);
} else {
    $special_ref = false;
    $ref = $_GET['ref'];
}

//check type and act accordingly for "edition" and "magazine". Error if neither.
switch ( strtolower ( $_GET['type'] ) ) {
    case "edition":
        //get available editions on server
        $editions = array_reverse ( glob ( 'media/[0123456789]*', GLOB_ONLYDIR ) );
        if ( $special_ref ) {
            if ( array_key_exists ( $ref, $editions ) ) {
                $directory = $editions[$ref];
            } else {
                header ( "HTTP/1.0 404 Not Found" );
                exit ( "EDITION_NOT_FOUND_FROM_KEYWORD" );
            }
        } else {
            foreach ( $editions as $key => $value ) {
                if ( stristr( $value, $ref ) ) {
                    $edition_ref = $key;
                }
            }
            if ( isset ( $edition_ref ) ) {
                $directory = $editions[$edition_ref];
            } else {
                header ( "HTTP/1.0 404 Not Found" );
                exit ( "EDITION_NOT_FOUND_FROM_REFERENCE" );
            }
        }
        $edition_details = explode ( "_", basename ( $directory ) );
        //Calculate Internet Archive Filename
        $ia_filename = "WtnEdition" . $edition_details[0] . date ( "d\-m\-Y", strtotime ( $edition_details[1] ) ) . "/" . basename ( $directory ) . ".mp3";
        //Calculate Wordpress Slug
        $wp_slug = 'edition-' . $edition_details[0];
        break;
    case "magazine":
        //get available magazines on server
        $magazines = array_reverse ( glob ( 'media/M*', GLOB_ONLYDIR ) );
        if ( $special_ref ) {
            if ( array_key_exists ( $ref, $magazines ) ) {
                $directory = $magazines[$ref];
            } else {
                header ( "HTTP/1.0 404 Not Found" );
                exit ( "MAGAZINE_NOT_FOUND_FROM_KEYWORD" );
            }
        }
        else {
            foreach ( $magazines as $key => $value ) {
                if( stristr( $value, $ref ) ){
                    $magazine_ref = $key;
                }
            }
            if ( true ) {
                $directory = $magazines[$magazine_ref];
            } else {
                header ( "HTTP/1.0 404 Not Found" );
                exit ( "MAGAZINE_NOT_FOUND_FROM_REFERENCE" );
            }
        }
        $magazine_details = explode ( "_", basename ( $directory ) );
        //Calculate Internet Archive Filename
        $ia_filename = "WtnMagazine" . substr ( $magazine_details[0], 1, 3 ) . $magazine_details[2] . date ( "Y", strtotime ( $magazine_details[1] ) ) . "/" . basename ( $directory ) . ".mp3";
        //Calculate Wordpress Slug
        $wp_slug = 'magazine-' . substr ( $magazine_details[0], 1, 3 );
        break;
    default:
        header ( "HTTP/1.1 500 Internal Server Error" );
        exit ( "UNKNOWN_MEDIA_TYPE_REQUESTED" );
}

//check format and generate appropriate format. Error if neither.
switch ( strtolower ( $_GET['format'] ) ) {
    case "mp3":
        $header = "Location: " . $base_urls[strtolower ( $_GET['format'] )] . $ia_filename;
        break;
    case "html":
    case "htm":
        $header = "Location: " . $base_urls[strtolower ( $_GET['format'] )] . $wp_slug;
        break;
    default:
        header ( "HTTP/1.0 404 Not Found" );
        exit ( "FORMAT_NOT_SUPPORTED" );
}
header ( $header, true, 302 );
exit ( 0 );

?>