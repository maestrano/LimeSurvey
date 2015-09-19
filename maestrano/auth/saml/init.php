<?php
/**
 * This controller creates a SAML request and redirects to
 * Maestrano SAML Identity Provider
 *
 */

//-----------------------------------------------
// Define root folder and load base
//-----------------------------------------------
if (!defined('MAESTRANO_ROOT')) { define("MAESTRANO_ROOT", realpath(dirname(__FILE__). '/../../')); }
if (!defined('ROOT_PATH')) { define('ROOT_PATH', realpath(MAESTRANO_ROOT . '/../')); }
chdir(ROOT_PATH);

//-----------------------------------------------
// Load Maestrano library
//-----------------------------------------------
require_once ROOT_PATH . '/vendor/maestrano/maestrano-php/lib/Maestrano.php';
Maestrano::configure(ROOT_PATH . '/maestrano.json');

$req = new Maestrano_Saml_Request($_GET);
header('Location: ' . $req->getRedirectUrl());
