<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

function headers_callback ($output) {
    $etag = md5 ($output);
    if ((isset ($_SERVER ["HTTP_IF_NONE_MATCH"])) && 
         ($_SERVER ["HTTP_IF_NONE_MATCH"] == $etag)) {
         header ("HTTP/1.1 304 Not Modified");
         exit ();
    }

    header ("Content-type: application/vnd.google-earth.kml+xml");
    // no-cache is needed otherwise IE does not try to get new version.
    header ("Cache-control: no-cache, must-revalidate");
    header (sprintf ("ETag: %s", $etag));

    return $output;
}

function main ($features) {

    echo '<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
';

    if (SITETITLE) {
        printf ('    <name>%s</name>', htmlspecialchars (SITETITLE));
    }
    foreach ($features as $feature) {
        $id = $feature->id;
        $title = htmlspecialchars ($feature->title, ENT_QUOTES);
        $description = htmlspecialchars ($feature->description, ENT_QUOTES);
        $imgurl = ($feature->imgpath ? 
                    image_url_from_imgpath ($feature->imgpath)
                    : "");
        $thumburl = ($feature->imgpath ? 
                    thumb_url_from_imgpath ($feature->imgpath)
                    : "");
        $lon = $feature->lon;
        $lat = $feature->lat;
        $alt = (strlen ($title) > 60) ?
                    (substr ($title, 0, 57) . '...') :
                    $title;

        if ($imgurl) {
            $imgurlHTML = sprintf ('<a href="%s"><img alt="%s" src="%s"></a>', $imgurl, $alt, $thumburl);
        } else {
            $imgurlHTML = "";
        }

        if ($description) {
            $descriptionHTML = sprintf ('<p>%s</p>', $description) ;
        } else {
            $descriptionHTML = "";
        }

        printf ('
        <Placemark id="%s">
            <name>%s</name>
            <description><![CDATA[
                %s
                %s
            ]]></description>
            <Point>
                <coordinates>%s,%s</coordinates>
            </Point>
        </Placemark>
', $id, $title, $descriptionHTML, $imgurlHTML, $lon, $lat);
    }

    echo' </Document>
    </kml>';
}

if (!@include_once ("./inc/settings.php")) {
    exit ("server error");
}
require_once ("./inc/utils.php");
require_once ("./inc/db/" . (defined ("DBTYPE")? DBTYPE: "mysql") . ".php");

try {
    $connection->connect (DBHOST, DBUSER, DBPWD, DBNAME, DBPREFIX);
    $features = $connection->listfeatures ($_GET ['from_user']);
} catch (Exception $e) {
    exit ("server error");
}

ob_start ("headers_callback");
main ($features);
ob_end_flush ()
?>
