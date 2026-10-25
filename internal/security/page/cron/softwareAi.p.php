<?php
/**
 * La veille technique (`security\SoftwareWatchAiLib`), chaque matin sur le salon de sécurité.
 *
 * Du mardi au dimanche, le message ne part que pour un constat critique ou élevé apparu depuis la
 * veille. Le lundi, il reprend tout ce qui reste ouvert, classé par niveau, et il part même quand
 * rien n'est ouvert : un lundi sans message dit que la veille ne tourne plus. Le détail, faille par
 * faille, se lit par `php framework/lime.php -a ouvretaferme -e prod security/softwareAi`.
 */
new Page()
	->cron('index', function($data) {

		$weekly = (date('N', \Clock::timestamp()) === '1');

		['findings' => $findings, 'unread' => $unread, 'accepted' => $accepted] = \security\SoftwareWatchAiLib::check();

		if($weekly === FALSE) {

			$findings = \security\SoftwareWatchAiLib::getNew($findings, date('Y-m-d', strtotime(\Clock::date().' - 1 day')));

			if($findings === []) {
				return;
			}

		}

		$embeds = \security\SoftwareWatchAiLib::getEmbeds($findings, $unread, $accepted, $weekly);

		if($embeds === []) {
			$embeds[] = ['title' => 'Rien à signaler', 'color' => 3066993, 'description' => 'Aucune faille corrigée en attente, aucune fin de support proche, aucun retrait annoncé.'];
		}

		\communication\DiscordWebhookLib::send(
			$weekly ? '🛡️ **Veille technique** · le point du lundi' : '🛡️ **Veille technique** · nouveau depuis hier',
			$embeds,
			\communication\DiscordWebhookLib::SECURITY
		);

	}, interval: '0 9 * * *');
?>
