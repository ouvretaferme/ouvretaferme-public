<?php
namespace aid;

/**
 * La reprise des fiches de recherche (base L0 du chantier subventions : `<zone>.aides.jsonl`,
 * `organismes.json`, `tags.jsonl`, `tags.md`) dans le catalogue. Chaque ligne passe par build(),
 * comme une saisie de l'admin, et chaque refus est compté sous le nom de la règle qui l'a levé :
 * c'est la mesure qui valide ou amende le modèle.
 *
 * Elle se rejoue. Une aide se retrouve par ses alias (les identifiants de recherche), un organisme
 * par son nom, un tag par son fqn. Ce qui existe est mis à jour, et ce qui décrit une aide
 * (périmètre, éditions, sources, rôles, tags) est remplacé.
 */
class ImportLib {

	/**
	 * Les vrais doublons entre zones (L0/doublons.md, « Vrais doublons ») : la même aide saisie par deux
	 * agents. La clé est l'identifiant gardé, le plus général (règle 4), puis ceux qu'elle absorbe.
	 * L'échelon ne s'y lit pas : celui d'une agence de l'eau vaut pour toutes ses aides (getLevel()).
	 */
	const DUPLICATES = [
		'28-pnr-normandie-maine-plantation-haies' => ['52-pnr-normandie-maine-plantation'],
		'national-aelb-accompagnement-agriculteurs' => ['52-aelb-agr1-accompagnement'],
		'national-aelb-materiel-agroecologique' => ['52-aelb-agr5-materiel'],
		'national-aelb-mise-aux-normes-elevages' => ['52-aelb-agr5-elevages'],
		'national-aelb-retenues-substitution' => ['52-aelb-qua4-retenues'],
	];

	const LEVELS = [
		'national' => Aid::NATIONAL,
		'pac' => Aid::PAC,
		'regional' => Aid::REGION,
		'departemental' => Aid::DEPARTMENT,
		'intercommunal' => Aid::INTERCOMMUNAL,
		'autre' => Aid::OTHER,
	];

	const CALENDARS = [
		'permanent' => Aid::PERMANENT,
		'appel-a-projets' => Aid::CALL,
		'campagne-annuelle' => Aid::CAMPAIGN,
		// Une aide fermée est un appel dont la seule édition connue est close : elle reste au
		// catalogue, cachée, jusqu'à la relecture annuelle (maintenance.md, « Aide fermée »).
		'ferme' => Aid::CALL,
	];

	const LABELS = [
		'bio' => Aid::AB,
		'aop' => Aid::AOP,
		'igp' => Aid::IGP,
		'label-rouge' => Aid::LABEL_ROUGE,
		'hve' => Aid::HVE,
		'stg' => Aid::STG,
	];

	/**
	 * Les productions de la recherche vers les métiers de structure\Production. Une production sans
	 * métier va à `other` et jamais à « toutes » : une aide aux équidés ne doit pas sortir chez un
	 * maraîcher. « toutes » rend une liste vide, qui vaut toutes les productions.
	 */
	const PRODUCTIONS = [
		'toutes' => [],
		'maraichage' => ['maraichage'],
		'cultures-vivrieres' => ['maraichage', 'legume'],
		'arboriculture' => ['arboriculture'],
		'oleiculture' => ['arboriculture'],
		'trufficulture' => ['arboriculture'],
		'banane' => ['arboriculture'],
		'viticulture' => ['vigne'],
		'grandes-cultures' => ['grande-culture'],
		'legumineuses' => ['grande-culture'],
		'petits-fruits' => ['petit-fruit'],
		'horticulture' => ['horticulture'],
		'pepiniere' => ['horticulture'],
		'ppam' => ['ppam'],
		'plantes-aromatiques' => ['ppam'],
		'semences' => ['semence'],
		'champignons' => ['champignon'],
		'elevage-bovin' => ['bovin'],
		'elevage-laitier' => ['bovin'],
		'elevage-herbager' => ['bovin', 'ovin', 'caprin'],
		'elevage-ovin' => ['ovin'],
		'elevage-caprin' => ['caprin'],
		'elevage-porcin' => ['porcin'],
		'porcin' => ['porcin'],
		'volailles' => ['volaille'],
		'volaille' => ['volaille'],
		'aviculture' => ['volaille'],
		'palmipedes' => ['volaille'],
		'apiculture' => ['apiculture'],
		'elevage' => ['bovin', 'caprin', 'ovin', 'porcin', 'volaille'],
		'polyculture-elevage' => ['grande-culture', 'bovin', 'caprin', 'ovin', 'porcin', 'volaille'],
		'transformation-hors-vin' => ['transformation'],
	];

	const ORGANIZATION_TYPES = [
		'region' => Organization::REGION,
		'departement' => Organization::DEPARTMENT,
		'etat' => Organization::STATE,
		'operateur-etat' => Organization::OPERATOR,
		'banque-publique' => Organization::OPERATOR,
		'agence-eau' => Organization::WATER_AGENCY,
		'union-europeenne' => Organization::EUROPE,
		'epci' => Organization::EPCI,
		'chambre-agriculture' => Organization::CHAMBER,
		'association' => Organization::ASSOCIATION,
		'fondation' => Organization::ASSOCIATION,
		'interprofession' => Organization::ASSOCIATION,
		'commune' => Organization::OTHER,
		'entreprise' => Organization::OTHER,
		'autre' => Organization::OTHER,
	];

	/**
	 * Les agences et offices de l'eau, vers leurs circonscriptions de bassin (AidSetting::BASINS). Une
	 * agence intervient sur un bassin, qu'aucune collectivité ni aucun échelon d'aide ne découpe : seul
	 * le bassin peut borner le périmètre de son programme.
	 */
	const WATER_AGENCIES = [
		'Agence de l\'eau Artois-Picardie' => ['01'],
		'Agence de l\'eau Rhin-Meuse' => ['02'],
		'Agence de l\'eau Seine-Normandie' => ['03'],
		'Agence de l\'eau Loire-Bretagne' => ['04'],
		'Agence de l\'eau Adour-Garonne' => ['05'],
		'Agence de l\'eau Rhône Méditerranée Corse' => ['06', '12'],
		'Office de l\'eau Guadeloupe' => ['07'],
		'Office de l\'eau Réunion' => ['10'],
	];

	const ORGANIZATION_LEVELS = [
		'europeen' => Organization::EUROPE,
		'national' => Organization::NATIONAL,
		'regional' => Organization::REGION,
		'departemental' => Organization::DEPARTMENT,
		'intercommunal' => Organization::INTERCOMMUNAL,
		'local' => Organization::LOCAL,
	];

	/**
	 * Les cofinanceurs que la recherche nomme par un sigle, vers l'organisme qui les porte. Les autres
	 * noms génériques (« Départements », « EPCI engagés ») ne désignent aucun organisme : ils sont
	 * comptés, et restent dans le texte des conditions.
	 */
	const COFUNDERS = [
		'FEADER' => 'Union européenne (FEADER, 2e pilier de la PAC)',
		'FEAGA' => 'Union européenne (FEAGA, 1er pilier de la PAC)',
		'ODEADOM' => 'Office de développement de l\'économie agricole d\'outre-mer (ODEADOM)',
		'ODEADOM (COT)' => 'Office de développement de l\'économie agricole d\'outre-mer (ODEADOM)',
		'ODEADOM (crédits territorialisés du ministère des Outre-mer)' => 'Office de développement de l\'économie agricole d\'outre-mer (ODEADOM)',
		'FNGRA' => 'Fonds national de gestion des risques en agriculture (FNGRA)',
		'Ministère de l\'Agriculture' => 'Ministère de l\'Agriculture, de l\'Agro-alimentaire et de la Souveraineté alimentaire',
		'Ministère de l\'Agriculture (P149)' => 'Ministère de l\'Agriculture, de l\'Agro-alimentaire et de la Souveraineté alimentaire',
	];

	const PROJECTS = [
		'Investir dans du matériel ou un bâtiment' => Tag::INVEST,
		'S\'installer' => Tag::INSTALL,
		'Se faire conseiller, se former' => Tag::ADVICE,
		'Traverser une crise, soulager la trésorerie' => Tag::CRISIS,
		'Irriguer, stocker l\'eau' => Tag::IRRIGATION,
		'Réduire la facture d\'énergie' => Tag::ENERGY,
		'Planter des haies, protéger l\'environnement' => Tag::ENVIRONMENT,
		'Passer ou rester en bio' => Tag::ORGANIC,
		'Transformer, vendre en direct' => Tag::SELL,
		'Accéder au foncier' => Tag::LAND,
		'Embaucher, se faire remplacer' => Tag::EMPLOYMENT,
	];

	/**
	 * La confiance dans une fiche, lue dans ce que l'agent de recherche en a écrit, de la plus grave à
	 * la plus légère : une contradiction entre sources, puis un contenu relevé sur un relais, puis une
	 * valeur que la source n'a pas laissé lire. Sans aucun de ces mots, la source officielle a été lue.
	 */
	const VERIFICATIONS = [
		Aid::CONFLICT => '/contradi|diverg|discordan/iu',
		Aid::RELAY => '/\(relais\)|qu.un relais|sur un relais|selon f[èe]ve|d.apr[èe]s f[èe]ve|relev\w+ sur f[èe]ve/iu',
		Aid::TO_CHECK => '/illisible|inaccessible|captcha|non lu|pas pu|à (re)?vérifier|à confirmer|non confirm|non vérifi/iu',
	];

	/**
	 * Ce que la recherche a écrit à la place d'un taux qu'elle n'a pas trouvé : « non publié », « Non
	 * relevé (règlement PDF) », « Taux non lu sur la fiche ». Les 72 fiches de L0 qui en portent une
	 * commencent toutes ainsi, et aucun taux relevé ne commence par « non ».
	 */
	const RATE_UNKNOWN = '/^(taux )?non\\b/iu';

	/**
	 * Les deux mentions que la relecture de la recherche a laissées dans `particularites`. Une fiche à
	 * confirmer porte un chiffre que sa source officielle ne laisse pas encore relire : elle se publie,
	 * en confiance « à vérifier », pour que la carte dise qu'il reste à le faire confirmer. Une fiche à
	 * vérifier porte un texte qui ne peut pas sortir tel quel : elle attend que l'équipe la réécrive.
	 */
	const MENTION_CONFIRM = 'À confirmer par L4';
	const MENTION_HOLD = 'À vérifier avant toute publication';

	/**
	 * Ce qu'une fiche dit de chaque ligne fille, sur quoi la reprise d'une aide relue mesure son écart
	 * (getDifferences()). Une édition s'y lit par ses dates et son état, les seuls qu'une fiche donne.
	 */
	const CHILDREN = [
		'Area' => ['department'],
		'Edition' => ['startDate', 'endDate', 'state'],
		'Source' => ['url', 'kind'],
		'AidOrganization' => ['organization', 'role'],
		'AidTag' => ['tag'],
	];

	public static function read(string $path): array {

		$aids = [];

		foreach(glob($path.'/*.aides.jsonl') as $file) {

			foreach(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
				$aid = json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR);
				$aids[$aid['id']] = $aid;
			}

		}

		$tags = [];

		foreach(file($path.'/tags.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$tag = json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR);
			$tags[$tag['id']] = $tag['tags'];
		}

		return [
			'aids' => $aids,
			'organizations' => json_decode(file_get_contents($path.'/organismes.json'), TRUE, 512, JSON_THROW_ON_ERROR),
			'taxonomy' => self::readTaxonomy(file_get_contents($path.'/tags.md')),
			'tags' => $tags,
		];

	}

	/**
	 * La taxonomie tient dans le tableau « Taxonomie, par projet » de tags.md : une ligne par projet,
	 * puis chaque tag en « `fqn` Nom (n) », suivi de « aussi « Projet » » quand il en sert deux.
	 */
	private static function readTaxonomy(string $markdown): array {

		$taxonomy = [];

		foreach(explode("\n", $markdown) as $line) {

			$cells = array_map(trim(...), explode('|', $line));

			if(count($cells) !== 5 or array_key_exists($cells[1], self::PROJECTS) === FALSE) {
				continue;
			}

			preg_match_all('/`([a-z0-9-]+)` (.+?) \(\d+(?:, aussi « ([^»]+) »)?\)(?= · |$)/u', $cells[3], $matches, PREG_SET_ORDER);

			foreach($matches as $match) {

				$projects = [self::PROJECTS[$cells[1]]];

				if(($match[3] ?? '') !== '') {
					$projects[] = self::PROJECTS[$match[3]];
				}

				$taxonomy[$match[1]] = [
					'name' => trim($match[2]),
					'projects' => $projects,
				];

			}

		}

		return $taxonomy;

	}

	/**
	 * Les aides après fusion des vrais doublons : l'aide gardée reçoit les alias et l'union des
	 * départements de celles qu'elle absorbe, qui disparaissent de la liste.
	 */
	public static function merge(array $aids): array {

		foreach($aids as $id => $aid) {
			$aids[$id]['aliases'] = [$id];
			$aids[$id]['level'] = self::LEVELS[$aid['echelon']];
		}

		foreach(self::DUPLICATES as $kept => $absorbed) {

			// Une reprise peut porter sur un extrait de la recherche, comme le décor des tests, où manque
			// l'identifiant gardé ou l'un de ceux qu'il absorbe.
			if(array_key_exists($kept, $aids) === FALSE) {
				continue;
			}

			foreach($absorbed as $id) {

				$aids[$kept]['aliases'][] = $id;

				if(array_key_exists($id, $aids) === FALSE) {
					continue;
				}

				$aids[$kept]['departements'] = array_values(array_unique(array_merge($aids[$kept]['departements'], $aids[$id]['departements'])));
				unset($aids[$id]);

			}

		}

		return $aids;

	}

	public static function seed(string $path): array {

		$data = self::read($path);
		$aidsMerged = self::merge($data['aids']);

		$report = [
			'aidsRead' => count($data['aids']),
			'aidsMerged' => count($aidsMerged),
			'organizations' => self::seedOrganizations($data['organizations']),
			'tags' => self::seedTags($data['taxonomy']),
			'created' => 0,
			'updated' => 0,
			// Les aides publiées ou relues, que la reprise ne réécrit pas (Aid::acceptSeedRewrite()), celles
			// dont elle a rapporté un écart, et celles dont la fiche porte la mention à vérifier.
			'kept' => 0,
			'differences' => 0,
			'heldKept' => 0,
			// Les aides archivées, où la reprise n'écrit rien.
			'archivedSkipped' => 0,
			'refused' => [],
			'published' => 0,
			// Les aides créées sous un identifiant qu'une autre aide absorbe depuis : archivées, ou laissées
			// à l'équipe quand la reprise ne la réécrit pas.
			'absorbedArchived' => [],
			'absorbedKept' => [],
			'verification' => [],
			'productionsOrphan' => [],
			'fundersUnknown' => [],
			'cofundersUnknown' => [],
			'beneficiariesUnknown' => [],
			'areasMissing' => [],
			'editionsUndated' => 0,
			'permanentDated' => [],
			'tagsUnknown' => [],
			'aidsUntagged' => 0,
			'labels' => ['required' => [], 'bonus' => []],
		];

		$organizations = self::getOrganizationNames($data['organizations']);

		// Une agence de l'eau absente de WATER_AGENCIES verrait ses aides nationales valoir partout.
		$report['waterAgenciesUnknown'] = array_values(array_diff(
			array_column(array_filter($data['organizations'], fn(array $organization) => $organization['type'] === 'agence-eau'), 'organisme'),
			array_keys(self::WATER_AGENCIES)
		));

		// Lus une fois pour tout le semis, après leur reprise : chaque aide y retrouve son organisme et ses
		// tags sans requête. Un organisme se lit par son nom en base, qui se compare sans casse ni accent
		// (@collate(general)) : deux noms de la recherche que seule la casse distingue y sont un seul
		// organisme, qu'une clé PHP séparerait.
		$cOrganization = new \Collection();

		foreach(array_unique($organizations) as $name) {

			$eOrganization = OrganizationLib::getByName($name);

			if($eOrganization->notEmpty()) {
				$cOrganization[$name] = $eOrganization;
			}

		}

		$cTag = Tag::model()
			->select(Tag::getSelection())
			->getCollection(NULL, NULL, 'fqn');

		foreach($aidsMerged as $id => $aid) {
			self::seedAid($id, $aid, $organizations, $cOrganization, $cTag, $data['tags'][$id] ?? [], $report);
		}

		self::archiveAbsorbed($aidsMerged, $report);

		$report['labels'] = array_map(fn(array $ids) => count($ids), $report['labels']);
		$report['parentCandidates'] = self::getParentCandidates($aidsMerged);

		return $report;

	}

	/**
	 * Chaque nom et chaque variante d'un organisme, vers son nom retenu.
	 */
	private static function getOrganizationNames(array $organizations): array {

		$names = [];

		foreach($organizations as $organization) {

			foreach(array_merge([$organization['organisme']], $organization['variantes']) as $name) {
				$names[$name] = $organization['organisme'];
			}

		}

		// Un sigle suit l'organisme qu'il désigne jusqu'à son nom retenu, que COFUNDERS cite parfois sous
		// une variante depuis qu'un organisme national a pris son nom court. Un sigle dont l'organisme
		// manque au fichier reste inconnu, et ses fiches le comptent (getRoles()).
		foreach(self::COFUNDERS as $acronym => $name) {

			if(array_key_exists($name, $names)) {
				$names[$acronym] = $names[$name];
			}

		}

		return $names;

	}

	private static function seedOrganizations(array $organizations): array {

		$report = ['created' => 0, 'updated' => 0, 'renamed' => [], 'refused' => [], 'typeOther' => 0];

		foreach($organizations as $organization) {

			$type = self::ORGANIZATION_TYPES[$organization['type']];

			// La recherche range les parcs et les GAL en « autre », leur nom les dit.
			if($type === Organization::OTHER) {

				if(preg_match('/^Parc naturel/u', $organization['organisme'])) {
					$type = Organization::PARK;
				} else if(preg_match('/^GAL |groupe d.action locale/iu', $organization['organisme'])) {
					$type = Organization::GAL;
				}

			}

			if($type === Organization::OTHER) {
				$report['typeOther']++;
			}

			$input = [
				'name' => $organization['organisme'],
				'type' => $type,
				'level' => self::ORGANIZATION_LEVELS[$organization['echelon']],
				'url' => $organization['url_aides'][0] ?? NULL,
				'grants' => $organization['accorde'],
			];

			// Un organisme qui a changé de nom dans la recherche garde son ancien nom en variante : la ligne
			// se retrouve par lui, et se renomme au lieu d'en créer une seconde.
			foreach(array_merge([$organization['organisme']], $organization['variantes']) as $name) {

				$eOrganization = OrganizationLib::getByName($name);

				if($eOrganization->notEmpty()) {
					break;
				}

			}

			$isNew = $eOrganization->empty();

			if($isNew === FALSE and $eOrganization['name'] !== $organization['organisme']) {
				$report['renamed'][$eOrganization['name']] = $organization['organisme'];
			}

			$fw = new \FailWatch();

			$eOrganization->build($isNew ? OrganizationLib::getPropertiesCreate() : OrganizationLib::getPropertiesUpdate(), $input);

			if(self::validate($fw, $organization['organisme'], $report['refused']) === FALSE) {
				continue;
			}

			if($isNew) {
				OrganizationLib::create($eOrganization);
				$report['created']++;
			} else {
				OrganizationLib::update($eOrganization, OrganizationLib::getPropertiesUpdate());
				$report['updated']++;
			}

		}

		return $report;

	}

	private static function seedTags(array $taxonomy): array {

		$report = ['created' => 0, 'updated' => 0, 'refused' => []];

		foreach($taxonomy as $fqn => $tag) {

			$input = [
				'fqn' => $fqn,
				'name' => $tag['name'],
				'projects' => $tag['projects'],
			];

			$eTag = TagLib::getByFqn($fqn);
			$isNew = $eTag->empty();

			$fw = new \FailWatch();

			$eTag->build($isNew ? TagLib::getPropertiesCreate() : TagLib::getPropertiesUpdate(), $input);

			if(self::validate($fw, $fqn, $report['refused']) === FALSE) {
				continue;
			}

			if($isNew) {
				TagLib::create($eTag);
				$report['created']++;
			} else {
				TagLib::update($eTag, TagLib::getPropertiesUpdate());
				$report['updated']++;
			}

		}

		return $report;

	}

	private static function seedAid(string $id, array $aid, array $organizations, \Collection $cOrganization, \Collection $cTag, array $tags, array &$report): void {

		// Un organisme absent du fichier, ou que sa reprise a refusé, laisserait l'aide sans qui l'accorde.
		if(
			array_key_exists($aid['organisme'], $organizations) === FALSE or
			$cOrganization->offsetExists($organizations[$aid['organisme']]) === FALSE
		) {
			$report['fundersUnknown'][$aid['organisme']][] = $id;
			return;
		}

		$organization = $organizations[$aid['organisme']];

		$verification = self::getVerification($aid);

		$labels = self::getLabels($aid, $report);

		$input = [
			'name' => $aid['nom'],
			'summary' => NULL,
			'conditions' => self::getConditions($aid),
			'organization' => $cOrganization[$organization],
			'level' => self::getLevel($aid, $organization),
			'type' => self::getType($aid),
			'calendar' => self::CALENDARS[$aid['calendrier']],
			...self::getRate($aid['taux'], $aid['particularites']),
			'ratePercentMax' => $aid['taux_pct_max'],
			'amountMax' => NULL,
			'amountUnit' => NULL,
			'floor' => $aid['plancher_eur'],
			'ceiling' => $aid['plafond_eur'],
			'boundBase' => NULL,
			'boundPeriod' => NULL,
			'productions' => self::getProductions($aid, $report),
			// Aucun label s'écrit NULL, comme le rend un groupe de cases vide au panneau de l'équipe.
			'labels' => $labels['required'] ?: NULL,
			'labelsBonus' => $labels['bonus'] ?: NULL,
			'beneficiaries' => self::getBeneficiaries($aid, $report),
			'parent' => NULL,
			'keywords' => NULL,
			'verification' => $verification,
			'checkedAt' => $aid['verifie_le'],
		];

		// Une phrase qui ne nomme aucun demandeur reconnu ne prouve pas que l'aide passe par un tiers :
		// elle reste directe, sans mention.
		$input['direct'] = (
			$input['beneficiaries'] === [] or
			array_intersect($input['beneficiaries'], [Aid::FARM, Aid::YOUNG_FARMER, Aid::NEW_FARMER, Aid::LANDOWNER]) !== []
		);

		$eAid = AidLib::getByAlias($id);
		$isNew = $eAid->empty();

		// Une aide archivée ne se relit plus (Aid::getReviewReason()) : la reprise n'y écrit rien, pas même
		// un écart.
		if($isNew === FALSE and $eAid['status'] === Aid::ARCHIVED) {
			$report['archivedSkipped']++;
			return;
		}

		// Une aide publiée, ou relue par l'équipe, n'est pas réécrite : la reprise mesure ce que la fiche
		// dirait d'autre sur une aide construite à part (seedCompared()).
		$isCompared = ($isNew === FALSE and $eAid->acceptSeedRewrite() === FALSE);

		$isHeld = str_contains($aid['particularites'], self::MENTION_HOLD);

		// Tout se valide avant d'écrire : une fiche refusée ne touche pas à la base, et le semis
		// n'annule jamais une transaction qu'un appelant aurait ouverte.
		$fw = new \FailWatch();

		if($isCompared) {
			$eAidSeed = new Aid(['id' => $eAid['id']]);
			$eAidSeed->build(AidLib::getPropertiesSeed(), $input);
		} else {
			$eAid->build($isNew ? AidLib::getPropertiesCreate() : AidLib::getPropertiesUpdate(), $input);
		}

		if(self::validate($fw, $id, $report['refused']) === FALSE) {
			return;
		}

		$children = array_fill_keys(array_keys(self::CHILDREN), []);

		foreach([
			'Area' => self::getAreas($aid, $organization, $report),
			'Edition' => self::getEditions($aid, $report),
			'Source' => self::getSources($aid, $verification),
			'AidOrganization' => self::getRoles($aid, $organization, $organizations, $cOrganization, $report),
			'AidTag' => self::getTags($id, $tags, $cTag, $report),
		] as $module => $inputs) {

			foreach($inputs as $inputChild) {

				$element = self::buildChild($module, $inputChild, $id, $report['refused']);

				if($element->empty()) {
					return;
				}

				$children[$module][] = $element;

			}

		}

		if($isCompared) {
			self::seedCompared($eAid, $eAidSeed, $input, $children, $aid['aliases'], $isHeld, $report);
			return;
		}

		// Les alias ne se saisissent pas : ils disent d'où vient l'aide, et seule une reprise les pose.
		$eAid['aliases'] = $aid['aliases'];

		// Un taux rendu par la fiche dément la case que l'équipe aurait cochée, qu'aucune fiche ne porte.
		if($eAid['rate'] !== NULL) {
			$eAid['rateUnpublished'] = FALSE;
		}

		Aid::model()->beginTransaction();

			if($isNew) {
				AidLib::create($eAid);
			} else {
				AidLib::update($eAid, array_merge(AidLib::getPropertiesUpdate(), ['rateUnpublished', 'aliases']));
				self::deleteChildren($eAid);
			}

			SourceLib::synchronize($eAid, $children['Source']);

			foreach(array_diff_key($children, ['Source' => TRUE]) as $elements) {

				foreach($elements as $element) {

					$element['aid'] = $eAid;

					$lib = $element::class.'Lib';
					$lib::create($element);

				}

			}

			// Les sources se relisent en base : celles qui viennent d'être créées n'y prennent leur état
			// que par le défaut du schéma.
			$eAid['cSource'] = Source::model()
				->select(['kind', 'status'])
				->whereAid($eAid)
				->getCollection();

			$isConfirmable = ($verification === Aid::TO_CHECK and str_contains($aid['particularites'], self::MENTION_CONFIRM));

			// Seule une aide lue sur sa source officielle, ou dont il ne reste qu'un chiffre à faire
			// confirmer, se publie d'elle-même ; les autres attendent la relecture de l'équipe. La reprise ne
			// réécrit qu'un brouillon et ne dépublie jamais : une aide publiée dont la fiche perd son taux,
			// sa source officielle ou porte la mention à vérifier reçoit un écart (seedCompared()).
			if(
				$isHeld === FALSE and
				($verification === Aid::PRIMARY or $isConfirmable) and
				$eAid->acceptPublish()
			) {
				AidLib::publish($eAid);
			}

		Aid::model()->commit();

		$report[$isNew ? 'created' : 'updated']++;
		$report['verification'][$verification] = ($report['verification'][$verification] ?? 0) + 1;

		if($eAid['status'] === Aid::PUBLISHED) {
			$report['published']++;
		}

	}

	/**
	 * Une aide publiée ou relue garde tout ce que l'équipe y a écrit, propriétés, filles et état de
	 * publication : seuls ses alias suivent la fiche, sans quoi getByAlias() perdrait l'aide d'une fiche
	 * fusionnée et la recréerait. Ce que la fiche dirait d'autre devient son écart (`seedDifferences`),
	 * que la file de revue montre, sauf quand l'équipe l'a déjà gardé tel quel (`seedKept`). La mention à
	 * vérifier en est un à elle seule : déjà semée dans `notes`, elle n'y ferait aucune différence. Une
	 * reprise rejouée sur la même fiche n'écrit rien (cadrage de L5, R6) : l'écart se compare par son
	 * empreinte, la colonne JSON ne rendant pas un montant sous le type que la fiche lui donne.
	 */
	private static function seedCompared(Aid $eAid, Aid $eAidSeed, array $input, array $children, array $aliases, bool $isHeld, array &$report): void {

		$differences = self::getDifferences($eAid, $eAidSeed, $input, $children, $isHeld);

		if(
			($differences['properties'] === [] and $differences['children'] === [] and $differences['held'] === FALSE) or
			AidLib::getSeedFingerprint($differences) === $eAid['seedKept']
		) {
			$seedDifferences = NULL;
		} else {
			$seedDifferences = $differences;
		}

		$isSame = (
			$eAid['aliases'] === $aliases and
			(
				($seedDifferences === NULL and $eAid['seedDifferences'] === NULL) or
				($seedDifferences !== NULL and $eAid['seedDifferences'] !== NULL and AidLib::getSeedFingerprint($seedDifferences) === AidLib::getSeedFingerprint($eAid['seedDifferences']))
			)
		);

		if($isSame === FALSE) {
			$eAid['aliases'] = $aliases;
			$eAid['seedDifferences'] = $seedDifferences;
			AidLib::update($eAid, ['aliases', 'seedDifferences']);
		}

		$report['kept']++;

		if($eAid['seedDifferences'] !== NULL) {
			$report['differences']++;
		}

		if($isHeld) {
			$report['heldKept']++;
		}

		if($eAid['status'] === Aid::PUBLISHED) {
			$report['published']++;
		}

	}

	/**
	 * Une aide créée sous un identifiant que DUPLICATES fait absorber après coup reste en base à côté de
	 * l'aide gardée : le catalogue montrerait deux fois la même aide. Elle est archivée. Une aide publiée
	 * ou relue reste celle de l'équipe (Aid::acceptSeedRewrite()), qui l'archive elle-même, et la reprise
	 * la nomme.
	 */
	private static function archiveAbsorbed(array $aidsMerged, array &$report): void {

		foreach(self::DUPLICATES as $kept => $absorbed) {

			if(
				array_key_exists($kept, $aidsMerged) === FALSE or
				AidLib::getByAlias($kept)->empty()
			) {
				continue;
			}

			foreach($absorbed as $id) {

				$cAid = Aid::model()
					->select(['id', 'status', 'checkedBy'])
					->where(new \Sql('JSON_CONTAINS(aliases, '.Aid::model()->format(json_encode($id)).')'))
					->where(new \Sql('NOT JSON_CONTAINS(aliases, '.Aid::model()->format(json_encode($kept)).')'))
					->whereStatus('!=', Aid::ARCHIVED)
					->getCollection();

				foreach($cAid as $eAid) {

					if($eAid->acceptSeedRewrite()) {
						AidLib::archive($eAid);
						$report['absorbedArchived'][] = $id;
					} else {
						$report['absorbedKept'][] = $id;
					}

				}

			}

		}

	}

	/**
	 * L'écart entre une aide relue et sa fiche : les propriétés que l'équipe et la fiche écrivent toutes
	 * deux (AidLib::getPropertiesSeed()), avec la valeur d'entrée de la fiche, que « Reprendre la fiche »
	 * repasse par build() ; puis les lignes filles que la fiche ajouterait ou retirerait. La forme ne
	 * varie pas, pour que ses lecteurs la lisent sans garde : `properties` et `children` toujours posés,
	 * et chaque module de `children` qui change avec `added` et `removed`.
	 */
	private static function getDifferences(Aid $eAid, Aid $eAidSeed, array $input, array $children, bool $isHeld): array {

		$differences = [
			'properties' => [],
			'children' => [],
			'held' => $isHeld,
		];

		foreach(AidLib::getPropertiesSeed() as $property) {

			if(self::getValueCompared($eAidSeed[$property]) !== self::getValueCompared($eAid[$property])) {
				$differences['properties'][$property] = ($input[$property] instanceof \Element) ? self::getValueCompared($input[$property]) : $input[$property];
			}

		}

		foreach(self::CHILDREN as $module => $properties) {

			$keysSeed = array_map(fn(\Element $element) => self::getChildKey($element, $properties), $children[$module]);
			$keys = self::getChildKeys($eAid, $module);

			$added = array_values(array_filter($keysSeed, fn(array $key) => in_array($key, $keys, TRUE) === FALSE));
			$removed = array_values(array_filter($keys, fn(array $key) => in_array($key, $keysSeed, TRUE) === FALSE));

			if($added !== [] or $removed !== []) {
				$differences['children'][$module] = [
					'added' => $added,
					'removed' => $removed,
				];
			}

		}

		return $differences;

	}

	// Un ensemble se compare sans son ordre, et vide comme absent, un élément par son identifiant, un
	// nombre sans son type : la base rend en décimal ce qu'une fiche écrit en entier.
	private static function getValueCompared(mixed $value): mixed {

		if($value instanceof \Element) {
			return $value->empty() ? NULL : $value['id'];
		}

		if($value instanceof \Set) {
			return ($value->values() === []) ? NULL : $value->values();
		}

		if(is_array($value)) {
			sort($value);
			return $value;
		}

		if(is_int($value)) {
			return (float)$value;
		}

		return $value;

	}

	/**
	 * Les lignes filles d'une aide sous la forme que l'écart leur donne, pour les comparer à celles
	 * d'une fiche, ou recomposer la liste entière de chaque côté (AidLib::fillSeed()). Une source d'une
	 * nature qu'aucune fiche ne porte n'y entre pas : elle ne serait jamais que « retirée » par la fiche,
	 * que la reprise ne retire pas (SourceLib::synchronize()).
	 */
	public static function getChildKeys(Aid $eAid, string $module): array {

		$class = '\\aid\\'.$module;
		$properties = self::CHILDREN[$module];

		$keys = [];

		$mElement = $class::model()
			->select($properties)
			->whereAid($eAid);

		if($module === 'Source') {
			$mElement->whereKind('IN', SourceLib::getKindsSeed());
		}

		foreach($mElement->getCollection() as $element) {
			$keys[] = self::getChildKey($element, $properties);
		}

		return $keys;

	}

	// Une ligne fille par ce que la fiche en dit. Une adresse de source se lit sans sa barre finale,
	// comme SourceLib::existsByUrl().
	private static function getChildKey(\Element $element, array $properties): array {

		$key = [];

		foreach($properties as $property) {
			$key[$property] = self::getValueCompared($element[$property]);
		}

		if(array_key_exists('url', $key)) {
			$key['url'] = rtrim($key['url'], '/');
		}

		return $key;

	}

	/**
	 * Un refus est rangé sous le nom de la règle qui l'a levé, avec les premiers identifiants qu'il a
	 * touchés, pour qu'on puisse relire les fiches.
	 */
	private static function validate(\FailWatch $fw, string $id, array &$refused): bool {

		try {
			$fw->validate();
			return TRUE;
		} catch(\FailAction) {

			foreach($fw->get() as [, $failName]) {
				$refused[$failName][] = $id;
			}

			return FALSE;

		}

	}

	/**
	 * Un élément fils construit et validé, pas encore rattaché à son aide, ou un élément vide quand
	 * build() le refuse : le refus est alors compté.
	 */
	private static function buildChild(string $module, array $input, string $id, array &$refused): \Element {

		$class = '\\aid\\'.$module;
		$lib = $class.'Lib';

		$element = new $class();

		$fw = new \FailWatch();

		$element->build($lib::getPropertiesCreate(), $input);

		if(self::validate($fw, $id, $refused) === FALSE) {
			return new $class();
		}

		return $element;

	}

	/**
	 * Une reprise remplace ce qui décrit l'aide plutôt que de le comparer ligne à ligne : la fiche de
	 * recherche est la seule source, et ce qui en a disparu doit partir. Les sources font exception,
	 * alignées par adresse (SourceLib::synchronize()) pour garder ce que la veille en a relevé.
	 */
	private static function deleteChildren(Aid $eAid): void {

		foreach(['Area', 'Edition', 'AidOrganization', 'AidTag'] as $module) {

			$class = '\\aid\\'.$module;
			$lib = $class.'Lib';

			$elements = $class::model()
				->select('id')
				->whereAid($eAid)
				->getCollection();

			foreach($elements as $element) {
				$lib::delete($element);
			}

		}

	}

	/**
	 * L'échelon de l'aide, celui de sa fiche, sauf pour une agence ou un office de l'eau saisi en
	 * national, en PAC ou hors échelon : il intervient sur un bassin, qu'aucun échelon d'aide ne nomme,
	 * et getAreas() lui donne ce bassin pour périmètre.
	 */
	private static function getLevel(array $aid, string $organization): string {

		if(
			array_key_exists($organization, self::WATER_AGENCIES) and
			in_array($aid['echelon'], ['national', 'pac', 'autre'], TRUE)
		) {
			return Aid::OTHER;
		}

		return $aid['level'];

	}

	private static function getVerification(array $aid): string {

		$text = $aid['particularites'].' '.$aid['conditions'].' '.$aid['taux'];

		foreach(self::VERIFICATIONS as $verification => $pattern) {

			if(preg_match($pattern, $text)) {
				return $verification;
			}

		}

		return Aid::PRIMARY;

	}

	/**
	 * Le texte des conditions, reformulé par la recherche, se complète de ce que le modèle ne
	 * structure pas encore : le bénéficiaire tel que la source le décrit, les bornes d'âge et le
	 * cumul (charte, point 9, « sauf cumul »).
	 */
	private static function getConditions(array $aid): ?string {

		$paragraphs = array_filter([
			$aid['beneficiaires'],
			$aid['conditions'],
			$aid['conditions_age'],
			$aid['cumul'],
		], fn(?string $paragraph) => $paragraph !== NULL and $paragraph !== '');

		if($paragraphs === []) {
			return NULL;
		}

		return implode('', array_map(fn(string $paragraph) => '<p>'.encode($paragraph).'</p>', $paragraphs));

	}

	/**
	 * Le taux et les notes d'une fiche. Une note de relevé n'est pas un taux : la carte la montrerait comme
	 * une valeur. Elle passe en tête des notes, telle que la recherche l'a écrite, pour la relecture de L4,
	 * et l'aide reste sans taux, donc en brouillon (Aid::acceptPublish()).
	 */
	public static function getRate(?string $rate, ?string $notes): array {

		if($rate === NULL or preg_match(self::RATE_UNKNOWN, $rate) === 0) {
			return ['rate' => $rate, 'notes' => $notes];
		}

		return [
			'rate' => NULL,
			'notes' => 'Taux : '.$rate.(($notes === NULL) ? '' : "\n\n".$notes),
		];

	}

	private static function getType(array $aid): string {

		return match($aid['forme']) {
			'subvention', 'prime' => Aid::GRANT,
			'pret' => Aid::LOAN,
			'avance-remboursable' => Aid::ADVANCE,
			'garantie' => Aid::GUARANTEE,
			'credit-impot' => Aid::TAX,
			// Une exonération de la MSA porte sur les cotisations sociales, les autres sur l'impôt.
			'exoneration' => str_starts_with($aid['organisme'], 'MSA') ? Aid::SOCIAL : Aid::TAX,
			// Un accompagnement foncier est un portage ou une mise à disposition de terres.
			'accompagnement' => in_array('foncier', $aid['objet'], TRUE) ? Aid::LAND : Aid::SERVICE,
		};

	}

	private static function getProductions(array $aid, array &$report): array {

		$productions = [];

		foreach($aid['productions'] as $production) {

			if($production === 'toutes') {
				return [];
			}

			if(array_key_exists($production, self::PRODUCTIONS) === FALSE) {
				$report['productionsOrphan'][$production] = ($report['productionsOrphan'][$production] ?? 0) + 1;
				$productions[] = 'other';
				continue;
			}

			$productions = array_merge($productions, self::PRODUCTIONS[$production]);

		}

		return array_values(array_unique($productions));

	}

	/**
	 * La recherche range dans `labels` aussi bien le label qu'une aide exige que celui qui la bonifie
	 * (la DJA porte `bio` pour un supplément). Un label bonifie quand la fiche ouvre aussi l'aide à
	 * tous (`aucun` à côté), ou quand le texte le cite près d'une majoration (« +10 points bio ») ;
	 * il est exigé sinon, et toujours quand le nom de l'aide le porte (« AGRI Bio »).
	 */
	const LABEL_PATTERNS = [
		'bio' => 'biolog|\\bbio\\b|\\bAB\\b',
		'aop' => '\\bAOP\\b|\\bAOC\\b|appellation',
		'igp' => '\\bIGP\\b',
		'label-rouge' => 'label rouge',
		'hve' => '\\bHVE\\b',
		'stg' => '\\bSTG\\b',
	];

	const BONUS_PATTERN = 'major|bonif|suppl[ée]ment|\\+ ?\\d+ ?(%|points?|pts)|bonus';

	private static function getLabels(array $aid, array &$report): array {

		$labels = ['required' => [], 'bonus' => []];

		$isOpen = in_array('aucun', $aid['labels'], TRUE);
		$text = $aid['taux'].' '.$aid['particularites'].' '.$aid['conditions'].' '.$aid['cumul'];

		foreach(array_intersect_key(self::LABELS, array_flip($aid['labels'])) as $label => $bit) {

			$pattern = self::LABEL_PATTERNS[$label];

			$isNamed = (preg_match('/'.$pattern.'/iu', $aid['nom']) === 1);
			$isBonus = (preg_match('/('.self::BONUS_PATTERN.').{0,60}('.$pattern.')|('.$pattern.').{0,60}('.self::BONUS_PATTERN.')/iu', $text) === 1);

			$kind = ($isNamed === FALSE and ($isOpen or $isBonus)) ? 'bonus' : 'required';

			$labels[$kind][] = $bit;
			$report['labels'][$kind][$aid['id']] = TRUE;

		}

		return $labels;

	}

	/**
	 * Le demandeur, lu dans la phrase libre de la recherche. Une aide dont la phrase ne nomme aucun
	 * demandeur reconnu est comptée : elle reste affichée, sans mention.
	 */
	private static function getBeneficiaries(array $aid, array &$report): array {

		$patterns = [
			// Une chambre d'agriculture n'est pas une exploitation, même quand son nom en a le mot.
			Aid::FARM => '/(?<!chambre d.)(?<!chambres d.)agricult|exploit|éleveu|élevages?\b|producteu|paysan|maraîch|vitic|vigneron|arboric|apicult|apicole|horticult|pépiniér|trufficult|piscicult|piscicole|conchylic|aquacult|salicult|planteurs?\b|irrigants?|naisseurs?|cédants?|détenteurs? d.animaux|\bfermiers?\b|écorégime|non-salariés agricoles|GAEC|EARL|entreprises? agricoles?|PME agricoles?/iu',
			Aid::YOUNG_FARMER => '/jeunes? agricult|\bJA\b|\bDJA\b|moins de 40 ans|de 18 à 40 ans/u',
			Aid::NEW_FARMER => '/nouve(l|aux)( exploitant| installé|lle install)|install(é|és|ation) depuis moins|porteurs? de projet|candidats? à l.installation|en cours d.installation|qui s.installe|installants?|premi(ère|ers?) install|parcours à l.installation|jeunes installés|projet d.installation/iu',
			Aid::CUMA => '/\bCUMA\b/u',
			// Sans /i, pour les sigles : OP n'est pas « op » au milieu d'un mot.
			Aid::GROUP => '/\bGIEE\b|[Cc]ollectifs?|[Gg]roupements?|\bGAL\b|[Oo]rganisations? de producteurs|[Cc]oopératives?|\bSICA\b|\bOP\b|[Ss]tructures? collectives?|[Ii]nterprofessions?|\bODG\b|\bOUGC\b|[Gg]roupes? opérationnels?/u',
			Aid::COMMUNITY => '/collectivit|communes?\b|\bEPCI\b|intercommunal|syndicat/iu',
			Aid::ASSOCIATION => '/\bassociati(on|ve)s?\b/iu',
			// Seulement en tête de phrase, là où le propriétaire dépose lui-même : « avec l'accord du propriétaire » nomme un tiers,
			// et le propriétaire d'un chien de protection n'est pas un propriétaire foncier.
			Aid::LANDOWNER => '/^propriétaires?\b(?!.*\bchiens?\b)/iu',
		];

		$beneficiaries = [];

		foreach($patterns as $beneficiary => $pattern) {

			if(preg_match($pattern, $aid['beneficiaires'])) {
				$beneficiaries[] = $beneficiary;
			}

		}

		if($beneficiaries === []) {
			$report['beneficiariesUnknown'][] = $aid['id'];
		}

		return $beneficiaries;

	}

	/**
	 * Le périmètre en départements : ceux que la fiche nomme, sinon ceux de sa région ou le
	 * département d'outre-mer de sa zone. Une aide nationale ou PAC n'en a aucun.
	 *
	 * Le programme d'une agence ou d'un office de l'eau vaut pour tout son bassin : une fiche saisie
	 * en national, en PAC ou hors échelon, ou qui ne nomme aucun département, couvre donc le bassin
	 * entier, en plus des départements que la zone qui l'a relevée y a lus. Une fiche régionale ou
	 * départementale qui nomme ses départements est une déclinaison locale (une MAEC d'aire de
	 * captage) et les garde seuls.
	 */
	private static function getAreas(array $aid, string $organization, array &$report): array {

		$departments = $aid['departements'];

		if(
			array_key_exists($organization, self::WATER_AGENCIES) and
			($departments === [] or in_array($aid['echelon'], ['national', 'pac', 'autre'], TRUE))
		) {

			$departments = array_values(array_unique(array_merge(
				$departments,
				AidSetting::getDepartmentsByBasins(self::WATER_AGENCIES[$organization])
			)));

		} else if($departments === [] and in_array($aid['echelon'], ['national', 'pac'], TRUE) === FALSE) {

			// La zone d'une fiche est la région du fichier qui l'a saisie, ou un département d'outre-mer.
			// Chaque code de région est aussi un code de département (76, Occitanie et Seine-Maritime ;
			// 01, Guadeloupe et Ain) : la région se lit donc avant le département.
			if(array_key_exists($aid['zone'], AidSetting::REGIONS)) {
				$departments = AidSetting::getDepartmentsByRegion($aid['zone']);
			} else if(array_key_exists($aid['zone'], AidSetting::DEPARTMENTS)) {
				$departments = [$aid['zone']];
			} else {
				$report['areasMissing'][] = $aid['id'];
			}

		}

		// Une fiche peut répéter un code, que l'index unique (aid, department) refuserait.
		return array_map(fn(string $department) => ['department' => $department], array_values(array_unique($departments)));

	}

	/**
	 * Une édition par appel ou campagne, datée de ce que la fiche donne. Close si la fiche dit
	 * l'aide fermée ou si sa date de clôture est passée. Une aide permanente n'en porte aucune, et
	 * une date qu'elle porterait est comptée : elle ne se cache pas à son échéance.
	 */
	private static function getEditions(array $aid, array &$report): array {

		if($aid['calendrier'] === 'permanent') {

			if($aid['date_cloture'] === NULL) {
				return [];
			}

			$report['permanentDated'][] = $aid['id'];

		} else if($aid['date_ouverture'] === NULL and $aid['date_cloture'] === NULL) {
			$report['editionsUndated']++;
		}

		$isClosed = (
			$aid['calendrier'] === 'ferme' or
			($aid['date_cloture'] !== NULL and $aid['date_cloture'] < \Clock::date())
		);

		return [[
			'name' => NULL,
			'startDate' => $aid['date_ouverture'],
			'endDate' => $aid['date_cloture'],
			'state' => $isClosed ? Edition::CLOSED : Edition::OPEN,
			'rate' => NULL,
			'ceiling' => NULL,
			'url' => NULL,
			'checkedAt' => $aid['verifie_le'],
		]];

	}

	/**
	 * La page de l'organisme est la source principale, sauf quand la fiche n'a été lue que sur un
	 * relais : la page citée en est alors un.
	 */
	private static function getSources(array $aid, string $verification): array {

		$sources = [[
			'url' => $aid['url_source'],
			'kind' => match($verification) {
				Aid::RELAY => Source::RELAY,
				Aid::PRIMARY, Aid::TO_CHECK, Aid::CONFLICT => Source::PRIMARY,
			},
		]];

		if($aid['url_agregateur'] !== '' and $aid['url_agregateur'] !== NULL) {
			$sources[] = [
				'url' => $aid['url_agregateur'],
				'kind' => Source::AGGREGATOR,
			];
		}

		return $sources;

	}

	private static function getRoles(array $aid, string $organization, array $organizations, \Collection $cOrganization, array &$report): array {

		$eOrganizationFunder = $cOrganization[$organization];

		$roles = [[
			'organization' => $eOrganizationFunder,
			'role' => AidOrganization::FUNDER,
		]];

		// Le doublon se juge sur l'organisme résolu, pas sur son nom : Organization.name se compare sans
		// casse ni accent (@collate(general)), donc « MSA » et « Msa » sont un seul organisme.
		$organizationIds = [$eOrganizationFunder['id']];

		foreach($aid['cofinanceurs'] as $cofunder) {

			if(
				array_key_exists($cofunder, $organizations) === FALSE or
				$cOrganization->offsetExists($organizations[$cofunder]) === FALSE
			) {
				$report['cofundersUnknown'][$cofunder] = ($report['cofundersUnknown'][$cofunder] ?? 0) + 1;
				continue;
			}

			$eOrganization = $cOrganization[$organizations[$cofunder]];

			if(in_array($eOrganization['id'], $organizationIds, TRUE)) {
				continue;
			}

			$organizationIds[] = $eOrganization['id'];

			$roles[] = [
				'organization' => $eOrganization,
				'role' => AidOrganization::COFUNDER,
			];

		}

		return $roles;

	}

	private static function getTags(string $id, array $tags, \Collection $cTag, array &$report): array {

		if($tags === []) {
			$report['aidsUntagged']++;
		}

		$inputs = [];

		// Une fiche peut répéter un tag, que l'index unique (aid, tag) refuserait.
		foreach(array_unique($tags) as $fqn) {

			if($cTag->offsetExists($fqn) === FALSE) {
				$report['tagsUnknown'][$fqn][] = $id;
				continue;
			}

			$inputs[] = ['tag' => $cTag[$fqn]];

		}

		return $inputs;

	}

	/**
	 * Les pages qui portent plusieurs aides après fusion des doublons : des volets d'un même appel
	 * (doublons.md), candidats à un parent commun, à relire avant de le poser.
	 */
	private static function getParentCandidates(array $aids): array {

		$urls = [];

		foreach($aids as $id => $aid) {
			$urls[$aid['url_source']][] = $id;
		}

		return array_filter($urls, fn(array $ids) => count($ids) > 1);

	}

	/**
	 * Le nombre de lignes du catalogue où chaque colonne est vide, pour dire ce que la reprise ne
	 * remplit pas.
	 */
	public static function countEmpty(): array {

		$counts = [];

		foreach([Aid::model(), Organization::model(), Edition::model(), Source::model(), Area::model(), AidOrganization::model(), AidTag::model(), AidLink::model(), Tag::model()] as $mModule) {

			$module = $mModule->getModule();
			$counts[$module]['total'] = $mModule->count();

			foreach($mModule->getProperties() as $property) {

				if($property === 'id') {
					continue;
				}

				$type = $mModule->getPropertyType($property);

				// Une date ou un nombre n'est vide que NULL : MySQL refuse de les comparer à ''.
				if($type === 'json') {
					$empty = ' OR JSON_LENGTH(m1.'.$property.') = 0';
				} else if(in_array($type, ['set', 'bool'], TRUE)) {
					$empty = ' OR m1.'.$property.' = 0';
				} else if(str_starts_with($type, 'text') or str_starts_with($type, 'editor') or $type === 'url') {
					$empty = ' OR m1.'.$property.' = \'\'';
				} else {
					$empty = '';
				}

				$counts[$module][$property] = new ($mModule::class)()
					->where('(m1.'.$property.' IS NULL'.$empty.')')
					->count();

			}

		}

		return $counts;

	}

}
?>
