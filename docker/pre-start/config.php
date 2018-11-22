<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MDL_DBHOST');
$CFG->dbname    = getenv('MDL_DBNAME');
$CFG->dbuser    = getenv('MDL_DBUSER');
$CFG->dbpass    = getenv('MDL_DBPASS');
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => getenv('MDL_DBPORT'),
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_general_ci',
);

$CFG->wwwroot   = getenv('MDL_WWWROOT');
$CFG->dataroot  = '/opt/app-root/moodledata';
$CFG->admin     = 'admin';
$CFG->sslproxy = true;
$CFG->directorypermissions = 02777;

//   Redis session handler (requires redis server and redis extension):
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis-dev-001.moodle-cloud-dev-001.svc';
$CFG->session_redis_port = 6379;  // Optional.
$CFG->session_redis_database = 0;  // Optional, default is db 0.
$CFG->session_redis_auth = 'redis'; // Optional, default is don't set one.
$CFG->session_redis_prefix = ''; // Optional, default is don't set one.
$CFG->session_redis_acquire_lock_timeout = 120;
$CFG->session_redis_lock_expire = 7200;

$CFG->tool_generator_users_password = 'examplepassword';

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
