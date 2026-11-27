<?php
/**
 * PDF Quote & Questionnaire Generator.
 *
 * Pure-PHP PDF 1.4 generator with zero external dependencies.
 * Generates branded summary PDFs containing full client/event details,
 * line item estimates, uploaded inspiration images, and full questionnaire responses
 * for attachment to Venue Notification Recipients emails.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_PDF_Writer {

	/** @var float Page width in PDF points (US Letter = 612pt). */
	private $page_w = 612.0;

	/** @var float Page height in PDF points (US Letter = 792pt). */
	private $page_h = 792.0;

	/** @var float Left/right margin in points. */
	private $margin = 40.0;

	/** @var float Top/bottom safe margin in points. */
	private $bottom_margin = 52.0;

	/** @var float Current Y cursor measured downward from top of page. */
	private $y = 40.0;

	/** @var int Active 0-based page index. */
	private $current_page = -1;

	/** @var array List of page drawing buffers indexed by page number. */
	private $pages = array();

	/** @var array List of per-page XObject aliases used: array( page_idx => array( alias => image_idx ) ). */
	private $page_images = array();

	/** @var array List of per-page Link annotations. */
	private $page_annots = array();

	/** @var array Deduplicated loaded image resources keyed by source hash. */
	private $images = array();

	/** @var array Map source key => index in $this->images. */
	private $image_lookup = array();

	/** @var string Document left footer label. */
	private $footer_label = 'Rose Hall Weddings & Events';

	/** @var array Adobe Standard 14 Helvetica & Helvetica-Bold glyph widths (0..255). */
	private $font_widths = array();

	public function __construct( $footer_label = 'Rose Hall Weddings & Events' ) {
		$this->footer_label = $footer_label;
		$this->init_font_widths();
		$this->add_page();
	}

	public function get_content_width() {
		return $this->page_w - ( 2 * $this->margin );
	}

	public function get_margin() {
		return $this->margin;
	}

	public function get_y() {
		return $this->y;
	}

	public function set_y( $y ) {
		$this->y = (float) $y;
	}

	public function advance_y( $delta ) {
		$this->y += (float) $delta;
	}

	public function get_remaining_height() {
		return ( $this->page_h - $this->bottom_margin ) - $this->y;
	}

	/**
	 * Start a new PDF page.
	 */
	public function add_page() {
		$this->pages[]       = '';
		$this->page_images[] = array();
		$this->page_annots[] = array();
		$this->current_page  = count( $this->pages ) - 1;
		$this->y             = 36.0;
	}

	/**
	 * Ensure needed vertical height exists on the current page; break to new page if not.
	 */
	public function check_page_break( $needed_h ) {
		if ( ( $this->y + (float) $needed_h ) > ( $this->page_h - $this->bottom_margin ) ) {
			$this->add_page();
			return true;
		}
		return false;
	}

	/**
	 * Initialize Standard 14 Helvetica and Helvetica-Bold metrics.
	 */
	private function init_font_widths() {
		$r = array_fill( 0, 256, 556 );
		$b = array_fill( 0, 256, 611 );

		$r_ascii = array(
			32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
			40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
			48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
			56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
			64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
			72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
			80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
			88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
			96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
			104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556,
			112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722,
			120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260, 125 => 334, 126 => 584,
			128 => 556, 133 => 1000, 145 => 222, 146 => 222, 147 => 333, 148 => 333, 149 => 350, 150 => 556, 151 => 1000,
			160 => 278, 163 => 556, 165 => 556, 215 => 584, 224 => 556, 225 => 556, 232 => 556, 233 => 556, 234 => 556,
			237 => 278, 241 => 556, 243 => 556, 250 => 556, 252 => 556,
		);

		$b_ascii = array(
			32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
			40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
			48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
			56 => 556, 57 => 556, 58 => 333, 59 => 333, 60 => 584, 61 => 584, 62 => 584, 63 => 611,
			64 => 975, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
			72 => 722, 73 => 278, 74 => 556, 75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778,
			80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
			88 => 667, 89 => 667, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556,
			96 => 333, 97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611,
			104 => 611, 105 => 278, 106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611, 111 => 611,
			112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333, 117 => 611, 118 => 556, 119 => 778,
			120 => 556, 121 => 556, 122 => 500, 123 => 389, 124 => 280, 125 => 389, 126 => 584,
			128 => 556, 133 => 1000, 145 => 278, 146 => 278, 147 => 500, 148 => 500, 149 => 350, 150 => 556, 151 => 1000,
			160 => 278, 163 => 556, 165 => 556, 215 => 584, 224 => 556, 225 => 556, 232 => 556, 233 => 556, 234 => 556,
			237 => 278, 241 => 611, 243 => 611, 250 => 611, 252 => 611,
		);

		foreach ( $r_ascii as $code => $w ) {
			$r[ $code ] = $w;
		}
		foreach ( $b_ascii as $code => $w ) {
			$b[ $code ] = $w;
		}

		$this->font_widths['R'] = $r;
		$this->font_widths['I'] = $r;
		$this->font_widths['B'] = $b;
	}

	/**
	 * Append raw drawing command to the active page stream.
	 */
	private function out( $cmd ) {
		$this->pages[ $this->current_page ] .= $cmd . "\n";
	}

	/**
	 * Convert a top-down Y coordinate to PDF bottom-up Y.
	 */
	private function pdf_y( $top_y ) {
		return $this->page_h - (float) $top_y;
	}

	/**
	 * Map a 0-255 RGB triplet to PDF 0..1 float string.
	 */
	private function rgb_str( $r, $g, $b ) {
		return sprintf( '%.3F %.3F %.3F', $r / 255, $g / 255, $b / 255 );
	}

	/**
	 * Draw a rectangle (top-down coordinates). $style: 'F' fill, 'D' stroke, 'FD' fill + stroke.
	 */
	public function rect( $x, $y, $w, $h, $style = 'F', $fill_rgb = null, $stroke_rgb = null, $line_w = 0.6 ) {
		$cmds = array( 'q' );
		if ( is_array( $fill_rgb ) ) {
			$cmds[] = $this->rgb_str( $fill_rgb[0], $fill_rgb[1], $fill_rgb[2] ) . ' rg';
		}
		if ( is_array( $stroke_rgb ) ) {
			$cmds[] = $this->rgb_str( $stroke_rgb[0], $stroke_rgb[1], $stroke_rgb[2] ) . ' RG';
		}
		$cmds[] = sprintf( '%.2F w', (float) $line_w );

		$py = $this->page_h - ( (float) $y + (float) $h );
		$op = 'f';
		if ( 'D' === $style ) {
			$op = 'S';
		} elseif ( 'FD' === $style || 'DF' === $style ) {
			$op = 'B';
		}
		$cmds[] = sprintf( '%.2F %.2F %.2F %.2F re %s', (float) $x, $py, (float) $w, (float) $h, $op );
		$cmds[] = 'Q';
		$this->out( implode( "\n", $cmds ) );
	}

	/**
	 * Draw a horizontal/arbitrary line (top-down coordinates).
	 */
	public function line( $x1, $y1, $x2, $y2, $stroke_rgb = array( 220, 212, 198 ), $line_w = 0.7 ) {
		$cmd = sprintf(
			"q\n%s RG\n%.2F w\n%.2F %.2F m %.2F %.2F l S\nQ",
			$this->rgb_str( $stroke_rgb[0], $stroke_rgb[1], $stroke_rgb[2] ),
			(float) $line_w,
			(float) $x1,
			$this->pdf_y( $y1 ),
			(float) $x2,
			$this->pdf_y( $y2 )
		);
		$this->out( $cmd );
	}

	/**
	 * Convert UTF-8 string to Windows-1252 single-byte encoding for WinAnsiEncoding PDF fonts.
	 */
	public function to_winansi( $text ) {
		$str = (string) $text;
		$str = str_replace( array( "\r\n", "\r", "\t" ), array( "\n", "\n", '    ' ), $str );

		// Map common Unicode symbols cleanly before single-byte WinAnsi encoding.
		$replacements = array(
			"\xE2\x86\x92" => '->',   // right arrow
			"\xE2\x86\x90" => '<-',   // left arrow
			"\xE2\x9C\x93" => 'Yes',  // checkmark
			"\xE2\x9C\x94" => 'Yes',  // heavy checkmark
			"\xE2\x89\xA4" => '<=',
			"\xE2\x89\xA5" => '>=',
			"\xC2\xA0"     => ' ',
			"\xE2\x80\xA2" => "\x95", // bullet
			"\xE2\x80\x93" => "\x96", // en dash
			"\xE2\x80\x94" => "\x97", // em dash
			"\xE2\x80\x98" => "\x91", // lsquo
			"\xE2\x80\x99" => "\x92", // rsquo
			"\xE2\x80\x9C" => "\x93", // ldquo
			"\xE2\x80\x9D" => "\x94", // rdquo
			"\xE2\x80\xA6" => '...',  // ellipsis
		);
		$str = strtr( $str, $replacements );

		$converted = false;
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $str );
		}
		if ( false === $converted && function_exists( 'mb_convert_encoding' ) ) {
			$converted = @mb_convert_encoding( $str, 'Windows-1252', 'UTF-8' );
		}
		if ( false !== $converted ) {
			$str = $converted;
		} else {
			// Pure-PHP UTF-8 -> Latin-1/CP1252 fallback (never leaves UTF-8 multibyte mojibake).
			$str = preg_replace_callback(
				'/[\xC0-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF7][\x80-\xBF]{3}/',
				function( $m ) {
					$b0 = ord( $m[0][0] );
					if ( 2 === strlen( $m[0] ) ) {
						$cp = ( ( $b0 & 0x1F ) << 6 ) | ( ord( $m[0][1] ) & 0x3F );
						return ( $cp >= 0xA0 && $cp <= 0xFF ) ? chr( $cp ) : '?';
					}
					return '';
				},
				$str
			);
		}

		// Strip control bytes other than \n (0x0A).
		$out = '';
		$len = strlen( $str );
		for ( $i = 0; $i < $len; $i++ ) {
			$ord = ord( $str[ $i ] );
			if ( 10 === $ord || $ord >= 32 ) {
				$out .= $str[ $i ];
			}
		}
		return $out;
	}

	/**
	 * Escape a WinAnsi string literal for PDF parentheses delimiters.
	 */
	private function escape_pdf_str( $winansi_str ) {
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $winansi_str );
	}

	/**
	 * Measure rendered width of text in points.
	 */
	public function get_string_width( $text, $font = 'R', $size = 10.0, $already_winansi = false ) {
		$s      = $already_winansi ? $text : $this->to_winansi( $text );
		$widths = isset( $this->font_widths[ $font ] ) ? $this->font_widths[ $font ] : $this->font_widths['R'];
		$total  = 0;
		$len    = strlen( $s );
		for ( $i = 0; $i < $len; $i++ ) {
			$code   = ord( $s[ $i ] );
			$total += isset( $widths[ $code ] ) ? $widths[ $code ] : 556;
		}
		return ( $total * (float) $size ) / 1000.0;
	}

	/**
	 * Wrap UTF-8 text into an array of WinAnsi lines fitting $max_w points.
	 */
	public function wrap_text( $text, $max_w, $font = 'R', $size = 9.5 ) {
		$win   = $this->to_winansi( $text );
		$max_w = max( 20.0, (float) $max_w );
		$paras = explode( "\n", $win );
		$lines = array();

		foreach ( $paras as $para ) {
			$para = trim( $para );
			if ( '' === $para ) {
				$lines[] = '';
				continue;
			}

			$words = preg_split( '/ +/', $para );
			$line  = '';

			foreach ( $words as $word ) {
				$test = ( '' === $line ) ? $word : ( $line . ' ' . $word );
				if ( $this->get_string_width( $test, $font, $size, true ) <= $max_w ) {
					$line = $test;
					continue;
				}

				if ( '' !== $line ) {
					$lines[] = $line;
					$line    = '';
				}

				// If a single word/URL is wider than $max_w, wrap character-by-character cleanly.
				if ( $this->get_string_width( $word, $font, $size, true ) > $max_w ) {
					$chunk = '';
					$wlen  = strlen( $word );
					for ( $i = 0; $i < $wlen; $i++ ) {
						$ch = $word[ $i ];
						if ( '' !== $chunk && $this->get_string_width( $chunk . $ch, $font, $size, true ) > $max_w ) {
							$lines[] = $chunk;
							$chunk   = $ch;
						} else {
							$chunk .= $ch;
						}
					}
					$line = $chunk;
				} else {
					$line = $word;
				}
			}

			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		if ( empty( $lines ) ) {
			$lines[] = '';
		}
		return $lines;
	}

	/**
	 * Resolve PDF internal font resource name from short code ('R', 'B', 'I').
	 */
	private function font_alias( $font ) {
		if ( 'B' === $font ) {
			return '/F2';
		}
		if ( 'I' === $font ) {
			return '/F3';
		}
		return '/F1';
	}

	/**
	 * Draw a single line of text at top-down ($x, $baseline_y).
	 * $align: 'L' left, 'R' right, 'C' center within $box_w.
	 */
	public function draw_line( $text, $x, $baseline_y, $font = 'R', $size = 9.5, $rgb = array( 44, 37, 35 ), $align = 'L', $box_w = 0.0, $already_winansi = false ) {
		$win   = $already_winansi ? $text : $this->to_winansi( $text );
		$drawx = (float) $x;

		if ( 'R' === $align && $box_w > 0 ) {
			$tw    = $this->get_string_width( $win, $font, $size, true );
			$drawx = (float) $x + (float) $box_w - $tw;
		} elseif ( 'C' === $align && $box_w > 0 ) {
			$tw    = $this->get_string_width( $win, $font, $size, true );
			$drawx = (float) $x + ( ( (float) $box_w - $tw ) / 2.0 );
		}

		$cmd = sprintf(
			"q\nBT\n%s %.2F Tf\n%s rg\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\nET\nQ",
			$this->font_alias( $font ),
			(float) $size,
			$this->rgb_str( $rgb[0], $rgb[1], $rgb[2] ),
			$drawx,
			$this->pdf_y( $baseline_y ),
			$this->escape_pdf_str( $win )
		);
		$this->out( $cmd );
	}

	/**
	 * Draw pre-wrapped lines starting at top-down $start_y and return height consumed.
	 */
	public function draw_lines( $lines, $x, $start_y, $line_h, $font = 'R', $size = 9.5, $rgb = array( 44, 37, 35 ), $align = 'L', $box_w = 0.0 ) {
		$cur_y = (float) $start_y;
		foreach ( $lines as $line ) {
			$this->draw_line( $line, $x, $cur_y + ( $size * 0.85 ), $font, $size, $rgb, $align, $box_w, true );
			$cur_y += (float) $line_h;
		}
		return count( $lines ) * (float) $line_h;
	}

	/**
	 * Attach a clickable URI link annotation to the active page (top-down coords).
	 */
	public function add_link( $x, $y, $w, $h, $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return;
		}
		$y_top = $this->page_h - (float) $y;
		$y_bot = $this->page_h - ( (float) $y + (float) $h );
		$this->page_annots[ $this->current_page ][] = array(
			'x1'  => (float) $x,
			'y1'  => $y_bot,
			'x2'  => (float) $x + (float) $w,
			'y2'  => $y_top,
			'uri' => (string) $url,
		);
	}

	/**
	 * Resolve a local path or URL into binary bytes, mapping WP upload/content URLs locally first.
	 */
	private function fetch_source_bytes( $source ) {
		if ( empty( $source ) || ! is_string( $source ) ) {
			return '';
		}

		// Direct local filesystem path.
		if ( @is_file( $source ) && @is_readable( $source ) ) {
			$data = @file_get_contents( $source );
			return is_string( $data ) ? $data : '';
		}

		// Map WordPress uploads or content URL to local filesystem path before HTTP fallback.
		if ( 1 === preg_match( '#^https?://#i', $source ) ) {
			$source_no_scheme = preg_replace( '#^https?://#i', '', $source );
			$url_no_query     = strtok( $source_no_scheme, '?' );

			$path_mappings = array();
			if ( function_exists( 'wp_upload_dir' ) ) {
				$up = wp_upload_dir();
				if ( ! empty( $up['baseurl'] ) && ! empty( $up['basedir'] ) ) {
					$path_mappings[] = array(
						'url' => preg_replace( '#^https?://#i', '', rtrim( $up['baseurl'], '/' ) ),
						'dir' => rtrim( $up['basedir'], '/' ),
					);
				}
			}
			if ( defined( 'WP_CONTENT_URL' ) && defined( 'WP_CONTENT_DIR' ) ) {
				$path_mappings[] = array(
					'url' => preg_replace( '#^https?://#i', '', rtrim( WP_CONTENT_URL, '/' ) ),
					'dir' => rtrim( WP_CONTENT_DIR, '/' ),
				);
			}
			if ( defined( 'RHW_CALCULATOR_PLUGIN_URL' ) && defined( 'RHW_CALCULATOR_PLUGIN_DIR' ) ) {
				$path_mappings[] = array(
					'url' => preg_replace( '#^https?://#i', '', rtrim( RHW_CALCULATOR_PLUGIN_URL, '/' ) ),
					'dir' => rtrim( RHW_CALCULATOR_PLUGIN_DIR, '/' ),
				);
			}

			foreach ( $path_mappings as $map ) {
				if ( empty( $map['url'] ) || empty( $map['dir'] ) ) {
					continue;
				}
				if ( 0 === stripos( $url_no_query, $map['url'] ) ) {
					$rel        = substr( $url_no_query, strlen( $map['url'] ) );
					$local_file = $map['dir'] . '/' . ltrim( rawurldecode( $rel ), '/' );
					if ( @is_file( $local_file ) && @is_readable( $local_file ) ) {
						$data = @file_get_contents( $local_file );
						if ( is_string( $data ) && '' !== $data ) {
							return $data;
						}
					}
				}
			}

			// Remote fallback for external URLs.
			if ( function_exists( 'wp_remote_get' ) ) {
				$resp = wp_remote_get(
					$source,
					array(
						'timeout'   => 8,
						'sslverify' => false,
					)
				);
				if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
					return (string) wp_remote_retrieve_body( $resp );
				}
			}
		}

		return '';
	}

	/**
	 * Load and normalize an image (JPEG, PNG, WEBP, GIF) into a PDF-embeddable image resource.
	 */
	public function load_image_resource( $source ) {
		if ( empty( $source ) || ! is_string( $source ) ) {
			return null;
		}

		$cache_key = md5( $source );
		if ( isset( $this->image_lookup[ $cache_key ] ) ) {
			return $this->images[ $this->image_lookup[ $cache_key ] ];
		}

		$bytes = $this->fetch_source_bytes( $source );
		if ( '' === $bytes || 0 === strpos( $bytes, '%PDF-' ) ) {
			return null;
		}

		$prepared = null;
		try {
			$prepared = $this->normalize_image_via_gd( $bytes );
			if ( ! $prepared ) {
				$prepared = $this->normalize_image_via_imagick( $bytes );
			}
			if ( ! $prepared ) {
				$prepared = $this->parse_raw_jpeg_headers( $bytes );
			}
		} catch ( Throwable $e ) {
			$prepared = null;
		}

		if ( ! $prepared || empty( $prepared['data'] ) || empty( $prepared['w'] ) || empty( $prepared['h'] ) ) {
			return null;
		}

		$idx                              = count( $this->images );
		$prepared['idx']                  = $idx;
		$this->images[]                   = $prepared;
		$this->image_lookup[ $cache_key ] = $idx;

		return $prepared;
	}

	/**
	 * Decode, orient, resize (<= 1050px), and flatten image onto white RGB JPEG via GD.
	 */
	private function normalize_image_via_gd( $bytes ) {
		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagejpeg' ) ) {
			return null;
		}

		$src = @imagecreatefromstring( $bytes );
		if ( ! $src && 0 === strpos( $bytes, 'RIFF' ) && false !== strpos( substr( $bytes, 0, 16 ), 'WEBP' ) && function_exists( 'imagecreatefromwebp' ) ) {
			$tmp = tempnam( sys_get_temp_dir(), 'rhwwebp' );
			if ( $tmp ) {
				@file_put_contents( $tmp, $bytes );
				$src = @imagecreatefromwebp( $tmp );
				@unlink( $tmp );
			}
		}
		if ( ! $src ) {
			return null;
		}

		// Handle iPhone/camera EXIF orientation for JPEGs.
		if ( "\xFF\xD8" === substr( $bytes, 0, 2 ) && function_exists( 'exif_read_data' ) && function_exists( 'imagerotate' ) ) {
			$mem = @fopen( 'php://memory', 'r+' );
			if ( $mem ) {
				fwrite( $mem, $bytes );
				rewind( $mem );
				$exif = @exif_read_data( $mem );
				fclose( $mem );
				if ( is_array( $exif ) && ! empty( $exif['Orientation'] ) ) {
					$angle = 0;
					switch ( (int) $exif['Orientation'] ) {
						case 3:
							$angle = 180;
							break;
						case 6:
							$angle = -90;
							break;
						case 8:
							$angle = 90;
							break;
					}
					if ( 0 !== $angle ) {
						$rotated = @imagerotate( $src, $angle, 0 );
						if ( $rotated ) {
							imagedestroy( $src );
							$src = $rotated;
						}
					}
				}
			}
		}

		$src_w = imagesx( $src );
		$src_h = imagesy( $src );
		if ( $src_w <= 0 || $src_h <= 0 ) {
			imagedestroy( $src );
			return null;
		}

		$max_edge = 1050;
		$scale    = 1.0;
		if ( max( $src_w, $src_h ) > $max_edge ) {
			$scale = $max_edge / max( $src_w, $src_h );
		}
		$dst_w = max( 1, (int) round( $src_w * $scale ) );
		$dst_h = max( 1, (int) round( $src_h * $scale ) );

		$dst   = imagecreatetruecolor( $dst_w, $dst_h );
		$white = imagecolorallocate( $dst, 255, 255, 255 );
		imagefill( $dst, 0, 0, $white );
		imagealphablending( $dst, true );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h );
		imagedestroy( $src );

		ob_start();
		imagejpeg( $dst, null, 85 );
		$jpeg_bytes = ob_get_clean();
		imagedestroy( $dst );

		if ( empty( $jpeg_bytes ) ) {
			return null;
		}

		return array(
			'data'        => $jpeg_bytes,
			'w'           => $dst_w,
			'h'           => $dst_h,
			'cs'          => 'DeviceRGB',
			'bpc'         => 8,
			'filter'      => 'DCTDecode',
			'invert_cmyk' => false,
		);
	}

	/**
	 * Fallback normalization using Imagick if GD fails/lacks WebP.
	 */
	private function normalize_image_via_imagick( $bytes ) {
		if ( ! class_exists( 'Imagick' ) ) {
			return null;
		}
		$im = new Imagick();
		$im->readImageBlob( $bytes );
		if ( method_exists( $im, 'autoOrient' ) ) {
			$im->autoOrient();
		}
		$im->setImageBackgroundColor( 'white' );
		if ( method_exists( $im, 'mergeImageLayers' ) && defined( 'Imagick::LAYERMETHOD_FLATTEN' ) ) {
			$im = $im->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
		}
		$w = $im->getImageWidth();
		$h = $im->getImageHeight();
		if ( max( $w, $h ) > 1050 ) {
			$im->thumbnailImage( 1050, 1050, true, true );
			$w = $im->getImageWidth();
			$h = $im->getImageHeight();
		}
		$im->setImageColorspace( Imagick::COLORSPACE_SRGB );
		$im->setImageFormat( 'jpeg' );
		$im->setImageCompressionQuality( 85 );
		$jpeg_bytes = $im->getImageBlob();
		$im->clear();
		$im->destroy();

		if ( empty( $jpeg_bytes ) || $w <= 0 || $h <= 0 ) {
			return null;
		}

		return array(
			'data'        => $jpeg_bytes,
			'w'           => $w,
			'h'           => $h,
			'cs'          => 'DeviceRGB',
			'bpc'         => 8,
			'filter'      => 'DCTDecode',
			'invert_cmyk' => false,
		);
	}

	/**
	 * Fallback direct JPEG SOF header parser when neither GD nor Imagick is installed.
	 */
	private function parse_raw_jpeg_headers( $bytes ) {
		if ( "\xFF\xD8" !== substr( $bytes, 0, 2 ) ) {
			return null;
		}
		$len = strlen( $bytes );
		$pos = 2;
		while ( $pos + 9 < $len ) {
			if ( 0xFF !== ord( $bytes[ $pos ] ) ) {
				$pos++;
				continue;
			}
			$marker = ord( $bytes[ $pos + 1 ] );
			$pos   += 2;
			if ( 0x00 === $marker || 0xD8 === $marker || 0xD9 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
				continue;
			}
			if ( $pos + 2 > $len ) {
				break;
			}
			$seg_len = ( ord( $bytes[ $pos ] ) << 8 ) | ord( $bytes[ $pos + 1 ] );
			// SOF0..SOF3, SOF5..SOF7, SOF9..SOF11, SOF13..SOF15 contain dimensions.
			if ( ( $marker >= 0xC0 && $marker <= 0xCF ) && ! in_array( $marker, array( 0xC4, 0xC8, 0xCC ), true ) ) {
				if ( $pos + 7 >= $len ) {
					break;
				}
				$bpc        = ord( $bytes[ $pos + 2 ] );
				$h          = ( ord( $bytes[ $pos + 3 ] ) << 8 ) | ord( $bytes[ $pos + 4 ] );
				$w          = ( ord( $bytes[ $pos + 5 ] ) << 8 ) | ord( $bytes[ $pos + 6 ] );
				$components = ord( $bytes[ $pos + 7 ] );
				$cs         = 'DeviceRGB';
				if ( 1 === $components ) {
					$cs = 'DeviceGray';
				} elseif ( 4 === $components ) {
					$cs = 'DeviceCMYK';
				}
				if ( $w > 0 && $h > 0 ) {
					return array(
						'data'        => $bytes,
						'w'           => $w,
						'h'           => $h,
						'cs'          => $cs,
						'bpc'         => $bpc ? $bpc : 8,
						'filter'      => 'DCTDecode',
						'invert_cmyk' => ( 'DeviceCMYK' === $cs ),
					);
				}
				break;
			}
			if ( $seg_len < 2 ) {
				break;
			}
			$pos += $seg_len;
		}
		return null;
	}

	/**
	 * Draw an embedded image onto the current page (top-down coordinates).
	 */
	public function draw_image( $img_res, $x, $y, $w, $h ) {
		if ( empty( $img_res ) || ! isset( $img_res['idx'] ) ) {
			return;
		}
		$idx   = (int) $img_res['idx'];
		$alias = '/Im' . ( $idx + 1 );
		$this->page_images[ $this->current_page ][ $alias ] = $idx;

		$py = $this->page_h - ( (float) $y + (float) $h );
		$this->out(
			sprintf(
				"q\n%.2F 0 0 %.2F %.2F %.2F cm\n%s Do\nQ",
				(float) $w,
				(float) $h,
				(float) $x,
				$py,
				$alias
			)
		);
	}

	/**
	 * Assemble the complete PDF 1.4 binary and write it to $filepath.
	 */
	public function save_to_file( $filepath ) {
		$total_pages = count( $this->pages );

		// Stamp footers on every page before serializing objects.
		for ( $i = 0; $i < $total_pages; $i++ ) {
			$this->current_page = $i;
			$footer_y           = $this->page_h - 32.0;
			$this->line( $this->margin, $footer_y - 8.0, $this->page_w - $this->margin, $footer_y - 8.0, array( 228, 222, 214 ), 0.6 );
			$this->draw_line( $this->footer_label, $this->margin, $footer_y + 4.0, 'R', 8.0, array( 125, 118, 114 ), 'L', 350.0 );
			$this->draw_line(
				sprintf( 'Page %d of %d', $i + 1, $total_pages ),
				$this->page_w - $this->margin - 120.0,
				$footer_y + 4.0,
				'R',
				8.0,
				array( 125, 118, 114 ),
				'R',
				120.0
			);
		}

		$objects = array();
		$next_id = 6; // 1=Catalog, 2=Pages, 3=F1, 4=F2, 5=F3

		// Fonts with WinAnsiEncoding.
		$objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
		$objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
		$objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>";

		// Image XObjects.
		$img_obj_ids = array();
		foreach ( $this->images as $idx => $img ) {
			$oid                 = $next_id++;
			$img_obj_ids[ $idx ] = $oid;
			$stream              = $img['data'];
			$decode_entry        = ! empty( $img['invert_cmyk'] ) ? ' /Decode [1 0 1 0 1 0 1 0]' : '';
			$objects[ $oid ]     = "<< /Type /XObject /Subtype /Image /Width " . (int) $img['w'] .
				" /Height " . (int) $img['h'] .
				" /ColorSpace /" . $img['cs'] .
				" /BitsPerComponent " . (int) $img['bpc'] .
				$decode_entry .
				" /Filter /" . $img['filter'] .
				" /Length " . strlen( $stream ) .
				" >>\nstream\n" . $stream . "\nendstream";
		}

		// Pages, Content Streams, and Link Annotations.
		$kid_refs      = array();
		$can_gz        = function_exists( 'gzcompress' );

		for ( $i = 0; $i < $total_pages; $i++ ) {
			$raw_stream     = $this->pages[ $i ];
			$filter_str     = '';
			$stream_payload = $raw_stream;
			if ( $can_gz ) {
				$compressed = @gzcompress( $raw_stream );
				if ( false !== $compressed ) {
					$stream_payload = $compressed;
					$filter_str     = ' /Filter /FlateDecode';
				}
			}

			$content_oid           = $next_id++;
			$objects[ $content_oid ] = "<< /Length " . strlen( $stream_payload ) . $filter_str . " >>\nstream\n" . $stream_payload . "\nendstream";

			$annot_refs = array();
			if ( ! empty( $this->page_annots[ $i ] ) ) {
				foreach ( $this->page_annots[ $i ] as $annot ) {
					$aoid              = $next_id++;
					$annot_refs[]      = $aoid . ' 0 R';
					$uri_escaped       = $this->escape_pdf_str( $this->to_winansi( $annot['uri'] ) );
					$objects[ $aoid ]  = sprintf(
						'<< /Type /Annot /Subtype /Link /Rect [%.2F %.2F %.2F %.2F] /Border [0 0 0] /A << /Type /Action /S /URI /URI (%s) >> >>',
						$annot['x1'],
						$annot['y1'],
						$annot['x2'],
						$annot['y2'],
						$uri_escaped
					);
				}
			}

			$xobj_dict = '';
			if ( ! empty( $this->page_images[ $i ] ) ) {
				$pairs = array();
				foreach ( $this->page_images[ $i ] as $alias => $img_idx ) {
					if ( isset( $img_obj_ids[ $img_idx ] ) ) {
						$pairs[] = $alias . ' ' . $img_obj_ids[ $img_idx ] . ' 0 R';
					}
				}
				if ( ! empty( $pairs ) ) {
					$xobj_dict = ' /XObject << ' . implode( ' ', $pairs ) . ' >>';
				}
			}

			$annots_entry = ! empty( $annot_refs ) ? ' /Annots [' . implode( ' ', $annot_refs ) . ']' : '';

			$page_oid           = $next_id++;
			$kid_refs[]         = $page_oid . ' 0 R';
			$objects[ $page_oid ] = sprintf(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >>%s >>%s /Contents %d 0 R >>',
				$this->page_w,
				$this->page_h,
				$xobj_dict,
				$annots_entry,
				$content_oid
			);
		}

		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kid_refs ) . '] /Count ' . $total_pages . ' >>';
		ksort( $objects );

		// Build PDF byte stream & cross-reference table.
		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		$max_id  = $next_id - 1;

		for ( $id = 1; $id <= $max_id; $id++ ) {
			$offsets[ $id ] = strlen( $pdf );
			$pdf           .= $id . " 0 obj\n" . $objects[ $id ] . "\nendobj\n";
		}

		$xref_offset = strlen( $pdf );
		$pdf        .= "xref\n0 " . ( $max_id + 1 ) . "\n";
		$pdf        .= "0000000000 65535 f \n";
		for ( $id = 1; $id <= $max_id; $id++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $id ] );
		}

		$pdf .= "trailer\n<< /Size " . ( $max_id + 1 ) . " /Root 1 0 R >>\n";
		$pdf .= "startxref\n" . $xref_offset . "\n%%EOF\n";

		$written = @file_put_contents( $filepath, $pdf );
		return false !== $written && @file_exists( $filepath );
	}
}

class RHW_PDF_Generator {

	/** Rose Hall Burgundy brand RGB. */
	private static $burgundy = array( 123, 17, 45 );

	/** Rose Hall Gold accent RGB. */
	private static $gold = array( 197, 160, 89 );

	/** Rose Hall Light Gold text RGB. */
	private static $light_gold = array( 234, 208, 168 );

	/** Body charcoal RGB. */
	private static $text_dark = array( 44, 37, 35 );

	/** Muted gray RGB. */
	private static $text_muted = array( 107, 99, 96 );

	/** Soft cream row highlight RGB. */
	private static $cream_bg = array( 250, 245, 235 );

	/** Light warm zebra row RGB. */
	private static $alt_bg = array( 250, 248, 244 );

	/** Table border RGB. */
	private static $border_rgb = array( 224, 216, 204 );

	/**
	 * Generate the full Quote & Questionnaire PDF file on disk for a submission.
	 *
	 * @return string|false Local file path to generated PDF, or false on failure.
	 */
	public static function generate_submission_pdf( $submission_id, $submission, $form, $calc ) {
		$temp_dir = self::prepare_temp_directory();
		if ( ! $temp_dir ) {
			return false;
		}

		$filename = 'Rose-Hall-Quote-' . intval( $submission_id ) . '.pdf';
		$filepath = trailingslashit( $temp_dir ) . $filename;

		$answers_raw = isset( $submission['answers'] ) ? $submission['answers'] : array();
		$answers     = is_string( $answers_raw ) ? ( json_decode( $answers_raw, true ) ?: array() ) : (array) $answers_raw;
		$responses   = RHW_Form_Manager::format_questionnaire_responses( $form, $answers );

		$pdf = new RHW_PDF_Writer( sprintf( 'Rose Hall Weddings & Events  |  Lead Quote #%d', intval( $submission_id ) ) );

		self::render_header_banner( $pdf, $submission_id, $submission );
		self::render_client_overview( $pdf, $submission_id, $submission, $form, $calc );
		self::render_line_items_section( $pdf, $calc );
		self::render_questionnaire_section( $pdf, $responses );
		self::render_uploaded_images_section( $pdf, $responses, $submission );

		if ( $pdf->save_to_file( $filepath ) ) {
			return $filepath;
		}

		return false;
	}

	/**
	 * Ensure secure writable temp directory inside uploads and clean stale PDFs (> 1 hour).
	 */
	private static function prepare_temp_directory() {
		$dir = '';
		if ( function_exists( 'wp_upload_dir' ) ) {
			$up = wp_upload_dir();
			if ( ! empty( $up['basedir'] ) ) {
				$dir = trailingslashit( $up['basedir'] ) . 'rhw-quotes-tmp';
			}
		}
		if ( '' === $dir ) {
			$dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/rhw-quotes-tmp';
		}

		if ( ! is_dir( $dir ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $dir );
			} else {
				@mkdir( $dir, 0755, true );
			}
		}
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return false;
		}

		$index_file = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}
		$htaccess_file = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			@file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all\n" );
		}

		// Prune files older than 1 hour so disk stays clean while allowing delayed SMTP hooks.
		$existing = @glob( trailingslashit( $dir ) . '*.pdf' );
		if ( is_array( $existing ) ) {
			$cutoff = time() - 3600;
			foreach ( $existing as $old_pdf ) {
				if ( @filemtime( $old_pdf ) < $cutoff ) {
					@unlink( $old_pdf );
				}
			}
		}

		return $dir;
	}

	/**
	 * Draw a styled Burgundy & Gold section heading.
	 */
	private static function render_section_heading( RHW_PDF_Writer $pdf, $title ) {
		$pdf->check_page_break( 48 );
		$x = $pdf->get_margin();
		$w = $pdf->get_content_width();
		$y = $pdf->get_y();

		$pdf->draw_line( $title, $x, $y + 13.0, 'B', 12.5, self::$burgundy );
		$pdf->line( $x, $y + 18.0, $x + $w, $y + 18.0, self::$gold, 1.4 );
		$pdf->set_y( $y + 26.0 );
	}

	/**
	 * Render top branded header with optional logo from Settings.
	 */
	private static function render_header_banner( RHW_PDF_Writer $pdf, $submission_id, $submission ) {
		$x      = $pdf->get_margin();
		$w      = $pdf->get_content_width();
		$y      = $pdf->get_y();
		$head_h = 84.0;

		$pdf->rect( $x, $y, $w, $head_h, 'F', self::$burgundy );
		$pdf->rect( $x, $y + $head_h, $w, 3.0, 'F', self::$gold );

		$text_x = $x + 18.0;
		$text_w = $w - 36.0;

		$header_image_url = function_exists( 'get_option' ) ? get_option( 'rhw_header_image_url', '' ) : '';
		if ( ! empty( $header_image_url ) ) {
			$logo = $pdf->load_image_resource( $header_image_url );
			if ( $logo ) {
				$box_size = 58.0;
				$box_x    = $x + 14.0;
				$box_y    = $y + 13.0;
				$pdf->rect( $box_x, $box_y, $box_size, $box_size, 'F', array( 255, 255, 255 ) );

				$inner = 52.0;
				$ratio = min( $inner / $logo['w'], $inner / $logo['h'] );
				$iw    = $logo['w'] * $ratio;
				$ih    = $logo['h'] * $ratio;
				$ix    = $box_x + ( ( $box_size - $iw ) / 2.0 );
				$iy    = $box_y + ( ( $box_size - $ih ) / 2.0 );
				$pdf->draw_image( $logo, $ix, $iy, $iw, $ih );

				$text_x = $box_x + $box_size + 14.0;
				$text_w = ( $x + $w - 16.0 ) - $text_x;
			}
		}

		$pdf->draw_line( 'Rose Hall Weddings & Events', $text_x, $y + 30.0, 'B', 17.5, array( 255, 255, 255 ) );
		$pdf->draw_line(
			sprintf( 'LEAD QUOTE & QUESTIONNAIRE SUMMARY  •  REQUEST #%d', intval( $submission_id ) ),
			$text_x,
			$y + 49.0,
			'B',
			9.5,
			self::$light_gold
		);

		$submitted_at = ! empty( $submission['created_at'] ) ? $submission['created_at'] : gmdate( 'Y-m-d H:i:s' );
		$pdf->draw_line( 'Submitted: ' . $submitted_at, $text_x, $y + 66.0, 'R', 8.5, array( 245, 236, 224 ) );

		$pdf->set_y( $y + $head_h + 18.0 );
	}

	/**
	 * Render Client Contact, Event Summary & Booked Consultation overview card.
	 */
	private static function render_client_overview( RHW_PDF_Writer $pdf, $submission_id, $submission, $form, $calc ) {
		self::render_section_heading( $pdf, 'Client & Event Details' );

		$x     = $pdf->get_margin();
		$w     = $pdf->get_content_width();
		$col_w = ( $w - 16.0 ) / 2.0;

		$event_type_label = ! empty( $submission['event_type'] )
			? RHW_Form_Manager::resolve_choice_label( $form, 'event_type', $submission['event_type'] )
			: 'N/A';
		$venue_label      = ! empty( $submission['venue_name'] )
			? RHW_Form_Manager::resolve_choice_label( $form, 'venue_selection', $submission['venue_name'] )
			: 'N/A';
		$rate_note        = ! empty( $calc['is_weekend'] ) ? 'Weekend / Holiday Rate' : 'Weekday Rate';
		$event_date_str   = ! empty( $submission['event_date'] ) ? ( $submission['event_date'] . ' (' . $rate_note . ')' ) : 'TBD';
		$guest_count_str  = isset( $submission['guest_count'] ) ? ( intval( $submission['guest_count'] ) . ' guests' ) : 'N/A';

		$left_rows = array(
			array( 'Client Name', ! empty( $submission['customer_name'] ) ? $submission['customer_name'] : 'N/A' ),
			array( 'Email Address', ! empty( $submission['customer_email'] ) ? $submission['customer_email'] : 'N/A' ),
			array( 'Phone / WhatsApp', ! empty( $submission['customer_phone'] ) ? $submission['customer_phone'] : 'N/A' ),
			array( 'Country of Residence', ! empty( $submission['country_of_residence'] ) ? $submission['country_of_residence'] : 'N/A' ),
		);

		$right_rows = array(
			array( 'Quote Reference', '#' . intval( $submission_id ) ),
			array( 'Event Type', $event_type_label ),
			array( 'Preferred Date', $event_date_str ),
			array( 'Guest Count', $guest_count_str ),
			array( 'Selected Venue', $venue_label ),
		);

		$left_h  = self::measure_kv_block_height( $pdf, $left_rows, $col_w - 20.0 );
		$right_h = self::measure_kv_block_height( $pdf, $right_rows, $col_w - 20.0 );
		$box_h   = max( $left_h, $right_h ) + 18.0;

		$pdf->check_page_break( $box_h + 12.0 );
		$start_y = $pdf->get_y();

		$pdf->rect( $x, $start_y, $w, $box_h, 'FD', self::$alt_bg, self::$border_rgb, 0.7 );
		$pdf->line( $x + $col_w + 8.0, $start_y + 10.0, $x + $col_w + 8.0, $start_y + $box_h - 10.0, self::$border_rgb, 0.6 );

		self::draw_kv_block( $pdf, $left_rows, $x + 12.0, $start_y + 10.0, $col_w - 20.0 );
		self::draw_kv_block( $pdf, $right_rows, $x + $col_w + 20.0, $start_y + 10.0, $col_w - 20.0 );

		$pdf->set_y( $start_y + $box_h + 10.0 );

		// Highlighted Consultation Card (if scheduled).
		if ( ! empty( $submission['consultation_date'] ) && ! empty( $submission['consultation_time'] ) ) {
			$ts        = strtotime( $submission['consultation_date'] );
			$slot_text = ( false !== $ts ? date( 'l, M j, Y', $ts ) : $submission['consultation_date'] ) . ' at ' . $submission['consultation_time'];
			$c_lines   = array();
			$c_lines[] = array( 'label' => 'Booked Consultation: ', 'value' => $slot_text, 'url' => '' );
			if ( ! empty( $submission['google_meet_link'] ) ) {
				$c_lines[] = array( 'label' => 'Google Meet Link: ', 'value' => $submission['google_meet_link'], 'url' => $submission['google_meet_link'] );
			}
			$cal_url = ! empty( $submission['calendar_links']['ics'] )
				? $submission['calendar_links']['ics']
				: ( ! empty( $submission['gcal_link'] ) ? $submission['gcal_link'] : '' );
			if ( ! empty( $cal_url ) ) {
				$c_lines[] = array( 'label' => 'Add Appointment To Calendar: ', 'value' => $cal_url, 'url' => $cal_url );
			}

			$c_box_h = 16.0;
			foreach ( $c_lines as &$cl ) {
				$cl['wrapped'] = $pdf->wrap_text( $cl['label'] . $cl['value'], $w - 28.0, 'R', 9.0 );
				$c_box_h      += count( $cl['wrapped'] ) * 13.0;
			}
			unset( $cl );

			$pdf->check_page_break( $c_box_h + 12.0 );
			$cy = $pdf->get_y();
			$pdf->rect( $x, $cy, $w, $c_box_h, 'FD', self::$cream_bg, self::$border_rgb, 0.7 );
			$pdf->rect( $x, $cy, 4.0, $c_box_h, 'F', self::$burgundy );

			$cur_y = $cy + 9.0;
			foreach ( $c_lines as $cl ) {
				$h_used = count( $cl['wrapped'] ) * 13.0;
				$font   = empty( $cl['url'] ) ? 'B' : 'R';
				$color  = empty( $cl['url'] ) ? self::$burgundy : self::$text_dark;
				$pdf->draw_lines( $cl['wrapped'], $x + 14.0, $cur_y, 13.0, $font, 9.0, $color );
				if ( ! empty( $cl['url'] ) ) {
					$pdf->add_link( $x + 14.0, $cur_y, $w - 28.0, $h_used, $cl['url'] );
				}
				$cur_y += $h_used;
			}
			$pdf->set_y( $cy + $c_box_h + 14.0 );
		} else {
			$pdf->advance_y( 6.0 );
		}
	}

	private static function measure_kv_block_height( RHW_PDF_Writer $pdf, $rows, $max_w ) {
		$label_w = 104.0;
		$val_w   = max( 60.0, $max_w - $label_w );
		$total_h = 0.0;
		foreach ( $rows as $row ) {
			$wrapped  = $pdf->wrap_text( (string) $row[1], $val_w, 'R', 8.8 );
			$total_h += max( 14.0, count( $wrapped ) * 12.0 ) + 4.0;
		}
		return $total_h;
	}

	private static function draw_kv_block( RHW_PDF_Writer $pdf, $rows, $x, $start_y, $max_w ) {
		$label_w = 104.0;
		$val_w   = max( 60.0, $max_w - $label_w );
		$cur_y   = $start_y;
		foreach ( $rows as $row ) {
			$wrapped = $pdf->wrap_text( (string) $row[1], $val_w, 'R', 8.8 );
			$row_h   = max( 14.0, count( $wrapped ) * 12.0 ) + 4.0;
			$pdf->draw_line( $row[0] . ':', $x, $cur_y + 8.5, 'B', 8.5, self::$text_muted );
			$pdf->draw_lines( $wrapped, $x + $label_w, $cur_y, 12.0, 'R', 8.8, self::$text_dark );
			$cur_y += $row_h;
		}
	}

	/**
	 * Render Line Item Estimate table + Subtotal / Service Charge / Tax / Total.
	 */
	private static function render_line_items_section( RHW_PDF_Writer $pdf, $calc ) {
		self::render_section_heading( $pdf, 'Line Item Estimate' );

		$x      = $pdf->get_margin();
		$w      = $pdf->get_content_width();
		$symbol = isset( $calc['currency_symbol'] ) ? $calc['currency_symbol'] : '$';
		$pos    = isset( $calc['currency_position'] ) ? $calc['currency_position'] : 'before';

		$cols = array(
			'desc' => 232.0,
			'cat'  => 96.0,
			'note' => 114.0,
			'amt'  => $w - 442.0, // 90pt
		);

		$draw_table_header = function() use ( $pdf, $x, $w, $cols ) {
			$hy = $pdf->get_y();
			$hh = 24.0;
			$pdf->rect( $x, $hy, $w, $hh, 'FD', self::$cream_bg, self::$border_rgb, 0.7 );
			$cx = $x + 8.0;
			$pdf->draw_line( 'Item Description', $cx, $hy + 15.0, 'B', 8.5, self::$burgundy );
			$cx += $cols['desc'];
			$pdf->draw_line( 'Category', $cx, $hy + 15.0, 'B', 8.5, self::$burgundy );
			$cx += $cols['cat'];
			$pdf->draw_line( 'Quantity / Note', $cx, $hy + 15.0, 'B', 8.5, self::$burgundy );
			$cx += $cols['note'];
			$pdf->draw_line( 'Amount', $cx, $hy + 15.0, 'B', 8.5, self::$burgundy, 'R', $cols['amt'] - 16.0 );
			$pdf->set_y( $hy + $hh );
		};

		$draw_table_header();

		$line_items = ! empty( $calc['line_items'] ) && is_array( $calc['line_items'] ) ? $calc['line_items'] : array();
		if ( empty( $line_items ) ) {
			$ry = $pdf->get_y();
			$pdf->rect( $x, $ry, $w, 26.0, 'FD', array( 255, 255, 255 ), self::$border_rgb, 0.6 );
			$pdf->draw_line( 'No priced line items recorded.', $x + 8.0, $ry + 16.0, 'I', 9.0, self::$text_muted );
			$pdf->set_y( $ry + 26.0 );
		} else {
			foreach ( $line_items as $idx => $item ) {
				$title = isset( $item['title'] ) ? $item['title'] : '';
				$cat   = isset( $item['category'] ) ? $item['category'] : '';
				$note  = ! empty( $item['note'] ) ? $item['note'] : ( isset( $item['quantity'] ) ? (string) $item['quantity'] : '' );
				$amt   = RHW_Pricing_Engine::format_money( isset( $item['amount'] ) ? $item['amount'] : 0, $symbol, $pos );

				$l_title = $pdf->wrap_text( $title, $cols['desc'] - 14.0, 'B', 8.8 );
				$l_cat   = $pdf->wrap_text( $cat, $cols['cat'] - 10.0, 'R', 8.5 );
				$l_note  = $pdf->wrap_text( $note, $cols['note'] - 10.0, 'R', 8.5 );

				$max_lines = max( count( $l_title ), count( $l_cat ), count( $l_note ) );
				$row_h     = max( 22.0, ( $max_lines * 11.5 ) + 10.0 );

				if ( $pdf->check_page_break( $row_h ) ) {
					$draw_table_header();
				}

				$ry      = $pdf->get_y();
				$fill_bg = ( 1 === $idx % 2 ) ? self::$alt_bg : array( 255, 255, 255 );
				$pdf->rect( $x, $ry, $w, $row_h, 'FD', $fill_bg, self::$border_rgb, 0.5 );

				$cx = $x + 8.0;
				$pdf->draw_lines( $l_title, $cx, $ry + 6.0, 11.5, 'B', 8.8, self::$text_dark );
				$cx += $cols['desc'];
				$pdf->draw_lines( $l_cat, $cx, $ry + 6.0, 11.5, 'R', 8.5, self::$text_muted );
				$cx += $cols['cat'];
				$pdf->draw_lines( $l_note, $cx, $ry + 6.0, 11.5, 'R', 8.5, self::$text_dark );
				$cx += $cols['note'];
				$pdf->draw_line( $amt, $cx, $ry + 14.5, 'B', 8.8, self::$text_dark, 'R', $cols['amt'] - 16.0 );

				$pdf->set_y( $ry + $row_h );
			}
		}

		// Summary rows (Subtotal, Service Charge, Tax, Estimated Cost).
		$summary_rows = array(
			array(
				'label'     => 'Subtotal:',
				'amount'    => RHW_Pricing_Engine::format_money( isset( $calc['subtotal'] ) ? $calc['subtotal'] : 0, $symbol, $pos ),
				'highlight' => false,
			),
		);
		if ( ! empty( $calc['service_charge'] ) && (float) $calc['service_charge'] > 0 ) {
			$rate           = isset( $calc['service_charge_rate'] ) ? $calc['service_charge_rate'] : 20;
			$summary_rows[] = array(
				'label'     => sprintf( 'F&B Service Charge (%s%% on F&B):', $rate ),
				'amount'    => RHW_Pricing_Engine::format_money( $calc['service_charge'], $symbol, $pos ),
				'highlight' => false,
			);
		}
		if ( ! empty( $calc['tax_amount'] ) && (float) $calc['tax_amount'] > 0 ) {
			$tax_r          = isset( $calc['tax_rate'] ) ? $calc['tax_rate'] : 15;
			$summary_rows[] = array(
				'label'     => sprintf( 'Government Tax (%s%%):', $tax_r ),
				'amount'    => RHW_Pricing_Engine::format_money( $calc['tax_amount'], $symbol, $pos ),
				'highlight' => false,
			);
		}
		$summary_rows[] = array(
			'label'     => 'Estimated Cost:',
			'amount'    => RHW_Pricing_Engine::format_money( isset( $calc['grand_total'] ) ? $calc['grand_total'] : 0, $symbol, $pos ),
			'highlight' => true,
		);

		$totals_h = ( count( $summary_rows ) * 24.0 ) + 4.0;
		$pdf->check_page_break( $totals_h + 26.0 );

		$label_box_w = $cols['desc'] + $cols['cat'] + $cols['note'] - 8.0;
		$amt_x       = $x + $cols['desc'] + $cols['cat'] + $cols['note'] + 8.0;
		$amt_w       = $cols['amt'] - 16.0;

		foreach ( $summary_rows as $srow ) {
			$sy = $pdf->get_y();
			$rh = ! empty( $srow['highlight'] ) ? 28.0 : 22.0;
			if ( ! empty( $srow['highlight'] ) ) {
				$pdf->rect( $x, $sy, $w, $rh, 'FD', self::$cream_bg, self::$gold, 1.0 );
				$pdf->draw_line( $srow['label'], $x + 8.0, $sy + 17.5, 'B', 11.0, self::$burgundy, 'R', $label_box_w - 8.0 );
				$pdf->draw_line( $srow['amount'], $amt_x, $sy + 17.5, 'B', 11.5, self::$burgundy, 'R', $amt_w );
			} else {
				$pdf->rect( $x, $sy, $w, $rh, 'FD', array( 255, 255, 255 ), self::$border_rgb, 0.5 );
				$pdf->draw_line( $srow['label'], $x + 8.0, $sy + 14.5, 'B', 9.0, self::$text_muted, 'R', $label_box_w - 8.0 );
				$pdf->draw_line( $srow['amount'], $amt_x, $sy + 14.5, 'B', 9.2, self::$text_dark, 'R', $amt_w );
			}
			$pdf->set_y( $sy + $rh );
		}

		$disc_lines = $pdf->wrap_text(
			'Disclaimer: This preliminary quote is an estimated guide based on selected options and standard venue rates. Final pricing is confirmed upon formal proposal.',
			$w,
			'I',
			8.0
		);
		$pdf->advance_y( 6.0 );
		$pdf->draw_lines( $disc_lines, $x, $pdf->get_y(), 10.5, 'I', 8.0, self::$text_muted );
		$pdf->advance_y( ( count( $disc_lines ) * 10.5 ) + 12.0 );
	}

	/**
	 * Render Full Questionnaire Responses table using actual Question & Choice labels.
	 */
	private static function render_questionnaire_section( RHW_PDF_Writer $pdf, $responses ) {
		self::render_section_heading( $pdf, 'Full Questionnaire Responses' );

		$x     = $pdf->get_margin();
		$w     = $pdf->get_content_width();
		$col_q = 215.0;
		$col_a = $w - $col_q;

		$draw_q_header = function() use ( $pdf, $x, $w, $col_q ) {
			$hy = $pdf->get_y();
			$hh = 24.0;
			$pdf->rect( $x, $hy, $w, $hh, 'FD', self::$cream_bg, self::$border_rgb, 0.7 );
			$pdf->draw_line( 'Question', $x + 8.0, $hy + 15.0, 'B', 8.5, self::$burgundy );
			$pdf->draw_line( 'Client Response', $x + $col_q + 8.0, $hy + 15.0, 'B', 8.5, self::$burgundy );
			$pdf->set_y( $hy + $hh );
		};

		$draw_q_header();

		if ( empty( $responses ) ) {
			$ry = $pdf->get_y();
			$pdf->rect( $x, $ry, $w, 26.0, 'FD', array( 255, 255, 255 ), self::$border_rgb, 0.6 );
			$pdf->draw_line( 'No questionnaire responses recorded.', $x + 8.0, $ry + 16.0, 'I', 9.0, self::$text_muted );
			$pdf->set_y( $ry + 36.0 );
			return;
		}

		foreach ( $responses as $idx => $resp ) {
			$q_text = isset( $resp['label'] ) ? (string) $resp['label'] : '';
			$a_val  = isset( $resp['display_value'] ) ? (string) $resp['display_value'] : '';

			if ( '' === trim( $a_val ) ) {
				$a_val = '—';
			} elseif ( ! empty( $resp['is_image'] ) ) {
				$a_val = '[Uploaded Image — shown below] ' . basename( strtok( $a_val, '?' ) ) . "\n" . $a_val;
			} elseif ( ! empty( $resp['is_file'] ) && ! empty( $resp['is_url'] ) ) {
				$a_val = '[Uploaded File] ' . basename( strtok( $a_val, '?' ) ) . "\n" . $a_val;
			}

			$l_q   = $pdf->wrap_text( $q_text, $col_q - 16.0, 'B', 8.6 );
			$l_a   = $pdf->wrap_text( $a_val, $col_a - 16.0, 'R', 8.8 );
			$lines = max( count( $l_q ), count( $l_a ) );
			$row_h = max( 22.0, ( $lines * 11.5 ) + 10.0 );

			if ( $pdf->check_page_break( $row_h ) ) {
				$draw_q_header();
			}

			$ry      = $pdf->get_y();
			$fill_bg = ( 1 === $idx % 2 ) ? self::$alt_bg : array( 255, 255, 255 );
			$pdf->rect( $x, $ry, $w, $row_h, 'FD', $fill_bg, self::$border_rgb, 0.5 );
			$pdf->line( $x + $col_q, $ry, $x + $col_q, $ry + $row_h, self::$border_rgb, 0.5 );

			$pdf->draw_lines( $l_q, $x + 8.0, $ry + 6.0, 11.5, 'B', 8.6, self::$text_muted );
			$pdf->draw_lines( $l_a, $x + $col_q + 8.0, $ry + 6.0, 11.5, 'R', 8.8, self::$text_dark );

			if ( ! empty( $resp['is_url'] ) && ! empty( $resp['raw_value'] ) ) {
				$pdf->add_link( $x + $col_q + 8.0, $ry + 4.0, $col_a - 16.0, $row_h - 8.0, $resp['raw_value'] );
			}

			$pdf->set_y( $ry + $row_h );
		}

		$pdf->advance_y( 16.0 );
	}

	/**
	 * Render Uploaded Images / Design Inspiration files visually inside the PDF.
	 */
	private static function render_uploaded_images_section( RHW_PDF_Writer $pdf, $responses, $submission ) {
		$uploads     = array();
		$local_paths = isset( $submission['uploaded_file_paths'] ) && is_array( $submission['uploaded_file_paths'] )
			? $submission['uploaded_file_paths']
			: array();

		foreach ( $responses as $resp ) {
			if ( empty( $resp['raw_value'] ) || ! is_string( $resp['raw_value'] ) ) {
				continue;
			}
			if ( empty( $resp['is_file'] ) && empty( $resp['is_image'] ) ) {
				continue;
			}
			$key       = $resp['key'];
			$url       = $resp['raw_value'];
			$source    = ! empty( $local_paths[ $key ] ) ? $local_paths[ $key ] : $url;
			$uploads[] = array(
				'label'    => $resp['label'],
				'url'      => $url,
				'source'   => $source,
				'is_image' => ! empty( $resp['is_image'] ),
			);
		}

		if ( empty( $uploads ) ) {
			return;
		}

		self::render_section_heading( $pdf, 'Uploaded Design Inspiration & Files' );

		$x = $pdf->get_margin();
		$w = $pdf->get_content_width();

		foreach ( $uploads as $item ) {
			$img_res = null;
			if ( $item['is_image'] ) {
				$img_res = $pdf->load_image_resource( $item['source'] );
				if ( ! $img_res && $item['source'] !== $item['url'] ) {
					$img_res = $pdf->load_image_resource( $item['url'] );
				}
			}

			$title_lines = $pdf->wrap_text( $item['label'], $w - 24.0, 'B', 9.5 );
			$url_lines   = $pdf->wrap_text( $item['url'], $w - 24.0, 'R', 8.0 );
			$title_h     = count( $title_lines ) * 12.5;
			$url_h       = count( $url_lines ) * 10.5;

			if ( $img_res ) {
				$max_img_w = $w - 32.0;
				$max_img_h = 240.0;
				$ratio     = min( $max_img_w / $img_res['w'], $max_img_h / $img_res['h'] );
				// Prevent small images from stretching larger than natural 72dpi size unless tiny.
				$ratio  = min( $ratio, 1.5 );
				$draw_w = $img_res['w'] * $ratio;
				$draw_h = $img_res['h'] * $ratio;

				$card_h = 12.0 + $title_h + 8.0 + $draw_h + 8.0 + $url_h + 10.0;
				$pdf->check_page_break( $card_h + 8.0 );

				$cy = $pdf->get_y();
				$pdf->rect( $x, $cy, $w, $card_h, 'FD', array( 255, 255, 255 ), self::$border_rgb, 0.7 );
				$pdf->draw_lines( $title_lines, $x + 12.0, $cy + 10.0, 12.5, 'B', 9.5, self::$burgundy );

				$img_x = $x + ( ( $w - $draw_w ) / 2.0 );
				$img_y = $cy + 10.0 + $title_h + 8.0;
				$pdf->rect( $img_x - 2.0, $img_y - 2.0, $draw_w + 4.0, $draw_h + 4.0, 'D', null, self::$border_rgb, 0.5 );
				$pdf->draw_image( $img_res, $img_x, $img_y, $draw_w, $draw_h );

				$url_y = $img_y + $draw_h + 8.0;
				$pdf->draw_lines( $url_lines, $x + 12.0, $url_y, 10.5, 'R', 8.0, array( 0, 115, 170 ) );
				$pdf->add_link( $x + 12.0, $url_y, $w - 24.0, $url_h, $item['url'] );

				$pdf->set_y( $cy + $card_h + 12.0 );
			} else {
				// Non-image upload (e.g., PDF inspiration document).
				$fname  = basename( strtok( $item['url'], '?' ) );
				$card_h = 12.0 + $title_h + 16.0 + $url_h + 10.0;
				$pdf->check_page_break( $card_h + 8.0 );

				$cy = $pdf->get_y();
				$pdf->rect( $x, $cy, $w, $card_h, 'FD', self::$alt_bg, self::$border_rgb, 0.7 );
				$pdf->draw_lines( $title_lines, $x + 12.0, $cy + 10.0, 12.5, 'B', 9.5, self::$burgundy );
				$pdf->draw_line( 'Attached File: ' . $fname, $x + 12.0, $cy + 10.0 + $title_h + 11.0, 'R', 8.8, self::$text_dark );

				$url_y = $cy + 10.0 + $title_h + 16.0;
				$pdf->draw_lines( $url_lines, $x + 12.0, $url_y, 10.5, 'R', 8.0, array( 0, 115, 170 ) );
				$pdf->add_link( $x + 12.0, $url_y, $w - 24.0, $url_h, $item['url'] );

				$pdf->set_y( $cy + $card_h + 12.0 );
			}
		}
	}
}
