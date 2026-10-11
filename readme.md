# Popular Clicks Extended - a YOURLS plugin

Plugin for [YOURLS](https://yourls.org) 1.10.x (tested with 1.10.6). Requires PHP 8.1 or later, as YOURLS does.

* Plugin URI:       [github.com/vaughany/yourls-popular-clicks-extended](https://github.com/vaughany/yourls-popular-clicks-extended)
* Description:      A YOURLS plugin showing the most popular clicks for given time periods.
* Version:          0.4
* Release date:     2026-10-11
* Author:           Paul Vaughan
* Author URI:       [github.com/vaughany](https://github.com/vaughany/)


## Description

This plugin shows you which short links get clicked the most.  It can do 'last n minutes' type reports (e.g. last 5 minutes, last hour, last 24 hours etc) as well as 'blocks of time' reports (e.g. today, yesterday, this week, this month etc).  It is inspired by the [popular clicks](https://github.com/miconda/yourls/) plugin (and others) and improves on their work substantially.

The plugin's page has four tabs:

* **Calendar periods:** this hour and the previous hour, today and yesterday, this week and last week (starting Monday), this month and last month, this year and last year.
* **Rolling periods:** from the last 5 minutes up to the last 4 years, and all time. Note that 'the last 24 hours' is not the same as 'yesterday'.
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

If you know what you're doing you can add and amend this plugin, but some changes will be easier than others. If you make substantial improvements, [let me know](https://github.com/vaughany/yourls-popular-clicks-extended/issues).


## Bugs and Features

I'm always keen to add new features, improve performance and squash bugs, so if you do any of these things, let me know. [Fork the project](https://github.com/vaughany/yourls-popular-clicks-extended/), make your changes and send me a pull request.  If you find a bug but aren't a coder yourself, [open an issue](https://github.com/vaughany/yourls-popular-clicks-extended/issues) and give me as much detail as possible about the problem:

* What you did
* What you expected to happen
* What actually happened
* Steps to reproduce

## History

* 2026-10-11, v0.4:     Updated for YOURLS 1.10.x and PHP 8.1+, and finished the tabbed layout started in v0.3, using YOURLS's own tab styles. Fixed times now that YOURLS stores clicks in UTC, and calendar periods no longer miss the last second of each period. Added a settings tab (rows per report, and recent clicks on the main admin page, now off by default). Escaped all output and made strings translatable. Removed the debug and unused blacklist constants.
* 2018-05-10, v0.3:     Unfinished: moved to the newer YOURLS database API and started a tabbed layout. Showed recent clicks on the main admin page.
* 2015-12-30, v0.2:     Accounted for GMT offset and changed longurl to title on admin page for easier reference
* 2015-01-19, v0.1.1:   Minutely changed the wording to work out the current and previous weeks, fixing a minor bug.
* 2014-07-16, v0.1:     First version I'd consider fit for release.

## Finally...

I hope you find this plugin useful.
