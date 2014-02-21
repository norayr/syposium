<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

class feature {
    private $id = null;
    private $lon = null;
    private $lat = null;
    private $imgpath = null;
    private $title = null;
    private $description = null;
    private $date = 0;
    private $user = null;

    const err_lonlat_invalid = 1;

    function __construct ($id, $lon, $lat, $imgpath, $title, $description, $date, $user) {
        $this->imgpath = $imgpath;

        // id
        if (isset ($id)) {
            $this->id = $id;
        }

        // title
        $this->title = $title;

        // description
        $this->description = $description;

        // date
        $this->date = $date;

        // user
        $this->user = $user;

        // longitude
        if (!isset ($lon) || !is_numeric ($lon) ||
             ($lon < -180) || ($lon > 180)) {
            throw new Exception (self::err_lonlat_invalid);
        }
        $this->lon = $lon;

        // latitude
        if (!isset ($lat) || !is_numeric ($lat) ||
             ($lat < -90) || ($lat > 90)) {
            throw new Exception (self::err_lonlat_invalid);
        }
        $this->lat = $lat;
    }

    public function __get ($attr) {
        if (isset ($this->$attr)) return $this->$attr;
            else throw new Exception ('Unknow attribute '.$attr);
    }

    public function __set ($attr,$value) {
        throw new Exception ('properties can only be set in constructor');
    }

}

interface anydbConnection {
    const err_driver_unavailable = 1;
    const err_connection = 2;
    const err_unknown_database = 3;
    const err_query = 3;

    /*
     * connect to database; That method may be called multiple times.
     */
    public function connect($host, $user, $pwd, $dbname, $dbprefix);

    /*
     * return true if users table already exists
     */
    public function users_table_exists();

    /*
     * create users table; 
     * throws an err_query error in case users table already exists.
     */
    public function create_users_table();

    /*
     * return true if items table already exists
     */
    public function items_table_exists();

    /*
     * create items table;
     * throws an err_query error in case items table already exists.
     */
    public function create_items_table();

    /*
     * returns true if $usrname is name of an existing user, false otherwise.
     */
    public function user_exists ($usrname);

    /*
     * set password $pwd for user $usrname. If $usrname does not exist, create
     * it.
     * throws an err_query error in case $pwd is null
     */
    public function setpwd($usrname, $pwd);

    /*
     * check that $pwd_md5 is md5 for $username password.
     */
    public function checkpwdmd5($usrname, $pwd_md5);

    /*
     * saves feature in database. If feature has an id, feature will be updated
     * in database; otherwise it will be created. Returns saved feature
     */
    public function save_feature($feature);

    /*
     * delete feature from database. Returns true in case of success, even if
     * image was not referenced in the database.
     */
    public function delete_feature($feature);

    /*
     * Returns feature with given id. If none exists, returns null.
     */
    public function getfeature($id);

    /*
     * returns an array of features managed by $user. If $user is undefined or
     * if user is "admin", return all available features.
     */
    public function listfeatures($user);

    /*
     * returns the most recent features sorted by date. If $num_features is not
     * defined or is null, returns all features sorted by date.
     */
    public function mostrecentfeatures($num_features);

    /*
     * returns true if a feature with imgpath exists
     */
    public function imgpath_exists($imgpath);

    /*
     * get name of database backend
     */
    public function getdbname();
}
?>
