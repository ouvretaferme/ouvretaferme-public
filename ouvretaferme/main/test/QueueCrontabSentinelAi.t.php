<?php
namespace main;

/**
 * Chaque action de la file a-t-elle son runner dans la crontab, et chaque runner son consommateur ?
 *
 * Un consommateur déclaré ne suffit pas : `queue/cron/run` ne sert que les actions que sa ligne nomme
 * dans `action=`, et `dev/crontab` régénère la ligne sans ce paramètre, qui se pose donc à la main.
 * Une action oubliée dépose des tâches que personne ne prend, et rien ne fait vieillir une tâche en
 * attente : elle reste là sans une erreur nulle part. Vu à la relecture du lot 3b des notifications,
 * dix types ajoutés et la ligne restée sur le seul `shop-sale`.
 *
 * Dans l'autre sens, une action nommée sans consommateur fait lever `queue/cron/run` avant qu'il ne
 * serve quoi que ce soit : toute la ligne s'arrête, les actions voisines comprises.
 *
 * Remplace le contrôle que portait `NotificationTypeSentinel`, borné au préfixe `notification.` :
 * la file sert aussi d'autres familles depuis le lot 7 des notifications.
 *
 * Lancement : dev/test package=main file=QueueCrontabSentinelAi
 */
class QueueCrontabSentinelAiTest extends \Test {

	const ENVIRONMENTS = ['prod', 'dev'];

	// La borne de cronie (MAX_COMMAND), prise sur la ligne entière pour garder de la marge
	const LINE_MAX = 1000;

	/**
	 * Les actions de `framework/queue/test/Queue.t.php`, que son `init()` déclare à la file sans
	 * possibilité de les retirer : une passe `dev/test package='*'` fait tourner tous les tests dans un
	 * seul processus, et la sentinelle les verrait sans ligne de crontab dès qu'elle passe après lui.
	 */
	const TEST_PREFIX = 'queue.test.';

	public function testEveryActionHasItsCrontabRunner(): void {

		$consumed = array_values(array_filter(
			\queue\QueueLib::getActions(),
			fn(string $action) => str_starts_with($action, self::TEST_PREFIX) === FALSE
		));

		$this->assertTrue($consumed !== [], 'la file déclare au moins une action, sinon le contrôle ne lit rien');

		foreach(self::ENVIRONMENTS as $environment) {

			$run = $this->getRun($environment);

			$missing = array_values(array_diff($consumed, $run));
			$orphan = array_values(array_diff($run, $consumed));

			$this->assertEquals([], $missing, 'crontab '.$environment.' : actions de la file qu\'aucune ligne queue/cron/run ne sert : '.implode(', ', $missing));
			$this->assertEquals([], $orphan, 'crontab '.$environment.' : actions servies par une ligne queue/cron/run sans consommateur : '.implode(', ', $orphan));

			$twice = array_values(array_unique(array_diff_assoc($run, array_unique($run))));

			$this->assertEquals([], $twice, 'crontab '.$environment.' : actions servies par deux lignes queue/cron/run, en retirer une : '.implode(', ', $twice));

		}

	}

	/**
	 * Le cron du serveur refuse une ligne trop longue à l'installation, et la ligne des notifications
	 * grossit d'une action à chaque type ajouté : refusée le 04/10/2026 à 1 055 caractères. Une ligne
	 * qui dépasse se coupe en deux lignes `queue/cron/run`, les actions réparties entre elles.
	 */
	public function testEveryLineFitsTheServerCron(): void {

		foreach(self::ENVIRONMENTS as $environment) {

			$file = \Package::getPath('main').'/conf/crontab/'.$environment;

			$long = [];

			foreach(file($file, FILE_IGNORE_NEW_LINES) as $number => $line) {
				if(strlen($line) > self::LINE_MAX) {
					$long[] = 'ligne '.($number + 1).' ('.strlen($line).' caractères)';
				}
			}

			$this->assertEquals([], $long, 'crontab '.$environment.' : lignes de plus de '.self::LINE_MAX.' caractères, que le serveur refuse ; couper la ligne en deux : '.implode(', ', $long));

		}

	}

	/**
	 * Les actions nommées par les lignes `queue/cron/run` d'une crontab, toutes lignes confondues.
	 */
	protected function getRun(string $environment): array {

		$file = \Package::getPath('main').'/conf/crontab/'.$environment;

		preg_match_all('/queue\/cron\/run action=(\S+)/', file_get_contents($file), $matches);

		$run = [];

		foreach($matches[1] as $list) {
			$run = array_merge($run, explode(',', $list));
		}

		return $run;

	}

}
?>
