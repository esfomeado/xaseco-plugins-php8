<?php
Aseco::registerEvent('onSync', 'discord_onSync');
Aseco::registerEvent('onPlayerConnect', 'discord_onPlayerConnect');
Aseco::registerEvent('onEverySecond', 'discord_onEverySecond');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'discord_onAnswer');

global $discord_widget_xml;
$discord_widget_xml = null;

function discord_build() {
	global $discord_widget_xml;
	$action = 99001;
	$discord_widget_xml = '<?xml version="1.0" encoding="UTF-8"?><manialinks>';
	$discord_widget_xml .= '<manialink id="DISCORD00">';
	$discord_widget_xml .= '<frame posn="60 65 0">';
	$discord_widget_xml .= '<quad posn="0 0 0.001" sizen="10 10" bgcolor="111F" action="'. $action .'"/>';
	$discord_widget_xml .= '<quad posn="0 -0.5 0.002" sizen="8.4 8.4" bgcolor="66A9"/>';
	$discord_widget_xml .= '<label posn="0 -0.6 0.003" sizen="6 2" halign="center" textsize="3" text="D"/>';
	$discord_widget_xml .= '<label posn="0 -5.4 0.003" sizen="14 2" halign="center" textsize="2" text="Discord"/>';
	$discord_widget_xml .= '</frame>';
	$discord_widget_xml .= '</manialink>';
	$discord_widget_xml .= '</manialinks>';
}

function discord_show($aseco, $player = null) {
	global $discord_widget_xml;
	if (!$discord_widget_xml) discord_build();
	if ($player) {
		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $discord_widget_xml, 0, false);
	} else {
		$aseco->client->query('SendDisplayManialinkPage', $discord_widget_xml, 0, false);
	}
}

function discord_onSync($aseco) { discord_show($aseco); }
function discord_onPlayerConnect($aseco, $player) { discord_show($aseco, $player); }
function discord_onEverySecond($aseco) { discord_show($aseco); }

function discord_onAnswer($aseco, $answer) {
	$login = $answer[1];
	$action = (int)$answer[2];
	if ($action === 99001) {
		$discord_link = 'https://discord.gg/YOUR_INVITE_CODE';
		$aseco->client->query('ChatSendToLogin', '$fff[Discord] $zClick this link to join: $l[' . $discord_link . ']Join Discord$l', $login);
	}
}
?>
