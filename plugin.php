<?php

/*
Plugin Name:    Popular Clicks Extended
Plugin URI:     http://github.com/vaughany/yourls-popular-clicks-extended
Description:    A YOURLS plugin showing the most popular clicks for given time periods.
Version:        0.1
Release date:   2014-07-16
Author:         Paul Vaughan
Author URI:     http://github.com/vaughany/
*/

/**
 * TODO:
 *      Use global $now instead of time() so that the whole report is consistent.
 *      Use different pages for different types of reports.
 *      Report is in English: use the language functions to provide for potential translations.
 *      Most recent n lines from the log.
 */

/**
 * https://github.com/YOURLS/YOURLS/wiki/Coding-Standards
 * https://github.com/YOURLS/YOURLS/wiki#for-developpers
 * https://github.com/YOURLS/YOURLS/wiki/Plugin-List#get-your-plugin-listed-here
*/

// No direct call.
if ( !defined ('YOURLS_ABSPATH') ) { die(); }

// Change to true to get extra debugging info on-screen. Must be true or false, cannot be undefined.
define ( "PCE_DEBUG", false );
// Define the separator between bits of information.
define ( "PCE_SEP", ' | ' );

yourls_add_action( 'plugins_loaded', 'popularclicksextended_add_page' );

function popularclicksextended_add_page() {
    yourls_register_plugin_page( 'popularclicksextended', 'Popular Clicks Extended', 'popularclicksextended_display_page' );
}

function popularclicksextended_display_page() {

    echo '<h2>Popular Clicks Extended</h2>'."\n";
    echo '<p>This report shows the most popular clicks for the selected time periods as of ' . date( 'jS F Y, g:ia', time() ) . '.</p>' . "\n";
    echo '<p>Legend: <em>Position. Clicks | Short URL | Long URL</em></p>'."\n";

    /**
     * show_last_period(): queries the database for the number of clicks per link since n seconds ago,
     *     e.g. 'time() - 300' to 'time()'
     *     e.g. '2014-07-15 14:52:27' to '2014-07-15 14:57:27'
     *
     * $period:     integer:    The number of seconds to look back.
     * $rows:       integer:    The number of rows to pull from the database (maximum), defaults to 10.
     * $desc:       string:     Describes the time period for the report.
     */
    function show_last_period( $period, $rows, $desc ) {

        global $ydb;

        // Check for an appropriate integer, set a default if not appropriate.
        if ( !is_int( $rows ) || $rows == 0 || $rows == null || $rows == '' ) {
            $rows = 10;
        }

        // Take the seconds off the current time, then change the timestamp into a date.
        $since = date( 'Y-m-d H:i:s', ( time() - $period ) );

        $sql = "SELECT a.shorturl AS shorturl, COUNT(*) AS clicks, b.url AS longurl 
            FROM " . YOURLS_DB_TABLE_LOG . " a, " . YOURLS_DB_TABLE_URL . " b 
            WHERE a.shorturl = b.keyword 
                AND click_time >= '" . $since . "'
            GROUP BY a.shorturl 
            ORDER BY COUNT(*) DESC, shorturl ASC
            LIMIT " . $rows . ";";

        if ( PCE_DEBUG ) {
            echo '<p style="color: #f00;">(' . $sql . ")</p>\n";
        }

        if ( $results = $ydb->get_results( $sql ) ) {
            $out = render_results( $results );
        } else {
            $out = '<p>No results for the chosen time period.</p>' . "\n";
        }

        echo '<h3>Popular clicks for the last ' . $desc . ":</h3>\n";
        if (PCE_DEBUG) {
            echo '<p style="color: #f00;">(Period from ' . $since . ' to now.)</p>' . "\n";
        }
        echo $out;

    }

    /**
     * show_specific_period(): queries the database for the number of clicks per link per whole period,
     *     e.g. 'today'
     *     e.g. '2014-07-15 00:00:00' to '2014-07-15 23:59:59'
     *
     * $period: string:     Date partial for a single day, format depends on $type.
     * $type:   string:     One of: hour, day, week, month, year; so $period can be processed correctly.
     * $rows:   integer:    The number of rows to pull from the database (maximum), defaults to 10.
     * $desc:   string:     Describes the time period for the report.
     */
    function show_specific_period( $period, $type, $rows, $desc ) {

        global $ydb;

        // Check for an appropriate integer, set a default if not appropriate.
        if ( !is_int($rows) || $rows == 0 || $rows == null || $rows == '' ) {
            $rows = 10;
        }

        // Test for each $type, create $from and $to date bounds accordingly.
        if ( $type == 'hour' ) {
            // Create the bounds for a single hour.
            $from   = $period . ':00:00';
            $to     = $period . ':59:59';
        } else if ( $type == 'day' ) {
            // Create the bounds for a single day.
            $from   = $period . ' 00:00:00';
            $to     = $period . ' 23:59:59';
        } else if ( $type == 'week' ) {
            // Create the bounds for a single week.
            $from   = $period . ' 00:00:00';
            $to     = date( 'Y-m-d', strtotime( $period . ' + 6 days' ) ) .' 23:59:59';
        } else if ( $type == 'month' ) {
            // Create the bounds for a single month.
            $from   = $period . '-01 00:00:00';
            $to     = date( 'Y-m-d', strtotime( $period . '-' . date( 't', strtotime( $from ) ) ) ) . ' 23:59:59';
        } else if ( $type == 'year' ) {
            // Create the bounds for a single year.
            $from   = $period . '-01-01 00:00:00';
            $to     = $period . '-12-31 23:59:59';
        } else {
            // If no type is specified, defaults to literally everything (up to 32bit Unix signed integer limit).
            $from   = '1970-01-01 00:00:00';
            $to     = date( 'Y-m-d H:i:s', 2147483647 );
        }

        $sql = "SELECT a.shorturl AS shorturl, COUNT(*) AS clicks, b.url AS longurl 
            FROM " . YOURLS_DB_TABLE_LOG . " a, " . YOURLS_DB_TABLE_URL . " b 
            WHERE a.shorturl = b.keyword 
                AND click_time >= '" . $from . "'
                AND click_time <= '" . $to . "'
            GROUP BY a.shorturl 
            ORDER BY COUNT(*) DESC, shorturl ASC
            LIMIT " . $rows . ";";

        if ( PCE_DEBUG ) {
            echo '<p style="color: #f00;">(' . $sql . ")</p>\n";
        }

        if ( $results = $ydb->get_results( $sql ) ) {
            $out = render_results( $results );
        } else {
            $out = '<p>No results for the chosen time period.</p>' . "\n";
        }

        echo '<h3>Popular clicks for ' . $desc . ":</h3>\n";
        if ( PCE_DEBUG ) {
            echo '<p style="color: #f00;">(Period from ' . $from . ' to ' . $to . ".)</p>\n";
        }
        echo $out;

    }

    /**
     * Often-used function to parse and format the results.
     */
    function render_results( $results ) {
        $out = '<ol>';
        foreach ( $results as $result ) {
            $out .= '<li>';
            $out .= $result->clicks . PCE_SEP;
            $out .= '<a href="' . YOURLS_SITE . '/' . $result->shorturl . '+" target="blank">' . $result->shorturl . '</a>' . PCE_SEP;
            $out .= '<a href="' . $result->longurl . '" target="blank">' . $result->longurl . '</a>';
            $out .= '</li>';
        }
        $out .= "</ol>\n";

        return $out;
    }

    /**
     * show_log() shows the n most recent lines from the log table.
     *
     * $rows:   integer:    The number of rows to pull from the database (maximum), defaults to 10.
     */
    function show_log( $rows ) {

        global $ydb;

        // Check for an appropriate integer, set a default if not appropriate.
        if ( !is_int($rows) || $rows == 0 || $rows == null || $rows == '' ) {
            $rows = 10;
        }

        $sql = "SELECT click_time, ip_address, country_code, referrer, a.shorturl AS shorturl, b.url AS longurl 
            FROM " . YOURLS_DB_TABLE_LOG . " a, " . YOURLS_DB_TABLE_URL . " b 
            WHERE a.shorturl = b.keyword 
            ORDER BY click_time DESC
            LIMIT " . $rows . ";";

        if ( PCE_DEBUG ) {
            echo '<p style="color: #f00;">(' . $sql . ")</p>\n";
        }

        if ( $results = $ydb->get_results( $sql ) ) {
            $out = '<ol>';
            foreach ( $results as $result ) {
                $out .= '<li>';
                $out .= $result->click_time . PCE_SEP;
                $out .= '<a href="' . YOURLS_SITE . '/' . $result->shorturl . '+" target="blank">' . $result->shorturl . '</a> / ';
                $out .= '<a href="' . $result->longurl . '" target="blank">' . $result->longurl . '</a>' . PCE_SEP;
                $out .= $result->ip_address . PCE_SEP;
                $out .= $result->country_code . PCE_SEP;
                $out .= $result->referrer;
                $out .= '</li>';
            }
            $out .= "</ol>\n";
        } else {
            $out = '<p>No logs to display.</p>' . "\n";
        }

        echo $out;

    }

    echo "<hr>\n";
    echo '<h2>Popular clicks for &quot;<em>period</em>&quot;</h2>'."\n";

    /**
     * show_specific_period() shows a specific hour, day, week, month or year. You can add more if you know what you're doing.
     * TODO: Can we clean this up a bit, maybe a little refactoring?
     */
    // Specific hours.
    show_specific_period( date( 'Y-m-d H', time() ), 'hour', null, 'this hour (' . date( 'jS F Y, ga', time() ) . ' to ' . date( 'ga', strtotime( '+ 1 hour' ) ) . ') (so far)' );
    show_specific_period( date( 'Y-m-d H', strtotime( '- 1 hour' ) ), 'hour', null, 'the previous hour (' . date( 'jS F Y, ga', strtotime( '- 1 hour' ) ) . ' to ' . date( 'ga', time() ) . ')' );
    // Specific days.
    show_specific_period( date( 'Y-m-d', time() ), 'day', null, 'today (' . date( 'jS F Y', time() ) . ') (so far)' );
    show_specific_period( date( 'Y-m-d', strtotime( '- 1 day' ) ), 'day', null, 'yesterday (' . date( 'jS F Y', strtotime( '- 1 day' ) ) . ')' );
    // Specific weeks:
    show_specific_period( date( 'Y-m-d', strtotime( 'last monday' ) ), 'week', null, 'this week (beginning ' . date( 'jS F Y', strtotime( 'last monday' ) ) . ') (so far)' );
    show_specific_period( date( 'Y-m-d', strtotime( 'last monday - 7 days' ) ), 'week', null, 'last week  (beginning ' . date( 'jS F Y', strtotime( 'last monday - 7 days' ) ) . ')' );
    // Specific months:
    show_specific_period( date( 'Y-m', time() ), 'month', null, 'this month (' . date( 'F Y', time() ) . ') (so far)' );
    show_specific_period( date( 'Y-m', strtotime( '- 1 month' ) ), 'month', null, 'last month (' . date( 'F Y', strtotime( '- 1 month' ) ) . ')' );
    // Specific years:
    //show_specific_period( date( 'Y', time() ), 'year', null, 'this year (' . date( 'Y', time() ) . ') (so far)');
    //show_specific_period( date( 'Y', strtotime( '- 1 year' ) ), 'year', null, 'last year (' . date('Y', strtotime( '- 1 year' ) ) . ')' );

    echo "<hr>\n";
    echo '<h2>Popular clicks for the last &quot;<em>period</em>&quot;</h2>'."\n";

    /**
     * show_last_period() shows all clicks from n seconds ago until now. Note that 24 hours here is not the same as 'yesterday', above.
     */
    show_last_period( 60 * 5,                   null, '5 minutes');
    show_last_period( 60 * 30,                  null, '30 minutes');
    show_last_period( 60 * 60,                  null, 'hour');
    show_last_period( 60 * 60 * 6,              null, '6 hours');
    show_last_period( 60 * 60 * 12,             null, '12 hours');
    show_last_period( 60 * 60 * 24,             null, '24 hours');
    show_last_period( 60 * 60 * 24 * 2,         null, '2 days');
    show_last_period( 60 * 60 * 24 * 7,         null, 'week');
    show_last_period( 60 * 60 * 24 * 14,        null, '2 weeks');
    show_last_period( 60 * 60 * 24 * 30,        null, 'month');
    show_last_period( 60 * 60 * 24 * 60,        null, '2 months');
    show_last_period( 60 * 60 * 24 * 90,        null, '3 months');
    show_last_period( 60 * 60 * 24 * 180,       null, '6 months');
    //show_last_period( 60 * 60 * 24 * 365,       null, 'year');
    //show_last_period( 60 * 60 * 24 * 365 * 2,   null, '2 years');
    // ...and the catch-all:
    //show_last_period( time(),                   null, 'billion years');

    echo "<hr>\n";
    echo '<h2>Recently used short links</h2>'."\n";

    show_log( 10 );

    echo "<hr>\n";

    if ( PCE_DEBUG ) {
        echo '<p style="color: #f00;">';
        echo 'Last monday: ' . date( 'Y-m-d', strtotime( 'last monday' ) ) . "<br>\n";
        echo 'Monday before: ' . date( 'Y-m-d', strtotime( 'last monday - 7 days' ) ) . "<br>\n";
        echo 'Last month: ' . date( 'Y-m', strtotime( '- 1 month' ) ) . "<br>\n";
        echo '32-bit max Unix int: ' . date( 'Y-m-d H:i:s', 2147483647) . "\n";
        echo '</p>';
    }

    // Nice footer.
    echo '<p>This plugin by <a href="https://github.com/vaughany/">Paul Vaughan</a>, heavily inspired by <a href="https://github.com/miconda/yourls">Popular Clicks</a>, is <a href="#">available (to fork and improve) on GitHub</a>.</p>';
}
