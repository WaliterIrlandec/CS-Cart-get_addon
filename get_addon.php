#!/usr/bin/env php
<?php

namespace Tygh\GetAddon;

use Tygh\Registry;

define('AREA', 'A');
define('ACCOUNT_TYPE', 'admin');

require(dirname(__FILE__) . '/init.php');

/**
*
*/
class GetAddonException extends \Exception
{

    protected function getMessageBody()
    {

        switch ( $this->getCode() ) {
            case 0 : $message = 'Быть такого не может!!!'; break;
            case 1 : $message = "Add-on '" . $this->getMessage() . "' doesn't exist on this site"; break;
            case 2 : $message = "Cannot create PATH: " . $this->getMessage(); break;
            case 3 : $message = "Data issue: " . $this->getMessage(); break;

            default: $message = "Something broke" . ' ' . $this->getMessage();
        }

        return $message;
    }

    public function __toString()
    {
        return 'Error!!! ' . $this->getMessageBody();
    }
}

/**
* Print info
*/
class DisplayMessage
{

    protected $line = 1;
    protected $break = '<br /><br />';

    public function push( $value, $showLine = true )
    {

        //  leave it blank for the header
        if (Addon::isUpload()) {
            return;
        }

        fn_echo( ($showLine ? (int) $this->line++ . '.  ' : '') . $value . $this->break);

    }

    public function hr()
    {
        $this->push('<hr />', false);
    }

    public function end( $value = '' )
    {

        $value && $this->push($value);
        $this->push('FIN', false);
        exit;
    }
}

/**
* Join cli & http
*/
class ScriptParam
{

    protected $param = [];

    public function __construct()
    {

        if (self::isConsole()) {
        //  cli

            //  param wih short name
            $consoleParamsWithShort = array(
                'a:'    => 'addon_name:',
                'p::'   => 'package::',
                'z'     => 'zip'
            );

            //  param only for long name
            $consoleParams = array_merge($consoleParamsWithShort, [
                'help',
                'wibug',
                'latest'
            ]);

            $this->param = getopt( implode('', array_keys($consoleParams)), $consoleParams );

            //  duplicate param with short name
            foreach ($consoleParamsWithShort as $short => $long) {

                if (isset($this->param[trim(str_replace(':', '', $short))])) {
                    $this->param[trim(str_replace(':', '', $long))] = $this->param[trim(str_replace(':', '', $short))];
                }

            }

        } else {
        //  browser

            $this->param = $_REQUEST;

        }

    }

    public function readAddonName()
    {
      readline_completion_function(array('self', 'autocompleter'));
      $command_input = readline("Add-on name: ");

      $this->set('addon_name', $command_input);
    }

    public function autocompleter($input, $index)
    {
      // FIXME: add for global const config
      $addons_folder = DIR_ROOT . '/app/addons/';

      return is_dir($addons_folder)
        ? $addons_list = array_diff(scandir($addons_folder), array('..', '.'))
        : [];
    }

    public function get($name)
    {
        return isset($this->param[$name]) ? $this->param[$name] : '';
    }

    public function set($name, $value)
    {
        return $this->param[$name] = $value;
    }

    public function isset($name)
    {
        return isset($this->param[$name]);
    }

    static public function isConsole()
    {
        global $argc;
        return ($argc > 0);
    }

    static public function getHelp()
    {

        return self::isConsole()
        ? "
usage: php get_addon.php [--help] [--wibug] [-a|--addon_name='first_addon, second_addon'] [-p|--package=package_name] [-z|--zip]

Options:
            --help          Show this message
            --wibug         Display PHP notice
        -a  --addon_name    List of the add-ons separated by comma
                            (if empty, can enter in autocomplete input)
        -p  --package       Create folder on package format with the
                            specified name or (if not exist) the add-on version
        -z  --zip           Create zip archive
            --latest        Create latest folder

Example:
        php get_addon.php --addon_name=altteam_esp --package='production esp'
        php get_addon.php -aaltteam_shop_by_brands -zp
"
        : "
Request params:
    help          Show this message
    wibug         Display PHP notice
    addon_name    List of the add-ons separated by comma
                  (if empty, can enter in autocomplete input)
    package       Create folder on package format with the specified
                  name or (if not exist) the add-on version
    zip           Create zip archive
    upload        Upload zip archive

";
    }

}

/**
*
*/
class Addon
{

    private $data = [];
    static public $scriptParam = [];

    public function __construct($id)
    {

        $data = &$this->data;

        $data['id'] = $id;
        $data['settings'] = Registry::get('addons.' . $id);

        $data['addon_scheme'] = \Tygh\Addons\SchemesManager::getScheme($id);

        $data['version'] = $this->getVersion();

        $data['save_name']      = $this->getSaveName();
        $data['save_addon_dir'] = DIR_ROOT . '/get_addons/' . $data['id'] . '/';
        $data['to_dir']      = $data['save_addon_dir'] . $data['save_name'];
        $data['temp_dir']      = $data['save_addon_dir'] . $data['save_name'];

        fn_rm($data['to_dir']);

        fn_rm($data['save_addon_dir'] . '/temp');
        fn_mkdir($data['save_addon_dir'] . '/temp');

    }

    public function __destruct() {
        fn_rm($this->getData('save_addon_dir') . '/temp');
    }

    private function getData($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        } else {
            throw new GetAddonException( "Cann't find data: " . $name, 3 );
        }
    }

    private function setData($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function getVersion()
    {
        return $this->getData('addon_scheme')->getVersion();
    }

    public function getNormalName()
    {
        return $this->getData('addon_scheme')->getName();
    }

    public function getSaveName()
    {
        return self::isPackage()
            ? !empty(Addon::$scriptParam->get('package'))
                ? Addon::$scriptParam->get('package')
                : $this->getData('id') . '_' . $this->getVersion()
            : $this->getData('id') . date('Y_m_d-H_i_s');
    }

    public function createArchive()
    {
        if (!self::isZip()) {
            return false;
        }

        fn_copy(
            $this->getData('to_dir'),
            $this->getData('save_addon_dir') .'temp/'
        );

        $this->setData('archive_name', $this->getData('to_dir') . '/' . $this->getData('save_name') . '.zip');

        $create_archive = self::zipFolder(
            $this->getData('save_addon_dir') .'temp/',
            $this->getData('archive_name')
        );

    }

    public function uploadArchive()
    {

        if ((!self::isPackage() && !self::isZip()) || !Addon::isUpload()) {
            return false;
        }

        header('Content-Type: application/zip');
        fn_get_file($this->getData('archive_name'));

    }

    static public function isUpload()
    {
        return Addon::$scriptParam->isset('upload');
    }

    static public function isPackage()
    {
        return Addon::$scriptParam->isset('package');
    }

    static public function isZip()
    {
        return Addon::$scriptParam->isset('zip');
    }

    //  based on Veter function
    static public function zipFolder($source, $destination, $callback = '')
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        $zip = new \ZipArchive();
        if (!$zip->open($destination, \ZIPARCHIVE::CREATE)) {
            return false;
        }

        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source) === true) {

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file)
            {

                $file = str_replace('\\', '/', $file);

                // Ignore "." and ".." folders
                if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                    continue;

                $file = realpath($file);

                if (is_dir($file) === true)
                {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                }
                else if (is_file($file) === true)
                {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        }
        else if (is_file($source) === true)
        {
            $zip->addFromString(basename($source), file_get_contents($source));
        }

        $status = $zip->close();

        if ($status && gettype($callback) == 'object') {
            $callback();
        }

        return $status;
    }

    //  based on Kubik function
    static public function fullCopy($source, $target) {

        if (is_dir($source))  {

            fn_mkdir($target);
            $d = dir($source);

            while (FALSE !== ($entry = $d->read())) {
                if ($entry == '.' || $entry == '..') continue;
                self::fullCopy("$source/$entry", "$target/$entry");
            }

            $d->close();

        } else {
            fn_copy($source, $target);
        }

    }

}

$m = new DisplayMessage();
Addon::$scriptParam = new ScriptParam();

//  dispaly help
if (Addon::$scriptParam->isset('help')) {
    fn_print_r(ScriptParam::getHelp());
    exit();
}

//  enable error reporting
if (Addon::$scriptParam->isset('wibug')) {

    error_reporting(E_ALL);
    ini_set('display_errors', 'On');

    if (!defined('DEVELOPMENT')) {
        define('DEVELOPMENT', true);
    }

}

//  check required fields
if (!Addon::$scriptParam->get('addon_name')) {

  if (ScriptParam::isConsole()) {
    //  try to get with input line
    Addon::$scriptParam->readAddonName();
  }

  if (!Addon::$scriptParam->get('addon_name')) {
    $m->end('Блять!!! введи имя в addon_name, можно через запятаю');    //  Kubik original mesage $)
  }
}

//  create main folder if not exist
if (!is_dir(DIR_ROOT . '/get_addons/')) {
    fn_mkdir(DIR_ROOT . '/get_addons/');
}


$themes = array();

$dir_list = scandir(DIR_ROOT . '/design/themes');

foreach ($dir_list as $v) {

    if (is_dir(DIR_ROOT . '/design/themes/' . $v) && strpos($v, '.') === false) {
        $themes[] = $v;
    }

}

$scan_dirs = array(
    '/app/addons/',
    '/js/addons/',
    '/design/backend/templates/addons/',
    '/design/backend/css/addons/',
    '/design/backend/mail/templates/addons/',
    '/design/backend/media/images/addons/',
);

$dir_themes = array(
    '/design/themes/[name]/templates/addons/',
    '/design/themes/[name]/css/addons/',
    '/design/themes/[name]/mail/templates/addons/',
    '/design/themes/[name]/media/images/addons/',

);

foreach ($dir_themes as $v) {
    foreach ($themes as $name) {
        $dir_for_scan = str_replace('[name]', $name, $v);
        $scan_dirs[] = $dir_for_scan;
    }
}

$m->push('Start', false);
$m->hr();

$addons = explode(',', Addon::$scriptParam->get('addon_name'));

foreach ($addons as $addon_name) {

    try {

        $addon_name = trim($addon_name);

        if (file_exists(DIR_ROOT . '/app/addons/' . $addon_name . '/addon.xml')) {

            $addon = new Addon($addon_name);

            $save_name      = $addon->getSaveName();
            $save_addon_dir = DIR_ROOT . '/get_addons/' . $addon_name . '/';
            $to_dir = $save_addon_dir . $save_name;

            if ( !fn_mkdir($to_dir) ) {
                throw new GetAddonException( $to_dir, 2 );
            }

            foreach ($scan_dirs as $v) {

                $from = DIR_ROOT . $v . $addon_name;
                $to = $to_dir . str_replace('design/themes', 'var/themes_repository', $v  . $addon_name);

                if (file_exists($from)) {
                    Addon::fullCopy($from, $to);
                }
            }

            if (is_dir(DIR_ROOT . '/var/langs/en')) {

                $dir_list = scandir(DIR_ROOT . '/var/langs');

                foreach ($dir_list as $v) {

                    if (is_dir(DIR_ROOT . '/var/langs/' . $v) && strpos($v, '.') === false) {

                        $from = DIR_ROOT . '/var/langs/' . $v .  '/addons/' . $addon_name . '.po';
                        $to = $to_dir . '/var/langs/' . $v .  '/addons/' . $addon_name . '.po';

                        if (file_exists($from)) {

                            fn_mkdir($to_dir . '/var/langs/' . $v .  '/addons/');
                            fn_copy($from, $to);
                        }
                    }
                }
            }

            fn_rm($save_addon_dir . 'latest');

            if (Addon::$scriptParam->isset('latest')) {
              //  copy on 'latest' folder
              fn_copy($to_dir, $save_addon_dir . '0.latest');
            }

            //  create archive if package
            $addon->createArchive();

            $addon->uploadArchive();

            $m->push($addon->getNormalName()
              . ' (' . $addon_name . ') '
              . ' Version: ' . $addon->getVersion());

        } else {
            throw new GetAddonException( $addon_name, 1 );
        }

    } catch( GetAddonException $e ){

        $m->push($e);
        continue;
    }
}

$m->hr();
$m->end();
