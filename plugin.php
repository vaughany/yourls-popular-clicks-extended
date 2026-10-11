<?php

/*
Plugin Name:    Popular Clicks Extended
Plugin URI:     https://github.com/vaughany/yourls-popular-clicks-extended
Description:    A YOURLS plugin showing the most popular clicks for given time periods.
Version:        0.4
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
const VAUGHANY_PCE_REL_VER  = '0.4';
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
];

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
    ];
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

    $rows = filter_var( $_POST['rows'] ?? '', FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1, 'max_range' => 100 ] ] );
    if ( $rows === false ) {
        yourls_add_notice( yourls__( 'Rows per report must be a whole number from 1 to 100.', VAUGHANY_PCE_DOMAIN ), 'error' );
        return;
    }

    yourls_update_option( VAUGHANY_PCE_OPTION, [
        'rows'          => $rows,
        'show_on_admin' => !empty( $_POST['show_on_admin'] ),
    ] );

    yourls_add_notice( yourls__( 'Settings saved.', VAUGHANY_PCE_DOMAIN ), 'success' );
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
 * Whole calendar periods (this hour, yesterday, last month etc.) in local time.
 *
 * @return array List of ['desc' => string, 'from' => DateTimeImmutable, 'to' => DateTimeImmutable], in local time; 'to' is exclusive.
 */
function vaughany_pce_calendar_periods( int $now, int $offset ): array {
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

    return [
        [ 'from' => $hour,       'to' => $hour->modify( '+1 hour' ),  'desc' => sprintf( yourls__( 'this hour (%1$s to %2$s) (so far)', $d ), $date( 'jS F Y, ga', $hour ), $date( 'ga', $hour->modify( '+1 hour' ) ) ) ],
        [ 'from' => $prev_hour,  'to' => $hour,                       'desc' => sprintf( yourls__( 'the previous hour (%1$s to %2$s)', $d ), $date( 'jS F Y, ga', $prev_hour ), $date( 'ga', $hour ) ) ],
        [ 'from' => $day,        'to' => $day->modify( '+1 day' ),    'desc' => sprintf( yourls__( 'today (%s) (so far)', $d ), $date( 'jS F Y', $day ) ) ],
        [ 'from' => $prev_day,   'to' => $day,                        'desc' => sprintf( yourls__( 'yesterday (%s)', $d ), $date( 'jS F Y', $prev_day ) ) ],
        [ 'from' => $week,       'to' => $week->modify( '+7 days' ),  'desc' => sprintf( yourls__( 'this week (beginning %s) (so far)', $d ), $date( 'jS F Y', $week ) ) ],
        [ 'from' => $prev_week,  'to' => $week,                       'desc' => sprintf( yourls__( 'last week (beginning %s)', $d ), $date( 'jS F Y', $prev_week ) ) ],
        [ 'from' => $month,      'to' => $month->modify( '+1 month' ), 'desc' => sprintf( yourls__( 'this month (%s) (so far)', $d ), $date( 'F Y', $month ) ) ],
        [ 'from' => $prev_month, 'to' => $month,                      'desc' => sprintf( yourls__( 'last month (%s)', $d ), $date( 'F Y', $prev_month ) ) ],
        [ 'from' => $year,       'to' => $year->modify( '+1 year' ),  'desc' => sprintf( yourls__( 'this year (%s) (so far)', $d ), $date( 'Y', $year ) ) ],
        [ 'from' => $prev_year,  'to' => $year,                       'desc' => sprintf( yourls__( 'last year (%s)', $d ), $date( 'Y', $prev_year ) ) ],
    ];
}

/**
 * Rolling periods: from n seconds ago until now. Note that 24 hours here is not the same as 'yesterday', above.
 *
 * @return array List of ['desc' => string, 'seconds' => int|null], where null means all time.
 */
function vaughany_pce_rolling_periods(): array {
    $d      = VAUGHANY_PCE_DOMAIN;
    $minute = 60;
    $hour   = 60 * $minute;
    $day    = 24 * $hour;

    return [
        [ 'seconds' => 5 * $minute,   'desc' => yourls__( 'the last 5 minutes', $d ) ],
        [ 'seconds' => 30 * $minute,  'desc' => yourls__( 'the last 30 minutes', $d ) ],
        [ 'seconds' => $hour,         'desc' => yourls__( 'the last hour', $d ) ],
        [ 'seconds' => 6 * $hour,     'desc' => yourls__( 'the last 6 hours', $d ) ],
        [ 'seconds' => 12 * $hour,    'desc' => yourls__( 'the last 12 hours', $d ) ],
        [ 'seconds' => 24 * $hour,    'desc' => yourls__( 'the last 24 hours', $d ) ],
        [ 'seconds' => 2 * $day,      'desc' => yourls__( 'the last 2 days', $d ) ],
        [ 'seconds' => 7 * $day,      'desc' => yourls__( 'the last week', $d ) ],
        [ 'seconds' => 14 * $day,     'desc' => yourls__( 'the last 2 weeks', $d ) ],
        [ 'seconds' => 30 * $day,     'desc' => yourls__( 'the last month', $d ) ],
        [ 'seconds' => 60 * $day,     'desc' => yourls__( 'the last 2 months', $d ) ],
        [ 'seconds' => 90 * $day,     'desc' => yourls__( 'the last 3 months', $d ) ],
        [ 'seconds' => 180 * $day,    'desc' => yourls__( 'the last 6 months', $d ) ],
        [ 'seconds' => 365 * $day,    'desc' => yourls__( 'the last year', $d ) ],
        [ 'seconds' => 2 * 365 * $day, 'desc' => yourls__( 'the last 2 years', $d ) ],
        [ 'seconds' => 3 * 365 * $day, 'desc' => yourls__( 'the last 3 years', $d ) ],
        [ 'seconds' => 4 * 365 * $day, 'desc' => yourls__( 'the last 4 years', $d ) ],
        // ...and the catch-all:
        [ 'seconds' => null,          'desc' => yourls__( 'all time', $d ) ],
    ];
}

/**
 * Query the database for the number of clicks per link between two times.
 *
 * @param string|null $from Earliest click time (inclusive), as a UTC 'Y-m-d H:i:s' string, or null for no limit.
 * @param string|null $to   Latest click time (exclusive), as a UTC 'Y-m-d H:i:s' string, or null for no limit.
 * @param int         $rows The maximum number of rows to return.
 * @return array List of objects with keyword, url, title and clicks properties.
 */
function vaughany_pce_get_popular( ?string $from, ?string $to, int $rows ): array {
    $where = [];
    $binds = [ 'rows' => $rows ];

    if ( $from !== null ) {
        $where[]       = 'l.click_time >= :from';
        $binds['from'] = $from;
    }
    if ( $to !== null ) {
        $where[]     = 'l.click_time < :to';
        $binds['to'] = $to;
    }

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
 * Format a Unix timestamp (local time) as a UTC string for querying the database.
 */
function vaughany_pce_to_db_time( int $local_timestamp, int $offset ): string {
    return gmdate( 'Y-m-d H:i:s', $local_timestamp - $offset );
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
 * @param int $rows The maximum number of rows to show.
 */
function vaughany_pce_render_log( int $rows ): string {
    $d = VAUGHANY_PCE_DOMAIN;

    $sql = 'SELECT l.click_time AS click_time, l.ip_address AS ip_address, l.country_code AS country_code, l.referrer AS referrer,
            u.keyword AS keyword, u.url AS url, u.title AS title
        FROM `' . YOURLS_DB_TABLE_LOG . '` l
        INNER JOIN `' . YOURLS_DB_TABLE_URL . '` u ON l.shorturl = u.keyword
        ORDER BY l.click_time DESC, l.click_id DESC
        LIMIT :rows';

    $results = yourls_get_db( 'read-vaughany_pce_log' )->fetchObjects( $sql, [ 'rows' => $rows ] ) ?: [];

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
 * Show recent clicks below the links table on the main admin page, if enabled in the settings.
 */
function vaughany_pce_admin_recent_clicks(): void {
    $settings = vaughany_pce_get_settings();
    if ( !$settings['show_on_admin'] ) {
        return;
    }

    echo '<h3>' . yourls_esc_html__( 'Recent clicks', VAUGHANY_PCE_DOMAIN ) . '</h3>' . PHP_EOL;
    echo vaughany_pce_render_log( $settings['rows'] );
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
    $now    = time();
    $offset = vaughany_pce_offset( $now );

    echo '<h2>' . yourls_esc_html__( 'Popular Clicks Extended', $d ) . '</h2>' . PHP_EOL;
    echo '<p>' . yourls_esc_html( sprintf( yourls__( 'This report shows the most popular clicks for the selected time periods as of %s.', $d ), yourls_date_i18n( 'jS F Y, g:ia', $now + $offset ) ) ) . '</p>' . PHP_EOL;

    $tabs = [
        'stat_tab_calendar' => yourls__( 'Calendar periods', $d ),
        'stat_tab_rolling'  => yourls__( 'Rolling periods', $d ),
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

    // Whole hours, days, weeks, months and years.
    echo '<div id="stat_tab_calendar" class="tab">' . PHP_EOL;
    echo '<h2>' . yourls_esc_html( $tabs['stat_tab_calendar'] ) . '</h2>' . PHP_EOL;
    foreach ( vaughany_pce_calendar_periods( $now, $offset ) as $period ) {
        $results = vaughany_pce_get_popular(
            vaughany_pce_to_db_time( $period['from']->getTimestamp(), $offset ),
            vaughany_pce_to_db_time( $period['to']->getTimestamp(), $offset ),
            $settings['rows']
        );
        echo '<h3>' . yourls_esc_html( sprintf( yourls__( 'Popular clicks for %s:', $d ), $period['desc'] ) ) . '</h3>' . PHP_EOL;
        echo vaughany_pce_render_table( $results );
    }
    echo '</div>' . PHP_EOL;

    // From n seconds ago until now.
    echo '<div id="stat_tab_rolling" class="tab">' . PHP_EOL;
    echo '<h2>' . yourls_esc_html( $tabs['stat_tab_rolling'] ) . '</h2>' . PHP_EOL;
    foreach ( vaughany_pce_rolling_periods() as $period ) {
        $from    = $period['seconds'] === null ? null : gmdate( 'Y-m-d H:i:s', $now - $period['seconds'] );
        $results = vaughany_pce_get_popular( $from, null, $settings['rows'] );
        echo '<h3>' . yourls_esc_html( sprintf( yourls__( 'Popular clicks for %s:', $d ), $period['desc'] ) ) . '</h3>' . PHP_EOL;
        echo vaughany_pce_render_table( $results );
    }
    echo '</div>' . PHP_EOL;

    echo '<div id="stat_tab_recent" class="tab">' . PHP_EOL;
    echo '<h2>' . yourls_esc_html( $tabs['stat_tab_recent'] ) . '</h2>' . PHP_EOL;
    echo '<h3>' . yourls_esc_html__( 'Recently used short links', $d ) . '</h3>' . PHP_EOL;
    echo vaughany_pce_render_log( $settings['rows'] );
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
