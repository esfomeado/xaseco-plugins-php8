# XAseco plugins for PHP 8

The third-party XAseco plugins used on the TMNF server, ported to PHP 8 for
[xaseco-php8](https://github.com/esfomeado/xaseco-php8) (XAseco 1.16 on PHP 8).

The first commit is the plugins as they ran on the old PHP 5.4 server; every
later commit is part of the port or a fix, so `git diff` against it shows what
changed.

| Plugin | Version | Author | License |
|---|---|---|---|
| `plugin.records_eyepiece.php` | 1.1.1 | undef.de | GPL v2+ |
| `plugin.mania_karma.php` | 2.0.1 | undef.de | GPL v2+ |
| `plugin.nickname_sync.php` | 1.2.1 | undef.de, .anDy | GPL v2+ |
| `plugin.personal_best_medals.php` | 1.0.3 | undef.de, Olymptor | GPL v2+ |
| `plugin.fufi.menu.php` (+ `fufi/`) | 0.36 | oorf-fuckfish | not stated |
| `plugin.bestcps.php` | 1.1 | galaad | not stated |
| `plugin.pay2play.php` | 1.01 | Leigham | not stated |
| `plugin.freezone.php` (+ `freezone/`) | | ManiaLive / NADEO | REST client LGPL v3, plugin not stated |
| `plugin.discord.php` | 1.0 | | not stated |

This repository is private: several plugins state no license.

## Requirements

- xaseco-php8; the plugins use its `$aseco->db` (mysqli) and `triggerFatalError()`.
- Configuration files (`records_eyepiece.xml`, `mania_karma.xml`, ...) belong to
  the server installation and are not part of this repository.

## Installation

Copy `plugins/` into the XAseco directory and list the plugins in `plugins.xml`.
