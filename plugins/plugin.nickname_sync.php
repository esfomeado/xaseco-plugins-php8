<?php

/*
 * Plugin: Nickname Sync
 * ~~~~~~~~~~~~~~~~~~~~~
 * This Plugin synchronizes the Nicknames of Players in your local database with
 * Nicknames from DedimaniaRecords or GerymaniaRecords
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Original Author:	.anDy
 * Version:		1.2.1
 * Date:		2012-10-21
 * Copyright:		2011 - 2012 by .anDy and undef.de
 * Home:		http://www.undef.de/Trackmania/Plugins/
 * System:		XAseco/1.15+
 * Game:		Trackmania Forever (TMF) only
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
 *  - plugins/plugin.localdatabase.php		Required, for Datasbase access and Local-Records-Widget
 *  - plugins/plugin.dedimania.php		Required, only if you want the Dedimania-Sync
 *  - plugins/plugin.gerymania.php		Required, only if you want the Gerymania-Sync (only for Gamemode 'Stunts'. Get it here: http://www.tm-forum.com/viewtopic.php?f=127&t=27398)
 *
 */


Aseco::registerEvent('onSync',			'ns_onSync');


global $ns_config;


/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onSync
function ns_onSync ($aseco) {
	global $ns_config;


	// Check for the right XAseco-Version
	$xaseco_min_version = '1.15';
	if ( defined('XASECO_VERSION') ) {
		$version = str_replace(
			array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'),
			array('.1','.2','.3','.4','.5','.6','.7','.8','.9'),
			XASECO_VERSION
		);
		if ( version_compare($version, $xaseco_min_version, '<') ) {
			triggerFatalError('[plugin.nickname_sync.php] Not supported XAseco version ('. $version .')! Please update to min. version '. $xaseco_min_version .'!');
		}
	}
	else {
		triggerFatalError('[plugin.nickname_sync.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco_min_version .'+');
	}

	if ($aseco->server->getGame() != 'TMF') {
		triggerFatalError('[plugin.nickname_sync.php] This plugin supports only TMF, can not start with a "'. $aseco->server->getGame() .'" Dedicated-Server!');
	}


	// Check for required plugins
	$required = array('plugin.localdatabase.php' => true);
	foreach ($required as $plugin => &$state) {
		foreach ($aseco->plugins as $installed_plugin) {
			if ($plugin == $installed_plugin) {
				// Found, skip to next plugin
				continue 2;
			}
		}
		triggerFatalError('[plugin.nickname_sync.php] Unmet requirements! You need to activate "' . $plugin . '" in your "plugins.xml" to run this Plugin!');
	}


	// Check for optional plugins
	$optional = array(
		'plugin.dedimania.php'		=> true,
		'plugin.gerymania.php'		=> true
	);
	$optionalCount = 0;
	foreach ($optional as $plugin => &$state) {
		$ns_config['Plugins'][$plugin] = false;			// Auto off, turn on if found
		foreach ($aseco->plugins as &$installed_plugin) {
			if ($plugin == $installed_plugin) {
				$ns_config['Plugins'][$plugin] = true;
				// Found, skip to next plugin
				continue 2;
			}
		}
		unset($installed_plugin);
		$optionalCount++;
	}
	unset($state);
	if (count($optional) == $optionalCount) {
		trigger_error('[plugin.nickname_sync.php] Plugin is currently useless! You need to activate "' . implode('" or "', array_keys($optional)) . '" in your "plugins.xml" to run this Plugin!', E_USER_WARNING);
		$ns_config['SyncNicknames'] = false;
	}
	else {
		$ns_config['SyncNicknames'] = true;
	}


	// Register related events
	if ($ns_config['Plugins']['plugin.dedimania.php'] == true) {
		$aseco->registerEvent('onDediRecsLoaded',		'ns_onDedimaniaRecordsLoaded');
	}
	if ($ns_config['Plugins']['plugin.gerymania.php'] == true) {
		$aseco->registerEvent('onGerymaniaRecordsLoaded',	'ns_onGerymaniaRecordsLoaded');
	}


	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.nickname_sync.php',
		'author'	=> 'undef.de',
		'version'	=> '1.2.1'
	);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// $dedi_db is imported from plugin.dedimania.php
function ns_onDedimaniaRecordsLoaded ($aseco, $RecsValid) {
	global $ns_config, $dedi_db;


	if ( ( isset($dedi_db['Challenge']['Records']) ) && (count($dedi_db['Challenge']['Records']) > 0) ) {
		ns_comparePlayers($dedi_db['Challenge']['Records'], 'Dedimania', 'Login', 'NickName');
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// $gery_db is imported from plugin.gerymania.php
function ns_onGerymaniaRecordsLoaded ($aseco, $db) {
	global $ns_config, $gery_db;


	if ( ($gery_db != false) && (count($gery_db) > 0) ) {
		ns_comparePlayers($gery_db, 'Gerymania', 'login', 'nick');
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ns_comparePlayers ($publics, $database, $keyLogin, $keyNick) {
	global $aseco;


	$playersToUpdate = array();
	$playersUnknownPublic = array();
	foreach ($publics as &$public) {

		// Do not compare/sync TMN-Logins from Dedimania with TMF-Logins (if this is the Database from Dedimania)
		if ( ( isset($public['Game']) ) && ($public['Game'] == 'TMN') ) {
			continue;
		}

		// Check if player is currently on server
		if (isset($aseco->server->players->player_list[$public[$keyLogin]])) {
			// Player is online, no action
			continue;
		}

		// Search Nickname of Player in local Records
		foreach ($aseco->server->records->record_list as &$local) {
			if ($local->player->login == $public[$keyLogin]) {
				// Check for different Nickname
				if ($local->player->nickname != $public[$keyNick]) {
					// Update Nickname in local Records (impermanent, only for showing new Nickname in Widgets)
					$local->player->nickname = $public[$keyNick];
					// Add it to the update list to make this Nickname permament
					$playersToUpdate[$public[$keyLogin]] = $public[$keyNick];
				}
				continue 2;
			}
		}

		// Nickname not found, cache it for Database search
		$playersUnknownPublic[$public[$keyLogin]] = $public[$keyNick];
		continue;
	}
	unset($public, $local);

	if (count($playersUnknownPublic) > 0) {

		// Check if Players exists on Database
		$playersKnownLocal = ns_getPlayers($playersUnknownPublic);

		foreach ($playersKnownLocal as $login => &$nick) {
			// Check for different Nickname
			if ($playersUnknownPublic[$login] != $nick) {
				// Add it to update list to make it permament
				$playersToUpdate[$login] = $playersUnknownPublic[$login];
			}
		}
		unset($playersKnownLocal, $nick);
	}
	unset($playersUnknownPublic);

	// Update all new Nicknames to Databases
	ns_updatePlayers($playersToUpdate, $database);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ns_updatePlayers ($players, $database) {
	global $aseco;


	if (count($players) > 0) {
		$aseco->console('[plugin.nickname_sync.php] Update Nicknames of ['. implode(', ', array_keys($players)) .'] from '. $database);

		foreach ($players as $login => &$nick) {
			// Update Player Nickname
			$query = "UPDATE `players` SET `NickName`='". $aseco->db->real_escape_string($nick) ."' WHERE `Login`='". $aseco->db->real_escape_string($login) ."';";
			$result = $aseco->db->query($query);

			if ($aseco->db->affected_rows == -1) {
				trigger_error('[plugin.nickname_sync.php] Could not update Player from '. $database .' Database! ('. $aseco->db->error .')'. CRLF .'sql = '. $query, E_USER_WARNING);
			}
		}
		unset($nick);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ns_getPlayers ($players) {
	global $aseco;


	// Find Players from Players-Array in Database
	$players = array();
	$query = "SELECT `Login`,`NickName` FROM `players` WHERE `Login` IN ('". implode("','", array_keys($players)) ."');";

	$result = $aseco->db->query($query);
	if ($result) {
		if ($result->num_rows > 0) {
			while ($row = $result->fetch_row()) {
				// row[0] = Login, row[1] = Nickname
				$players[$row[0]] = $row[1];
			}
		}

		// Free up the results
		if ($result) $result->free();
	}

	return $players;
}

?>
