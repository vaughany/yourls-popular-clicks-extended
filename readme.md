# Popular Clicks Extended - a YOURLS plugin

Plugin for [YOURLS](http://yourls.org) 1.7.x.

* Plugin URI:       [github.com/vaughany/yourls-popular-links-extended](http://github.com/vaughany/yourls-popular-links-extended)
* Description:      A YOURLS plugin showing the most popular clicks for given time periods.
* Version:          0.2
* Release date:     2015-12-30
* Author:           Paul Vaughan
* Author URI:       [github.com/vaughany](http://github.com/vaughany/)


## Description

This plugin shows you which short links get clicked the most.  It can do 'last n minutes' type reports (e.g. last 5 minutes, last hour, last 24 hours etc) as well as 'blocks of time' reports (e.g. today, yesterday, this week, this month etc).  It is inspired by the [popular clicks](https://github.com/miconda/yourls/) plugin (and others) and improves on their work substantially.


## Installation

1. In `/user/plugins`, create a new folder named `popular-links-extended`.
2. Drop these files in that directory.
3. Go to the Manage Plugins page (e.g. `http://sho.rt/admin/plugins.php`) and activate the plugin.
4. Under the Manage Plugins link should be a new link called `Popular Clicks Extended`.
5. Have fun!


## License

Uses YOURLS' license, aka *"Do whatever the hell you want with it"*.  I borrowed code from others which had no licence so I can't claim this whole plugin as my own work, but the lion's share of it is.


## Configuration

If you know what you're doing you can add and amend this plugin, but some changes will be easier than others. If you make substantial improvements, [let me know](https://github.com/vaughany/yourls-popular-clicks-extended/issues).


## Bugs and Features

I'm always keen to add new features, improve performance and squash bugs, so if you do any of these things, let me know. [Fork the project](https://github.com/vaughany/yourls-popular-clicks-extended/), make your changes and send me a pull request.  If you find a bug but aren't a coder yourself, [open an issue](https://github.com/vaughany/yourls-popular-clicks-extended/issues) and give me as much detail as possible about the problem:

* What you did
* What you expected to happen
* What actually happened
* Steps to reproduce

## History

* 2015-12-30, v0.2:     Accounted for GMT offset and changed longurl to title on admin page for easier reference
* 2015-01-19, v0.1.1:   Minutely changed the wording to work out the current and previous weeks, fixing a minor bug.
* 2014-07-16, v0.1:     First version I'd consider fit for release.

## Finally...

I hope you find this plugin useful.
