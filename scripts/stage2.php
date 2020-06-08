<?php

//////////////////////////////////
// Upload Processor Stage 2     //
// ========================     //
//                              //
// Author: Gavin Smalley        //
// Date:   11th Nov 2017        //
// Version: 1.0                 //
//                              //
//////////////////////////////////
//                              //
// Stage 2 of a 3 stage process //
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
define ( 'WP_USE_THEMES', false );
require( $config['wordpress']['path_to_wp'] );

//Log Function Useage
//echo_log($publication, $log, $text)
//appends "[DATE] - $text" to the file "$config['general']['log_dir']/$publication/$log"

//Build lists of publications to process

$publications_on_device = glob($config['general']['upload_dir'] . $config['glob_patterns']['publications'], GLOB_ONLYDIR);

//Iterate through publications one by one.

foreach ( $publications_on_device as $pub_dir ) {
    $publication = basename ( $pub_dir );
    $publication_data = explode ( "_", $publication );
    //Check if there is a log directory.  If there isn't then ignore it as stage1.php needs to be executed first.
    if ( file_exists ( $config['general']['log_dir'] . $publication ) ) {
        //Determine type (Edition|Magazine) as some things are done differently.
        echo_log ( $publication, "general.log", "Stage 2 commenced." );
        if ( substr ( $publication, 0, 1 ) === $config['glob_patterns']['magazine_prefix'] ) {
            $type = "Magazine";
            $number = substr ( $publication_data[0], 1, 3 );
            $name = $type . " " . $number . ' - ' . date ( "jS\ F\ Y", strtotime ( $publication_data[1] ) ) . ' - ' . str_replace ( "-", " ", $publication_data[2] ) . ' - ' . str_replace ( "-", " ", $publication_data[3] );
            $iabucket = $config['organisation']['orgCamel'] . $type . $number . $publication_data[2] . date ( "Y", strtotime ( $publication_data[1] ) );
            $mp3_title = $type . " " . $number . " - " . $publication_data[1] . " - " . str_replace ( "-", " ", $publication_data[2] );
            $categories = explode ( ",", $config['wordpress']['magazine_categories'] );
        } else {
            $type = "Edition";
            $number = $publication_data[0];
            $name = $type . " " . $number . " " . date ( "jS\ F\ Y", strtotime ( $publication_data[1] ) );
            $iabucket = $config['organisation']['orgCamel'] . $type . $number . date ( "d\-m\-Y", strtotime ( $publication_data[1] ) );
            $mp3_title = $type . " " . $number . " " . $publication_data[1];
            $categories = explode ( ",", $config['wordpress']['edition_categories'] );
        }
        $album     = $config['organisation']['org'] . " " . $type . "s";
        $mp3_date  = date ( "Y", strtotime ( $publication_data[1] ) );
        $tags      = "Album: " . $album . "|Title: " . $mp3_title . "|Track: " . $number . "|Year: " . $mp3_date . "|Comment: " . $mp3_title;
        $ia_date   = date ( "Y\-m\-d", strtotime ( $publication_data[1] ) ); //yyyy-mm-dd
        $year      = date ( "Y", strtotime ( $publication_data[1] ) ); //yyyy
        $shortdesc = $config['organisation']['org'] . ' ' . $name; //WTN Edition 0001 dd-mm-YYYY
        $desc      = $config['organisation']['organisation'] . ' ' . $name; //Witney Talking News Edition 0001 dd-mm-YYYY
 
        //Upload to internet archive (if not already done)
        if ( !file_exists ( $config['general']['log_dir'] . $publication . '/.uploaded' ) ) {
            echo_log($publication, "general.log", "No .uploaded file, uploading.");
            //Check for a file back from transloadit
            $returned_file = $config['general']['processed_dir'] . $publication . '.mp3';
            if ( file_exists ( $returned_file ) && filesize ( $returned_file ) != 0 ) {
                echo_log($publication, "general.log", "File found from Transloadit, setting tags and uploading.");
                //Correct ID3 tags
                tag_file($returned_file, $album, $mp3_title, $number, $mp3_date, $mp3_title);
                echo_log($publication, "general.log", "Converted File Tagged: " . $tags);
                //Generate specific IA metadata
                $upload_filename = basename ( $returned_file );
                $longfilename    = $iabucket . "/" . $upload_filename; //WtnEdition0001dd-mm-YYYY/0001_dd-mm-YYYY.mp3
                echo_log ( $publication, "general.log", "Uploading to IA as: " . $longfilename );
                //cURL to iarchive
                $file_read = fopen ( $returned_file, 'r' );
                $headers = array (
                    'authorization: LOW ' . $config['iarchive']['ia_access_key'] . ':' . $config['iarchive']['ia_secret_key'],
                    'x-amz-auto-make-bucket:1',
                    'x-archive-meta-mediatype:' . $config['iarchive']['mediatype'],
                    'x-archive-meta01-collection:' . $config['iarchive']['collection'],
                    'x-archive-meta-creator:' . $config['organisation']['organisation'],
                    'x-archive-meta-licenseurl:' . $config['iarchive']['license'],
                    'x-archive-meta01-subject:' . $config['organisation']['organisation'],
                    'x-archive-meta02-subject:' . $type . 's',
                    'x-archive-meta-title:' . $shortdesc,
                    'x-archive-meta-description:' . $desc,
                    'x-archive-meta-date:' . $ia_date,
                    'x-archive-meta-year:' . $year
                );
                echo_log ( $publication, "general.log", "Internet Archive cURL Headers: " . serialize ( $headers ) );
                
                $ch = curl_init();
                
                curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, true );
                curl_setopt ( $ch, CURLOPT_HEADER, true );
                curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
                curl_setopt ( $ch, CURLOPT_URL, $config['iarchive']['curl_prefix'] . $longfilename );
                curl_setopt ( $ch, CURLOPT_PUT, true );
                curl_setopt ( $ch, CURLOPT_INFILE, $file_read );
                curl_setopt ( $ch, CURLOPT_INFILESIZE, filesize ( $returned_file ) );
                curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt ( $ch, CURLOPT_VERBOSE, true );
                $debug = curl_exec ( $ch );
                curl_close ( $ch );
                //Put result in ".uploaded"
                echo_log ( $publication, ".uploaded", serialize ( $debug ) );
            } else {
                //No file back from transloadit, FATAL ERROR
                echo_log($publication, "general.log", "FATAL ERROR: No file found from Transloadit, skipping.");
                echo "FATAL ERROR: " . $returned_file . " not found, check Transloadit.";
            }
        }

        //Create Wordpress metadata (if not already done)
        if ( !file_exists ( $config['general']['log_dir'] . $publication . '/post_details.json' ) ) {
            //Check file has been uploaded to iarchive first.
            if ( file_exists ( $config['general']['log_dir'] . $publication . '/.uploaded' ) ) {
                $uploaded_file = $config['general']['processed_dir'] . $publication . '.mp3';
                $mediaurl      = $config['iarchive']['url_prefix'] . $iabucket . "/" . basename ( $uploaded_file ); //http://archive.org/download/WtnEdition0001dd-mm-YYYY/0001_dd-mm-YYYY.mp3
                $iamlpath      = "IAML-Mapping/" . $iabucket . "/" . basename ( $uploaded_file ); //IAML-Mapping/WtnEdition0001dd-mm-YYYY/0001_dd-mm-YYYY.mp3
                //Get MP3 metadata
                $metadata = array();
                $id3 = new getID3();
                $data = $id3->analyze ( $uploaded_file );
                if ( ! empty ( $data['audio'] ) ) {
                    unset ( $data['audio']['streams'] );
                    $metadata = $data['audio'];
                }
                if ( ! empty ( $data['fileformat'] ) ) {
                    $metadata['fileformat'] = $data['fileformat'];
                }
                if ( ! empty ( $data['filesize'] ) ) {
                    $metadata['filesize'] = (int) $data['filesize'];
                }
                if ( ! empty ( $data['mime_type'] ) ) {
                    $metadata['mime_type'] = $data['mime_type'];
                }
                if ( ! empty ( $data['playtime_seconds'] ) ) {
                    $metadata['length'] = (int) round( $data['playtime_seconds'] );
                }
                if ( ! empty ( $data['playtime_string'] ) ) {
                    $metadata['length_formatted'] = $data['playtime_string'];
                }
                foreach ( array ( 'id3v2', 'id3v1' ) as $version ) {
                    if ( ! empty ( $data[$version]['comments'] ) ) {
			foreach ( $data[$version]['comments'] as $key => $list ) {
                            if ( 'length' !== $key && ! empty ( $list ) ) {
                                $metadata[$key] = reset( $list );
                                // Fix bug in byte stream analysis.
                                if ( 'terms_of_use' === $key && 0 === strpos ( $metadata[$key], 'yright notice.' ) ) {
                                    $metadata[$key] = 'Cop' . $metadata[$key];
                                }
                            }
                        }
                        break;
                    }
                }
                if ( ! array_key_exists('album', $metadata ) ) {
                    $metadata['album'] = $org . ' ' . $entry['type'] . 's'; //"WTN Editions" or "WTN Magazines"
                }
                $meta = serialize ( $metadata );
                echo_log ( $publication, "general.log", "Generic Metadata: " . $meta );
                //Wordpress Metadata
                $media_slug = str_replace ( ".", "-", basename ( $uploaded_file ) ); //0001_dd-mm-YYYY-mp3
                $post_slug  = strtolower ( $type ) . '-' . $number; //edition-0001
                $post_date  = date ( "Y\-m\-d\ H\:i\:s", strtotime ( $publication_data[1] ) ); //yyyy-mm-dd hh:ii:ss
                $post_title = $type . ' ' . $number; //Edition 0001
                $post_content   = $name . '<br />[audio mp3="' . $mediaurl . '"]' . $name . "[/audio]"; //Edition 0001 ddth MM YYYY\r\n[audio mp3="https://archive.org/downloads/WtnEdition0001dd-mm-YYYY/0001_dd-mm-YYYY.mp3"]Edition 0001 ddth MM YYYY[/audio]
                //Write out "post_details.json"
                $post_details = array (
                    'publication_date' => $post_date,
                    'media_content'    => $desc,
                    'media_title'      => $shortdesc,
                    'media_excerpt'    => $desc,
                    'media_slug'       => $media_slug,
                    'media_guid'       => $iamlpath,
                    'media_meta'       => $meta,
                    'media_filesize'   => (int) $data['filesize'],
                    'post_content'     => $post_content,
                    'post_title'       => $post_title,
                    'post_excert'      => $name,
                    'post_slug'        => $post_slug,
                    'enclosure'        => $mediaurl
                );
                $post_json = json_encode ( $post_details );
                echo_log ( $publication, "general.log", "Created Post Data JSON: " . $post_json );
                $file_handle = fopen ( $config['general']['log_dir'] . $publication . '/post_details.json', 'w' );
                fwrite ( $file_handle, $post_json );
                fclose ( $file_handle );
            }
        }

        //Add post to Wordpress (if not already done)
        if ( !file_exists ( $config['general']['log_dir'] . $publication . '/.posted' ) ) {
            if ( file_exists ( $config['general']['log_dir'] . $publication . '/post_details.json' ) ) {
                //Get the details back from the json file as we cannot guarantee that the above section ran this time, so they may not be in memory.
                $post_data_json = file_get_contents ( $config['general']['log_dir'] . $publication . '/post_details.json' );
                echo_log ( $publication, "general.log", "Retrieved Post Data JSON: " . $post_data_json );
                $post_data_array = json_decode ( $post_data_json, true );
                print_r ( $post_data_array );
                //Add the posts to wordpress
                //Media Post
                $mpost_args = array (
                    'post_author'=>$config['wordpress']['poster_id'],
                    'post_date'=>$post_data_array['publication_date'],
                    'post_date_gmt'=>$post_data_array['publication_date'],
                    'post_content'=>wp_slash( $post_data_array['media_content'] ),
                    'post_content_filtered'=>'',
                    'post_title'=>$post_data_array['media_title'],
                    'post_excerpt'=>wp_slash( $post_data_array['media_excerpt'] ),
                    'post_status'=>'inherit',
                    'post_type'=>'attachment',
                    'comment_status'=>'closed',
                    'ping_status'=>'closed',
                    'post_password'=>'',
                    'post_name'=>$post_data_array['media_slug'],
                    'to_ping'=>'',
                    'pinged'=>'',
                    'post_parent'=>'0',
                    'menu_order'=>'0',
                    'post_mime_type'=>'audio/mpeg',
                    'guid'=>$post_data_array['media_guid']
                );
                $media_metadata = unserialize($post_data_array['media_meta']);
                $media_metadata['IAML'] = true;
                //Actual Post
                $post_args = array (
                    'post_author'=>$config['wordpress']['poster_id'],
                    'post_date'=>$post_data_array['publication_date'],
                    'post_date_gmt'=>$post_data_array['publication_date'],
                    'post_content'=>wp_slash( $post_data_array['post_content'] ),
                    'post_content_filtered'=>'',
                    'post_title'=>$post_data_array['post_title'],
                    'post_excerpt'=>wp_slash( $post_data_array['post_excert'] ),
                    'post_status'=>'publish',
                    'post_type'=>'post',
                    'comment_status'=>'closed',
                    'ping_status'=>'closed',
                    'post_password'=>'',
                    'post_name'=>$post_data_array['post_slug'],
                    'to_ping'=>'',
                    'pinged'=>'',
                    'post_parent'=>'0',
                    'menu_order'=>'0',
                    'post_mime_type'=>''
                );

                //echo "Original data:" . PHP_EOL;
                //print_r($post_data_array);
                //echo PHP_EOL . PHP_EOL . "Media Post Details:" . PHP_EOL;
                //print_r($mpost_args);
                //echo PHP_EOL . PHP_EOL . "Media Metadata:" . PHP_EOL;
                //print_r($media_metadata);
                //echo PHP_EOL . PHP_EOL . "Post Details:" . PHP_EOL;
                //print_r($post_args);
                //echo PHP_EOL . PHP_EOL;

                ## Insert Post for display post
                    #https://developer.wordpress.org/reference/functions/wp_insert_post/
                $display_post_id = wp_insert_post ( $post_args );

                ## Insert Attachment for media file
                    #https://developer.wordpress.org/reference/functions/wp_insert_attachment/
                    #This is a wrapper for wp_insert_post() and takes all the same arguments
                $media_post_id = wp_insert_attachment ( $mpost_args, $post_data_array['media_guid'], $display_post_id );

                ## Add metadata for media file
                    #https://developer.wordpress.org/reference/functions/wp_update_attachment_metadata/
                wp_update_attachment_metadata ( $media_post_id, $media_metadata );

                ## Add taxonomy for display post
                    #https://developer.wordpress.org/reference/functions/wp_set_object_terms/
                //$format = array ( $config['wordpress']['post_format'] );

                wp_set_object_terms ( $display_post_id, $categories, 'category' );
                wp_set_object_terms ( $display_post_id, $config['wordpress']['post_format'], 'post_format' );

                ## Add metadata for enclosure
                    #https://developer.wordpress.org/reference/functions/add_metadata/
//                $enclosure = wp_slash ( $post_data_array['enclosure'] . '<br/>' . $post_data_array['media_filesize'] . '<br/>audio/mpeg' );
                $enc_url = $post_data_array['enclosure'];
                $enc_size = $post_data_array['media_filesize'];
                $enclosure = "$enc_url\n$enc_size\naudio/mpeg";
                //changed after seeing how the WP Core adds enclosures.  Line 623 in https://core.trac.wordpress.org/browser/tags/4.8/src/wp-includes/functions.php#L554
                $enclosure_id = add_post_meta ( $display_post_id, 'enclosure', "$enc_url\n$enc_size\naudio/mpeg\n" );
                delete_post_meta ( $display_post_id, '_encloseme' ); //We add this to stop the WP do_enclose() function from removing the filesize of the custom enclosure to preserve XML sanity in the Podcast.
                //This may work if we change "<br/>" to "\r\n" in the enclosure.  More research necessary.  (Changing "enclosure" to "_enclosure" doesn't work.)

                //Add post refs to "post_refs.json" (ready for stage3.php).
                $post_refs = array (
                    'media'     => $media_post_id,
                    'post'      => $display_post_id,
                    'enclosure' => $enclosure_id
                );
                $post_ref_json = json_encode ( $post_refs );
                echo_log ( $publication, "general.log", "Post Refs JSON: " . $post_ref_json );
                $file_handle = fopen ( $config['general']['log_dir'] . $publication . '/post_refs.json', 'w' );
                fwrite ( $file_handle, $post_ref_json );
                fclose ( $file_handle );
                
                //Add details to log and create .posted file
                if ( $media_post_id > 0 && $display_post_id > 0 && $enclosure_id > 0 ) {
                    //Posted successfully
                    echo_log ( $publication, "general.log", "Posted Successfully." );
                } else {
                    echo_log ( $publication, "general.log", "FATAL ERROR: Posting failed." );
                    echo "FATAL ERROR: Posting failed for " . $publication . PHP_EOL;
                }
                echo_log ( $publication, "general.log", "Post Reference JSON: " . $post_ref_json );
                touch ( $config['general']['log_dir'] . $publication . '/.posted' );
            } else {
                echo_log($publication, "general.log", "FATAL ERROR: post_details.json doesn't exists but we are being asked to post to WordPress.");
                echo "FATAL ERROR: " . $publication . "post_details.json doesn't exist but we are being asked to post to WordPress.";
            }
        }
        echo_log ( $publication, "general.log", "Stage 2 Complete" );
    } else {
        echo "ERROR: stage2.php found publications that have not been processed by stage1.php";
    }
}

//That's stage 2 complete.
    //stage3.php will run after the next WP cron and correct the enclosure tag as WP cron sets the filesize to 0.
        //it will also email the notfy@wtn.org.uk mailing list.

?>