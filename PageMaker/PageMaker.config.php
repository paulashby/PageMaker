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
	
	'removeCreated' => array(
		'name'=> 'rmv_created',
		'type' => 'checkbox', 
		'label' => 'Remove pages on uninstall?',
		'autocheck' => 0,
		'checkedValue' => 1,
		'uncheckedValue' => 0,
		'value' => $this->rmv_created,
		'required' => false 
	)
);