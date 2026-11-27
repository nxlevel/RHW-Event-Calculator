<?php
/**
 * Google Calendar Integration Client (OAuth 2.0 & Service Account JSON)
 *
 * Provides pure-PHP (WordPress HTTP API + OpenSSL) two-way sync with a team's
 * Google Account Calendar: FreeBusy reads to block conflicting times and event creation
 * with optional Google Meet links and attendee invites.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Google_Calendar {

	const OPTION_SETTINGS = 'rhw_gcal_settings';
	const OPTION_TOKENS   = 'rhw_gcal_oauth_tokens';
	const TRANSIENT_TOKEN = 'rhw_gcal_access_token_cache';
	const SCOPES          = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/userinfo.email';

	/**
	 * Retrieve Google Calendar sync settings.
	 */
	public static function get_settings() {
		$defaults = array(
			'mode'              => 'oauth', // 'oauth' or 'service_account'
			'client_id'         => '',
			'client_secret'     => '',
			'service_json'      => '',
			'calendar_id'       => 'primary',
			'sync_busy_times'   => 1,
			'auto_create_event' => 1,
			'create_meet_link'  => 1,
			'invite_attendee'   => 1,
		);

		$saved = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Save Google Calendar sync settings.
	 */
	public static function save_settings( $data ) {
		$current = self::get_settings();

		$updated = array(
			'mode'              => isset( $data['mode'] ) && in_array( $data['mode'], array( 'oauth', 'service_account' ), true ) ? $data['mode'] : $current['mode'],
			'client_id'         => isset( $data['client_id'] ) ? sanitize_text_field( wp_unslash( $data['client_id'] ) ) : $current['client_id'],
			'client_secret'     => isset( $data['client_secret'] ) ? sanitize_text_field( wp_unslash( $data['client_secret'] ) ) : $current['client_secret'],
			'service_json'      => isset( $data['service_json'] ) ? trim( wp_unslash( $data['service_json'] ) ) : $current['service_json'],
			'calendar_id'       => ! empty( $data['calendar_id'] ) ? sanitize_text_field( wp_unslash( $data['calendar_id'] ) ) : 'primary',
			'sync_busy_times'   => ! empty( $data['sync_busy_times'] ) ? 1 : 0,
			'auto_create_event' => ! empty( $data['auto_create_event'] ) ? 1 : 0,
			'create_meet_link'  => ! empty( $data['create_meet_link'] ) ? 1 : 0,
			'invite_attendee'   => ! empty( $data['invite_attendee'] ) ? 1 : 0,
		);

		update_option( self::OPTION_SETTINGS, $updated );
		delete_transient( self::TRANSIENT_TOKEN );
		self::clear_busy_cache();

		return $updated;
	}

	/**
	 * Get the OAuth redirect URI to register in Google Cloud Console.
	 */
	public static function get_redirect_uri() {
		return admin_url( 'admin.php?page=rhw-calculator-calendar&rhw_gcal_action=oauth_callback' );
	}

	/**
	 * Check if Google Calendar API connection is configured & authorized.
	 */
	public static function is_connected() {
		$settings = self::get_settings();

		if ( 'service_account' === $settings['mode'] ) {
			if ( empty( $settings['service_json'] ) ) {
				return false;
			}
			$decoded = json_decode( $settings['service_json'], true );
			return is_array( $decoded ) && ! empty( $decoded['client_email'] ) && ! empty( $decoded['private_key'] );
		}

		// OAuth mode
		$tokens = get_option( self::OPTION_TOKENS, array() );
		return ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] ) && is_array( $tokens ) && ! empty( $tokens['refresh_token'] );
	}

	/**
	 * Get connected account email/description for admin status badge.
	 */
	public static function get_connected_account_label() {
		$settings = self::get_settings();

		if ( 'service_account' === $settings['mode'] ) {
			$decoded = json_decode( $settings['service_json'], true );
			if ( is_array( $decoded ) && ! empty( $decoded['client_email'] ) ) {
				return $decoded['client_email'];
			}
			return '';
		}

		$tokens = get_option( self::OPTION_TOKENS, array() );
		if ( is_array( $tokens ) && ! empty( $tokens['account_email'] ) ) {
			return $tokens['account_email'];
		}

		return self::is_connected() ? __( 'Connected Google Account', 'rhw-calculator' ) : '';
	}

	/**
	 * Build Google OAuth2 authorization URL.
	 */
	public static function get_oauth_authorize_url() {
		$settings = self::get_settings();
		if ( empty( $settings['client_id'] ) ) {
			return '';
		}

		$state = wp_create_nonce( 'rhw_gcal_oauth_state' );
		set_transient( 'rhw_gcal_oauth_state_' . get_current_user_id(), $state, 600 );

		$params = array(
			'client_id'              => $settings['client_id'],
			'redirect_uri'           => self::get_redirect_uri(),
			'response_type'          => 'code',
			'scope'                  => self::SCOPES,
			'access_type'            => 'offline',
			'prompt'                 => 'consent',
			'include_granted_scopes' => 'true',
			'state'                  => $state,
		);

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	/**
	 * Handle OAuth2 callback code exchange and store tokens.
	 */
	public static function handle_oauth_callback( $code, $state ) {
		$expected_state = get_transient( 'rhw_gcal_oauth_state_' . get_current_user_id() );
		delete_transient( 'rhw_gcal_oauth_state_' . get_current_user_id() );

		if ( empty( $state ) || empty( $expected_state ) || ! hash_equals( $expected_state, $state ) ) {
			return new WP_Error( 'invalid_state', __( 'Google OAuth security verification failed. Please try connecting again.', 'rhw-calculator' ) );
		}

		$settings = self::get_settings();
		if ( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) {
			return new WP_Error( 'missing_credentials', __( 'Please save your Google Client ID and Client Secret first.', 'rhw-calculator' ) );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'redirect_uri'  => self::get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err_msg = ! empty( $body['error_description'] ) ? $body['error_description'] : ( ! empty( $body['error'] ) ? $body['error'] : __( 'Failed to obtain Google access token.', 'rhw-calculator' ) );
			return new WP_Error( 'oauth_error', $err_msg );
		}

		$existing_tokens = get_option( self::OPTION_TOKENS, array() );
		$refresh_token   = ! empty( $body['refresh_token'] ) ? $body['refresh_token'] : ( isset( $existing_tokens['refresh_token'] ) ? $existing_tokens['refresh_token'] : '' );

		if ( empty( $refresh_token ) ) {
			return new WP_Error( 'no_refresh_token', __( 'Google did not return a refresh token. Please remove app access in your Google Account security permissions and reconnect.', 'rhw-calculator' ) );
		}

		// Fetch user email for clear display in Admin
		$account_email = '';
		$userinfo_res  = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Bearer ' . $body['access_token'],
				),
			)
		);
		if ( ! is_wp_error( $userinfo_res ) ) {
			$userinfo = json_decode( wp_remote_retrieve_body( $userinfo_res ), true );
			if ( ! empty( $userinfo['email'] ) ) {
				$account_email = sanitize_email( $userinfo['email'] );
			}
		}

		update_option(
			self::OPTION_TOKENS,
			array(
				'refresh_token' => $refresh_token,
				'account_email' => $account_email,
				'connected_at'  => current_time( 'mysql' ),
			)
		);

		$expires_in = ! empty( $body['expires_in'] ) ? intval( $body['expires_in'] ) : 3500;
		set_transient( self::TRANSIENT_TOKEN, $body['access_token'], max( 60, $expires_in - 120 ) );
		self::clear_busy_cache();

		return true;
	}

	/**
	 * Disconnect Google OAuth account.
	 */
	public static function disconnect() {
		delete_option( self::OPTION_TOKENS );
		delete_transient( self::TRANSIENT_TOKEN );
		self::clear_busy_cache();
	}

	/**
	 * Retrieve a valid Google API Access Token (cached & auto-refreshed).
	 */
	public static function get_access_token() {
		$cached = get_transient( self::TRANSIENT_TOKEN );
		if ( ! empty( $cached ) ) {
			return $cached;
		}

		$settings = self::get_settings();

		if ( 'service_account' === $settings['mode'] ) {
			return self::get_service_account_token( $settings['service_json'] );
		}

		// OAuth 2.0 refresh token flow
		$tokens = get_option( self::OPTION_TOKENS, array() );
		if ( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) || empty( $tokens['refresh_token'] ) ) {
			return new WP_Error( 'not_connected', __( 'Google Account is not connected.', 'rhw-calculator' ) );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'refresh_token' => $tokens['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err = ! empty( $body['error_description'] ) ? $body['error_description'] : __( 'Unable to refresh Google access token.', 'rhw-calculator' );
			return new WP_Error( 'refresh_failed', $err );
		}

		$expires_in = ! empty( $body['expires_in'] ) ? intval( $body['expires_in'] ) : 3500;
		set_transient( self::TRANSIENT_TOKEN, $body['access_token'], max( 60, $expires_in - 120 ) );

		return $body['access_token'];
	}

	/**
	 * Sign RS256 JWT and obtain access token for Service Account JSON.
	 */
	private static function get_service_account_token( $json_str ) {
		if ( ! function_exists( 'openssl_sign' ) ) {
			return new WP_Error( 'no_openssl', __( 'PHP OpenSSL extension is required for Service Account JWT authentication.', 'rhw-calculator' ) );
		}

		$creds = json_decode( $json_str, true );
		if ( ! is_array( $creds ) || empty( $creds['client_email'] ) || empty( $creds['private_key'] ) ) {
			return new WP_Error( 'invalid_service_json', __( 'Invalid Google Service Account JSON.', 'rhw-calculator' ) );
		}

		$now = time();
		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);
		$claim = array(
			'iss'   => $creds['client_email'],
			'scope' => 'https://www.googleapis.com/auth/calendar',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'exp'   => $now + 3600,
			'iat'   => $now,
		);

		$segments   = array(
			self::base64url_encode( wp_json_encode( $header ) ),
			self::base64url_encode( wp_json_encode( $claim ) ),
		);
		$sign_input = implode( '.', $segments );

		$signature = '';
		$signed    = openssl_sign( $sign_input, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256 );
		if ( ! $signed ) {
			return new WP_Error( 'jwt_sign_failed', __( 'Failed to sign Service Account JWT assertion.', 'rhw-calculator' ) );
		}

		$segments[] = self::base64url_encode( $signature );
		$jwt        = implode( '.', $segments );

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err = ! empty( $body['error_description'] ) ? $body['error_description'] : __( 'Service Account authentication failed.', 'rhw-calculator' );
			return new WP_Error( 'sa_token_failed', $err );
		}

		$expires_in = ! empty( $body['expires_in'] ) ? intval( $body['expires_in'] ) : 3500;
		set_transient( self::TRANSIENT_TOKEN, $body['access_token'], max( 60, $expires_in - 120 ) );

		return $body['access_token'];
	}

	/**
	 * Fetch user's writable Google Calendars (for calendar dropdown in admin).
	 */
	public static function list_calendars() {
		if ( ! self::is_connected() ) {
			return array();
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return array();
		}

		$response = wp_remote_get(
			'https://www.googleapis.com/calendar/v3/users/me/calendarList?minAccessRole=writer',
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['items'] ) || ! is_array( $body['items'] ) ) {
			return array();
		}

		$calendars = array();
		foreach ( $body['items'] as $cal ) {
			$calendars[] = array(
				'id'       => isset( $cal['id'] ) ? $cal['id'] : '',
				'summary'  => isset( $cal['summary'] ) ? $cal['summary'] : ( isset( $cal['id'] ) ? $cal['id'] : '' ),
				'primary'  => ! empty( $cal['primary'] ),
				'timezone' => isset( $cal['timeZone'] ) ? $cal['timeZone'] : '',
			);
		}

		return $calendars;
	}

	/**
	 * Query Google Calendar FreeBusy API for busy intervals between two DateTime bounds.
	 *
	 * @param string $start_iso ISO8601 start (RFC3339).
	 * @param string $end_iso   ISO8601 end (RFC3339).
	 * @param string $timezone  IANA timezone string.
	 * @return array List of [ 'start' => int_ts, 'end' => int_ts ]
	 */
	public static function get_busy_intervals( $start_iso, $end_iso, $timezone ) {
		$settings = self::get_settings();
		if ( empty( $settings['sync_busy_times'] ) || ! self::is_connected() ) {
			return array();
		}

		$calendar_id = ! empty( $settings['calendar_id'] ) ? $settings['calendar_id'] : 'primary';
		$cache_key   = 'rhw_gcal_busy_' . md5( $calendar_id . '|' . $start_iso . '|' . $end_iso );
		$cached      = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return array();
		}

		$response = wp_remote_post(
			'https://www.googleapis.com/calendar/v3/freeBusy',
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'timeMin'  => $start_iso,
						'timeMax'  => $end_iso,
						'timeZone' => $timezone,
						'items'    => array(
							array( 'id' => $calendar_id ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$busy_raw = array();

		if ( ! empty( $body['calendars'][ $calendar_id ]['busy'] ) && is_array( $body['calendars'][ $calendar_id ]['busy'] ) ) {
			$busy_raw = $body['calendars'][ $calendar_id ]['busy'];
		}

		$intervals = array();
		foreach ( $busy_raw as $slot ) {
			if ( ! empty( $slot['start'] ) && ! empty( $slot['end'] ) ) {
				$intervals[] = array(
					'start' => strtotime( $slot['start'] ),
					'end'   => strtotime( $slot['end'] ),
				);
			}
		}

		set_transient( $cache_key, $intervals, 60 );
		return $intervals;
	}

	/**
	 * Create an event directly in the connected team Google Calendar.
	 *
	 * @param array $args Event details (summary, description, start_iso, end_iso, timezone, attendee_email, attendee_name, location).
	 * @return array|WP_Error Event ID and links or error.
	 */
	public static function create_event( $args ) {
		$settings = self::get_settings();
		if ( empty( $settings['auto_create_event'] ) || ! self::is_connected() ) {
			return new WP_Error( 'not_enabled', __( 'Google Calendar auto-event creation is disabled or not connected.', 'rhw-calculator' ) );
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$calendar_id = ! empty( $settings['calendar_id'] ) ? rawurlencode( $settings['calendar_id'] ) : 'primary';

		$event_payload = array(
			'summary'     => $args['summary'],
			'description' => $args['description'],
			'location'    => isset( $args['location'] ) ? $args['location'] : 'Phone / Google Meet - Rose Hall Weddings & Events',
			'start'       => array(
				'dateTime' => $args['start_iso'],
				'timeZone' => $args['timezone'],
			),
			'end'         => array(
				'dateTime' => $args['end_iso'],
				'timeZone' => $args['timezone'],
			),
			'reminders'   => array(
				'useDefault' => false,
				'overrides'  => array(
					array( 'method' => 'email', 'minutes' => 1440 ),
					array( 'method' => 'popup', 'minutes' => 30 ),
				),
			),
		);

		if ( ! empty( $settings['invite_attendee'] ) && ! empty( $args['attendee_email'] ) && is_email( $args['attendee_email'] ) ) {
			$event_payload['attendees'] = array(
				array(
					'email'       => $args['attendee_email'],
					'displayName' => isset( $args['attendee_name'] ) ? $args['attendee_name'] : '',
				),
			);
		}

		$query_args = array();
		if ( ! empty( $settings['invite_attendee'] ) && ! empty( $event_payload['attendees'] ) ) {
			$query_args['sendUpdates'] = 'all';
		}
		if ( ! empty( $settings['create_meet_link'] ) ) {
			$query_args['conferenceDataVersion'] = 1;
			$event_payload['conferenceData']     = array(
				'createRequest' => array(
					'requestId'             => 'rhw-' . wp_generate_uuid4(),
					'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ),
				),
			);
		}

		$url = 'https://www.googleapis.com/calendar/v3/calendars/' . $calendar_id . '/events';
		if ( ! empty( $query_args ) ) {
			$url .= '?' . http_build_query( $query_args );
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $event_payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// If conferenceData fails (e.g. consumer @gmail account limitations or permissions), retry without conferenceData
		if ( $code >= 400 && ! empty( $event_payload['conferenceData'] ) ) {
			unset( $event_payload['conferenceData'] );
			unset( $query_args['conferenceDataVersion'] );
			$retry_url = 'https://www.googleapis.com/calendar/v3/calendars/' . $calendar_id . '/events';
			if ( ! empty( $query_args ) ) {
				$retry_url .= '?' . http_build_query( $query_args );
			}
			$response = wp_remote_post(
				$retry_url,
				array(
					'timeout' => 20,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $event_payload ),
				)
			);
			if ( ! is_wp_error( $response ) ) {
				$code = wp_remote_retrieve_response_code( $response );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
			}
		}

		if ( $code >= 400 || empty( $body['id'] ) ) {
			$msg = ! empty( $body['error']['message'] ) ? $body['error']['message'] : __( 'Failed to create Google Calendar event.', 'rhw-calculator' );
			return new WP_Error( 'gcal_create_failed', $msg );
		}

		self::clear_busy_cache();

		$meet_link = '';
		if ( ! empty( $body['hangoutLink'] ) ) {
			$meet_link = $body['hangoutLink'];
		} elseif ( ! empty( $body['conferenceData']['entryPoints'][0]['uri'] ) ) {
			$meet_link = $body['conferenceData']['entryPoints'][0]['uri'];
		}

		return array(
			'event_id'  => $body['id'],
			'html_link' => isset( $body['htmlLink'] ) ? $body['htmlLink'] : '',
			'meet_link' => $meet_link,
		);
	}

	/**
	 * Delete an event from Google Calendar when cancelled in admin.
	 */
	public static function delete_event( $event_id ) {
		if ( empty( $event_id ) || ! self::is_connected() ) {
			return false;
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return false;
		}

		$settings    = self::get_settings();
		$calendar_id = ! empty( $settings['calendar_id'] ) ? rawurlencode( $settings['calendar_id'] ) : 'primary';

		$response = wp_remote_request(
			'https://www.googleapis.com/calendar/v3/calendars/' . $calendar_id . '/events/' . rawurlencode( $event_id ),
			array(
				'method'  => 'DELETE',
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		self::clear_busy_cache();

		return ! is_wp_error( $response );
	}

	/**
	 * Clear cached busy intervals (e.g. after booking or admin block change).
	 */
	public static function clear_busy_cache() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rhw_gcal_busy_%' OR option_name LIKE '_transient_timeout_rhw_gcal_busy_%'" );
	}

	/**
	 * Generate a universal 1-click "Add to Google Calendar" URL.
	 */
	public static function build_manual_gcal_url( $title, $start_ts, $end_ts, $details, $location = '' ) {
		if ( empty( $location ) ) {
			$location = 'Rose Hall Weddings & Events (Phone / Google Meet), Montego Bay, Jamaica';
		}
		$start_iso = gmdate( 'Ymd\THis\Z', $start_ts );
		$end_iso   = gmdate( 'Ymd\THis\Z', $end_ts );

		return 'https://calendar.google.com/calendar/render?' . http_build_query(
			array(
				'action'   => 'TEMPLATE',
				'text'     => $title,
				'dates'    => $start_iso . '/' . $end_iso,
				'details'  => $details,
				'location' => $location,
			)
		);
	}

	/**
	 * URL-safe base64 encoder for JWT generation.
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
