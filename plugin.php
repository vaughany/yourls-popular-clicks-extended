<?php

/*
Plugin Name:    Popular Clicks Extended
Plugin URI:     https://github.com/vaughany/yourls-popular-clicks-extended
Description:    A YOURLS plugin showing the most popular clicks for given time periods.
Version:        0.5
Release date:   2026-10-11
Requires:       YOURLS 1.10, PHP 8.1
Author:         Paul Vaughan
Author URI:     https://github.com/vaughany/
*/

/**
 * TODO:
 *      Config options to toggle the reports: do you really need recent 5 mins or 4 years ago?
 *      Issue/idea on YOURLS repo: https://github.com/yourls/yourls/issues/1732
 */

/**
 * https://docs.yourls.org/development/plugins.html
 * https://docs.yourls.org/development/form-security.html
 * https://github.com/YOURLS/awesome#plugins
 */

// No direct call.
if ( !defined( 'YOURLS_ABSPATH' ) ) { die(); }

// Some version details, same as at the top of this file, for use in the page footer.
const VAUGHANY_PCE_REL_VER  = '0.5';
const VAUGHANY_PCE_REL_DATE = '2026-10-11';

// Repository URL.
const VAUGHANY_PCE_REPO = 'https://github.com/vaughany/yourls-popular-clicks-extended';

// Text domain for translatable strings.
const VAUGHANY_PCE_DOMAIN = 'vaughany_pce';

// Settings, stored in the YOURLS options table.
const VAUGHANY_PCE_OPTION   = 'vaughany_pce_settings';
const VAUGHANY_PCE_DEFAULTS = [
    'rows'          => 10,      // Rows per report, 1 to 100.
    'show_on_admin' => false,   // Show recent clicks below the links table on the main admin page.
    'exclude'       => [],      // Short URL patterns to leave out of every report, e.g. 'z123*'.
    'fy_start'      => 0,       // Month the financial year starts, 1 to 12, or 0 for no financial year reports.
];

// Limits on the exclusion patterns.
const VAUGHANY_PCE_MAX_EXCLUDE        = 50;
const VAUGHANY_PCE_MAX_EXCLUDE_LENGTH = 200;

// Period shown on the traffic sources tab, and by the API, if none is chosen.
const VAUGHANY_PCE_DEFAULT_PERIOD = 'last_24_hours';

// This version relies on APIs from YOURLS 1.10.
if ( version_compare( YOURLS_VERSION, '1.10', '<' ) ) {
    yourls_add_notice( 'Popular Clicks Extended requires YOURLS 1.10 or later and has not been loaded.', 'error' );
    return;
}

// Adding actions.
yourls_add_action( 'plugins_loaded', 'vaughany_pce_init' );
yourls_add_action( 'load-vaughany_pce', 'vaughany_pce_handle_post' );
yourls_add_action( 'html_head', 'vaughany_pce_html_head' );
yourls_add_action( 'admin_page_after_table', 'vaughany_pce_admin_recent_clicks' );
yourls_add_filter( 'api_actions', 'vaughany_pce_api_actions' );

/**
 * Standard init function.
 */
function vaughany_pce_init(): void {
    yourls_register_plugin_page( 'vaughany_pce', yourls__( 'Popular Clicks Extended', VAUGHANY_PCE_DOMAIN ), 'vaughany_pce_display_page' );
}

/**
 * Get the settings, with defaults for anything missing or invalid.
 */
function vaughany_pce_get_settings(): array {
    $settings = yourls_get_option( VAUGHANY_PCE_OPTION, [] );
    if ( !is_array( $settings ) ) {
        $settings = [];
    }
    $settings = array_merge( VAUGHANY_PCE_DEFAULTS, $settings );

    return [
        'rows'          => max( 1, min( 100, (int) $settings['rows'] ) ),
        'show_on_admin' => (bool) $settings['show_on_admin'],
        'exclude'       => vaughany_pce_clean_patterns( (array) $settings['exclude'] ),
        'fy_start'      => max( 0, min( 12, (int) $settings['fy_start'] ) ),
    ];
}

/**
 * Tidy a list of exclusion patterns: trimmed, no blanks or duplicates, and within the limits.
 *
 * @param array $patterns
 * @return string[]
 */
function vaughany_pce_clean_patterns( array $patterns ): array {
    $clean = [];
    foreach ( $patterns as $pattern ) {
        $pattern = trim( (string) $pattern );
        if ( $pattern !== '' && strlen( $pattern ) <= VAUGHANY_PCE_MAX_EXCLUDE_LENGTH ) {
            $clean[] = $pattern;
        }
    }

    return array_slice( array_values( array_unique( $clean ) ), 0, VAUGHANY_PCE_MAX_EXCLUDE );
}

/**
 * Save the settings form, before any page output.
 */
function vaughany_pce_handle_post(): void {
    if ( !isset( $_POST['vaughany_pce_save'] ) ) {
        return;
    }

    // Dies with a 403 if the nonce is missing or invalid.
    yourls_verify_nonce( 'vaughany_pce_settings' );

    $d    = VAUGHANY_PCE_DOMAIN;
    $rows = filter_var( $_POST['rows'] ?? '', FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1, 'max_range' => 100 ] ] );
    if ( $rows === false ) {
        yourls_add_notice( yourls__( 'Rows per report must be a whole number from 1 to 100.', $d ), 'error' );
        return;
    }

    $fy_start = filter_var( $_POST['fy_start'] ?? 0, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0, 'max_range' => 12 ] ] );
    if ( $fy_start === false ) {
        $fy_start = 0;
    }

    $exclude = vaughany_pce_clean_patterns( preg_split( '/\R/', (string) ( $_POST['exclude'] ?? '' ) ) );

    yourls_update_option( VAUGHANY_PCE_OPTION, [
        'rows'          => $rows,
        'show_on_admin' => !empty( $_POST['show_on_admin'] ),
        'exclude'       => $exclude,
        'fy_start'      => $fy_start,
    ] );

    yourls_add_notice( yourls__( 'Settings saved.', $d ), 'success' );
}

/**
 * Load YOURLS's own tab styles and script (as used on the stats pages) on this plugin's page only.
 *
 * @param array|string $context YOURLS passes the context wrapped in an array.
 */
function vaughany_pce_html_head( $context ): void {
    if ( is_array( $context ) ) {
        $context = $context[0] ?? '';
    }
    if ( $context !== 'plugin_page_vaughany_pce' ) {
        return;
    }

    $version = '?v=' . rawurlencode( YOURLS_VERSION );
    echo '<link rel="stylesheet" href="' . yourls_esc_url( yourls_site_url( false ) . '/css/infos.css' . $version ) . '" type="text/css" media="screen" />' . PHP_EOL;
    echo '<script src="' . yourls_esc_url( yourls_site_url( false ) . '/js/infos.js' . $version ) . '" type="text/javascript"></script>' . PHP_EOL;
    echo '<style>.vaughany-pce-table{margin-bottom:1.5em;border-collapse:collapse}.vaughany-pce-table th,.vaughany-pce-table td{padding:3px 8px;text-align:left;vertical-align:top}.vaughany-pce-table tbody tr:nth-child(odd){background:#E3F3FF}.vaughany-pce-table td.vaughany-pce-num{text-align:right}</style>' . PHP_EOL;
}

/**
 * The difference in seconds between local (display) time and UTC, which is what YOURLS stores.
 * Respects YOURLS_HOURS_OFFSET and the YOURLS Time Zones plugin.
 */
function vaughany_pce_offset( int $now ): int {
    return (int) yourls_get_timestamp( $now ) - $now;
}

/**
 * Every reporting period, keyed by an ID which is also used by the traffic sources tab and the API.
 *
 * 'calendar' periods are whole hours, days, weeks, months and (financial) years in local time.
 * 'rolling' periods run from n seconds ago until now. Note that 24 hours is not the same as 'yesterday'.
 *
 * @param int $now      Current Unix time.
 * @param int $offset   Local time offset, from vaughany_pce_offset().
 * @param int $fy_start Month the financial year starts, or 0 for none.
 * @return array ID => ['group' => 'calendar'|'rolling', 'desc' => string, 'from' => ?string, 'to' => ?string],
 *               where 'from' (inclusive) and 'to' (exclusive) are UTC 'Y-m-d H:i:s' strings, or null for no limit.
 */
function vaughany_pce_periods( int $now, int $offset, int $fy_start ): array {
    $d     = VAUGHANY_PCE_DOMAIN;
    $local = new DateTimeImmutable( '@' . ( $now + $offset ) );

    $hour  = $local->setTime( (int) $local->format( 'G' ), 0 );
    $day   = $local->setTime( 0, 0 );
    $week  = $day->modify( 'monday this week' );
    $month = $day->modify( 'first day of this month' );
    $year  = $day->setDate( (int) $local->format( 'Y' ), 1, 1 );

    $prev_hour  = $hour->modify( '-1 hour' );
    $prev_day   = $day->modify( '-1 day' );
    $prev_week  = $week->modify( '-7 days' );
    $prev_month = $month->modify( '-1 month' );
    $prev_year  = $year->modify( '-1 year' );

    $date = fn( string $format, DateTimeImmutable $dt ): string => yourls_date_i18n( $format, $dt->getTimestamp() );

    $calendar = [
        'this_hour'      => [ $hour,       $hour->modify( '+1 hour' ),   sprintf( yourls__( 'this hour (%1$s to %2$s) (so far)', $d ), $date( 'jS F Y, ga', $hour ), $date( 'ga', $hour->modify( '+1 hour' ) ) ) ],
        'previous_hour'  => [ $prev_hour,  $hour,                        sprintf( yourls__( 'the previous hour (%1$s to %2$s)', $d ), $date( 'jS F Y, ga', $prev_hour ), $date( 'ga', $hour ) ) ],
        'today'          => [ $day,        $day->modify( '+1 day' ),     sprintf( yourls__( 'today (%s) (so far)', $d ), $date( 'jS F Y', $day ) ) ],
        'yesterday'      => [ $prev_day,   $day,                         sprintf( yourls__( 'yesterday (%s)', $d ), $date( 'jS F Y', $prev_day ) ) ],
        'this_week'      => [ $week,       $week->modify( '+7 days' ),   sprintf( yourls__( 'this week (beginning %s) (so far)', $d ), $date( 'jS F Y', $week ) ) ],
        'previous_week'  => [ $prev_week,  $week,                        sprintf( yourls__( 'last week (beginning %s)', $d ), $date( 'jS F Y', $prev_week ) ) ],
        'this_month'     => [ $month,      $month->modify( '+1 month' ), sprintf( yourls__( 'this month (%s) (so far)', $d ), $date( 'F Y', $month ) ) ],
        'previous_month' => [ $prev_month, $month,                       sprintf( yourls__( 'last month (%s)', $d ), $date( 'F Y', $prev_month ) ) ],
        'this_year'      => [ $year,       $year->modify( '+1 year' ),   sprintf( yourls__( 'this year (%s) (so far)', $d ), $date( 'Y', $year ) ) ],
        'previous_year'  => [ $prev_year,  $year,                        sprintf( yourls__( 'last year (%s)', $d ), $date( 'Y', $prev_year ) ) ],
    ];

    if ( $fy_start > 0 ) {
        // The financial year containing today starts this year or last year, depending on the month.
        $fy = $day->setDate( (int) $local->format( 'Y' ), $fy_start, 1 );
        if ( $fy > $day ) {
            $fy = $fy->modify( '-1 year' );
        }
        $prev_fy = $fy->modify( '-1 year' );
        $next_fy = $fy->modify( '+1 year' );

        $calendar['this_financial_year']     = [ $fy,      $next_fy, sprintf( yourls__( 'this financial year (%1$s to %2$s) (so far)', $d ), $date( 'F Y', $fy ), $date( 'F Y', $next_fy->modify( '-1 month' ) ) ) ];
        $calendar['previous_financial_year'] = [ $prev_fy, $fy,      sprintf( yourls__( 'last financial year (%1$s to %2$s)', $d ), $date( 'F Y', $prev_fy ), $date( 'F Y', $fy->modify( '-1 month' ) ) ) ];
    }

    $periods = [];
    foreach ( $calendar as $id => [ $from, $to, $desc ] ) {
        $periods[ $id ] = [
            'group' => 'calendar',
            'desc'  => $desc,
            'from'  => vaughany_pce_to_db_time( $from->getTimestamp(), $offset ),
            'to'    => vaughany_pce_to_db_time( $to->getTimestamp(), $offset ),
        ];
    }

    $minute  = 60;
    $hours   = 60 * $minute;
    $days    = 24 * $hours;
    $rolling = [
        'last_5_minutes'  => [ 5 * $minute,     yourls__( 'the last 5 minutes', $d ) ],
        'last_30_minutes' => [ 30 * $minute,    yourls__( 'the last 30 minutes', $d ) ],
        'last_hour'       => [ $hours,          yourls__( 'the last hour', $d ) ],
        'last_6_hours'    => [ 6 * $hours,      yourls__( 'the last 6 hours', $d ) ],
        'last_12_hours'   => [ 12 * $hours,     yourls__( 'the last 12 hours', $d ) ],
        'last_24_hours'   => [ 24 * $hours,     yourls__( 'the last 24 hours', $d ) ],
        'last_2_days'     => [ 2 * $days,       yourls__( 'the last 2 days', $d ) ],
        'last_7_days'     => [ 7 * $days,       yourls__( 'the last week', $d ) ],
        'last_14_days'    => [ 14 * $days,      yourls__( 'the last 2 weeks', $d ) ],
        'last_30_days'    => [ 30 * $days,      yourls__( 'the last month', $d ) ],
        'last_60_days'    => [ 60 * $days,      yourls__( 'the last 2 months', $d ) ],
        'last_90_days'    => [ 90 * $days,      yourls__( 'the last 3 months', $d ) ],
        'last_180_days'   => [ 180 * $days,     yourls__( 'the last 6 months', $d ) ],
        'last_365_days'   => [ 365 * $days,     yourls__( 'the last year', $d ) ],
        'last_2_years'    => [ 2 * 365 * $days, yourls__( 'the last 2 years', $d ) ],
        'last_3_years'    => [ 3 * 365 * $days, yourls__( 'the last 3 years', $d ) ],
        'last_4_years'    => [ 4 * 365 * $days, yourls__( 'the last 4 years', $d ) ],
        // ...and the catch-all:
        'all_time'        => [ null,            yourls__( 'all time', $d ) ],
    ];

    foreach ( $rolling as $id => [ $seconds, $desc ] ) {
        $periods[ $id ] = [
            'group' => 'rolling',
            'desc'  => $desc,
            'from'  => $seconds === null ? null : gmdate( 'Y-m-d H:i:s', $now - $seconds ),
            'to'    => null,
        ];
    }

    return $periods;
}

/**
 * Periods for the current time and settings.
 */
function vaughany_pce_current_periods( array $settings ): array {
    $now = time();
    return vaughany_pce_periods( $now, vaughany_pce_offset( $now ), $settings['fy_start'] );
}

/**
 * Format a Unix timestamp (local time) as a UTC string for querying the database.
 */
function vaughany_pce_to_db_time( int $local_timestamp, int $offset ): string {
    return gmdate( 'Y-m-d H:i:s', $local_timestamp - $offset );
}

/**
 * SQL conditions for a period and the exclusion patterns, with their bound values.
 *
 * @param array  $period  A period from vaughany_pce_periods().
 * @param array  $exclude Exclusion patterns: '*' matches any characters, anything else must match exactly.
 * @param string $column  The short URL (keyword) column to check the patterns against.
 * @param array  $binds   Bound values, added to.
 * @return string[]
 */
function vaughany_pce_where( array $period, array $exclude, string $column, array &$binds ): array {
    $where = [];

    if ( $period['from'] !== null ) {
        $where[]       = 'l.click_time >= :from';
        $binds['from'] = $period['from'];
    }
    if ( $period['to'] !== null ) {
        $where[]     = 'l.click_time < :to';
        $binds['to'] = $period['to'];
    }

    foreach ( array_values( $exclude ) as $i => $pattern ) {
        $where[]                = $column . ' NOT LIKE :exclude_' . $i;
        $binds[ 'exclude_' . $i ] = str_replace( '*', '%', addcslashes( $pattern, '\\%_' ) );
    }

    return $where;
}

/**
 * Query the database for the number of clicks per link in a period.
 *
 * @param array $period  A period from vaughany_pce_periods().
 * @param int   $rows    The maximum number of rows to return.
 * @param array $exclude Exclusion patterns.
 * @return array List of objects with keyword, url, title and clicks properties.
 */
function vaughany_pce_get_popular( array $period, int $rows, array $exclude ): array {
    $binds = [ 'rows' => $rows ];
    $where = vaughany_pce_where( $period, $exclude, 'u.keyword', $binds );

    $sql = 'SELECT u.keyword AS keyword, u.url AS url, u.title AS title, COUNT(*) AS clicks
        FROM `' . YOURLS_DB_TABLE_LOG . '` l
        INNER JOIN `' . YOURLS_DB_TABLE_URL . '` u ON l.shorturl = u.keyword'
        . ( $where ? ' WHERE ' . implode( ' AND ', $where ) : '' ) . '
        GROUP BY u.keyword, u.url, u.title
        ORDER BY clicks DESC, u.keyword ASC
        LIMIT :rows';

    return yourls_get_db( 'read-vaughany_pce_popular' )->fetchObjects( $sql, $binds ) ?: [];
}

/**
 * Query the database for the number of clicks per referrer in a period, for all short URLs or one.
 *
 * @param array  $period  A period from vaughany_pce_periods().
 * @param int    $rows    The maximum number of rows to return.
 * @param array  $exclude Exclusion patterns.
 * @param string $keyword A short URL (keyword) to report on, or '' for all.
 * @return array List of objects with referrer and clicks properties.
 */
function vaughany_pce_get_referrers( array $period, int $rows, array $exclude, string $keyword ): array {
    $binds = [ 'rows' => $rows ];
    $where = vaughany_pce_where( $period, $exclude, 'l.shorturl', $binds );

    if ( $keyword !== '' ) {
        $where[]          = 'l.shorturl = :keyword';
        $binds['keyword'] = $keyword;
    }

    $sql = 'SELECT l.referrer AS referrer, COUNT(*) AS clicks
        FROM `' . YOURLS_DB_TABLE_LOG . '` l'
        . ( $where ? ' WHERE ' . implode( ' AND ', $where ) : '' ) . '
        GROUP BY l.referrer
        ORDER BY clicks DESC, l.referrer ASC
        LIMIT :rows';

    return yourls_get_db( 'read-vaughany_pce_referrers' )->fetchObjects( $sql, $binds ) ?: [];
}

/**
 * Render a list of popular clicks as a table.
 */
function vaughany_pce_render_table( array $results ): string {
    $d = VAUGHANY_PCE_DOMAIN;

    if ( !$results ) {
        return '<p>' . yourls_esc_html__( 'No results for the chosen time period.', $d ) . '</p>' . PHP_EOL;
    }

    $total = 0;
    $out   = '<table class="vaughany-pce-table">' . PHP_EOL;
    $out  .= '<thead><tr><th>' . yourls_esc_html__( 'Clicks', $d ) . '</th><th>' . yourls_esc_html__( 'Short URL', $d ) . '</th><th>' . yourls_esc_html__( 'Website', $d ) . '</th></tr></thead>' . PHP_EOL;
    $out  .= '<tbody>' . PHP_EOL;
    foreach ( $results as $result ) {
        $total += (int) $result->clicks;
        $out   .= '<tr>';
        $out   .= '<td class="vaughany-pce-num">' . (int) $result->clicks . '</td>';
        $out   .= '<td>' . vaughany_pce_short_link( $result->keyword ) . '</td>';
        $out   .= '<td>' . vaughany_pce_long_link( $result->url, $result->title ) . '</td>';
        $out   .= '</tr>' . PHP_EOL;
    }
    $out .= '</tbody>' . PHP_EOL;
    $out .= '<tfoot><tr><td class="vaughany-pce-num"><strong>' . $total . '</strong></td><td colspan="2"><strong>' . yourls_esc_html__( 'Total clicks for the links shown', $d ) . '</strong></td></tr></tfoot>' . PHP_EOL;
    $out .= '</table>' . PHP_EOL;

    return $out;
}

/**
 * Link to a short URL's stats page.
 */
function vaughany_pce_short_link( string $keyword ): string {
    return '<a href="' . yourls_esc_url( yourls_statlink( $keyword ) ) . '">' . yourls_esc_html( $keyword ) . '</a>';
}

/**
 * Link to a long URL, showing its title if there is one.
 */
function vaughany_pce_long_link( string $url, ?string $title ): string {
    $text = ( $title !== null && $title !== '' ) ? $title : $url;
    return '<a href="' . yourls_esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" title="' . yourls_esc_attr( $url ) . '">' . yourls_esc_html( $text ) . '</a>';
}

/**
 * Render the n most recent clicks from the log table.
 *
 * @param int   $rows    The maximum number of rows to show.
 * @param array $exclude Exclusion patterns.
 */
function vaughany_pce_render_log( int $rows, array $exclude ): string {
    $d     = VAUGHANY_PCE_DOMAIN;
    $binds = [ 'rows' => $rows ];
    $where = vaughany_pce_where( [ 'from' => null, 'to' => null ], $exclude, 'u.keyword', $binds );

    $sql = 'SELECT l.click_time AS click_time, l.ip_address AS ip_address, l.country_code AS country_code, l.referrer AS referrer,
            u.keyword AS keyword, u.url AS url, u.title AS title
        FROM `' . YOURLS_DB_TABLE_LOG . '` l
        INNER JOIN `' . YOURLS_DB_TABLE_URL . '` u ON l.shorturl = u.keyword'
        . ( $where ? ' WHERE ' . implode( ' AND ', $where ) : '' ) . '
        ORDER BY l.click_time DESC, l.click_id DESC
        LIMIT :rows';

    $results = yourls_get_db( 'read-vaughany_pce_log' )->fetchObjects( $sql, $binds ) ?: [];

    if ( !$results ) {
        return '<p>' . yourls_esc_html__( 'No logs to display.', $d ) . '</p>' . PHP_EOL;
    }

    $out  = '<table class="vaughany-pce-table">' . PHP_EOL;
    $out .= '<thead><tr><th>' . yourls_esc_html__( 'Time', $d ) . '</th><th>' . yourls_esc_html__( 'Short URL', $d ) . '</th><th>' . yourls_esc_html__( 'Website', $d )
        . '</th><th>' . yourls_esc_html__( 'IP address', $d ) . '</th><th>' . yourls_esc_html__( 'Country', $d ) . '</th><th>' . yourls_esc_html__( 'Referrer', $d ) . '</th></tr></thead>' . PHP_EOL;
    $out .= '<tbody>' . PHP_EOL;
    foreach ( $results as $result ) {
        // Click times are stored in UTC.
        $time = yourls_date_i18n( 'Y-m-d H:i:s', yourls_get_timestamp( (int) strtotime( $result->click_time . ' UTC' ) ) );

        $out .= '<tr>';
        $out .= '<td>' . yourls_esc_html( $time ) . '</td>';
        $out .= '<td>' . vaughany_pce_short_link( $result->keyword ) . '</td>';
        $out .= '<td>' . vaughany_pce_long_link( $result->url, $result->title ) . '</td>';
        $out .= '<td>' . yourls_esc_html( (string) $result->ip_address ) . '</td>';
        $out .= '<td>' . yourls_esc_html( (string) $result->country_code ) . '</td>';
        $out .= '<td>' . yourls_esc_html( (string) $result->referrer ) . '</td>';
        $out .= '</tr>' . PHP_EOL;
    }
    $out .= '</tbody>' . PHP_EOL;
    $out .= '</table>' . PHP_EOL;

    return $out;
}

/**
 * Turn a short URL or keyword, as typed or passed to the API, into a keyword.
 */
function vaughany_pce_keyword_from_input( string $input ): string {
    $input = trim( $input );
    $site  = yourls_get_yourls_site() . '/';
    if ( str_starts_with( $input, $site ) ) {
        $input = substr( $input, strlen( $site ) );
    }

    return yourls_sanitize_keyword( $input, true );
}

/**
 * Render the traffic sources tab: the top referrers for a chosen period, for all short URLs or one.
 */
function vaughany_pce_render_sources( array $periods, array $settings ): string {
    $d         = VAUGHANY_PCE_DOMAIN;
    $period_id = (string) ( $_GET['sources_period'] ?? VAUGHANY_PCE_DEFAULT_PERIOD );
    if ( !isset( $periods[ $period_id ] ) ) {
        $period_id = VAUGHANY_PCE_DEFAULT_PERIOD;
    }
    $keyword = vaughany_pce_keyword_from_input( (string) ( $_GET['sources_keyword'] ?? '' ) );

    // A GET form, so the report can be bookmarked; the fragment opens this tab again.
    $out  = '<form action="' . yourls_esc_url( yourls_admin_url( 'plugins.php' ) . '#stat_tab_sources' ) . '" method="get">' . PHP_EOL;
    $out .= '<input type="hidden" name="page" value="vaughany_pce">' . PHP_EOL;
    $out .= '<p><label for="vaughany_pce_sources_period">' . yourls_esc_html__( 'Period:', $d ) . '</label> ';
    $out .= '<select id="vaughany_pce_sources_period" name="sources_period">' . PHP_EOL;
    $groups = [ 'calendar' => yourls__( 'Calendar periods', $d ), 'rolling' => yourls__( 'Rolling periods', $d ) ];
    foreach ( $groups as $group => $label ) {
        $out .= '<optgroup label="' . yourls_esc_attr( $label ) . '">' . PHP_EOL;
        foreach ( $periods as $id => $period ) {
            if ( $period['group'] === $group ) {
                $out .= '<option value="' . yourls_esc_attr( $id ) . '"' . ( $id === $period_id ? ' selected' : '' ) . '>' . yourls_esc_html( ucfirst( $period['desc'] ) ) . '</option>' . PHP_EOL;
            }
        }
        $out .= '</optgroup>' . PHP_EOL;
    }
    $out .= '</select> ';
    $out .= '<label for="vaughany_pce_sources_keyword">' . yourls_esc_html__( 'Short URL (optional):', $d ) . '</label> ';
    $out .= '<input type="text" id="vaughany_pce_sources_keyword" name="sources_keyword" value="' . yourls_esc_attr( $keyword ) . '" placeholder="' . yourls_esc_attr__( 'All short URLs', $d ) . '" class="text"> ';
    $out .= '<input type="submit" value="' . yourls_esc_attr__( 'Show', $d ) . '" class="button"></p>' . PHP_EOL;
    $out .= '</form>' . PHP_EOL;

    $heading = $keyword === ''
        ? sprintf( yourls__( 'Traffic sources for %s:', $d ), $periods[ $period_id ]['desc'] )
        : sprintf( yourls__( 'Traffic sources for %1$s, for short URL "%2$s":', $d ), $periods[ $period_id ]['desc'], $keyword );
    $out .= '<h3>' . yourls_esc_html( $heading ) . '</h3>' . PHP_EOL;

    if ( $keyword !== '' && !yourls_keyword_is_taken( $keyword ) ) {
        return $out . '<p>' . yourls_esc_html( sprintf( yourls__( 'There is no short URL "%s".', $d ), $keyword ) ) . '</p>' . PHP_EOL;
    }

    $results = vaughany_pce_get_referrers( $periods[ $period_id ], $settings['rows'], $settings['exclude'], $keyword );
    if ( !$results ) {
        return $out . '<p>' . yourls_esc_html__( 'No results for the chosen time period.', $d ) . '</p>' . PHP_EOL;
    }

    $total = 0;
    $out  .= '<table class="vaughany-pce-table">' . PHP_EOL;
    $out  .= '<thead><tr><th>' . yourls_esc_html__( 'Clicks', $d ) . '</th><th>' . yourls_esc_html__( 'Referrer', $d ) . '</th></tr></thead>' . PHP_EOL;
    $out  .= '<tbody>' . PHP_EOL;
    foreach ( $results as $result ) {
        $total += (int) $result->clicks;
        // YOURLS records 'direct' when there was no referrer.
        $referrer = $result->referrer === 'direct' ? yourls__( 'Direct, or no referrer', $d ) : (string) $result->referrer;
        $out     .= '<tr><td class="vaughany-pce-num">' . (int) $result->clicks . '</td><td>' . yourls_esc_html( $referrer ) . '</td></tr>' . PHP_EOL;
    }
    $out .= '</tbody>' . PHP_EOL;
    $out .= '<tfoot><tr><td class="vaughany-pce-num"><strong>' . $total . '</strong></td><td><strong>' . yourls_esc_html__( 'Total clicks for the referrers shown', $d ) . '</strong></td></tr></tfoot>' . PHP_EOL;
    $out .= '</table>' . PHP_EOL;

    return $out;
}

/**
 * Show recent clicks below the links table on the main admin page, if enabled in the settings.
 */
function vaughany_pce_admin_recent_clicks(): void {
    $settings = vaughany_pce_get_settings();
    if ( !$settings['show_on_admin'] ) {
        return;
    }

    echo '<h3>' . yourls_esc_html__( 'Recent clicks', VAUGHANY_PCE_DOMAIN ) . '</h3>' . PHP_EOL;
    echo vaughany_pce_render_log( $settings['rows'], $settings['exclude'] );
}

/**
 * Render the settings form.
 */
function vaughany_pce_render_settings( array $settings ): string {
    $d      = VAUGHANY_PCE_DOMAIN;
    $action = yourls_admin_url( 'plugins.php?page=vaughany_pce' ) . '#stat_tab_settings';

    $out  = '<form action="' . yourls_esc_url( $action ) . '" method="post">' . PHP_EOL;
    $out .= yourls_nonce_field( 'vaughany_pce_settings', 'nonce', false, false ) . PHP_EOL;
    $out .= '<p><label for="vaughany_pce_rows">' . yourls_esc_html__( 'Rows per report:', $d ) . '</label> ';
    $out .= '<input type="number" id="vaughany_pce_rows" name="rows" min="1" max="100" value="' . (int) $settings['rows'] . '" class="text"></p>' . PHP_EOL;

    $out .= '<p><label><input type="checkbox" name="show_on_admin" value="1"' . ( $settings['show_on_admin'] ? ' checked' : '' ) . '> ';
    $out .= yourls_esc_html__( 'Show recent clicks (including visitor IP addresses) below the links table on the main admin page.', $d ) . '</label></p>' . PHP_EOL;

    $out .= '<p><label for="vaughany_pce_exclude">' . yourls_esc_html__( 'Leave these short URLs out of every report, one per line. * matches any characters: e.g. z123* leaves out every short URL starting with z123.', $d ) . '</label><br>' . PHP_EOL;
    $out .= '<textarea id="vaughany_pce_exclude" name="exclude" rows="4" cols="40">' . yourls_esc_textarea( implode( "\n", $settings['exclude'] ) ) . '</textarea></p>' . PHP_EOL;

    $out .= '<p><label for="vaughany_pce_fy_start">' . yourls_esc_html__( 'Financial year starts in:', $d ) . '</label> ';
    $out .= '<select id="vaughany_pce_fy_start" name="fy_start">' . PHP_EOL;
    $out .= '<option value="0">' . yourls_esc_html__( 'No financial year reports', $d ) . '</option>' . PHP_EOL;
    for ( $m = 1; $m <= 12; $m++ ) {
        $out .= '<option value="' . $m . '"' . ( $m === $settings['fy_start'] ? ' selected' : '' ) . '>' . yourls_esc_html( yourls_date_i18n( 'F', gmmktime( 0, 0, 0, $m, 1, 2000 ) ) ) . '</option>' . PHP_EOL;
    }
    $out .= '</select></p>' . PHP_EOL;

    $out .= '<p><input type="submit" name="vaughany_pce_save" value="' . yourls_esc_attr__( 'Save settings', $d ) . '" class="button primary"></p>' . PHP_EOL;
    $out .= '</form>' . PHP_EOL;

    return $out;
}

/**
 * The plugin's admin page.
 */
function vaughany_pce_display_page(): void {
    $d        = VAUGHANY_PCE_DOMAIN;
    $settings = vaughany_pce_get_settings();

    // Use the same time throughout, so the whole report is consistent.
    $now     = time();
    $offset  = vaughany_pce_offset( $now );
    $periods = vaughany_pce_periods( $now, $offset, $settings['fy_start'] );

    echo '<h2>' . yourls_esc_html__( 'Popular Clicks Extended', $d ) . '</h2>' . PHP_EOL;
    echo '<p>' . yourls_esc_html( sprintf( yourls__( 'This report shows the most popular clicks for the selected time periods as of %s.', $d ), yourls_date_i18n( 'jS F Y, g:ia', $now + $offset ) ) ) . '</p>' . PHP_EOL;
    if ( $settings['exclude'] ) {
        echo '<p>' . yourls_esc_html( sprintf( yourls__( 'Leaving out short URLs matching: %s', $d ), implode( ', ', $settings['exclude'] ) ) ) . '</p>' . PHP_EOL;
    }

    $tabs = [
        'stat_tab_calendar' => yourls__( 'Calendar periods', $d ),
        'stat_tab_rolling'  => yourls__( 'Rolling periods', $d ),
        'stat_tab_sources'  => yourls__( 'Traffic sources', $d ),
        'stat_tab_recent'   => yourls__( 'Recent clicks', $d ),
        'stat_tab_settings' => yourls__( 'Settings', $d ),
    ];

    // Markup expected by YOURLS's js/infos.js.
    echo '<div id="tabs">' . PHP_EOL;
    echo '<div class="wrap_unfloat">' . PHP_EOL;
    echo '<ul id="headers" class="toggle_display stat_tab">' . PHP_EOL;
    foreach ( $tabs as $id => $label ) {
        echo '<li><a href="#' . $id . '"><h2>' . yourls_esc_html( $label ) . '</h2></a></li>' . PHP_EOL;
    }
    echo '</ul>' . PHP_EOL;
    echo '</div>' . PHP_EOL;

    foreach ( [ 'calendar' => 'stat_tab_calendar', 'rolling' => 'stat_tab_rolling' ] as $group => $tab ) {
        echo '<div id="' . $tab . '" class="tab">' . PHP_EOL;
        echo '<h2>' . yourls_esc_html( $tabs[ $tab ] ) . '</h2>' . PHP_EOL;
        foreach ( $periods as $period ) {
            if ( $period['group'] !== $group ) {
                continue;
            }
            echo '<h3>' . yourls_esc_html( sprintf( yourls__( 'Popular clicks for %s:', $d ), $period['desc'] ) ) . '</h3>' . PHP_EOL;
            echo vaughany_pce_render_table( vaughany_pce_get_popular( $period, $settings['rows'], $settings['exclude'] ) );
        }
        echo '</div>' . PHP_EOL;
    }

    echo '<div id="stat_tab_sources" class="tab">' . PHP_EOL;
    echo '<h2>' . yourls_esc_html( $tabs['stat_tab_sources'] ) . '</h2>' . PHP_EOL;
    echo vaughany_pce_render_sources( $periods, $settings );
    echo '</div>' . PHP_EOL;

    echo '<div id="stat_tab_recent" class="tab">' . PHP_EOL;
    echo '<h2>' . yourls_esc_html( $tabs['stat_tab_recent'] ) . '</h2>' . PHP_EOL;
    echo '<h3>' . yourls_esc_html__( 'Recently used short links', $d ) . '</h3>' . PHP_EOL;
    echo vaughany_pce_render_log( $settings['rows'], $settings['exclude'] );
    echo '</div>' . PHP_EOL;

    echo '<div id="stat_tab_settings" class="tab">' . PHP_EOL;
    echo '<h2>' . yourls_esc_html( $tabs['stat_tab_settings'] ) . '</h2>' . PHP_EOL;
    echo '<h3>' . yourls_esc_html__( 'Settings', $d ) . '</h3>' . PHP_EOL;
    echo vaughany_pce_render_settings( $settings );
    echo '</div>' . PHP_EOL;

    echo '</div>' . PHP_EOL;

    // Nice footer.
    echo '<hr>' . PHP_EOL;
    echo '<p>This plugin by <a href="https://github.com/vaughany/">Paul Vaughan</a>, version ' . yourls_esc_html( VAUGHANY_PCE_REL_VER ) . ' (' . yourls_esc_html( VAUGHANY_PCE_REL_DATE )
        . '), heavily inspired by <a href="https://github.com/miconda/yourls">Popular Clicks</a>, is <a href="' . yourls_esc_url( VAUGHANY_PCE_REPO )
        . '">available on GitHub</a> (<a href="' . yourls_esc_url( VAUGHANY_PCE_REPO . '/issues' ) . '">file a bug here</a>).</p>' . PHP_EOL;
}

/**
 * Add this plugin's actions to the YOURLS API (yourls-api.php).
 */
function vaughany_pce_api_actions( array $actions ): array {
    $actions['popular_clicks']    = 'vaughany_pce_api_popular_clicks';
    $actions['popular_referrers'] = 'vaughany_pce_api_popular_referrers';

    return $actions;
}

/**
 * Read the API request's period and limit, or return an error response.
 *
 * @return array Either ['settings', 'period_id', 'period', 'limit'], or ['error' => API response].
 */
function vaughany_pce_api_request(): array {
    $settings  = vaughany_pce_get_settings();
    $periods   = vaughany_pce_current_periods( $settings );
    $period_id = (string) ( $_REQUEST['period'] ?? VAUGHANY_PCE_DEFAULT_PERIOD );

    if ( !isset( $periods[ $period_id ] ) ) {
        $message = 'Unknown period. Use one of: ' . implode( ', ', array_keys( $periods ) );
        return [ 'error' => [ 'statusCode' => 400, 'errorCode' => '400', 'message' => $message, 'simple' => $message ] ];
    }

    $limit = filter_var( $_REQUEST['limit'] ?? $settings['rows'], FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1, 'max_range' => 100 ] ] );

    return [
        'settings'  => $settings,
        'period_id' => $period_id,
        'period'    => $periods[ $period_id ],
        'limit'     => $limit === false ? $settings['rows'] : $limit,
    ];
}

/**
 * The common part of an API response.
 */
function vaughany_pce_api_response( array $request ): array {
    return [
        'statusCode'  => 200,
        'message'     => 'success',
        'period'      => $request['period_id'],
        'description' => $request['period']['desc'],
        'from'        => (string) $request['period']['from'],
        'to'          => (string) $request['period']['to'],
    ];
}

/**
 * API action 'popular_clicks': the most clicked short URLs in a period.
 * Parameters: period (default last_24_hours), limit (1 to 100, default from the settings).
 */
function vaughany_pce_api_popular_clicks(): array {
    $request = vaughany_pce_api_request();
    if ( isset( $request['error'] ) ) {
        return $request['error'];
    }

    $response = vaughany_pce_api_response( $request );
    $links    = [];
    $simple   = [];
    foreach ( vaughany_pce_get_popular( $request['period'], $request['limit'], $request['settings']['exclude'] ) as $i => $result ) {
        $links[ 'link_' . ( $i + 1 ) ] = [
            'shorturl' => yourls_link( $result->keyword ),
            'keyword'  => $result->keyword,
            'url'      => $result->url,
            'title'    => (string) $result->title,
            'clicks'   => (int) $result->clicks,
        ];
        $simple[] = (int) $result->clicks . ' ' . yourls_link( $result->keyword );
    }
    $response['links']  = $links;
    $response['simple'] = implode( "\n", $simple );

    return $response;
}

/**
 * API action 'popular_referrers': the top traffic sources in a period, for all short URLs or one.
 * Parameters: period (default last_24_hours), limit (1 to 100, default from the settings), shorturl (optional).
 */
function vaughany_pce_api_popular_referrers(): array {
    $request = vaughany_pce_api_request();
    if ( isset( $request['error'] ) ) {
        return $request['error'];
    }

    $keyword = vaughany_pce_keyword_from_input( (string) ( $_REQUEST['shorturl'] ?? '' ) );
    if ( $keyword !== '' && !yourls_keyword_is_taken( $keyword ) ) {
        $message = 'Unknown short URL: ' . $keyword;
        return [ 'statusCode' => 404, 'errorCode' => '404', 'message' => $message, 'simple' => $message ];
    }

    $response             = vaughany_pce_api_response( $request );
    $response['shorturl'] = $keyword === '' ? '' : yourls_link( $keyword );
    $referrers            = [];
    $simple               = [];
    foreach ( vaughany_pce_get_referrers( $request['period'], $request['limit'], $request['settings']['exclude'], $keyword ) as $i => $result ) {
        $referrers[ 'referrer_' . ( $i + 1 ) ] = [
            'referrer' => (string) $result->referrer,
            'clicks'   => (int) $result->clicks,
        ];
        $simple[] = (int) $result->clicks . ' ' . $result->referrer;
    }
    $response['referrers'] = $referrers;
    $response['simple']    = implode( "\n", $simple );

    return $response;
}
