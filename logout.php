<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

if (!@include_once ("./inc/settings.php")) {
    header ('Location: index.php');
}

if (version_compare (PHP_VERSION, '5.2.0', '>=')) {
    setcookie (sprintf ("%sauth", DBPREFIX), "", time () - 3600, "" , "",false, true);
    setcookie (sprintf ("%suser", DBPREFIX), "", time () - 3600, "" , "",false, true);
} else {
    setcookie (sprintf ("%sauth", DBPREFIX), "", time () - 3600, "" , "",false);
    setcookie (sprintf ("%suser", DBPREFIX), "", time () - 3600, "" , "",false);
}
header ('Location: index.php');
?>
