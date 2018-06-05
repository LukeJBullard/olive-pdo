<?php
    /**
     * OliveWeb PDO Module
     * Config File
     * August 2014-June 2018
     * 
     * @author Luke Bullard
     * @version 1.1
     */
    
    //make sure we are included securely
    if (!defined("INPROCESS")) { header("HTTP/1.0 403 Forbidden"); exit(0); }
    
    /**
     * The configuration array for the PDO Module
     * Parameters:
     *      autoload => The Name of the connection to autoload when the module is loaded
     *      connection name => array(
     *          driver => The name of the driver the connection should use. Currently only mysql is available
     *          hostname => The hostname or IP of the MySQL server to connect to
     *          username => The username to authenticate with
     *          password => The password to authenticate with
     *          utf8 => Boolean, if the database is mysql and uses a UTF8 characterset. If the database uses UTF8 and this isn't set, weird things will happen
     *      )
     */
    $pdo_config = array(
        //the database connection to automatically load
        //when the module is loaded
        "autoload" => "example"
        
        //a mysql database connection
        ,"example" => array(
            "driver" => "mysql"
            ,"hostname" => "mysql.myserver.com"
            ,"username" => "myuser"
            ,"password" => "password"
            ,"database" => "mydatabase"
            ,"utf8" => false
        )
    );
?>