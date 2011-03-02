<?php
class SimpleTags_Client {
	/**
	 * PHP4 constructor - Initialize Simple Tags client
	 *
	 * @return SimpleTags
	 */
	function SimpleTags_Client() {
		global $simple_tags;
		
		// Get options
		$options = get_option( STAGS_OPTIONS_NAME );
		
		// Add pages in WP_Query
		if ( $options['use_tag_pages'] == 1 ) {
			add_action( 'init', array(&$this, 'registerTagsForPage'), 11 );
		}
		
		// Call autolinks ?
		if ( $options['auto_link_tags'] == '1' ) {
			require( STAGS_DIR . '/inc/class.client.autolinks.php');
			$simple_tags['client-autolinks'] = new SimpleTags_Client_Autolinks();
		}
		
		// Call auto terms ?
		require( STAGS_DIR . '/inc/class.client.autoterms.php');
		$simple_tags['client-autoterms'] = new SimpleTags_Client_Autoterms();
		
		// Call post tags ?
		require( STAGS_DIR . '/inc/class.client.post_tags.php');
		$simple_tags['client-post_tags'] = new SimpleTags_Client_PostTags();
		
		return true;
	}
	
	/**
	 * Register taxonomy post_tags for page post type
	 *
	 * @return void
	 * @author Amaury Balmer
	 */
	function registerTagsForPage() {
		register_taxonomy_for_object_type( 'post_tag', 'page' );
	}
	
	/**
	 * Randomize an array and keep association
	 *
	 * @param array $array
	 * @return boolean
	 */
	function randomArray( &$array ) {
		if ( !is_array($array) || empty($array) ) {
			return false;
		}
		
		$keys = array_keys($array);
		shuffle($keys);
		foreach( (array) $keys as $key ) {
			$new[$key] = $array[$key];
		}
		$array = $new;
		
		return true;
	}
	
	/**
	 * Build rel for tag link
	 *
	 * @return string
	 */
	function buildRel() {
		global $wp_rewrite;
		$rel = ( is_object($wp_rewrite) && $wp_rewrite->using_permalinks() ) ? 'tag' : ''; // Tag ?
		if ( !empty($rel) ) {
			$rel = 'rel="' . $rel . '"'; // Add HTML Tag
		}
		
		return $rel;
	}
	
	/**
	 * Format data for output
	 *
	 * @param string $html_class
	 * @param string $format
	 * @param string $title
	 * @param string $content
	 * @param boolean $copyright
	 * @param string $separator
	 * @return string|array
	 */
	function outputContent( $html_class= '', $format = 'list', $title = '', $content = '', $copyright = true, $separator = '' ) {
		if ( empty($content) ) {
			return ''; // return nothing
		}
		
		if ( $format == 'array' && is_array($content) ) {
			return $content; // Return PHP array if format is array
		}
		
		if ( is_array($content) ) {
			switch ( $format ) {
				case 'list' :
					$output = '<ul class="'.$html_class.'">'. "\n\t".'<li>' . implode("</li>\n\t<li>", $content) . "</li>\n</ul>\n";
					break;
				default :
					$output = '<div class="'.$html_class.'">'. "\n\t" . implode("{$separator}\n", $content) . "</div>\n";
					break;
			}
		} else {
			$content = trim($content);
			switch ( $format ) {
				case 'string' :
					$output = $content;
					break;
				case 'list' :
					$output = '<ul class="'.$html_class.'">'. "\n\t" . '<li>'.$content."</li>\n\t" . "</ul>\n";
					break;
				default :
					$output = '<div class="'.$html_class.'">'. "\n\t" . $content . "</div>\n";
					break;
			}
		}
		
		// Replace false by empty
		$title = trim($title);
		if ( strtolower($title) == 'false' ) {
			$title = '';
		}
		
		// Put title if exist
		if ( !empty($title) ) {
			$title .= "\n\t";
		}
		
		if ( $copyright === true )
			return "\n" . '<!-- Generated by Simple Tags ' . STAGS_VERSION . ' - http://wordpress.org/extend/plugins/simple-tags -->' ."\n\t". $title . $output. "\n";
		else
			return "\n\t". $title . $output. "\n";
	}
	
	/**
	 * Remplace marker by dynamic values (use for related tags, current tags and tag cloud)
	 *
	 * @param string $element_loop
	 * @param object $term
	 * @param string $rel
	 * @param integer $scale_result
	 * @param integer $scale_max
	 * @param integer $scale_min
	 * @param integer $largest
	 * @param integer $smallest
	 * @param string $unit
	 * @param string $maxcolor
	 * @param string $mincolor
	 * @return string
	 */
	function formatInternalTag( $element_loop = '', $term = null, $rel = '', $scale_result = 0, $scale_max = null, $scale_min = 0, $largest = 0, $smallest = 0, $unit = '', $maxcolor = '', $mincolor = '' ) {
		// Need term object
		$element_loop = str_replace('%tag_link%', esc_url(get_term_link($term, $term->taxonomy)), $element_loop);
		$element_loop = str_replace('%tag_feed%', esc_url(get_term_feed_link($term->term_id, $term->taxonomy, '')), $element_loop);
		
		$element_loop = str_replace('%tag_name%', esc_html( $term->name ), $element_loop);
		$element_loop = str_replace('%tag_name_attribute%', esc_html(strip_tags($term->name)), $element_loop);
		$element_loop = str_replace('%tag_id%', $term->term_id, $element_loop);
		$element_loop = str_replace('%tag_count%', (int) $term->count, $element_loop);
		
		// Need rel
		$element_loop = str_replace('%tag_rel%', $rel, $element_loop);
		
		// Need max/min/scale and other :)
		if ( $scale_result !== null ) {
			$element_loop = str_replace('%tag_size%', 'font-size:'.$this->round(($scale_result - $scale_min)*($largest-$smallest)/($scale_max - $scale_min) + $smallest, 2).$unit.';', $element_loop);
			$element_loop = str_replace('%tag_color%', 'color:'.$this->getColorByScale($this->round(($scale_result - $scale_min)*(100)/($scale_max - $scale_min), 2),$mincolor,$maxcolor).';', $element_loop);
			$element_loop = str_replace('%tag_scale%', $scale_result, $element_loop);
		}
		
		// External link
		$element_loop = str_replace('%tag_technorati%', $this->formatExternalTag( 'technorati', $term->name ), $element_loop);
		$element_loop = str_replace('%tag_flickr%', $this->formatExternalTag( 'flickr', $term->name ), $element_loop);
		$element_loop = str_replace('%tag_delicious%', $this->formatExternalTag( 'delicious', $term->name ), $element_loop);
		
		return $element_loop;
	}
	
	/**
	 * Extend the round PHP function for force a dot for all locales instead the comma.
	 *
	 * @param string $value
	 * @param string $approximation
	 * @return void
	 * @author Amaury Balmer
	 */
	function round( $value, $approximation ) {
		$value = round( $value, $approximation );
		$value = str_replace( ',', '.', $value ); // Fixes locale comma
		$value = str_replace( ' ', '' , $value ); // No space
		return $value;
	}
	
	/**
	 * Format nice URL depending service
	 *
	 * @param string $type
	 * @param string $tag_name
	 * @return string
	 */
	function formatExternalTag( $type = '', $term_name = '' ) {
		if ( empty($term_name) ) {
			return '';
		}
		
		$term_name = esc_html($term_name);
		switch ( $type ) {
			case 'technorati':
				return '<a class="tag_technorati" href="'.esc_url('http://technorati.com/tag/'.str_replace(' ', '+', $term_name)).'" rel="tag">'.$term_name.'</a>';
				break;
			case 'flickr':
				return '<a class="tag_flickr" href="'.esc_url('http://www.flickr.com/photos/tags/'.preg_replace('/[^a-zA-Z0-9]/', '', strtolower($term_name)).'/').'" rel="tag">'.$term_name.'</a>';
				break;
			case 'delicious':
				return '<a class="tag_delicious" href="'.esc_url('http://del.icio.us/popular/'.strtolower(str_replace(' ', '', $term_name))).'" rel="tag">'.$term_name.'</a>';
				break;
			default:
				return '';
				break;
		}
	}
}
?>