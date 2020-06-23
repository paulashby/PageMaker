<?php namespace ProcessWire;

/**
 * Optional config file for PageMaker.module
 *
 * When present, the module will be configurable and the configurable properties
 * described here will be automatically populated to the module at runtime.  
 * 
 * For this module, this is populated after installation
 */
$config = array(
	
	'firstField' => array(
		'name'=> 'err_mssg',
		'type' => 'text', 
		'label' => 'Error message',
		'description' => 'Shown if the module cannot be uninstalled due to created templates remaining in the tree. Feel free to enter a custom message to suit your needs', 
		'value' => 'Unable to uninstall the module as existing pages are using templates created by PageMaker', 
		'required' => false 
	)
);