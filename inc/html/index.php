<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

@include_once ("./inc/settings.php");
require_once ("./inc/db/" . (defined ("DBTYPE")? DBTYPE: "mysql") . ".php");

try {
    $connection->connect (DBHOST, DBUSER, DBPWD, DBNAME, DBPREFIX);
} catch (Exception $e) {
}

if (defined ("THUMBSMAXSIZE") && (THUMBSMAXSIZE > 0)) {
    $thumbsmaxsize = THUMBSMAXSIZE;
} else {
    $thumbsmaxsize = 400; // default value;
}

if (defined ("POPUPPOS")) {
    $popuppos = POPUPPOS;
} else {
    $popuppos = 3;
}
?>
<html lang="<?php echo $lang?>">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" >
    <title><?php echo defined ("SITETITLE") ? htmlspecialchars (SITETITLE) : "SYP"?></title>
    <link rel="alternate" type="application/atom+xml" title="Atom 1.0" href="news.php">
    <link rel="stylesheet" href="./openlayers/theme/default/style.css" type="text/css">
    <link rel="stylesheet" href="./media/syp.css" type="text/css">

    <style type="text/css">
        .olPopup {
            <?php printf("_width: expression(Math.min(parseInt(this.scrollWidth) + 7, %d) + 'px');\n", ($thumbsmaxsize + 30))?>
        }
        .olPopup p {
            <?php printf("max-width: %dpx;\n", $thumbsmaxsize - 20)?>

        }
        .olPopup img {
            <?php printf("max-height: %dpx;\n", $thumbsmaxsize)?>
            <?php printf("max-width: %dpx;\n", $thumbsmaxsize)?>

            /* for IE (does not understand max-heigth max-width) */
            <?php printf("_height: expression((this.scrollHeight>this.scrollWidth) ? 
                 Math.min(parseInt(this.scrollHeight), %d ) + 'px' : 'auto');\n", $thumbsmaxsize)?>
            <?php printf("_width: expression((this.scrollWidth>this.scrollHeight) ? 
                 Math.min(parseInt(this.scrollWidth), %d ) + 'px' : 'auto');\n", $thumbsmaxsize)?>
        }
    </style>

<?php
    if (file_exists ("./media/syp_custom.css")) {
        printf("    <link rel=\"stylesheet\" href=\"./media/syp_custom.css\" type=\"text/css\">\n");
    }
?>

    <script type="text/javascript">
        var SypStrings = {
            language: "<?php echo $lang ?>",
            poweredByLink: "<?php ptrans('powered by <a href=\"http://syp.renevier.net\">syp</a>')?>",
            noImageRegistered: "<?php ptrans('There is no image registered on this site.')?>"
        };
        var sypSettings =  {
            popupPos: <?php printf ($popuppos)?>
        };
    </script>
    <script src="./openlayers/OpenLayers.js" type="text/javascript"></script>
    <script src="./js/syp.js" type="text/javascript"></script>

    <noscript>
    <style type="text/css">
        #map {
            display: none;
        }
    </style>
    </noscript>

    </head>

    <body onload="SYP.init();">

        <noscript>
            <p><?php ptrans('SYP needs javascript. Please activate scripts in your browser.')?></p>
        </noscript>

        <div id="map"></div>
        <div id="message"></div>

    <div id="bigimg_container">
    <div id="bigimg_transparency"></div>
        <div id="bigimg_content">
            <img id="bigimg" onclick="SYP.closeBigImage()">
            <img id="bigimg_close" alt="<?php ptrans('close')?>" 
                    src="openlayers/theme/default/img/close.gif" 
                    onclick="SYP.closeBigImage()">
        </div>
    </div>

</body>
</html>
