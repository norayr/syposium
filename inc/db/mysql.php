<?php
/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
   license. */

require_once ("./inc/db/anydb.php");

class mysqlConnection implements anydbConnection {
    var $link = null;
    var $dbprefix = null;

    public function connect ($host, $user, $pwd, $dbname, $dbprefix) {
        if (!function_exists ("mysql_connect")) {
            throw new Exception (anydbConnection::err_driver_unavailable);
        }
        if ($this->link) { // connection has already been opened
            return;
        }
        $this->link = @mysql_connect ($host,$user,$pwd,true);
        if (!$this->link) {
            throw new Exception (anydbConnection::err_connection);
        }
        if (!mysql_select_db ($dbname, $this->link)) {
            throw new Exception (anydbConnection::err_unknown_database);
        }
        $this->dbprefix = $dbprefix;
    }

    public function users_table_exists () {
        return $this->_tblexists ("users");
    }
    public function create_users_table () {
        $query = sprintf ("CREATE TABLE " .
                           "%susers (
                            name VARCHAR(255) NOT NULL, pwd CHAR(32),
                            PRIMARY KEY (name));", $this->dbprefix);
        $this->_execute_query ($query);
    }

    public function items_table_exists () {
        return $this->_tblexists ("items");
    }
    public function create_items_table () {
        $query = sprintf ("CREATE TABLE " .
                            "%sitems (
                                id MEDIUMINT NOT NULL AUTO_INCREMENT,
                                location POINT,
                                title VARCHAR(127),
                                description TEXT,
                                imgpath VARCHAR(255),
                                date DATETIME,
                                user VARCHAR(255),
                                PRIMARY KEY (id)
                            );", $this->dbprefix);
        $this->_execute_query ($query);
    }

    public function user_exists ($user_name) {
        $usrname_escaped = mysql_real_escape_string ($user_name);
        $query = sprintf ("SELECT COUNT(*) FROM %susers WHERE name LIKE '%s';",
                        $this->dbprefix, $usrname_escaped);
        $res = mysql_fetch_array ($this->_execute_query ($query), MYSQL_NUM);
        return ($res [0] == 1);
    }

    public function setpwd ($user_name, $pwd) {
        if (strlen ($pwd) == 0) {
            throw new Exception (anydbConnection::err_query);
        }
        $usrname_escaped = mysql_real_escape_string ($user_name);
        if ($this->user_exists ($user_name)) {
            $query = sprintf ("UPDATE %susers SET pwd='%s' WHERE name like '%s';", 
                    $this->dbprefix, md5 ($pwd), $usrname_escaped);
        } else {
            $query = sprintf ("INSERT INTO %susers VALUES ('%s', '%s');", 
                               $this->dbprefix, $usrname_escaped, md5 ($pwd));
        }
        $this->_execute_query ($query);
    }

    public function checkpwdmd5 ($user_name, $pwd_md5) {
        $query = sprintf ("SELECT COUNT(*) FROM %susers WHERE name LIKE '%s'
                           AND pwd LIKE '%s';",
                           $this->dbprefix, 
                           mysql_real_escape_string ($user_name),
                           mysql_real_escape_string ($pwd_md5));
        $res = mysql_fetch_array ($this->_execute_query ($query), MYSQL_NUM);
        if ($res [0] >= 1) {
            return true;
        } else {
            return false;
        }
    }

    public function save_feature ($feature) {
        try {
            $id = $feature->id;
        } catch (Exception $e) {}
        if (isset ($id)) {
            $query = sprintf ("UPDATE %sitems SET
                                    imgpath='%s', 
                                    title='%s', 
                                    description='%s', 
                                    location=GeomFromText('POINT(%s %s)')
                            WHERE id = '%s';",
                            $this->dbprefix,
                            mysql_real_escape_string ($feature->imgpath),
                            mysql_real_escape_string ($feature->title),
                            mysql_real_escape_string ($feature->description),
                            $feature->lon,
                            $feature->lat,
                            $id);
                $this->_execute_query ($query);
                return $feature;
        } else {
              $query = sprintf ("INSERT INTO %sitems
                              (imgpath, title, description, location, date, user)
                                VALUES ('%s', '%s', '%s', 
                               GeomFromText('POINT(%s %s)'), NOW(), '%s')", 
                              $this->dbprefix,
                              mysql_real_escape_string ($feature->imgpath),
                              mysql_real_escape_string ($feature->title),
                              mysql_real_escape_string ($feature->description),
                              $feature->lon,
                              $feature->lat,
                              mysql_real_escape_string ($feature->user)
                    );

                $this->_execute_query ($query);
                $id = mysql_insert_id ();
                return new feature ($id, $feature->lon, $feature->lat,
                                    $feature->imgpath, $feature->title,
                                    $feature->description, $feature->date,
                                    $feature->user);
        }
    }

    public function delete_feature ($feature) {
        $query = sprintf ("DELETE from %sitems WHERE id = '%s'",
                        $this->dbprefix,
                        mysql_real_escape_string ($feature->id));
        $this->_execute_query ($query);
        return true;
    }

    public function getfeature ($id) {
        $query = sprintf ("SELECT id, imgpath, title, description, AsText(location)
                           AS location, UNIX_TIMESTAMP(date) AS date, user
                           FROM %sitems WHERE id = '%s';", 
                        $this->dbprefix, mysql_real_escape_string ($id));
        $row = mysql_fetch_assoc ($this->_execute_query ($query));
        if ($row === false) {
            return null;
        }
        return $this->_feature_frow_row ($row);
    }

    public function listfeatures ($user) {
        if ($user && ($user != "admin")) {
            $from_user_query = sprintf (" WHERE user = '%s' ",
                                        mysql_real_escape_string ($user));
        } else {
            $from_user_query = "";
        }

        $query = sprintf ("SELECT id, imgpath, title, description, AsText(location)
                            AS location, UNIX_TIMESTAMP(date) AS date, user
                            FROM %sitems %s;",
                          $this->dbprefix, $from_user_query);

        $features = array ();
        $res = $this->_execute_query ($query);
        while ($row = mysql_fetch_assoc ($res)) {
            $feature = $this->_feature_frow_row ($row);
            if (isset ($feature)) {
                $features[] = $feature;
            }
        }
        return $features;
    }

    public function mostrecentfeatures ($num_features) {
        $query = sprintf ("SELECT id, imgpath, title, description,
                           AsText(location) AS location, UNIX_TIMESTAMP(date)
                           AS date, user FROM %sitems ORDER BY date DESC",
                           $this->dbprefix);
        if ($num_features) {
            $query .= sprintf (" LIMIT %d", $num_features);
        }
        $features = array ();
        $res = $this->_execute_query ($query);
        while ($row = mysql_fetch_assoc ($res)) {
            $feature = $this->_feature_frow_row ($row);
            if (isset ($feature)) {
                $features[] = $feature;
            }
        }
        return $features;
    }

    public function imgpath_exists ($imgpath) {
        $query = sprintf ("SELECT COUNT(*) FROM %sitems WHERE imgpath LIKE '%s';",
                           $this->dbprefix, mysql_real_escape_string ($imgpath));
        $res = mysql_fetch_array  ($this->_execute_query ($query), MYSQL_NUM);
        return ($res [0] >= 1) ? true : false;
    }

    public function getdbname () {
        return "Mysql";
    }

    private function _tblexists ($tblname) {
        $query = sprintf ("SHOW TABLES LIKE '%s%s';",
                            $this->dbprefix, $tblname);
        return mysql_num_rows ($this->_execute_query ($query)) == 1;
    }

    private function _feature_frow_row ($row) {
        // XXX: should I remove invalid features from database ?
        if (!preg_match ('/^POINT\(([0-9\-\.]+)\s+([0-9\-\.]+)\)$/',
                        $row ["location"], $matches)) {
            return null;
        }
        $lon = $matches [1];
        $lat = $matches [2];
        try {
            $feature = new feature ($row ["id"], $lon, $lat, $row ["imgpath"],
                                    $row ["title"], $row ["description"],
                                    $row ["date"], $row ["user"]);
        } catch (Exception $e) {
            return null;
        }
        return $feature;
    }

    private function _execute_query ($query) {
        if (!function_exists ("mysql_query")) {
            throw new Exception (anydbConnection::err_driver_unavailable);
        }
        if (!$this->link) {
            throw new Exception (anydbConnection::err_query);
        }
        $res = mysql_query ($query, $this->link);
        if ($res == false) {
            throw new Exception (anydbConnection::err_query);
        }
        return $res;
    }
}

$connection = new mysqlConnection();
?>
