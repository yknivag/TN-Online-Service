<?php

//////////////////////////////////
// Upload Processor Stage 3     //
// ========================     //
//                              //
// Author: Gavin Smalley        //
// Date:   11th Nov 2017        //
// Version: 1.0                 //
//                              //
//////////////////////////////////
//                              //
// Stage 3 of a 3 stage process //
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
define( 'WP_USE_THEMES', false );
require( $config['wordpress']['path_to_wp'] );

    //Mailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require "PHPMailer/src/Exception.php";
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";

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
        echo_log($publication, "general.log", "Stage 3 started.");
        if ( substr ( $publication, 0, 1 ) === $config['glob_patterns']['magazine_prefix'] ) {
            $type = "Magazine";
            $lcase_type = strtolower ( $type );
            $number = substr ( $publication_data[0], 1, 3 );
            $email_longtitle = $config['organisation']['organisation'] . " " . $type . " " . $number . " (" . str_replace ( "-", " ", $publication_data[3] ) . ")";
        } else {
            $type = "Edition";
            $lcase_type = strtolower ( $type );
            $number = $publication_data[0];
            $email_longtitle = $type . " " . $number . " of the " . $config['organisation']['organisation'];
        }
        $lcase_type = strtolower ( $type );
        $nice_date = date ( "jS\ F\ Y", strtotime ( $publication_data[1] ) );
        $permalink = $config['organisation']['baseURL'] . $lcase_type . "-" . $number . "/";
        //If not yet corrected
        if ( !file_exists ( $config['general']['log_dir'] . $publication . '/.corrected' ) ) {
            //Check it's been posted and get the json file that contains the post refs.
            if ( file_exists ( $config['general']['log_dir'] . $publication . '/.posted' ) ) {
                //correct it
                ##THIS MAY NO LONGER BE NECESSARY GIVEN RECENT CHANGE TO stage2.php.
            } else {
                echo_log($publication, "general.log", "Stage 3 started but this hasn't been posted yet.");
                echo "FATAL ERROR: Stage 3 started for: " . $publication . " but it hasn't been posted yet.";
            }
        }
        //If not yet emailed
        if ( !file_exists ( $config['general']['log_dir'] . $publication . '/.emailed' ) ) {
            //Check it's been posted
            if ( file_exists ( $config['general']['log_dir'] . $publication . '/.posted' ) ) {
                //Check it isn't a back issue (ie more than 10 days old).
                if ( strtotime ( $publication_data[1] ) > strtotime ( "-10days" ) ) {
                    //Email it
                    echo_log ( $publication, "general.log", "Beginning Email." );
                    echo_log ( $publication, "general.log", "Long Title: " . $email_longtitle );
                    echo_log ( $publication, "general.log", "Nice Date: " . $nice_date );
                    echo_log ( $publication, "general.log", "Permalink: " . $permalink );
                    
                    //Set the substitution maps so the template can be personalised for this publication.
                    $map_fields = array ( 
                        "###EMAIL-LONGTITLE###",
                        "###NICE-DATE###",
                        "###LCASE-TYPE###",
                        "###NUMBER###",
                        "###PERMALINK###"
                    );
                    $map_data = array (
                        $email_longtitle,
                        $nice_date,
                        $lcase_type,
                        $number,
                        $permalink
                    );
//$email_longtitle###EMAIL-LONGTITLE### = Edition 1693 of the Witney Talking News [Witney Talking News Magazine ## (##Theme##)]
//$nice_date###NICE-DATE### = 23rd November 2017
//$lcase_type###LCASE-TYPE### = edition [magazine]
//$number###NUMBER### = 1963
//$permalink###PERMALINK### = https://wtn.org.uk/edition-1693/
                    
                    $email_html_template = file_get_contents ( "email_template_html.dat" );
                    $email_plaintext_template = file_get_contents ( "email_template_plain.dat" );
                    
                    $email_html_body = str_replace ( $map_fields, $map_data, $email_html_template );
                    $email_plaintext_body = str_replace ( $map_fields, $map_data, $email_plaintext_template );

                    
                    $mail = new PHPMailer(true);
                    
                    try {
                        //Server settings
                        //$mail->SMTPDebug = 2;                                 // Enable verbose debug output
                        //$mail->isSMTP();                                      // Set mailer to use SMTP
                        //$mail->Host = $config['email']['SMPTHost'];           // Specify main and backup SMTP servers
                        //$mail->SMTPAuth = true;                               // Enable SMTP authentication
                        //$mail->Username = $config['email']['SMTPUsername'];   // SMTP username
                        //$mail->Password = $config['email']['SMTPPassword'];   // SMTP password
                        //$mail->SMTPSecure = $config['email']['SMTPSecure'];   // Enable TLS encryption, `ssl` also accepted
                        //$mail->Port = $config['email']['SMTPPort'];           // TCP port to connect to

                        //Sendmail
                        $mail->isSendmail();
                        
                        //Recipients
                        $mail->setFrom($config['email']['fromEmail'], $config['organisation']['organisation']);
                        $mail->addAddress($config['email']['notifyEmail']);   // Add a recipient
                        $mail->addReplyTo($config['email']['replyToEmail'], $config['organisation']['organisation']);

                        //Content
                        $mail->isHTML(true);                                  // Set email format to HTML
                        $mail->Subject = $email_longtitle;
                        $mail->Body    = $email_html_body;                    // HTML Body
                        $mail->AltBody = $email_plaintext_body;               // Plain text body for email clients that don't support HTML

                        $mail->send();
                        echo_log ($publication, "general.log", "Email sent, see .emailed file for details");
                        echo_log ( $publication, ".emailed", "Email Subject: " . $email_longtitle );
                        echo_log ( $publication, ".emailed", "HTML Email: " . $email_html_body );
                        echo_log ( $publication, ".emailed", "Plain Text Email: " . $email_plaintext_body );
                    } catch (Exception $e) {
                        echo_log ($publication, "general.log", "ERROR: Email could not be sent." );
                        echo_log ($publication, "general.log", "Mailer Error: " . $mail->ErrorInfo );
                        echo_log ($publication, ".emailed", "Mailer Error: " . $mail->ErrorInfo );
                        echo "ERROR: Email could not be sent for " . $publication;
                    }
                } else {
                    echo_log ( $publication, "general.log", "Publication older than 10 days." );
                    echo_log ( $publication, "general.log", "Pub Date: " . $publication_data[1] );
                }          
            }
        }
    } else {
        echo "ERROR: stage3.php found publications that have not been processed by stage1.php";
    }
}

//That's the end of the process.
//There is also a batch file which cleans up all edition files once they're over 50 days old.
?>