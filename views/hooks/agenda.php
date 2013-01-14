<?php
/**
 * @for Day Template
 * This file contains the hook logic required to create an effective day grid view.
 *
 * @package TribeEventsCalendarPro
 * @since  3.0
 * @author Modern Tribe Inc.
 *
 */

if ( !defined('ABSPATH') ) { die('-1'); }


if( !class_exists('Tribe_Events_Agenda_Template')){
	class Tribe_Events_Agenda_Template extends Tribe_Template_Factory {

		static $timeslots = array();

		public static function init(){

			add_filter( 'tribe_events_list_the_title', array( __CLASS__, 'the_title' ), 10, 1 );
		
			
			// clear out list hooks
			add_filter( 'tribe_events_list_show_separators', '__return_false' );
			add_filter( 'tribe_events_list_before_the_content', '__return_false' );
			add_filter( 'tribe_events_list_the_content', '__return_false' );
			add_filter( 'tribe_events_list_after_the_content', '__return_false' );
			add_filter( 'tribe_events_list_before_the_meta', '__return_false' );
			add_filter( 'tribe_events_list_the_meta', '__return_false' );
			add_filter( 'tribe_events_list_after_the_meta', '__return_false' );



			add_filter( 'tribe_get_ical_link', array(__CLASS__,'ical_link'), 20, 1 );

			// Override list hooks
			add_filter( 'tribe_events_list_the_event_title', array( __CLASS__, 'the_event_title' ), 20, 1 );
			add_filter( 'tribe_events_list_before_header', array( __CLASS__, 'before_header' ), 20, 1 );
			add_filter( 'tribe_events_list_before_header_nav', array( __CLASS__, 'before_header_nav' ), 20, 1 );
			add_filter( 'tribe_events_list_header_nav', array( __CLASS__, 'header_navigation' ), 20, 1 );
			add_filter( 'tribe_events_list_inside_before_loop', array( __CLASS__, 'inside_before_loop'), 20, 1);
			add_filter( 'tribe_events_list_inside_after_loop', array( __CLASS__, 'inside_after_loop' ), 20, 1 );
			add_filter( 'tribe_events_list_before_footer', array( __CLASS__, 'before_footer' ), 20, 1 );
			add_filter( 'tribe_events_list_before_footer_nav', array( __CLASS__, 'before_footer_nav' ), 20, 1 );
			add_filter( 'tribe_events_list_footer_nav', array( __CLASS__, 'footer_navigation' ), 20, 1 );
		}

		function the_title( $html ){
			global $wp_query;
			$html = sprintf('<h2 class="tribe-events-page-title">%s %s</h2>',
				__('Agenda for ', 'tribe-event-agenda-view'),
				Date("l, F jS Y", strtotime($wp_query->get('start_date')))
				);
			return $html;
		}
		// Event Title
		public static function the_event_title( $html ){
			$event_id = get_the_ID();
			$venue_name = tribe_get_meta( 'tribe_event_venue_name' );
			$venue_address = tribe_get_meta('tribe_event_venue_address');
			$html = sprintf('<h2 class="entry-title summary"><a class="url" href="%s" title="%s" rel="bookmark">%s</a>%s%s%s%s</h2>',
				tribe_get_event_link(),
				get_the_title( $event_id ),
				get_the_title( $event_id ),
				( !empty( $venue_name ) || !empty( $venue_address ) ) ? ' @ ' : '',
				$venue_name,
				( !empty( $venue_name ) && !empty( $venue_address ) ) ? ', ' : '',
				$venue_address
				);
			return $html;
		}
		public static function ical_link( $link ){
			global $wp_query;
			$day = $wp_query->get('start_date');
			return trailingslashit( esc_url(trailingslashit( tribe_get_day_permalink( $day ) ) . 'ical') );
		}
		// Day Header
		public static function before_header( $html ){
			global $wp_query;
			$current_day = $wp_query->get('start_date');
			
			$html = '<div id="tribe-events-header" data-date="'. Date('Y-m-d', strtotime($current_day) ) .'" data-title="'. wp_title( '&raquo;', false ) .'" data-header="'. Date("l, F jS Y", strtotime($wp_query->get('start_date'))) .'">';
			return $html;
		}
		// Day Navigation
		public static function before_header_nav( $html ){
			$html = '<h3 class="tribe-events-visuallyhidden">'. __( 'Day Navigation', 'tribe-events-calendar-pro' ) .'</h3>';
			$html .= '<ul class="tribe-events-sub-nav">';
			return $html;
		}
		public static function header_navigation( $html ){
			$tribe_ecp = TribeEvents::instance();
			global $wp_query;
			
			$current_day = $wp_query->get('start_date');
			$yesterday = Date('Y-m-d', strtotime($current_day . " -1 day") );
			$tomorrow = Date('Y-m-d', strtotime($current_day . " +1 day") );
			
			$html = '';
			
			// Display Previous Page Navigation
			$html .= '<li class="tribe-nav-previous"><a href="'. tribe_get_day_permalink( $yesterday ) .'" data-day="'. $yesterday .'" rel="prev">&larr; '. __( 'Previous Day', 'tribe-events-calendar-pro' ) .'</a></li>';
			
			// Display Next Page Navigation
			$html .= '<li class="tribe-nav-next"><a href="'. tribe_get_day_permalink( $tomorrow ) .'" data-day="'. $tomorrow .'" rel="next">'. __( 'Next Day', 'tribe-events-calendar-pro' ) .' &rarr;</a>';
			// Loading spinner
			$html .= '<img class="tribe-ajax-loading tribe-spinner-medium" src="'. trailingslashit( $tribe_ecp->pluginUrl ) . 'resources/images/tribe-loading.gif" alt="Loading Events" />';
			$html .= '</li><!-- .tribe-nav-next -->';
			
			return $html;
		}
		// Day Before Loop
		public static function inside_before_loop( $pass_through ){
			global $post;

			$html = '';

			// setup the "start time" for the event header
			$start_time = !empty( $post->tribe_is_allday ) && $post->tribe_is_allday ? 
				__( 'All Day', 'tribe-events-calendar' ) :
				tribe_get_start_date( null, false, 'ga ' );

			// determine if we want to open up a new time block
			if( ! in_array( $start_time, self::$timeslots ) ) {

				self::$timeslots[] = $start_time;	

				// close out any prior opened time blocks
				$html .= ( Tribe_Events_List_Template::$loop_increment > 0 ) ? '</div>' : '';

				// open new time block & time vs all day header
				$html .= sprintf( '<div class="tribe-events-day-time-slot"><h5>%s</h5>', $start_time );

			}
			return apply_filters('tribe_template_factory_debug', $html . $pass_through, 'Tribe_Events_Agenda_inside_before_loop');
		}
		// Day Inside After Loop
		public static function inside_after_loop( $pass_through ){
			global $wp_query;

			// close out the last time block
			$html = ( Tribe_Events_List_Template::$loop_increment == count($wp_query->posts) ) ? '</div>' : '';

			return apply_filters('tribe_template_factory_debug', $pass_through . $html, 'Tribe_Events_Agenda_inside_after_loop');
		}
		// Day Footer
		public static function before_footer( $html ){			
			$html = '<div id="tribe-events-footer">';
			return $html;
		}
		// Day Navigation
		public static function before_footer_nav( $html ){
			$html = '<h3 class="tribe-events-visuallyhidden">'. __( 'Day Navigation', 'tribe-events-calendar-pro' ) .'</h3>';
			$html .= '<ul class="tribe-events-sub-nav">';
			return $html;
		}
		public static function footer_navigation( $html ){
			$tribe_ecp = TribeEvents::instance();
			global $wp_query;
			
			$current_day = $wp_query->get('start_date');
			$yesterday = Date('Y-m-d', strtotime($current_day . " -1 day") );
			$tomorrow = Date('Y-m-d', strtotime($current_day . " +1 day") );
			
			$html = '';
			
			// Display Previous Page Navigation
			$html .= '<li class="tribe-nav-previous"><a href="'. tribe_get_day_permalink( $yesterday ) .'" data-day="'. $yesterday .'" rel="prev">&larr; '. __( 'Previous Day', 'tribe-events-calendar-pro' ) .'</a></li>';
			
			// Display Next Page Navigation
			$html .= '<li class="tribe-nav-next"><a href="'. tribe_get_day_permalink( $tomorrow ) .'" data-day="'. $tomorrow .'" rel="next">'. __( 'Next Day', 'tribe-events-calendar-pro' ) .' &rarr;</a>';
			$html .= '</li><!-- .tribe-nav-next -->';
			
			return $html;
		}
	}
	Tribe_Events_Agenda_Template::init();
}