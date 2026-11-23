<?php

/*
 * Plugin: Personal Best Medals
 * ~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 * For a detailed description and documentation, please refer to:
 * http://www.undef.name/XAseco1/Personal-Best-Medals.php
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de, Olymptor
 * Version:		1.0.3
 * Date:		2016-07-20
 * Copyright:		2012 - 2016 by undef.de, Olymptor
 * System:		XAseco/1.16+
 * Game:		Trackmania Forever (TMF)
 * ----------------------------------------------------------------------------------
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * ----------------------------------------------------------------------------------
 *
 * Dependencies:
 *  - plugins/plugin.localdatabase.php		Required, for find the LocalRecord
 *  - plugins/plugin.dedimania.php		Optional, for find the DedimaniaRecord and if enabled
 *  - plugins/plugin.ultimania.php		Optional, for find the UltimaniaRecord and if enabled (only for Gamemode 'Stunts'. Get it here: http://www.tm-forum.com/viewtopic.php?f=127&t=30924)
 */

/* The following manialink id's are used in this plugin (the 923 part of id can be changed on trouble):
 *
 * ManialinkID's
 * ~~~~~~~~~~~~~
 * 92300			id for manialink Widget
 * 92301			id for manialink PlayerMedalWindow
 * 92302			id for manialink NewMedalWidget
 *
 * ActionID's
 * ~~~~~~~~~~
 * 92300			id for action show PlayerMedalWindow
 * 92301			id for action close PlayerMedalWindow
 */

Aseco::registerEvent('onSync',				'pbme_onSync');
Aseco::registerEvent('onNewChallenge2',			'pbme_onNewChallenge2');
Aseco::registerEvent('onStatusChangeTo4',		'pbme_onStatusChangeTo4');		// Running - Play
Aseco::registerEvent('onPlayerConnect',			'pbme_onPlayerConnect');
Aseco::registerEvent('onPlayerFinish1',			'pbme_onPlayerFinish1');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'pbme_onPlayerManialinkPageAnswer');
Aseco::registerEvent('onEndRace1',			'pbme_onEndRace1');

global $pbme_config;

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_onSync ($aseco) {
	global $pbme_config;

	// Check for the right XAseco-Version
	$xaseco_min_version = '1.16';			// Official "1.15b", but not useable with version_compare()
	if ( defined('XASECO_VERSION') ) {
		$version = str_replace(
			array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'),
			array('.1','.2','.3','.4','.5','.6','.7','.8','.9'),
			XASECO_VERSION
		);
		if ( version_compare($version, $xaseco_min_version, '<') ) {
			triggerFatalError('[plugin.personal_best_medals.php] Not supported XAseco version ('. $version .')! Please update to min. version '. $xaseco_min_version .'!');
		}
	}
	else {
		triggerFatalError('[plugin.personal_best_medals.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco_min_version .'+');
	}

	if ($aseco->server->getGame() != 'TMF') {
		triggerFatalError('[plugin.personal_best_medals.php] This plugin supports only TMF, can not start with a "'. $aseco->server->getGame() .'" Dedicated-Server!');
	}

	// Read Configuration
	if (!$pbme_config = $aseco->xml_parser->parseXML('personal_best_medals.xml', true, true)) {
		triggerFatalError('[plugin.personal_best_medals.php] Could not read/parse config file "personal_best_medals.xml"!');
	}
	$pbme_config = $pbme_config['PERSONAL_BEST_MEDALS'];

	// Transform 'TRUE' or 'FALSE' from string to boolean
	$pbme_config['RECORD_BASE'][0]['DEDIMANIA'][0]		= ((strtoupper($pbme_config['RECORD_BASE'][0]['DEDIMANIA'][0]) == 'TRUE')	? true : false);
	$pbme_config['RECORD_BASE'][0]['ULTIMANIA'][0]		= ((strtoupper($pbme_config['RECORD_BASE'][0]['ULTIMANIA'][0]) == 'TRUE')	? true : false);
	$pbme_config['NEW_MEDAL_WIDGET'][0]['ENABLED'][0]	= ((strtoupper($pbme_config['NEW_MEDAL_WIDGET'][0]['ENABLED'][0]) == 'TRUE')	? true : false);

	// Check for the display timeout of the NewMedalWidget
	if ( (!isset($pbme_config['NEW_MEDAL_WIDGET'][0]['TIMEOUT'][0])) || ($pbme_config['NEW_MEDAL_WIDGET'][0]['TIMEOUT'][0] == 0) ) {
		$pbme_config['NEW_MEDAL_WIDGET'][0]['TIMEOUT'][0] = 5;
	}

	// Check the required Plugins and if one is not installed trigger_error()
	$required = array(
		'plugin.localdatabase.php'	=> array('status' => true),
		'plugin.dedimania.php'		=> array('status' => $pbme_config['RECORD_BASE'][0]['DEDIMANIA'][0]),
		'plugin.ultimania.php'		=> array('status' => $pbme_config['RECORD_BASE'][0]['ULTIMANIA'][0]),
	);
	foreach ($required as $plugin => &$state) {
		if ($state['status'] == false) {
			// Not required, skip check
			continue;
		}
		foreach ($aseco->plugins as &$installed_plugin) {
			if ($plugin == $installed_plugin) {
				// Found, skip to next plugin
				continue 2;
			}
		}
		triggerFatalError('[plugin.personal_best_medals.php] Unmet requirements! With your current configuration you need to activate "'. $plugin .'" in your "plugins.xml" to run this Plugin!');
	}
	unset($state, $installed_plugin);

	$pbme_config['ManialinkId'] = '923';
	$pbme_config['Version'] = '1.0.3';

	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.personal_best_medals.php',
		'author'	=> 'undef.de, Olymptor',
		'version'	=> $pbme_config['Version']
	);

	$pbme_config['CurrentTrack'] = array();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_onNewChallenge2 ($aseco, $challenge_item) {
	global $pbme_config;

	// Store the current Track data
	if ($aseco->server->gameinfo->mode == Gameinfo::STNT) {
		$pbme_config['CurrentTrack'] = array(
			'AuthorScore'	=> $challenge_item->gbx->authorScore,
			'Goldtime'	=> $challenge_item->gbx->goldTime,
			'Silvertime'	=> $challenge_item->gbx->silverTime,
			'Bronzetime'	=> $challenge_item->gbx->bronzeTime,
		);
	}
	else {
		$pbme_config['CurrentTrack'] = array(
			'AuthorScore'	=> $challenge_item->gbx->authorTime,
			'Goldtime'	=> $challenge_item->gbx->goldTime,
			'Silvertime'	=> $challenge_item->gbx->silverTime,
			'Bronzetime'	=> $challenge_item->gbx->bronzeTime,
		);
	}

	// Find the PB for all Players
	pbme_findPlayerBestScore(false);

	// Display the Widget
	foreach ($aseco->server->players->player_list as &$player) {
		pbme_buildMedalWidget($player->login, true);
	}
	unset($player);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_onStatusChangeTo4 ($aseco, $call) {
	global $pbme3_config;

	// Find the PB for all Players
	pbme_findPlayerBestScore(false);

	// Display the Widget
	foreach ($aseco->server->players->player_list as &$player) {
		pbme_buildMedalWidget($player->login, true);
	}
	unset($player);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_onEndRace1 ($aseco, $race) {
	global $pbme3_config;

	// Hide the Widget from all Players
	pbme_buildMedalWidget(false, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_onPlayerConnect ($aseco, $player) {
	global $pbme_config;

	// Add PB from the new Player
	pbme_findPlayerBestScore($player->login);

	// Display the Widget
	if (count($pbme_config['CurrentTrack']) > 0) {
		pbme_buildMedalWidget($player->login, true);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_onPlayerFinish1 ($aseco, $finish_item) {
	global $pbme_config;

	if ($finish_item->score == 0) {
		// No actual finish, bail out immediately
		return;
	}

	if ($pbme_config['NEW_MEDAL_WIDGET'][0]['ENABLED'][0] == true) {
		// Find the Medal the Player has reached last time
		$medal_before = pbme_findPlayerMedal($finish_item->player->login);
	}

	// If the Player has driven a better PM, store them and Update the Widget
	$newpb = false;
	if ($aseco->server->gameinfo->mode == Gameinfo::STNT) {
		if ( ($finish_item->score > $pbme_config['Players'][$finish_item->player->login]) || ($pbme_config['Players'][$finish_item->player->login] == 999999999) ) {
			$pbme_config['Players'][$finish_item->player->login] = $finish_item->score;
			$newpb = true;
		}
	}
	else {
		if ( ($finish_item->score < $pbme_config['Players'][$finish_item->player->login]) || ($pbme_config['Players'][$finish_item->player->login] == 999999999) ) {
			$pbme_config['Players'][$finish_item->player->login] = $finish_item->score;
			$newpb = true;
		}
	}
	if ($newpb == true) {
		// Find the Medal the Player has reached
		$player_medal = pbme_findPlayerMedal($finish_item->player->login);

		// Action, only if it has changed from '---' (none) medal
		if ($player_medal['name'] != '---') {
			// Rebuild the Players MedalWidget
			pbme_buildMedalWidget($finish_item->player->login, true);

			// Is public notification is enabled?
			if ($pbme_config['NEW_MEDAL_WIDGET'][0]['ENABLED'][0] == true) {
				// Select the right message
				if ( ($medal_before['name'] == '---') || ($medal_before['name'] != $player_medal['name']) ) {
					$time_msg = 'won the ';
				}
				else {
					$time_msg = 'secured his/her ';
				}

				// Display the NewMedalWidget to all Players
				$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
				$xml .= '<manialinks>';
				$xml .= '<manialink id="'. $pbme_config['ManialinkId'] .'02">';
				$xml .= '<frame posn="'. $pbme_config['NEW_MEDAL_WIDGET'][0]['POS_X'][0] .' '. $pbme_config['NEW_MEDAL_WIDGET'][0]['POS_Y'][0] .' 2">';
				$xml .= '<quad posn="0 0 0.02" sizen="4.5 4.5" style="MedalsBig" substyle="'. $player_medal['medal'] .'"/>';
				$xml .= '<label posn="5.5 -2.25 0.03" sizen="65 4.5" valign="center" textsize="'. $pbme_config['NEW_MEDAL_WIDGET'][0]['TEXTSIZE'][0] .'" textcolor="'. $pbme_config['NEW_MEDAL_WIDGET'][0]['TEXTCOLOR'][0]['DEFAULT'][0] .'F" text="$SPlayer $'. $pbme_config['NEW_MEDAL_WIDGET'][0]['TEXTCOLOR'][0]['HIGHLITE'][0] . pbme_handleSpecialChars(stripColors($finish_item->player->nickname)) .'$Z$S '. $time_msg . $player_medal['name'] .' medal!"/>';
				$xml .= '</frame>';
				$xml .= '</manialink>';
				$xml .= '</manialinks>';
				$aseco->client->query('SendDisplayManialinkPage', $xml, ($pbme_config['NEW_MEDAL_WIDGET'][0]['TIMEOUT'][0] * 1000), false);
			}
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerManialinkPageAnswer
// $answer = [0]=PlayerUid, [1]=Login, [2]=Answer
function pbme_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $pbme_config;

	// If id = 0, bail out immediately
	if ($answer[2] == 0) {
		return;
	}

	// Init
	$widgets = '';

	if ($answer[2] == (int)$pbme_config['ManialinkId'] .'00') {

		// Show PlayerMedalWindow
		$widgets .= pbme_buildPlayerMedalWindow();

	}
	else if ($answer[2] == (int)$pbme_config['ManialinkId'] .'01') {

		// Close PlayerMedalWindow
		$widgets .= '<manialink id="'. $pbme_config['ManialinkId'] .'01"></manialink>';

	}

	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= $widgets;
	$xml .= '</manialinks>';

	// Send to given Player
	$aseco->client->query('SendDisplayManialinkPageToLogin', $answer[1], $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_buildMedalWidget ($login = false, $display = true) {
	global $aseco, $pbme_config;

	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $pbme_config['ManialinkId'] .'00">';
	if ($display == true) {
		// Find the Medal the Player has reached
		$player_medal = pbme_findPlayerMedal($login);

		$xml .= '<frame posn="'. $pbme_config['WIDGET'][0]['POS_X'][0] .' '. $pbme_config['WIDGET'][0]['POS_Y'][0] .' 0">';
		$xml .= '<format textsize="1"/>';
		$xml .= '<quad posn="0 0 0.001" sizen="4.6 6.5" action="'. $pbme_config['ManialinkId'] .'00" style="'. $pbme_config['WIDGET'][0]['BACKGROUND_STYLE'][0] .'" substyle="'. $pbme_config['WIDGET'][0]['BACKGROUND_SUBSTYLE'][0] .'"/>';
		$xml .= '<quad posn="-0.18 -4.6 0.002" sizen="2.1 2.1" image="'. $pbme_config['IMAGES'][0]['WIDGET_OPEN_SMALL'][0] .'"/>';
		$xml .= '<quad posn="0.8 -0.4 0.002" sizen="3 3" style="MedalsBig" substyle="'. $player_medal['medal'] .'"/>';
		$xml .= '<label posn="2.3 -3.4 0.1" sizen="3.65 2" halign="center" text="'. $player_medal['name'] .'"/>';
		$xml .= '<label posn="2.3 -4.9 0.1" sizen="6.35 2" halign="center" textcolor="'. $pbme_config['WIDGET'][0]['TEXT_COLOR'][0] .'" scale="0.6" text="MEDAL"/>';
		$xml .= '</frame>';
	}
	$xml .= '</manialink>';
	if ($display == false) {
		// On 'false' close also the PlayerMedalWindow and the NewMedalWidget (called at the event onEndRace1)
		$xml .= '<manialink id="'. $pbme_config['ManialinkId'] .'01"></manialink>';
		$xml .= '<manialink id="'. $pbme_config['ManialinkId'] .'02"></manialink>';
	}
	$xml .= '</manialinks>';


	if ($login !=  false) {
		// Send to given Player
		$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, 0, false);
	}
	else {
		// Send to all connected Players
		$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_buildPlayerMedalWindow () {
	global $aseco, $pbme_config;


	$xml = '<manialink id="'. $pbme_config['ManialinkId'] .'01">';
	$xml .= '<frame posn="-40.1 30.45 3">';			// BEGIN: Window Frame
	$xml .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="001B"/>';
	$xml .= '<quad posn="-0.2 0.2 0.04" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

	// Header Line
	$xml .= '<quad posn="0.8 -1.3 0.02" sizen="78.4 3" bgcolor="09FC"/>';
	$xml .= '<quad posn="0.8 -4.3 0.03" sizen="78.4 0.1" bgcolor="FFF9"/>';
	$xml .= '<quad posn="1.8 -1 0.04" sizen="3.2 3.2" style="MedalsBig" substyle="MedalNadeoPerspective"/>';

	// Title
	$xml .= '<label posn="5.5 -1.9 0.04" sizen="74 0" textsize="2" scale="0.9" textcolor="FFFF" text="Personal Best Medals TOP-100"/>';
	$xml .= '<quad posn="2.7 -54.1 0.04" sizen="11 1" url="http://www.undef.name/XAseco1/Personal-Best-Medals.php" bgcolor="0000"/>';
	$xml .= '<label posn="2.7 -54.1 0.04" sizen="30 1" textsize="1" scale="0.7" textcolor="000F" text="PERSONAL-BEST-MEDALS/'. $pbme_config['Version'] .'"/>';

	// Close Button
	$xml .= '<frame posn="77.4 1.3 0.05">';
	$xml .= '<quad posn="0 0 0.01" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
	$xml .= '<quad posn="1.1 -1.35 0.02" sizen="1.8 1.75" bgcolor="EEEF"/>';
	$xml .= '<quad posn="0.65 -0.7 0.03" sizen="2.6 2.6" action="'. $pbme_config['ManialinkId'] .'01" style="Icons64x64_1" substyle="Close"/>';
	$xml .= '</frame>';

	// Reload Button
	$xml .= '<quad posn="75.3 -53.2 0.12" sizen="3.2 3.2" action="'. $pbme_config['ManialinkId'] .'00" style="Icons64x64_1" substyle="Refresh"/>';

	// Content
	$xml .= '<frame posn="2.5 -6.5 1">';
	$xml .= '<format textsize="1" textcolor="FFFF"/>';

	$xml .= '<quad posn="0 0.8 0.02" sizen="17.75 46.88" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="19.05 0.8 0.02" sizen="17.75 46.88" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="38.1 0.8 0.02" sizen="17.75 46.88" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="57.15 0.8 0.02" sizen="17.75 46.88" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';

	// Resort by 'score'
	$data = array();
	foreach ($pbme_config['Players'] as $login => &$score) {
		$data[$login] = $score;
	}
	array_multisort($data, SORT_NUMERIC, SORT_ASC, $pbme_config['Players']);
	unset($data, $login, $score);

	$entry = 1;
	$line = 0;
	$offset = 0;
	foreach ($pbme_config['Players'] as $login => $time_score) {

		$player = $aseco->server->players->player_list[$login];
		if ( !$player->id ) {
			// Skip disconnected Players
			continue;
		}

		// Find the Medal the Player has reached
		$player_medal = pbme_findPlayerMedal($login);

		if ($aseco->server->gameinfo->mode == Gameinfo::STNT) {
			$score = (($time_score == 999999999) ? '---' : $time_score);
		}
		else {
			// All other Gamemodes
			$score = formatTime( (($time_score == 999999999) ? -1 : $time_score) );
		}

		$xml .= '<quad posn="'. (0.6 + $offset) .' '. (($line == 0) ? 0.3 : -(1.83 * $line - 0.3)) .' 0.03" sizen="2 2" style="MedalsBig" substyle="'. $player_medal['medal'] .'"/>';
		$xml .= '<label posn="'. (6.4 + $offset) .' -'. (1.83 * $line) .' 0.04" sizen="4 1.7" halign="right" scale="0.9" textcolor="DDDF" text="'. $score .'"/>';
		$xml .= '<label posn="'. (6.9 + $offset) .' -'. (1.83 * $line) .' 0.04" sizen="11.2 1.7" scale="0.9" text="'. pbme_handleSpecialChars($player->nickname) .'"/>';

		$line ++;
		$entry ++;

		// Reset lines
		if ($line >= 25) {
			$offset += 19.05;
			$line = 0;
		}

		// Display max. 100 entries, count start from 1
		if ($entry >= 101) {
			break;
		}
	}
	$xml .= '</frame>';

	// Footer
	$xml .= '</frame>';						// END: Window Frame
	$xml .= '</manialink>';

	return $xml;
}


/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// $dedi_db is imported from plugin.dedimania.php
// $ultimania_db is imported from plugin.ultimania.php
function pbme_findPlayerBestScore ($login = false) {
	global $aseco, $pbme_config, $dedi_db, $ultimania_db;


	if ($login == false) {
		// Find the best time/score for all Players
		$pbme_config['Players'] = array();
		foreach ($aseco->server->players->player_list as &$player) {
			$pbme_config['Players'][$player->login] = 999999999;
		}
		unset($player);

		// Find in LocalRecords
		if (count($aseco->server->records->record_list) > 0) {
			foreach ($aseco->server->players->player_list as &$player) {
				foreach ($aseco->server->records->record_list as &$entry) {
					if ( ($entry->player->login == $player->login) && ($entry->score > 0) ) {
						$pbme_config['Players'][$player->login] = $entry->score;
						continue 2;
					}
				}
			}
			unset($player);
		}

		// Find in DedimaniaRecords (if enabled and NOT for Gamemode 'Stunts')
		if ( ($pbme_config['RECORD_BASE'][0]['DEDIMANIA'][0] == true) && ($aseco->server->gameinfo->mode != Gameinfo::STNT) ) {

			if ( ( isset($dedi_db['Challenge']['Records']) ) && (count($dedi_db['Challenge']['Records']) > 0) ) {
				foreach ($aseco->server->players->player_list as &$player) {
					for ($i = 0; $i < count($dedi_db['Challenge']['Records']); $i ++) {
						if ( ($dedi_db['Challenge']['Records'][$i]['Login'] == $player->login) && (($dedi_db['Challenge']['Records'][$i]['Best'] < $pbme_config['Players'][$player->login]) || ($pbme_config['Players'][$player->login] == 999999999)) ) {
							$pbme_config['Players'][$player->login] = $dedi_db['Challenge']['Records'][$i]['Best'];
							continue 2;
						}
					}
				}
				unset($player);
			}

		}

		// Find in UltimaniaRecords (if enabled and ONLY for Gamemode 'Stunts')
		if ( ($pbme_config['RECORD_BASE'][0]['ULTIMANIA'][0] == true) && ($aseco->server->gameinfo->mode == Gameinfo::STNT) ) {
			if ( ($ultimania_db != false) && (count($ultimania_db) > 0) ) {
				foreach ($aseco->server->players->player_list as &$player) {
					for ($i = 0; $i < count($ultimania_db); $i ++) {
						if ( ($ultimania_db[$i]['login'] == $player->login) && (($ultimania_db[$i]['score'] > $pbme_config['Players'][$player->login]) || ($pbme_config['Players'][$player->login] == 999999999)) ) {
							$pbme_config['Players'][$player->login] = $ultimania_db[$i]['score'];
							continue 2;
						}
					}
				}
				unset($player);
			}
		}
	}
	else {
		// Find the best time/score for given Player only
		$score = 999999999;

		// Find in LocalRecords
		foreach ($aseco->server->records->record_list as &$entry) {
			if ( ($entry->player->login == $login) && ($entry->score > 0) ) {
				$score = $entry->score;
				break;
			}
		}

		// Find in DedimaniaRecords (if enabled and NOT for Gamemode 'Stunts')
		if ( ($pbme_config['RECORD_BASE'][0]['DEDIMANIA'][0] == true) && ($aseco->server->gameinfo->mode != Gameinfo::STNT) ) {
			if ( ( isset($dedi_db['Challenge']['Records']) ) && (count($dedi_db['Challenge']['Records']) > 0) ) {
				for ($i = 0; $i < count($dedi_db['Challenge']['Records']); $i ++) {
					if ( ($dedi_db['Challenge']['Records'][$i]['Login'] == $login) && (($dedi_db['Challenge']['Records'][$i]['Best'] < $score) || ($score == 999999999)) ) {
						$score = $dedi_db['Challenge']['Records'][$i]['Best'];
						break;
					}
				}
			}
		}

		// Find in UltimaniaRecords (if enabled and ONLY for Gamemode 'Stunts')
		if ( ($pbme_config['RECORD_BASE'][0]['ULTIMANIA'][0] == true) && ($aseco->server->gameinfo->mode == Gameinfo::STNT) ) {
			if ( ($ultimania_db != false) && (count($ultimania_db) > 0) ) {
				for ($i = 0; $i < count($ultimania_db); $i ++) {
					if ( ($ultimania_db[$i]['login'] == $login) && (($ultimania_db[$i]['score'] > $score) || ($score == 999999999)) ) {
						$score = $ultimania_db[$i]['score'];
						break;
					}
				}
			}
		}

		// Set the found best score
		$pbme_config['Players'][$login] = $score;
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_findPlayerMedal ($login) {
	global $aseco, $pbme_config;

	$player_medal = array(
		'medal' => 'MedalSlot',
		'name'	=> '---',
	);
	if ( (isset($pbme_config['Players'][$login])) && ($pbme_config['Players'][$login] != 999999999) ) {
		if ($aseco->server->gameinfo->mode == Gameinfo::STNT) {
			if ($pbme_config['Players'][$login] > $pbme_config['CurrentTrack']['AuthorScore']) {
				$player_medal = array(
					'medal' => 'MedalNadeo',
					'name'	=> 'Author',
				);
			}
			else if ($pbme_config['Players'][$login] > $pbme_config['CurrentTrack']['Goldtime']) {
				$player_medal = array(
					'medal' => 'MedalGold',
					'name'	=> 'Gold',
				);
			}
			else if ($pbme_config['Players'][$login] > $pbme_config['CurrentTrack']['Silvertime']) {
				$player_medal = array(
					'medal' => 'MedalSilver',
					'name'	=> 'Silver',
				);
			}
			else if ($pbme_config['Players'][$login] > $pbme_config['CurrentTrack']['Bronzetime']) {
				$player_medal = array(
					'medal' => 'MedalBronze',
					'name'	=> 'Bronze',
				);
			}
		}
		else {
			if ($pbme_config['Players'][$login] < $pbme_config['CurrentTrack']['AuthorScore']) {
				$player_medal = array(
					'medal' => 'MedalNadeo',
					'name'	=> 'Author',
				);
			}
			else if ($pbme_config['Players'][$login] < $pbme_config['CurrentTrack']['Goldtime']) {
				$player_medal = array(
					'medal' => 'MedalGold',
					'name'	=> 'Gold',
				);
			}
			else if ($pbme_config['Players'][$login] < $pbme_config['CurrentTrack']['Silvertime']) {
				$player_medal = array(
					'medal' => 'MedalSilver',
					'name'	=> 'Silver',
				);
			}
			else if ($pbme_config['Players'][$login] < $pbme_config['CurrentTrack']['Bronzetime']) {
				$player_medal = array(
					'medal' => 'MedalBronze',
					'name'	=> 'Bronze',
				);
			}
		}
	}
	return $player_medal;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbme_handleSpecialChars ($string) {

	// Remove links, e.g. "$(L|H|P)[...]...$(L|H|P)"
	$string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)\$(L|H|P)/i', '$2', $string);
	$string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)/i', '$2', $string);
	$string = preg_replace('/\${1}(L|H|P)(.*?)/i', '$2', $string);

	// Remove $S (shadow)
	// Remove $H (manialink)
	// Remove $W (wide)
	// Remove $I (italic)
	// Remove $L (link)
	// Remove $O (bold)
	// Remove $N (narrow)
	$string = preg_replace('/\${1}[SHWILON]/i', '', $string);

	// Convert &
	// Convert "
	// Convert '
	// Convert >
	// Convert <
	$string = str_replace(
			array(
				'&',
				'"',
				"'",
				'>',
				'<'
			),
			array(
				'&amp;',
				'&quot;',
				'&apos;',
				'&gt;',
				'&lt;'
			),
			$string
	);
	$string = stripNewlines($string);	// stripNewlines() from basic.inc.php

	return validateUTF8String($string);	// validateUTF8String() from basic.inc.php
}

?>
