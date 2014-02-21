<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

$error = false;

if (!@include_once ("./inc/settings.php")) {
    $error = true;
}
require_once ("./inc/db/" . (defined ("DBTYPE")? DBTYPE: "mysql") . ".php");
require_once ("./inc/utils.php");

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
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
       "http://www.w3.org/TR/html4/loose.dtd">  
<html lang="<?php echo $lang?>">
<head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" >
      <title><?php ptrans('SYP admin')?></title>
      <link rel="stylesheet" href="./media/admin.css" type="text/css">
</head>
<body>
    <p class="error center"><?php ptrans('SYP is not correctly installed. Please follow README.txt instructions
       and go to <a href="install.en.php">wizard</a>.')?></p>
</body>
</html>
<?php
    exit ();
    } // if ($error)

    $user = $_COOKIE [sprintf ("%suser", DBPREFIX)];
    $pwd = $_COOKIE [sprintf ("%sauth", DBPREFIX)];
    $logged = ($connection->checkpwdmd5 ($user, $pwd));
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
       "http://www.w3.org/TR/html4/loose.dtd">  
<html lang="<?php echo $lang?>">
<head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" >
      <title><?php ptrans('SYP admin');?></title>

      <link rel="stylesheet" href="./media/admin.css" type="text/css">
      <link rel="stylesheet" href="./openlayers/theme/default/style.css" type="text/css">

<?php
    if (file_exists ("./media/admin_custom.css")) {
        printf("    <link rel=\"stylesheet\" href=\"./media/admin_custom.css\" type=\"text/css\">\n");
    }
?>

      <script type="text/javascript">
        var SypStrings = {
            AddItem: "<?php ptrans('add a place')?>",
            Cancel: "<?php ptrans('cancel')?>",
            DragDropHowto: "<?php ptrans('You can move an item by drag & droping it.')?>",
            SelectHowto: "<?php ptrans('To modify an item data, select matching marker.')?>",
            AddHowto: "<?php ptrans('Click on the map to add a marker.')?>",
            ServerError: "<?php ptrans('There was a server error.')?>",
            UnreferencedError: "<?php ptrans('Item was not registered on the server.')?>",
            NochangeError: "<?php ptrans('No change was made.')?>",
            RequestError: "<?php ptrans('Server did not understood request. That\'s probably caused by a bug in SYP.')?>",
            ToobigError: "<?php ptrans('Image was too big and was not accepted by server.')?>",
            UnauthorizedError: "<?php ptrans('Password is not correct.')?>",
            NotimageError: "<?php ptrans('File does not look like an image.')?>",
            UnconsistentError: "<?php ptrans('Server reply was inconsistent.')?>",
            DelSucces: "<?php ptrans('Successfully removed.')?>",
            UpdateSucces: "<?php ptrans('Save took place correctly.')?>",
            emptyPasswordError: "<?php ptrans('Password cannot be empty')?>",
            userPasswordmatchError: "<?php ptrans('Passwords do not match.')?>",
            changeSamePass: "<?php ptrans('New password is the same as old password.')?>",
            changePassBadPass: "<?php ptrans('Bad password.')?>",
            changePassSuccess: "<?php ptrans('Password successfully changed.')?>",
            newUserNonameError: "<?php ptrans('User name has not been set.')?>",
            newUserExistsError: "<?php ptrans('User already exists in database.')?>",
            newUserSuccess: "<?php ptrans('User successfully added.')?>"
        };

        var sypSettings =  {
            loggedUser: <?php printf ($logged ? "\"$user\"": "null")?>
        };

      </script>
      <script src="./js/jquery-1.3.2.js" type="text/javascript"></script>
      <script src="./openlayers/OpenLayers.js" type="text/javascript"></script>
      <script src="./js/admin.js" type="text/javascript"></script>

    <noscript>
    <style type="text/css">
        #map, #editor, #admin, #login_area {
            display: none;
        }
    </style>
    </noscript>

</head>

<body>

    <noscript>
    <p><?php ptrans('SYP needs javascript. Please activate scripts in your browser.')?></p>
    </noscript>

    <div id="header">
    <?php other_languages($lang) ?>
    <div id="user_management">
        <p id="logout" class="user_link"><a href="logout.php"><?php ptrans('Logout')?></a></p>
        <p id="change_pass" class="user_link"><a href=""><?php ptrans('Change my password')?></a></p>
        <p id="add_user" class="user_link"><a href=""><?php ptrans('Add a co-administrator')?></a></p>
    </div>
        <div id="user_area">
            <input id="user_close" type="image" src="openlayers/theme/default/img/close.gif"
                        title="<?php ptrans('close without saving')?>" alt="<?php ptrans('close')?>">
            <form id="changepass" method="post">
                <label for="pass_current"><?php ptrans('current password:')?></label>
                <br>
                <input id="pass_current" name="pass_current" type="password">
                <br>
                <label for="pass_new"><?php ptrans('new password:')?></label>
                <br>
                <input id="pass_new" name="pass_new" type="password">
                <br>
                <label for="pass_new_confirm"><?php ptrans('confirm new password:')?></label>
                <br>
                <input id="pass_new_confirm" name="pass_new_confirm" type="password">
                <br>
                <input id="pass_submit" name="pass_submit" type="submit" value="<?php ptrans('Validate password')?>">
                <input type="hidden" name="request" value="changepass">
            </form>
            <form id="newuser" method="post">
                <label for="newuser_name"><?php ptrans('user name:')?></label>
                <br>
                <input id="newuser_name" name="newuser_name">
                <br>
                <label for="newuser_password"><?php ptrans('user password:')?></label>
                <br>
                <input id="newuser_password" name="newuser_password" type="password" value="">
                <br>
                <label for="newuser_password_confirm"><?php ptrans('confirm password:')?></label>
                <br>
                <input id="newuser_password_confirm" name="newuser_password_confirm" type="password">
                <br>
                <input id="newuser_submit" name="newuser_submit" type="submit" value="<?php ptrans('Validate user')?>">
                <input type="hidden" name="request" value="newuser">
                </form>
                <p id="user_comm" class="center"></p>
                <p id="user_throbber" class="throbber center">
                    <?php ptrans('Connecting')?>
                    <img src="media/newuser-throbber.gif" alt="<?php ptrans('throbber')?>">
                </p>
        </div>
    </div>


    <div id="map"></div>

    <div id="admin" class="center">
        <input id="newfeature_button" type="button" value="<?php ptrans('add a place')?>">
        <p id="server_comm"></p>
        <p id="instructions"></p>
    </div>

    <div id="editor" class="center">
        <input id="editor_close" type="image" src="openlayers/theme/default/img/close.gif"
             title="<?php ptrans('close without saving')?>" alt="<?php ptrans('close')?>">
        <form id="feature_update" method="post" enctype="multipart/form-data">
            <label for="title"><?php ptrans('title:')?></label><br>
            <input id="title" name="title"><br>
            <label for="description"><?php ptrans('description:')?></label><br>
            <textarea id="description" name="description" rows="4"></textarea><br>
            <div><img id="img"></div>
            <input id="image_delete" type="button" value="<?php ptrans('delete image')?>">
            <div>
                <label for="image_file"><?php ptrans('add an image:')?></label>
                <input id="image_file" type="file" name="image_file">
            </div>
            <br>
            <div class="center">
            <input id="validate_editor" type="submit" value="<?php ptrans('Validate changes')?>">
            </div>
            <input type="hidden" name="request">
            <input type="hidden" name="lon">
            <input type="hidden" name="lat">
            <input type="hidden" name="fid">
            <input type="hidden" name="keep_img">
        </form>
        <form id="feature_delete" method="post">
            <input id="delete" type="submit" value="<?php ptrans('Delete item')?>">
            <input type="hidden" name="request" value="del">
            <input type="hidden" name="fid">
        </form>
        <p id="editor_throbber" class="throbber center">
            <?php ptrans('Connecting')?>
            <img src="media/editor-throbber.gif" alt="<?php ptrans('throbber')?>">
        </p>
    </div>

      <div id="login_area"
        <?php 
        if ($logged) {
            echo ' class="hidden"';
        }
      ?>>
     <div id="login_transparency"></div>
     <div id="login_padding"></div>
     <div id="login_content">
        <form id="login_form" method="post">
        <div id="cookie_warning" class="center warn"><?php ptrans('You need to have cookies enabled to administrate SYP')?></div>
            <table>
                <tr>
                    <td><label for="user"><?php ptrans('user')?></label></td>
                    <td style="width: 100%"><input id="user" name="user"></td>
                </tr>
                <tr>
                    <td><label for="password"><?php ptrans('password')?></label></td>
                    <td style="width: 100%"><input id="password" name="password" type="password"></td>
                </tr>
            </table>
            <p class="center">
                <input id="login_submit" type="submit" value="<?php ptrans('Login')?>">
                <input type="hidden" name="request" value="auth">
            </p>
            <p id="pwd_throbber" class="throbber center">
                <?php ptrans('Connecting')?>
                <img src="media/pwd-throbber.gif" alt="<?php ptrans('throbber')?>">
            </p>
            <p class="error center" id="login_error"></p>
        </form>
     </div>
     </div>

     <iframe id="api_frame" name="api_frame" src="" frameborder="0" width="0" height="0"></iframe>

</body>
</html>
