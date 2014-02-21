<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

    $language_list = Array ();

function ptrans ($str) {
    echo trans ($str);
}

function trans ($str) {
    global $translations, $lang;

    $res = $translations[$lang][$str];
    if (!isset ($res) || strlen ($res) == 0) {
        return $str;
    } else {
        return $res;
    }
}

function parse_accept_language () {
    global $translations;
    $possibilities = array();
    if (isset ($_SERVER ['HTTP_ACCEPT_LANGUAGE'])) {
        // set to lower case now
        $accepts = explode (',',
                        strtolower ($_SERVER ['HTTP_ACCEPT_LANGUAGE']));
        foreach ($accepts as $acc) {
            if (preg_match ('/^\s*([a-zA-Z]+)(-[a-zA-Z]+)?\s*$/', $acc, $matches)) {
                $possibilities [$matches [1]] = 1.0;
            }
            if (preg_match ('/^\s*([a-zA-Z]+)(-[a-zA-Z]+)?\s*;\s*q\s*=\s*([0-9\.]+)\s*$/',
                            $acc, $matches)) {
                $val = floatval ($matches [3]);
                if ($val > 1.0) {
                    $val = 1.0;
                }
                $possibilities [$matches [1]] = max ($val, $possibilities [$matches [1]]);
            }
        }
        arsort ($possibilities);
        foreach ($possibilities as $lang => $value) {
            if (isset ($translations [$lang])) {
                return $lang;
            }
        }
    }
    return "en"; // nothing found; default to english
}

function other_languages ($current_lang) {
 $script = pathinfo ($_SERVER ["SCRIPT_NAME"], PATHINFO_FILENAME);
    $dotpos = strpos ($script, '.');
    if ($dotpos !== false) {
        $script = substr ($script, 0, $dotpos);
    }

    global $translations;
    $links = Array ();
    foreach ($translations as $lang => $obj) {
        $lang_name = $obj ["_language_name"];
        if ($lang == $current_lang) {
            array_push ($links, "<a>$lang_name</a>");
        } else  {
            array_push ($links, "<a href=\"$script.$lang.php\" title=\"$lang_name\" lang=\"$lang\" hreflang=\"$lang\">$lang_name</a>");
        }
    }                                                                                                                      
    echo "<div id=\"other-language\">" . join("", $links) . "</div>\n";
}

// load languages
foreach (scandir ("inc/i10n/") as $entry) {
    if (is_dir ("inc/i10n/$entry") && ($entry [0] != ".")) {
        $target = "inc/i10n/$entry/syp.php";
        if (is_file ($target)) {
            include $target;
        }
    }
}

// detects language                                                                                                         
$fname = pathinfo ($_SERVER ["SCRIPT_NAME"], PATHINFO_FILENAME);                                                            
$lang = ltrim (strstr ($fname, '.'), '.');
if ((!isset ($lang)) ||
    (strlen ($lang) == 0) ||
    (!isset ($translations [$lang]))) {
    $lang = parse_accept_language ();
}
