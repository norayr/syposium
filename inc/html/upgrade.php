<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */
    if (!@include_once ("./inc/settings.php")) {
        header ('Location: install.php');
    }
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
       "http://www.w3.org/TR/html4/loose.dtd">  
<html lang="<?php echo $lang?>">
<head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" >
      <link rel="stylesheet" href="./media/common.css" type="text/css" >
      <title><?php ptrans ('SYP upgrade')?></title>
</head>
<body>
<?php
    function create_all_thumbs($con) {
        $features = $con->listfeatures ("admin");
        foreach ($features as $feature) {
            if ($feature->imgpath) {
                $imgfilename = UPLOADDIR . "/" . $feature->imgpath;
                $thumbfilename = getthumbsdir () .  "/mini_" . $feature->imgpath;
                if ((file_exists ($imgfilename)) && 
                    (!(file_exists ($thumbfilename)))) {
                        create_thumbnail_or_copy ($imgfilename, $thumbfilename);
                }
            }
        }
        return true;
    }

    require_once ("./inc/db/" . (defined ("DBTYPE")? DBTYPE: "mysql") . ".php");
    require_once ("./inc/install_utils.php");
    require_once ("./inc/utils.php");

    $error = false;
    try {
        $connection->connect (DBHOST, DBUSER, DBPWD, DBNAME, DBPREFIX);
        $usrtblexists = $connection->users_table_exists ();
        $itemstblexists = $connection->items_table_exists ();
    } catch (Exception $e) {
        $error = true;
    }
    if (!$usrtblexists || !$itemstblexists) {
        $error = true;
    }
    if ($error) {
       die(sprintf('<p class="error center">%s</p>', trans('SYP is not correctly installed. Please follow README.txt instructions
       and go to <a href="install.en.php">wizard</a>.')));
    }

    safe_create_writable_dir (getthumbsdir ());
    if (create_all_thumbs ($connection)) {
        par_success (trans('Thumbnails successfully created.'));
    } else {
        par_error_and_leave (trans('Error when creating thumbnails.'));
    }

    par_success (trans ('SYP upgrade went smoothly. You can now go to <a href="admin.en.php">admin area</a>'));

?>
</body>
</html>
