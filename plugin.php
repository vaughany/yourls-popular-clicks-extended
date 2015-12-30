<?php

/*
Plugin Name:    Popular Clicks Extended
Plugin URI:     http://github.com/vaughany/yourls-popular-clicks-extended
Description:    A YOURLS plugin showing the most popular clicks for given time periods.
Version:        0.2
Release date:   2015-12-30
Author:         Paul Vaughan
Author URI:     http://github.com/vaughany/
*/

/**
 * TODO:
 *      Use global $now instead of time() so that the whole report is consistent.
 *      Use different pages for different types of reports.
 *      Report is in English: use the language functions to provide for potential translations.
 *      Config options to toggle the options: do you really need recent 5 mins or 2 years ago?
 *      Issue/idea on YOURLS repo: https://github.com/yourls/yourls/issues/1732
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
// Some version details, same as at the top of this file, for use in the page footer.
define ( "PCE_REL_VER",  '0.1' );
define ( "PCE_REL_DATE", '2014-07-16' );
// Repository URL.
define ( "PCE_REPO", 'https://github.com/vaughany/yourls-popular-clicks-extended' );
// Get the GMT offset if it is set
define( "PCE_OFFSET", defined( 'YOURLS_HOURS_OFFSET' ) ? YOURLS_HOURS_OFFSET * 60 * 60 : 0 );

yourls_add_action( 'plugins_loaded', 'vaughany_popularclicksextended_add_page' );

function vaughany_popularclicksextended_add_page() {
    yourls_register_plugin_page( 'popularclicksextended', 'Popular Clicks Extended', 'vaughany_popularclicksextended_display_page' );
}

function vaughany_popularclicksextended_display_page() {

    echo '<h2>Popular Clicks Extended</h2>' . "\n";
    echo '<p>This report shows the most popular clicks for the selected time periods as of ' . date( 'jS F Y, g:ia', time() ) . '.</p>' . "\n";
    echo '<p>Legend: <em>Position. Clicks' . PCE_SEP . 'Short URL' . PCE_SEP . 'Page</em></p>' . "\n";

    /**
     * vaughany_show_last_period(): queries the database for the number of clicks per link since n seconds ago,
     *     e.g. 'time() - 300' to 'time()'
     *     e.g. '2014-07-15 14:52:27' to '2014-07-15 14:57:27'
     *
     * $period:     integer:    The number of seconds to look back.
     * $rows:       integer:    The number of rows to pull from the database (maximum), defaults to 10.
     * $desc:       string:     Describes the time period for the report.
     */
    function vaughany_show_last_period( $period, $rows, $desc ) {

        global $ydb;

        // Check for an appropriate integer, set a default if not appropriate.
        if ( !is_int( $rows ) || $rows == 0 || $rows == null || $rows == '' ) {
            $rows = 10;
        }

        // Take the seconds off the current time, then change the timestamp into a date.
        $since = date( 'Y-m-d H:i:s', ( time() - $period + PCE_OFFSET ) );

        $sql = "SELECT a.shorturl AS shorturl, COUNT(*) AS clicks, b.url AS longurl, b.title as title
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
            $out = vaughany_render_results( $results );
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
     * vaughany_show_specific_period(): queries the database for the number of clicks per link per whole period,
     *     e.g. 'today'
     *     e.g. '2014-07-15 00:00:00' to '2014-07-15 23:59:59'
     *
     * $period: string:     Date partial for a single day, format depends on $type.
     * $type:   string:     One of: hour, day, week, month, year; so $period can be processed correctly.
     * $rows:   integer:    The number of rows to pull from the database (maximum), defaults to 10.
     * $desc:   string:     Describes the time period for the report.
     */
    function vaughany_show_specific_period( $period, $type, $rows, $desc ) {

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
            $to     = date( 'Y-m-d', strtotime( $period . ' + 6 days', time() + PCE_OFFSET ) ) . ' 23:59:59';
        } else if ( $type == 'month' ) {
            // Create the bounds for a single month.
            $from   = $period . '-01 00:00:00';
            $to     = date( 'Y-m-d', strtotime( $period . '-' . date( 't', strtotime( $from, time() + PCE_OFFSET ) ), time() + PCE_OFFSET ) ) . ' 23:59:59';
        } else if ( $type == 'year' ) {
            // Create the bounds for a single year.
            $from   = $period . '-01-01 00:00:00';
            $to     = $period . '-12-31 23:59:59';
        } else {
            // If no type is specified, defaults to literally everything (up to 32bit Unix signed integer limit).
            $from   = '1970-01-01 00:00:00';
            $to     = date( 'Y-m-d H:i:s', 2147483647 );
        }

        $sql = "SELECT a.shorturl AS shorturl, COUNT(*) AS clicks, b.url AS longurl, b.title as title
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
            $out = vaughany_render_results( $results );
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
    function vaughany_render_results( $results ) {
        $total = 0;
        $out = '<ol>';
        foreach ( $results as $result ) {
            $total += $result->clicks;
            $out .= '<li>';
            $out .= $result->clicks . PCE_SEP;
            $out .= '<a href="' . YOURLS_SITE . '/' . $result->shorturl . '+" target="blank">' . $result->shorturl . '</a>' . PCE_SEP;
            $out .= '<a href="' . $result->longurl . '" target="blank">' . $result->title . '</a>';
            $out .= '</li>';
        }
        $out .= "</ol>\n";
        $out .= '<p>' . $total . " total clicks this period.</p>\n";

        return $out;
    }

    /**
     * vaughany_show_log() shows the n most recent lines from the log table.
     *
     * $rows:   integer:    The number of rows to pull from the database (maximum), defaults to 10.
     */
    function vaughany_show_log( $rows ) {

        global $ydb;

        // Check for an appropriate integer, set a default if not appropriate.
        if ( !is_int($rows) || $rows == 0 || $rows == null || $rows == '' ) {
            $rows = 10;
        }

        $sql = "SELECT click_time, ip_address, country_code, referrer, a.shorturl AS shorturl, b.url AS longurl, b.title as title
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
                $out .= '<a href="' . $result->longurl . '" target="blank">' . $result->title . '</a>' . PCE_SEP;
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
    echo '<h2>Popular clicks for &quot;<em>period</em>&quot;</h2>' . "\n";

    /**
     * vaughany_show_specific_period() shows a specific hour, day, week, month or year. You can add more if you know what you're doing.
     * TODO: Can we clean this up a bit, maybe a little refactoring?
     */
    // Specific hours.
    vaughany_show_specific_period( date( 'Y-m-d H', time() + PCE_OFFSET ), 'hour', null, 'this hour (' . date( 'jS F Y, ga', time() + PCE_OFFSET ) . ' to ' . date( 'ga', strtotime( '+ 1 hour', time() + PCE_OFFSET ) ) . ') (so far)' );
    vaughany_show_specific_period( date( 'Y-m-d H', strtotime( '- 1 hour', time() + PCE_OFFSET ) ), 'hour', null, 'the previous hour (' . date( 'jS F Y, ga', strtotime( '- 1 hour', time() + PCE_OFFSET ) ) . ' to ' . date( 'ga', time() + PCE_OFFSET ) . ')' );
    // Specific days.
    vaughany_show_specific_period( date( 'Y-m-d', time() + PCE_OFFSET ), 'day', null, 'today (' . date( 'jS F Y', time() + PCE_OFFSET ) . ') (so far)' );
    vaughany_show_specific_period( date( 'Y-m-d', strtotime( '- 1 day', time() + PCE_OFFSET ) ), 'day', null, 'yesterday (' . date( 'jS F Y', strtotime( '- 1 day', time() + PCE_OFFSET ) ) . ')' );
    // Specific weeks:
    vaughany_show_specific_period( date( 'Y-m-d', strtotime( 'monday this week', time() + PCE_OFFSET ) ), 'week', null, 'this week (beginning ' . date( 'jS F Y', strtotime( 'monday this week', time() + PCE_OFFSET ) ) . ') (so far)' );
    vaughany_show_specific_period( date( 'Y-m-d', strtotime( 'monday this week - 7 days', time() + PCE_OFFSET ) ), 'week', null, 'last week  (beginning ' . date( 'jS F Y', strtotime( 'monday this week - 7 days', time() + PCE_OFFSET ) ) . ')' );
    // Specific months:
    vaughany_show_specific_period( date( 'Y-m', time() + PCE_OFFSET ), 'month', null, 'this month (' . date( 'F Y', time() + PCE_OFFSET ) . ') (so far)' );
    vaughany_show_specific_period( date( 'Y-m', strtotime( '- 1 month', time() + PCE_OFFSET ) ), 'month', null, 'last month (' . date( 'F Y', strtotime( '- 1 month', time() + PCE_OFFSET ) ) . ')' );
    // Specific years:
    vaughany_show_specific_period( date( 'Y', time() + PCE_OFFSET ), 'year', null, 'this year (' . date( 'Y', time() + PCE_OFFSET ) . ') (so far)');
    vaughany_show_specific_period( date( 'Y', strtotime( '- 1 year', time() + PCE_OFFSET ) ), 'year', null, 'last year (' . date('Y', strtotime( '- 1 year', time() + PCE_OFFSET ) ) . ')' );

    echo "<hr>\n";
    echo '<h2>Popular clicks for the last &quot;<em>period</em>&quot;</h2>' . "\n";

    /**
     * vaughany_show_last_period() shows all clicks from n seconds ago until now. Note that 24 hours here is not the same as 'yesterday', above.
     */
    vaughany_show_last_period( 60 * 5,                  null, '5 minutes');
    vaughany_show_last_period( 60 * 30,                 null, '30 minutes');
    vaughany_show_last_period( 60 * 60,                 null, 'hour');
    vaughany_show_last_period( 60 * 60 * 6,             null, '6 hours');
    vaughany_show_last_period( 60 * 60 * 12,            null, '12 hours');
    vaughany_show_last_period( 60 * 60 * 24,            null, '24 hours');
    vaughany_show_last_period( 60 * 60 * 24 * 2,        null, '2 days');
    vaughany_show_last_period( 60 * 60 * 24 * 7,        null, 'week');
    vaughany_show_last_period( 60 * 60 * 24 * 14,       null, '2 weeks');
    vaughany_show_last_period( 60 * 60 * 24 * 30,       null, 'month');
    vaughany_show_last_period( 60 * 60 * 24 * 60,       null, '2 months');
    vaughany_show_last_period( 60 * 60 * 24 * 90,       null, '3 months');
    vaughany_show_last_period( 60 * 60 * 24 * 180,      null, '6 months');
    vaughany_show_last_period( 60 * 60 * 24 * 365,      null, 'year');
    vaughany_show_last_period( 60 * 60 * 24 * 365 * 2,  null, '2 years');
    // ...and the catch-all:
    vaughany_show_last_period( time(),                  null, 'billion years');

    echo "<hr>\n";
    echo '<h2>Recently used short links</h2>' . "\n";

    vaughany_show_log( 10 );

    echo "<hr>\n";

    if ( PCE_DEBUG ) {
        echo '<p style="color: #f00;">';
        echo 'Last monday: ' . date( 'Y-m-d', strtotime( 'last monday', time() + PCE_OFFSET ) ) . "<br>\n";
        echo 'Monday before: ' . date( 'Y-m-d', strtotime( 'last monday - 7 days', time() + PCE_OFFSET ) ) . "<br>\n";
        echo 'Last month: ' . date( 'Y-m', strtotime( '- 1 month', time() + PCE_OFFSET ) ) . "<br>\n";
        echo '32-bit max Unix int: ' . date( 'Y-m-d H:i:s', 2147483647) . "\n";
        echo '</p>';
    }

    // Nice footer.
    echo '<p>This plugin by <a href="https://github.com/vaughany/">Paul Vaughan</a>, version ' . PCE_REL_VER . ' (' . PCE_REL_DATE .
        '), heavily inspired by <a href="https://github.com/miconda/yourls">Popular Clicks</a>, is <a href="' . PCE_REPO .
        '">available on GitHub</a> (<a href="' . PCE_REPO . '/issues">file a bug here</a>).</p>' . "\n";
}
