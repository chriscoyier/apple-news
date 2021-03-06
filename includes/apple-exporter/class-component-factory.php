<?php
namespace Apple_Exporter;

/**
 * This class in in charge of creating components. Manual component
 * instantiation should be avoided, use this instead.
 *
 * @since 0.2.0
 */
class Component_Factory {

	/**
	 * Available components.
	 *
	 * @var array
	 * @static
	 * @access private
	 */
	private static $components = array();

	/**
	 * Current workspace.
	 *
	 * @var Workspace
	 * @static
	 * @access private
	 */
	private static $workspace = null;

	/**
	 * Current settings.
	 *
	 * @var Settings
	 * @static
	 * @access private
	 */
	private static $settings = null;

	/**
	 * Current styles.
	 *
	 * @var Component_Text_Styles
	 * @static
	 * @access private
	 */
	private static $styles = null;

	/**
	 * Current layouts.
	 *
	 * @var Component_Layouts
	 * @static
	 * @access private
	 */
	private static $layouts = null;

	/**
	 * Initialize the component factory.
	 *
	 * @param Workspace $workspace
	 * @param Settings $settings
	 * @param Component_Text_Styles $styles
	 * @param Component_Layouts $layouts
	 * @static
	 * @access public
	 */
	public static function initialize( $workspace, $settings, $styles, $layouts ) {
		self::$workspace = $workspace;
		self::$settings  = $settings;
		self::$styles    = $styles;
		self::$layouts   = $layouts;

		// Order is important. Components are checked in the order they are added.
		self::register_component( 'gallery'      ,   '\\Apple_Exporter\\Components\\Gallery'         );
		self::register_component( 'tweet'        ,   '\\Apple_Exporter\\Components\\Tweet'           );
		self::register_component( 'instagram'    ,   '\\Apple_Exporter\\Components\\Instagram'       );
		self::register_component( 'img'          ,   '\\Apple_Exporter\\Components\\Image'           );
		self::register_component( 'iframe'       ,   '\\Apple_Exporter\\Components\\Embed_Web_Video' );
		self::register_component( 'video'        ,   '\\Apple_Exporter\\Components\\Video'           );
		self::register_component( 'audio'        ,   '\\Apple_Exporter\\Components\\Audio'           );
		self::register_component( 'heading'      ,   '\\Apple_Exporter\\Components\\Heading'         );
		self::register_component( 'blockquote'   ,   '\\Apple_Exporter\\Components\\Quote'           );
		self::register_component( 'pre'          ,   '\\Apple_Exporter\\Components\\Pre'             );
		self::register_component( 'p'            ,   '\\Apple_Exporter\\Components\\Body'            );
		self::register_component( 'hr'           ,   '\\Apple_Exporter\\Components\\Divider'         );
		// Non HTML-based components
		self::register_component( 'intro'        ,   '\\Apple_Exporter\\Components\\Intro'           );
		self::register_component( 'cover'        ,   '\\Apple_Exporter\\Components\\Cover'           );
		self::register_component( 'title'        ,   '\\Apple_Exporter\\Components\\Title'           );
		self::register_component( 'byline'       ,   '\\Apple_Exporter\\Components\\Byline'          );
		self::register_component( 'advertisement',   '\\Apple_Exporter\\Components\\Advertisement'   );

		// Allow built-in components and order to be overridden
		self::$components = apply_filters( 'apple_news_initialize_components', self::$components );
	}

	/**
	 * Register a component.
	 *
	 * @param string $shortname
	 * @param string $classname
	 * @static
	 * @access private
	 */
	private static function register_component( $shortname, $classname ) {
		self::$components[ $shortname ] = apply_filters( 'apple_news_register_component', $classname, $shortname );
	}

	/**
	 * Get a component.
	 *
	 * @param string $shortname
	 * @param string $html
	 * @return Component
	 * @static
	 * @access public
	 */
	public static function get_component( $shortname, $html ) {
		$class = self::$components[ $shortname ];

		if ( is_null( $class ) || ! class_exists( $class ) ) {
			return null;
		}

		return new $class( $html, self::$workspace, self::$settings, self::$styles, self::$layouts );
	}

	/**
	 * Given a node, returns an array of all the components inside that node. If
	 * the node is a component itself, returns an array of only one element.
	 *
	 * @param DomNode $node
	 * @return array
	 * @static
	 * @access public
	 */
	public static function get_components_from_node( $node ) {
		$result = array();

		foreach ( self::$components as $shortname => $class ) {
			$matched_node = $class::node_matches( $node );

			// Nothing matched? Skip to next match.
			if ( ! $matched_node ) {
				continue;
			}

			// Did we match several components? If so, a hash is returned. Both the
			// body and heading components can returns this, in the case they find
			// non-markdown-able elements inside.
			if ( is_array( $matched_node ) ) {
				foreach ( $matched_node as $base_component ) {
					$result[] = self::get_component( $base_component['name'], $base_component['value'] );
				}

				return $result;
			}

			// We matched a single node
			$html = $node->ownerDocument->saveXML( $matched_node );
			$result[] = self::get_component( $shortname, $html );
			return $result;
		}

		// Nothing found. Maybe it's a container element?
		if ( $node->hasChildNodes() ) {
			foreach ( $node->childNodes as $child ) {
				$result = array_merge( $result, self::get_components_from_node( $child, $node ) );
			}
			// Remove all nulls from the array
			$result = array_filter( $result );
		}

		// If nothing was found, log this as a component error by recording the node name.
		// Only record components with a tagName since otherwise there is nothing to report.
		// Others nodes without a match are almost always just stray empty text nodes
		// that are always safe to remove. Paragraphs should also be ignored for this reason.
		if ( empty( $result ) && ( ! empty( $node->tagName ) && 'p' !== $node->tagName ) ) {
			self::$workspace->log_error( 'component_errors', $node->tagName );
		}

		return $result;
	}

}
