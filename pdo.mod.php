<?php
    /**
     * OliveWeb PDO Module
     * August 2014-September 2018
     * 
     * @author Luke Bullard
     * @version 1.1
     */
    
    //make sure we are included securely
    if (!defined("INPROCESS")) { header("HTTP/1.0 403 Forbidden"); exit(0); }
    
    //PDO defined error codes
    define("ERR_PDO_EXISTS", -1); //connection already exists
    define("ERR_PDO_DRIVER", -2); //driver not found for connection
    define("ERR_PDO_CREDENTIALS", -3); //database credentials invalid or nonexistant
    define("ERR_PDO_CONNECTION", -4); //connection to the database failed
    define("ERR_PDO_MISSING", -5); //connection (or connection data) nonexistant
    define("ERR_PDO_PREPARE", -6); //preparing a statement failed
    define("ERR_PDO_EXECUTE", -7); //executing a statement failed
    
    /**
     * The OliveWeb module class for PDO
     */
    class MOD_pdo
    {
        //PDO connections array
        private $m_connections;
        
        //current PDO connection
        private $m_currentConnection;

        //current read only PDO connection
        private $m_currentReadOnlyConnection;
        
        //constructor
        public function __construct()
        {
            //get the config file located in the same directory as this script
            require(dirname(__FILE__) . "/config.php");
            
            //make sure the configuration array exists
            if (!(isset($pdo_config) && is_array($pdo_config)))
            {
                return;
            }
            
            //variable to track the status of autoloading
            $t_autoload = false;
            
            //preload all the config file's database credentials,
            //without connecting them
            foreach ($pdo_config as $t_key => $t_value)
            {
                //if setting up autoloading, confirm the value is a string
                //then set the autoload tracking variable to the value
                if ($t_key == "autoload" && is_string($t_value))
                {
                    $t_autoload = $t_value;
                    continue;
                }
                
                //if there isn't a connection with the same key already,
                //confirm the value is an array then set one up
                if (isset($this->m_connections[$t_key]) == false && is_array($t_value))
                {
                    $this->m_connections[$t_key] = $t_value;
                    $this->m_connections[$t_key]["loaded"] = false;
                }
            }
            
            //if the autoload value isn't set, return. otherwise, choose it
            if ($t_autoload == false)
            {
                return;
            }
            $this->chooseConnection($t_autoload);
        }

        private function loadMySQLConnection($a_hostname, $a_username, $a_password, $a_database, $a_utf8=false)
        {
            //build a MySQL DSN (Data Source Name) for the connection
            $t_dsn = "mysql:host=" . $a_hostname . ";dbname=" . $a_database;
                    
            //try to connect to the database
            try
            {
                if ($a_utf8)
                {
                    $t_dsn .= ";charset=utf8";
                    return new PDO($t_dsn, $a_username, $a_password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
                } else {
                    return new PDO($t_dsn, $a_username, $a_password);
                }
            } catch (PDOException $e_databaseConnection)
            {
                return ERR_PDO_CONNECTION;
            }
        }
        
        /**
         * Loads a connection
         * 
         * @param String $a_key The name of the connection to load.
         * @return Integer|Boolean Boolean true if the connection was successfully loaded, or the Integer error code if loading failed.
         */
        private function load($a_key)
        {
            //if connection doesn't exist, return error code
            if (!isset($this->m_connections[$a_key]))
            {
                return ERR_PDO_MISSING;
            }
            
            //if database connection already exists, return error code
            if (isset($this->m_connections[$a_key]["connection"]))
            {
                return ERR_PDO_EXISTS;
            }
            
            //if driver isn't set, return error code
            if (!isset($this->m_connections[$a_key]["driver"]))
            {
                return ERR_PDO_DRIVER; 
            }
            
            //setup the connection based on it's driver
            switch ($this->m_connections[$a_key]["driver"])
            {
                //MySQL driver
                case "mysql":
                    //if the connection doesn't have all the necessary fields, return error code.
                    if (!isset($this->m_connections[$a_key]["username"]
                              ,$this->m_connections[$a_key]["password"]
                              ,$this->m_connections[$a_key]["hostname"]
                              ,$this->m_connections[$a_key]["database"])
                        )
                    {
                        return ERR_PDO_CREDENTIALS;
                    }
                    
                    //make temporary variables for the fields for easy access
                    $t_username = $this->m_connections[$a_key]["username"];
                    $t_password = $this->m_connections[$a_key]["password"];
                    $t_hostname = $this->m_connections[$a_key]["hostname"];
                    $t_database = $this->m_connections[$a_key]["database"];
                    
                    $t_utf8 = false;

                    if (isset($this->m_connections[$a_key]["utf8"]))
                    {
                        $t_utf8 = $this->m_connections[$a_key]["utf8"];
                    }

                    $t_connection = $this->loadMySQLConnection($t_hostname, $t_username, $t_password, $t_database, $t_utf8);
                    if ($t_connection === ERR_PDO_CONNECTION)
                    {
                        return ERR_PDO_CONNECTION;
                    }

                    $this->m_connections[$a_key]["connection"] = $t_connection;

                    $this->m_connections[$a_key]["connection"]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    //load read only connection
                    if (isset($this->m_connections[$a_key]["read_only"]))
                    {
                        //if the connection doesn't have all the necessary fields, return error code.
                        if (!isset($this->m_connections[$a_key]["read_only"]["username"]
                                    ,$this->m_connections[$a_key]["read_only"]["password"]
                                    ,$this->m_connections[$a_key]["read_only"]["hostname"]
                                    ,$this->m_connections[$a_key]["read_only"]["database"])
                            )
                        {
                            return ERR_PDO_CREDENTIALS;
                        }
                        
                        //make temporary variables for the fields for easy access
                        $t_username = $this->m_connections[$a_key]["read_only"]["username"];
                        $t_password = $this->m_connections[$a_key]["read_only"]["password"];
                        $t_hostname = $this->m_connections[$a_key]["read_only"]["hostname"];
                        $t_database = $this->m_connections[$a_key]["read_only"]["database"];
                        
                        $t_utf8 = false;

                        if (isset($this->m_connections[$a_key]["read_only"]["utf8"]))
                        {
                            $t_utf8 = $this->m_connections[$a_key]["read_only"]["utf8"];
                        }

                        $t_connection = $this->loadMySQLConnection($t_hostname, $t_username, $t_password, $t_database, $t_utf8);
                        if ($t_connection === ERR_PDO_CONNECTION)
                        {
                            return ERR_PDO_CONNECTION;
                        }

                        $this->m_connections[$a_key]["connection_read_only"] = $t_connection;

                        $this->m_connections[$a_key]["connection_read_only"]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    }

                    break;
                default:
                    //driver not found. return error code
                    return ERR_PDO_DRIVER;
            }
            
            //set the connection's loaded state to true
            $this->m_connections[$a_key]["loaded"] = true;
            
            //success, return true
            return true;
        }
        
        /**
         * Select a connection to perform queries against. If the connection isn't loaded yet, chooseConnection will attempt to load it.
         * 
         * @param String $a_key The name of the connection to choose.
         * @return Boolean|Integer Boolean true if the connection was successfully chosen, or the Integer error code (see Load) if there was an error.
         */
        public function chooseConnection($a_key)
        {
            //if the connection doesn't exist, return error code
            if (!isset($this->m_connections[$a_key]))
            {
                return ERR_PDO_MISSING;
            }
            
            //if the connection isn't loaded, load it
            if (!$this->m_connections[$a_key]["loaded"])
            {
                $t_loadedValue = $this->load($a_key);
                
                //if the load() method returned an error code, pass it along; return it
                if ($t_loadedValue !== true)
                {
                    return $t_loadedValue;
                }
            }
            
            //if the connection still isn't loaded, return error code
            if ($this->m_connections[$a_key]["loaded"] == false)
            {
                return ERR_PDO_MISSING;
            }
            
            //set the current connection to the new, chosen one's connection
            $this->m_currentConnection = $this->m_connections[$a_key]["connection"];
            $this->m_currentReadOnlyConnection = $this->m_connections[$a_key]["connection"];
            
            //if the read only connection is setup, use that for selects
            if (isset($this->m_connections[$a_key]["connection_read_only"]))
            {
                $this->m_currentReadOnlyConnection = $this->m_connections[$a_key]["connection_read_only"];
            }
            
            //success, return true
            return true;
        }
        
        /**
         * Roll back the transaction in progress.
         * 
         * @return Boolean|Integer Boolean true or false if the roll back was successful, or Integer ERR_PDO_MISSING if no connection is chosen.
         */
        public function rollback()
        {
            //if there is no current connection, return error code
            if (!isset($this->m_currentConnection))
            {
                return ERR_PDO_MISSING;
            }
            
            //return the boolean value from rollBack(),
            //with true signifying success...
            return $this->m_currentConnection->rollBack();
        }
        
        /**
         * Begin a transaction.
         * 
         * @return Boolean|Integer Boolean true or false if the transaction has begun, or Integer ERR_PDO_MISSING if no connection is chosen.
         */
        public function beginTransaction()
        {
            //if there is no current connection, return error code
            if (!isset($this->m_currentConnection))
            {
                return ERR_PDO_MISSING;
            }
            
            //return the boolean value from beginTransaction(),
            //with true signifying success...
            return $this->m_currentConnection->beginTransaction();
        }
        
        /**
         * Commits a transaction to the database.
         * 
         * @return Boolean|Integer Boolean true or false if the transaction was committed, or Integer ERR_PDO_MISSING if no connection is chosen.
         */
        public function commit()
        {
            //if there is no current connection, return error code
            if (!isset($this->m_currentConnection))
            {
                return ERR_PDO_MISSING;
            }
            
            //return the boolean value from commit(),
            //with true signifying success...
            return $this->m_currentConnection->commit();
        }
        
        /**
         * Retrieves data from the database.
         * 
         * @param String $a_query The SQL query to run. To put in placeholders, use the ? symbol.
         * @param Mixed $a_argument1 The data to fill the first placeholder, or omit this argument if there is no such placeholder.
         * @param Mixed $a_argument2 The data to fill the second placeholder, or omit this argument if there is no such placeholder.
         * @param Mixed $a_argumentN The data to fill the Nth placeholder, or omit this argument if there is no such placeholder.
         * 
         * @return Array[][]|Integer A two dimensional array representing the returned results. For example, to retrieve column 'first_name' from
         *                  the first column returned, use [0]['first_name']. If there was an error, an Integer error code is returned.
         */
        public function select()
        {
            //if there is no current connection, return error code
            if (!isset($this->m_currentReadOnlyConnection))
            {
                return ERR_PDO_MISSING;
            }
            
            //get all the arguments passed to this function
            $t_args = func_get_args();
            
            //shift the actual SQL query string off the arguments array
            $t_sql = array_shift($t_args);
            
            //prepare a statement with this SQL query string
            $t_statement = $this->m_currentReadOnlyConnection->prepare($t_sql);
            
            //if the preparing failed, return error code
            if ($t_statement === false)
            {
                return ERR_PDO_PREPARE;
            }
            
            //variable for the arguments that are to be passed to execute()
            $t_executeArguments = null;
            
            //if there was at least one argument besides the query string passed,
            //set our execute arguments to the remaining (ie. excluding query string)
            //arguments
            if (isset($t_args[0]))
            {
                $t_executeArguments = $t_args;
            }
            
            //execute
            if (!$t_statement->execute($t_executeArguments))
            {
                //an error occured, return the appropriate error code
                return ERR_PDO_EXECUTE;
            }
            $t_return = $t_statement->fetchAll(PDO::FETCH_ASSOC);
            $t_statement->closeCursor();
            return $t_return;
        }
        
        /**
         * Executes a query against the database.
         * 
         * @param String $a_query The SQL query to run. To put in placeholders, use the ? symbol.
         * @param Mixed $a_argument1 The data to fill the first placeholder, or omit this argument if there is no such placeholder.
         * @param Mixed $a_argument2 The data to fill the second placeholder, or omit this argument if there is no such placeholder.
         * @param Mixed $a_argumentN The data to fill the Nth placeholder, or omit this argument if there is no such placeholder.
         * 
         * @return Integer The last inserted ID into the database, or an Integer Error code if the execution failed.
         */
        public function execute()
        {
            //if there is no current connection, return error code
            if (!isset($this->m_currentConnection))
            {
                return ERR_PDO_MISSING;
            }
            
            //get all the arguments passed to this function
            $t_args = func_get_args();
            
            //shift the actual SQL query string off the arguments array
            $t_sql = array_shift($t_args);
            
            //prepare a statement with this SQL query string
            $t_statement = $this->m_currentConnection->prepare($t_sql);
            
            //if the preparing failed, return error code
            if ($t_statement === false)
            {
                return ERR_PDO_PREPARE;
            }
            
            //variable for the arguments that are to be passed to execute()
            $t_executeArguments = null;
            
            //if there was at least one argument besides the query string passed,
            //set our execute arguments to the remaining (ie. excluding query string)
            //arguments
            if (isset($t_args[0]))
            {
                $t_executeArguments = $t_args;
            }
            
            //execute
            if (!$t_statement->execute($t_executeArguments))
            {
                //an error occured, return the appropriate error code
                return ERR_PDO_EXECUTE;
            }
            
            //get the last insert ID, which will be returned
            $t_return = $this->m_currentConnection->lastInsertId();
            $t_statement->closeCursor();
            return $t_return;
        }
    }
?>