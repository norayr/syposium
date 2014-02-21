#!/usr/bin/php
<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

// only execute from command line
if (!(isset ($argc)) || !(isset ($argv))) {
    exit (0);
}

$ROOTDIR = "../../";
// scripts in rootdir we need to link to
$SCRIPTS = array ("admin", "index", "upgrade", "install");

function usage() {
    global $argv;
    return ("Usage: " . $argv[0] . " [lang1] [lang2]\n");
}

function warn($str) { // writes string to stderr
    error ($str);
}

function error($str) { // writes string to stderr
    $stderr = fopen ('php://stderr', 'w');
    fwrite ($stderr, $str);
    fclose ($stderr); 
}

function main($argv, $argc, $rootdir, $scripts) {
    $options = getopt("h");
    if (isset ($options ['h'])) {
        print usage ();
        return 0;
    }

    if ($argc <= 1) { // update all existing langs
        foreach (scandir (".") as $entry) {
            if (is_dir ($entry) && !($entry[0] == ".") && !($entry == "en")) {
                updatelang ($entry, $rootdir, $scripts);
            }
        }
    } else {
        foreach (array_slice ($argv, 1) as $item) {
            updatelang ($item, $rootdir, $scripts); 
        }
    }

    return 0;
}

function escape_newline ($str, $tab) {
    return str_replace ("\n", "\\n\" .\n$tab$tab\"", $str);
}
function escape_slash ($str) {
    $res = str_replace ("\\", "\\\\", $str);
    $res = str_replace ("\"", "\\\"", $res);
    return $res;
}

function escape_all ($str, $tab) {
    $str = escape_slash ($str);
    $str = escape_newline ($str, $tab);
    return $str;
}

function updatelang ($lang, $rootdir, $scripts) {
    if (!preg_match ('/^[a-zA-Z]{2,3}(-[a-zA-Z]{2,3})?$/', $lang)) {
        warn ("$lang is not a valid lang format.\n");
        return false;
    }
    if ($lang == "en") {
        warn ("en is reference language. It must be managed manually.\n");
        return false;
    }

    if (!is_dir ($lang)) {
        if (!mkdir ($lang)) {
            error ("could not create $lang directory.\n");
            return false;
        }
    }

    require ("en/syp.php");
    if (is_file ("$lang/syp.php")) {
        require ("$lang/syp.php");
    }

    $translator_name = $translations[$lang]["_translator_name"];
    $translator_mail = $translations[$lang]["_translator_mail"];
    $language_name   = $translations[$lang]["_language_name"];

    $tab = str_repeat (" ", 4);

    $tmpname = tempnam ("", "");
    $output = fopen ($tmpname, "w");

    fwrite ($output, "<?php\n");

    fwrite ($output, "$tab" . "\$translations['" . $lang . "'] = array(\n");
    fwrite ($output, "$tab$tab" . "// your name\n");
    fwrite ($output, "$tab$tab" . "\"_translator_name\" => \"" . escape_slash ($translator_name) . "\",\n");
    fwrite ($output, "\n");

    fwrite ($output, "$tab$tab" . "// your email\n");
    fwrite ($output, "$tab$tab" . "\"_translator_mail\" => \"" . escape_slash ($translator_mail) . "\",\n");
    fwrite ($output, "\n");

    fwrite ($output, "$tab$tab" . "// your language name in your language. It will be used to link to\n");
    fwrite ($output, "$tab$tab" . "// pages in your languages from pages in other\n");
    fwrite ($output, "$tab$tab" . "\"_language_name\" => \"" . escape_slash ($language_name) . "\",\n");
    fwrite ($output, "\n");

    fwrite ($output, "$tab$tab" . "/* starts translation */\n");
    fwrite ($output, "\n");
    fwrite ($output, "\n");

    foreach ($translations['en'] as $key => $value) {
        if ($key[0] == "_") {
            continue;
        }
        $value = $translations[$lang][$key];

        fwrite ($output, "$tab$tab" . "\"" . escape_all ($key, $tab) . "\"" . "\n");
        fwrite ($output, "$tab$tab  " . "=>\n");
        fwrite ($output, "$tab$tab" . "\"" . escape_all ($value, $tab) . "\"" . "\n");
        fwrite ($output, "$tab$tab  " . ",\n");
        fwrite ($output, "\n");
    }

    fwrite ($output, "$tab" . ")\n");
    fwrite ($output, "?>"); // <?php <- fixes vim syntax

    fclose($output);

    if (!rename ($tmpname, "$lang/syp.php")) {
        error ("could not move $tmpname to $lang/sys.php");
        unlink ($tmpname);
        return false;
    }
    if (!chmod ("$lang/syp.php", 0644)) {
        error ("could not set permissions to $lang/sys.php");
        return false;
    }

    foreach ($scripts as $script) {
        $link = $rootdir . "/"  . $script . "." . $lang . ".php";
        $target = $script . ".php";
        if (!file_exists ($link)) {
            if (!symlink ($target, $link)) {
                error ("could not link $target to $link");
            }
        }
    }
    return true;
}

exit (main($argv, $argc, $ROOTDIR, $SCRIPTS));
?>
