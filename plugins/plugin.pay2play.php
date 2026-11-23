<?php

/* Pay2Play v1.01
 *
 * Plugin by Leigham.
 *
 * Important: This plugin will only work on a TMUF server. 
 * The server must have at least a small amount of coppers for
 * the plugin to work.
 *
 * All settings are configurable in the pay2play.xml file.
 */

Aseco::registerEvent('onStartup',                   'p2p_setup');
Aseco::registerEvent('onSync',                      'p2p_maintain');
Aseco::registerEvent('onPlayerConnect',             'p2p_connect');
Aseco::registerEvent('onNewChallenge',							'p2p_check');
Aseco::registerEvent('onBeginRound',                'p2p_check');
Aseco::registerEvent('onEndRound',									'p2p_off');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'p2p_click');
Aseco::registerEvent('onBillUpdated',               'p2p_bill');
Aseco::registerEvent('onEverySecond',               'p2p_time');
Aseco::registerEvent('onShutdown',									'p2p_apocalypse');


function p2p_setup($aseco) { //Read pay2play.xml and load settings
	global $p2p;
	
	$p2p = array();
	$votes = array();
	
	if ($config = $aseco->xml_parser->parseXml('pay2play.xml', true)) {
    $config = $config['SETTINGS'];
    
    if (strtolower($config['REPLAY'][0]['ENABLED'][0]) == 'true') {
    	$p2p['replay']['active'] = true;
    	$p2p['replay']['position'] = floatval($config['REPLAY'][0]['POSX'][0]).' '.floatval($config['REPLAY'][0]['POSY'][0]).' 1';
    	$p2p['replay']['cost'] = intval($config['REPLAY'][0]['COST'][0]);
    	$p2p['replay']['max_val'] = intval($config['REPLAY'][0]['MAX_REPLAYS'][0]);
    	$p2p['replay']['blink'] = ((strtolower($config['REPLAY'][0]['BLINK'][0]) == 'true') ? true : false);
    	if ($config['REPLAY'][0]['CVOTE_OFF'][0] == 'true') $votes[] = array('Command'	=> 'ChallengeRestart', 'Ratio' => (float)-1);
    	$p2p['replay']['total'] = 0;
    	$p2p['replay']['success'] = false;
    	$p2p['replay']['max'] = false;
    } else {
    	$p2p['replay']['active'] = false;
    } 
    
    if (strtolower($config['SKIP'][0]['ENABLED'][0]) == 'true') {
    	$p2p['skip']['active'] = true;
    	$p2p['skip']['position'] =floatval($config['SKIP'][0]['POSX'][0]).' '.floatval($config['SKIP'][0]['POSY'][0]).' 1';
    	$p2p['skip']['cost'] = intval($config['SKIP'][0]['COST'][0]);
    	$p2p['skip']['delay'] = intval($config['SKIP'][0]['DELAY'][0]);
    	$p2p['skip']['blink'] = ((strtolower($config['SKIP'][0]['BLINK'][0]) == 'true') ? true : false);
    	if ($config['REPLAY'][0]['CVOTE_OFF'][0] == 'true') $votes[] = array('Command'	=> 'NextChallenge', 'Ratio' => (float)-1);
    	$p2p['skip']['success'] = false;
    } else {
    	$p2p['skip']['active'] = false;
    }
    
    $p2p['score'] = false;
    $p2p['manialink'] = '12891';
    $p2p['thistrack'] = array();
    $p2p['bills'] = array();
    $p2p['timelimit'] = -1;
    
    if (isset($votes[0])) {
    	$aseco->client->query('GetCallVoteRatios');
			$p2p['oldvotes'] = $aseco->client->getResponse();
			$aseco->client->query('SetCallVoteRatios', $votes);
		}
      
	} else {
		triggerFatalError('[Pay2Play] Could not read/parse settings file pay2play.xml!');
		return false;
	}
}

function p2p_maintain($aseco) { 	// Do maintenance checks
	global $p2p;
	
  $aseco->plugin_versions[] = array(
     'plugin'   => 'plugin.pay2play.php',
     'author'   => 'Leigham',
     'version'   => '1.01'
  );
  
  if (!in_array('plugin.rasp_jukebox.php', $aseco->plugins)) {
  	triggerFatalError('[Pay2Play] This plugin requires the rasp jukebox. Please activate "plugin.rasp_jukebox.php" in your plugins.xml file!');
  }
}

function p2p_connect($aseco) { //Deal with new player
	global $p2p;
	
	if (!$p2p['score']) {
		if ($p2p['replay']['active']) {
			if (!$p2p['replay']['success']){
				if (!$p2p['replay']['max']) {
					p2p_buildReplay($aseco);
				} else {
					p2p_buildReplay($aseco, 'max');
				}
			} else {
				p2p_buildReplay($aseco, 'success');
			}
		}
		if ($p2p['skip']['active']) {
			if (!$p2p['skip']['success']) {	
				p2p_buildSkip($aseco);
			} else {
				p2p_buildSkip($aseco, 'success');
			}
		}
	}
}

function p2p_check($aseco) { //Check track and load widgets
	global $p2p;
	
	$p2p['score'] = false;
	
	if ($p2p['replay']['active']) {
		$aseco->client->query('GetCurrentChallengeInfo');
		$thistrack = $aseco->client->getResponse();

		if (isset($p2p['thistrack']['FileName']) && $p2p['thistrack']['FileName'] == $thistrack['FileName']) {
			
			if ($p2p['replay']['total'] >= $p2p['replay']['max_val'] && $p2p['replay']['max_val'] != 0) {
				$p2p['replay']['max'] = true;
				p2p_buildReplay($aseco, 'max'); 
			} else {
				p2p_buildReplay($aseco);
				$p2p['replay']['max'] = false;
			}
		} else {
			$p2p['replay']['total'] = 0;
			$p2p['replay']['max'] = false;
			p2p_buildReplay($aseco);
		}
		$p2p['thistrack'] = $thistrack;
	}
	if ($p2p['skip']['active']) {
		p2p_buildSkip($aseco);
	}
	$p2p['timelimit'] = time() + ($aseco->server->gameinfo->timelimit / 1000);
}

function p2p_off($aseco) { //Close widgets and reset variables
	global $p2p;
	
	$p2p['replay']['success'] = false;
	$p2p['skip']['success'] = false;
	$p2p['score'] = true;
	
	$xml = '<manialink id="'.$p2p['manialink'].'00">
	</manialink> <manialink id="'.$p2p['manialink'].'01">
	</manialink>';
		
	$aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
}

function p2p_buildReplay($aseco, $state = false) { //Build replay widget
	global $p2p;
	
	if ($state == 'success') {
		
		if ($p2p['replay']['blink']) {
			$a = array('style="TextTitle2Blink"', 0.5, 0.35, '$ccc', '$c90', 7);
		} else {
			$a = array('', 0.9, 0.55, '', '', 4.5);
		}
		
		$xml = '<manialink id="'.$p2p['manialink'].'00">
   	 <frame posn="'.$p2p['replay']['position'].'">
   	    <quad posn="0 0 0" sizen="4.6 6.5" style="BgsPlayerCard" substyle="BgCardSystem"/>
   	    <label posn="2.25 -0.75 0.1" sizen="'.$a[5].' 2" halign="center" '.$a[0].' textsize="1" scale="'.$a[1].'" textcolor="FFFF" text="'.$a[3].'TRACK"/>
   	    <label posn="2.25 -2.3 0.1" sizen="8.4 2" halign="center" '.$a[0].' textsize="1" scale="'.$a[2].'" textcolor="FC0F" text="'.$a[4].'WILL BE"/>
   	    <label posn="2.25 -3.55 0.1" sizen="'.$a[5].' 2" halign="center" '.$a[0].' textsize="1" scale="'.$a[1].'" textcolor="FFFF" text="'.$a[3].'REPLAYED"/>
   	    <label posn="2.25 -5 0.1" sizen="8.4 2" halign="center" '.$a[0].' textsize="1" scale="'.$a[2].'" textcolor="FC0F" text="'.$a[4].'NEXT!"/>
   		</frame>
		</manialink>';
		
	} elseif ($state == 'max') {
		
		$xml = '<manialink id="'.$p2p['manialink'].'00">
   	 	<frame posn="'.$p2p['replay']['position'].'">
        <quad posn="0 0 0" sizen="4.6 6.5" style="BgsPlayerCard" substyle="BgCardSystem"/>
        <label posn="2.25 -0.75 0.1" sizen="5 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="MAXIMUM"/>
        <label posn="2.25 -2.3 0.1" sizen="8.4 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="REPLAY"/>
        <label posn="2.25 -3.55 0.1" sizen="5 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="LIMIT"/>
        <label posn="2.25 -5 0.1" sizen="8.4 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="REACHED!"/>
    	</frame>
		</manialink>';
			
	} else {
	
		$xml = '<manialink id="'.$p2p['manialink'].'00">
  	  <frame posn="'.$p2p['replay']['position'].'">
  	    <quad posn="0 0 0" sizen="4.6 6.5" style="BgsPlayerCard" substyle="BgCardSystem" action="'.$p2p['manialink'].'00"/>
  	    <label posn="2.25 -0.75 0.1" sizen="5 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="PAY '.$p2p['replay']['cost'].'"/>
   	    <label posn="2.25 -2.3 0.1" sizen="8.4 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="COPPERS"/>
   	    <label posn="2.25 -3.55 0.1" sizen="5 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="FOR"/>
   	    <label posn="2.25 -5 0.1" sizen="8.4 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="REPLAY"/>
   	 </frame>
		</manialink>';
	}
	$aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
}

function p2p_buildSkip($aseco, $state = false) { //Build skip widget
	global $p2p;
	
	if ($state == 'success') {
		
		if ($p2p['skip']['blink']) {
			$a = array('style="TextTitle2Blink"', 0.5, 0.35, '$ccc', '$c90', 7);
		} else {
			$a = array('', 0.9, 0.6, '', '', 5);
		}
		
		$xml = '<manialink id="'.$p2p['manialink'].'01">
 		  <frame posn="'.$p2p['skip']['position'].'">
				<quad posn="0 0 0" sizen="4.6 6.5" style="BgsPlayerCard" substyle="BgCardSystem"/>
	      <label posn="2.25 -0.75 0.1" sizen="'.$a[5].' 2" halign="center" '.$a[0].' textsize="1" textcolor="FFFF" scale="'.$a[1].'" text="'.$a[3].'TRACK"/>
				<label posn="2.25 -2.3 0.1" sizen="8.4 2" halign="center" '.$a[0].' textsize="1" textcolor="FCOF" scale="'.$a[2].'" text="'.$a[4].'WILL BE"/>
				<label posn="2.25 -3.55 0.1" sizen="'.$a[5].' 2" halign="center" '.$a[0].' textsize="1" textcolor="FFFF" scale="'.$a[1].'" text="'.$a[3].'SKIPPED"/>
 	 	    <label posn="2.25 -5 0.1" sizen="8.4 2" halign="center" '.$a[0].' textsize="1" textcolor="FCOF" scale="'.$a[2].'" text="'.$a[4].'SHORTLY!"/>
 			</frame>
		</manialink>';
		
	} else {
	
		$xml = '<manialink id="'.$p2p['manialink'].'01">
 		  <frame posn="'.$p2p['skip']['position'].'">
	      <quad posn="0 0 0" sizen="4.6 6.5" style="BgsPlayerCard" substyle="BgCardSystem" action="'.$p2p['manialink'].'01"/>
	      <label posn="2.25 -0.75 0.1" sizen="5 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="PAY '.$p2p['skip']['cost'].'"/>
 	 	    <label posn="2.25 -2.3 0.1" sizen="8.4 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="COPPERS"/>
 	 	    <label posn="2.25 -3.55 0.1" sizen="5 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="FOR"/>
 	 	    <label posn="2.25 -5 0.1" sizen="8.4 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="SKIP"/>
 	 		</frame>
		</manialink>';
	}
	$aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
}

function p2p_click($aseco, $command) {  //Deal with button clicks
	global $p2p;
	
	$login = $command[1];
	$answer = $command[2];
	$aseco->client->query('GetDetailedPlayerInfo', $login);
	$player = $aseco->client->getResponse();
	$nickname = $player['NickName'];
	
	if ($answer == $p2p['manialink'].'00') {
		$aseco->client->query('GetNextChallengeInfo');
		$nexttrack = $aseco->client->getResponse();
		if ($p2p['thistrack']['FileName'] != $nexttrack['FileName']) {
			//	Check for TMF server
			if ($aseco->server->getGame() == 'TMF') {
				// check for TMUF server
				if ($aseco->server->rights) {
					// check for TMUF player
					if ($player['OnlineRights'] == 3) {
						// start the transaction
						$message = 'You need to pay '.$p2p['replay']['cost'].' coppers to replay this track';
						$aseco->client->query('SendBill', $login, $p2p['replay']['cost'], $message, '');
						$id = $aseco->client->getResponse();
						$p2p['bills'][$id] = array($login, $nickname, 'replay');
					} else {
					$message = formatText($aseco->getChatMessage('UNITED_ONLY'), 'account');
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
					}
				} else {
				$message = formatText($aseco->getChatMessage('UNITED_ONLY'), 'server');
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				}
			} else {
			$message = $aseco->getChatMessage('FOREVER_ONLY');
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			}
		} else {
			$message = '>$f00 This track is already being replayed';
			$aseco->client->query('ChatSendServerMessageToLogin', $message, $login);
		}
	} elseif ($answer == $p2p['manialink'].'01') {
		if ($p2p['timelimit'] >= $p2p['skip']['delay'] + time() + 10) {
			//	Check for TMF server
			if ($aseco->server->getGame() == 'TMF') {
				// check for TMUF server
				if ($aseco->server->rights) {
					// check for TMUF player
					if ($player['OnlineRights'] == 3) {
						// start the transaction
						$message = 'You need to pay '.$p2p['skip']['cost'].' coppers to skip this track';
						$aseco->client->query('SendBill', $login, $p2p['skip']['cost'], $message, '');
						$id = $aseco->client->getResponse();
						$p2p['bills'][$id] = array($login, $nickname, 'skip');
					} else {
					$message = formatText($aseco->getChatMessage('UNITED_ONLY'), 'account');
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
					}
				} else {
				$message = formatText($aseco->getChatMessage('UNITED_ONLY'), 'server');
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				}
			} else {
			$message = $aseco->getChatMessage('FOREVER_ONLY');
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			}
		} else {
			$message = '>$f00 This track will end before your action can be completed, please be patient.';
			$aseco->client->query('ChatSendServerMessageToLogin', $message, $login);
		}
	}
}

function p2p_bill($aseco, $bill) {  //Deal with bill payments
	global $p2p, $jukebox, $atl_restart;
	$id = $bill[0];
	// check for known bill ID
	if (array_key_exists($id, $p2p['bills'])) {
		// get bill info
		$login = $p2p['bills'][$id][0];
		$nickname = $p2p['bills'][$id][1];
		$state = $p2p['bills'][$id][2];
		$coppers = $p2p[$state]['cost'];
		
		if ($state == 'replay') {
				// check bill state
			switch($bill[1]) {
			case 4:  // Payed (Paid)
				if (!$p2p['score']) {
					$uid = $aseco->server->challenge->uid;
					$jukebox = array_reverse($jukebox, true);
					$jukebox[$uid]['FileName'] = $aseco->server->challenge->filename;
					$jukebox[$uid]['Name'] = $aseco->server->challenge->name;
					$jukebox[$uid]['Env'] = $aseco->server->challenge->environment;
					$jukebox[$uid]['Login'] = $login;
					$jukebox[$uid]['Nick'] = $nickname;
					$jukebox[$uid]['source'] = 'Pay2Play';
					$jukebox[$uid]['tmx'] = false;
					$jukebox[$uid]['uid'] = $uid;
					$jukebox = array_reverse($jukebox, true);
					$aseco->releaseEvent('onJukeboxChanged', array('replay', $jukebox[$uid]));
				} else {
					if (isset($atl_restart)) $atl_restart = true;
					$aseco->client->query('ChallengeRestart');
				}				
				$message = '$s$f90Player $z$s'.$nickname.'$z$s$f90  pays '.$coppers.' coppers and queues track for replay!';
				$aseco->client->query('ChatSendServerMessage', $message);
				$aseco->console('Player {1} paid {2} coppers to replay the current track', $login, $coppers);
				unset($p2p['bills'][$id]);
				$p2p['replay']['success'] = true;
				$p2p['replay']['total']++;
				if (!$p2p['score']) {
					p2p_buildReplay($aseco, 'success');
				}
				break;
			case 5:  // Refused
				$message = '{#server}> {#error}Transaction refused!';
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				unset($p2p['bills'][$id]);
				break;
			case 6:  // Error
				$message = '{#server}> {#error}Transaction failed: {#highlite}$i ' . $bill[2];
				if ($login != '')
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				else
					$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
				unset($p2p['bills'][$id]);
				break;
			default:  // CreatingTransaction/Issued/ValidatingPay(e)ment
				break;
			}
		} elseif ($state == 'skip') {
				// check bill state
			switch($bill[1]) {
			case 4:  // Payed (Paid)
				$time = time() + $p2p['skip']['delay'];
				$p2p['time']['skip'] = $time;
				$message = '$s$f90Player $z$s'.$nickname.'$z$s$f90  pays '.$coppers.' coppers. Track will be skipped shortly!';
				$aseco->client->query('ChatSendServerMessage', $message);
				$aseco->console('Player {1} paid {2} coppers to skip the current track', $login, $coppers);
				unset($p2p['bills'][$id]);
				$p2p['skip']['success'] = true;
				p2p_buildSkip($aseco, 'success');
				break;
			case 5:  // Refused
				$message = '{#server}> {#error}Transaction refused!';
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				unset($p2p['bills'][$id]);
				break;
			case 6:  // Error
				$message = '{#server}> {#error}Transaction failed: {#highlite}$i ' . $bill[2];
				if ($login != '')
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				else
					$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
				unset($p2p['bills'][$id]);
				break;
			default:  // CreatingTransaction/Issued/ValidatingPay(e)ment
				break;
			}
		}		
	}
}

function p2p_time($aseco) { //Deal with time functions
	global $p2p;
	
	if (isset($p2p['time']['skip'])) {
		$time = time();
		
		if ($time >= $p2p['time']['skip']) {
			// load the next track
			// don't clear scores if in Cup mode
			if ($aseco->server->gameinfo->mode == Gameinfo::CUP)
				$aseco->client->query('NextChallenge', true);
			else
				$aseco->client->query('NextChallenge');
			unset($p2p['time']['skip']);
		}
	}
}

function p2p_apocalypse($aseco) { //Restore altered callvotes
	global $p2p;
	if (!isset($p2p['oldvotes']))  // not started yet (XAseco stopped during start-up)
		return;
	
	$aseco->client->query('SetCallVoteRatios', $p2p['oldvotes']);
}
		
?>