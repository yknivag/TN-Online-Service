<?php
session_start();
//session_destroy();
$authorised = false;
$config = parse_ini_file( "../../scripts/config-live.ini", true );

//Check if someone has logged out
if(isset($_GET['logout']) && $_GET['logout'] === "true") {
    session_destroy();
    header('Location: '.$_SERVER['PHP_SELF']);
    die;
}

//Check if someone has logged in
if(isset($_POST['username']) && isset($_POST['password'])) {
    if($_POST['username'] == $config['uploader']['username'] && md5($_POST['password']) == $config['uploader']['passhash']) {
        $_SESSION['username'] = $config['uploader']['username'];
        $authorised = true;
    } else {
        $_SESSION['username'] = "";
        session_destroy();
    }
}
//Check if there is still someone logged in
elseif(isset($_SESSION['username']) && $_SESSION['username'] == $config['uploader']['username']) {
    $authorised = true;
}
else {
    $_SESSION['username'] = "";
    $authorised = false;
    session_destroy();
}
//Check if publication details have been submitted
if(isset($_POST['pub_type'])) {
    $stage1result = true;
//        echo "<pre>";
//        var_dump($_POST);
//        echo "</pre>";
    if($_POST['pub_type'] == "edition" && $_POST['number'] != "" && $_POST['date'] != "") {
        $foldername = $_POST['number'] . "_" . $_POST['date'];
        $_SESSION['foldername'] = $foldername;
    }
    elseif($_POST['pub_type'] == "magazine" && $_POST['number'] != "" && $_POST['date'] != "" && $_POST['magname'] != "" && $_POST['magtheme'] != "") {
        $foldername = 'M' . $_POST['number'] . "_" . $_POST['date'] . "_" . str_replace(" ", "-", $_POST['magname']) . "-Magazine_" . str_replace(" ", "-", $_POST['magtheme']);
        if(stristr($foldername, "Magazine-Magazine")) {
            $foldername = "";
            $stage1result = false;
        }
        else {
            $_SESSION['foldername'] = $foldername;
            $stage1result = true;
        }
    }
    else {
        $foldername = "";
        $stage1result = false;
    }
}

?>
<!doctype html>
<html class="no-js" lang="en" dir="ltr">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="x-ua-compatible" content="ie=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Witney Talking News</title>
        <link rel="stylesheet" href="../css/foundation.min.css">
        <link rel="stylesheet" href="../jquery-ui/jquery-ui.structure.min.css">
        <link rel="stylesheet" href="../jquery-ui/jquery-ui.theme.min.css">
        <link rel="stylesheet" href="../css/jquery.dm-uploader.min.css">
        <link rel="stylesheet" href="../css/app.css">
    </head>
    <body>
        <div class="row container" id="container">
            <div class="small-12 columns">
                
                <div id="header">
                    <div class="row header">
                        <div class="small-12 columns">
                            <h1><img src="../images/Logo.png" style="max-height: 84px;" alt="Logo"> Any Town Talking News</h1>
                            <h2>File Uploader</h2>
                            <hr>
                        </div>
                    </div>
                </div>

                <div id="main">
                    <div class="row content">
                        <?php if($authorised) { ?>
                        <div class="small-12 columns">
                            
                            
                            <?php if(!isset($foldername) || $foldername == "") { ?>
                            <p>Thank you for logging in, you may use this tool to 
                                upload a publication to the Witney Talking News 
                                Online Services.  Any publication added here will 
                                be added to the website, Sonata+*, Podcast*, Streaming Playlists*, Alexa* 
                                and be emailed to those who subscribe*.</p>
                            <p>* These will not happen if the publication is more than 6 weeks old (10 weeks for the Podcast and Alexa). 
                                Instead it will be treated as an archived publication and simply added to the website.</p>
                            <p>Begin by selecting the type of publication and then filling in the appropriate boxes.</p>
                            
                            <h3>1. Publication Details.</h3>
                            <?php if(isset($stage1result) && $stage1result === false) { ?>
                            <div class="callout alert">
                                <h4 class="alert">Error</h4>
                                <p class="alert">There was a problem with the submitted data, please try again.</p>
                                <p class="alert">Ensure all the fields are filled in. If uploading a magazine 
                                    ensure the name field doesn't end with the word &quot;magazine&quot; as this will be added automatically.</p>
                            <?php } ?>
                            <div class="expanded button-group">
                                <a class="button primary" id="editionSelector">Edition</a>
                                <a class="button secondary" id="magazineSelector">Magazine</a>
                            </div>
                            <form data-abide name="pubDetails" id="pubDetailsForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                <input type="hidden" id="pub_type" name="pub_type" value="edition">
                                <label>Number
                                    <input name="number" id="pubNumber" class="pub_data" type="text" placeholder="Enter number" aria-describedby="numberHelpText">
                                </label>
                                <p class="help-text" id="numberHelpText">For an edition this must be a 4 digit number, for a magazine a 3 digit number. Prefix with zeros if required.</p>
                                <label>Date
                                    <input name="date" id="pubDate" class="datepicker pub_data" type="text" value="<?php echo date("d-m-Y"); ?>" placeholder="Enter date" required aria-describedby="dateHelpText">
                                </label>
                                <p class="help-text" id="dateHelpText">This is the date of distribution of the USB stick containing the publication.</p>
                                <div id="magazine_details" hidden>
                                <label>Magazine Name
                                    <input name="magname" id="pubName" class="pub_data" type="text" placeholder="Enter Magazine Name" aria-describedby="magnameHelpText">
                                </label>
                                <p class="help-text" id="magnameHelpText">Typically the season of recording or similar it will appear in the title before the word &quot;Magazine&quot;</p>
                                <label>Magazine Theme
                                    <input name="magtheme" id="pubTheme" class="pub_data" type="text" placeholder="Enter Magazine Theme" aria-describedby="magthemeHelpText">
                                </label>
                                <p class="help-text" id="magthemeHelpText">Could be the same, as in the example below, but usually distinct from the name.</p>
                                </div>
                                <p>This will be called: <strong><span id="pub_name"></span></strong>.</p>
                                <p>If the above name is incorrect please change the details until it is. It should look like one of the following examples<br>
                                    Edition: <strong>1700_18-01-2018</strong><br>
                                        Magazine: <strong>M016_16-10-2018_Autumn-Magazine_Autumn</strong></p>
                                <input type="submit" class="button success" value="Submit"> <input type="reset" class="button alert" value="Reset">
                            </form>
                            <?php if(isset($stage1result) && $stage1result === false) { ?>
                            </div>
                            <?php } ?>
                            
                            <?php } else { ?>
                            <h3>2. Add the files.</h3>
                            <p>Working on: <strong><?php echo $foldername; ?></strong></p>
                            <p><strong>Please Note:</strong> When the uploads are all finished, please verify that there are no red 
                               indicators in the &quot;Debug Messages&quot; below.  If there are, please keep hold of the USB 
                               stick and copy and paste the debug output into an email to <a href="mailto:webmaster@wtn.org.uk">webmaster@wtn.org.uk</a></p>
                            <div class="row">
                                <div class="small-12 medium-6 columns content">
                                    <div id="drag-and-drop-zone" class="dm-uploader">
                                        <h3>Drag &amp; Drop files here</h3>
                                        <p>Open the USB stick so that you can see the files, 
                                            highlight them all and drag them with your mouse 
                                            over the area enclosed by the dotted lines, as the border turns solid blue
                                            release your mouse button.</p>
                                        <p>Files will start to upload immediately</p>
                                    </div>
                                </div>
                                <div class="small-12 medium-6 columns content">
                                    <h3 class="text-center">File List</h3>
                                        <ul class="list-group list-group-flush" id="files">
                                            <li class="text-muted text-center empty">No files uploaded.</li>
                                        </ul>
                                </div>
                            </div>
                            <div class="row">
                                <div class="small-12 columns content">
                                    <p>Once all uploads have finished, please logout.</p>
                                    <a class="button primary extended" href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=true">Logout</a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="small-12 columns content">
                                    <h3>Debug Messages</h3>
                                    <ul class="list-group list-group-flush" id="debug">
                                        <li class="list-group-item text-muted empty">Loading plugin....</li>
                                    </ul>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } else { ?>
                        <div class="small-12 columns">
                            <h3>Login</h3>
                            <p>You must be logged in to use this service</p>
                            <form name="login" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                <label>Username
                                    <input name="username" type="text" placeholder="Enter username">
                                </label>
                                <label>Password
                                    <input name="password" type="password" placeholder="Enter password">
                                </label>
                                <input type="submit" class="button success" value="Login"> <input type="reset" class="button alert" value="Reset">
                            </form>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                
            </div>
        </div>
        
        <div id="footer">
            <div class="row">
                <div class="small-12 columns text-center">
                    <hr>
                    Tool provide by and copyright &copy; 2018 <a href="https://wtn.org.uk">Witney Talking News</a><br>
                    <a href="https://github.com/yknivag/TN-Online-Service" target="_blank">Source & License</a>
                </div>
            </div>
        </div>

        <script src="../js/vendor/jquery.js"></script>
        <script src="../js/vendor/what-input.js"></script>
        <script src="../js/vendor/foundation.min.js"></script>
        <script src="../jquery-ui/jquery-ui.min.js"></script>
        <script src="../js/vendor/jquery.dm-uploader.min.js"></script>
        <script src="../js/app.js"></script>        

        <!-- File item template -->
        <script type="text/html" id="files-template">
            <li class="media">
                <div class="media-body mb-1">
                    <p class="mb-2">
                        <strong>%%filename%%</strong><br>Status: <span class="text-muted">Waiting</span>
                    </p>
                    <div class="primary progress" role="progressbar" tabindex="0" aria-valuenow="0" aria-valuemin="0" aria-valuetext="25 percent" aria-valuemax="100">
                        <div class="progress-meter" style="width: 0%">
                            <p class="progress-meter-text"></p>
                        </div>
                    </div>
              <hr class="mt-1 mb-1" />
            </div>
          </li>
        </script>

        <!-- Debug item template -->
        <script type="text/html" id="debug-template">
            <li class="list-group-item"><span class="%%color%% label">%%date%%</span>: %%message%%</li>
        </script>
        
    </body>
</html>
