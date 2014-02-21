<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

function safe_create_dir ($dirname) {
    if (is_dir ($dirname)) {
        return;
    }
    if (file_exists ($dirname)) {
        par_error_and_leave ($dirname . ": " . trans ('exist but is not a directory'));
    }
    if (!mkdir ($dirname)) {
        par_error_and_leave ($dirname . ": " . trans ('could not create directory'));
    } else {
        par_success ($dirname . ": " . trans ('directory created'));
    }
}

function safe_create_writable_dir ($dirname) {
    safe_create_dir ($dirname);
    if (!is_writeable ($dirname) || !is_executable ($dirname)) {
        par_error_and_leave ($dirname . ": " . trans ('could not write in directory'));
    }
}

function leave () {
    exit ("\n</body></html>");
}
function par_success ($message) {
    printf ("<p class=\"success center\">%s</p>", $message);
}
function par_error ($message) {
    printf ("<p class=\"error center\">%s</p>", $message);
}
function par_warn ($message) {
    printf ("<p class=\"warn center\">%s</p>", $message);
}
function par_error_and_leave ($message) {
    printf ("<p class=\"error center\">%s</p>", $message);
    leave ();
}
?>
