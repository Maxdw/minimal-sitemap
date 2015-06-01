<?php
/**
 * XmlHelper
 *
 * Simple helper class that allows the user to concatenate
 * XML documents or fragments based on the required elements.
 *
 * Use $instance->init to start a XML document
 * Use $instance->open to open an element
 * Use $instance->close to close # of (or all) elements
 * Use $instance->single to append a self closing element
 * Use $instance->element to append an element
 * Use $instance->append to append raw content to the XML
 *
 * Typecasting $instance to (string) or calling $instance->render()
 * will return all previously declared XML if $instance was created
 * using $capture = true (default).
 *
 * @copyright Copyright (c) Dutchwise V.O.F. (http://dutchwise.nl)
 * @author Max van Holten (<max@dutchwise.nl>)
 * @license http://www.opensource.org/licenses/mit-license.php MIT license
 */
if(!class_exists('XmlHelper')) {
	class XmlHelper extends HtmlHelper {
		
		/**
		 * Initiates the XML document.
		 * 
		 * @param string $version
		 * @param string $encoding
		 * @return string
		 */
		public function init($version = '1.0', $encoding = 'UTF-8') {
			$init = "<?xml version=\"{$version}\" encoding=\"{$encoding}\"?>";
			
			if($this->_captureEnabled) {
				$this->_output .= $init;
			}
			
			return $init;
		}
		 
	}
}
?>