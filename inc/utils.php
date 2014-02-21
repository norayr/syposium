<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

// using include because that file may be sourced even if config file has not
// been created.
@include_once ("inc/settings.php");

function getthumbsdir () {
    if (THUMBSDIR) {
        return rtrim (THUMBSDIR, "/");
    } else {
        $uploaddir = rtrim (UPLOADDIR, "/");
        return $uploaddir . "/" . "_thumbs";
    }
}

function gethost () {
    $host = $_SERVER ["HTTP_HOST"];
    $colpos = strpos ($host, ':');
    // some web clients add port informations in Host header
    if ($colpos !== false) {
        $host = substr ($host, 0, $colpos);
    }
    return $host;
}

function ext_safe ($path) {
    $basename = basename_safe ($path);
    return end (explode (".", $basename)); 
}

function basename_safe ($path) {
    return end (explode ("/", $path)); 
}

function unquote($gpc_str) {
   if (!isset ($gpc_str)) {
       return $gpc_str;
   }
   if (get_magic_quotes_gpc ()) {
        return stripslashes ($gpc_str);
   } else {
       return $gpc_str;
   }
}

function thumb_url_from_imgpath ($filename) {
    if (defined ("THUMBSDIRURL") && (strlen (THUMBSDIRURL) != 0)) {
        return rtrim (THUMBSDIRURL, '/') . "/mini_" . rawurlencode ($filename);
    }
    return full_url_from_path (getthumbsdir () . "/mini_" . rawurlencode ($filename));
}

function image_url_from_imgpath ($filename) {
    if (defined ("IMGSDIRURL") && (strlen (IMGSDIRURL) != 0)) {
        return rtrim (IMGSDIRURL, '/') . "/" . rawurlencode ($filename);
    }

    return full_url_from_path (UPLOADDIR . "/" . rawurlencode ($filename));
}

function full_url_from_path ($path) {
    $rel_path = $path;

    while (substr($rel_path, 0, 2) == "./") { // strips ./
        $rel_path = substr ($rel_path, 2);
    }

    if ($rel_path [0] == "/") {
        $path = $rel_path;
    } else {
        $script_dir = dirname ($_SERVER ["SCRIPT_NAME"]);
        while ((substr ($rel_path, 0, 3) == "../") &&
                (strlen($script_dir) != 0)) {
            $rel_path = substr ($rel_path, 3);
            while (substr($rel_path, 0, 2) == "./") {
                $rel_path = substr ($rel_path, 2);
            }
             $script_dir = substr ($script_dir, 0, strrpos ($script_dir, "/"));
        }
        if ((strlen ($script_dir) == 0) && (substr ($rel_path, 0, 3) == "../")) {
            return null;
        }
        $path = "$script_dir/$rel_path";
    }

    $host = gethost();
    $port = $_SERVER ["SERVER_PORT"];
    if ($_SERVER ["HTTPS"] == "on") {
        $proto = "https";
    } else {
        $proto = "http";
    }

    if (($port == "80" && $proto == "http") ||
        ($port == "443" && $proto == "https")) {
        $port = "";
    } else {
        $port = ":$port";
    }

    return "$proto://$host$port$path";
}

function create_thumbnail_or_copy ($filename, $destfile) { 
    try {   
        $thumbnail_ok = create_thumbnail ($filename, $destfile);
    } catch (Exception $e) {
        $thumbnail_ok = false;
    }
    if (!$thumbnail_ok) {
        if (!copy ($filename, $destfile)) {
            return false; 
        }
    }   
    return true;
}

function create_thumbnail ($filename, $destfile) {
    if (!function_exists ("imagecreatefromjpeg")
        || !function_exists ("imagecreatefrompng")) {
        return false;
    }
    $ext = strtolower (ext_safe ($filename));
    if ($ext == "jpg" || $ext == "jpeg") {
        $image = imagecreatefromjpeg ($filename);
    } else if ($ext == "png") {
        $image = imagecreatefrompng ($filename);
    } else {
        return false;
    }

    if ($image === false) {
        return false;
    }

    if (defined (THUMBSMAXSIZE) && (THUMBSMAXSIZE > 0)) {
        $thumbsmaxsize = THUMBSMAXSIZE;
    } else {
        $thumbsmaxsize = 400; // default value;
    }

    $width = imageSX ($image);
    $height = imageSY ($image);
    if (($width  <= $thumbsmaxsize) || ($height <= $thumbsmaxsize)) {
        return false;
    }

    if ($width > $height) {
        $thumb_width = $thumbsmaxsize;
        $thumb_height = $height * ($thumbsmaxsize / $width);
    } else if ($width  < $height) {
        $thumb_width = $width * ($thumbsmaxsize / $height);
        $thumb_height = $thumbsmaxsize;
    } else if ($width  == $height) {
        $thumb_width = $thumbsmaxsize;
        $thumb_height = $thumbsmaxsize;
    }

    $thumb_image = ImageCreateTrueColor ($thumb_width, $thumb_height);
    if ($thumb_image === false) {
        return false;
    }
    if (!imagecopyresampled ($thumb_image, $image, 0, 0, 0, 0,
                        $thumb_width, $thumb_height, $width, $height)) {
        return false;
    }

    if ($ext == "jpg" || $ext == "jpeg") {
        if (!imagejpeg ($thumb_image, $destfile, 100)) {
            return false;
        }
    } else if ($ext == "png") {
        if (!imagepng ($thumb_image, $destfile)) {
            return false;
        }
    }

    imagedestroy ($image); 
    imagedestroy ($thumb_image); 

    return true;
}
?>
