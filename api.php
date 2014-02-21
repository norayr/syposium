<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

function exit_document ($body) {
    $charset_meta = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    exit ("<html>$charset_meta<head></head><body>$body</body></html>");
}

function success ($reason) {
    exit_document ("<success request=\"$reason\"></success>");
}

function success_changepass ($username) {
    $res = "<success request=\"changepass\"><user>" .
            htmlspecialchars ($username) .
            "</user></success>";
    exit_document ($res);
}

function success_newuser ($username) {
    $res = "<success request=\"newuser\"><user>" .
            htmlspecialchars ($username) .
            "</user></success>";
    exit_document ($res);
}

function success_auth ($user) {
    $res = "<success request=\"$reason\"><user>" . 
            htmlspecialchars ($user) .
            "</user></success>";
    exit_document ($res);
}

function success_feature ($feature, $request) {
    $res = "<success request=\"$request\"><feature>";
    $res .= "<id>" .  $feature->id .  "</id>";

    $res .= "<imgurl>" .
             ($feature->imgpath ? 
                    image_url_from_imgpath ($feature->imgpath)
                    : "") .
             "</imgurl>";

    $res .= "<description>" .
                 htmlspecialchars ($feature->description) .
                 "</description>";

    // XXX: we do not use <title> because that would be interpreted and
    // altered by browers html parser
    $res .= "<heading>" . 
            htmlspecialchars ($feature->title) .
            "</heading>";

    $res .= "<lon>" . $feature->lon . "</lon>";
    $res .= "<lat>" . $feature->lat . "</lat>";
    $res .= "</feature></success>";
    exit_document ($res);
}

function success_delete_feature ($feature) {
    $res = "<success request=\"del\"><feature>";
    $res .= "<id>" .  $feature->id .  "</id>";
    $res .= "</feature></success>";
    exit_document ($res);
}

function error ($reason) {
    exit_document ("<error reason=\"$reason\"></error>");
}

function error_newuser_exists () {
    error ("newuser_exists");
}

function error_feature ($id, $reason) {
    $res = "<error reason=\"$reason\"><feature>";
    $res .= "<id>" .  $id .  "</id>";
    $res .= "</feature></error>";
    exit_document ($res);
}

function error_nochange ($id) {
    error_feature ($id, "nochange");
}
function error_unreferenced ($id) {
    error_feature ($id, "unreferenced");
}

function error_server () {
    error ("server");
}

function error_wrongpass () {
    error ("wrongpass");
}

function error_unauthorized () {
    error ("unauthorized");
}

function error_request () {
    error ("request");
}

function error_file_too_big () {
    error ("toobig");
}

function error_notanimage () {
    error ("notimage");
}

function save_uploaded_file ($file, $con) {
    $dest = "";
    if (isset ($file) && ($file ["error"] != UPLOAD_ERR_NO_FILE)) {
        img_check_upload ($file);
        $dest = unique_file (UPLOADDIR, $file ["name"], $con);
        if (!isset ($dest) || 
                (!move_uploaded_file ($file ["tmp_name"], $dest))) {
            error_server ();
        }
        $mini_dest = getthumbsdir () . "/mini_" . basename_safe ($dest);

        if (!create_thumbnail_or_copy ($dest, $mini_dest)) {
            error_server ();
        }
    }
    return basename_safe ($dest);
}

function img_check_upload ($file) {
    if (!is_uploaded_file ($file ["tmp_name"])) {
        if ($file ["error"] ==  UPLOAD_ERR_INI_SIZE) {
            error_file_too_big ();
        } else {
            error_server ();
        }
    }
    if (!getimagesize ($file ["tmp_name"])) {
        error_notanimage ();
    }
}

function delete_image_if_unused ($imgpath, $con) {
    if (!isset ($imgpath) || (strlen ($imgpath) == 0)) {
        return;
    }
    if ($con->imgpath_exists ($imgpath)) {
        return;
    }

    $path = UPLOADDIR . "/" . $imgpath;
    if (file_exists ($path)) {
        unlink ($path);
    }

    $thumb_path = getthumbsdir () . "/mini_" . $imgpath;
    if (file_exists ($thumb_path)) {
        unlink ($thumb_path);
    }
}

function unique_file ($dirname, $relpath, $con) {
   $relpath = str_replace ('/', '', $relpath); // strip slashes from path
   $relpath = str_replace ('\\', '', $relpath); // strip antislashes from path
   $filename = $dirname . '/' . $relpath;
   $counter = 1;

   $dotpos = strrpos ($relpath, '.');
   if ($dotpos) {
       $base = substr ($relpath, 0, $dotpos);
       $ext = substr ($relpath, $dotpos + 1);
   } else {
       $base = $relpath;
       $ext = "";
   }

   while ($counter < 1000) {
       if (!file_exists ($filename) && 
           !($con->imgpath_exists (basename_safe ($filename)))) {
           return $filename;
       } else {
            $counter++;
            $filename = $dirname . '/' . $base . '_' . $counter . '.' . $ext;
       }
   }
   // we tried to find an unused filename 1000 times. Give up now.
   return null;
}

function setcookies ($user, $pwd) {
    // cookie will be valid for 2 weeks. I've chosen that value
    // arbitrarily, and it may change in the future.
    $time = time () + 14 * 60 * 24 * 60;
    if (version_compare (PHP_VERSION, '5.2.0', '>=')) {
        setcookie (sprintf ("%sauth", DBPREFIX), md5 ($pwd), $time, "" , "", false, true);
        setcookie (sprintf ("%suser", DBPREFIX), $user, $time, "" , "", false, true);
    } else {
        setcookie (sprintf ("%sauth", DBPREFIX), md5 ($pwd), $time, "" , "", false);
        setcookie (sprintf ("%suser", DBPREFIX), $user, $time, "" , "", false);
    }

}

function check_auth ($con, $user, $pwd, $auth_only) {
    $authentificated = false;

    if (isset ($pwd)) {
        if ($con->checkpwdmd5 ($user, md5 ($pwd))) {
            setcookies ($user, $pwd);
            $authentificated = true;
            if ($auth_only) {
                success_auth ($user);
            }
        } else {
            error_unauthorized ();
        }
    }

    if (!$authentificated && !($con->checkpwdmd5 (
                             $_COOKIE [sprintf ("%suser",  DBPREFIX)],
                             $_COOKIE [sprintf ("%sauth",  DBPREFIX)]))) {
        error_unauthorized ();
    }
}

function main ($con) {
    if (!isset ($_POST ["request"])) {
        error_request ();
    }

    $pwd = unquote ($_POST ["password"]);
    $user = unquote ($_POST ["user"]);
    // does user only want authentication or does he want to do other things
    $auth_only = ($_POST ["request"] == "auth");
    check_auth ($con, $user, $pwd, $auth_only);
    if (!$user) {
        $user = $_COOKIE [sprintf ("%suser",  DBPREFIX)];
    }

    switch ($_POST ["request"]) {
        case "update":
            $id = $_POST ["fid"];
            $feature = $con->getfeature ($id);
            if (!isset ($feature)) {
                error_unreferenced ($id);
            }
            if (($feature->user != $user) && ($user != "admin")) {
                error_unauthorized ();
            }

            // no file uploaded, but editor currently has an image: it means
            // image was not changed
            if ($_POST ["keep_img"] == "yes") {
                $imgpath = $feature->imgpath;
            } else {
                $imgpath = save_uploaded_file ($_FILES ["image_file"], $con);
            }

            $lon = $_POST ["lon"];
            $lat = $_POST ["lat"];
            $title = unquote ($_POST ["title"]);
            $description = unquote ($_POST ["description"]);

            try {
                $new_feature = new feature ($id, $lon, $lat, $imgpath, $title, $description, 0, $user);
            } catch (Exception $e) {
                error_request ();
            }

            if (($new_feature->lon == $feature->lon) &&
                ($new_feature->lat == $feature->lat) &&
                ($new_feature->title == $feature->title) &&
                ($new_feature->imgpath == $feature->imgpath) &&
                ($new_feature->description == $feature->description)) {
                error_nochange ($feature->id);
            }

            $old_imgpath = "";
            if ($feature->imgpath && ($feature->imgpath != $new_feature->imgpath)) {
                $old_imgpath = $feature->imgpath;
            }

            try {
                $con->save_feature ($new_feature);
            } catch (Exception $e) {
                error_server ();
            }
            if ($old_imgpath) {
                try {
                    delete_image_if_unused ($old_imgpath, $con); 
                } catch (Exception $e) {}
            }
            success_feature ($new_feature, "update");
        break;
        case "add":
            $imgpath = save_uploaded_file ($_FILES ["image_file"], $con);

            $lon = $_POST ["lon"];
            $lat = $_POST ["lat"];
            $title = unquote ($_POST ["title"]);
            $description = unquote ($_POST ["description"]);
            try {
                $feature = new feature (null, $lon, $lat, $imgpath, $title, $description, 0, $user);
            } catch (Exception $e) {
                error_request ();
            }
            try {
                $feature = $con->save_feature ($feature);
            } catch (Exception $e) {
                error_server ();
            }
            success_feature ($feature, "add");
        break;
        case "del":
            $id = $_POST ["fid"];
            $feature = $con->getfeature ($id);
            if (!isset ($feature)) {
                error_unreferenced ($id);
            }
            if ($feature->user != $user) {
                error_unauthorized ();
            }
            $imgpath = $feature->imgpath;

            try {
                $con->delete_feature ($feature);
            } catch (Exception $e) {
                error_server ();
            }

            try {
                delete_image_if_unused ($imgpath, $con);
            } catch (Exception $e) {}

            success_delete_feature ($feature);
        case "changepass":
            $currpass = unquote ($_POST ["pass_current"]);
            if (!$con->checkpwdmd5 ($user, md5 ($currpass))) {
                error_wrongpass ();
            }
            $newpass = unquote ($_POST ["pass_new"]);
            try {
                $con->setpwd ($user, $newpass);
            } catch (Exception $e) {
                if ($e->getMessage () == anydbConnection::err_query) {
                    error_request ();
                }
                error_server ();
            }
            setcookies ($user, $newpass);
            success_changepass ($user);
        break;
        case "newuser":
            if ($user != "admin") {
                error_unauthorized ();
            }
            $newuser_name = unquote ($_POST ["newuser_name"]);
            if (!$newuser_name) {
                error_request ();
            }
            if ($con->user_exists ($newuser_name)) {
                error_newuser_exists ();
            }
            $newuser_password = unquote ($_POST ["newuser_password"]);
            try {
                $con->setpwd ($newuser_name, $newuser_password);
            } catch (Exception $e) {
                if ($e->getMessage () == anydbConnection::err_query) {
                    error_request ();
                }
                error_server ();
            }
            success_newuser ($newuser_name);
        break;
        default:
            error_request();
        break;
    }

    error_server ();
}

if (!@include_once ("./inc/settings.php")) {
    error_server ();
}
require_once ("./inc/db/" . (defined ("DBTYPE")? DBTYPE: "mysql") . ".php");
require_once ("./inc/utils.php");

try {
    $connection->connect (DBHOST, DBUSER, DBPWD, DBNAME, DBPREFIX);
} catch (Exception $e) {
    error_server ();
}

main ($connection);
?>
