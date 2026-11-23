# XAseco plugins for PHP 8

Third-party plugins for [xaseco-php8](https://github.com/esfomeado/xaseco-php8)
(XAseco 1.16 on PHP 8), ported to PHP 8 and fixed.

| Plugin | What it does |
|---|---|
| `plugin.records_eyepiece.php` | Record, ranking and info widgets and windows |
| `plugin.mania_karma.php` | Track karma voting with the global Mania-Karma database |
| `plugin.personal_best_medals.php` | Shows each player's medal for their personal best |
| `plugin.nickname_sync.php` | Updates stored nicknames from Dedimania records |
| `plugin.fufi.menu.php` (+ `fufi/`) | In-game menu for commands |
| `plugin.bestcps.php` | Best time at each checkpoint |
| `plugin.pay2play.php` | Players pay coppers to replay or skip a track |
| `plugin.freezone.php` (+ `freezone/`) | FreeZone server support |
| `plugin.discord.php` | Discord invite widget |

## Installation

Copy `plugins/` into the XAseco directory, add the plugins to `plugins.xml` and
put their configuration files (`records_eyepiece.xml`, `mania_karma.xml`, ...)
next to `config.xml`.
