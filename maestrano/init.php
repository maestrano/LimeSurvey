<?php

//-----------------------------------------------
// Define root folder and load base
//-----------------------------------------------
if (!defined('MAESTRANO_ROOT')) { define("MAESTRANO_ROOT", realpath(dirname(__FILE__))); }
if (!defined('ROOT_PATH')) { define('ROOT_PATH', realpath(MAESTRANO_ROOT . '/../')); }
chdir(ROOT_PATH);

//-----------------------------------------------
// Load Maestrano library
//-----------------------------------------------
require_once ROOT_PATH . '/vendor/maestrano/maestrano-php/lib/Maestrano.php';
Maestrano::configure(ROOT_PATH . '/maestrano.json');

//-----------------------------------------------
// Require your app specific files here
//-----------------------------------------------
if (!defined('APPPATH')) {
  chdir(ROOT_PATH);

  $system_path = ROOT_PATH . "/framework";
  $application_folder = ROOT_PATH . "/application";
  /*
   * ---------------------------------------------------------------
   *  Resolve the system path for increased reliability
   * ---------------------------------------------------------------
   */
      if (realpath($system_path) !== FALSE)
      {
        $system_path = realpath($system_path).'/';
      }

      // ensure there's a trailing slash
      $system_path = rtrim($system_path, '/').'/';

      // Is the system path correct?
      if (!is_dir($system_path))
      {
        exit("Your system folder path does not appear to be set correctly. Please open the following file and correct this: ".pathinfo(ROOT_PATH, PATHINFO_BASENAME));
      }

      /*
       * -------------------------------------------------------------------
       *  Now that we know the path, set the main path constants
       * -------------------------------------------------------------------
       */


      // The name of THIS file
      define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));

      define('ROOT', dirname(ROOT_PATH));

      // The PHP file extension
      define('EXT', '.php');

      // Path to the system folder
      define('BASEPATH', str_replace("\\", "/", $system_path));

      // Path to the front controller (this file)
      define('FCPATH', str_replace(SELF, '', ROOT_PATH));

      // Name of the "system folder"
      define('SYSDIR', trim(strrchr(trim(BASEPATH, '/'), '/'), '/'));


      // The path to the "application" folder
      if (is_dir($application_folder))
      {
        define('APPPATH', $application_folder.'/');
      }
      else
      {
        if (!is_dir(BASEPATH . $application_folder . '/'))
        {
          exit("Your application folder path does not appear to be set correctly. Please open the following file and correct this: ".SELF);
        }

        define('APPPATH', BASEPATH . $application_folder . '/');
      }

      if (file_exists(APPPATH.'config'.DIRECTORY_SEPARATOR.'config.php'))
      {
          $aSettings= include(APPPATH.'config'.DIRECTORY_SEPARATOR.'config.php');
      }
      else
      {
          $aSettings=array();
      }
      // Set debug : if not set : set to default from PHP 5.3
      if (isset($aSettings['config']['debug']))
      {
          if ($aSettings['config']['debug']>0)
          {
              define('YII_DEBUG', true);
        if($aSettings['config']['debug']>1)
      error_reporting(E_ALL);
        else
      error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
          }
          else
          {
              define('YII_DEBUG', false);
              error_reporting(0);
          }
      }
      else
      {
          error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);// Not needed if user don't remove his 'debug'=>0, for application/config/config.php (Installation is OK with E_ALL)
      }

      /**
       * Load Psr4 autoloader, should be replaced by composer autoloader at some point.
       */
      require_once 'application/Psr4AutoloaderClass.php';
      $loader = new Psr4AutoloaderClass();
      $loader->register();
      $loader->addNamespace('ls\pluginmanager', __DIR__ . '/application/libraries/PluginManager');
      $loader->addNamespace('ls\\pluginmanager', __DIR__ . '/application/libraries/PluginManager/Storage');
      /*
       * --------------------------------------------------------------------
       * LOAD THE BOOTSTRAP FILE
       * --------------------------------------------------------------------
       *
       * And away we go...
       *
       */

      require_once BASEPATH . 'yii' . EXT;
      require_once APPPATH . 'core/LSYii_Application' . EXT;

      $config = require_once(APPPATH . 'config/internal' . EXT);

      if (!file_exists(APPPATH . 'config/config' . EXT)) {    
          // If Yii can not start due to unwritable runtimePath, present an error    
          $runtimePath = $config['runtimePath'];
          if (!is_dir($runtimePath) || !is_writable($runtimePath)) {
              // @@TODO: present html page styled like the installer
              die (sprintf('%s should be writable by the webserver (766 or 776).', $runtimePath));
          }
      }

      Yii::$enableIncludePath = false;
      Yii::createApplication('LSYii_Application', $config);

      require_once APPPATH . 'helpers/globalsettings_helper.php';
}