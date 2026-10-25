<?php
namespace dev;

/**
 * Partage de la base de développement entre les passes de tests et les changements de schéma.
 *
 * Les passes de tests ne s'attendent jamais entre elles : elles lisent et écrivent dans des
 * décors qu'elles posent elles-mêmes. Le seul croisement qui fait un rouge sans cause est un
 * changement de schéma joué pendant une passe. Chaque processus pose donc un jeton, `test` ou
 * `schema`, et seul un jeton de l'autre sorte se fait attendre.
 *
 * Un changement de schéma qui attend pose déjà son jeton, marqué `waiting` : une passe neuve attend derrière
 * lui, sans quoi des passes qui se relaient sans trou le retiendraient au-delà de son délai.
 *
 * Un jeton se lève à la fin du processus. Celui d'un processus tué ne se lève pas : il est ignoré
 * dès que son pid n'existe plus, ce qui dispense de toute péremption.
 *
 * Chaque attente effective s'inscrit au journal du jour : c'est la seule trace qui dise, après coup, si
 * TIMEOUT reste au-dessus de ce que dure une passe.
 */
class TestLockLib {

	const TEST = 'test';
	const SCHEMA = 'schema';

	const TIMEOUT = 900;

	const MUTEX = 'lock';

	public static function getDirectory(): string {
		return sys_get_temp_dir().'/otf-dev-test';
	}

	private static function prepareDirectory(): string {

		$directory = self::getDirectory();

		if(is_dir($directory) === FALSE) {
			@mkdir($directory, 0777, TRUE);
		}

		return $directory;

	}

	private static function write(string $file, string $kind, string $label, bool $waiting): void {

		file_put_contents($file, json_encode([
			'pid' => getmypid(),
			'kind' => $kind,
			'label' => $label,
			'waiting' => $waiting,
			'start' => \Clock::datetime(),
			'cwd' => getcwd()
		]));

	}

	/**
	 * `$waiting` à FALSE écarte les jetons des changements de schéma qui attendent encore.
	 *
	 * @return array<int, array>
	 */
	public static function getLive(?string $kind = NULL, bool $waiting = TRUE): array {

		$live = [];

		foreach(glob(self::getDirectory().'/*.json') ?: [] as $file) {

			$token = json_decode((string)@file_get_contents($file), TRUE);

			if(is_array($token) === FALSE) {
				continue;
			}

			if($token['pid'] === getmypid()) {
				continue;
			}

			if(posix_kill($token['pid'], 0) === FALSE) {
				@unlink($file);
				continue;
			}

			if($waiting === FALSE and self::isWaiting($token)) {
				continue;
			}

			if($kind === NULL or $token['kind'] === $kind) {
				$live[] = $token;
			}

		}

		return $live;

	}

	/**
	 * Le répertoire est commun à toutes les copies du dépôt sur le poste, dont certaines tournent un code
	 * qui ne marque pas l'attente : un jeton sans la clé est celui d'un processus qui n'attend pas.
	 */
	private static function isWaiting(array $token): bool {
		return ($token['waiting'] ?? FALSE) === TRUE;
	}

	/**
	 * Attend qu'aucun processus ne tienne de jeton de l'autre sorte, puis pose celui de `$kind`.
	 *
	 * La dernière lecture et la pose se font sous un même verrou de fichier : sans lui, une passe et un
	 * changement de schéma lancés ensemble passent chacun leur attente avant que l'autre ait posé son
	 * jeton. Le verrou se relâche entre deux sondages.
	 *
	 * Un changement de schéma pose son jeton en attente avant d'attendre, et les passes neuves s'arrêtent
	 * devant lui. Les processus d'une passe jobs=N déjà lancée passent outre : le schéma attend la fin de
	 * leur parent, qui attend la leur.
	 */
	public static function take(string $kind, string $label, int $timeout = self::TIMEOUT): void {

		$other = match($kind) {
			self::TEST => self::SCHEMA,
			self::SCHEMA => self::TEST
		};

		$start = \Clock::timestamp();
		$mutex = fopen(self::prepareDirectory().'/'.self::MUTEX, 'c');

		$file = self::getDirectory().'/'.getmypid().'.json';
		register_shutdown_function(fn() => @unlink($file));

		if($kind === self::SCHEMA) {
			self::write($file, $kind, $label, TRUE);
		}

		$waiting = (self::isInRunningPass() === FALSE);

		while(TRUE) {

			self::wait($other, max(0, $timeout - (\Clock::timestamp() - $start)), $waiting);

			flock($mutex, LOCK_EX);

			if(self::getLive($other, $waiting) === []) {
				self::write($file, $kind, $label, FALSE);
				flock($mutex, LOCK_UN);
				break;
			}

			flock($mutex, LOCK_UN);

		}

		fclose($mutex);

	}

	private static function isInRunningPass(): bool {

		$file = self::getDirectory().'/'.posix_getppid().'.json';

		if(is_file($file) === FALSE) {
			return FALSE;
		}

		$token = json_decode(file_get_contents($file), TRUE);

		return (is_array($token) and $token['kind'] === self::TEST);

	}

	public static function getJournal(string $date): string {
		return self::getDirectory().'/waits-'.$date.'.log';
	}

	public static function describe(array $token): string {
		return $token['kind'].(self::isWaiting($token) ? ' en attente' : '').' '.$token['label'].', pid '.$token['pid'].', depuis '.$token['start'];
	}

	/**
	 * Attend qu'aucun processus ne tienne de jeton `$kind`, en le disant une fois par minute.
	 *
	 * Le verrou se tient entre processus de ligne de commande, et son attente précède tout travail : au-delà
	 * du délai, le processus sort en code 2, comme un refus de dev/test, et non en 0 qui ferait passer une
	 * passe ou une migration de schéma jamais jouée pour réussie.
	 */
	public static function wait(string $kind, int $timeout = self::TIMEOUT, bool $waiting = TRUE): void {

		$start = \Clock::timestamp();
		$said = NULL;
		$waited = [];

		while(($live = self::getLive($kind, $waiting)) !== []) {

			$waited = $live;

			if(\Clock::timestamp() - $start > $timeout) {
				self::log($kind, $start, $waited, TRUE);
				echo 'Error: still waiting after '.$timeout.' s for '.implode(' ; ', array_map(self::describe(...), $live))."\n";
				exit(2);
			}

			if($said === NULL or \Clock::timestamp() - $said >= 60) {
				fwrite(STDERR, 'En attente de : '.implode(' ; ', array_map(self::describe(...), $live))."\n");
				$said = \Clock::timestamp();
			}

			sleep(2);

		}

		if($waited !== []) {
			self::log($kind, $start, $waited, FALSE);
		}

	}

	/**
	 * Une ligne JSON par attente, `seconds` mesurant l'attente entière et `for` ce qui la retenait encore
	 * en dernier. Le verrou exclusif garde les lignes entières quand deux processus finissent ensemble.
	 */
	private static function log(string $kind, int $start, array $live, bool $timeout): void {

		file_put_contents(self::getJournal(\Clock::date()), json_encode([
			'end' => \Clock::datetime(),
			'pid' => getmypid(),
			'kind' => $kind,
			'seconds' => \Clock::timestamp() - $start,
			'for' => array_map(self::describe(...), $live),
			'timeout' => $timeout
		])."\n", FILE_APPEND | LOCK_EX);

	}

	/**
	 * Joue un script shell sous le jeton `schema` que l'appelant a posé, et rend son code de sortie.
	 * Le script est un argument de bash, jamais recomposé en ligne de commande : un bloc `## Jouer` de
	 * `migration/` s'y passe tel qu'il est écrit, guillemets compris. L'entrée des commandes est vide,
	 * sans quoi la première qui lit la sienne avalerait la suite du script, et bash sortirait en 0 sans
	 * l'avoir jouée.
	 */
	public static function play(string $script): int {

		// Le tableau s'écrit en littéral pour que ShellCommandSentinel y lise la forme sans shell.
		$process = proc_open(['bash', '-e', '-c', $script], [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, getcwd());

		return proc_close($process);

	}

}
?>
