<?php
/**
 * Form Manager.
 *
 * Handles CRUD operations for calculator forms and defines the default
 * Rose Hall Weddings & Events form configuration.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Form_Manager {

	/**
	 * Get all forms.
	 */
	public static function get_all_forms() {
		global $wpdb;
		$table = $wpdb->prefix . 'rhw_forms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
	}

	/**
	 * Get single form by ID.
	 */
	public static function get_form( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rhw_forms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", intval( $id ) ), ARRAY_A );

		if ( $row ) {
			$row['sections']              = json_decode( $row['sections'], true ) ?: array();
			$row['questions']             = json_decode( $row['questions'], true ) ?: array();
			$row['pricing_settings']      = json_decode( $row['pricing_settings'], true ) ?: array();
			$row['notification_settings'] = json_decode( $row['notification_settings'], true ) ?: array();
			$row['appearance_settings']   = json_decode( $row['appearance_settings'], true ) ?: array();
		}

		return $row;
	}

	/**
	 * Save (create or update) form.
	 */
	public static function save_form( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rhw_forms';

		$id = ! empty( $data['id'] ) ? intval( $data['id'] ) : 0;

		$title                 = sanitize_text_field( $data['title'] );
		$description           = sanitize_textarea_field( isset( $data['description'] ) ? $data['description'] : '' );
		$status                = sanitize_text_field( isset( $data['status'] ) ? $data['status'] : 'published' );
		$sections              = is_array( $data['sections'] ) ? wp_json_encode( $data['sections'] ) : $data['sections'];
		$questions             = is_array( $data['questions'] ) ? wp_json_encode( $data['questions'] ) : $data['questions'];
		$pricing_settings      = is_array( $data['pricing_settings'] ) ? wp_json_encode( $data['pricing_settings'] ) : $data['pricing_settings'];
		$notification_settings = is_array( $data['notification_settings'] ) ? wp_json_encode( $data['notification_settings'] ) : $data['notification_settings'];
		$appearance_settings   = is_array( $data['appearance_settings'] ) ? wp_json_encode( $data['appearance_settings'] ) : $data['appearance_settings'];

		$payload = array(
			'title'                 => $title,
			'description'           => $description,
			'status'                => $status,
			'sections'              => $sections,
			'questions'             => $questions,
			'pricing_settings'      => $pricing_settings,
			'notification_settings' => $notification_settings,
			'appearance_settings'   => $appearance_settings,
			'updated_at'            => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update( $table, $payload, array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
			return $id;
		} else {
			$payload['created_at'] = current_time( 'mysql' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, $payload, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete form by ID.
	 */
	public static function delete_form( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rhw_forms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->delete( $table, array( 'id' => intval( $id ) ), array( '%d' ) );
	}

	/**
	 * Duplicate form by ID.
	 */
	public static function duplicate_form( $id ) {
		$form = self::get_form( $id );
		if ( ! $form ) {
			return false;
		}

		$form['id']    = 0;
		$form['title'] = $form['title'] . ' (Copy)';
		return self::save_form( $form );
	}

	/**
	 * Returns full default Rose Hall Weddings form configuration seeded with Google Form questions,
	 * Weekday/Weekend venue pricing, file uploads, and line items.
	 */
	public function get_default_rose_hall_form_data() {
		return array(
			'sections'              => array(
				array(
					'id'          => 'sec_event',
					'title'       => 'Event & Venue Details',
					'description' => 'Tell us about your upcoming event date, guest count, preferred venue, décor, etc.',
					'icon'        => 'calendar',
				),
				array(
					'id'          => 'sec_catering_bar',
					'title'       => 'Catering & Signature Bar',
					'description' => 'Select your desired dining service and signature open bar package.',
					'icon'        => 'glass',
				),
				array(
					'id'          => 'sec_decor_rentals',
					'title'       => 'Décor, Rentals & Flowers',
					'description' => 'Select ceremony chairs, dining linens, tableware, and upload your design inspiration.',
					'icon'        => 'heart',
				),
				array(
					'id'          => 'sec_entertainment',
					'title'       => 'Entertainment & Services',
					'description' => 'Enhance your celebration with professional DJ services, planning, and entertainment.',
					'icon'        => 'music',
				),
				array(
					'id'          => 'sec_attractions',
					'title'       => 'Rose Hall Attractions',
					'description' => 'Exquisite tours and golf experiences at our historic Great Houses.',
					'icon'        => 'star',
				),
				array(
					'id'          => 'sec_contact',
					'title'       => 'Estimate Review & Contact Details',
					'description' => 'Review your preliminary estimate and submit your details to schedule a consultation.',
					'icon'        => 'user',
				),
			),
			'pricing_settings'      => array(
				'currency_symbol'       => '$',
				'currency_position'     => 'before',
				'tax_rate'              => 15.0, // 15% Government Tax from quote
				'service_charge_rate'   => 20.0, // 20% Service Charge on F&B from quote
				'estimate_label'        => 'Estimated Cost',
				'disclaimer_text'       => 'Please note: This amount is an estimated preliminary guide based on selected options and standard venue rates. A 50% deposit will be required to secure your preferred venue. Bespoke elements (custom floral designs, tenting, specialized catering, and transport) are finalized during consultation with our Destination Relationship Specialists. All rates attract government taxes. Prices are subject to change without prior notice.',
				'deposit_percentage'    => 50.0, // 50% deposit required as stated on venue sheet
			),
			'notification_settings' => array(
				'recipient_emails'      => get_option( 'admin_email' ) . ', weddings@rosehall.com',
				'email_subject'         => 'New Wedding Quote & Consultation Request: {customer_name} ({event_date})',
				'sender_name'           => 'Rose Hall Weddings & Events',
				'sender_email'          => get_option( 'admin_email' ),
				'send_customer_receipt' => 1,
			),
			'appearance_settings'   => array(
				'primary_color'    => '#7B112D', // Elegant Rose Hall Wine/Burgundy
				'accent_color'     => '#C5A059', // Elegant Rose Hall Gold/Champagne
				'background_color' => '#FDFBF7', // Luxury Warm Ivory
				'card_background'  => '#FFFFFF',
				'text_color'       => '#2C2523',
				'border_radius'    => '8px',
				'button_style'     => 'pill',
				'custom_css'       => '',
			),
			'questions'             => array(
				// SECTION 1: Event & Venue Details
				array(
					'id'         => 'event_date',
					'section_id' => 'sec_event',
					'label'      => 'Select a Date and Time for your upcoming Wedding / Event',
					'help_text'  => 'Select your preferred date and time. Venue rental rates depend on whether your date falls on a Weekday (Monday–Thursday) or Weekend / Holiday (Friday–Sunday). Suggested ceremony times: 3:30 PM, 4:00 PM, or 4:30 PM for sunset photos.',
					'type'       => 'datetime',
					'required'   => true,
				),
				array(
					'id'             => 'guest_count',
					'section_id'     => 'sec_event',
					'label'          => 'Number of Persons in Attendance',
					'help_text'      => 'Enter expected guest count. This dynamically updates per-person seating, tableware, and bar pricing.',
					'type'           => 'number',
					'required'       => true,
					'is_guest_count' => true,
					'default_value'  => 75,
					'min'            => 1,
					'max'            => 1000,
				),
				array(
					'id'             => 'event_type',
					'section_id'     => 'sec_event',
					'label'          => 'Event Type',
					'type'           => 'radio',
					'required'       => true,
					'has_other_text' => true,
					'options'        => array(
						array( 'label' => 'Wedding (ceremony, cocktail & reception)', 'value' => 'wedding' ),
						array( 'label' => 'Dinner / Gala', 'value' => 'dinner_gala' ),
						array( 'label' => 'Party / Celebration', 'value' => 'party' ),
						array( 'label' => 'Seminar / Workshop', 'value' => 'seminar' ),
						array( 'label' => 'Festival / Concert', 'value' => 'festival' ),
						array( 'label' => 'Fun Day / Retreat', 'value' => 'retreat' ),
						array( 'label' => 'Other', 'value' => 'other' ),
					),
				),
				array(
					'id'             => 'service_type',
					'section_id'     => 'sec_event',
					'label'          => 'What type of service do you require from Rose Hall Weddings?',
					'type'           => 'radio',
					'required'       => true,
					'has_other_text' => true,
					'options'        => array(
						array(
							'label' => 'Full service (includes venue, catering, decor, photography, entertainment & transportation)',
							'value' => 'full_service',
						),
						array(
							'label' => 'Venue Only (does not include tables and chairs or any other service)',
							'value' => 'venue_only',
						),
						array(
							'label' => 'Elopement (Ceremony only-up to 20 guests including Bride & Groom for up to 2 hours)',
							'value' => 'elopement',
						),
						array(
							'label' => 'Other',
							'value' => 'other',
						),
					),
				),
				array(
					'id'          => 'venue_selection',
					'section_id'  => 'sec_event',
					'label'       => 'Which Rose Hall venue or venues are you most interested in?',
					'help_text'   => 'Pricing reflects official rate sheets and adjusts dynamically based on Weekdays vs. Weekends / Holidays. *Ceremony-only venues cannot host receptions.',
					'type'        => 'radio',
					'required'    => true,
					'category'    => 'Venue Rental',
					'options'     => array(
						array(
							'label'   => 'Rose Hall Great House (Weekdays $3,000 | Weekends $3,500)',
							'value'   => 'rose_hall_great_house',
							'pricing' => array(
								'type'           => 'venue',
								'is_venue'       => true,
								'weekday_amount' => 3000.00,
								'weekend_amount' => 3500.00,
								'amount'         => 3500.00,
								'category'       => 'Venue Rental',
							),
						),
						array(
							'label'   => 'White Witch Golf Club (Weekdays $3,000 | Weekends $3,500)',
							'value'   => 'white_witch_golf_club',
							'pricing' => array(
								'type'           => 'venue',
								'is_venue'       => true,
								'weekday_amount' => 3000.00,
								'weekend_amount' => 3500.00,
								'amount'         => 3500.00,
								'category'       => 'Venue Rental',
							),
						),
						array(
							'label'   => 'Cinnamon Hill Great House (Weekdays $3,000 | Weekends $3,500)',
							'value'   => 'cinnamon_hill_great_house',
							'pricing' => array(
								'type'           => 'venue',
								'is_venue'       => true,
								'weekday_amount' => 3000.00,
								'weekend_amount' => 3500.00,
								'amount'         => 3500.00,
								'category'       => 'Venue Rental',
							),
						),
						array(
							'label'   => 'Palms on the Ocean\'s Edge (Weekdays $2,000 | Weekends $2,500)',
							'value'   => 'palms_ocean_edge',
							'pricing' => array(
								'type'           => 'venue',
								'is_venue'       => true,
								'weekday_amount' => 2000.00,
								'weekend_amount' => 2500.00,
								'amount'         => 2500.00,
								'category'       => 'Venue Rental',
							),
						),
						array(
							'label'   => 'The Aqueduct (Ceremony only not able to host reception) (Weekdays $2,000 | Weekends $2,500)',
							'value'   => 'the_aqueduct',
							'pricing' => array(
								'type'           => 'venue',
								'is_venue'       => true,
								'weekday_amount' => 2000.00,
								'weekend_amount' => 2500.00,
								'amount'         => 2500.00,
								'category'       => 'Venue Rental',
							),
						),
						array(
							'label'   => 'Mount Zion Church (Monday - Sunday $1,000)',
							'value'   => 'mount_zion_church',
							'pricing' => array(
								'type'           => 'venue',
								'is_venue'       => true,
								'weekday_amount' => 1000.00,
								'weekend_amount' => 1000.00,
								'amount'         => 1000.00,
								'category'       => 'Venue Rental',
							),
						),
					),
				),
				array(
					'id'             => 'budget_range',
					'section_id'     => 'sec_event',
					'label'          => 'Please choose the range that best depicts your budget for your entire wedding (USD)',
					'type'           => 'radio',
					'required'       => true,
					'has_other_text' => true,
					'options'        => array(
						array( 'label' => 'Under $10,000', 'value' => 'under_10k' ),
						array( 'label' => '$10,000 - $14,999', 'value' => '10k_15k' ),
						array( 'label' => '$15,000 - $19,999', 'value' => '15k_20k' ),
						array( 'label' => '$20,000 - $30,000', 'value' => '20k_30k' ),
						array( 'label' => 'Over $30,000', 'value' => 'over_30k' ),
						array( 'label' => 'Other', 'value' => 'other' ),
					),
				),
				array(
					'id'         => 'wedding_type',
					'section_id' => 'sec_event',
					'label'      => 'Is this a legal or symbolic wedding?',
					'type'       => 'radio',
					'required'   => false,
					'options'    => array(
						array( 'label' => 'Legal', 'value' => 'legal' ),
						array( 'label' => 'Symbolic (does not require wedding certificate)', 'value' => 'symbolic' ),
					),
				),
				array(
					'id'         => 'require_officiant',
					'section_id' => 'sec_event',
					'label'      => 'Please indicate if you require an Officiant for the ceremony:',
					'type'       => 'radio',
					'required'   => false,
					'category'   => 'Planning & Officiant',
					'options'    => array(
						array(
							'label'   => 'Yes - Include Officiant & Marriage Certificate Processing ($550.00)',
							'value'   => 'yes',
							'pricing' => array( 'type' => 'fixed', 'amount' => 550.00, 'category' => 'Planning & Officiant' ),
						),
						array(
							'label'   => 'No - We have our own officiant',
							'value'   => 'no',
							'pricing' => array( 'type' => 'none', 'amount' => 0.00 ),
						),
					),
				),
				array(
					'id'          => 'wedding_vision',
					'section_id'  => 'sec_event',
					'label'       => 'What is your vision for your special day?',
					'type'        => 'textarea',
					'required'    => false,
					'placeholder' => 'Share your theme, color palette, preferred atmosphere, or any special requests...',
				),

				// SECTION 2: Catering & Bar Services
				array(
					'id'                    => 'dinner_style',
					'section_id'            => 'sec_catering_bar',
					'label'                 => 'Would you like a plated or buffet dinner?',
					'help_text'             => 'Plated is recommended for intimate parties (under 30 guests). Buffet is ideal for larger celebrations.',
					'type'                  => 'radio',
					'required'              => false,
					'requires_full_service' => true,
					'options'               => array(
						array( 'label' => 'Buffet (Custom menu curated upon consultation)', 'value' => 'buffet' ),
						array( 'label' => 'Plated (Not recommended for guests count over 30)', 'value' => 'plated' ),
					),
				),
				array(
					'id'                    => 'bar_package',
					'section_id'            => 'sec_catering_bar',
					'label'                 => 'Signature Bar Options',
					'help_text'             => 'Select your preferred open bar tier. All prices include bartender service and attract a 20% service charge.',
					'type'                  => 'radio',
					'required'              => false,
					'requires_full_service' => true,
					'category'              => 'Food & Beverage',
					'options'               => array(
						array(
							'label'   => 'None / Discuss custom bar requirements ($0.00)',
							'value'   => 'none',
							'pricing' => array( 'type' => 'none', 'amount' => 0.00 ),
						),
						array(
							'label'   => 'Basic Bar ($30/person 1st 2 hrs, $13.50/addl hr) - Rum punch, wines, beers, sodas, juices, champagne, water',
							'value'   => 'basic_bar',
							'pricing' => array(
								'type'           => 'bar_package',
								'amount'         => 30.00,
								'addl_hour_rate' => 13.50,
								'is_fb'          => true,
								'category'       => 'Food & Beverage',
							),
						),
						array(
							'label'   => 'Medium Bar ($36/person 1st 2 hrs, $16.50/addl hr) - Spirits (rums, vodka, scotch, gin), champagne, wines, beers, juices',
							'value'   => 'medium_bar',
							'pricing' => array(
								'type'           => 'bar_package',
								'amount'         => 36.00,
								'addl_hour_rate' => 16.50,
								'is_fb'          => true,
								'category'       => 'Food & Beverage',
							),
						),
						array(
							'label'   => 'Premium Bar ($54/person 1st 2 hrs, $22.00/addl hr) - Top shelf (Johnnie Walker, Hennessy, Jack Daniel\'s, Appleton, Beefeater, Grey Goose, Absolut, Tequila)',
							'value'   => 'premium_bar',
							'pricing' => array(
								'type'           => 'bar_package',
								'amount'         => 54.00,
								'addl_hour_rate' => 22.00,
								'is_fb'          => true,
								'category'       => 'Food & Beverage',
							),
						),
					),
				),
				array(
					'id'            => 'bar_duration',
					'section_id'    => 'sec_catering_bar',
					'label'         => 'Bar Service Duration (Hours)',
					'help_text'     => 'Minimum 2 hours. Additional hours calculated per the selected package rate.',
					'type'          => 'number',
					'required'      => false,
					'default_value' => 2,
					'min'           => 2,
					'max'           => 8,
					'conditional'   => array(
						'enabled'  => true,
						'field'    => 'bar_package',
						'operator' => 'not_equals',
						'value'    => 'none',
					),
				),
				array(
					'id'          => 'catering_bar_notes',
					'section_id'  => 'sec_catering_bar',
					'label'       => 'Please indicate which options from our Signature Menu and Bar Options you would prefer:',
					'type'        => 'textarea',
					'required'    => false,
					'placeholder' => 'Hors d\'oeuvres preferences, dietary requirements, champagne toasts, cake wishes...',
				),

				// SECTION 3: Décor, Rentals & Flowers
				array(
					'id'                    => 'include_standard_decor',
					'section_id'            => 'sec_decor_rentals',
					'label'                 => 'Include Standard Ceremony & Reception Rental Package?',
					'help_text'             => 'Includes Gold Chiavari chairs ($7/guest), Signing table ($45), Cocktail bistro tables ($55 ea), Sweetheart table ($60), 10-seater dining tables ($55 ea), Charger plates ($8/guest), Napkins ($5/guest), and Setup/Cleanup ($350).',
					'type'                  => 'radio',
					'required'              => false,
					'requires_full_service' => true,
					'options'               => array(
						array( 'label' => 'Yes - Include standard ceremony and dining rentals based on guest count', 'value' => 'yes' ),
						array( 'label' => 'No - I wish to customize all rental items separately with a coordinator', 'value' => 'no' ),
					),
				),
				array(
					'id'          => 'decor_image',
					'section_id'  => 'sec_decor_rentals',
					'label'       => 'Please attach image of the specific wedding décor design you have in mind',
					'help_text'   => 'Upload image file (JPG, PNG, WEBP, or PDF, max 10MB).',
					'type'        => 'file',
					'accept'      => 'image/*,.pdf',
					'required'    => false,
				),
				array(
					'id'         => 'personal_flowers',
					'section_id' => 'sec_decor_rentals',
					'label'      => 'Personal Flowers',
					'type'       => 'checkboxes',
					'required'   => false,
					'category'   => 'Personal Flowers',
					'options'    => array(
						array(
							'label'   => 'Bridal Bouquet with Matching Boutonniere ($225.00)',
							'value'   => 'bridal_bouquet',
							'pricing' => array( 'type' => 'fixed', 'amount' => 225.00, 'category' => 'Personal Flowers' ),
						),
						array(
							'label'   => 'Bridesmaid Bouquet with Matching Boutonniere ($125.00)',
							'value'   => 'bridesmaid_bouquet',
							'pricing' => array( 'type' => 'fixed', 'amount' => 125.00, 'category' => 'Personal Flowers' ),
						),
					),
				),
				array(
					'id'          => 'bouquet_image',
					'section_id'  => 'sec_decor_rentals',
					'label'       => 'If you already have a bouquet in mind, please attach an image of the same',
					'help_text'   => 'Upload floral inspiration image (JPG, PNG, WEBP, or PDF, max 10MB).',
					'type'        => 'file',
					'accept'      => 'image/*,.pdf',
					'required'    => false,
				),
				array(
					'id'          => 'generator_rental',
					'section_id'  => 'sec_decor_rentals',
					'label'       => 'Power Generator Rental ($885.00)',
					'help_text'   => 'Our venues are historic and outdoor. A heavy-duty generator ensures seamless sound, lighting, and kitchen power.',
					'type'        => 'radio',
					'required'    => false,
					'category'    => 'Equipment & Rentals',
					'options'     => array(
						array(
							'label'   => 'Include Heavy Duty Event Generator ($885.00)',
							'value'   => 'yes',
							'pricing' => array( 'type' => 'fixed', 'amount' => 885.00, 'category' => 'Equipment & Rentals' ),
						),
						array(
							'label'   => 'Not required / Client will provide own certified power source',
							'value'   => 'no',
							'pricing' => array( 'type' => 'none', 'amount' => 0.00 ),
						),
					),
				),
				array(
					'id'          => 'flower_preferences',
					'section_id'  => 'sec_decor_rentals',
					'label'       => 'Floral Preferences & Color Palette',
					'help_text'   => 'Popular Jamaican flowers include tropical dendrobium orchids, ginger lilies, and anthuriums. We can provide almost any variety with sufficient notice.',
					'type'        => 'textarea',
					'required'    => false,
					'placeholder' => 'Describe your preferred floral varieties, bouquets, ceremony arch designs, and centerpieces...',
				),
				array(
					'id'          => 'decor_tent_notes',
					'section_id'  => 'sec_decor_rentals',
					'label'       => 'Tent & Inclement Weather Considerations',
					'help_text'   => 'We recommend booking a tent for outdoor venues. Tents can be quoted and customized to your layout.',
					'type'        => 'textarea',
					'required'    => false,
					'placeholder' => 'Inquire about marquee tents, clear-span tents, string lighting, drapery...',
				),

				// SECTION 4: Entertainment & Services
				array(
					'id'         => 'require_planner',
					'section_id' => 'sec_entertainment',
					'label'      => 'Will you require a Wedding Planner from Rose Hall Weddings?',
					'help_text'  => 'Our dedicated on-site Destination Relationship Specialists manage end-to-end vendor curation, timeline, and day-of execution.',
					'type'       => 'radio',
					'required'   => true,
					'category'   => 'Planning & Officiant',
					'options'    => array(
						array(
							'label'   => 'Yes - Full Destination Wedding Planning & Coordination ($2,500.00)',
							'value'   => 'yes',
							'pricing' => array( 'type' => 'fixed', 'amount' => 2500.00, 'category' => 'Planning & Officiant' ),
						),
						array(
							'label'   => 'No - We already have an independent planner',
							'value'   => 'no',
							'pricing' => array( 'type' => 'none', 'amount' => 0.00 ),
						),
					),
				),
				array(
					'id'         => 'require_mc',
					'section_id' => 'sec_entertainment',
					'label'      => 'Would you like to book a Master of Ceremony (MC)?',
					'type'       => 'radio',
					'required'   => false,
					'category'   => 'Entertainment',
					'options'    => array(
						array(
							'label'   => 'Require Master of Ceremony - Starting cost ($500.00)',
							'value'   => 'yes',
							'pricing' => array( 'type' => 'fixed', 'amount' => 500.00, 'category' => 'Entertainment' ),
						),
						array(
							'label'   => 'No need for Master of Ceremony',
							'value'   => 'no',
							'pricing' => array( 'type' => 'none', 'amount' => 0.00 ),
						),
					),
				),
				array(
					'id'             => 'entertainment_options',
					'section_id'     => 'sec_entertainment',
					'label'          => 'What kind of entertainment do you have in mind? (Select all that apply)',
					'type'           => 'checkboxes',
					'required'       => false,
					'category'       => 'Entertainment',
					'has_other_text' => true,
					'options'        => array(
						array(
							'label'   => 'DJ Services - In House DJ (Ceremony, Cocktail Hour and Reception) ($1,500.00)',
							'value'   => 'dj_services',
							'pricing' => array( 'type' => 'fixed', 'amount' => 1500.00, 'category' => 'Entertainment' ),
						),
						array(
							'label'   => 'Fire Dancer (Starting cost $500.00)',
							'value'   => 'fire_dancer',
							'pricing' => array( 'type' => 'fixed', 'amount' => 500.00, 'category' => 'Entertainment' ),
						),
						array(
							'label'   => 'Violinist (Starting cost $350.00/hour)',
							'value'   => 'violinist',
							'pricing' => array( 'type' => 'fixed', 'amount' => 350.00, 'category' => 'Entertainment' ),
						),
						array(
							'label'   => 'Live Band (Quoted upon discussion)',
							'value'   => 'live_band',
							'pricing' => array( 'type' => 'none', 'amount' => 0.00 ),
						),
						array(
							'label'   => 'Steel Band (Quoted upon discussion)',
							'value'   => 'steel_band',
							'pricing' => array( 'type' => 'none', 'amount' => 0.00 ),
						),
						array(
							'label'   => 'Other Entertainment',
							'value'   => 'other',
							'pricing' => array( 'type' => 'none', 'amount' => 0.00 ),
						),
					),
				),
				array(
					'id'         => 'hotel_stay',
					'section_id' => 'sec_entertainment',
					'label'      => 'Please indicate the hotel that you and your guests will be staying:',
					'type'       => 'select',
					'required'   => false,
					'options'    => array(
						array( 'label' => '-- Select a Resort / Hotel --', 'value' => '' ),
						array( 'label' => 'Half Moon Resort', 'value' => 'half_moon' ),
						array( 'label' => 'Hyatt Ziva / Zilara Rose Hall', 'value' => 'hyatt' ),
						array( 'label' => 'Jewel Grande Montego Bay', 'value' => 'jewel_grande' ),
						array( 'label' => 'Hilton Rose Hall Resort & Spa', 'value' => 'hilton' ),
						array( 'label' => 'Iberostar Rose Hall Beach / Suites / Grand', 'value' => 'iberostar' ),
						array( 'label' => 'Sandals Montego Bay / Royal Caribbean', 'value' => 'sandals' ),
						array( 'label' => 'Secrets / Breathless St. James', 'value' => 'secrets' ),
						array( 'label' => 'Other (Please specify below)', 'value' => 'other' ),
					),
				),
				array(
					'id'          => 'hotel_other',
					'section_id'  => 'sec_entertainment',
					'label'       => 'If other, please state the name of your place of stay:',
					'type'        => 'text',
					'required'    => false,
					'placeholder' => 'Name of resort, villa, or rental',
					'conditional' => array(
						'enabled'  => true,
						'field'    => 'hotel_stay',
						'operator' => 'equals',
						'value'    => 'other',
					),
				),
				array(
					'id'         => 'require_transportation',
					'section_id' => 'sec_entertainment',
					'label'      => 'Will you require transportation to and from your resort to the venue for you and guests on the day of the wedding?',
					'help_text'  => 'Transportation is quoted depending on fleet size (luxury coaches, mini-buses, private shuttles).',
					'type'       => 'radio',
					'required'   => false,
					'options'    => array(
						array( 'label' => 'Yes (Let us quote transportation options)', 'value' => 'yes' ),
						array( 'label' => 'No (Guests will arrange their own transportation)', 'value' => 'no' ),
					),
				),

				// SECTION 5: Rose Hall Attractions
				array(
					'id'         => 'include_attractions',
					'section_id' => 'sec_attractions',
					'label'      => 'Would you like to include Rose Hall Great House tours or Rose Hall Golf in your program?',
					'help_text'  => 'Extra cost. Learn more at https://rosehall.com/',
					'type'       => 'radio',
					'required'   => false,
					'options'    => array(
						array( 'label' => 'Yes - Add tours or golf to our wedding weekend itinerary', 'value' => 'yes' ),
						array( 'label' => 'No', 'value' => 'no' ),
					),
				),
				array(
					'id'          => 'attraction_selection',
					'section_id'  => 'sec_attractions',
					'label'       => 'Which attractions would you like to add?',
					'type'        => 'checkboxes',
					'required'    => false,
					'category'    => 'Attractions & Tours',
					'conditional' => array(
						'enabled'  => true,
						'field'    => 'include_attractions',
						'operator' => 'equals',
						'value'    => 'yes',
					),
					'options'     => array(
						array(
							'label'   => 'Rose Hall Great House: Day & Garden Tour ($30.00/person)',
							'value'   => 'tour_day_garden',
							'pricing' => array( 'type' => 'per_guest', 'amount' => 30.00, 'category' => 'Attractions & Tours' ),
						),
						array(
							'label'   => 'Rose Hall Great House: Haunted Night Tour ($35.00/person)',
							'value'   => 'tour_haunted_night',
							'pricing' => array( 'type' => 'per_guest', 'amount' => 35.00, 'category' => 'Attractions & Tours' ),
						),
						array(
							'label'   => 'Cinnamon Hill Great House Tour ($30.00/person)',
							'value'   => 'tour_cinnamon_hill',
							'pricing' => array( 'type' => 'per_guest', 'amount' => 30.00, 'category' => 'Attractions & Tours' ),
						),
						array(
							'label'   => 'Cinnamon Hill Golf Course ($175.00/person)',
							'value'   => 'golf_cinnamon_hill',
							'pricing' => array( 'type' => 'per_guest', 'amount' => 175.00, 'category' => 'Attractions & Tours' ),
						),
						array(
							'label'   => 'White Witch Golf Course (Open Nov to April) ($175.00/person)',
							'value'   => 'golf_white_witch',
							'pricing' => array( 'type' => 'per_guest', 'amount' => 175.00, 'category' => 'Attractions & Tours' ),
						),
					),
				),
				array(
					'id'            => 'attraction_guests',
					'section_id'    => 'sec_attractions',
					'label'         => 'Number of persons participating in the tour or golfing:',
					'help_text'     => 'Specify how many persons will be taking the tour or golfing (pricing multiplies by this number, not your full wedding guest count).',
					'type'          => 'number',
					'required'      => false,
					'default_value' => 20,
					'min'           => 1,
					'max'           => 500,
					'conditional'   => array(
						'enabled'  => true,
						'field'    => 'include_attractions',
						'operator' => 'equals',
						'value'    => 'yes',
					),
				),
				array(
					'id'          => 'comments_additional',
					'section_id'  => 'sec_attractions',
					'label'       => 'Comments (or additional information)',
					'type'        => 'textarea',
					'required'    => false,
					'placeholder' => 'Any additional questions, timing notes, or special wishes...',
				),

				// SECTION 6: Lead Capture & Contact Details
				array(
					'id'          => 'lead_name',
					'section_id'  => 'sec_contact',
					'label'       => 'Full Name',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => 'e.g. Leaveine Damas',
				),
				array(
					'id'          => 'lead_email',
					'section_id'  => 'sec_contact',
					'label'       => 'Email Address',
					'type'        => 'email',
					'required'    => true,
					'placeholder' => 'your.name@example.com',
				),
				array(
					'id'          => 'lead_phone',
					'section_id'  => 'sec_contact',
					'label'       => 'Contact Number (Phone / WhatsApp)',
					'type'        => 'tel',
					'required'    => true,
					'placeholder' => '+1 (876) 000-0000',
				),
				array(
					'id'          => 'lead_country',
					'section_id'  => 'sec_contact',
					'label'       => 'Country of Residence',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => 'e.g. United States, United Kingdom, Canada, Jamaica',
				),
				array(
					'id'             => 'referral_source',
					'section_id'     => 'sec_contact',
					'label'          => 'How did you hear about Rose Hall Weddings & Events?',
					'type'           => 'select',
					'required'       => true,
					'has_other_text' => true,
					'options'        => array(
						array( 'label' => '-- Select Option --', 'value' => '' ),
						array( 'label' => 'Instagram', 'value' => 'instagram' ),
						array( 'label' => 'Facebook', 'value' => 'facebook' ),
						array( 'label' => 'LinkedIn', 'value' => 'linkedin' ),
						array( 'label' => 'Twitter', 'value' => 'twitter' ),
						array( 'label' => 'Google Ad / Search', 'value' => 'google_ad' ),
						array( 'label' => 'Find Yello (online)', 'value' => 'find_yello' ),
						array( 'label' => 'Wedding Wire / The Knot', 'value' => 'wedding_wire' ),
						array( 'label' => 'Weddings JA', 'value' => 'weddings_ja' ),
						array( 'label' => 'Family / Friend Recommendation', 'value' => 'family_friend' ),
						array( 'label' => 'Wedding Planner / Travel Agent', 'value' => 'planner_agent' ),
						array( 'label' => 'Other', 'value' => 'other' ),
					),
				),
			),
		);
	}
}
