<?php
/**
 * Consultation Calendar Schedule, Slot Interval Engine, and Blocking Manager.
 *
 * Manages configurable time intervals (default 1 hour), per-day-of-week open hours,
 * individual slot blocking, whole-day blocking, and live Google Calendar busy checks.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Calendar_Manager {

	const OPTION_SCHEDULE = 'rhw_calendar_settings';

	/**
	 * Return default weekly schedule (Mon-Sat 9:00 AM - 5:00 PM, Sun closed).
	 */
	public static function get_default_weekly_hours() {
		$days = array(
			'monday'    => array( 'label' => __( 'Monday', 'rhw-calculator' ), 'enabled' => 1, 'start' => '09:00', 'end' => '17:00' ),
			'tuesday'   => array( 'label' => __( 'Tuesday', 'rhw-calculator' ), 'enabled' => 1, 'start' => '09:00', 'end' => '17:00' ),
			'wednesday' => array( 'label' => __( 'Wednesday', 'rhw-calculator' ), 'enabled' => 1, 'start' => '09:00', 'end' => '17:00' ),
			'thursday'  => array( 'label' => __( 'Thursday', 'rhw-calculator' ), 'enabled' => 1, 'start' => '09:00', 'end' => '17:00' ),
			'friday'    => array( 'label' => __( 'Friday', 'rhw-calculator' ), 'enabled' => 1, 'start' => '09:00', 'end' => '17:00' ),
			'saturday'  => array( 'label' => __( 'Saturday', 'rhw-calculator' ), 'enabled' => 1, 'start' => '09:00', 'end' => '17:00' ),
			'sunday'    => array( 'label' => __( 'Sunday', 'rhw-calculator' ), 'enabled' => 0, 'start' => '09:00', 'end' => '17:00' ),
		);
		return $days;
	}

	/**
	 * Retrieve calendar schedule settings.
	 */
	public static function get_settings() {
		$wp_tz      = get_option( 'timezone_string' );
		$default_tz = ! empty( $wp_tz ) ? $wp_tz : 'America/Jamaica';

		$defaults = array(
			'enabled'          => 1,
			'required'         => 0,
			'interval_minutes' => 60, // Default 1-hour slots
			'buffer_minutes'   => 0,
			'min_notice_hours' => 24,
			'max_days_ahead'   => 180,
			'timezone'         => $default_tz,
			'weekly_hours'     => self::get_default_weekly_hours(),
		);

		$saved = get_option( self::OPTION_SCHEDULE, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $defaults );

		// Ensure all 7 days exist cleanly
		$default_hours = self::get_default_weekly_hours();
		if ( ! is_array( $settings['weekly_hours'] ) ) {
			$settings['weekly_hours'] = $default_hours;
		} else {
			foreach ( $default_hours as $day_key => $day_def ) {
				if ( ! isset( $settings['weekly_hours'][ $day_key ] ) || ! is_array( $settings['weekly_hours'][ $day_key ] ) ) {
					$settings['weekly_hours'][ $day_key ] = $day_def;
				} else {
					$settings['weekly_hours'][ $day_key ] = wp_parse_args( $settings['weekly_hours'][ $day_key ], $day_def );
					$settings['weekly_hours'][ $day_key ]['label'] = $day_def['label'];
				}
			}
		}

		return $settings;
	}

	/**
	 * Save calendar schedule settings from admin POST data.
	 */
	public static function save_settings( $post ) {
		$defaults = self::get_default_weekly_hours();
		$weekly   = array();

		foreach ( $defaults as $day_key => $day_def ) {
			$raw_day = isset( $post['weekly_hours'][ $day_key ] ) ? (array) $post['weekly_hours'][ $day_key ] : array();
			$start   = isset( $raw_day['start'] ) && preg_match( '/^\d{2}:\d{2}$/', $raw_day['start'] ) ? $raw_day['start'] : $day_def['start'];
			$end     = isset( $raw_day['end'] ) && preg_match( '/^\d{2}:\d{2}$/', $raw_day['end'] ) ? $raw_day['end'] : $day_def['end'];

			$weekly[ $day_key ] = array(
				'enabled' => ! empty( $raw_day['enabled'] ) ? 1 : 0,
				'start'   => $start,
				'end'     => $end,
			);
		}

		$interval = isset( $post['interval_minutes'] ) ? intval( $post['interval_minutes'] ) : 60;
		if ( $interval < 15 || $interval > 240 ) {
			$interval = 60;
		}

		$buffer = isset( $post['buffer_minutes'] ) ? max( 0, min( 120, intval( $post['buffer_minutes'] ) ) ) : 0;
		$notice = isset( $post['min_notice_hours'] ) ? max( 0, min( 720, intval( $post['min_notice_hours'] ) ) ) : 24;
		$ahead  = isset( $post['max_days_ahead'] ) ? max( 7, min( 730, intval( $post['max_days_ahead'] ) ) ) : 180;
		$tz     = ! empty( $post['timezone'] ) ? sanitize_text_field( wp_unslash( $post['timezone'] ) ) : 'America/Jamaica';

		// Validate timezone string
		try {
			new DateTimeZone( $tz );
		} catch ( Exception $e ) {
			$tz = 'America/Jamaica';
		}

		$updated = array(
			'enabled'          => ! empty( $post['calendar_enabled'] ) ? 1 : 0,
			'required'         => ! empty( $post['calendar_required'] ) ? 1 : 0,
			'interval_minutes' => $interval,
			'buffer_minutes'   => $buffer,
			'min_notice_hours' => $notice,
			'max_days_ahead'   => $ahead,
			'timezone'         => $tz,
			'weekly_hours'     => $weekly,
		);

		update_option( self::OPTION_SCHEDULE, $updated );
		RHW_Google_Calendar::clear_busy_cache();

		return self::get_settings();
	}

	/**
	 * Return valid DateTimeZone for calendar calculations.
	 */
	public static function get_timezone( $settings = null ) {
		if ( null === $settings ) {
			$settings = self::get_settings();
		}
		try {
			return new DateTimeZone( $settings['timezone'] );
		} catch ( Exception $e ) {
			return wp_timezone();
		}
	}

	/**
	 * Build raw slot intervals [start_ts, end_ts, time_label, end_label] for a date based on settings.
	 */
	private static function build_day_template_slots( $date_ymd, $settings, $tz ) {
		$day_dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_ymd, $tz );
		if ( ! $day_dt ) {
			return array( 'enabled' => false, 'day_name' => '', 'slots' => array() );
		}

		$day_key  = strtolower( $day_dt->format( 'l' ) ); // e.g. 'monday'
		$day_name = $day_dt->format( 'l' );
		$day_cfg  = isset( $settings['weekly_hours'][ $day_key ] ) ? $settings['weekly_hours'][ $day_key ] : null;

		if ( ! $day_cfg || empty( $day_cfg['enabled'] ) ) {
			return array(
				'enabled'  => false,
				'day_name' => $day_name,
				'slots'    => array(),
			);
		}

		$start_str = ! empty( $day_cfg['start'] ) ? $day_cfg['start'] : '09:00';
		$end_str   = ! empty( $day_cfg['end'] ) ? $day_cfg['end'] : '17:00';

		$start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date_ymd . ' ' . $start_str, $tz );
		$end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date_ymd . ' ' . $end_str, $tz );

		if ( ! $start_dt || ! $end_dt || $end_dt <= $start_dt ) {
			return array(
				'enabled'  => false,
				'day_name' => $day_name,
				'slots'    => array(),
			);
		}

		$interval_secs = max( 15, intval( $settings['interval_minutes'] ) ) * 60;
		$buffer_secs   = max( 0, intval( $settings['buffer_minutes'] ) ) * 60;
		$step_secs     = $interval_secs + $buffer_secs;

		$start_ts = $start_dt->getTimestamp();
		$end_ts   = $end_dt->getTimestamp();
		$slots    = array();

		for ( $cursor = $start_ts; ( $cursor + $interval_secs ) <= $end_ts; $cursor += $step_secs ) {
			$s_dt = ( new DateTimeImmutable( '@' . $cursor ) )->setTimezone( $tz );
			$e_dt = ( new DateTimeImmutable( '@' . ( $cursor + $interval_secs ) ) )->setTimezone( $tz );

			$slots[] = array(
				'start_ts'    => $cursor,
				'end_ts'      => $cursor + $interval_secs,
				'time'        => $s_dt->format( 'h:i A' ),
				'end_time'    => $e_dt->format( 'h:i A' ),
				'range_label' => $s_dt->format( 'g:i A' ) . ' – ' . $e_dt->format( 'g:i A' ),
				'short_label' => $s_dt->format( 'g:i A' ),
			);
		}

		return array(
			'enabled'  => true,
			'day_name' => $day_name,
			'slots'    => $slots,
		);
	}

	/**
	 * Fetch full day slots status (with DB bookings, manual blocks, and Google Calendar FreeBusy sync).
	 *
	 * @param string $date_ymd Date in Y-m-d format.
	 * @param bool   $is_admin Whether caller is privileged WP Admin (reveals booking details & past view).
	 * @return array Structured slot availability data.
	 */
	public static function get_day_slots( $date_ymd, $is_admin = false ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_ymd ) ) {
			return array(
				'date'            => $date_ymd,
				'day_name'        => '',
				'day_enabled'     => false,
				'all_day_blocked' => false,
				'slots'           => array(),
				'available_count' => 0,
				'message'         => __( 'Invalid date provided.', 'rhw-calculator' ),
			);
		}

		$settings = self::get_settings();
		$tz       = self::get_timezone( $settings );
		$now_ts   = time();

		$min_bookable_ts = $now_ts + ( intval( $settings['min_notice_hours'] ) * 3600 );
		$max_bookable_ts = $now_ts + ( intval( $settings['max_days_ahead'] ) * 86400 );

		$template = self::build_day_template_slots( $date_ymd, $settings, $tz );

		// Fetch DB bookings for this date
		global $wpdb;
		RHW_Activator::ensure_bookings_table_exists();
		$table = $wpdb->prefix . 'rhw_bookings';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$db_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE booking_date = %s AND status IN ('confirmed', 'blocked') ORDER BY id ASC",
				$date_ymd
			)
		);

		$all_day_booking = null;
		$parsed_rows     = array();
		$interval_secs   = max( 15, intval( $settings['interval_minutes'] ) ) * 60;

		foreach ( (array) $db_rows as $row ) {
			if ( 'all_day' === $row->booking_time ) {
				$all_day_booking = $row;
				continue;
			}
			$row_ts = self::parse_booking_time_ts( $date_ymd, $row->booking_time, $tz );
			$parsed_rows[] = array(
				'row'      => $row,
				'start_ts' => $row_ts,
				'end_ts'   => $row_ts ? ( $row_ts + $interval_secs ) : 0,
			);
		}

		// Fetch Google Calendar busy intervals for this day
		$day_start_dt   = new DateTimeImmutable( $date_ymd . ' 00:00:00', $tz );
		$day_end_dt     = new DateTimeImmutable( $date_ymd . ' 23:59:59', $tz );
		$busy_intervals = RHW_Google_Calendar::get_busy_intervals(
			$day_start_dt->format( DATE_RFC3339 ),
			$day_end_dt->format( DATE_RFC3339 ),
			$tz->getName()
		);

		$all_day_blocked = ( null !== $all_day_booking );
		$result_slots    = array();
		$available_count = 0;

		foreach ( $template['slots'] as $slot ) {
			$status      = 'available';
			$available   = true;
			$matched_row = null;

			if ( $all_day_blocked ) {
				$available   = false;
				$status      = 'blocked_admin';
				$matched_row = $all_day_booking;
			} elseif ( ! $is_admin && ( $slot['start_ts'] < $min_bookable_ts || $slot['start_ts'] > $max_bookable_ts ) ) {
				$available = false;
				$status    = 'past';
			}

			// Check local DB rows
			if ( $available || $is_admin ) {
				foreach ( $parsed_rows as $pr ) {
					$exact_match = ( strtolower( trim( $pr['row']->booking_time ) ) === strtolower( trim( $slot['time'] ) ) );
					$overlap     = ( $pr['start_ts'] && $slot['start_ts'] < $pr['end_ts'] && $slot['end_ts'] > $pr['start_ts'] );
					if ( $exact_match || $overlap ) {
						$matched_row = $pr['row'];
						$available   = false;
						$status      = ( 'blocked' === $pr['row']->status || 'blocked_admin' === $pr['row']->booking_type ) ? 'blocked_admin' : 'booked';
						break;
					}
				}
			}

			// Check Google Calendar FreeBusy conflicts
			if ( $available ) {
				foreach ( (array) $busy_intervals as $busy ) {
					if ( $slot['start_ts'] < $busy['end'] && $slot['end_ts'] > $busy['start'] ) {
						$available = false;
						$status    = 'google_busy';
						break;
					}
				}
			}

			if ( $available ) {
				$available_count++;
			}

			$item = array(
				'time'        => $slot['time'],
				'end_time'    => $slot['end_time'],
				'label'       => $slot['short_label'],
				'range_label' => $slot['range_label'],
				'available'   => $available,
				'status'      => $is_admin ? $status : ( $available ? 'available' : 'unavailable' ),
			);

			if ( $is_admin && $matched_row ) {
				$item['booking'] = array(
					'id'               => (int) $matched_row->id,
					'submission_id'    => $matched_row->submission_id ? (int) $matched_row->submission_id : null,
					'customer_name'    => $matched_row->customer_name,
					'customer_email'   => $matched_row->customer_email,
					'customer_phone'   => $matched_row->customer_phone,
					'booking_type'     => $matched_row->booking_type,
					'notes'            => $matched_row->notes,
					'status'           => $matched_row->status,
					'google_event_id'  => isset( $matched_row->google_event_id ) ? $matched_row->google_event_id : '',
					'google_meet_link' => isset( $matched_row->google_meet_link ) ? $matched_row->google_meet_link : '',
				);
			}

			$result_slots[] = $item;
		}

		$message = '';
		if ( ! $template['enabled'] ) {
			$message = sprintf(
				/* translators: %s: Day name */
				__( 'Consultations are not scheduled on %ss. Please select another date.', 'rhw-calculator' ),
				$template['day_name']
			);
		} elseif ( $all_day_blocked ) {
			$message = __( 'This entire day is blocked for consultations. Please select another date.', 'rhw-calculator' );
		} elseif ( 0 === $available_count ) {
			$message = __( 'All consultation times are booked on this date. Please select another available day.', 'rhw-calculator' );
		}

		$response = array(
			'date'            => $date_ymd,
			'formatted_date'  => ( new DateTimeImmutable( $date_ymd, $tz ) )->format( 'l, F j, Y' ),
			'day_name'        => $template['day_name'],
			'day_enabled'     => $template['enabled'],
			'all_day_blocked' => $all_day_blocked,
			'interval_mins'   => intval( $settings['interval_minutes'] ),
			'slots'           => $result_slots,
			'available_count' => $available_count,
			'message'         => $message,
		);

		if ( $is_admin && $all_day_booking ) {
			$response['all_day_booking'] = array(
				'id'    => (int) $all_day_booking->id,
				'notes' => $all_day_booking->notes,
			);
		}

		return $response;
	}

	/**
	 * Return availability summary for every day in a given calendar month (single DB query + single Google FreeBusy request).
	 *
	 * @param int  $year     4-digit year.
	 * @param int  $month    1-12 month.
	 * @param bool $is_admin Include admin event previews.
	 * @return array Month metadata + per-date summary map.
	 */
	public static function get_month_availability( $year, $month, $is_admin = false ) {
		$settings = self::get_settings();
		$tz       = self::get_timezone( $settings );

		$year  = max( 2020, min( 2100, intval( $year ) ) );
		$month = max( 1, min( 12, intval( $month ) ) );

		$first_day_str = sprintf( '%04d-%02d-01', $year, $month );
		$first_dt      = new DateTimeImmutable( $first_day_str . ' 00:00:00', $tz );
		$days_in_month = (int) $first_dt->format( 't' );
		$last_day_str  = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );
		$last_dt       = new DateTimeImmutable( $last_day_str . ' 23:59:59', $tz );

		// Single DB query for the month
		global $wpdb;
		RHW_Activator::ensure_bookings_table_exists();
		$table = $wpdb->prefix . 'rhw_bookings';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$db_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE booking_date BETWEEN %s AND %s AND status IN ('confirmed', 'blocked') ORDER BY booking_date ASC, id ASC",
				$first_day_str,
				$last_day_str
			)
		);

		$rows_by_date = array();
		foreach ( (array) $db_rows as $r ) {
			$rows_by_date[ $r->booking_date ][] = $r;
		}

		// Single Google Calendar FreeBusy query for the entire month
		$busy_intervals = RHW_Google_Calendar::get_busy_intervals(
			$first_dt->format( DATE_RFC3339 ),
			$last_dt->format( DATE_RFC3339 ),
			$tz->getName()
		);

		$now_ts          = time();
		$min_bookable_ts = $now_ts + ( intval( $settings['min_notice_hours'] ) * 3600 );
		$max_bookable_ts = $now_ts + ( intval( $settings['max_days_ahead'] ) * 86400 );
		$today_ymd       = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
		$interval_secs   = max( 15, intval( $settings['interval_minutes'] ) ) * 60;

		$days_map = array();

		for ( $d = 1; $d <= $days_in_month; $d++ ) {
			$date_ymd = sprintf( '%04d-%02d-%02d', $year, $month, $d );
			$template = self::build_day_template_slots( $date_ymd, $settings, $tz );
			$day_rows = isset( $rows_by_date[ $date_ymd ] ) ? $rows_by_date[ $date_ymd ] : array();

			$all_day_blocked = false;
			$booked_count    = 0;
			$blocked_count   = 0;
			$parsed_rows     = array();
			$admin_events    = array();

			foreach ( $day_rows as $row ) {
				if ( 'all_day' === $row->booking_time ) {
					$all_day_blocked = true;
					$blocked_count++;
					if ( $is_admin ) {
						$admin_events[] = array(
							'type'  => 'blocked',
							'time'  => __( 'All Day', 'rhw-calculator' ),
							'title' => $row->notes ? $row->notes : __( 'Day Blocked', 'rhw-calculator' ),
						);
					}
					continue;
				}

				if ( 'blocked' === $row->status || 'blocked_admin' === $row->booking_type ) {
					$blocked_count++;
					if ( $is_admin ) {
						$admin_events[] = array(
							'type'  => 'blocked',
							'time'  => $row->booking_time,
							'title' => $row->notes ? $row->notes : __( 'Blocked', 'rhw-calculator' ),
						);
					}
				} else {
					$booked_count++;
					if ( $is_admin ) {
						$admin_events[] = array(
							'type'  => 'booked',
							'time'  => $row->booking_time,
							'title' => $row->customer_name,
						);
					}
				}

				$r_ts          = self::parse_booking_time_ts( $date_ymd, $row->booking_time, $tz );
				$parsed_rows[] = array(
					'row'      => $row,
					'start_ts' => $r_ts,
					'end_ts'   => $r_ts ? ( $r_ts + $interval_secs ) : 0,
				);
			}

			$avail_count = 0;
			if ( $template['enabled'] && ! $all_day_blocked ) {
				foreach ( $template['slots'] as $slot ) {
					if ( ! $is_admin && ( $slot['start_ts'] < $min_bookable_ts || $slot['start_ts'] > $max_bookable_ts ) ) {
						continue;
					}

					$is_free = true;
					foreach ( $parsed_rows as $pr ) {
						if ( strtolower( trim( $pr['row']->booking_time ) ) === strtolower( trim( $slot['time'] ) ) || ( $pr['start_ts'] && $slot['start_ts'] < $pr['end_ts'] && $slot['end_ts'] > $pr['start_ts'] ) ) {
							$is_free = false;
							break;
						}
					}

					if ( $is_free && ! empty( $busy_intervals ) ) {
						foreach ( $busy_intervals as $busy ) {
							if ( $slot['start_ts'] < $busy['end'] && $slot['end_ts'] > $busy['start'] ) {
								$is_free = false;
								break;
							}
						}
					}

					if ( $is_free ) {
						$avail_count++;
					}
				}
			}

			$status = 'available';
			if ( ! $template['enabled'] ) {
				$status = 'closed';
			} elseif ( $all_day_blocked ) {
				$status = 'blocked';
			} elseif ( $date_ymd < $today_ymd ) {
				$status = 'past';
			} elseif ( 0 === $avail_count ) {
				$status = 'full';
			}

			$day_entry = array(
				'date'            => $date_ymd,
				'day'             => $d,
				'status'          => $status,
				'is_today'        => ( $date_ymd === $today_ymd ),
				'day_enabled'     => $template['enabled'],
				'all_day_blocked' => $all_day_blocked,
				'available_count' => $avail_count,
				'total_slots'     => count( $template['slots'] ),
			);

			if ( $is_admin ) {
				$day_entry['booked_count']  = $booked_count;
				$day_entry['blocked_count'] = $blocked_count;
				$day_entry['events']        = $admin_events;
			}

			$days_map[ $date_ymd ] = $day_entry;
		}

		return array(
			'year'          => $year,
			'month'         => $month,
			'month_label'   => $first_dt->format( 'F Y' ),
			'first_weekday' => (int) $first_dt->format( 'w' ), // 0=Sun .. 6=Sat
			'days_in_month' => $days_in_month,
			'today'         => $today_ymd,
			'gcal_synced'   => RHW_Google_Calendar::is_connected(),
			'days'          => $days_map,
		);
	}

	/**
	 * Block a specific slot or 'all_day' on a given date.
	 */
	public static function block_slot( $date_ymd, $time_str, $notes = 'Blocked by Admin' ) {
		global $wpdb;
		RHW_Activator::ensure_bookings_table_exists();
		$table = $wpdb->prefix . 'rhw_bookings';

		// Prevent duplicate identical block
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE booking_date = %s AND booking_time = %s AND status = 'blocked' LIMIT 1",
				$date_ymd,
				$time_str
			)
		);

		if ( $exists ) {
			return (int) $exists;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'submission_id'  => null,
				'customer_name'  => 'Admin Blocked Slot',
				'customer_email' => get_option( 'admin_email' ),
				'customer_phone' => '',
				'booking_date'   => $date_ymd,
				'booking_time'   => $time_str,
				'booking_type'   => 'blocked_admin',
				'notes'          => sanitize_text_field( $notes ),
				'status'         => 'blocked',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		RHW_Google_Calendar::clear_busy_cache();
		return (int) $wpdb->insert_id;
	}

	/**
	 * Unblock a slot or cancel a booking (also deletes Google Calendar event if linked).
	 */
	public static function delete_booking( $id ) {
		global $wpdb;
		RHW_Activator::ensure_bookings_table_exists();
		$table = $wpdb->prefix . 'rhw_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		if ( $row && ! empty( $row->google_event_id ) ) {
			RHW_Google_Calendar::delete_event( $row->google_event_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->delete( $table, array( 'id' => intval( $id ) ), array( '%d' ) );
		RHW_Google_Calendar::clear_busy_cache();

		return false !== $deleted;
	}

	/**
	 * Check whether a specific date+time slot is still available right before booking submission.
	 */
	public static function is_slot_available( $date_ymd, $time_str ) {
		$day = self::get_day_slots( $date_ymd, false );
		foreach ( $day['slots'] as $s ) {
			if ( strtolower( trim( $s['time'] ) ) === strtolower( trim( $time_str ) ) ) {
				return ! empty( $s['available'] );
			}
		}
		return false;
	}

	/**
	 * Helper to parse a booking_time string in timezone into Unix timestamp.
	 */
	private static function parse_booking_time_ts( $date_ymd, $time_str, $tz ) {
		$time_str = trim( (string) $time_str );
		if ( '' === $time_str || 'all_day' === $time_str ) {
			return 0;
		}
		try {
			$dt = new DateTimeImmutable( $date_ymd . ' ' . $time_str, $tz );
			return $dt->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * Sign an HMAC token for downloading an appointment .ics file without expiring in email.
	 */
	public static function sign_ics_token( $submission_id, $customer_email ) {
		$payload = intval( $submission_id ) . '|' . strtolower( trim( (string) $customer_email ) );
		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	/**
	 * Verify a signed .ics download token cleanly.
	 */
	public static function verify_ics_token( $submission_id, $customer_email, $sig ) {
		if ( empty( $sig ) || empty( $submission_id ) ) {
			return false;
		}
		$expected = self::sign_ics_token( $submission_id, $customer_email );
		return hash_equals( $expected, (string) $sig );
	}

	/**
	 * Escape text according to RFC 5545 iCalendar rules.
	 */
	private static function escape_ics_text( $text ) {
		$str = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$str = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\;', '\,' ), $str );
		return str_replace( "\n", '\n', $str );
	}

	/**
	 * Fold long iCalendar content lines to <= 74 octets as required by RFC 5545.
	 */
	private static function fold_ics_line( $line ) {
		$out = '';
		while ( strlen( $line ) > 74 ) {
			$cut = 74;
			while ( $cut > 0 && ( ord( $line[ $cut ] ) & 0xC0 ) === 0x80 ) {
				$cut--;
			}
			if ( 0 === $cut ) {
				$cut = 74;
			}
			$out .= substr( $line, 0, $cut ) . "\r\n ";
			$line = substr( $line, $cut );
		}
		return $out . $line;
	}

	/**
	 * Build an RFC 5545 .ics calendar file string for any popular calendar client
	 * (Apple Calendar, Outlook Desktop/App, Google Calendar, Yahoo, Thunderbird, Android).
	 */
	public static function build_ics_content( $title, $start_ts, $end_ts, $details = '', $location = '', $uid = '' ) {
		if ( empty( $location ) ) {
			$location = 'Rose Hall Weddings & Events (Phone / Video Consultation), Montego Bay, Jamaica';
		}
		if ( empty( $uid ) ) {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			$uid  = md5( $title . '|' . $start_ts ) . '@' . ( $host ? $host : 'rosehall.com' );
		}

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Rose Hall Weddings & Events//Event Cost Calculator//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . gmdate( 'Ymd\THis\Z', $start_ts ),
			'DTEND:' . gmdate( 'Ymd\THis\Z', $end_ts ),
			self::fold_ics_line( 'SUMMARY:' . self::escape_ics_text( $title ) ),
			self::fold_ics_line( 'LOCATION:' . self::escape_ics_text( $location ) ),
			self::fold_ics_line( 'DESCRIPTION:' . self::escape_ics_text( $details ) ),
			'STATUS:CONFIRMED',
			'END:VEVENT',
			'END:VCALENDAR',
		);

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Build direct "Add Appointment To Calendar" URLs for all popular calendar providers.
	 */
	public static function build_calendar_links( $submission_id, $customer_email, $title, $start_ts, $end_ts, $details, $location = '', $google_override_url = '' ) {
		if ( empty( $location ) ) {
			$location = 'Rose Hall Weddings & Events (Phone / Video Consultation), Montego Bay, Jamaica';
		}

		$iso_start = gmdate( 'Y-m-d\TH:i:s\Z', $start_ts );
		$iso_end   = gmdate( 'Y-m-d\TH:i:s\Z', $end_ts );
		$cmp_start = gmdate( 'Ymd\THis\Z', $start_ts );
		$cmp_end   = gmdate( 'Ymd\THis\Z', $end_ts );

		$ics_url = add_query_arg(
			array(
				'action' => 'rhw_download_ics',
				'sid'    => intval( $submission_id ),
				'sig'    => self::sign_ics_token( $submission_id, $customer_email ),
			),
			admin_url( 'admin-ajax.php' )
		);

		$google_url = ! empty( $google_override_url )
			? $google_override_url
			: RHW_Google_Calendar::build_manual_gcal_url( $title, $start_ts, $end_ts, $details, $location );

		$outlook_url = 'https://outlook.live.com/calendar/0/action/compose?' . http_build_query(
			array(
				'rru'      => 'addevent',
				'startdt'  => $iso_start,
				'enddt'    => $iso_end,
				'subject'  => $title,
				'body'     => $details,
				'location' => $location,
			)
		);

		$office365_url = 'https://outlook.office.com/calendar/0/action/compose?' . http_build_query(
			array(
				'rru'      => 'addevent',
				'startdt'  => $iso_start,
				'enddt'    => $iso_end,
				'subject'  => $title,
				'body'     => $details,
				'location' => $location,
			)
		);

		$yahoo_url = 'https://calendar.yahoo.com/?' . http_build_query(
			array(
				'v'      => '60',
				'title'  => $title,
				'st'     => $cmp_start,
				'et'     => $cmp_end,
				'desc'   => $details,
				'in_loc' => $location,
			)
		);

		return array(
			'ics'       => $ics_url,
			'google'    => $google_url,
			'outlook'   => $outlook_url,
			'office365' => $office365_url,
			'yahoo'     => $yahoo_url,
		);
	}

	/**
	 * Write a temporary .ics calendar invite file for attaching to notification emails.
	 */
	public static function save_temp_ics_file( $submission_id, $ics_content ) {
		if ( empty( $ics_content ) ) {
			return false;
		}
		$up  = wp_upload_dir();
		$dir = ! empty( $up['basedir'] ) ? trailingslashit( $up['basedir'] ) . 'rhw-quotes-tmp' : rtrim( sys_get_temp_dir(), '/\\' ) . '/rhw-quotes-tmp';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return false;
		}

		// Clean stale .ics files (> 1 hour)
		$old_files = @glob( trailingslashit( $dir ) . '*.ics' );
		if ( is_array( $old_files ) ) {
			$cutoff = time() - 3600;
			foreach ( $old_files as $f ) {
				if ( @filemtime( $f ) < $cutoff ) {
					@unlink( $f );
				}
			}
		}

		$filepath = trailingslashit( $dir ) . 'Rose-Hall-Consultation-' . intval( $submission_id ) . '.ics';
		if ( false !== @file_put_contents( $filepath, $ics_content ) && file_exists( $filepath ) ) {
			return $filepath;
		}
		return false;
	}
}
