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

    header ("Content-type: application/atom+xml; charset=UTF-8");
    header ("Cache-control: must-revalidate");
    header (sprintf ("ETag: %s", $etag));

    return $output;
}

function date3339 ($feature) {
    $date = date ('Y-m-d\TH:i:s', $feature->date);

    $matches = array();
    if (preg_match ('/^([\-+])(\d{2})(\d{2})$/',
                    date ('O', $feature->date), $matches)) {
        $date .= $matches [1] . $matches [2] . ':' . $matches [3];
    } else {
        $date .= 'Z';
    }
    return $date;
}

// method from http://diveintomark.org/archives/2004/05/28/howto-atom-id#other
function unique_id_from_feature ($feature) {
    $date = date('Y-m-d', $feature->date);
    $res = sprintf("tag:%s,%s:%d", gethost(), $date, $feature->id);
    return $res;
}

function unique_id_from_site () {
    $id = md5 (full_url_from_path (""));
    $res = sprintf("tag:%s,1970-01-01:%d", gethost, $id);
    return $res;
}

function main ($features) {
    printf ("<?xml version=\"1.0\" encoding=\"utf-8\"?>
<feed xmlns=\"http://www.w3.org/2005/Atom\"
      xmlns:georss=\"http://www.georss.org/georss\">\n");

    printf("  <link rel=\"alternate\" type=\"text/html\" href=\"%s\"/>\n",
            full_url_from_path (""));
    printf("  <link rel=\"self\" href=\"%s\" type=\"application/atom+xml\"/>\n",
            full_url_from_path (basename ($_SERVER ["PHP_SELF"])));
    printf("  <id>%s</id>\n", unique_id_from_site());

    if (count ($features) > 0) {
        printf("  <updated>%s</updated>\n", date3339 ($features[0]));
    }

    if (SITETITLE) {
        printf ("  <title>%s</title>\n", htmlspecialchars (SITETITLE));
    }

    if (WEBMASTERMAIL) {
        printf ("  <author>\n");
        printf ("    <email>%s</email>\n", WEBMASTERMAIL);
        printf("   </author>\n");
    }

    printf ("\n");

    foreach ($features as $feature) {
        printf ("    <entry>\n");

        if ($feature->title) {
            $title = htmlspecialchars ($feature->title, ENT_QUOTES);
        } else {
            $title = $feature->id;
        }
        printf ("      <title>%s</title>\n", $title);

        $rel_url = sprintf ("index.php?lat=%.18F&lon=%.18F&zoom=12",
                                         $feature->lat, $feature->lon);
        $link = htmlspecialchars (full_url_from_path ($rel_url), ENT_QUOTES);
        printf ("      <link rel=\"alternate\" type=\"text/html\" href=\"%s\"/>\n", $link);

        printf ("      <id>%s</id>\n", unique_id_from_feature ($feature));

        printf ("      <updated>%s</updated>\n", date3339 ($feature));

        if ($feature->description) {
            $contentHTML = sprintf ("<p>%s</p>", htmlspecialchars ($feature->description, ENT_QUOTES));
        } else {
            $contentHTML = sprintf ("<p>%s</p>", htmlspecialchars ($feature->title, ENT_QUOTES));
        }

        // FIXME: we consider thumbnail are correctly sized if gd library is
        // installed. That may not always be true. For example if gd was installed
        // after images were initially uploaded.
        if (function_exists ("imagecreatefromjpeg")) { 
            if ($feature->imgpath) {
                $imgurl = image_url_from_imgpath ($feature->imgpath);
                $thumburl = thumb_url_from_imgpath ($feature->imgpath);
                $contentHTML .= sprintf ('<a href="%s"><img alt="%s" src="%s"></a>', $imgurl, $alt, $thumburl);
            }
        }

        if (strlen ($contentHTML) != 0) {
            printf ("       <content type=\"html\">
                %s
       </content>\n", htmlspecialchars ($contentHTML));
        }

        printf("      <georss:point>%.18F %.18F</georss:point>\n",
                    $feature->lat, $feature->lon);

        printf("    </entry>\n\n");
    }
    printf ("</feed>");
}

if (!@include_once ("./inc/settings.php")) {
    exit ("server error");
}
require_once ("./inc/db/" . (defined ("DBTYPE")? DBTYPE: "mysql") . ".php");
require_once ("./inc/utils.php");

try {
    $connection->connect (DBHOST, DBUSER, DBPWD, DBNAME, DBPREFIX);
    $features = $connection->mostrecentfeatures (10);
} catch (Exception $e) {
    exit ("server error");
}

ob_start ("headers_callback");
main ($features);
ob_end_flush ()
?>
