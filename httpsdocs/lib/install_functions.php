<?php

/**
 * backup functions used by install scripts
 */

namespace CB\INSTALL;

use CB;

/**
 * return default values for casebox configuration
 * @return [type] [description]
 */
function getDefaultConfigValues()
{
    return array(
       'prefix' => 'cb'

        ,'apache_user' => 'apache'

        ,'db_host' => 'localhost'
        ,'db_port' => '3306'

        ,'su_db_user' => 'root'
        // ,'su_db_pass' => '' // shouldn't be saved to config.ini

        ,'db_user' => 'local'
        ,'db_pass' => ''

        ,'server_name' => 'https://yourserver.com/'

        ,'solr_home' => '/var/solr/data/'
        ,'solr_host' => '127.0.0.1'
        ,'solr_port' => '8983'

        ,'session.lifetime' => '180'

        //;ADMIN_EMAIL: email adress used to notify admin on any casebox problems
        ,'admin_email' => 'your.email@server.com'
        //;SENDER_EMAIL: email adress placed in header for sent mails
        ,'sender_email' => 'emails.sender@server.com'

        ,'comments_email' => 'comments@subdomain.domain.com'
        ,'comments_host' => '127.0.0.1'
        ,'comments_port' => 993
        ,'comments_ssl' => true
        ,'comments_user' => ''
        ,'comments_pass' => ''

        ,'PYTHON' => 'python'
        ,'backup_dir' => \CB\APP_DIR . 'backup' . DIRECTORY_SEPARATOR
    );
}

/**
 * get question / phrase to be displayed for a given paramName
 */
function getParamPhrase($paramName)
{
    $phrases = array(
        'apache_user' => 'Specify apache user {default}:' . "\n"
        ,'prefix' => 'Specify prefix used for database names, solr core and log files {default}:' . "\n"
        ,'server_name' => 'Provide server name with protocol {default}:' . "\n"

        ,'db_host' => 'db host {default}: '
        ,'db_port' => 'db port {default}: '
        ,'su_db_user' => 'privileged db user {default}: '
        ,'su_db_pass' => 'privileged db user\'s password: '
        ,'db_user' => 'db user {default}: '
        ,'db_pass' => 'db password: '

        ,'admin_email' => 'Specify administrator email address {default}:' . "\n"
        ,'sender_email' => 'Specify sender email address, placed in header for sent mails {default}:' . "\n"

        ,'define_comments_email' => 'Would you like to define comments email parametters [Y/n]: '
        ,'comments_email' => 'Specify comments email address, used to receive replies for Casebox comment notifications {default}:' . "\n"
        ,'comments_host' => 'Specify comments email server host {default}:' . "\n"
        ,'comments_port' => 'Specify comments email server port {default}:' . "\n"
        ,'comments_ssl' => 'Specify if ssl connection is used for comments email server [Y/n]: '
        ,'comments_user' => 'Specify username for comments email server connection (can be left blank if email could be used as username):' . "\n"
        ,'comments_pass' => 'Specify password for comments email server connection:' . "\n"

        ,'PYTHON' => 'Specify python path {default}:' . "\n"

        ,'solr_home' => 'solr home directory {default}: '
        ,'solr_host' => 'solr host {default}: '
        ,'solr_port' => 'solr port {default}: '

        ,'backup_dir' => 'Specify backup directory {default}:' . "\n"

        // ,'? or overwrite, cause it asks only when doesnt exist
        ,'log_solr_overwrite' => 'Solr core {prefix}_log exists or can\'t access solr. Would you like to try to create it [Y/n]: '

        ,'overwrite__casebox_db' => "'{prefix}_casebox' database exists. Would you like to backup it and overwrite with dump from current installation [Y/n]: "

        ,'create__casebox_from_dump' => "{prefix}_casebox database does not exist. Would you like to create it from current installation dump file [Y/n]: "

        ,'create_basic_core' => "Do you want to create a basic default core [Y,n]: "
        ,'core_name' => "Core name:\n"

        //core_create specific params
        ,'core_overwrite_existing_db' => 'Database for given core name already exists. Would you like to overwrite it?'
        ,'core_root_email' => 'Specify email address for root user:' . "\n"
        ,'core_root_pass' => 'Specify root user password:' . "\n"
        ,'core_solr_overwrite' => 'Solr core already exists, overwrite [Y/n]: '
        ,'core_solr_reindex' => 'Reindex core [Y/n]: '
        ,'overwrite_existing_core_db' => "Core database exists. Would you like to backup it and overwrite with dump from current installation [Y/n]: "

    );

    return empty($phrases[$paramName])
        ? $paramName
        : $phrases[$paramName];
}

/**
 * display notices for specific operation system
 * @return [type] [description]
 */
function displaySystemNotices()
{
    $PATH = getenv('PATH');
    if (\CB\Util\getOS() == "WIN" && !( strpos($PATH,'mysql') || strpos($PATH,'MySQL') ) )  {
        echo "Notice: on Windows platform path to mysql/bin should be added to \"Path\" environment variable.\n\n";
    } else {

    }
}

/**
 * set ownership to apache user for following CB folders:
 *     logs, data, httpsdocs/cores
 * @param [type] &$cfg [description]
 */
function setOwnershipForApacheUser(&$cfg)
{
    if (\CB\Util\getOS() == "WIN") {
        return ;
    }

    $cmdSet = [
        'chown -R ' . $cfg['apache_user'].' "' . \CB\LOGS_DIR . '"',
        'chown -R ' . $cfg['apache_user'].' "' . \CB\DATA_DIR . '"',
        'chown -R ' . $cfg['apache_user'].' "' . \CB\DOC_ROOT . 'config.ini"'
    ];

    foreach($cmdSet as $shell_cmd) {
        shell_exec($shell_cmd);
    };

}

/**
 * init solr connection params
 * @return void
 */
function initSolrConfig(&$cfg)
{
    echo "\nSpecify solr configuration:\n";

    $retry = true;
    do {
        $cfg['solr_home'] = readParam('solr_home', $cfg['solr_home']);
        //add trailing slash
        if (!in_array(substr($cfg['solr_home'], -1), array('/', '\\'))) {
            $cfg['solr_home'] .= DIRECTORY_SEPARATOR;
        }

        $retry = false;

        if (!file_exists($cfg['solr_home'])) {
            if (\CB\Cache::get('RUN_SETUP_INTERACTIVE_MODE')) {
                $retry = confirm('Can\'t access specified path, would you like to check and enter it again [Y/n]:' . "\n");
            } else {
                trigger_error('Error accessing solr home directory "' . $cfg['solr_home'] .'".', E_USER_ERROR);
            }
        }

    } while ($retry);

    $cfg['solr_host'] = readParam('solr_host', $cfg['solr_host']);

    $cfg['solr_port'] = readParam('solr_port', $cfg['solr_port']);
}

/**
 * create symlynks in solr directory for casebox config sets
 * @param  array &$cfg
 * @return boolean
 */
function createSolrConfigsetsSymlinks(&$cfg)
{

   if(isset($cfg['prefix']))  {
       
    //creating solr symlinks
    $solrCSPath = $cfg['solr_home'] . 'configsets' . DIRECTORY_SEPARATOR;
    $CBCSPath = \CB\SYS_DIR . 'solr_configsets' . DIRECTORY_SEPARATOR;

    if (!file_exists($solrCSPath)) {
        mkdir($solrCSPath, 0777, true);
    }

    $r = true;

    $defaultLinkName = $solrCSPath . $cfg['prefix'].'_default';
    if (!file_exists($defaultLinkName)) {
        $r = symlink($CBCSPath . 'default_config' . DIRECTORY_SEPARATOR, $defaultLinkName );
    }

    $logLinkName = $solrCSPath . $cfg['prefix'].'_log';
    if ( !file_exists($logLinkName) ) {
        $r = $r && symlink($CBCSPath . 'log_config' . DIRECTORY_SEPARATOR, $logLinkName );
    }

    if (\CB\Util\getOS() == "LINUX") {
            shell_exec("chown -R ".fileowner($CBCSPath).":".filegroup($CBCSPath)." ".$CBCSPath);
        }

        // create dir for log core
    $logCore = $cfg['solr_home'] . $cfg['prefix'].'_log';

        if ( !file_exists($logCore) ) {
            mkdir($logCore, 0777, true);
           // symlink($CBCSPath . 'log_config' . DIRECTORY_SEPARATOR. 'conf', $logCore . DIRECTORY_SEPARATOR . 'conf' );
        }

     if (\CB\Util\getOS() == "LINUX") {
            // set owner of core folder for solr
            shell_exec("chown -R ".fileowner($cfg['solr_home']).":".filegroup($cfg['solr_home'])." ".$logCore);
        }
        
    }

    return [ 'success' => $r, 'links' => [ 'log' => $defaultLinkName, 'default' => $defaultLinkName ] ];
    
}

/**
 * method to create a solr core with additional checks
 * @param  array &$cfg
 * @return boolean
 */
function createSolrCore(&$cfg, $coreName, $paramPrefix = 'core_')
{

    //verify if solr core exist
    $solrHost     = $cfg['solr_host'];
    $solrPort     = $cfg['solr_port'];
    $createCore   = true;
    $askReindex   = true;
    $fullCoreName = $cfg['prefix'].'_'.$coreName;

    $solr = \CB\Solr\Service::verifyConfigConnection(
        array(
            'host' => $solrHost
            ,'port' => $solrPort
            ,'core' => $fullCoreName
            ,'SOLR_CLIENT' => $cfg['SOLR_CLIENT']
        )
    );

    if ($solr !== false) {
        if (confirm($paramPrefix.'solr_overwrite', 'n')) {
            echo 'Unloading core '.$coreName.'... ';
            unset($solr);
            if (solrUnloadCore($solrHost, $solrPort, $fullCoreName)) {
                display_OK();
            } else {
                displayError("Error unloading core.\n");
                $createCore = false;
            }
        } else {
            $createCore = false;
        }
    }



    if ($createCore) {
        echo 'Creating solr core ... ';

        if (solrCreateCore($solrHost, $solrPort, $fullCoreName, $cfg)) {
            display_OK();
        } else {
            displayError("Error creating core.\n");
            $askReindex = false;
        }
    }

    if ($askReindex && ($paramPrefix !== 'log_')) {
        if (confirm($paramPrefix.'solr_reindex', 'n')) {
            echo 'Reindexing core ... ';

            $cmd_reindex_core = 'php '.\CB\BIN_DIR.'solr_reindex_core.php -c '.$coreName.' -a -l';
            $reindex_result = shell_exec($cmd_reindex_core);
            // here need to verify result of execution solr_reindex_core.php
            display_OK();
        }
    }
}

/**
 * unload a solr core
 * @return boolean
 */
function solrUnloadCore($host, $port, $coreName)
{
    $rez = true;

    $url = 'http://' . $host. ':' . $port . '/solr/admin/cores?action=UNLOAD&' .
        'core=' . $coreName . '&deleteInstanceDir=true'; //

    if ($h = fopen($url, 'r')) {
        fclose($h);
    } else {
        $rez = false;
    }

    return $rez;
}

/**
 * create a solr core
 * @return boolean
 */
function solrCreateCore($host, $port, $coreName, $cfg = array() )
{
    $rez = true;

    if (isset($cfg['solr_home'])) {
        
        $CB_CORE_SOLR_PATH = $cfg['solr_home'].$coreName;

        // check if path on solr data exists
        if (!file_exists($CB_CORE_SOLR_PATH)) {
            // create
            mkdir($CB_CORE_SOLR_PATH, 0777, true);
        }

        // make link to config
       /* $confLink = $CB_CORE_SOLR_PATH.DIRECTORY_SEPARATOR.'conf';

        $CBCSPath = \CB\SYS_DIR . 'solr_configsets' . DIRECTORY_SEPARATOR;

        $confPath = $CBCSPath.'default_config'.DIRECTORY_SEPARATOR.'conf';
        
        if (!file_exists($confLink) && file_exists($confPath)) {
            $r = symlink($confPath, $confLink);
        } elseif (!file_exists($confPath)) {
            trigger_error($confPath, E_USER_WARNING);
        } */


   if (\CB\Util\getOS() == "LINUX") {
            // set owner of core folder for solr same as parent
            shell_exec("chown -R ".fileowner($cfg['solr_home']).":".filegroup($cfg['solr_home'])." ".$CB_CORE_SOLR_PATH);
        }

    }

    $instance_create_url = 'http://' . $host. ':' . $port . '/solr/admin/cores?action=CREATE&' .
        'name=' . $coreName . '&instanceDir='.$coreName.'&configSet='.$cfg['prefix'].'_default';
    if ($h = fopen($instance_create_url, 'r')) {
        fclose($h);
    } else {
        $rez = false;
    }

    return $rez;
}

/**
 * verify specified database params
 * @return boolean
 */
function verifyDBConfig(&$cfg)
{

    // simply try to acces with superuser, 
    //  so if we have super user then can create regulary user with grand access

    $success = true;
      try {
            $dbh = new \mysqli(
                $cfg['db_host'],
                $cfg['su_db_user'],
                (isset($cfg['su_db_pass']) ? $cfg['su_db_pass']:null),
                (isset($cfg['db_name']) ? $cfg['db_name']:null),
                $cfg['db_port']
            );
        } catch (\Exception $e) {
            $success = false;
        }

    return $success;
    
}

/**
 * short function to connect to DB with privileged user
 * @param  array $cfg
 * @return db handler | null
 */
function connectDBWithSuUser($cfg)
{

    @$newParams = array(
        'db_host' => $cfg['db_host'],
        'db_user' => $cfg['su_db_user'],
        'db_pass' => $cfg['su_db_pass'],
        'db_name' => $cfg['db_name'],
        'db_port' => $cfg['db_port'],
        'initsql' => $cfg['initsql']
    );

    return @\CB\DB\connectWithParams($newParams);
}

/**
 * init database connection params
 * @return void
 */
function initDBConfig(&$cfg)
{
    echo 'Specify database configuration:' . "\n";

    //init database configuration
    $cfg['db_host'] = readParam('db_host', $cfg['db_host']);
    $cfg['db_port'] = readParam('db_port', $cfg['db_port']);

    $cfg['db_user'] = readParam('db_user', $cfg['db_user']);
    $cfg['db_pass'] = readParam('db_pass');

    $cfg['su_db_user'] = readParam('su_db_user', $cfg['su_db_user']);
    $cfg['su_db_pass'] = readParam('su_db_pass');

}

/**
 * create default database (<prefix>__casebox)
 * @param  array $cfg
 * @return boolean
 */
function createMainDatabase($cfg)
{

   $rez = true;

    connectDBWithSuUser($cfg);

    $cbDb = $cfg['prefix'] . '__casebox';

    $DB_USER = isset($cfg['su_db_user']) ? $cfg['su_db_user']: $cfg['db_user'];
    $DB_PASS = isset($cfg['su_db_pass']) ? $cfg['su_db_pass']: $cfg['db_pass'];

    $r = \CB\DB\dbQuery('use `' . $cbDb . '`');
    if ($r) {
        if (confirm('overwrite__casebox_db')) {
            if (!(\CB\Cache::get('RUN_SETUP_CREATE_BACKUPS') == false)) {
                echo 'Backuping .. ';
               if(backupDB($cbDb, $DB_USER, $DB_PASS)) {
                   display_OK();
               } else {
                   display_ERROR('FALSE');
               }

            }

            

            echo 'Applying dump .. ';
            echo shell_exec('mysql --user=' . $DB_USER . ( $DB_PASS ? ' --password=' . $DB_PASS : '' ) . ' ' . $cbDb . ' < ' . \CB\APP_DIR . 'install/mysql/_casebox.sql');
            display_OK();
        }
    } else {
        if (confirm('create__casebox_from_dump')) {
            if (\CB\DB\dbQuery('CREATE DATABASE IF NOT EXISTS `' . $cbDb . '` CHARACTER SET utf8 COLLATE utf8_general_ci')) {
                echo shell_exec('mysql --user=' . $DB_USER . ( $DB_PASS ? ' --password=' . $DB_PASS : '' )  . ' ' . $cbDb . ' < ' . \CB\APP_DIR . 'install/mysql/_casebox.sql');
            } else {
                $rez = false;
                display_ERROR('Cant create database "' . $cbDb . '".');
            }
        }
    }

   // GrandDBAccess($cfg);
    
    return $rez;
}

/**
 * create db_user if not- exists and grand all privileges to access
 * @param type $cfg
 */
/*function GrandDBAccess($cfg) {
       

    // first check if user exists
     $SQL_CHECK_USER = "SELECT EXISTS(SELECT 1 FROM mysql.user WHERE USER = '".$cfg['db_user']."')";

     $db_user_rez = \CB\DB\dbQuery($SQL_CHECK_USER);
     $db_user = $db_user_rez->fetch_array();
    if( $db_user[0] != 1 )  {

        $SQL_CREATE_USER = "CREATE USER `".$cfg['db_user']."`@`".$cfg['db_host']."` IDENTIFIED BY '".$cfg['db_pass']."'";
        \CB\DB\dbQuery($SQL_CREATE_USER);

    }
      // GRANT ALL PRIVILEGES ON `xian\_%`.* TO xian@'192.168.1.%';
        $SQL_GRAND_ACCESS = "GRANT ALL PRIVILEGES ON `".$cfg['prefix']."\_%`.* TO `".$cfg['db_user']."`@`".$cfg['db_host']."` WITH GRANT OPTION;";
      // $SQL_GRAND_ACCESS = "GRANT ALL PRIVILEGES ON *.* TO ".$cfg['db_user']."@".$cfg['db_host']." WITH GRANT OPTION;";

      \CB\DB\dbQuery($SQL_GRAND_ACCESS);

      $SQL_FLUSH = "FLUSH PRIVILEGES;";

      \CB\DB\dbQuery($SQL_FLUSH);
} */

/**
 * read a line from stdin
 * @return varchar
 */
function readALine($message)
{
    $rez = '';
    if (PHP_OS == 'WINNT') {
        echo $message;
        $rez = stream_get_line(STDIN, 1024, PHP_EOL);
    } else {
        $rez = readline($message);
    }

    return trim($rez);
}

/**
 * get a paramValue
 * @param  varchar $paramName
 * @param  varchar $defaultValue
 * @return varchar
 */
function readParam($paramName, $defaultValue = null)
{
    $rez = $defaultValue;

    if (\CB\Cache::get('RUN_SETUP_INTERACTIVE_MODE')) {
        $question = str_replace(
            '{default}',
            '(default "' . $defaultValue. '")',
            getParamPhrase($paramName)
        );

        if(defined('CB\\PREFIX')) {
             $question = str_replace('{prefix}', constant('CB\\PREFIX'), $question);
        }
        
        $l = readAline($question);

        /* define prefix not defined yet */
        if ($paramName == 'prefix' && !defined('CB\\PREFIX')) {
            define('CB\\PREFIX', $l);
        }

        if (!empty($l)) {
            $rez = $l;
        }

    } else {
        $cfg = \CB\Cache::get('RUN_SETUP_CFG');
        if (!empty($cfg[$paramName])) {
            $rez = $cfg[$paramName];
        }
    }

    return trim($rez);
}

/**
 * confirm description
 * @param  varchar $message
 * @return boolean
 */
function confirm($paramName)
{
    $l = '';
    do {
        $l = readParam($paramName, 'y');
        $l = strtolower($l);
    } while (!in_array($l, array('', 'y', 'n')));

    return (($l == 'y') || ($l == ''));
}

/**
 * save ini file
 * @param  varchar  $file
 * @param  array  $array
 * @param  integer $i
 * @return variant
 */
function putIniFile ($file, $array, $i = 0)
{
    $str = "";
    foreach ($array as $k => $v) {
        if (is_array($v)) {
            $str .= str_repeat(" ", $i*2) . "[$k]" . PHP_EOL;
            $str .= putIniFile("", $v, $i+1);
        } else {
            $str .= str_repeat(" ", $i*2) . "$k = $v" . PHP_EOL;
        }
    }

    if ($file) {
        return file_put_contents($file, $str);
    } else {
        return $str;
    }
}

/**
 * define backup_dir constant and create folder if doesnt exist
 * @param  array &$cfg
 * @return varchar
 */
function defineBackupDir(&$cfg)
{
    if (\CB\Cache::exist('RUN_INSTALL_BACKUP_DIR')) {
        return \CB\Cache::get('RUN_INSTALL_BACKUP_DIR');
    }

    $dir = empty($cfg['backup_dir'])
        ? \CB\APP_DIR . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR
        : $cfg['backup_dir'];

    \CB\Cache::set('RUN_INSTALL_BACKUP_DIR', $dir);

    if (!file_exists($dir)) {
        mkdir($dir, 0766, true);
    }

    return $dir;
}

/**
 * backup given file to sys/backup folder
 * @param  varchar $fileName
 * @return boolean
 */
function backupFile($fileName)
{

    if (!file_exists($fileName)) {
        return false;
    }

    return rename($fileName, \CB\Cache::get('RUN_INSTALL_BACKUP_DIR'). date('Ymd_His_') . basename($fileName));
}

/**
 * backup given database to sys/backup folder
 * @param  varchar $dbName
 * @return boolean
 */
function backupDB($dbName, $dbUser, $dbPass)
{
    $fileName = \CB\Cache::get('RUN_INSTALL_BACKUP_DIR') . date('Ymd_His_') . $dbName . '.sql';

    shell_exec('mysqldump --routines --user=' . $dbUser . ' --password=' . $dbPass . ' ' . $dbName . ' > ' . $fileName);

    return true;
}

/**
 * function to display errors in interactive mode or to raise them
 * @param  varchar $error
 * @return void
 */
function displayError($error)
{
    if (\CB\Cache::exist('RUN_SETUP_INTERACTIVE_MODE')) {
        if (\CB\Cache::get('RUN_SETUP_INTERACTIVE_MODE')) {
            display_ERROR($error);
            return;
        }
    }

    trigger_error($error, E_USER_ERROR);
}


function display_OK($msg = "OK") {
    if(\CB\Util\getOS() == "LINUX") {
      echo "\033[32m".$msg."\033[0m".PHP_EOL;
    } else {
      echo $msg.PHP_EOL;
    }

    
}

function display_ERROR($msg = "ERROR") {
    if(\CB\Util\getOS() == "LINUX") {
      echo "\033[31m".$msg."\033[0m".PHP_EOL;
    } else {
      echo $msg.PHP_EOL;
    }


}