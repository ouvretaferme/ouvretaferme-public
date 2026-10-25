<?php
namespace work;

/**
 * Le catalogue E-Phy des produits phytopharmaceutiques autorisés, publié chaque semaine par l'ANSES
 * sur data.gouv.fr (licence ouverte). Il sert à retrouver un produit par son nom ou son AMM quand une
 * structure le note, et à lui montrer les doses autorisées sur ses cultures. Il n'est la source
 * d'aucune donnée de la structure : son produit (work\Tool à routine phyto) recopie ce qu'il prend.
 */
class PhytoProductLib extends PhytoProductCrud {

	const DATASET = 'https://www.data.gouv.fr/api/1/datasets/donnees-ouvertes-du-catalogue-e-phy-des-produits-phytopharmaceutiques-matieres-fertilisantes-et-supports-de-culture-adjuvants-produits-mixtes-et-melanges/';

	// Les matières fertilisantes (MFSC) et les adjuvants seuls n'entrent pas au registre phytosanitaire.
	const TYPES = ['PPP', 'PRODUIT-MIXTE', 'MELANGE'];

	const ORGANIC = 'Utilisable en agriculture biologique';

	const AMATEUR = 'Amateur / emploi autorisé dans les jardins';

	public static function getByAmm(string $amm): PhytoProduct {

		return PhytoProduct::model()
			->select(PhytoProduct::getSelection())
			->whereAmm($amm)
			->get();

	}

	/**
	 * Par le nom commercial, un second nom ou le début de l'AMM. Les noms qui commencent par la
	 * saisie passent devant ceux qui la contiennent.
	 */
	public static function search(string $query, bool $organic = FALSE, int $limit = 10): \Collection {

		$query = trim($query);

		if($query === '') {
			return new \Collection();
		}

		return self::getCatalog($query, $organic, $limit);

	}

	/**
	 * Comme `search()`, mais une saisie vide rend le début du catalogue par ordre alphabétique.
	 */
	public static function getCatalog(string $query, bool $organic, int $limit, array $ammsExclude = []): \Collection {

		$query = trim($query);
		$starts = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';

		self::applySearch($query, $organic, $ammsExclude);

		return PhytoProduct::model()
			->select(PhytoProduct::getSelection())
			->sort(new \Sql('status = '.PhytoProduct::model()->format(PhytoProduct::WITHDRAWN).' ASC, name LIKE '.PhytoProduct::model()->format($starts).' DESC, name ASC'))
			->getCollection(0, $limit);

	}

	public static function countCatalog(string $query, bool $organic, array $ammsExclude = []): int {

		self::applySearch(trim($query), $organic, $ammsExclude);

		return PhytoProduct::model()->count();

	}

	private static function applySearch(string $query, bool $organic, array $ammsExclude): void {

		PhytoProduct::model()
			->whereAmm('NOT IN', $ammsExclude, if: $ammsExclude !== [])
			->whereOrganic(TRUE, if: $organic);

		if($query === '') {
			return;
		}

		PhytoProduct::model()->or(...self::getNameConditions($query));

	}

	/**
	 * Les conditions qui trouvent un produit par son nom, un second nom ou le début de son AMM, à
	 * réunir par `or()`. La recherche des produits autorisés de la Boîte à outils y ajoute la
	 * substance.
	 */
	public static function getNameConditions(string $query): array {

		$like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
		$starts = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';

		$conditions = [
			fn() => $this->whereName('LIKE', $like),
			// JSON_SEARCH compare en binaire : sans LOWER(), « sluxx » ne trouve pas « SLUXX PRO ».
			fn() => $this->where('JSON_SEARCH(LOWER(otherNames), "one", LOWER('.PhytoProduct::model()->format($like).')) IS NOT NULL'),
		];

		// « Essenciel » doit trouver « ESSEN'CIEL ». Les guillemets restent dans otherNames pour qu'une saisie ne puisse pas enjamber deux noms.
		$compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $query);

		if($compact !== '') {
			$likeCompact = PhytoProduct::model()->format('%'.mb_strtolower($compact).'%');
			$conditions[] = fn() => $this->where('REGEXP_REPLACE(name, "[^[:alnum:]]", "") LIKE '.$likeCompact);
			$conditions[] = fn() => $this->where('REGEXP_REPLACE(LOWER(otherNames), "[^[:alnum:]\"]", "") LIKE '.$likeCompact);
		}

		// La colonne `amm` est en ascii : MySQL refuse d'y comparer une saisie accentuée.
		if(mb_check_encoding($query, 'ASCII')) {
			$conditions[] = fn() => $this->whereAmm('LIKE', $starts);
		}

		return $conditions;

	}

	/**
	 * Recharge le catalogue depuis la dernière publication. Un produit garde son id d'une semaine à
	 * l'autre, ses usages sont réécrits avec les espèces de leur culture, et un produit qui n'est plus
	 * utilisable sort du catalogue. Rend les cultures que `PhytoUsageLib::CROPS` et `CROPS_OUTSIDE`
	 * ne déclarent pas : leurs usages restent sans espèce jusqu'à ce que la table les reprenne.
	 */
	public static function reload(): array {

		$files = self::download();

		$products = self::readProducts($files['produits_utf8.csv']);
		[$usages, $usableUntil] = self::readUsages($files['produits_usages_utf8.csv'], $products, \Clock::date());

		// Un produit retiré se garde jusqu'à la plus tardive des fins d'utilisation de ses usages, et
		// sort du catalogue sans usage encore utilisable : l'ANSES n'en publie aucune pour la plupart
		// des produits retirés depuis longtemps.
		foreach($products as $amm => $product) {

			if($product['status'] === PhytoProduct::AUTHORIZED) {
				continue;
			}

			if(array_key_exists($amm, $usableUntil)) {
				$products[$amm]['usableUntil'] = $usableUntil[$amm];
			} else {
				unset($products[$amm]);
			}

		}

		$cPhytoProduct = PhytoProduct::model()
			->select(PhytoProduct::getSelection())
			->getCollection(index: 'amm');

		$plantsByCrop = PhytoUsageLib::getPlantsByCrop();
		$unknown = [];

		PhytoProduct::model()->beginTransaction();

			foreach($products as $amm => $values) {

				if($cPhytoProduct->offsetExists($amm)) {

					$ePhytoProduct = $cPhytoProduct[$amm];
					$ePhytoProduct->merge($values);

					PhytoProduct::model()
						->select(array_keys($values))
						->update($ePhytoProduct);

					PhytoUsage::model()
						->whereProduct($ePhytoProduct)
						->delete();

				} else {

					$ePhytoProduct = new PhytoProduct($values);
					self::create($ePhytoProduct);

				}

				foreach($usages[$amm] ?? [] as $usage) {

					$key = PhytoUsageLib::getCropKey($usage['crop']);

					if(array_key_exists($key, $plantsByCrop) === FALSE and array_key_exists($key, PhytoUsageLib::CROPS_OUTSIDE) === FALSE) {
						$unknown[$usage['crop']] = TRUE;
					}

					PhytoUsageLib::create(new PhytoUsage($usage + [
						'product' => $ePhytoProduct,
						'plants' => $plantsByCrop[$key] ?? [],
					]));

				}

			}

			foreach($cPhytoProduct as $amm => $ePhytoProduct) {

				if(array_key_exists($amm, $products) === FALSE) {
					PhytoUsage::model()
						->whereProduct($ePhytoProduct)
						->delete();
					self::delete($ePhytoProduct);
				}

			}

		PhytoProduct::model()->commit();

		return array_keys($unknown);

	}

	/**
	 * Le zip en UTF-8 de la dernière publication, dont l'adresse change à chaque fois : elle se lit
	 * dans les ressources du jeu de données. Rend le contenu des deux fichiers utiles.
	 */
	private static function download(): array {

		$curl = new \util\CurlLib();

		$dataset = json_decode($curl->exec(self::DATASET, []), TRUE);

		$url = NULL;

		foreach($dataset['resources'] ?? [] as $resource) {
			if(str_ends_with($resource['url'], '-utf8.zip')) {
				$url = $resource['url'];
				break;
			}
		}

		if($url === NULL) {
			throw new \Exception('E-Phy: no UTF-8 archive in dataset');
		}

		// Lu par Phar et non par ZipArchive : l'extension zip n'est pas installée partout, Phar l'est
		// avec PHP. Phar exige un fichier d'extension .zip.
		$tmpFile = tempnam(sys_get_temp_dir(), 'ephy');
		$zipFile = $tmpFile.'.zip';
		file_put_contents($zipFile, $curl->exec($url, []));

		$files = [];

		try {

			foreach(['produits_utf8.csv', 'produits_usages_utf8.csv'] as $name) {

				$content = @file_get_contents('phar://'.$zipFile.'/'.$name);

				if($content === FALSE) {
					throw new \Exception('E-Phy: missing '.$name.' in '.$url);
				}

				$files[$name] = $content;

			}

		} finally {
			unlink($zipFile);
			unlink($tmpFile);
		}

		return $files;

	}

	/**
	 * Les colonnes se lisent par leur intitulé, l'ANSES ayant déjà inséré des colonnes d'une
	 * publication à l'autre.
	 */
	private static function readCsv(string $content): \Generator {

		$handle = fopen('php://memory', 'r+');
		fwrite($handle, $content);
		rewind($handle);

		$header = array_map(fn($name) => trim($name, " \u{FEFF}"), fgetcsv($handle, separator: ';', escape: ''));

		while(($line = fgetcsv($handle, separator: ';', escape: '')) !== FALSE) {

			if(count($line) < count($header)) {
				continue;
			}

			yield array_combine($header, array_slice($line, 0, count($header)));

		}

		fclose($handle);

	}

	/**
	 * Les substances d'un produit sont réunies par leur nom international, que le catalogue écrit
	 * entre parenthèses (« soufre micronisé (Sulphur) 800.0 g/kg »), et nommées par le plus court des
	 * noms français qu'il leur donne sur l'ensemble du catalogue : « soufre » réunit « soufre
	 * micronisé » et « soufre triture ». À longueur égale, le nom le plus fréquent l'emporte, ce qui
	 * préfère « tébuconazole » au « Tebuconazole » que quelques fiches recopient en anglais.
	 */
	private static function readProducts(string $content): array {

		$products = [];
		$names = [];

		foreach(self::readCsv($content) as $line) {

			$status = match($line['Etat d’autorisation']) {
				'AUTORISE' => PhytoProduct::AUTHORIZED,
				'RETIRE' => PhytoProduct::WITHDRAWN,
				default => NULL
			};

			if(
				$status === NULL or
				in_array($line['type produit'], self::TYPES) === FALSE
			) {
				continue;
			}

			$otherNames = array_values(array_filter(array_map('trim', explode('|', $line['seconds noms commerciaux']))));

			$substances = [];

			foreach(self::readSubstances($line['Substances actives']) as [$name, $international]) {
				$substances[$international] = $international;
				$names[$international][$name] = ($names[$international][$name] ?? 0) + 1;
			}

			$products[$line['numero AMM']] = [
				'amm' => $line['numero AMM'],
				'name' => trim($line['nom produit']),
				'otherNames' => $otherNames,
				'functions' => ($line['fonctions'] === '') ? NULL : implode(', ', array_unique(explode(' | ', $line['fonctions']))),
				'formulation' => ($line['formulations'] === '') ? NULL : $line['formulations'],
				'organic' => str_contains($line['mentions autorisees'], self::ORGANIC),
				'amateur' => ($line['gamme usage'] === self::AMATEUR),
				'substances' => array_values($substances),
				'status' => $status,
				'usableUntil' => NULL,
			];

		}

		$labels = [];

		foreach($names as $international => $counts) {
			uksort($counts, fn($a, $b) => (mb_strlen($a) <=> mb_strlen($b)) ?: ($counts[$b] <=> $counts[$a]) ?: strcmp($a, $b));
			$labels[$international] = array_key_first($counts);
		}

		foreach($products as $amm => $product) {
			$products[$amm]['substances'] = array_map(fn($international) => [
				'international' => $international,
				'name' => $labels[$international],
			], $product['substances']);
		}

		return $products;

	}

	/**
	 * Une substance par morceau de la colonne, `[nom français, nom international]`, sans sa
	 * concentration. Le nom international est la dernière parenthèse du morceau, qui peut en porter
	 * une (« Straight Chain Lepidopteran Pheromones (SCLPs) - Acetates »), et le nom français aussi
	 * (« acides gras C7 à C18 et C18 insaturé (sels de potassium) »). Un morceau sans parenthèse vaut
	 * pour les deux.
	 */
	public static function readSubstances(string $column): array {

		$substances = [];

		foreach(explode('|', $column) as $part) {

			$part = trim($part);

			if($part === '') {
				continue;
			}

			if(preg_match('/^(.+)\s\(([^()]*(?:\([^()]*\)[^()]*)*)\)(?:\s+[\d.,]+\s+\S.*)?$/u', $part, $match)) {
				$substances[] = [trim($match[1]), trim($match[2])];
			} else {
				$name = trim(preg_replace('/\s+[\d.,]+\s+\S+$/u', '', $part));
				$substances[] = [$name, $name];
			}

		}

		return $substances;

	}

	/**
	 * Les usages d'un produit autorisé sont ceux qui le sont encore. Ceux d'un produit retiré sont tous
	 * en « Retrait » : n'en restent que ceux dont la date de fin d'utilisation, au format JJ/MM/AAAA,
	 * n'est pas passée. Rend aussi, par AMM d'un produit retiré, la plus tardive de ces dates.
	 */
	private static function readUsages(string $content, array $products, string $today): array {

		$usages = [];
		$usableUntil = [];

		foreach(self::readCsv($content) as $line) {

			$amm = $line['numero AMM'];

			if(array_key_exists($amm, $products) === FALSE) {
				continue;
			}

			if($products[$amm]['status'] === PhytoProduct::AUTHORIZED) {

				if(str_starts_with($line['etat usage'], 'Autoris') === FALSE) {
					continue;
				}

			} else {

				$date = \DateTime::createFromFormat('!d/m/Y', $line['date fin utilisation']);

				if($date === FALSE or $date->format('Y-m-d') < $today) {
					continue;
				}

				$usableUntil[$amm] = max($usableUntil[$amm] ?? '', $date->format('Y-m-d'));

			}

			$parts = explode('*', $line['identifiant usage']);

			$usages[$amm][] = [
				'crop' => $parts[0],
				'treatment' => $parts[1] ?? NULL,
				'target' => $parts[2] ?? NULL,
				'dose' => ($line['dose retenue'] === '') ? NULL : (float)$line['dose retenue'],
				'doseUnit' => ($line['dose retenue unite'] === '') ? NULL : $line['dose retenue unite'],
				'harvestDelay' => ($line['delai avant recolte jour'] === '') ? NULL : (int)$line['delai avant recolte jour'],
			];

		}

		return [$usages, $usableUntil];

	}

}
?>
