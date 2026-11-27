<?php
/**
 * Authoritative Pricing & Calculation Engine.
 *
 * Used both for server-side recalculation upon submission,
 * and as the baseline schema for real-time frontend calculations.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Pricing_Engine {

	/**
	 * Perform full calculation of an event estimate from form data and user answers.
	 *
	 * @param array $form_data Full form config array (questions, pricing_settings, etc.).
	 * @param array $answers   Associative array of question_id => value.
	 * @return array Calculated breakdown, totals, and metadata.
	 */
	public static function calculate( $form_data, $answers ) {
		$pricing_settings = isset( $form_data['pricing_settings'] ) ? $form_data['pricing_settings'] : array();
		if ( is_string( $pricing_settings ) ) {
			$pricing_settings = json_decode( $pricing_settings, true ) ?: array();
		}

		$tax_rate            = isset( $pricing_settings['tax_rate'] ) ? floatval( $pricing_settings['tax_rate'] ) : 15.0;
		$service_charge_rate = isset( $pricing_settings['service_charge_rate'] ) ? floatval( $pricing_settings['service_charge_rate'] ) : 20.0;
		$currency_symbol     = isset( $pricing_settings['currency_symbol'] ) ? sanitize_text_field( $pricing_settings['currency_symbol'] ) : '$';
		$currency_position   = isset( $pricing_settings['currency_position'] ) ? sanitize_text_field( $pricing_settings['currency_position'] ) : 'before';

		$questions = isset( $form_data['questions'] ) ? $form_data['questions'] : array();
		if ( is_string( $questions ) ) {
			$questions = json_decode( $questions, true ) ?: array();
		}

		// 1. Determine Guest Count
		$guest_count = 75; // Default reference from proposal
		foreach ( $questions as $q ) {
			if ( ! empty( $q['is_guest_count'] ) || ( isset( $q['id'] ) && 'guest_count' === $q['id'] ) ) {
				$qid = $q['id'];
				if ( isset( $answers[ $qid ] ) && is_numeric( $answers[ $qid ] ) && intval( $answers[ $qid ] ) > 0 ) {
					$guest_count = intval( $answers[ $qid ] );
				}
				break;
			}
		}

		// 2. Determine Day Type (Weekday vs Weekend / Holiday) from event_date
		$is_weekend = false;
		if ( ! empty( $answers['event_date'] ) ) {
			$date_str   = sanitize_text_field( $answers['event_date'] );
			$date_clean = trim( explode( ' at ', $date_str )[0] );
			$date_clean = trim( explode( 'T', $date_clean )[0] );
			$timestamp  = strtotime( $date_clean );
			if ( $timestamp ) {
				$day_of_week = (int) date( 'N', $timestamp ); // 1 (Mon) to 7 (Sun)
				// Friday (5), Saturday (6), Sunday (7) are weekend rates in luxury venue hospitality
				if ( $day_of_week >= 5 ) {
					$is_weekend = true;
				}
			}
		}
		if ( isset( $answers['day_type'] ) ) {
			$is_weekend = ( 'weekend' === $answers['day_type'] );
		}

		// Check service type early for macro-skips
		$service_type = '';
		foreach ( $questions as $q ) {
			if ( isset( $q['id'] ) && 'service_type' === $q['id'] && isset( $answers['service_type'] ) ) {
				$service_type = sanitize_text_field( $answers['service_type'] );
				break;
			}
		}
		$is_venue_only = ( 'venue_only' === $service_type || stripos( $service_type, 'Venue Only' ) !== false );

		$questions_by_id = array();
		foreach ( $questions as $q ) {
			if ( ! empty( $q['id'] ) ) {
				$questions_by_id[ $q['id'] ] = $q;
			}
		}

		$line_items  = array();
		$subtotal    = 0.0;
		$fb_subtotal = 0.0;

		// 3. Evaluate each question and its pricing rules
		foreach ( $questions as $q ) {
			$qid = isset( $q['id'] ) ? $q['id'] : '';
			if ( empty( $qid ) ) {
				continue;
			}

			// Check conditional visibility (including chained parents)
			if ( ! self::is_question_visible( $q, $answers, $questions_by_id ) ) {
				continue;
			}

			// If Venue Only is selected, skip decor/catering/entertainment questions unless explicitly allowed
			if ( $is_venue_only && ! empty( $q['requires_full_service'] ) ) {
				continue;
			}

			$val = isset( $answers[ $qid ] ) ? $answers[ $qid ] : null;
			if ( null === $val || '' === $val ) {
				continue;
			}

			$q_type = isset( $q['type'] ) ? $q['type'] : 'text';

			// Case A: Radio or Select
			if ( in_array( $q_type, array( 'radio', 'select' ), true ) ) {
				$selected_val = is_string( $val ) ? trim( $val ) : '';
				if ( ! empty( $q['options'] ) && is_array( $q['options'] ) ) {
					foreach ( $q['options'] as $opt ) {
						$opt_val = isset( $opt['value'] ) ? $opt['value'] : ( isset( $opt['label'] ) ? $opt['label'] : '' );
						if ( $opt_val === $selected_val ) {
							$item = self::evaluate_option_pricing( $opt, $guest_count, $answers, $q, $is_weekend );
							if ( $item && $item['amount'] > 0 ) {
								$line_items[] = $item;
								$subtotal    += $item['amount'];
								if ( ! empty( $item['is_fb'] ) ) {
									$fb_subtotal += $item['amount'];
								}
							}
							break;
						}
					}
				}
			}

			// Case B: Checkboxes (multiple selections)
			elseif ( 'checkbox' === $q_type || 'checkboxes' === $q_type ) {
				$selected_vals = is_array( $val ) ? $val : array( $val );
				if ( ! empty( $q['options'] ) && is_array( $q['options'] ) ) {
					foreach ( $q['options'] as $opt ) {
						$opt_val = isset( $opt['value'] ) ? $opt['value'] : ( isset( $opt['label'] ) ? $opt['label'] : '' );
						if ( in_array( $opt_val, $selected_vals, true ) ) {
							$item = self::evaluate_option_pricing( $opt, $guest_count, $answers, $q, $is_weekend );
							if ( $item && $item['amount'] > 0 ) {
								$line_items[] = $item;
								$subtotal    += $item['amount'];
								if ( ! empty( $item['is_fb'] ) ) {
									$fb_subtotal += $item['amount'];
								}
							}
						}
					}
				}
			}

			// Case C: Numeric inputs with formula or unit price
			elseif ( 'number' === $q_type && is_numeric( $val ) ) {
				$num_val = floatval( $val );
				if ( $num_val > 0 && ! empty( $q['unit_price'] ) && floatval( $q['unit_price'] ) > 0 ) {
					$unit_price = floatval( $q['unit_price'] );
					$cost       = $num_val * $unit_price;
					$is_fb      = ! empty( $q['is_fb'] );
					$item       = array(
						'key'        => $qid,
						'title'      => isset( $q['label'] ) ? $q['label'] : 'Custom Item',
						'category'   => isset( $q['category'] ) ? $q['category'] : 'Miscellaneous',
						'quantity'   => $num_val,
						'unit_price' => $unit_price,
						'amount'     => $cost,
						'is_fb'      => $is_fb,
						'note'       => sprintf( '%s × %s', $num_val, self::format_money( $unit_price, $currency_symbol, $currency_position ) ),
					);
					$line_items[] = $item;
					$subtotal    += $cost;
					if ( $is_fb ) {
						$fb_subtotal += $cost;
					}
				}
			}
		}

		// 4. Automated Décor & Rental Requirements based on user opt-in (Item 3)
		if ( ! $is_venue_only ) {
			self::apply_standard_full_service_rentals( $answers, $guest_count, $line_items, $subtotal, $currency_symbol, $currency_position, $service_type );
		}

		// 5. Calculate Taxes and Service Charges
		// 20% Service charge on Food & Beverage, 15% Tax on Subtotal
		$fb_service_charge = ( $fb_subtotal > 0 && $service_charge_rate > 0 ) ? round( ( $fb_subtotal * $service_charge_rate ) / 100, 2 ) : 0.00;
		$tax_amount        = ( $subtotal > 0 && $tax_rate > 0 ) ? round( ( $subtotal * $tax_rate ) / 100, 2 ) : 0.00;
		$grand_total       = round( $subtotal + $fb_service_charge + $tax_amount, 2 );

		return array(
			'guest_count'         => $guest_count,
			'is_weekend'          => $is_weekend,
			'line_items'          => $line_items,
			'subtotal'            => round( $subtotal, 2 ),
			'fb_subtotal'         => round( $fb_subtotal, 2 ),
			'service_charge_rate' => $service_charge_rate,
			'service_charge'      => $fb_service_charge,
			'tax_rate'            => $tax_rate,
			'tax_amount'          => $tax_amount,
			'grand_total'         => $grand_total,
			'currency_symbol'     => $currency_symbol,
			'currency_position'   => $currency_position,
			'formatted'           => array(
				'subtotal'       => self::format_money( $subtotal, $currency_symbol, $currency_position ),
				'fb_subtotal'    => self::format_money( $fb_subtotal, $currency_symbol, $currency_position ),
				'service_charge' => self::format_money( $fb_service_charge, $currency_symbol, $currency_position ),
				'tax_amount'     => self::format_money( $tax_amount, $currency_symbol, $currency_position ),
				'grand_total'    => self::format_money( $grand_total, $currency_symbol, $currency_position ),
			),
		);
	}

	/**
	 * Evaluate pricing for a single selected option with Weekday/Weekend support.
	 */
	private static function evaluate_option_pricing( $opt, $guest_count, $answers, $question, $is_weekend = false ) {
		if ( ! isset( $opt['pricing'] ) || ! is_array( $opt['pricing'] ) ) {
			return null;
		}

		$pricing = $opt['pricing'];
		$type    = isset( $pricing['type'] ) ? $pricing['type'] : 'none';

		// Determine base amount with Weekday / Weekend support
		$amount = isset( $pricing['amount'] ) ? floatval( $pricing['amount'] ) : 0.0;
		if ( ! empty( $pricing['is_venue'] ) || 'venue' === $type || ! empty( $pricing['weekend_amount'] ) ) {
			if ( $is_weekend && isset( $pricing['weekend_amount'] ) && floatval( $pricing['weekend_amount'] ) > 0 ) {
				$amount = floatval( $pricing['weekend_amount'] );
			} elseif ( ! $is_weekend && isset( $pricing['weekday_amount'] ) && floatval( $pricing['weekday_amount'] ) > 0 ) {
				$amount = floatval( $pricing['weekday_amount'] );
			}
		}

		$label = isset( $opt['label'] ) ? $opt['label'] : ( isset( $opt['value'] ) ? $opt['value'] : '' );

		if ( 'none' === $type || $amount <= 0.0 ) {
			return null;
		}

		$category   = isset( $pricing['category'] ) ? $pricing['category'] : ( isset( $question['category'] ) ? $question['category'] : 'General' );
		$is_fb      = ! empty( $pricing['is_fb'] );
		$qty        = 1;
		$unit_price = $amount;
		$total      = 0.0;
		$note       = '';

		switch ( $type ) {
			case 'venue':
			case 'fixed':
				$qty   = 1;
				$total = $amount;
				$note  = $is_weekend ? 'Weekend/Holiday Rate' : 'Weekday Rate';
				break;

			case 'per_guest':
				// Check if this is an attraction tour where custom participant count is specified (up to Section 1 guest count)!
				$multiplier = $guest_count;
				if ( isset( $question['id'] ) && 'attraction_selection' === $question['id'] ) {
					if ( isset( $answers['attraction_guests'] ) && is_numeric( $answers['attraction_guests'] ) && intval( $answers['attraction_guests'] ) > 0 ) {
						$multiplier = min( intval( $answers['attraction_guests'] ), $guest_count );
					}
				}
				$qty   = $multiplier;
				$total = $amount * $multiplier;
				$note  = sprintf( '%d persons × $%s', $multiplier, number_format( $amount, 2 ) );
				break;

			case 'table_ratio_10':
				$qty   = (int) ceil( $guest_count / 10 );
				$total = $qty * $amount;
				$note  = sprintf( '%d tables (10-seater) × $%s', $qty, number_format( $amount, 2 ) );
				break;

			case 'table_ratio_15':
				$qty   = (int) ceil( $guest_count / 15 );
				$total = $qty * $amount;
				$note  = sprintf( '%d bistro tables × $%s', $qty, number_format( $amount, 2 ) );
				break;

			case 'bar_package':
				$duration = 2;
				if ( isset( $answers['bar_duration'] ) && is_numeric( $answers['bar_duration'] ) ) {
					$duration = max( 2, intval( $answers['bar_duration'] ) );
				}
				$base_2hr_rate  = $amount;
				$addl_hour_rate = isset( $pricing['addl_hour_rate'] ) ? floatval( $pricing['addl_hour_rate'] ) : 0.0;
				$addl_hours     = max( 0, $duration - 2 );

				$per_person_cost = $base_2hr_rate + ( $addl_hours * $addl_hour_rate );
				$qty             = $guest_count;
				$unit_price      = $per_person_cost;
				$total           = $per_person_cost * $guest_count;
				$is_fb           = true;
				$note            = sprintf( '%d guests × %d hrs ($%s/person)', $guest_count, $duration, number_format( $per_person_cost, 2 ) );
				break;

			default:
				$qty   = 1;
				$total = $amount;
				break;
		}

		return array(
			'key'        => isset( $opt['value'] ) ? $opt['value'] : sanitize_title( $label ),
			'title'      => $label,
			'category'   => $category,
			'quantity'   => $qty,
			'unit_price' => $unit_price,
			'amount'     => round( $total, 2 ),
			'is_fb'      => $is_fb,
			'note'       => $note,
		);
	}

	/**
	 * Apply standard decor & rental items for full service weddings as quoted in the Rose Hall standard proposal.
	 */
	private static function apply_standard_full_service_rentals( $answers, $guest_count, &$line_items, &$subtotal, $symbol, $pos, $service_type = '' ) {
		// Only include when explicitly selected as 'yes' (Item 3)
		$include_decor = isset( $answers['include_standard_decor'] ) && ( 'yes' === $answers['include_standard_decor'] || '1' === (string) $answers['include_standard_decor'] );

		if ( ! $include_decor ) {
			return;
		}

		$is_elopement  = ( 'elopement' === $service_type || false !== stripos( (string) $service_type, 'elopement' ) );
		$existing_keys = wp_list_pluck( $line_items, 'key' );

		// 1. Gold Chiavari Chairs / Ivory Cushions ($7.00 per guest)
		if ( ! in_array( 'gold_chiavari_chairs', $existing_keys, true ) ) {
			$cost          = $guest_count * 7.00;
			$line_items[]  = array(
				'key'        => 'gold_chiavari_chairs',
				'title'      => 'Gold Chiavari Chairs with Ivory Cushions',
				'category'   => 'Décor & Rentals',
				'quantity'   => $guest_count,
				'unit_price' => 7.00,
				'amount'     => $cost,
				'is_fb'      => false,
				'note'       => sprintf( '%d chairs × %s', $guest_count, self::format_money( 7.00, $symbol, $pos ) ),
			);
			$subtotal     += $cost;
		}

		// 2. Signing Table & Linen ($45.00 flat)
		if ( ! in_array( 'signing_table', $existing_keys, true ) ) {
			$line_items[] = array(
				'key'        => 'signing_table',
				'title'      => 'Ceremony Signing Table with Linen',
				'category'   => 'Décor & Rentals',
				'quantity'   => 1,
				'unit_price' => 45.00,
				'amount'     => 45.00,
				'is_fb'      => false,
				'note'       => 'Flat fee',
			);
			$subtotal    += 45.00;
		}

		// For Elopement (Ceremony only up to 20 guests for 2 hrs): do NOT add dining reception tables/settings
		if ( $is_elopement ) {
			return;
		}

		// 3. Cocktail Bistro Tables with Linen ($55.00 each, 1 per 15 guests)
		if ( ! in_array( 'bistro_tables', $existing_keys, true ) ) {
			$qty          = (int) ceil( $guest_count / 15 );
			$cost         = $qty * 55.00;
			$line_items[] = array(
				'key'        => 'bistro_tables',
				'title'      => 'Cocktail Bistro Tables with Ivory Linen',
				'category'   => 'Décor & Rentals',
				'quantity'   => $qty,
				'unit_price' => 55.00,
				'amount'     => $cost,
				'is_fb'      => false,
				'note'       => sprintf( '%d tables × %s', $qty, self::format_money( 55.00, $symbol, $pos ) ),
			);
			$subtotal    += $cost;
		}

		// 4. Sweetheart Table with Linen ($60.00 flat)
		if ( ! in_array( 'sweetheart_table', $existing_keys, true ) ) {
			$line_items[] = array(
				'key'        => 'sweetheart_table',
				'title'      => 'Sweetheart Table with Linen',
				'category'   => 'Décor & Rentals',
				'quantity'   => 1,
				'unit_price' => 60.00,
				'amount'     => 60.00,
				'is_fb'      => false,
				'note'       => 'Flat fee',
			);
			$subtotal    += 60.00;
		}

		// 5. Dining Tables (10-seater rounds or trestles @ $55.00 each)
		if ( ! in_array( 'dining_tables', $existing_keys, true ) ) {
			$qty          = (int) ceil( $guest_count / 10 );
			$cost         = $qty * 55.00;
			$line_items[] = array(
				'key'        => 'dining_tables',
				'title'      => '10-Seater Rounds or Trestle Tables with Linen',
				'category'   => 'Décor & Rentals',
				'quantity'   => $qty,
				'unit_price' => 55.00,
				'amount'     => $cost,
				'is_fb'      => false,
				'note'       => sprintf( '%d tables × %s', $qty, self::format_money( 55.00, $symbol, $pos ) ),
			);
			$subtotal    += $cost;
		}

		// 6. Charger Plates ($8.00 per guest)
		if ( ! in_array( 'charger_plates', $existing_keys, true ) ) {
			$cost         = $guest_count * 8.00;
			$line_items[] = array(
				'key'        => 'charger_plates',
				'title'      => 'Charger Plates',
				'category'   => 'Décor & Rentals',
				'quantity'   => $guest_count,
				'unit_price' => 8.00,
				'amount'     => $cost,
				'is_fb'      => false,
				'note'       => sprintf( '%d place settings × %s', $guest_count, self::format_money( 8.00, $symbol, $pos ) ),
			);
			$subtotal    += $cost;
		}

		// 7. Napkins ($5.00 per guest)
		if ( ! in_array( 'napkins', $existing_keys, true ) ) {
			$cost         = $guest_count * 5.00;
			$line_items[] = array(
				'key'        => 'napkins',
				'title'      => 'Dinner Napkins',
				'category'   => 'Décor & Rentals',
				'quantity'   => $guest_count,
				'unit_price' => 5.00,
				'amount'     => $cost,
				'is_fb'      => false,
				'note'       => sprintf( '%d napkins × %s', $guest_count, self::format_money( 5.00, $symbol, $pos ) ),
			);
			$subtotal    += $cost;
		}

		// 8. Setup, Breakdown and Cleanup Fee ($350.00 standard on quote)
		if ( ! in_array( 'setup_cleanup_fee', $existing_keys, true ) ) {
			$line_items[] = array(
				'key'        => 'setup_cleanup_fee',
				'title'      => 'Setup, Breakdown and Clean-up Service',
				'category'   => 'Planning & Service',
				'quantity'   => 1,
				'unit_price' => 350.00,
				'amount'     => 350.00,
				'is_fb'      => false,
				'note'       => 'Standard invoice fee',
			);
			$subtotal    += 350.00;
		}
	}

	/**
	 * Check if a question is visible based on conditional logic (including parent chain).
	 */
	public static function is_question_visible( $question, $answers, $questions_by_id = array(), $visited = array() ) {
		if ( empty( $question['conditional'] ) || empty( $question['conditional']['enabled'] ) ) {
			return true;
		}

		$qid = isset( $question['id'] ) ? $question['id'] : '';
		if ( ! empty( $qid ) ) {
			if ( isset( $visited[ $qid ] ) ) {
				return true; // Prevent cycle infinite loop
			}
			$visited[ $qid ] = true;
		}

		$condition = $question['conditional'];
		$target_id = isset( $condition['field'] ) ? $condition['field'] : '';
		$operator  = isset( $condition['operator'] ) ? $condition['operator'] : 'equals';
		$expected  = isset( $condition['value'] ) ? $condition['value'] : '';

		if ( empty( $target_id ) ) {
			return false;
		}

		// Ensure parent question itself is visible
		if ( ! empty( $questions_by_id[ $target_id ] ) ) {
			if ( ! self::is_question_visible( $questions_by_id[ $target_id ], $answers, $questions_by_id, $visited ) ) {
				return false;
			}
		}

		if ( ! isset( $answers[ $target_id ] ) ) {
			return false;
		}

		$actual = $answers[ $target_id ];

		switch ( $operator ) {
			case 'equals':
				return (string) $actual === (string) $expected;

			case 'not_equals':
				return (string) $actual !== (string) $expected;

			case 'contains':
				if ( is_array( $actual ) ) {
					return in_array( $expected, $actual, true );
				}
				return stripos( (string) $actual, (string) $expected ) !== false;

			case 'not_empty':
				if ( is_array( $actual ) ) {
					$filtered = array_filter(
						$actual,
						function( $item ) {
							return '' !== trim( (string) $item );
						}
					);
					return ! empty( $filtered );
				}
				return '' !== trim( (string) $actual );

			case 'greater_than':
				return floatval( $actual ) > floatval( $expected );

			default:
				return true;
		}
	}

	/**
	 * Helper to format currency string.
	 */
	public static function format_money( $amount, $symbol = '$', $position = 'before' ) {
		$formatted_number = number_format( (float) $amount, 2 );
		if ( 'after' === $position ) {
			return $formatted_number . ' ' . $symbol;
		}
		return $symbol . $formatted_number;
	}
}
