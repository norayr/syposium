<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
       "http://www.w3.org/TR/html4/loose.dtd">  
<html lang="<?php echo $lang?>">
<head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" >
      <link rel="stylesheet" href="./media/install.css" type="text/css" >
      <title><?php ptrans ('SYP wizard')?></title>
      <script type="text/javascript">
      var sypStrings = {
            emptyPasswordError: "<?php ptrans('Password cannot be empty')?>"
      };

      function init () {
        if (document.getElementById('db_host')) { 
            document.getElementById('db_host').focus();
            document.getElementById('db_host').select();
        } else if (document.getElementById('admin_pass')) { 
            document.getElementById('admin_pass').focus();
            document.getElementById('admin_pass').select();
        }
      }

      function checkpwd () {
          var pass = document.getElementById('admin_pass').value;
          if (!pass) {
              document.getElementById('empty_pass_error').innerHTML = sypStrings.emptyPasswordError;
              document.getElementById('empty_pass_error').style.display = "block";
              document.getElementById('admin_pass').focus();
              return false;
          }
          return true;
      }
      </script>
</head>
<body onload="init()">

<?php

    define ("CONFIG_FILE", "./inc/settings.php");

    require_once ("./inc/install_utils.php");

    if (version_compare (PHP_VERSION, '5.0.0', '<')) {
        par_error_and_leave (trans ("You need at least PHP version 5"));
    }

    function error_unwritable_config () {
        par_error_and_leave (trans ("Cannot save config file. You need either to set inc/ writable, or use manual method. See README.txt for more informations."));
    }

    function create_install_form () {
        if (isset ($_POST ["db_form_submit"])) {
            $type = $_POST ["db_type"];
            $host = $_POST ["db_host"];
            $name = $_POST ["db_name"];
            $user = $_POST ["db_user"];
            $prefix = $_POST ["db_prefix"];
            $title = $_POST ["site_title"];
        } else {
            $type = "mysql";
            $host = "localhost";
            $user = "syp";
            $name = "syp";
            $prefix = "syp_";
            $title = "SYP";
        }

        print '<form method="post" action="' . $_SERVER ["REQUEST_URI"] .  '"><fieldset>' . "\n";
        print '<legend>' . trans ("SYP configuration") . '</legend>' . "\n";

        $drivers = array();
        $handle = opendir("./inc/db");
        if (!$handle) {
            par_error_and_leave (trans ('Could not list <code>inc/db</code> directory'));
        }
        while (false !== ($file = readdir($handle))) {
            if ($file == "." or $file == "..") {
                continue;
            }
            $driver_name = substr($file,0,strrpos($file,'.'));
            if ($driver_name == "anydb") {
                continue;
            }
            array_push ($drivers, $driver_name);
        }
        closedir($handle);


        print '<div><label for="db_type" title="' .
              trans ("You can specify a database backend. Mysql is the most available for standard web hosting services.") .
              '">' . trans ("database backend:") . '</label>' . "\n" .
              '<select id="db_type" name="db_type">'. "\n";
        foreach ($drivers as $driver) {
            if ($driver == $type) {
                print '<option name="' . $driver . '" selected="true">' . $driver . '</option>' . "\n";
            } else {
                print '<option name="' . $driver . '">' . $driver . '</option>' . "\n";
            }
        }
        print "</select>" . "\n";

        print '<div><label for="db_host" title="' .
              trans ("address of the database server (example: localhost, db.myhost.com or 192.168.0.15).") .
              '">' . trans ("database server hostname:") . '</label>' . "\n" .
              '<input id="db_host" name="db_host" value="' . $host . '"></div>' . "\n";

        print '<div><label for="db_name" title="' .
              trans ("The name of the database that SYP will be installed into. The database must exist.") .
              '">' . trans ("database name:") . '</label>' . "\n" .
              '<input id="db_name" name="db_name" value="' . $name . '"></div>' . "\n";

        print '<div><label for="db_user" title="' .
              trans ("The username with which you connect to the database.") .
              '">' . trans ("database user:") . '</label>' . "\n" .
              '<input id="db_user" name="db_user" value="' . $user . '"></div>' . "\n";

        print '<div><label for="db_pass" title="' .
              trans ("The password with which you connect to the database.") .
              '">' . trans ("database password:") . '</label>' . "\n" .
              '<input id="db_pass" name="db_pass" type="password"></div>' . "\n";

        print '<div><label for="db_prefix" title="' .
              trans ("You can specify a table prefix. This way you can run multiple SYP installations in the same database, each with its own prefix.") .
              '">' . trans ("tables prefix:") . '</label>' . "\n" .
              '<input id="db_prefix" name="db_prefix" value="' . $prefix . '">' . "\n";

        print '<div><label for="site_title" title="' .
              trans ("The title you want to give to your SYP site.") .
              '">' . trans ("site title:") . '</label>' . "\n" .
              '<input id="site_title" name="site_title" value="' . $title . '">' . "\n";

        print '<div><input id="db_form_submit" name="db_form_submit" type="submit" value="' . trans ("Start install") . '"></div>';
        print '</fieldset></form>';
    }

    if (file_exists (CONFIG_FILE)) {
        require_once (CONFIG_FILE);
    } else if (isset ($_POST["db_form_submit"])) { // user has submitted form

        function _unquote($gpc_str) {
           if (!isset ($gpc_str)) {
               return $gpc_str;
           }
           if (get_magic_quotes_gpc ()) {
                return stripslashes ($gpc_str);
           } else {
               return $gpc_str;
           }
        }

        define (DBTYPE, _unquote ($_POST ["db_type"]));
        define (DBHOST, _unquote ($_POST ["db_host"]));
        define (DBNAME, _unquote ($_POST ["db_name"]));
        define (DBUSER, _unquote ($_POST ["db_user"]));
        define (DBPWD, _unquote ($_POST ["db_pass"]));
        define (DBPREFIX, _unquote ($_POST ["db_prefix"]));
        define (SITETITLE, _unquote ($_POST ["site_title"]));

        // default values
        define (UPLOADDIR, "upload");
        define (THUMBSDIR, "upload/_thumbs");
    } else {
        if (!is_writable (dirname (CONFIG_FILE))) {
            error_unwritable_config ();
        }

        create_install_form ();
        leave ();
    }

    if (!include_once ("./inc/db/" . DBTYPE . ".php")) {
        par_error_and_leave (trans("Unkown backend: ", DBTYPE));
    }
    require_once ("./inc/utils.php");

    try {
        $connection->connect (DBHOST, DBUSER, DBPWD, DBNAME, DBPREFIX);
    } catch (Exception $e) {
        switch ($e->getMessage ()) {
            case anydbConnection::err_driver_unavailable:
                par_error ($connection->getdbname() . ': ' . trans ('not supported'));
                break;
            case anydbConnection::err_connection:
                par_error (trans ('Could not connect to database.'));
                break;
            case anydbConnection::err_unknown_database:
                par_error (trans ('Database does not exist.'));
                break;
            default:
                par_error (trans ('Unknown error when connecting to database.'));
                break;
        }

        if (isset ($_POST ["db_form_submit"])) {
            // user had submited database informations. They seem to be wrong.
            // Ask again.
            create_install_form ();
        }
        leave ();
    }

    // we can connect to table. If config file does not exist, try to create it now.
    if (!file_exists (CONFIG_FILE)) {
        $handle = fopen ("./inc/settings.php.in", "r");
        $lines = array();
        if ($handle) {
            while (!feof ($handle)) {
                $line = fgets ($handle, 4096);
                foreach (array ("DBTYPE", "DBHOST", "DBNAME", "DBUSER", "DBPWD", "DBPREFIX", "SITETITLE") as $value) {
                    $pattern = "(define\s+\(\s*\"$value\"\s*,\s*\")[^\"]*(\"\s*\)\s*;)";
                    if (preg_match( "/$pattern/", $line, $match )) {
                        $line = $match[1] . addslashes (constant ($value)) . $match[2];
                        break;
                    }
                }
                array_push ($lines, $line);
            }
            fclose ($handle);
        } else {
            par_error_and_leave (trans ('Could not read <code>inc/settings.php.in</code>'));
        }

        $handle = fopen(CONFIG_FILE, 'w');
        if (!$handle) {
            error_unwritable_config ();
        }
        fwrite ($handle, join($lines));
        par_success (trans ('Config file created'));
    } else {
        par_success (trans ('Config file exists'));
    }

    try {
        $users_table_exists = $connection->users_table_exists ();
    } catch(Exception $e) {
        par_error_and_leave (trans ('Unknown error when checking user table.'));
    }

    if ($users_table_exists) {
        par_success (trans ('Found user table.'));
    } else {
        $empty_pass = (isset ($_POST ["admin_pass"]) && (strlen ($_POST ["admin_pass"]) == 0));
        if ($_POST ["admin_pass"]) {
            try {
                $connection->create_users_table (true);
            } catch (Exception $e) {
                par_error_and_leave (trans ('Error when creating user table.'));
            }
            par_success (trans ('User table created.'));
            try {
                $connection->setpwd ("admin", $_POST ["admin_pass"]);
            } catch (Exception $e) {
                par_error_and_leave (trans ('Error when initializing password.'));
            }
            par_success (trans ('Admin password initialized.'));

        } else {
            print ('<form class="center" method="post" action="" onsubmit="return checkpwd()">
                    <label for="admin_pass">' . trans ("choose admin password") . '</label>
                    <input id="admin_pass" name="admin_pass" type="password">');
            if ($empty_pass) {
                print ('<p class="error" id="empty_pass_error">' . trans('Password cannot be empty') . '</p>');
            } else {
                print ('<p class="error" style="display: none" id="empty_pass_error"></p>');
            }
            print ('<br><input type="submit"></form>');
            leave ();
        }
    }

    try {
        $items_table_exists = $connection->items_table_exists ();
    } catch (Exception $e) {
        par_error_and_leave (trans ('Unknown error when checking data table.'));
    }
    if ($items_table_exists) {
        par_success (trans ('Found data table.'));
    } else {
        try {
            $connection->create_items_table (true);
        } catch (Exception $e) {
            par_error_and_leave (trans ('Error when creating data table.'));
        }
        par_success (trans ('Data table created.'));
    }

    safe_create_writable_dir (UPLOADDIR);
    safe_create_writable_dir (getthumbsdir ());

    if (!function_exists ("gd_info")) {
        par_warn (trans ('It looks like GD extension is not installed.'));
    }

    par_success (trans ('SYP is installed. You can now go to <a href="admin.en.php">admin area</a>'));
?>

</body>
</html>
