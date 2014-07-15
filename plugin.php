<?php

/*
Plugin Name:    Best Links
Plugin URI:     http://github.com/vaughany/yourls-best-links
Description:    A report showing the most popular links for given time periods.
Version:        1.0
Author:         Paul Vaughan
Author URI:     http://github.com/vaughany/
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

/*
https://github.com/YOURLS/YOURLS/wiki/Coding-Standards
https://github.com/YOURLS/YOURLS/wiki#for-developpers
https://github.com/YOURLS/YOURLS/wiki/Plugin-List#get-your-plugin-listed-here
*/

yourls_add_action( 'plugins_loaded', 'bestlinks_add_page' );

function popularclicks_add_page() {
    yourls_register_plugin_page( 'best_links', 'Best Links', 'bestlinks_do_page' );
}

function popularclicks_do_page() {

    $nonce = yourls_create_nonce('popular_clickks');
    echo '<h2>Best Links</h2>'."\n";
    echo '<p>This report shows the best links for the selected time periods.</p>'."\n";
    echo '<p>Legend: <em>Clicks | Short URL | Long URL</em></p>'."\n";

    /**
     * show_best():     queries the database for the number of clicks per link per time period.
     * $days:           integer:    The number of days to look back.
     * $rows:           integer:    The number of rows to pull from the database (maximum).
     * $desc:           string:     Describes the time period.
     */
    function show_best($days, $rows, $desc) {

        // Database object.
        global $ydb;

        // TODO: Prefer fine-grained settings here, such as 'last hour', 'yesterday' and such.
        // http://pastebin.com/raw.php?i=Ts7sTZVm
        $results = $ydb->get_results("
            SELECT a.shorturl AS shorturl, COUNT(*) AS clicks, b.url AS longurl 
            FROM ".YOURLS_DB_TABLE_LOG." a, ".YOURLS_DB_TABLE_URL." b 
            WHERE a.shorturl = b.keyword 
                AND DATE_SUB(NOW(), INTERVAL ".$days." DAY) < a.click_time 
            GROUP BY a.shorturl 
            ORDER BY COUNT(*) DESC, shorturl ASC
            LIMIT ".$rows.";");
    
        if ($results) {
            $out = '<ol>';
            foreach ( $results as $result ) {
                $out .= '<li>'.$result->clicks.' | ';
                $out .= '<a href="'.YOURLS_SITE.'/'.$result->shorturl.'+" target="blank">'.$result->shorturl.'</a> | ';
                $out .= '<a href="'.$result->longurl.'" target="blank">'.$result->longurl.'</li>';
            }
            $out .= "</ol>\n";
        } else {
            $out = '<p>No results to show for the chosen time period.</p>'."\n";
        }
        echo '<h3>Best links for the last '.$desc.":</h3>\n";
        echo $out;
    }

    // Run the above function with a specific number of days, number of results, and description.
    show_best(1, 5, '24 hours');
    show_best(2, 10, '48 hours');
    show_best(7, 15, 'week');
    show_best(2, 15, 'two weeks');
    show_best(30, 15, 'month');
    show_best(60, 15, 'two months');
    show_best(90, 15, 'three months');
    show_best(180, 15, 'six months');
    show_best(365, 30, 'year');
    //show_best(730, 30, 'two years');
    //show_best(1095, 30, 'three years');
    //show_best(9999999, 60, 'all time');
}
