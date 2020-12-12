<?php

/**
 * @package KandandaMassEmail
 * @author  Heinl Christian <heinchrs@gmail.com>
 * @copyright  (C) 2015-2020 Heinl Christian
 * @license GNU General Public License version 2 or later
 */

// -- No direct access
defined('_JEXEC') || die('=;)');

// Include the helper-php
require_once __DIR__ . '/helper.php';

// Create new instance of helper class
$emailForm = new KandandaMassEmail($params);

// Process formular data and send out emails
$emailForm->processFormData();

// Show formular data
require_once JModuleHelper::getLayoutPath('mod_kandandamassemail', $params->get('layout', 'default'));
