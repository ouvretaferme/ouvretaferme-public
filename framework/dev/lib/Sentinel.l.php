<?php
namespace dev;

/**
 * Outils communs aux tests-sentinelles (framework/dev/test/CLAUDE.md).
 *
 * Une sentinelle lit le dépôt entier, pas seulement les packages : `Lime.s.php` vit à la racine
 * d'une app, les `.yml` et les feuilles de style à côté du PHP. Lire tout le PHP par l'analyseur
 * lexical coûte 0,35 s (2 481 fichiers mesurés le 2026-09-14), chaque sentinelle relit donc ce
 * dont elle a besoin sans cache partagé.
 *
 * Une règle que le dépôt enfreint déjà se protège par un cliquet : la sentinelle déclare, fichier
 * par fichier, le nombre d'occurrences existantes, et `compare()` refuse aussi bien une occurrence
 * de plus qu'une déclaration devenue trop haute. La dette ne peut que baisser, et chaque baisse
 * oblige à corriger le chiffre, ce qui la rend visible.
 */
class SentinelLib {

	/**
	 * Répertoires jamais lus : historique git, configuration des sessions, dépendances installées,
	 * scripts locaux de chacun (`perso/`, hors git).
	 */
	const EXCLUDED = ['.git', '.claude', 'node_modules', 'nodejs', 'perso', 'vendor'];

	const GIT_MEMORY = '-c core.packedGitWindowSize=16m -c core.packedGitLimit=128m -c core.preloadIndex=false';

	public static function getRoot(): string {
		return LIME_DIRECTORY;
	}

	/**
	 * La référence que les sentinelles lisent comme l'amont, `origin/main` sauf quand `/push` en
	 * nomme une autre par `OTF_SENTINEL_AMONT`. Il rejoue une sentinelle sur le commit qu'il essaie,
	 * dans un worktree détaché qui partage les refs du dépôt : `origin/main` y désigne l'amont d'avant
	 * ce commit, et un fichier que le commit crée ou déplace s'y lirait absent.
	 */
	public static function getUpstream(): string {
		return getenv('OTF_SENTINEL_AMONT') ?: 'origin/main';
	}

	/**
	 * Les chemins que git suit, depuis la racine du dépôt.
	 *
	 * @return array<string, TRUE>
	 */
	public static function getTracked(): ?array {

		static $tracked = FALSE;

		if($tracked === FALSE) {

			$output = [];
			$status = 0;
			exec('cd '.escapeshellarg(self::getRoot()).' && git ls-files -z 2>/dev/null', $output, $status);

			$paths = [];

			foreach(explode("\0", implode("\n", $output)) as $path) {
				if($path !== '') {
					$paths[$path] = TRUE;
				}
			}

			// Les deux issues d'un relevé vide demandent l'inverse l'une de l'autre, et c'est le code
			// de sortie qui les sépare. git absent ou en échec : on ne sait pas ce qui est suivi, et le
			// repli est le système de fichiers, annoncé. git qui répond zéro fichier dans un dépôt :
			// la commande n'a pas fait ce qu'elle dit, et s'appuyer dessus rendrait vert tout ce que
			// l'inventaire compare.
			if($status !== 0) {

				$tracked = NULL;

				echo 'Sentinelles : git est indisponible ici ('.self::getRoot().'), l\'inventaire porte donc aussi ce que git ne suit pas.'."\n";
				echo '  Un rouge peut nommer un fichier simplement pas encore commité, et la déclaration ne se baisse pas pour lui.'."\n";
				echo '  Sous Docker, les lancer depuis la machine, ou installer git dans l\'image php-fpm-otf.'."\n";

			} else if($paths === []) {
				throw new \Exception('git ls-files a répondu sans erreur et sans aucun fichier depuis '.self::getRoot().' : le relevé des sentinelles ne peut pas se faire');
			} else {
				$tracked = $paths;
			}

		}

		return $tracked;

	}

	/**
	 * Les fichiers du dépôt dont le nom finit par `$suffix`, indexés par leur chemin depuis la racine
	 * et triés, pour que deux lancements nomment les écarts dans le même ordre.
	 *
	 * Un fichier que git ne suit pas est écarté. Il n'existe que dans cet arbre, alors que la
	 * déclaration qui le confronte est poussée : le déclarer rend la sentinelle rouge chez tous
	 * ceux qui tirent, ne pas le déclarer la rend rouge ici, et aucun des deux gestes n'est le bon.
	 * Mesuré le 2026-10-01 sur `RealStructureSentinelAi`, rouge pour huit tests non suivis d'un
	 * chantier dont la fenêtre était fermée (relevé par E-Dashboard).
	 *
	 * ⚠️ **`$tracked: FALSE` pour un vocabulaire, c'est-à-dire un inventaire de ce qui existe et qui
	 * sert à reconnaître le légitime, non à le confronter.** Un nom connu de trop ne rend personne
	 * rouge ; un nom manquant fait prendre pour fautif ce qui est juste. Le filtre posé sur les
	 * `.m.php` dont `OrmSentinel` tire ses noms de module a rendu `$eEquipment` inconnu, donc mal
	 * nommé, dans deux fichiers suivis d'un lot dont les modules ne l'étaient pas encore, et le faux
	 * rouge frappait toutes les fenêtres de l'arbre jusqu'au push (relevé par C-Prod via pavow, le
	 * 2026-10-01).
	 *
	 * @return array<string, string>
	 */
	public static function getFiles(string $suffix, bool $tracked = TRUE): array {

		$root = self::getRoot();
		$files = [];

		$directories = new \RecursiveCallbackFilterIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
			fn(\SplFileInfo $file) => in_array($file->getFilename(), self::EXCLUDED, TRUE) === FALSE
		);

		foreach(new \RecursiveIteratorIterator($directories) as $file) {

			$relative = substr($file->getPathname(), strlen($root) + 1);

			if(str_ends_with($file->getFilename(), $suffix) and ($tracked === FALSE or self::getTracked() === NULL or isset(self::getTracked()[$relative]))) {
				$files[$relative] = $file->getPathname();
			}

		}

		ksort($files);

		return $files;

	}

	/**
	 * Les fichiers que `getFiles()` écarte parce que git ne les suit pas, comptés par package
	 * (les deux premiers segments du chemin), ou NULL quand git ne répond pas.
	 *
	 * Une passe verte ne dit rien d'eux, et rien dans un vert ne le distingue de celui d'un fichier
	 * lu : la passe le dit donc elle-même, à côté de son total. Mesuré le 2026-10-20 sur T26 de
	 * 3d-tech, quatre fichiers neufs écrits sous des passes vertes et rouges au premier essai de push,
	 * qui seul les ajoute à l'index (`.claude/push/main.py`, porte des fichiers neufs).
	 *
	 * @return array<string, int>|null
	 */
	public static function getUntracked(): ?array {

		$paths = self::getGitPaths('ls-files', '--others', '--exclude-standard');

		if($paths === NULL) {
			return NULL;
		}

		$packages = [];

		foreach($paths as $path) {

			$segments = explode('/', $path);

			if(
				in_array($segments[0], self::EXCLUDED, TRUE) === FALSE and
				preg_match('/\.(php|css|js|yml|md)$/', $path) === 1
			) {
				$packages[] = implode('/', array_slice($segments, 0, min(2, count($segments) - 1))) ?: '.';
			}

		}

		$packages = array_count_values($packages);
		ksort($packages);

		return $packages;

	}

	/**
	 * Les jetons significatifs d'un code PHP, sous une forme unique `[type, texte, ligne]`.
	 *
	 * Espaces et commentaires sont retirés : une règle ne porte jamais sur eux, et leur absence
	 * permet de lire le jeton voisin par `$i ± 1`. `token_get_all()` ne donne pas de ligne aux
	 * jetons d'un caractère (`(`, `;`…) : elle est reportée depuis le jeton précédent, en comptant
	 * les retours à la ligne qu'il contient.
	 *
	 * @return array<int, array{0: int|string, 1: string, 2: int}>
	 */
	public static function getTokens(string $code): array {

		$tokens = [];
		$line = 1;

		foreach(token_get_all($code) as $token) {

			if(is_array($token)) {

				[$type, $text, $line] = $token;

				if($type !== T_WHITESPACE and $type !== T_COMMENT and $type !== T_DOC_COMMENT) {
					$tokens[] = [$type, $text, $line];
				}

				$line += substr_count($text, "\n");

			} else {
				$tokens[] = [$token, $token, $line];
			}

		}

		return $tokens;

	}

	/**
	 * La position du jeton qui ferme la parenthèse, le crochet ou l'accolade ouvert en `$position`.
	 * Rend la dernière position si le code est tronqué.
	 */
	public static function getClosing(array $tokens, int $position): int {

		$depth = 0;
		$count = count($tokens);

		for($i = $position; $i < $count; $i++) {

			$type = $tokens[$i][0];

			if($type === '(' or $type === '[' or $type === '{' or $type === T_CURLY_OPEN or $type === T_DOLLAR_OPEN_CURLY_BRACES) {
				$depth++;
			} else if($type === ')' or $type === ']' or $type === '}') {

				$depth--;

				if($depth === 0) {
					return $i;
				}

			}

		}

		return $count - 1;

	}

	public static function getNamespace(array $tokens): string {

		foreach($tokens as $i => [$type]) {

			if($type === T_NAMESPACE) {
				return $tokens[$i + 1][1];
			}

		}

		return '';

	}

	/**
	 * Les entrées `stale` de `compare()`, augmentées de ce que l'amont dit de chacune.
	 *
	 * Séparée de `compare()`, qui reste une fonction pure : la phrase ajoutée ici dépend de l'état du
	 * working tree au moment du lancement, donc un test qui comparerait la sortie de `compare()` à une
	 * valeur écrite deviendrait vert ou rouge selon ce que les autres fenêtres ont modifié.
	 *
	 * Un fichier identique à `origin/main` porte sa résorption dans l'amont : la baisse est juste, et
	 * elle est verte pour tous ceux qui tirent. Un fichier que l'arbre modifie peut ne devoir sa
	 * résorption qu'à des hunks non poussés, et la baisser verdit ici en rendant l'amont rouge
	 * ailleurs : elle part avec ces hunks, chez leur auteur. Un fichier que l'amont ne porte pas du
	 * tout n'a jamais rendu son entrée vraie là-bas, et la baisse ne dit rien de ce que l'arbre y
	 * ajoutera. Une entrée dont le fichier manque des deux côtés porte déjà sa propre garde et n'est
	 * pas touchée.
	 *
	 * ⚠️ `diff --quiet origin/main -- <chemin>` rend **0** sur un fichier que l'amont ne porte pas,
	 * la même valeur que sur un fichier identique : git compare deux absences. Mesuré le 2026-09-30
	 * sur `commercialisation/selling/page/configure/lot2RecetteAi.p.php`, non suivi, 0 contre 1 sur
	 * un fichier modifié. C'est le cas où le message compte le plus, puisque c'est celui où la baisse
	 * est juste pour une autre raison que celle qu'il donnerait, donc l'existence du blob se demande
	 * avant, par `cat-file -e`. Git n'est interrogé que sur un écart déjà constaté, et son silence ne
	 * change pas le message.
	 *
	 * @param string[] $stale
	 * @return string[]
	 */
	public static function explainStale(array $stale): array {

		return array_map(function(string $entry): string {

			$path = strtok($entry, ' ');

			if(is_file(self::getRoot().'/'.$path) === FALSE) {
				return $entry;
			}

			exec('git -C '.escapeshellarg(self::getRoot()).' rev-parse --verify --quiet '.escapeshellarg(self::getUpstream()).' 2>/dev/null', $lines, $status);

			if($status !== 0) {
				return $entry;
			}

			// `git diff origin/main -- <chemin>` ne rend aucune différence sur un fichier que
			// l'amont ne porte pas, exactement comme sur un fichier identique : les deux cas se
			// séparent avant, par l'existence du blob.
			exec('git -C '.escapeshellarg(self::getRoot()).' cat-file -e '.escapeshellarg(self::getUpstream().':'.$path).' 2>/dev/null', $lines, $status);

			if($status !== 0) {
				return $entry.' [absent de origin/main : l\'entrée n\'y a jamais été vraie, elle se déclarera avec les hunks qui l\'y feront entrer]';
			}

			exec('git -C '.escapeshellarg(self::getRoot()).' diff --quiet '.escapeshellarg(self::getUpstream()).' -- '.escapeshellarg($path).' 2>/dev/null', $lines, $status);

			return $entry.match($status) {
				0 => ' [identique à origin/main, donc résorbée dans l\'amont : baisser la déclaration]',
				1 => ' [mais l\'arbre le modifie par rapport à origin/main : la résorption peut n\'exister que dans l\'arbre, et la déclaration part alors avec les hunks qui la résorbent, chez leur auteur]',
				default => '',
			};

		}, $stale);

	}

	/**
	 * La fin du message d'un échec de sentinelle : l'état git des fichiers qu'il nomme, quand ce
	 * n'est pas celui d'un fichier suivi et identique à `HEAD`.
	 *
	 * Un fichier non suivi n'existe que dans l'arbre qui l'écrit, et deux postes alignés sur le même
	 * commit en portent d'autres : le rouge qu'il cause est local, et la déclaration ne se corrige pas
	 * pour lui. « non suivi, écrit le 2026-09-30 05:41:10 » classe ce rouge sans enquête, là où le
	 * message seul envoie chercher dans le code (conseil des gardiens,
	 * `2026-09-29-artefact-derive-se-perime`, piste 2, sur `SkillDocumentedSentinelAi`). L'heure est
	 * celle du disque, pas un âge : un âge lirait l'horloge que les tests figent.
	 *
	 * Seuls les chemins écrits depuis la racine sont reconnus, `CLAUDE.md` compris, puisque ce sont
	 * les seuls que le disque départage. Deux sortes de chemins ne sont pas la cause du rouge et ne se classent pas :
	 * le fichier de la sentinelle, que le message nomme pour sa déclaration, et ceux d'un complément
	 * entre crochets, qu'une explication a déjà classés. git n'est interrogé que si le message nomme
	 * un autre chemin.
	 */
	public static function explainGitState(string $message, string $sentinel): string {

		preg_match_all('#(?<![\w./@-])(?:[\w.@-]+/)*[\w.@-]+\.[A-Za-z0-9]+#u', preg_replace('/\[[^\]]*\]/u', '', $message), $matches);

		$paths = array_values(array_unique(array_filter(
			$matches[0],
			fn(string $path) => ($path !== $sentinel and is_file(self::getRoot().'/'.$path))
		)));

		if($paths === []) {
			return '';
		}

		$tracked = self::getGitPaths('--literal-pathspecs', 'ls-files', '--', ...$paths);
		$ignored = self::getGitPaths('--literal-pathspecs', 'ls-files', '--others', '--ignored', '--exclude-standard', '--', ...$paths);
		$written = self::getGitPaths('--literal-pathspecs', 'diff', '--name-only', 'HEAD', '--', ...$paths);

		if($tracked === NULL or $ignored === NULL or $written === NULL) {
			return ' [état git non lu]';
		}

		$states = [];

		foreach($paths as $path) {

			$time = date('Y-m-d H:i:s', filemtime(self::getRoot().'/'.$path));

			if(in_array($path, $ignored, TRUE)) {
				$states[] = $path.' ignoré par le .gitignore du dépôt, écrit le '.$time;
			} else if(in_array($path, $tracked, TRUE) === FALSE) {
				$states[] = $path.' non suivi, écrit le '.$time;
			} else if(in_array($path, $written, TRUE)) {
				$states[] = $path.' suivi, modifié dans l\'arbre le '.$time;
			}

		}

		if($states === []) {
			return '';
		}

		return ' [état git : '.implode(' · ', $states).']';

	}

	/**
	 * Les entrées neuves d'un cliquet, chacune finie par un verdict qui nomme sa cause et le geste.
	 *
	 * Sur un arbre partagé, le même rouge a cinq causes, et une seule appelle une déclaration : une
	 * occurrence en vol chez une autre session, un arbre en retard sur l'amont ou un fichier de
	 * sentinelle réécrit dans l'arbre se déclareraient à tort. Le verdict confronte le fichier de
	 * l'occurrence et celui de la déclaration à `HEAD` et à `origin/main`, la cause la plus sûre à
	 * corriger d'abord : un arbre en retard fausse toutes les comparaisons suivantes. `origin/main`
	 * est celui du dernier `fetch` de cet arbre, pas l'amont réel. Un statut de git autre que 0 ou 1
	 * rend « amont non lu » et ne conclut rien, un silence de git se lisant comme une absence.
	 *
	 * Les entrées viennent de `compare()` (`chemin (…)`) ou d'`assertDeclared()` (`chemin:clé (…)`).
	 *
	 * @param string[] $new
	 * @return string[]
	 */
	public static function explainNew(array $new, string $declaration): array {

		$behind = self::getGitPaths('diff', '--name-only', 'HEAD', self::getUpstream());
		$written = self::getGitPaths('diff', '--name-only', 'HEAD');
		$upstream = self::getGitPaths('ls-tree', '-r', '--name-only', self::getUpstream());

		return array_map(function(string $entry) use($declaration, $behind, $written, $upstream): string {

			$path = strtok(strtok($entry, ' '), ':');

			return $entry.' ['.self::getNewCause($path, $declaration, $behind, $written, $upstream).']';

		}, $new);

	}

	/**
	 * @param string[]|null $behind
	 * @param string[]|null $written
	 * @param string[]|null $upstream
	 */
	private static function getNewCause(string $path, string $declaration, ?array $behind, ?array $written, ?array $upstream): string {

		if($behind === NULL or $written === NULL or $upstream === NULL) {
			return 'amont non lu, aucune cause établie';
		}

		foreach([$path, $declaration] as $file) {

			if(in_array($file, $behind, TRUE)) {
				return '/pull d\'abord, l\'arbre est en retard sur origin/main pour '.$file;
			}

		}

		if(in_array($path, $upstream, TRUE) === FALSE) {
			return 'fichier neuf, à corriger ou à déclarer par son lot';
		}

		if(in_array($path, $written, TRUE)) {
			return 'écriture en vol, à corriger par son lot ou à dire à qui tient ce fichier';
		}

		if(in_array($declaration, $written, TRUE)) {
			return 'le fichier de la déclaration diffère de origin/main dans l\'arbre, voir avec qui le tient avant de déclarer';
		}

		// L'occurrence vient du dernier commit qui a écrit le fichier ; un commit plus récent sur la
		// déclaration peut l'avoir découverte en la baissant, et se nomme alors à côté.
		$file = self::getLastCommit($path);
		$declared = self::getLastCommit($declaration);

		if($file === NULL or $declared === NULL) {
			return 'amont non lu, aucune cause établie';
		}

		$cause = 'rouge dans origin/main, à corriger ou à déclarer par '.$file['author'];

		if($declared['time'] > $file['time']) {
			$cause .= ', déclaration changée depuis par '.$declared['author'];
		}

		return $cause;

	}

	/**
	 * Le dernier commit d'`origin/main` sur un fichier, nommé comme `/push` ouvre chaque titre,
	 * `[poste] [chantier] <hash>`, puisque c'est à eux que le geste s'adresse.
	 *
	 * @return array{author: string, time: int}|null
	 */
	private static function getLastCommit(string $path): ?array {

		$output = self::getGitPaths('log', '-1', '--format=%h%x09%ct%x09%s', self::getUpstream(), '--', $path);

		if($output === NULL or $output === []) {
			return NULL;
		}

		[$commit, $time, $subject] = explode("\t", $output[0], 3);

		return [
			'author' => (preg_match('/^(\[[^\]]+\] ?)+/', $subject, $prefix) === 1) ? trim($prefix[0]).' '.$commit : $commit,
			'time' => (int)$time,
		];

	}

	/**
	 * Les chemins qu'une commande git rend un par ligne, ou NULL quand git ne répond pas.
	 *
	 * @return string[]|null
	 */
	public static function getGitPaths(string ...$arguments): ?array {

		$output = self::getGitOutput(...$arguments);

		if($output === NULL) {
			return NULL;
		}

		return array_values(array_filter(explode("\n", $output), fn(string $path) => $path !== ''));

	}

	/**
	 * La sortie d'une commande git telle quelle, lignes vides comprises, ou NULL quand git ne répond pas.
	 *
	 * Seul le `.gitignore` du dépôt dit ce qui est ignoré. git lit aussi l'ignore global de la machine,
	 * `core.excludesFile` ou à défaut `~/.config/git/ignore`, sans aucun réglage : les deux postes
	 * mesurés le 2026-09-30 masquaient ainsi tout `.claude/` pour l'un, `.claude/settings.local.json`
	 * pour l'autre, et une sentinelle rendait un verdict différent par poste sur le même commit. Un
	 * réglage vide ne prouvant pas l'absence d'ignore global, la précaution se pose sur chaque appel
	 * (conseil des gardiens, `2026-09-29-artefact-derive-se-perime`, piste 3).
	 */
	public static function getGitOutput(string ...$arguments): ?string {

		// Sans shell, les arguments arrivent à git tels quels. Une passe parallèle tourne sous
		// `ulimit -v` (TestJobLib::MEMORY), où git ne peut ni projeter un paquet entier en mémoire ni
		// ouvrir les fils qui lisent l'index : GIT_MEMORY borne les fenêtres et lit l'index d'un seul
		// fil. Sans verrou facultatif, `git diff` ne bute pas sur l'index qu'une autre passe rafraîchit.
		$process = proc_open(['git', '-C', self::getRoot(), ...explode(' ', self::GIT_MEMORY), '-c', 'core.excludesFile=/dev/null', ...$arguments], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, NULL, getenv() + ['GIT_OPTIONAL_LOCKS' => '0']);

		if($process === FALSE) {
			return NULL;
		}

		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		if(proc_close($process) !== 0) {
			return NULL;
		}

		return $output;

	}

	/**
	 * Confronte les occurrences trouvées (`chemin:ligne`) à la déclaration (`chemin => nombre`).
	 *
	 * Rend deux listes de messages, une par sens : `new`, les fichiers qui portent plus
	 * d'occurrences que déclaré, avec leurs lignes ; `stale`, les fichiers déclarés au-dessus de ce
	 * qu'ils portent, déclaration à baisser.
	 *
	 * @param string[] $found
	 * @param array<string, int> $declared
	 * @return array{new: string[], stale: string[]}
	 */
	public static function compare(array $found, array $declared): array {

		$lines = [];

		foreach($found as $occurrence) {

			$separator = strrpos($occurrence, ':');
			$lines[substr($occurrence, 0, $separator)][] = substr($occurrence, $separator + 1);

		}

		ksort($lines);

		$new = [];
		$stale = [];

		foreach($lines as $file => $list) {

			$expected = $declared[$file] ?? 0;

			if(count($list) > $expected) {
				$new[] = $file.' ('.count($list).' pour '.$expected.' déclarée(s), lignes '.implode(', ', $list).')';
			}

		}

		foreach($declared as $file => $expected) {

			// Symétrique du filtre de `getFiles()` : un fichier présent sur le disque que git ne
			// suit pas est invisible des deux côtés du cliquet, sans quoi le rouge se déplace au
			// lieu de disparaître. Déclarée seule, l'entrée serait périmée chez son auteur, dont
			// l'arbre porte le fichier ; déclarée nulle part, l'occurrence serait nouvelle chez
			// lui. Ignorée des deux côtés, elle dort jusqu'au jour où le fichier est poussé, et
			// redevient alors vraie partout. Un chemin que ni le disque ni git ne portent reste
			// nommé : celui-là ne dort pas, il ne désigne plus rien.
			// Une clé peut porter ce qu'elle compte à la suite du chemin (`ui/A.u.php « Ferme »`).
			$declaredPath = strtok($file, ' ');

			if(self::getTracked() !== NULL and is_file(self::getRoot().'/'.$declaredPath) and isset(self::getTracked()[$declaredPath]) === FALSE) {
				continue;
			}

			$count = count($lines[$file] ?? []);

			if($count < $expected) {

				// Un fichier absent du disque rend zéro occurrence sans que la dette ait bougé :
				// sur cet arbre partagé, c'est d'abord le signe d'un retard sur `origin/main`, et
				// baisser la déclaration verdit ici en rendant l'amont rouge chez les autres.
				// Une clé n'est pas toujours un chemin nu : elle peut porter ce qu'elle compte à la
				// suite du chemin (`ui/A.u.php « Ferme »`). La commande proposée ne vaut que sur le
				// chemin seul, et sur une clé composée elle échouerait, ce qui se lit exactement
				// comme « le fichier n'est plus dans l'amont », l'inverse de ce qu'elle mesure.
				$path = strtok($file, ' ');

				$stale[] = is_file(self::getRoot().'/'.$path)
					? $file.' ('.$expected.' déclarée(s) pour '.$count.')'
					: $file.' (absent de l\'arbre, '.$expected.' déclarée(s). Vérifier `git cat-file -e origin/main:'.$path.'` avant de baisser)';

			}

		}

		return [
			'new' => $new,
			'stale' => $stale,
		];

	}

}
?>
