<?php
/**
 * MinimalSitemapXmlTemplate
 *
 * Handles sitemap XML representation and URL data.
 *
 * @copyright Copyright (c) Dutchwise V.O.F. (http://dutchwise.nl)
 * @author Max van Holten (<max@dutchwise.nl>)
 * @license http://www.opensource.org/licenses/mit-license.php MIT license
 */
class MinimalSitemapXmlTemplate {
	
	/**
	 * XML Namespace.
	 *
	 * @var string
	 */
	protected $_ns = 'http://www.sitemaps.org/schemas/sitemap/0.9';
	
	/**
	 * XML Helper class, representing
	 * the document body.
	 *
	 * @var XmlHelper
	 */
	protected $_body = null;
	
	/**
	 * The XML timestamp format.
	 *
	 * @var string
	 */
	protected $_timestampFormat = 'Y-m-d\TH:i:sP';
	
	/**
	 * Sitemap data.
	 *
	 * @var array
	 */
	protected $_data = array(
		'posts' => array(),
		'categories' => array(),
		'tags' => array()
	);
	
	/**
	 * Sitemap URLs containing arrays
	 * of urls and metadata.
	 *
	 * @var array
	 */
	protected $_urls = array();
	
	/**
	 * Homepage URL.
	 *
	 * @var string
	 */
	protected $_homeUrl = null;
	
	/**
	 * Adds an URL to the sitemap.
	 *
	 * @param string $url
	 * @param string $modified
	 * @param array $extra
	 * @return void
	 */
	public function setURL($url, $modified = '', array $extra = array()) {
		$entry = array();
		$entry['loc'] = esc_url($url);
		
		if($modified) {
			$entry['modified'] = esc_html($modified);
		}
		
		array_walk($extra, 'esc_html');
		
		$this->_urls[$url] = array_merge($extra, $entry);
	}
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->_body = new XmlHelper();
		$this->_body->init();
		$this->_body->open('urlset', array('xmlns' => $this->_ns));
		
		$this->_homeUrl = esc_url(get_home_url() . (substr(get_home_url(), -1) === '/' ? '' : '/'));
	}
	
	/**
	 * Renders the XML template.
	 *
	 * @return string
	 */
	public function render() {	
		$body =& $this->_body;
	
		foreach($this->_urls as $url) {
			$body->open('url');
			
			foreach($url as $element => $content) {
				$body->element($element, $content);
			}
			
			$body->close(1);
		}
		
		$body->close();
		
		return $body->render();
	}
	
	/**
	 * Loads all required posts URLs including term URLs.
	 *
	 * @return void
	 */
	public function loadURLs() {
		global $wpdb;
		global $post;
		
		// query all posts viable to have an URL
		$results = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE (post_status = 'publish' AND post_password = '' AND menu_order = 0) ORDER BY post_modified DESC");
		
		// modified timestamp format
		$format = $this->_timestampFormat;
		
		foreach($results as $result) {
			$post = new WP_Post($result);
			$taxonomies = get_taxonomies(array('public' => true, 'rewrite' => true)); 
			$terms = wp_get_post_terms($post->ID, $taxonomies);
			
			// get post modified timestamp
			$mod_time = get_post_modified_time($format, null, $post);
			$mod_date = apply_filters('get_the_modified_date', $mod_time, $format);
			
			// get categories/tags and other taxonomy page URLs related to this post
			foreach($terms as $term) {
				$this->setURL(get_term_link($term));
			}
			
			// get author url
			if($user_url = get_the_author_meta('user_id')) {
				$this->setURL($user_url);
			}
			
			// get post url
			$url = get_permalink($post);
			
			$this->setURL($url, $mod_date);
		}
	}
	
	/**
	 * Filters out all URLs matching the
	 * provided pattern.
	 *
	 * @param string $pattern
	 * @return int
	 */
	public function filterOut($pattern) {
		$count = 0;
		
		$pattern = trim($pattern);
		$pattern = "@$pattern@i";
		
		foreach($this->_urls as $url => $meta) {
			if(preg_match($pattern, $url) == 1) {
				$count++;
				unset($this->_urls[$url]);
			}
		}
		
		return $count;
	}
	
	/**
	 * Magic PHP method, is run when this
	 * object is typecasted to string.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->render();
	}
	
}

// user settings
$options = get_option('sitemap_settings');

// load sitemap template class and urls
$template = new MinimalSitemapXmlTemplate();
$template->loadURLs();

// set extra user set URLs
if($options['include']) {
	$includes = explode(PHP_EOL, $options['include']);
	array_walk($includes, array($template, 'setURL'));
}

// filter out URLs using user configured patterns
if($options['pattern']) {
	$patterns = explode(PHP_EOL, $options['pattern']);
	array_walk($patterns, array($template, 'filterOut'));
}

// render xml
echo $template;
?>