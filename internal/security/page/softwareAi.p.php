<?php
/**
 * La veille technique en ligne de commande.
 *
 *   php framework/lime.php -a ouvretaferme -e dev security/softwareAi
 *     chaque constat, du plus grave au moins grave, avec ses failles une à une ;
 *   php framework/lime.php -a ouvretaferme -e dev security/softwareAi:witness
 *     éprouve chaque source sur une faille, une version ou une fin de support connues : une veille
 *     muette ne prouve rien tant qu'elle n'a pas montré qu'elle sait trouver.
 *
 * Lecture seule, aucune base touchée.
 */
new Page()
	->cli('index', function($data) {

		['findings' => $findings, 'unread' => $unread, 'accepted' => $accepted] = \security\SoftwareWatchAiLib::check();

		echo count($findings).' constat(s), '.$accepted.' élément(s) écarté(s) au registre.'."\n";

		foreach($unread as $name => $reason) {
			echo $name.' non lu, '.$reason."\n";
		}

		foreach($findings as $finding) {

			echo "\n".'['.\security\SoftwareWatchAiLib::getLevelLabel($finding['level']).'] '.$finding['name'].' · '.$finding['text'].($finding['date'] !== NULL ? ' · depuis le '.$finding['date'] : '')."\n";

			if($finding['url'] !== '') {
				echo '  '.$finding['url']."\n";
			}

			foreach($finding['vulnerabilities'] as $vulnerability) {
				echo '  '.$vulnerability['id'].' · '.\security\SoftwareWatchAiLib::getLevelLabel($vulnerability['level']).' · publiée le '.$vulnerability['published'].($vulnerability['fixed'] !== NULL ? ' · corrigée en '.$vulnerability['fixed'] : ' · sans correctif').' · '.mb_substr($vulnerability['summary'], 0, 100)."\n";
			}

		}

	})
	->cli('witness', function($data) {

		$checks = [
			'OSV, paquet Debian (ghostscript 10.0.0~dfsg-11)' => fn() => count(\security\OsvAiLib::query([['ecosystem' => 'Debian:12', 'package' => 'ghostscript', 'version' => '10.0.0~dfsg-11']])[0]),
			'OSV, paquet Ubuntu (ghostscript 10.02.1~dfsg1-0ubuntu7)' => fn() => count(\security\OsvAiLib::query([['ecosystem' => 'Ubuntu:24.04:LTS', 'package' => 'ghostscript', 'version' => '10.02.1~dfsg1-0ubuntu7']])[0]),
			'OSV, Packagist (guzzlehttp/guzzle 7.4.0)' => fn() => count(\security\OsvAiLib::query([['ecosystem' => 'Packagist', 'package' => 'guzzlehttp/guzzle', 'version' => '7.4.0']])[0]),
			'OSV, npm (lodash 4.17.20)' => fn() => count(\security\OsvAiLib::query([['ecosystem' => 'npm', 'package' => 'lodash', 'version' => '4.17.20']])[0]),
			'php.net, versions de sécurité après 8.4.0' => fn() => count(\security\ReleaseAiLib::getSecurityReleases('php', '8.4.0')),
			'nodejs.org, versions de sécurité après 22.0.0' => fn() => count(\security\ReleaseAiLib::getSecurityReleases('node', '22.0.0')),
			'endoflife.date, PHP 7.4 sans support' => fn() => (int)(\security\ReleaseAiLib::findCycle(\security\ReleaseAiLib::getCycles('php'), '7.4.33')['eolFrom'] < \Clock::date()),
			'endoflife.date, branche de Chrome connue' => fn() => count(\security\ReleaseAiLib::getCycles('chrome')),
		];

		$failed = 0;

		foreach($checks as $label => $check) {

			try {
				$count = $check();
			} catch(\Throwable $e) {
				$count = 0;
				$label .= ' ('.$e->getMessage().')';
			}

			if($count === 0) {
				$failed++;
			}

			echo ($count > 0 ? 'trouve ' : 'ÉCHEC  ').$label.' · '.$count."\n";

		}

		echo "\n".($failed === 0 ? 'Chaque source sait trouver.' : $failed.' source(s) muette(s) sur un cas connu : leur silence dans la veille ne prouve rien.')."\n";

	});
?>
