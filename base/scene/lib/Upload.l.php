<?php
namespace scene;

/**
 * La conversion d'un modèle 3D téléversé par `upload/upload.mjs`, sous les règles d'un profil
 * (`upload/<profil>.json`) : `avatar` pour l'avatar d'un utilisateur, `decor` pour un objet du décor des scènes, dont
 * les règles se lisent par famille (`families`, `building` pour un bâtiment).
 *
 * Le fichier vient d'un inconnu, et une faille de node déclenchée par lui doit trouver le moins possible : node
 * tourne sans environnement, sans les connexions que PHP a déjà ouvertes, et sous un plafond de mémoire, de durée
 * et de sortie. Sa sortie est relue ici, forme comprise, et tout ce qui n'est pas un refus connu ou une conversion
 * complète devient le refus `format`. Sa sortie d'erreur ne se lit pas : elle ne s'afficherait jamais, et un tube
 * plein bloquerait la lecture de l'autre.
 */
class UploadLib {

	/**
	 * La mémoire virtuelle de node, en Kio. Ce n'est pas la mémoire qu'il occupe : V8 réserve au démarrage un espace
	 * d'adressage qu'il ne remplit pas, et node meurt avant la première ligne du script quand le plafond ne le contient pas,
	 * ce que le serveur lit comme un fichier illisible. La réserve change d'une version à l'autre : node 18 démarre sous
	 * 360 Mo, node 20 et 22 sous 760 Mo. Le plafond en laisse au moins le double à la version installée
	 * (`UploadAiTest::testMemoryLeavesMargin()`). Le tas de JavaScript se borne à part, et node qui meurt contre lui
	 * n'écrit pas de fichier core (`ulimit -c 0`).
	 */
	const MEMORY = 2097152;
	const HEAP = 128;
	const TIMEOUT = 15;

	private static array $rules = [];

	private static string $node = '';

	public static function getRules(string $profile): array {

		if(array_key_exists($profile, self::$rules) === FALSE) {
			self::$rules[$profile] = json_decode(file_get_contents(self::getRulesFile($profile)), TRUE, flags: JSON_THROW_ON_ERROR);
		}

		return self::$rules[$profile];

	}

	/**
	 * Les règles d'un modèle, comme `sceneUploadRules()` (`upload/upload.mjs`) les lit : celles du profil, et pour un
	 * profil à familles le tronc commun complété du bloc de `$family`. Une famille inconnue, ou donnée à un profil qui
	 * n'en a pas, est une faute de l'appelant.
	 */
	public static function getModelRules(string $profile, ?string $family = NULL): array {

		$rules = self::getRules($profile);

		if(array_key_exists('families', $rules) === FALSE) {

			if($family !== NULL) {
				throw new \Exception('Profile '.$profile.' has no family');
			}

			return $rules;

		}

		if($family === NULL or array_key_exists($family, $rules['families']) === FALSE) {
			throw new \Exception('Unknown family '.$family.' for profile '.$profile);
		}

		$families = $rules['families'];
		unset($rules['families']);

		return $families[$family] + $rules + ['family' => $family];

	}

	/**
	 * Rend `['refusal' => code, 'values' => [...]]` pour un modèle refusé, et pour un modèle converti
	 * `['rules' => version, 'triangles' => n, 'index' => [...], 'buffer' => binaire]`, l'index et le
	 * binaire au format que lit `sceneBuildModel()` (`static/js/model.js`). Un profil à familles demande la sienne.
	 */
	public static function run(string $profile, string $file, ?string $family = NULL): array {

		$rules = self::getModelRules($profile, $family);

		// Le binaire pèse 27 octets par triangle et grossit d'un tiers en base64 ; le double laisse la place à l'index
		$outputLimit = 2 * $rules['maxTriangles'] * 36;

		$arguments = ['--bake', '--rules='.self::getRulesFile($profile), '--in='.$file];

		if($family !== NULL) {
			$arguments[] = '--family='.$family;
		}

		$result = self::runNode(LIME_DIRECTORY.'/base/scene/upload/upload.mjs', $arguments, $outputLimit);

		$unreadable = ['refusal' => 'format', 'values' => []];

		if($result['code'] !== 0) {
			return $unreadable;
		}

		$output = json_decode($result['output'], TRUE);

		if(is_array($output) === FALSE) {
			return $unreadable;
		}

		// L'index nomme le modèle d'après sa famille, l'avatar d'après son profil
		$model = $family ?? $profile;

		return self::readRefusal($rules, $output) ?? self::readBaked($rules, $model, $output) ?? $unreadable;

	}

	/**
	 * Le binaire d'un modèle déjà converti, ses normales refaites par la conversion d'aujourd'hui (`sceneUploadSmooth()`,
	 * `upload/upload.mjs --smooth`), pour un modèle cuit avant elle. Positions et index ne changent pas, et un binaire déjà
	 * à jour revient identique. Un échec est un défaut du serveur, jamais un refus fait à l'utilisateur : il lève.
	 */
	public static function smooth(string $profile, string $buffer, array $index): string {

		$bufferFile = tempnam(sys_get_temp_dir(), 'smooth');
		$indexFile = tempnam(sys_get_temp_dir(), 'smooth');

		try {

			file_put_contents($bufferFile, $buffer);
			file_put_contents($indexFile, json_encode($index, JSON_THROW_ON_ERROR));

			$result = self::runNode(
				LIME_DIRECTORY.'/base/scene/upload/upload.mjs',
				['--smooth', '--rules='.self::getRulesFile($profile), '--in='.$bufferFile, '--index='.$indexFile],
				// Le binaire revient tel qu'entré, grossi d'un tiers par le base64
				2 * strlen($buffer)
			);

		} finally {
			unlink($bufferFile);
			unlink($indexFile);
		}

		$output = ($result['code'] === 0) ? json_decode($result['output'], TRUE) : NULL;
		$smoothed = is_string($output['buffer'] ?? NULL) ? base64_decode($output['buffer'], TRUE) : FALSE;

		// Les positions sont les `vertices × 6` premiers octets : une sortie qui les change n'est pas une reprise des normales
		if(
			$smoothed === FALSE or
			strlen($smoothed) !== strlen($buffer) or
			substr($smoothed, 0, $index['vertices'] * 6) !== substr($buffer, 0, $index['vertices'] * 6)
		) {
			throw new \Exception('Normals of the baked model not rebuilt by upload.mjs --smooth');
		}

		return $smoothed;

	}

	/**
	 * Lance `$script` sous node et rend `['code' => int, 'output' => string]`. La sortie standard est lue jusqu'à
	 * `$outputLimit` octets : au-delà, ou passé l'échéance de la lecture, le processus est tué et `code` vaut -1.
	 */
	private static function runNode(string $script, array $arguments, int $outputLimit): array {

		// `env -i` retire PATH : node est désigné par son chemin réel
		$command = 'env -i '.escapeshellarg(self::getNode()).' --max-old-space-size='.self::HEAP.' '.escapeshellarg($script);

		foreach($arguments as $argument) {
			$command .= ' '.escapeshellarg($argument);
		}

		// Le processus hériterait des descripteurs de PHP, dont les connexions déjà authentifiées à la base et à Redis : on
		// les ferme tous, hormis les trois standard
		$descriptors = scandir('/proc/self/fd');

		if($descriptors === FALSE) {
			throw new \Exception('Descriptors of the PHP process not readable in /proc/self/fd, node not started for the uploaded model');
		}

		foreach($descriptors as $descriptor) {
			if(ctype_digit($descriptor) and (int)$descriptor > 2) {
				$command .= ' '.$descriptor.'>&-';
			}
		}

		$pipes = [];

		// Les bornes `ulimit` demandent un shell : bash, que la veille suit déjà (SoftwareAiLib::BINARIES). `-k 2` tue node deux secondes
		// après le délai s'il a ignoré SIGTERM, ce que peut faire du code exécuté dans node, et qui bloquerait sinon le worker sans limite
		$process = proc_open(
			['bash', '-c', 'ulimit -c 0; ulimit -v '.self::MEMORY.'; exec timeout -k 2 '.self::TIMEOUT.' '.$command],
			[
				0 => ['file', '/dev/null', 'r'],
				1 => ['pipe', 'w'],
				2 => ['file', '/dev/null', 'w'],
			],
			$pipes
		);

		if(is_resource($process) === FALSE) {
			throw new \Exception('Node not started for the uploaded model, proc_open failed');
		}

		// Le tube ne se lit pas jusqu'à sa fin : un processus que node détacherait en le gardant ouvert la retarderait sans
		// limite. La lecture s'arrête quand timeout sort, ou à son échéance augmentée d'une marge
		$deadline = hrtime(TRUE) + (self::TIMEOUT + 2 + 1) * 1000000000;
		$output = '';
		$code = -1;
		$finished = FALSE;

		stream_set_blocking($pipes[1], FALSE);

		while(strlen($output) <= $outputLimit and hrtime(TRUE) < $deadline) {

			$read = [$pipes[1]];
			$write = NULL;
			$except = NULL;

			if(stream_select($read, $write, $except, 0, 100000) > 0) {
				$output .= fread($pipes[1], $outputLimit + 1);
			}

			// proc_get_status() ne rend le code de sortie qu'une fois, proc_close() ne le connaitra plus
			$status = proc_get_status($process);

			if($status['running'] === FALSE) {
				$output .= stream_get_contents($pipes[1], $outputLimit + 1);
				$code = $status['exitcode'];
				$finished = TRUE;
				break;
			}

		}

		$accepted = ($finished and strlen($output) <= $outputLimit);

		// Sortie trop longue ou échéance : timeout transmet le signal à node avant de sortir
		if($finished === FALSE) {
			proc_terminate($process);
		}

		fclose($pipes[1]);
		proc_close($process);

		return [
			'code' => $accepted ? $code : -1,
			'output' => $accepted ? $output : '',
		];

	}

	/**
	 * Le chemin réel de node, le même pour tout le parc. Un serveur sans node ne sait convertir aucun modèle : c'est
	 * un défaut du serveur, pas du fichier, qui ne doit pas se présenter à l'utilisateur comme un refus.
	 */
	private static function getNode(): string {

		if(self::$node === '') {

			$output = [];
			$code = 0;
			exec('command -v node', $output, $code);

			$node = ($code === 0) ? realpath($output[0]) : FALSE;

			if($node === FALSE) {
				throw new \Exception('Node not found in PATH, the uploaded models cannot be converted');
			}

			self::$node = $node;

		}

		return self::$node;

	}

	private static function getRulesFile(string $profile): string {

		if(preg_match('/^[a-z]+$/', $profile) !== 1) {
			throw new \Exception('Invalid profile '.$profile);
		}

		return LIME_DIRECTORY.'/base/scene/upload/'.$profile.'.json';

	}

	// Un refus porte un code des règles et des valeurs scalaires, jamais rien d'autre
	private static function readRefusal(array $rules, array $result): ?array {

		if(array_keys($result) !== ['refusal', 'values']) {
			return NULL;
		}

		$values = $result['values'];

		if(
			in_array($result['refusal'], $rules['refusals'], TRUE) === FALSE or
			is_array($values) === FALSE or
			array_filter($values, fn($value, $key) => is_string($key) and (is_string($value) or is_int($value)), ARRAY_FILTER_USE_BOTH) !== $values
		) {
			return NULL;
		}

		return ['refusal' => $result['refusal'], 'values' => $values];

	}

	// Une conversion se garde si le binaire a la taille que l'index annonce et si l'index ne nomme que le modèle attendu
	private static function readBaked(array $rules, string $model, array $result): ?array {

		if(array_keys($result) !== ['rules', 'triangles', 'index', 'buffer']) {
			return NULL;
		}

		$buffer = is_string($result['buffer']) ? base64_decode($result['buffer'], TRUE) : FALSE;
		$index = $result['index'];

		if(
			$result['rules'] !== $rules['version'] or
			is_int($result['triangles']) === FALSE or $result['triangles'] < 1 or $result['triangles'] > $rules['maxTriangles'] or
			$buffer === FALSE or
			is_array($index) === FALSE or
			($index['vertices'] ?? NULL) !== $result['triangles'] * 3 or
			($index['colors'] ?? NULL) !== 0 or
			is_array($index['models'][$model] ?? NULL) === FALSE or
			array_keys($index['models']) !== [$model] or
			strlen($buffer) !== $index['vertices'] * 9
		) {
			return NULL;
		}

		return [
			'rules' => $result['rules'],
			'triangles' => $result['triangles'],
			'index' => $index,
			'buffer' => $buffer,
		];

	}

}
?>
