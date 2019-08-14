<?php
/**
 * SQL data install script
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */

/**
 * Populate log error table
 */
$errorCodes = Mage::getSingleton('netevensync/config')->getErrorLabels();
$logModel = Mage::getModel('netevensync/log');

foreach($errorCodes as $code => $label) {
	$data = array(
			'id'			=> null,
			'code'			=> $code,
			'has_error'		=> 0,
		);
	$logModel->setData($data);
	$logModel->save();
}

/**
 * Populate process types
 */
$processCodes = Mage::getSingleton('netevensync/config')->getProcessCodes();
$processModel = Mage::getModel('netevensync/config_process');

foreach($processCodes as $processCode) {
	$data = array(
			'id'			=> null,
			'process_code'	=> $processCode, 
			'last_sync'		=> '1970-01-01 00:00:00',
			'next_sync'		=> '1970-01-01 00:00:00',
	);
	$processModel->setData($data);
	$processModel->save();
}