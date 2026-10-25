<?php
namespace security;

/**
 * La veille technique : chaque logiciel de `SoftwareAiLib` comparé à ce que ses éditeurs publient.
 *
 * Un constat ne dit pas qu'OTF est attaquable, il dit qu'un logiciel dont OTF dépend a une faille
 * corrigée ailleurs, une version de sécurité, une fin de support ou un retrait annoncé. Pour ne
 * pas noyer le reste, la veille ne retient que ce qui se corrige : une faille sans correctif publié
 * ne se dit que critique. Chaque constat a un niveau, de critique à bas, que l'exposition du
 * logiciel ajuste (`SoftwareAiLib::EXPOSED`) ; le message du jour ne porte que les constats
 * critiques et élevés apparus depuis la veille, celui du lundi les reprend tous.
 *
 * Chaque source se lit à part : celle qui refuse est dite dans le message (`unread`), les autres
 * continuent.
 */
class SoftwareWatchAiLib {

	use \Notifiable;

	const VULNERABLE = 'vulnerable';
	const SECURITY_UPDATE = 'security-update';
	const SECURITY_RELEASE = 'security-release';
	const OUTDATED = 'outdated';
	const EOL = 'eol';
	const EOL_UPCOMING = 'eol-upcoming';
	const REBOOT = 'reboot';
	const POLICY = 'policy';
	const REMOVAL = 'removal';

	const SOFTWARE = 'software';
	const SYSTEM = 'system';
	const LIBRARY = 'library';
	const SERVICE = 'service';

	/**
	 * Une fin de support se signale ce nombre de jours avant sa date, le temps de préparer la
	 * montée de version.
	 */
	const EOL_NOTICE_DAYS = 90;

	/**
	 * Les listes d'apt se rafraîchissent chaque jour sur Ubuntu (`apt-daily.timer`) : plus vieilles
	 * que ça, les mises à jour qu'elles ne connaissent pas échappent à la veille.
	 */
	const APT_LISTS_MAX_AGE_DAYS = 3;

	/**
	 * Les constats, ce qui n'a pas pu se lire et pourquoi, et le nombre d'éléments que le registre
	 * écarte (`SoftwareAiLib::ACCEPTED`). Les services tiers ajoutent les leurs par l'événement
	 * `watchServices`, dans les packages qui les appellent.
	 *
	 * @return array{findings: array<int, array>, unread: array<string, string>, accepted: int}
	 */
	public static function check(): array {

		$findings = [];
		$unread = [];
		$accepted = 0;

		$versions = SoftwareAiLib::getVersions();

		foreach(SoftwareAiLib::SOFTWARES as $key => $software) {

			if($versions[$key] === NULL) {
				$unread[$software['name']] = 'version illisible sur cette machine';
				continue;
			}

			try {
				$findings = [...$findings, ...self::checkSoftware($key, $software, $versions[$key])];
			} catch(\Throwable $e) {
				$unread[$software['name']] = $e->getMessage();
			}

		}

		try {
			$findings = [...$findings, ...self::checkSystem($unread, $accepted)];
		} catch(\Throwable $e) {
			$unread['Paquets du système'] = $e->getMessage();
		}

		try {
			$findings = [...$findings, ...self::checkLibraries($accepted)];
		} catch(\Throwable $e) {
			$unread['Bibliothèques'] = $e->getMessage();
		}

		// Une annonce de retrait se lit sur une semaine, le récapitulatif du lundi la reprenant.
		$since = date('Y-m-d', strtotime(\Clock::date().' - 7 days'));

		foreach(self::notify('watchServices', $since) as $result) {
			$findings = [...$findings, ...$result['findings']];
			$unread += $result['unread'];
		}

		foreach($findings as $index => $finding) {
			if(SoftwareAiLib::isAccepted($finding['key'])) {
				unset($findings[$index]);
				$accepted++;
			}
		}

		return [
			'findings' => self::sort(array_values($findings)),
			'unread' => $unread,
			'accepted' => $accepted,
		];

	}

	/**
	 * Un constat, sous la forme que lisent le message et la ligne de commande. `date` est le jour où
	 * le constat est apparu : une faille publiée, une version sortie, une fin de support atteinte.
	 */
	public static function finding(string $key, string $group, string $kind, string $name, int $level, string $text, string $url = '', ?string $date = NULL, array $vulnerabilities = []): array {

		return [
			'key' => $key,
			'group' => $group,
			'kind' => $kind,
			'name' => $name,
			'level' => $level,
			'text' => $text,
			'url' => $url,
			'date' => $date,
			'vulnerabilities' => $vulnerabilities,
		];

	}

	private static function checkSoftware(string $key, array $software, string $version): array {

		// La distribution suit le logiciel qu'elle apporte, avec ses autres paquets.
		if(isset($software['package']) and SoftwareAiLib::isSystemPackage($software['package'])) {
			return [];
		}

		$findings = [];
		$today = \Clock::date();

		$product = $software['eol'] ?? SoftwareAiLib::getSystem()['id'];

		$cycles = ReleaseAiLib::getCycles($product);
		$cycle = ReleaseAiLib::findCycle($cycles, $version);

		if($cycle === NULL) {
			throw new \Exception('branche de la version '.$version.' inconnue de endoflife.date');
		}

		$eolFrom = $cycle['eolFrom'];

		if($eolFrom !== NULL) {

			$notice = date('Y-m-d', strtotime($eolFrom.' - '.self::EOL_NOTICE_DAYS.' days'));

			if($eolFrom <= $today) {
				$findings[] = self::finding('eol:'.$key, self::SOFTWARE, self::EOL, $software['name'], OsvAiLib::HIGH, 'version '.$version.' sans support depuis le '.\util\DateUi::numeric($eolFrom).', passer à la branche '.ReleaseAiLib::getRecommendedCycle($cycles)['name'], ReleaseAiLib::ENDOFLIFE_PAGE_URL.$product, $eolFrom);
			} else if($notice <= $today) {
				$findings[] = self::finding('eol:'.$key, self::SOFTWARE, self::EOL_UPCOMING, $software['name'], OsvAiLib::MEDIUM, 'branche '.$cycle['name'].' sans support à partir du '.\util\DateUi::numeric($eolFrom), ReleaseAiLib::ENDOFLIFE_PAGE_URL.$product, $notice);
			}

		}

		$releases = isset($software['security']) ? ReleaseAiLib::getSecurityReleases($software['security'], $version) : [];

		if($releases !== []) {

			uksort($releases, 'version_compare');

			$target = array_key_last($releases);

			$findings[] = self::finding(
				'release:'.$key.':'.$target,
				self::SOFTWARE,
				self::SECURITY_RELEASE,
				$software['name'],
				match($software['exposure']) {
					SoftwareAiLib::EXPOSED => OsvAiLib::HIGH,
					SoftwareAiLib::INTERNAL => OsvAiLib::MEDIUM,
				},
				$version.' installée, '.self::quantity(count($releases), 'version de sécurité publiée', 'versions de sécurité publiées').' depuis, passer à '.$target,
				($key === 'php') ? ReleaseAiLib::PHP_ARCHIVE_URL.substr($releases[$target], 0, 4).'.php' : 'https://nodejs.org/en/blog/vulnerability',
				max($releases)
			);

		} else if($key !== 'system' and $cycle['latest'] !== NULL and version_compare($cycle['latest'], $version, '>')) {
			$findings[] = self::finding('release:'.$key.':'.$cycle['latest'], self::SOFTWARE, self::OUTDATED, $software['name'], OsvAiLib::LOW, $version.' installée, '.$cycle['latest'].' publiée', '', $cycle['latestAt']);
		}

		return $findings;

	}

	private static function checkSystem(array &$unread, int &$accepted): array {

		$system = SoftwareAiLib::getSystem();

		if($system === NULL or $system['ecosystem'] === NULL) {
			throw new \Exception('système que OSV ne connaît pas');
		}

		$findings = [];

		if(SoftwareAiLib::isRebootRequired()) {
			$findings[] = self::finding('reboot', self::SYSTEM, self::REBOOT, 'Redémarrage', OsvAiLib::HIGH, 'une mise à jour installée n\'agit qu\'au prochain redémarrage du serveur', '', date('Y-m-d', (int)filemtime('/var/run/reboot-required')));
		}

		// Daté du jour, le constat repart chaque matin tant que la policy manque : rien ne date sa disparition
		if(SoftwareAiLib::isImageMagickPolicyMissing()) {
			$findings[] = self::finding('imagemagick-policy', self::SYSTEM, self::POLICY, 'ImageMagick', OsvAiLib::HIGH, 'la policy du dépôt n\'est pas en place et un SVG se lit, à reposer (migration/2026-10-21-securite-imagemagick-policy.md)', '', \Clock::date());
		}

		['packages' => $packages, 'listsAt' => $listsAt] = SoftwareAiLib::getSystemUpgrades();

		if($listsAt === NULL) {
			$unread['Mises à jour du système'] = 'listes d\'apt absentes';
		} else if($listsAt < date('Y-m-d', strtotime(\Clock::date().' - '.self::APT_LISTS_MAX_AGE_DAYS.' days'))) {
			$unread['Mises à jour du système'] = 'listes d\'apt du '.\util\DateUi::numeric($listsAt).', une mise à jour plus récente ne se voit pas';
		}

		$queries = [];

		foreach($packages as $source => $package) {

			if($package['security'] === FALSE) {
				continue;
			}

			if(SoftwareAiLib::isKernel($source)) {
				$findings[] = self::finding('update:'.$source, self::SYSTEM, self::SECURITY_UPDATE, 'Noyau ('.$source.')', OsvAiLib::HIGH, $package['installed'].' installé, mise à jour de sécurité du noyau en attente, active au redémarrage suivant');
			} else {
				$queries[] = [
					'ecosystem' => $system['ecosystem'],
					'package' => $source,
					'version' => $package['installed'],
					'exposure' => SoftwareAiLib::getExposure($source),
					'source' => 'mise à jour du système',
				];
			}

		}

		foreach(self::checkVulnerabilities($queries, self::SYSTEM, $accepted) as $index => $finding) {

			// Le dépôt de sécurité ne publie que des correctifs : une mise à jour qu'il porte compte
			// même quand OSV n'a pas encore rattaché sa faille.
			$findings[] = $finding ?? self::finding(
				'update:'.$queries[$index]['package'],
				self::SYSTEM,
				self::SECURITY_UPDATE,
				$queries[$index]['package'],
				OsvAiLib::MEDIUM,
				$queries[$index]['version'].' installée, mise à jour de sécurité en attente'
			);

		}

		return $findings;

	}

	private static function checkLibraries(int &$accepted): array {

		$libraries = SoftwareAiLib::getLibraries();

		return array_values(array_filter(self::checkVulnerabilities($libraries, self::LIBRARY, $accepted)));

	}

	/**
	 * Un constat par paquet qui porte au moins une faille retenue, NULL pour un paquet qui n'en a
	 * aucune, dans l'ordre des requêtes. Une faille que le registre écarte se compte dans
	 * `$accepted`.
	 *
	 * @param array<int, array{ecosystem: string, package: string, version: string, exposure: string, source: string}> $queries
	 * @return array<int, ?array>
	 */
	private static function checkVulnerabilities(array $queries, string $group, int &$accepted): array {

		$results = OsvAiLib::query($queries);

		$records = [];
		$findings = [];

		foreach($queries as $index => $query) {

			$vulnerabilities = [];

			foreach($results[$index] as $id) {

				if(SoftwareAiLib::isAccepted($id)) {
					$accepted++;
					continue;
				}

				$records[$id] ??= OsvAiLib::fetch($id);

				$vulnerabilities[] = OsvAiLib::describe($records[$id], $query['ecosystem'], $query['package'], $query['version']);

			}

			$findings[$index] = self::buildVulnerable($query, $vulnerabilities, $group);

		}

		return $findings;

	}

	/**
	 * Le constat d'un paquet, de ses failles. Une faille interne compte un cran de moins, et une
	 * faille sans correctif ne se retient que critique : rien ne la ferme de notre côté, et ce sont
	 * elles qui faisaient du bruit (2 842 failles relevées sur les 335 paquets du conteneur de
	 * développement le 21/10/2026, presque toutes sans correctif).
	 */
	public static function buildVulnerable(array $query, array $vulnerabilities, string $group): ?array {

		$kept = [];

		foreach($vulnerabilities as $vulnerability) {

			if($query['exposure'] === SoftwareAiLib::INTERNAL) {
				$vulnerability['level'] = max($vulnerability['level'] - 1, OsvAiLib::LOW);
			}

			if($vulnerability['fixed'] !== NULL or $vulnerability['level'] === OsvAiLib::CRITICAL) {
				$kept[] = $vulnerability;
			}

		}

		if($kept === []) {
			return NULL;
		}

		usort($kept, fn(array $a, array $b) => [$b['level'], $b['published']] <=> [$a['level'], $a['published']]);

		$level = $kept[0]['level'];

		$counts = [];

		foreach($kept as $vulnerability) {
			$counts[$vulnerability['level']] = ($counts[$vulnerability['level']] ?? 0) + 1;
		}

		krsort($counts);

		$fixed = array_filter(array_column($kept, 'fixed'));
		usort($fixed, 'version_compare');

		$text = $query['version'].' installée · '.implode(', ', array_map(fn(int $count, int $countLevel) => $count.' '.self::getLevelLabel($countLevel, $count), $counts, array_keys($counts)));

		if($fixed === []) {
			$text .= ', sans correctif publié';
		} else if($group === self::SYSTEM) {
			$text .= (count($kept) > 1) ? ', corrigées par une mise à jour du système' : ', corrigée par une mise à jour du système';
		} else {
			$text .= ((count($kept) > 1) ? ', corrigées en ' : ', corrigée en ').end($fixed);
		}

		$key = $group.':'.$query['package'].':'.$query['version'];

		return self::finding($key, $group, self::VULNERABLE, $query['package'], $level, $text, $kept[0]['url'], max(array_column(array_filter($kept, fn(array $vulnerability) => $vulnerability['level'] === $level), 'published')), $kept);

	}

	private static function quantity(int $count, string $singular, string $plural): string {
		return $count.' '.(($count > 1) ? $plural : $singular);
	}

	public static function getLevelLabel(int $level, int $count = 1): string {

		$label = match($level) {
			OsvAiLib::CRITICAL => 'critique',
			OsvAiLib::HIGH => 'élevée',
			OsvAiLib::MEDIUM => 'moyenne',
			OsvAiLib::LOW => 'basse',
		};

		return ($count > 1) ? $label.'s' : $label;

	}

	/**
	 * Du plus grave au moins grave, puis du plus récent au plus ancien.
	 */
	private static function sort(array $findings): array {

		usort($findings, fn(array $a, array $b) => [$b['level'], $b['date'] ?? ''] <=> [$a['level'], $a['date'] ?? '']);

		return $findings;

	}

	/**
	 * Les blocs du message Discord, un par niveau : le critique et l'élevé ligne à ligne, le moyen
	 * et le bas résumés par famille, pour qu'une seule ligne dise « mettre le système à jour » au
	 * lieu de quarante. Le message du jour n'a que les deux premiers.
	 *
	 * @return array<int, array{title: string, color: int, description: string}>
	 */
	public static function getEmbeds(array $findings, array $unread, int $accepted, bool $weekly): array {

		$embeds = [];

		foreach([
			[OsvAiLib::CRITICAL, '🔴 Critique', 15158332],
			[OsvAiLib::HIGH, '🟠 Élevé', 15105570],
		] as [$level, $title, $color]) {

			$lines = array_map(
				fn(array $finding) => '• **'.self::escape($finding['name']).'** · '.self::escape($finding['text']).(str_starts_with($finding['url'], 'https://') ? ' · [détail](<'.$finding['url'].'>)' : ''),
				array_filter($findings, fn(array $finding) => $finding['level'] === $level)
			);

			if($lines !== []) {
				$embeds[] = ['title' => $title, 'color' => $color, 'description' => self::cut($lines)];
			}

		}

		if($weekly === FALSE) {
			return $embeds;
		}

		foreach([
			[OsvAiLib::MEDIUM, '🟡 À planifier', 16776960],
			[OsvAiLib::LOW, '⚪ Quand l\'occasion se présente', 12370112],
		] as [$level, $title, $color]) {

			$lines = self::summarize(array_filter($findings, fn(array $finding) => $finding['level'] === $level));

			if($lines !== []) {
				$embeds[] = ['title' => $title, 'color' => $color, 'description' => self::cut($lines)];
			}

		}

		$notes = [];

		foreach($unread as $name => $reason) {
			$notes[] = '• '.self::escape($name).' non lu, '.self::escape($reason);
		}

		if($accepted > 0) {
			$notes[] = '• '.self::quantity($accepted, 'élément écarté', 'éléments écartés').' au registre (`security\\SoftwareAiLib::ACCEPTED`)';
		}

		if($notes !== []) {
			$embeds[] = ['title' => 'Ce que la veille n\'a pas vu', 'color' => 9807270, 'description' => self::cut($notes)];
		}

		return $embeds;

	}

	/**
	 * Un texte venu d'une source distante (titre de flux, cellule d'un tableau) se lit tel quel dans
	 * Discord : sans échappement, un flux compromis pose un lien masqué dans le salon.
	 */
	private static function escape(string $text): string {
		return preg_replace('/([\\\\*_~|`\[\]()<>])/', '\\\\$1', $text);
	}

	/**
	 * Une ligne par famille : les paquets du système ensemble, les bibliothèques ensemble, chaque
	 * logiciel et chaque service à part.
	 */
	private static function summarize(array $findings): array {

		$lines = [];
		$grouped = [];

		foreach($findings as $finding) {
			if(in_array($finding['group'], [self::SYSTEM, self::LIBRARY], TRUE)) {
				$grouped[$finding['group']][] = $finding['name'];
			} else {
				$lines[] = '• **'.self::escape($finding['name']).'** · '.self::escape($finding['text']);
			}
		}

		foreach($grouped as $group => $names) {

			$names = array_values(array_unique($names));
			$shown = implode(', ', array_slice($names, 0, 8)).(count($names) > 8 ? ' et '.self::quantity(count($names) - 8, 'autre', 'autres') : '');

			$lines[] = match($group) {
				self::SYSTEM => '• **Paquets du système** · '.count($names).' à mettre à jour ('.$shown.'), une mise à jour du système les corrige',
				self::LIBRARY => '• **Bibliothèques** · '.count($names).' avec une faille corrigée ('.$shown.')',
			};

		}

		return $lines;

	}

	/**
	 * Discord refuse une description de plus de 4 096 caractères : la liste s'arrête avant.
	 */
	private static function cut(array $lines): string {

		$kept = [];
		$length = 0;

		foreach($lines as $line) {

			if($length + mb_strlen($line) > 3500) {
				$kept[] = '… et '.self::quantity(count($lines) - count($kept), 'autre', 'autres');
				break;
			}

			$kept[] = $line;
			$length += mb_strlen($line) + 1;

		}

		return implode("\n", $kept);

	}

	/**
	 * Les constats que le message du jour porte : critiques ou élevés, apparus depuis `$since`.
	 */
	public static function getNew(array $findings, string $since): array {

		return array_values(array_filter($findings, fn(array $finding) => (
			$finding['level'] >= OsvAiLib::HIGH and
			$finding['date'] !== NULL and
			$finding['date'] >= $since
		)));

	}

}
?>
