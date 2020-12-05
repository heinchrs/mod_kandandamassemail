<?php

/**
 * @package    KandandaMassEmail
 * @author     Heinl Christian <heinchrs@gmail.com>
 * @license    GNU General Public License version 2 or later 
 */

//-- No direct access
defined('_JEXEC') || die('=;)');

// Include the helper-php
require_once __DIR__ . '/helper.php';

//create new instance of helper class
$EmailForm = new KandandaMassEmail($params);
//process formular data and send out emails
$EmailForm->processFormData();

//Show formular data
require_once JModuleHelper::getLayoutPath('mod_kandandamassemail', $params->get('layout', 'default'));
?>

