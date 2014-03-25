<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014
 * @package    zipstream
 * @license    GNU/LGPL 
 * @filesource
 */

/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'ZipStream',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	'ZipStream\ZipStream' => 'system/modules/zipstream/ZipStream.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'show_zipdown' => 'system/modules/zipstream/templates',
));
