<?php
/**
 * Plugin Name: Minimal Sitemap
 * Description: The minimum code required to allow you to open up and manage a sitemap.xml endpoint. When enabled a dynamically generated sitemap XML will be available at /sitemap.xml. Manually add URLs or exclude URLs using Regular Expressions. Can be configured under 'Settings'.
 * Version: 1.0.0
 * Requires at least: 4.2
 * Author: Dutchwise
 * Author URI: http://www.dutchwise.nl/
 * Text Domain: minsit
 * Domain Path: /locale/
 * Network: true
 * License: MIT license (http://www.opensource.org/licenses/mit-license.php)
 */

include 'xml.php';

class MinimalSitemap {
	
	/**
	 * Sanitizes sitemap settings before saving.
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitizeSitemapSettings(array $input) {
		foreach($input as $key => &$value) {
			$value = stripslashes(strip_tags($value));
		}
		
		$field = 'enabled';
		
		if(array_key_exists($field, $input)) {
			$input[$field] = (int)!!$input[$field];
		}
		
		return apply_filters('minsit_sanitize_sitemap_options', $input);
	}
	
	/**
	 * Renders the sitemap admin settings page.
	 *
	 * @return void
	 */
	public function renderAdminSettingsPage() {
		$html = new HtmlHelper(false);
		
		echo $html->open('div', array('class' => 'wrap'));
		
		// start form
		echo $html->open('form', array(
			'action' => 'options.php',
			'method' => 'POST',
			'accept-charset' => get_bloginfo('charset'),
			'novalidate'
		));
		
		// form title
		echo $html->element('h2', __('Settings', 'minsit'));
		
		echo $html->single('br');
		
		// prepare form for settings (nonce, referer fields)
		settings_fields('sitemap');
		
		// renders all settings sections of the specified page
		do_settings_sections('sitemap');
		
		// renders the submit button
		submit_button();
		
		echo $html->close();
	}
	
	/**
	 * Renders the sitemap admin settings section.
	 *
	 * @param array $args 'id', 'title', 'callback'
	 * @return void
	 */
	public function renderAdminSitemapSettingsSection($args) {
		// do nothing
	}
	
	/**
	 * Renders the sitemap admin settings fields.
	 *
	 * @param array $args Unknown
	 * @return void
	 */
	public function renderAdminSitemapSettingField($args) {
		$options = get_option('sitemap_settings');
		$html = new HtmlHelper();
		$atts = array();
		
		// if option does not exist, add to database
		if($options == '') {
			add_option('sitemap_settings', array());
		}
		
		// make sure the required label_for and field arguments are present to render correctly
		if(!isset($args['label_for'], $args['field'])) {
			throw new InvalidArgumentException('add_settings_field incorrectly configured');
		}
		
		// define attributes each field should have
		$atts['id'] = $args['label_for'];
		$atts['name'] = "sitemap_settings[{$args['field']}]";
		
		// render html based on which field needs to be rendered
		switch($args['field']) {
			case 'enabled':
				$atts['type'] = 'checkbox';
				$atts['value'] = '1';
				
				$html->single('input', array(
					'id' => $atts['id'] . '_hidden',
					'type' => 'hidden',
					'value' => 0
				) + $atts);
				
				if(isset($options[$args['field']]) && $options[$args['field']]) {
					$atts['checked'] = 'checked';
				}
				
				$html->single('input', $atts);				
				break;
			case 'include':
			case 'pattern':
				$value = '';
			
				if(isset($options[$args['field']])) {
					$value = $options[$args['field']];
				}
				
				$atts['style'] = 'width: 550px;height: 199px;';
				
				$html->element('textarea', $atts, $value);
				
				if($args['field'] == 'pattern') {
					$tip = __('Use %s to match everything before the URI.', 'minsit');
					$reg = 'https?://?[da-z.-]+.[a-z.]+/';
					
					$html->element('p', sprintf($tip, "<span style=\"color:blue\">{$reg}</span>"));
				}
		}
		
		$html->close();
		
		echo $html;
	}
	
	/**
	 * Runs when the WordPress admin area is initialised.
	 *
	 * @return void
	 */
	public function onAdminInit() {
		register_setting('sitemap', 'sitemap_settings', array($this, 'sanitizeSitemapSettings'));
		
		add_settings_section(
			'sitemap_section',				// ID used to identify this section and with which to register options
			__( 'Sitemap', 'minsit' ),		// Title to be displayed on the administration page
			array($this, 'renderAdminSitemapSettingsSection'),
			'sitemap'						// Page on which to add this section of options
		);
		
		// regex tester app
		$regex_tester_url = 'https://regex101.com/';
		
		// field names and labels
		$fields = array(
			'enabled' => __('Enable Sitemap', 'minsit'),
			'include' => __('Include the following return separated URLs:', 'minsit'),
			'pattern' => sprintf(__('Exclude URLs matching the following return separated <a href="%s" target="_blank">Regex patterns:</a>', 'minsit'), $regex_tester_url)
		);
		
		// register and render the fields using add_settings_field and the $fields array
		foreach($fields as $field => $label) {
			add_settings_field(
				"sitemap_settings[{$field}]",// ID used to identify the field throughout the theme
				$label,						// The label to the left of the option interface element
				array($this, 'renderAdminSitemapSettingField'),
				'sitemap',						// The page on which this option will be displayed
				'sitemap_section',				// The name of the section to which this field belongs
				array(							// The array of arguments to pass to the callback.
					'field' => $field,
					'label_for' => $field
				)
			);
		}
	}
	
	/**
	 * Adds the sitemap.xml rewrite rule.
	 * 
	 * @action init
	 * @return void
	 */
	public function addRewriteRule() {
		add_rewrite_rule('sitemap\.xml$', 'index.php?sitemap=xml', 'top');
	}
	
	/**
	 * Adds the sitemap custom query.
	 *
	 * @param array $vars
	 * @filter query_vars
	 * @return array
	 */
	public function addQuery($vars) {
		$vars[] = 'sitemap';
		return $vars;
	}
	
	/**
	 * Prevents the sitemap.xml being rewritten to sitemap.xml/
	 *
	 * @param string $redirect_url
	 * @param string|null $requested_url
	 * @filter redirect_canonical
	 * @return string|boolean
	 */
	public function disableCanonical($redirect_url, $requested_url = null) {
		global $wp_query;
		
		if(isset($wp_query->query_vars['sitemap'])) {
			return false;
		}
		
		return $redirect_url;
	}
	
	/**
	 * Runs when this plugin is activated.
	 *
	 * @return void
	 */
	public function activate() {
		$this->addRewriteRule();
		flush_rewrite_rules();
	}
	
	/**
	 * Runs when this plugin is deactivated.
	 *
	 * @return void
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}
	
	/**
	 * Runs when WordPress is determining which template
	 * to render and send as a response.
	 *
	 * @param string $template
	 * @filter template_include
	 * @return void
	 */
	public function setTemplate($template) {
		global $wp_query;
		
		// load the settings array
		$options = get_option('sitemap_settings');
		
		if(!empty($options['enabled']) && isset($wp_query->query_vars['sitemap'])) {
			// make sure nothing is rendered
			$wp_query->is_page = 0;
			$wp_query->is_archive = 0;
			$wp_query->is_home = 0;
			$wp_query->is_404 = 0;
			$wp_query->is_post_type_archive = 0;
			
			// set xml content type
			header('Content-type: application/xml; charset=utf-8');
			
			// return the template file that will render the sitemap
			return __DIR__ . DIRECTORY_SEPARATOR . 'template.php';
		}
		
		return $template;
	}
	
	/**
	 * Runs when the WordPress admin menus are initialised.
	 *
	 * @return void
	 */
	public function onAdminMenu() {
		// adds the email menu item to WordPress's main Settings menu
		add_options_page(__('Sitemap Settings', 'minsit'), __('Sitemap', 'minsit'), 'manage_options', 'sitemap', array($this, 'renderAdminSettingsPage'));
	}
	
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action('admin_menu', array($this, 'onAdminMenu'));
		add_action('admin_init', array($this, 'onAdminInit'));
		add_action('init', array($this, 'addRewriteRule'), 1);
		
		add_filter('query_vars', array($this, 'addQuery'), 1);
		add_filter('template_include', array($this, 'setTemplate'), 1);
		add_filter('redirect_canonical', array($this, 'disableCanonical'));
		
		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
	}
	
}

new MinimalSitemap;