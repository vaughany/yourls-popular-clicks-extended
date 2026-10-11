# Popular Clicks Extended - a YOURLS plugin

Plugin for [YOURLS](https://yourls.org) 1.10.x (tested with 1.10.6). Requires PHP 8.1 or later, as YOURLS does.

* Plugin URI:       [github.com/vaughany/yourls-popular-clicks-extended](https://github.com/vaughany/yourls-popular-clicks-extended)
* Description:      A YOURLS plugin showing the most popular clicks for given time periods.
* Version:          0.5
* Release date:     2026-10-11
* Author:           Paul Vaughan
* Author URI:       [github.com/vaughany](https://github.com/vaughany/)


## Description

This plugin shows you which short links get clicked the most.  It can do 'last n minutes' type reports (e.g. last 5 minutes, last hour, last 24 hours etc) as well as 'blocks of time' reports (e.g. today, yesterday, this week, this month etc).  It is inspired by the [popular clicks](https://github.com/miconda/yourls/) plugin (and others) and improves on their work substantially.

The plugin's page has five tabs:

* **Calendar periods:** this hour and the previous hour, today and yesterday, this week and last week (starting Monday), this month and last month, this year and last year, and (if you set it up) this financial year and last financial year.
* **Rolling periods:** from the last 5 minutes up to the last 4 years, and all time. Note that 'the last 24 hours' is not the same as 'yesterday'.
* **Traffic sources:** the top referrers for any of the periods above, for all short URLs or just one.
* **Recent clicks:** the most recent clicks, with the time, short URL, page, visitor IP address, country and referrer.
* **Settings:** see below.

Times are shown in your local time, using `YOURLS_HOURS_OFFSET` in `user/config.php` or the official [Time Zones plugin](https://github.com/YOURLS/timezones) if you use it. YOURLS stores click times in UTC, and 'today', 'this week' etc. follow your local time.


## Installation

1. In `/user/plugins`, create a new folder named `popular-clicks-extended`.
2. Drop these files in that directory.
3. Go to the Manage Plugins page (e.g. `https://sho.rt/admin/plugins.php`) and activate the plugin.
4. Under the Manage Plugins link should be a new link called `Popular Clicks Extended`.
5. Have fun!


## License

Uses YOURLS' license, aka *"Do whatever the hell you want with it"*.  I borrowed code from others which had no licence so I can't claim this whole plugin as my own work, but the lion's share of it is.


## Configuration

On the plugin's Settings tab you can choose:

* **Rows per report:** how many links each report shows, from 1 to 100 (default 10).
* **Show recent clicks on the main admin page:** adds the recent clicks table below the links table on the main admin page. This is off by default, as it shows visitor IP addresses.
* **Short URLs to leave out:** one pattern per line; matching short URLs are left out of every report, the traffic sources and the API. `*` matches any characters, so `z123*` leaves out every short URL starting with `z123`; a pattern without `*` must match the whole short URL.
* **Financial year starts in:** pick a month to add 'this financial year' and 'last financial year' reports, e.g. July for a year running from July to June. Off by default.


## API

The plugin adds two actions to the [YOURLS API](https://yourls.org/docs/guide/advanced/api) (`yourls-api.php`), so you can use the reports elsewhere. As with the rest of the API, you need to authenticate (e.g. with a signature token) unless your YOURLS install is public, and `format` can be `json`, `jsonp`, `xml` or `simple`.

* `action=popular_clicks`: the most clicked short URLs in a period.
* `action=popular_referrers`: the top traffic sources in a period. Add `shorturl=` (a keyword or full short URL) for a single short URL.

Both take `period` (default `last_24_hours`) and `limit` (1 to 100, default from the settings). Periods are `this_hour`, `previous_hour`, `today`, `yesterday`, `this_week`, `previous_week`, `this_month`, `previous_month`, `this_year`, `previous_year`, `this_financial_year` and `previous_financial_year` (if set up), and `last_5_minutes`, `last_30_minutes`, `last_hour`, `last_6_hours`, `last_12_hours`, `last_24_hours`, `last_2_days`, `last_7_days`, `last_14_days`, `last_30_days`, `last_60_days`, `last_90_days`, `last_180_days`, `last_365_days`, `last_2_years`, `last_3_years`, `last_4_years` and `all_time`. For example:

    https://sho.rt/yourls-api.php?signature=1234567890&action=popular_clicks&period=yesterday&limit=5&format=json

If you know what you're doing you can add and amend this plugin, but some changes will be easier than others. If you make substantial improvements, [let me know](https://github.com/vaughany/yourls-popular-clicks-extended/issues).


## Bugs and Features

I'm always keen to add new features, improve performance and squash bugs, so if you do any of these things, let me know. [Fork the project](https://github.com/vaughany/yourls-popular-clicks-extended/), make your changes and send me a pull request.  If you find a bug but aren't a coder yourself, [open an issue](https://github.com/vaughany/yourls-popular-clicks-extended/issues) and give me as much detail as possible about the problem:

* What you did
* What you expected to happen
* What actually happened
* Steps to reproduce

## History

* 2026-10-11, v0.5:     Added a traffic sources tab: the top referrers for any period, for all short URLs or one ([#6](https://github.com/vaughany/yourls-popular-clicks-extended/issues/6), [#8](https://github.com/vaughany/yourls-popular-clicks-extended/issues/8)). Leave out short URLs matching patterns such as `z123*` ([#9](https://github.com/vaughany/yourls-popular-clicks-extended/issues/9)). Optional financial year reports ([#1](https://github.com/vaughany/yourls-popular-clicks-extended/issues/1)). `popular_clicks` and `popular_referrers` YOURLS API actions, for JSON or XML output ([#2](https://github.com/vaughany/yourls-popular-clicks-extended/issues/2)).
* 2026-10-11, v0.4:     Updated for YOURLS 1.10.x and PHP 8.1+, and finished the tabbed layout started in v0.3, using YOURLS's own tab styles. Fixed times now that YOURLS stores clicks in UTC, and calendar periods no longer miss the last second of each period. Added a settings tab (rows per report, and recent clicks on the main admin page, now off by default). Escaped all output and made strings translatable. Removed the debug and unused blacklist constants.
* 2018-05-10, v0.3:     Unfinished: moved to the newer YOURLS database API and started a tabbed layout. Showed recent clicks on the main admin page.
* 2015-12-30, v0.2:     Accounted for GMT offset and changed longurl to title on admin page for easier reference
* 2015-01-19, v0.1.1:   Minutely changed the wording to work out the current and previous weeks, fixing a minor bug.
* 2014-07-16, v0.1:     First version I'd consider fit for release.

## Finally...

I hope you find this plugin useful.
