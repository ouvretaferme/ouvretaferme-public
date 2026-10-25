<?php
/**
 * Veille de nuit sur les sources du catalogue des aides (aid\WatchLib). Ne dit rien quand rien n'a
 * bougé ; sinon, compte sur le salon Discord de la veille des aides ce que la nuit a relevé sur les
 * sources officielles et les relais, dont seules les officielles entrent dans la file « À revoir », avec
 * le lien vers elle. Le message ne cite aucune page lue : seulement des nombres.
 *
 * php framework/lime.php -a ouvretaferme -e dev aid/cron/watch
 */
new Page()
	->cron('index', function($data) {

		$report = \aid\WatchLib::run();

		$lines = [];

		if($report['broken']) {
			$lines[] = 'Plus de la moitié des '.$report['urls'].' pages ne se sont pas laissé lire : la veille est sans doute en panne, aucun échec n\'est compté cette nuit.';
		}

		foreach([
			'dead' => 'source(s) ne répondent plus depuis '.\aid\WatchLib::FAILURES_DEAD.' nuits',
			'alive' => 'source(s) répondent de nouveau',
			'moved' => 'source(s) ont changé d\'adresse',
			'unreadable' => 'source(s) ne se laissent plus lire depuis '.\aid\AidSetting::WATCH_UNREAD_DAYS.' nuits',
			'changed' => 'page(s) ont changé, comptées sans entrer dans la file',
		] as $key => $label) {

			if($report[$key] > 0) {
				$lines[] = '• '.$report[$key].' '.$label;
			}

		}

		if($lines === []) {
			return;
		}

		\communication\DiscordWebhookLib::send(
			'🔎 **Veille des aides**',
			[[
				'title' => $report['urls'].' page(s) lues cette nuit',
				'color' => 15844367, // ambre
				'description' => implode("\n", $lines)."\n\n".'La file « À revoir » se lit sur '.\Lime::getUrl().'/aid/admin/:review',
				'timestamp' => date('c', \Clock::timestamp()),
			]],
			\communication\DiscordWebhookLib::AID
		);

	}, interval: '30 1 * * *');
?>
