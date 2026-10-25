<?php
namespace structure;

class Structure extends StructureElement {

	const DEMO = 1;
	const DEMO_BIS = 2;

	// L'état de partage d'une fiche publique pour un lecteur donné. Trois valeurs et non deux :
	// un membre de la ferme voit ses propres blocs même quand aucun groupe ne les partage, et
	// l'écran doit alors le lui dire.
	const PROFILE_SHARED = 'shared';
	const PROFILE_HIDDEN = 'hidden';

	// Fonctionnalités qu'une offre ouvre hors des écrans entiers (R-56, R-57) : subscription les associe à
	// une offre (`Subscription::getFeatureSoftware()`), et le nom d'une fonctionnalité refusée à l'écran est
	// aussi la clé de son message dans subscription/ui/Alert.u.php.
	const FEATURE_VARIANTS = 'variants';
	const FEATURE_DESIGN = 'design';
	const FEATURE_BALANCE = 'balance';
	const FEATURE_API = 'api';

	// Dernier jour où l'ancienne empreinte des liens publics est acceptée (`checkChecksum()`)
	const CHECKSUM_LEGACY_UNTIL = '2026-12-02';

	// Les mots qu'on saute pour tirer les initiales d'un nom (`getInitials()`) : articles,
	// prépositions et formes juridiques ouvrent plus de 45 % des noms, et donneraient la lettre L à plus
	// d'un tiers des structures.
	const INITIALS_IGNORED = [
		'l', 'la', 'le', 'les', 'un', 'une', 'd', 'de', 'du', 'des', 'au', 'aux', 'à', 'et', 'en', 'sur', 'chez',
		'gaec', 'earl', 'scea', 'sarl', 'sas', 'sasu', 'eurl', 'ei', 'eirl', 'sca', 'sci', 'scop', 'scic'
	];

	const DEMO_URL = OTF_DEMO_URL.'/ferme/'.self::DEMO;
	const DEMO_URL_BIS = OTF_DEMO_URL.'/ferme/'.self::DEMO_BIS;

	protected static array $eCommunity = [];
	protected static array $cProduction = [];
	protected static array $eSystem = [];
	protected static array $producerType = [];
	protected static array $sharedWithCommunity = [];
	protected static array $seasonPlanned = [];

	private static ?Structure $eStructureConnected = NULL;

	public function __construct(array $array = []) {

		parent::__construct($array);

	}

	public static function setConnected(Structure $eStructure): void {
		self::$eStructureConnected = $eStructure;
	}

	public static function resetConnected(): void {
		self::$eStructureConnected = NULL;
	}

	public static function getConnected(): Structure {
		return self::$eStructureConnected ?? throw new \Exception('No connected structure');
	}

	public static function validateConnected(...$validate): Structure {
		return Structure::getConnected()->validate(...$validate);
	}

	public static function isConnected(Structure $eStructure): bool {
		return (
			self::$eStructureConnected !== NULL and
			self::$eStructureConnected->is($eStructure)
		);
	}

	public function getMember(): Member {
		return MemberLib::getOnline()[$this['id']] ?? new Member();
	}

	public function getChecksum(): string {
		return \main\CryptLib::digest('structure:'.$this['id'], 'structure');
	}

	/**
	 * L'ancienne empreinte, un CRC32, se calculait d'une structure à l'autre sans le secret. Elle reste
	 * acceptée jusqu'à CHECKSUM_LEGACY_UNTIL, pour les liens que les structures ont copiés hors du
	 * logiciel (2026-10-21)
	 */
	public function checkChecksum(string $checksum): bool {

		if(hash_equals($this->getChecksum(), $checksum)) {
			return TRUE;
		}

		return (
			\Clock::date() <= self::CHECKSUM_LEGACY_UNTIL and
			hash_equals((string)abs(crc32($this['id'].\main\MainSetting::$crypt['structure'])), $checksum)
		);

	}

	public static function getSelection(): array {

		return parent::getSelection() + [
			'cFinancialYear?' => fn(Structure $eStructure) => function() use ($eStructure) {
				if($eStructure->hasAccounting()) {
					StructureLib::connectDatabase($eStructure);
					return \financialYear\FinancialYearLib::getAll();
				} else {
					return new \Collection();
				}
			},
			'moneyStepDone?' => fn(Structure $eStructure) => fn() => (StructureSetting::hasAccountingApp() and \company\OnboardingAiLib::isMoneyStepDone($eStructure)),
			'hasEnableBankingAccessToRenew?' => fn(Structure $eStructure) => fn() => (StructureSetting::hasAccountingApp() and \bank\BankAccountLib::hasEnableBankingAccessToRenew($eStructure)),
			'cFavorite?' => fn(Structure $eStructure) => fn() => \toolbox\FavoriteLib::getByStructure($eStructure),
			'menus?' => fn(Structure $eStructure) => fn() => MenuLib::getMenus($eStructure),
			'menuCategories?' => fn(Structure $eStructure) => fn(string $menu) => MenuLib::getCategories($eStructure, $menu),
		];

	}

	public function isProducer(): bool {
		return $this['type'] === Structure::PRODUCER;
	}

	public function isShop(): bool {
		return $this['type'] === Structure::SHOP;
	}

	public function isCommunity(): bool {
		return $this['type'] === Structure::COMMUNITY;
	}

	public function isAmap(): bool {
		return $this['type'] === Structure::AMAP;
	}

	/**
	 * ⚠️ Le discriminant avant l'attribut : une ferme ne porte pas `communityType`, et le lire
	 * d'abord salirait chaque création de ferme d'un `Undefined array key` sans rien changer à la
	 * réponse. `CommunityFeatureAiTest::testEducationIsReadInTheRightOrder()` capte le warning.
	 */
	public function isEducation(): bool {
		return ($this->isCommunity() and $this['communityType'] === Structure::EDUCATION);
	}

	public function isNetwork(): bool {
		return ($this->isCommunity() and $this['communityType'] === Structure::NETWORK);
	}

	public function getAudienceRoles(): array {

		if($this->isEducation()) {
			return ['staff' => 'admin'];
		}

		return $this->isNetwork()
			? ['member' => 'member', 'staff' => 'advisor']
			: ['member' => 'member'];

	}

	public function isProject(): bool {
		return $this->producerType() === Structure::PROJECT;
	}

	/**
	 * La ferme **école** : la copie anonymisée d'une ferme réelle, commune à tout le site.
	 *
	 * Elle n'a pas de `parent` — elle n'appartient à aucun groupe — et porte `source`, la ferme
	 * dont elle est le miroir.
	 */
	public function isSchool(): bool {
		return $this->producerType() === Structure::SCHOOL;
	}

	public function isInstalled(): bool {
		return in_array($this->producerType(), [NULL, Structure::REAL], TRUE);
	}

	public function acceptRealService(): bool {
		return ($this->producerType() !== Structure::TRAINING);
	}

	public function isFixture(): bool {
		return in_array($this['id'], StructureSetting::FIXTURE_STRUCTURES);
	}

	public function canSchool(): bool {
		return (
			\user\ConnectionLib::isLogged() and
			$this->isSchool()
		);
	}

	public function acceptWrite(): bool {
		return ($this->isSchool() === FALSE);
	}

	/**
	 * ⚠️ Héritage coupé : `acceptCreate()` retombe sinon sur `acceptWrite()`, qui lit
	 * `producerType` — une propriété qu'une structure neuve ne porte pas encore au moment où
	 * `ModulePage` valide la paire. La règle ci-dessus ne concerne qu'une ferme école existante.
	 */
	public function acceptCreate(): bool {
		return TRUE;
	}

	public function acceptCreateType(): bool {

		$this->expects(['type', 'owner']);

		return match($this['type']) {
			Structure::PRODUCER, Structure::SHOP => TRUE,
			Structure::AMAP => $this['owner']->acceptCreateAmap(),
			Structure::COMMUNITY => $this->acceptCreateCommunity()
		};

	}

	/**
	 * Le type de groupe se juge tel qu'il est posté : l'écran n'offre au propriétaire que les types
	 * qu'il peut créer, mais un POST se forge.
	 */
	private function acceptCreateCommunity(): bool {

		$this->expects(['owner', 'communityType']);

		return match($this['communityType']) {
			Structure::PRODUCER => $this['owner']->acceptCreateCommunityProducer(),
			Structure::EDUCATION => $this['owner']->acceptCreateCommunityEducation(),
			Structure::NETWORK => $this['owner']->acceptCreateCommunityNetwork(),
			Structure::ACCOUNTING, NULL => FALSE
		};

	}

	public function active(): bool {
		return ($this['status'] === Structure::ACTIVE);
	}

	/** Une structure ne se ferme qu'une fois : une AMAP fermée reste ouverte à son équipe, qui ne la referme pas. */
	public function acceptClose(): bool {

		$this->expects(['status']);

		return $this->active();

	}

	// Est-ce que la ferme utilise le logiciel comptable d'OTF ?
	public function usesAccounting(): bool {

		return (
			$this->empty() === FALSE and
			$this->hasAccounting() and
			$this->conf(Feature::class)['hasFinancialYears']
		);

	}

	public function siren(): ?string {

		$this->expects(['siret']);

		if($this['siret'] === NULL) {
			return NULL;
		}

		return mb_substr($this['siret'], 0, 9);

	}

	/**
	 * Deux lettres pour reconnaître une structure sans logo : la première des deux premiers mots
	 * qui comptent, ou les deux premières d'un mot seul, comme la vignette d'un utilisateur sans
	 * prénom. Un mot qui ne commence pas par une lettre (un numéro, une date) ne compte pas, et un
	 * nom fait des seuls mots sautés repart de tous ses mots plutôt que de rester sans lettre.
	 */
	public function getInitials(): string {

		$this->expects(['name']);

		$words = array_values(array_filter(
			preg_split('/[^\p{L}\p{N}]+/u', $this['name'], -1, PREG_SPLIT_NO_EMPTY),
			fn(string $word) => preg_match('/^\p{L}/u', $word) === 1
		));

		$wordsKept = array_values(array_filter($words, fn(string $word) => in_array(mb_strtolower($word), self::INITIALS_IGNORED) === FALSE));

		if($wordsKept !== []) {
			$words = $wordsKept;
		}

		$letters = match(count($words)) {
			0 => mb_substr($this['name'], 0, 2),
			1 => mb_substr($words[0], 0, 2),
			default => mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1)
		};

		return mb_strtoupper($letters);

	}

	public function acceptLegal(): bool {

		return (
			$this->isProducer() or
			$this->isShop()
		);

	}

	public function acceptSurvey(): bool {

		return (
			FEATURE_SURVEY and
			(
				StructureSetting::SURVEY_MEMBERSHIP === FALSE or
				$this->isMembership()
			)
		);

	}

	public function acceptGhost(): bool {

		return (
			$this->assertProduction() and
			$this->isProducer()
		);

	}

	public function acceptRoles(): bool {

		return $this->isProducer();

	}

	public function acceptMarks(): bool {

		return $this->isProducer();

	}

	public function assertSection(string $section): bool {

		return (
			$this->acceptFeatureSection($section) and
			$this->canSection($section)
		);

	}

	public function acceptFeatureSection(string $section): bool {

		switch($section) {

			case 'production' :
				return $this->acceptProduction();

			case 'commercialisation' :
				return $this->acceptCommercialisation();

			case 'accounting' :
				return $this->acceptAccounting();

			case 'settings' :
				return TRUE;

			case 'amap' :
				return (
					StructureSetting::hasCommercialisationApp() and
					$this->isAmap()
				);

		}

	}

	public function canSection(string $section): bool {

		switch($section) {

			case 'production' :
				return $this->assertProduction();

			case 'commercialisation' :
				return $this->assertCommercialisation();

			case 'accounting' :
				return $this->assertAccounting();

			case 'settings' :
				return ($this->canManage() or $this->canManageAccounting());

			case 'amap' :
				return $this->canAmapCollective();

		}

	}

	/**
	 * L'appartenance ouvre une structure — et pour une ferme école, le groupe de formation.
	 */
	public function canRead(): bool {

		return (
			$this->isMember() or
			$this->canSchool() or
			$this->sharesWithMemberCommunity('shareProduction', 'productionObserve')
		);

	}

	/**
	 * L'état d'une ferme qui peut porter une fiche publique.
	 *
	 * Une boutique et une AMAP partagent l'adresse `/ferme/{id}` sans avoir de fiche, et une
	 * ferme fermée n'en a plus.
	 */
	public function acceptReadProfile(): bool {

		$this->expects(['type', 'status']);

		return (
			$this['type'] === self::PRODUCER and
			$this['status'] === self::ACTIVE
		);

	}

	/**
	 * Le droit de lire la fiche publique d'une ferme, par trois chemins.
	 *
	 * Le premier n'est pas un cas limite : 5 548 des 5 557 fermes producer actives n'ont
	 * aucune affiliation, et l'onglet Fermes met à leur exploitant un lien vers cette adresse.
	 */
	public function canReadProfile(): bool {

		return match($this['profileVisibility']) {

			self::PUBLIC => TRUE,
			self::NONE => $this->isMember(),
			self::MEMBERS => (
				$this->isMember() or
				$this->getProfileCommunities()->notEmpty() or
				$this->isProjectOfMemberCommunity() or
				$this->canSchool()
			)

		};

	}

	/**
	 * Ce que ce lecteur voit de la fiche d'identité de cette ferme.
	 *
	 * ⚠️ **Le socle, et non plus le droit de partage.** Ce que cette fiche montre — le système,
	 * les ateliers, le voisinage — est ce que la ferme **est** : un métier, des surfaces, une
	 * mécanisation, une certification. Ce que le droit `shareProduction` porte depuis le
	 * 10/09/2026 est ce qu'elle **fait cette saison**, le plan de culture et l'assolement, et il
	 * vit derrière `canObserveProduction()`. Deux natures de donnée, deux portes.
	 *
	 * Le socle se donne donc entre fermes d'un même groupe, sans case et sans rôle : un socle
	 * conditionné par un réglage n'est pas un socle. Un membre du groupe sans `productionObserve`
	 * voit désormais cette fiche — c'est un accès qui s'ouvre, assumé et annoncé.
	 *
	 * ⚠️ Sans condition ne veut pas dire sans rattachement : il faut un groupe en commun, ou être
	 * membre de la ferme. Qui n'a ni l'un ni l'autre atteint la fiche par `profileVisibility`, qui
	 * continue de régir la fiche publique, et n'en voit que l'identité.
	 *
	 * La valeur se pose par ferme et jamais par page : une même personne atteint plusieurs fermes
	 * par des chemins différents.
	 */
	public function getProfileShare(): string {

		// Une ferme école est faite pour être observée : ce qu'elle montre est une copie
		// anonymisée, il n'y a rien à protéger derrière un périmètre.
		if($this->isProjectOfMemberCommunity() or $this->canSchool()) {
			return self::PROFILE_SHARED;
		}

		if($this->isMember() or $this->getProfileCommunities()->notEmpty()) {
			return self::PROFILE_SHARED;
		}

		return self::PROFILE_HIDDEN;

	}

	public function getProfileCommunities(): \Collection {

		$this->expects(['id']);

		$eUser = \user\ConnectionLib::getOnline();

		// Un lecteur anonyme n'a aucun groupe : la requête chercherait des affiliations d'un
		// utilisateur sans identifiant et rendrait tout ou rien selon la façon dont elle est lue.
		if($eUser->empty()) {
			return new \Collection();
		}

		return \structure\AffiliationAiLib::getCommunitiesByStructure($this, $eUser);

	}

	/**
	 * Une ferme projet appartient à son groupe : ce que le groupe possède, ses membres le lisent.
	 *
	 * Le type du parent se vérifie ici et pas ailleurs : l'invariant du schéma n'est appliqué
	 * par aucun code du produit, et un `parent` posé à la main sur une ferme ordinaire ouvrirait
	 * sa fiche aux membres de n'importe quelle structure.
	 */
	private function isProjectOfMemberCommunity(): bool {

		$this->expects(['producerType', 'parent']);

		if($this['producerType'] !== self::PROJECT or $this['parent'] === NULL) {
			return FALSE;
		}

		// L'appartenance se lit sans requête, le type du parent en coûte une : la poser en
		// second borne cette lecture aux fermes projet d'un groupe dont on est membre.
		if(MemberLib::getOnline()->offsetExists($this['parent']['id']) === FALSE) {
			return FALSE;
		}

		return (StructureLib::getById($this['parent']['id'], ['type'])['type'] === self::COMMUNITY);

	}

	/**
	 * Un groupe dont ce lecteur est membre ouvre-t-il cette ferme, et ce lecteur y a-t-il droit ?
	 *
	 * **Deux conditions qui se cumulent, et elles ne sont pas de même nature.** Le partage ouvre
	 * la ferme **au groupe** : c'est la ferme qui l'accorde, à l'ensemble. Le rôle distribue
	 * ensuite **à l'intérieur** du groupe : c'est le groupe qui décide qui en profite. L'une sans
	 * l'autre laisserait soit la ferme sans recours contre un groupe qui grandit, soit le groupe
	 * sans moyen de distinguer un conseiller d'un adhérent.
	 *
	 * Le croisement est **par groupe** et jamais global : deux groupes peuvent être communs, et il
	 * suffit qu'un seul ouvre la ferme **et** accorde le droit. Peser les deux conditions
	 * séparément rendrait vrai sur un partage venu d'un groupe et un droit venu d'un autre.
	 *
	 * ⚠️ Le partage est celui du **rattachement** et non de la bascule du groupe : une ferme
	 * rattachée quand le groupe ne partageait que la vente ne se met pas à partager sa production
	 * le jour où le groupe l'ouvre pour les rattachements suivants. C'est l'affiliation qui fait foi.
	 *
	 * **Ce que cette porte gouverne, et ce qu'elle ne gouverne pas.** Ses trois appelants
	 * (`canRead()`, `canObserveProduction()`, `getProfileShare()`) portent tous sur les données de
	 * production. La **fiche publique** n'en dépend pas : `canReadProfile()` se décide sur
	 * `profileVisibility` et sur l'appartenance nue à un groupe commun, sans lire ni partage ni
	 * rôle. Un lecteur sans le droit perd donc ce que la ferme produit, jamais son existence sur le
	 * site. La distinction n'est évidente pour personne et se vérifie en lisant `canReadProfile()`,
	 * qui n'appelle pas cette méthode.
	 *
	 * ⚠️ **La porte dit toujours oui aujourd'hui, et ce n'est pas du code mort.** L'écran des rôles
	 * n'est pas ouvert aux groupes : tous les rôles naissent avec le droit et personne ne peut le
	 * retirer. Elle servira le jour où cet écran s'ouvrira, ou dès qu'un rôle naîtra sans. Retirer
	 * la condition parce qu'elle n'a jamais refusé serait retirer le seul endroit qui sait refuser.
	 *
	 * ⚠️ Mémoïsé par **triplet** (ferme, lecteur, partage) et non par ferme seule : une requête
	 * peut connecter successivement deux comptes — les tests le font — et poser deux questions
	 * différentes sur la même ferme. La clé porte donc les trois.
	 */
	private function sharesWithMemberCommunity(string $right, string $roleRight): bool {

		$this->expects(['id']);

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->empty()) {
			return FALSE;
		}

		$key = $this['id']."\0".$eUser['id']."\0".$right;

		if(array_key_exists($key, self::$sharedWithCommunity) === FALSE) {

			self::$sharedWithCommunity[$key] = $this->getProfileCommunities()
				->find(fn(Structure $eStructureCommunity) => (
					$eStructureCommunity->getMember()->isRole($roleRight) and
					$eStructureCommunity->grantsCommunityRight($right)
				))
				->notEmpty();

		}

		return self::$sharedWithCommunity[$key];

	}

	public function assertProduction(): bool {

		return (
			$this->conf(Feature::class)['production'] and
			$this->acceptProduction() and
			$this->canProduction()
		);
		
	}

	public function canProduction(): bool {

		if($this->hasFeatureProduction() === FALSE) {
			return FALSE;
		}

		return (
			$this->canObserveProduction() or
			$this->canPlanningLimited() or
			$this->canAnalyzeProduction() or
			$this->canManage()
		);
	}

	public function hasFeatureProduction(): bool {
		return (
			$this->acceptProduction()
		);
	}

	public function assertCommercialisation(): bool {

		return (
			$this->acceptCommercialisation() and
			$this->canCommercialisation()
		);

	}

	public function canCommercialisation(): bool {
		return (
			$this->canSelling() or
			$this->canAnalyzeCommercialisation() or
			$this->canManage()
		);
	}

	/**
	 * ⚠️ canAnalyzeAccounting() entre ici et non dans canAccounting(), qui sert aussi à décider
	 * qu'une facture de vente est lisible (selling\Invoice::canRead()) : l'y mettre ouvrirait les
	 * factures à qui ne doit voir que la synthèse. Les six appelants de cette méthode-ci sont tous
	 * de la navigation, ils décident si la section Gestion s'affiche et vers où elle mène.
	 */
	public function assertAccounting(): bool {

		return (
			$this->acceptAccounting() and
			$this->canAnalyzeAccounting()
		);

	}

	public function canAccounting(): bool {

		return (
			$this->canObserveAccounting() or
			$this->canManageAccounting()
		);
	}

	public function hasFeatureAccounting(): bool {
		return (
			$this->acceptAccounting()
		);
	}

	public function hasPilotage(): bool {
		return FEATURE_PILOTAGE;
	}

	/**
	 * ⚠️ Lecture seule, et strictement : aucune des trois portes ci-dessous n'ouvre `canManage()`
	 * ni `canPlanning()`, donc l'écran ne propose aucun geste d'écriture. Le partage d'un groupe
	 * donne à voir la production d'une ferme, il ne donne jamais la main dessus.
	 */
	public function canObserveProduction(): bool {

		return (
			$this->isRole('productionObserve') or
			$this->canSchool() or
			$this->sharesWithMemberCommunity('shareProduction', 'productionObserve')
		);

	}

	/**
	 * Ce lecteur peut-il sortir le plan de culture de cette ferme, vers une autre ou vers un fichier ?
	 *
	 * Deux chemins, et c'est un **`or`** : le rôle tenu sur la ferme elle-même, ou le croisement
	 * par un groupe — celui-ci cumulant bien l'autorisation de la ferme et le droit de l'adhésion,
	 * mais l'autre s'en passant entièrement. Le rôle `admin` porte `productionExport`, donc la
	 * réponse est oui chez soi sans le moindre rattachement, et c'est juste : sortir sa propre
	 * production ne demande la permission de personne.
	 *
	 * ⚠️ **Elle ne suffit donc pas à garder l'emport depuis la ferme d'un pair.** Là, la seconde
	 * condition se lit sur le groupe rendu par `getExportCommunity()`, dont la propriété
	 * `granted['shareProductionExport']` dit à quels publics cette ferme-là a ouvert la copie à ce
	 * groupe-là. Le texte qui
	 * figurait ici annonçait deux conditions cumulées, ce que le code ne fait pas ; il a fait
	 * prendre cette méthode pour une porte complète, deux fois le 2026-09-08.
	 *
	 * ⚠️ Le téléchargement CSV passe par ici aussi : sortir des données est sortir des données, et
	 * un fichier n'est pas moins une copie parce qu'il ne va pas dans une autre ferme.
	 */
	public function canExportProduction(): bool {

		return (
			$this->isRole('productionExport') or
			$this->sharesWithMemberCommunity('shareProductionExport', 'productionExport')
		);

	}

	/**
	 * Le même droit, mais **par le groupe que l'appelant nomme**.
	 *
	 * Les écrans de copie transportent le groupe dans leur adresse, et le rendent à la route qui
	 * exporte. `canExportProduction()` ne le regarde pas : elle répond oui dès qu'un groupe
	 * quelconque accorde, ce qui laisse passer un identifiant de groupe qui n'a rien à voir avec
	 * l'emport en cours. C'est le même croisement par groupe que `sharesWithMemberCommunity()`,
	 * restreint à un groupe au lieu de leur union.
	 *
	 * Le rôle tenu sur la ferme elle-même reste un chemin à part entière : on sort sa propre
	 * production sans passer par personne.
	 */
	public function canExportProductionByCommunity(Structure $eStructureCommunity): bool {

		if($this->isRole('productionExport')) {
			return TRUE;
		}

		$cStructureCommunity = $this->getProfileCommunities();

		if($cStructureCommunity->offsetExists($eStructureCommunity['id']) === FALSE) {
			return FALSE;
		}

		$eStructureCommunityShared = $cStructureCommunity[$eStructureCommunity['id']];

		return (
			$eStructureCommunityShared->getMember()->isRole('productionExport') and
			$eStructureCommunityShared->grantsCommunityRight('shareProductionExport')
		);

	}

	/**
	 * Ce groupe-ci a-t-il reçu ce droit, pour le public auquel le lecteur appartient ?
	 *
	 * ⚠️ Posée sur le **groupe** tel que `AffiliationAiLib::getCommunitiesByStructure()` le rend :
	 * c'est lui qui porte `granted`, l'union de ce que la ferme a accordé sur tous ses
	 * rattachements. Sur un groupe venu d'ailleurs, la propriété manque et la question n'a pas de
	 * sens — c'est un appel fautif, pas un état à rattraper.
	 *
	 * Posée sur l'élément parce que les écrans la posent : un `CommunityRightAiLib::grants()`
	 * appelé depuis une UI serait une requête déguisée au sens du CLAUDE.md. Même forme que
	 * `acceptCommunityFeature()`.
	 */
	public function grantsCommunityRight(string $right): bool {

		return \structure\CommunityRightAiLib::grants(
			$this,
			$right,
			$this['granted'][$right],
			\structure\CommunityRightAiLib::getAudience($this->getMember()['role']),
			$this['audiencesBringing']
		);

	}

	/**
	 * Le groupe par lequel le lecteur emporterait le plan de culture de cette ferme.
	 *
	 * ⚠️ Il rend le groupe **même quand la ferme n'a pas ouvert la copie** : c'est ce qui permet à
	 * l'écran de dire « cette ferme ne l'a pas ouvert » au lieu de taire le geste. La porte reste
	 * `canExportProduction()`, que ce rendu n'ouvre pas — il ne dit que par où l'on passerait.
	 *
	 * Celui qui a ouvert la copie vient en premier : deux groupes peuvent porter le droit, et
	 * proposer celui qui refuse quand un autre accepte serait un refus inventé.
	 *
	 * ⚠️ Seuls comptent les types de groupe où la copie existe. Le rôle porte `productionExport`
	 * partout : sans ce filtre, l'écran reproche à la ferme de n'avoir pas ouvert un droit qu'elle
	 * ne peut pas ouvrir (vu en formation le 2026-09-27, quand la copie y était absente). Le filtre
	 * porte sur le type et non sur ce que le groupe demande : une copie accordée au rattachement
	 * survit à la bascule du groupe.
	 */
	public function getExportCommunity(): Structure {

		$this->expects(['id']);

		$cStructureCommunity = $this->getProfileCommunities()
			->find(fn(Structure $eStructureCommunity) => (
				$eStructureCommunity->getMember()->isRole('productionExport') and
				\structure\CommunityRightAiLib::accepts($eStructureCommunity, 'shareProductionExport')
			));

		foreach($cStructureCommunity as $eStructureCommunity) {

			if($eStructureCommunity->grantsCommunityRight('shareProductionExport')) {
				return $eStructureCommunity;
			}

		}

		return $cStructureCommunity->first() ?? new Structure();

	}

	public function canAnalyzeProduction(): bool {
		return (
			$this->isRole('productionAnalyze') or
			$this->canSchool()
		);
	}

	public function canAnalyzeCommercialisation(): bool {
		return (
			$this->isRole('commercialisationAnalyze') or
			$this->canSchool()
		);
	}

	// Peut consulter la comptabilité sans la modifier
	public function canObserveAccounting(): bool {
		return (
			StructureSetting::hasAccountingApp() and
			(
				$this->isRole('accounting', Role::LIMITED) or
				$this->isRole('accounting', Role::FULL)
			)
		);
	}

	// Peut tenir la comptabilité
	public function canManageAccounting(): bool {
		return (
			StructureSetting::hasAccountingApp() and
			$this->isRole('accounting', Role::FULL)
		);
	}

	/**
	 * La comptabilité se réinitialise tant que rien n'y est validé ni déclaré. Les conditions
	 * interrogent les tables de la comptabilité, que ce fichier publié ne peut pas nommer : la page
	 * les pose pour l'affichage (`company\ResetLib::fillRefusals()`), une entrée par condition qui
	 * ferme le geste. Le geste ne passe pas par ici : il les relit sous verrou dans sa transaction
	 * (`company\ResetLib::reset()`), sans validation par la route.
	 */
	public function acceptAccountingReset(): bool {

		$this->expects(['accountingResetRefusals']);

		return ($this['accountingResetRefusals'] === []);

	}

	public function assertAccountingReset(): bool {

		return (
			$this->canManageAccounting() and
			$this->acceptAccountingReset()
		);

	}

	/**
	 * Peut consulter la synthèse comptable et ses documents : bilan, compte de résultat, soldes
	 * intermédiaires, archives, fichier FEC, attestation, analyses financières.
	 *
	 * ⚠️ Le contraire n'est pas vrai : ce droit n'ouvre que les écrans qui le nomment. Les gardes
	 * comptables du dépôt interrogent canObserveAccounting() ou canManageAccounting(), que le rôle
	 * d'analyse ne donne pas, et continuent donc de le refuser sans avoir été relues une par une.
	 */
	public function canAnalyzeAccounting(): bool {
		return (
			StructureSetting::hasAccountingApp() and
			(
				$this->isRole('accountingAnalyze') or
				$this->canObserveAccounting()
			)
		);
	}

	public function canProof(): bool {
		return (
			$this->empty() === FALSE and
			$this->hasAccounting() and
			$this->canObserveAccounting() and
			$this->acceptProof()
		);
	}

	// Peut voir le planning
	public function canPlanningLimited(): bool {
		return (
			$this->isRole('planning', Role::LIMITED) or
			$this->isRole('planning', Role::FULL)
		);
	}

	// Peut voir les données personnelles des clients et la page de gestion d'équipe
	public function canPersonalData(): bool {
		return $this->isOwner();
	}

	public function canSelling(): bool {

		return (
			$this->isRole('selling') or
			$this->canSchool()
		);

	}

	public function assertShop(): bool {

		return (
			$this->isRole('selling')
		);

	}

	/**
	 * Cette structure livre-t-elle au moins une AMAP ?
	 *
	 * La colonne est écrite par `amap\ProducerLib`, à l'entrée et au retrait d'une AMAP.
	 */
	public function acceptAmapProducer(): bool {

		$this->expects(['id']);

		return $this->conf(Feature::class)['hasAmap'];

	}

	/** La forme `accept*` de `isAmap()`, que la déclaration d'un bloc de l'accueil sait lire (`base/dashboard/CLAUDE.md`). */
	public function acceptAmap(): bool {
		return $this->isAmap();
	}

	/**
	 * Qui entre dans le menu d'une AMAP : son bureau, et le référent d'au moins un
	 * de ses producteurs actifs. C'est la question que pose `canSection('amap')`, donc tout le gabarit.
	 */
	public function canAmapCollective(): bool {

		return ($this->canManage() or StructureLib::isAmapReferent($this));

	}

	public function assertAmapProducer(): bool {

		return (
			$this->canSelling() and
			$this->acceptAmapProducer()
		);

	}

	public function assertInvoice(): bool {

		return (
			$this->isRole('selling')
		);

	}

	/**
	 * Le droit d'**écrire** une vente ou un client, et il n'est porté que par un rôle.
	 *
	 * ⚠️ Séparé de `canSelling()` le 2026-09-05, parce que les deux n'étaient qu'un : mesuré,
	 * n'importe quel compte connecté annulait les 366 ventes de la ferme école par
	 * `POST /selling/sale:doUpdateCanceledCollection`, dont la garde nomme sa propre paire
	 * `('canWrite', 'acceptStatusCanceled')` et ne consulte donc jamais `Sale::acceptWrite()`.
	 * Aucun `accept*` ne pouvait fermer ce chemin : seul le droit d'écrire le pouvait.
	 *
	 * Rien ne change pour une ferme ordinaire, où `canSchool()` est faux et où les deux méthodes
	 * rendent donc la même chose.
	 */
	public function canWriteSelling(): bool {

		return $this->isRole('selling');

	}

	// Peut accéder en lecture aux pages liées à la communication
	public function canCommunication(): bool {
		return $this->isOwner();
	}

	// Peut créer ou modifier des interventions
	public function canPlanning(): bool {
		return $this->isRole('planning', Role::FULL);
	}

	// Peut gérer la ferme
	public function canManage(): bool {
		return $this->isOwner();
	}

	public function canDuplicate(): bool {
		return $this->getMember()->isStaff();
	}

	public function assertDuplicate(): bool {

		return (
			$this->acceptDuplicate() and
			$this->canDuplicate()
		);

	}

	// Peut ouvrir les réglages de commercialisation, dont le comptable ne voit que la précomptabilité
	public function canSettingsCommercialisation(): bool {
		return ($this->canManage() or $this->canManageAccounting());
	}

	// Scopes d'API proposables pour une clé API personnelle de cette structure (cf. framework/api :
	// ApiSetting::PERSONAL_SCOPE_NAMES), selon ses fonctionnalités et ce qu'acceptApiScope() lui ouvre.
	// Seul un administrateur atteint la page des clés (canManage()).
	public function getApiScopes(): array {

		$scopes = ['general:read', 'general:write'];

		if($this->acceptProduction()) {
			$scopes[] = 'production:read';
			$scopes[] = 'production:write';
		}

		if($this->acceptCommercialisation()) {
			$scopes[] = 'commercialisation:read';
			$scopes[] = 'commercialisation:write';
		}

		if($this->acceptAccounting()) {
			$scopes[] = 'accounting:read';
			$scopes[] = 'accounting:write';
		}

		if($this->acceptLearning()) {
			$scopes[] = 'learning:read';
			$scopes[] = 'learning:write';
		}

		return array_values(array_filter($scopes, fn(string $scope) => $this->acceptApiScope($scope)));

	}

	// Peut gérer la ferme
	public static function canManageConnected(): bool {
		return self::getConnected()->canManage();
	}

	public function isMember(): bool {

		return (
			$this->notEmpty() and
			$this->getMember()->notEmpty()
		);

	}

	public function isOwner(): bool {

		if($this->empty()) {
			return FALSE;
		}

		$eMember = $this->getMember();

		return (
			$eMember->notEmpty() and
			$eMember['role']['admin']
		);

	}

	public function isRole(string $property, mixed $expected = TRUE): bool {

		if($this->empty()) {
			return FALSE;
		}

		return $this->getMember()->isRole($property, $expected);

	}

	public function isIndividual() {

		$this->expects(['legalCategory']);

		return $this['legalCategory'] === \structure\StructureSetting::CATEGORIE_JURIDIQUE_ENTREPRENEUR_INDIVIDUEL;

	}

	public function isCompany() {

		$this->expects(['legalCategory']);

		return $this['legalCategory'] !== \structure\StructureSetting::CATEGORIE_JURIDIQUE_ENTREPRENEUR_INDIVIDUEL;

	}

	public function isMembership(): bool {

		return ($this['membership'] === TRUE);

	}

	public function hasMembershipAdvantages(): bool {

		return (
			$this->isMembership() or
			StructureLib::isMembershipFree()
		);

	}

	public function isVatAccountingConfigured(): bool {

		return (
			$this['type'] === Structure::SHOP or
			$this->conf()['hasVat'] !== NULL
		);

	}

	/**
	 * L'assujettissement à la TVA pour qui attend un booléen. `hasVat` est nullable depuis le
	 * 2026-09-13 et NULL dit que la structure n'a pas encore répondu à la question : elle n'est donc
	 * pas assujettie tant qu'elle n'a pas dit oui. Les écrans qui distinguent l'absence de réponse,
	 * dont celui qui pose la question, lisent conf()['hasVat'] et reçoivent les trois états.
	 */
	public function hasVat(): bool {

		return ($this->conf()['hasVat'] === TRUE);

	}

	public function isLegal(): bool {

		$this->expects(['isLegal']);

		return $this['isLegal'];

	}

	public function isNotLegal(): bool {
		return $this->isLegal() === FALSE;
	}

	public function isLegalInfo(): bool {

		if($this->isLegal()) {
			return (
				($this->isFrance() === FALSE or $this['siret'] !== NULL) and
				$this['legalName'] !== NULL and
				$this['legalCity'] !== NULL
			);
		} else {
			return TRUE;
		}

	}

	public function validateLegalInfo(): self {
		return $this->isLegalInfo() ? $this : throw new \FailAction('structure\Structure::notLegal', ['structure' => $this]);
	}

	public function isVerifiedCountry(): bool {

		if($this->isLegal()) {
			return (
				$this->exists() and
				$this['verifiedCountry']
			);
		} else {
			return TRUE;
		}

	}

	public function isNotVerifiedCountry(): bool {

		return $this->isVerifiedCountry() === FALSE;

	}

	public function validateVerified(): self {

		$this->validate();

		return $this->isVerifiedCountry() ? $this : throw new \FailAction('structure\Structure::notVerified', ['structure' => $this]);

	}

	public function hasVatCountry(): bool {
		return TRUE;
	}

	public function isFrance(): bool {

		return (
			$this['legalCountry']->notEmpty() and
			in_array($this['legalCountry']['id'], \user\UserSetting::COUNTRY_FRANCE)
		);

	}

	public function isBelgium(): bool {

		return (
			$this['legalCountry']->notEmpty() and
			in_array($this['legalCountry']['id'], \user\UserSetting::COUNTRY_BELGIUM)
		);

	}

	/**
	 * `ask()` et non `$this['legalCountry']['eu']` : un délégué est chargé par sa clé étrangère et
	 * ne porte que son id, alors que l'appartenance à l'Union est une colonne du pays.
	 */
	public function isEuropeanUnion(): bool {

		$eCountry = \user\Country::ask($this['legalCountry']);

		return ($eCountry->notEmpty() and $eCountry['eu']);

	}

	public function isCountryVatAllowed(): bool {

		return (
			$this['legalCountry']->notEmpty() and
			in_array($this['legalCountry']['id'], \product\ProductSetting::getVatAllowed())
		);

	}

	public function canCreate(): bool {
		return TRUE;
	}

	/**
	 * ⚠️ Ni ferme école ni partage de groupe ici, et c'est le fond de la correction du 2026-09-05 :
	 * `canWrite()` gardait aussi la **lecture** des séries (`series\Series::canRead()` rendait
	 * `structure->canWrite()`), si bien qu'ouvrir la lecture d'une ferme ouvrait son écriture.
	 * Mesuré : n'importe quel compte connecté annulait les 366 ventes de la ferme école, et
	 * écrivait ses cultures. Les lecteurs passent désormais par `canRead()`, et cette méthode
	 * redit ce que son nom promet.
	 */
	public function canWrite(): bool {

		if($this->empty()) {
			return FALSE;
		}

		return $this->getMember()->notEmpty();

	}

	/**
	 * Les réglages de la structure portés par une table de réglages, `structure\Configuration` par
	 * défaut : `$eStructure->conf(\map\Configuration::class)['seasonLast']`. La lecture passe par le
	 * Lib du module (`structure\ConfLib`), qui la mémoïse par structure.
	 *
	 * @template T of \Element
	 * @param class-string<T> $class
	 * @return T
	 */
	public function conf(string $class = Configuration::class): \Element {
		return ($class.'Lib')::getByStructure($this);
	}

	/**
	 * Le type de producteur, chargé au besoin comme `conf()` ou `community()`.
	 *
	 * ⚠️ Il ne peut pas se lire directement sur l'élément : une propriété déléguée n'arrive
	 * porteuse que de son `id`. `series\Series::canRead()` appelle `structure->canWrite()` sur
	 * une structure dans cet état, et `canWrite()` interroge `isSchool()` — mesuré le 2026-09-05,
	 * `$this['producerType']` y levait un warning et rendait NULL, donc FALSE. La ferme école
	 * devenait illisible par le chemin même qui devait l'ouvrir, et le warning cassait le JSON de
	 * toute réponse ajax de la page.
	 *
	 * ⚠️ Le défaut ne se voyait que pour un lecteur **non membre** : `canWrite()` teste d'abord
	 * `getMember()->notEmpty()`, et `or` court-circuite. Sur sa propre ferme, tout allait bien.
	 *
	 * Mémoïsé par identifiant de structure et jamais dans un scalaire : une requête HTTP touche
	 * plusieurs structures, `StructureLib::runAll()` existe pour cela.
	 */
	public function producerType(): ?string {

		if($this->offsetExists('producerType')) {
			return $this['producerType'];
		}

		if(array_key_exists($this['id'], self::$producerType) === FALSE) {

			// ⚠️ La lecture passe par l'element rendu et non par une deuxieme indexation : sur un
			// id qui n'existe plus — element fantome, cle etrangere rompue — `getById()` rend une
			// Structure vide, et lire une propriete dessus leve un warning qui casse le JSON de
			// toute reponse ajax. C'est le symptome meme que cette methode existe pour eviter.
			$eStructure = StructureLib::getById($this['id'], ['id', 'producerType']);

			self::$producerType[$this['id']] = $eStructure->empty() ? NULL : $eStructure['producerType'];

		}

		return self::$producerType[$this['id']];

	}

	public function community(): \structure\Community {

		if($this->offsetExists('community')) {
			return $this['community'];
		} else {

			if(array_key_exists($this['id'], self::$eCommunity) === FALSE) {

				self::$eCommunity[$this['id']] = \structure\CommunityLib::getByStructure($this);
				self::$eCommunity[$this['id']]['structure'] = $this;

			}

			return self::$eCommunity[$this['id']];

		}

	}

	public function getCommunity(string $name): mixed {

		return $this->community()[$name];

	}

	public function productions(): \Collection {

		if($this->offsetExists('cProduction')) {
			return $this['cProduction'];
		} else {

			if(array_key_exists($this['id'], self::$cProduction) === FALSE) {

				self::$cProduction[$this['id']] = \structure\ProductionLib::getByStructure($this);
				self::$cProduction[$this['id']]->setColumn('structure', $this);

			}

			return self::$cProduction[$this['id']];

		}

	}

	public function getProduction(string $fqn): Production {

		return $this->productions()->find(fn(Production $eProduction) => $eProduction['fqn'] === $fqn, limit: 1, default: new Production());

	}

	public function system(): \structure\System {

		if(array_key_exists($this['id'], self::$eSystem) === FALSE) {

			self::$eSystem[$this['id']] = \structure\SystemLib::getByStructure($this);
			self::$eSystem[$this['id']]['structure'] = $this;

		}

		return self::$eSystem[$this['id']];

	}

	public function getSystem(string $name): mixed {

		return $this->system()[$name];

	}

	/**
	 * La préférence d'affichage qui s'applique à ce lecteur sur cette structure.
	 *
	 * Le défaut vient de `MemberModel::getDefaultValue()`, que le modèle **généré** surcharge :
	 * il rend les vraies valeurs du schéma — `white`, `[]`, `composition` — et NULL pour les
	 * quinze colonnes qui n'ont légitimement pas de défaut. C'est déjà la bonne réponse à
	 * « que voit un lecteur sans adhésion », et une ferme école n'en demande pas d'autre.
	 */
	public function getView(string $name): mixed {

		$eMember = $this->getMember();

		if($eMember->notEmpty()) {
			return $eMember[$name];
		} else {
			return new MemberModel()->getDefaultValue($name);
		}

	}

	public function getViewCached(string $name): ?int {
		return MemberLib::getViewCached($this, $name);
	}

	public function acceptProduction(): bool {
		return (
			$this->isProducer()
		);
	}

	/**
	 * La RH s'éteint avec la production : contrats, absences, heures supplémentaires et récapitulatif ne vivent
	 * qu'à côté du planning. ⚠️ Elle lit donc le drapeau de production, que `acceptProduction()` ne lit pas (cette
	 * dernière dit le type de structure, sur lequel le paramétrage de la production reste ouvert même éteinte) :
	 * les deux méthodes ne s'alignent pas.
	 */
	public function acceptHr(): bool {
		return (
			$this->acceptProduction() and
			$this->conf(Feature::class)['production'] and
			$this->conf(Feature::class)['hasHr']
		);
	}

	public function acceptCultivation(): bool {
		return (
			$this->acceptProduction()
		);
	}

	/**
	 * La structure a-t-elle des séries sur la saison en cours ? C'est ce qui ouvre la production de
	 * l'accueil : sans série, ses blocs n'auraient rien à dire et « Planifiez vos cultures » invite déjà
	 * à commencer.
	 *
	 * La saison précédente compte aussi, parce que ses cultures d'hiver se récoltent encore en janvier
	 * et en février, avant que la nouvelle soit planifiée. Une saison à venir compte de même, qui se
	 * planifie dès l'automne.
	 *
	 * L'accueil la pose pour chacun de ses blocs de production : la réponse se garde par structure.
	 */
	public function acceptSeasonPlanned(): bool {

		$this->expects(['id']);

		if(array_key_exists($this['id'], self::$seasonPlanned) === FALSE) {

			self::$seasonPlanned[$this['id']] = \series\Series::model()
				->whereStructure($this)
				->whereSeason('>=', \Clock::year() - 1)
				->exists();

		}

		return self::$seasonPlanned[$this['id']];

	}

	/**
	 * La mercuriale appartient aux groupes qui rassemblent des producteurs.
	 *
	 * ⚠️ Mesuré avant d'élargir : elle n'était ouverte qu'aux NETWORK. Le contrat la disait
	 * disparaissant d'EDUCATION et d'ACCOUNTING, où elle n'a en fait jamais été ; ce qu'il change
	 * réellement est qu'un PRODUCER y a droit. Un groupe de formation n'a pas de prix à
	 * relever, une structure comptable non plus.
	 */

	public function acceptQuotation(): bool {
		return (
			StructureSetting::hasCommercialisationApp() and
			$this->acceptProduction() and
			$this->isLegal() and
			(
				$this->getProduction('maraichage')->notEmpty() or
				$this->getProduction('legume')->notEmpty() or
				$this->getProduction('arboriculture')->notEmpty() or
				$this->getProduction('ppam')->notEmpty() or
				$this->getProduction('petit-fruit')->notEmpty() or
				$this->getProduction('champignon')->notEmpty()
			)
		);
	}

	public function acceptDuplicate(): bool {
		return $this->isCommunity();
	}

	/**
	 * Un groupe qui héberge les fermes de ses membres.
	 *
	 * Jumelle de acceptGroupQuotation() : les deux disent ce qu'un groupe accepte, et vivent sur
	 * l'élément qui sait y répondre plutôt que dans un Lib qui reçoit la structure.
	 */
	public function acceptGroupStructure(): bool {

		$this->expects(['type', 'communityType']);

		// Un groupe accepte des fermes dès qu'il a un type, et c'est la règle plutôt qu'une liste :
		// énumérer les quatre valeurs d'un enum qui en compte quatre invite le prochain lecteur à
		// croire qu'elle en exclut une. Le seul refus est l'absence de type, que le schéma dit
		// exigée sur un COMMUNITY malgré son « ? » — un groupe sans type est un état incomplet.
		return (
			$this['type'] === self::COMMUNITY and
			$this['communityType'] !== NULL
		);

	}

	/**
	 * Cette fonctionnalité de groupe est-elle allumée ici ?
	 *
	 * ⚠️ Posée sur l'élément parce que les écrans la posent : un `CommunityFeatureAiLib::has()`
	 * appelé depuis une UI est une requête déguisée au sens du CLAUDE.md, invisible de la page qui
	 * croit tout préparer. Même forme que `acceptGroupQuotation()`, qui répondait déjà ainsi.
	 */
	public function acceptCommunityFeature(string $feature): bool {
		return \structure\CommunityFeatureAiLib::has($this, $feature);
	}

	/**
	 * Ce type de groupe peut-il avoir cette fonctionnalité, allumée ou non ?
	 *
	 * Séparée de `acceptCommunityFeature()` pour la même raison que `acceptGroupQuotationSetting()`
	 * l'est de `acceptGroupQuotation()` : l'interrupteur doit rester atteignable une fois éteint.
	 */
	public function acceptCommunityFeatureSetting(string $feature): bool {

		if(
			$feature === 'hasCommunityLearning' and
			$this->acceptLearningSetting() === FALSE
		) {
			return FALSE;
		}

		return \structure\CommunityFeatureAiLib::accepts($this, $feature);

	}

	/**
	 * Les fonctionnalités que ce type de groupe peut porter, allumées ou non.
	 */
	public function getCommunityFeatures(): array {

		return array_values(array_filter(
			array_keys(\structure\CommunityFeatureAiLib::FEATURES),
			fn(string $feature) => $this->acceptCommunityFeatureSetting($feature)
		));

	}

	/**
	 * Les partages qu'un groupe peut proposer, selon son type.
	 *
	 * **Le type dit ce qui est possible, l'affiliation dit ce qui est fait.** Un organisme de
	 * formation ne propose rien : ses fermes école sont des copies et les fermes de ses étudiants
	 * s'affichent sans s'ouvrir. Une case qu'on peut cocher sans effet est pire qu'une case
	 * absente — elle laisse croire à un partage qui n'aura pas lieu.
	 *
	 * Rendue ici et non dans l'UI : l'écran s'en sert pour n'afficher que ce qui existe, et
	 * l'action pour n'accepter que ça — un POST se contourne, la liste doit être la même des deux
	 * côtés.
	 */
	public function getCommunityShares(): array {

		return \structure\CommunityRightAiLib::getRights($this);

	}

	/**
	 * Ce que ce groupe partage **effectivement**, déduit quand le type le rend inhérent.
	 *
	 * ⚠️ À ne pas confondre avec `getCommunityShares()`, qui dit ce qu'on peut **choisir**. Un
	 * groupe de formation ne propose aucune bascule sur la lecture de la production et la
	 * partage pourtant : ses fermes sont celles de ses étudiants, et un enseignant qui ne peut
	 * pas ouvrir le plan de culture de son étudiant n'a aucun moyen de le corriger. Le partage n'y est pas une option,
	 * c'est la raison d'être du groupe — donc il n'y a rien à cocher, et rien à cocher ne veut pas
	 * dire rien de partagé.
	 *
	 * **Déduit et non enregistré** : une propriété inhérente qu'on stocke peut diverger de ce
	 * qu'elle affirme, et elle divergeait déjà — le groupe de formation de la base de dev portait
	 * `shareProduction = 0`, ce qu'aucun écran ne pouvait plus dire sans mentir. Le déduire évite
	 * aussi toute migration : il n'y a rien à corriger dans une donnée que plus personne ne lit.
	 */
	public function hasCommunityShare(string $right): bool {

		return match(\structure\CommunityRightAiLib::getRegime($this, $right)) {

			CommunityRightAiLib::ABSENT => FALSE,
			CommunityRightAiLib::IMPOSED => TRUE,
			CommunityRightAiLib::OPTIONAL => \structure\CommunityRightAiLib::isAsked($this, $right)

		};

	}

	/**
	 * Les publics pour lesquels ce groupe demande ce droit.
	 *
	 * ⚠️ Ce que le groupe **demande**, jamais ce qu'une ferme a accordé : la première vit sur
	 * `Community`, la seconde sur le rattachement et gelée à sa date. L'écran de la ferme borne
	 * ses cases à cette liste, et l'action refuse tout public hors d'elle.
	 */
	public function askedCommunityAudiences(string $right): array {

		return \structure\CommunityRightAiLib::getAsked($this, $right);

	}

	/**
	 * Les droits à public que ce groupe porte, dans l'ordre de la déclaration.
	 *
	 * ⚠️ Ces trois-là existent sur l'élément pour la même raison que `grantsCommunityRight()` :
	 * les écrans posent la question, et un `CommunityRightAiLib::` appelé depuis une UI est une
	 * requête déguisée au sens du CLAUDE.md. Elles ne portent aucune règle propre — la
	 * déclaration reste seule à décider — et n'existent que pour donner à la présentation une
	 * porte qu'elle ait le droit d'ouvrir.
	 */
	public function getCommunityAudienceRights(): array {

		return \structure\CommunityRightAiLib::getAudienceRights($this);

	}

	/**
	 * Le paramétrage de ce groupe a-t-il un partage des données à montrer ? Il se range alors en
	 * onglets, qui nomment l'écran à la place du titre (`StructureUi::update()`).
	 */
	public function acceptCommunitySharing(): bool {

		return (
			$this->isCommunity() and
			$this->getCommunityAudienceRights() !== []
		);

	}

	/**
	 * Ce groupe impose-t-il ce droit, c'est-à-dire le porte-t-il sans qu'on puisse le refuser ?
	 */
	public function imposesCommunityRight(string $right): bool {

		return \structure\CommunityRightAiLib::getRegime($this, $right) === \structure\CommunityRightAiLib::IMPOSED;

	}

	/**
	 * La mise en route de ce groupe pose-t-elle ce droit ?
	 */
	public function acceptCommunityRightStart(string $right): bool {

		return \structure\CommunityRightAiLib::acceptsStart($this, $right);

	}

	public function restrictsCommunityReach(string $right): bool {

		return \structure\CommunityRightAiLib::restrictsReach($this, $right);

	}

	public function reachesCommunityAudience(string $right, int $audience, array $audiencesBringing): bool {

		return \structure\CommunityRightAiLib::reaches($this, $right, $audience, $audiencesBringing);

	}

	public function getCommunityAudience(): int {

		return \structure\CommunityRightAiLib::getAudience($this->getMember()['role']);

	}

	public function requiresCommunityRight(string $right): ?string {

		return \structure\CommunityRightAiLib::getRequired($right);

	}

	/**
	 * Les publics que ce périmètre accorde effectivement dans ce groupe.
	 *
	 * Le masque vient du rattachement et non d'ici : c'est la ferme qui accorde, le groupe ne dit
	 * que sous quel régime la valeur se lit — un droit imposé ouvre à tous ses publics sans que
	 * la colonne soit consultée.
	 */
	public function getGrantedCommunityAudiences(string $right, \Set $granted): array {

		return \structure\CommunityRightAiLib::getGranted($this, $right, $granted);

	}

	/**
	 * Ce groupe porte-t-il des fermes école ?
	 *
	 * Un état de la structure et non un droit : les fermes école ne se rattachent à personne, tout
	 * organisme de formation les voit toutes, et aucun autre type de groupe n'en a. La question ne
	 * pèse donc pas le membre connecté, seulement le type du groupe.
	 *
	 * ⚠️ Elle ne dit rien de `Structure::canSchool()`, qui parle d'une **ferme** école et non du
	 * groupe qui l'affiche. Les deux mots se ressemblent, les sujets non.
	 */
	public function acceptSchool(): bool {

		return \structure\CommunityFeatureAiLib::has($this, 'hasCommunitySchools');

	}

	/**
	 * Ce type de groupe peut ouvrir des relevés de prix.
	 *
	 * Dit ce que le type permet et non ce que le groupe a choisi. Les deux se séparent parce que
	 * l'interrupteur doit rester atteignable une fois éteint : il vit dans le paramétrage du
	 * groupe, où c'est cette question-ci qui décide de l'afficher, quand tout le reste des relevés
	 * interroge `acceptGroupQuotation()` et disparaît.
	 */
	public function acceptGroupQuotationSetting(): bool {

		return \structure\CommunityFeatureAiLib::accepts($this, 'hasQuotation');

	}

	/**
	 * Ce groupe a ses relevés de prix ouverts.
	 *
	 * ⚠️ Le statut compte autant que le type : sans lui, un groupe qui répondait « Désactivés »
	 * gardait son onglet, lequel ouvrait sur une invitation à les activer. Le réglage ne
	 * désactivait rien, il changeait ce que l'écran montrait.
	 */
	public function acceptGroupQuotation(): bool {

		return (
			StructureSetting::hasCommercialisationApp() and
			\structure\CommunityFeatureAiLib::has($this, 'hasQuotation')
		);

	}

	public function acceptVegetable(): bool {
		return (
			$this->acceptProduction() and
			(
				$this->getProduction('maraichage')->notEmpty() or
				$this->getProduction('legume')->notEmpty()
			)
		);
	}

	public function acceptCommercialisation(): bool {
		return (
			StructureSetting::hasCommercialisationApp() and
			(
				$this->isProducer() or
				$this->isAmap() or
				$this->isShop()
			)
		);
	}

	public function acceptAccounting(): bool {
		return (
			StructureSetting::hasAccountingApp() and
			$this->isLegal() and
			$this->isAmap() === FALSE
		);
	}

	public function acceptInvoicing(): bool {
		return (
			$this->exists() and
			$this->isFrance() and
			$this->acceptAccounting()
		);
	}

	public function acceptImportExport(): bool {

		return $this->isLegal();

	}

	/**
	 * Une AMAP fermée ne fait plus entrer d'adhérent, ni par une fiche, ni par le tableur, ni par le
	 * retour d'un retiré : `selling\Customer::acceptCreate()` et `acceptUpdateStatus()` la lisent pour
	 * toutes les routes qui écrivent une fiche.
	 */
	public function acceptNewCustomer(): bool {

		$this->expects(['type', 'status']);

		return (
			$this->isAmap() === FALSE or
			$this->active()
		);

	}

	/** Une AMAP fermée sert la saison qui court sans en poser de nouvelle, ni la première ni la suivante. */
	public function acceptAmapSeason(): bool {

		$this->expects(['status']);

		return $this->active();

	}

	/**
	 * Ce groupe a-t-il **le parcours en trois étapes** de son accueil ?
	 *
	 * La question ne lit **pas** le drapeau `hasStart` : elle dit qui en naît porteur, et c'est
	 * `FeatureLib` qui le pose à la création. Pour savoir si le parcours se rend **maintenant**,
	 * c'est le drapeau qu'on lit. Une AMAP n'en naît pas porteuse : sa mise en route est un bloc de
	 * son tableau de bord, qui disparaît quand elle est faite, et ne demande aucun drapeau.
	 *
	 * ⚠️ **Les trois types de groupe sont écrits, et non déduits d'un « tout sauf »** : une
	 * structure comptable n'a rien à démarrer, ni fonctionnalité à choisir ni membre à inviter au
	 * premier jour, et un cinquième type ajouté demain doit décider plutôt qu'hériter. Le parcours
	 * demande aussi à la classe du type des mots (`farms`, `the`, `of`, `your`) dans un `match` sans
	 * `default`.
	 *
	 * L'appariement type ↔ parcours est tenu par
	 * `CommunityFeatureSentinelAiTest::testEveryStartTypeAnswersItsWords()`.
	 */
	public function acceptCommunityStart(): bool {

		return (
			$this->isCommunity() and
			in_array($this['communityType'], [Structure::EDUCATION, Structure::PRODUCER, Structure::NETWORK], TRUE)
		);

	}

	/**
	 * La page de démarrage se rend-elle ?
	 *
	 * C'est la garde de la route `structure:start`, où arrive toute structure neuve après le choix
	 * de ses productions (`system.p.php`, `doUpdateProductions`). Une ferme y va toujours : son
	 * écran de démarrage ne dépend d'aucun drapeau.
	 *
	 * Un groupe n'y va pas, « pas de page de démarrage pour les COMMUNITY, ils arrivent directement
	 * sur le tableau de bord », décision du 11/09/2026 : il a son parcours. Une AMAP non
	 * plus : sa mise en route est le premier bloc de son tableau de bord, et cette page lui servirait
	 * l'écran d'une ferme.
	 */
	public function acceptStartPage(): bool {
		return ($this->isCommunity() === FALSE and $this->isAmap() === FALSE);
	}

	/**
	 * Le catalogue de la page d'arrivée, tel que l'accueil le propose sous « À essayer ». Une boutique
	 * le reçoit aussi, réduit aux univers qu'elle peut ouvrir (`DashboardLib::getStart()`) : sans lui,
	 * l'accueil d'une boutique qui ne vend pas encore serait vide (L38, A10, tranché par V-Dashboard).
	 * Un groupe et une AMAP ont chacun leur parcours de démarrage, qui n'est pas ce catalogue.
	 */
	public function acceptStartedFeatures(): bool {
		return ($this->isProducer() or $this->isShop());
	}

	public function assertStartedFeatures(): bool {

		return (
			$this->acceptStartedFeatures() and
			$this->canManage()
		);

	}

	public function acceptApi(bool $apiActive = \api\ApiSetting::IS_API_ACTIVE): bool {

		return (
			$this->acceptApiAccess() and (
				$apiActive or
				in_array($this['id'], \structure\StructureSetting::API_STRUCTURE_BETA)
			)
		);

	}

	public function acceptApiWrite(bool $writeActive = \api\ApiSetting::IS_API_WRITE_ACTIVE): bool {

		return (
			$this->acceptApi() and (
				$writeActive or
				in_array($this['id'], \structure\StructureSetting::API_STRUCTURE_BETA)
			)
		);

	}

	/**
	 * Le scope s'ouvre-t-il à cette structure ? La page des clés ne propose que ce qu'il ouvre
	 * (`getApiScopes()`), et l'appel refuse le reste (`ApiData::acceptScope()`) : les deux répondent
	 * par cette seule règle.
	 *
	 * Les formations ont leur porte à elles : `learning:read` et `learning:write` s'ouvrent ensemble à
	 * la structure qui a les formations (`acceptLearning()`), sans attendre que l'écriture des autres
	 * domaines s'ouvre. Tant que `LearningSetting::$active` est faux, ce sont les réseaux de
	 * `LearningSetting::STRUCTURE_BETA` qui ont allumé la fonctionnalité. Quand il passe à vrai,
	 * l'écriture des formations s'ouvre à toute structure qui a la fonctionnalité, hors de toute bêta,
	 * ce qui a été demandé (« ouvrir l'api e-learning à toutes les structures qui ont accès à
	 * cette fonctionnalité »). Les autres domaines suivent `acceptApiWrite()`.
	 *
	 * La comptabilité ne s'ouvre qu'à la structure qui l'a activée (`hasAccounting()`), comme ses
	 * écrans (`StructureLib::validateAccounting()`). L'offre qu'elle demande à l'écran, Gestion, est
	 * celle que l'API exige déjà à chaque appel (`ApiData::authorized()`).
	 */
	public function acceptApiScope(string $scope, bool $writeActive = \api\ApiSetting::IS_API_WRITE_ACTIVE): bool {

		if(str_starts_with($scope, 'learning:')) {
			return ($this->acceptApi() and $this->acceptLearning());
		}

		if(
			str_starts_with($scope, 'accounting:') and
			$this->hasAccounting() === FALSE
		) {
			return FALSE;
		}

		return \api\ApiSetting::isScopeOpen($scope, $this->acceptApiWrite($writeActive));

	}

	public function acceptApiAccess(): bool {
		return ($this->isAmap() === FALSE);
	}

	/**
	 * L'API reste gratuite pour un groupe (16/10/2026) : une structure qui y a accès hors
	 * groupe la reçoit avec une offre logiciel (une AMAP n'y a pas accès, `acceptApiAccess()`).
	 * `acceptFeature(FEATURE_API)`, qui garde les appels et l'autorisation OAuth, la page des clés et
	 * son bandeau d'accueil lisent cette même règle, pour que l'écran n'annonce pas une offre que
	 * l'API n'exige pas.
	 */
	public function isApiPaid(): bool {
		return (StructureSetting::hasSubscriptionPackage() and $this->isCommunity() === FALSE);
	}

	public function acceptSoftware(string $software): bool {
		return StructureLib::acceptSoftware($this, $software);
	}

	public function acceptFeature(string $feature): bool {
		return StructureLib::acceptFeature($this, $feature);
	}

	public function acceptAccrual(): bool {

		return (
			StructureSetting::hasAccountingApp() and
			in_array($this['id'], \financialYear\FinancialYearSetting::ACCRUAL_STRUCTURE_EXCLUDED) === FALSE
		);

	}

	public function acceptProof(): bool {

		return (
			StructureSetting::hasAccountingApp() and
			\proof\ProofSetting::isActive($this)
		);

	}

	public function acceptComposition(): bool {

		return $this->acceptProduction();

	}

	public function acceptLearning(): bool {

		return (
			$this->acceptLearningSetting() and
			$this->acceptCommunityFeature('hasCommunityLearning')
		);

	}

	public function acceptLearningSetting(): bool {

		return (
			\learning\LearningSetting::$active or
			in_array($this['id'], \learning\LearningSetting::STRUCTURE_BETA)
		);

	}

	public function acceptVat(): bool {

		return (
			$this->isLegal() and
			$this->isAmap() === FALSE
		);

	}

	public function acceptPresentation(): bool {

		return (
			$this->isAmap() === FALSE and
			$this->isEducation() === FALSE
		);

	}

	public function acceptLogo(): bool {
		return ($this->isEducation() === FALSE);
	}

	public function acceptBanner(): bool {
		return ($this->isEducation() === FALSE);
	}

	public function acceptRules(): bool {

		return (
			$this->isCommunity() and
			$this->isEducation() === FALSE
		);

	}

	public function acceptJoinWithoutFarm(): bool {
		return $this->isEducation();
	}

	/**
	 * ⚠️ `ModuleApi` n'interroge jamais un `accept*` **nommé**, seulement `acceptUpdate()` : cette
	 * règle n'atteint l'API que parce que `StructureLib::getPropertiesUpdate()` la lit. Recopiée
	 * dans un écran sans passer par cette liste, l'API la perdrait sans bruit.
	 */
	public function acceptUpdateProducerType(): bool {

		$this->expects(['type', 'producerType', 'creation']);

		return (
			$this->isProducer() and
			in_array($this['producerType'], StructureSetting::PRODUCER_TYPES_COLLECTIVE) === FALSE and
			$this['producerType'] !== Structure::REAL and
			$this['producerType'] !== Structure::TRAINING and
			$this['creation'] !== Structure::DUPLICATION
		);

	}

	/**
	 * Fermée sur les catégories posées par un groupe : aucun écran ne repropose une ferme projet,
	 * et son `parent` resterait pointé sur un groupe qui ne la possède plus.
	 */
	public function acceptRequalifyProducerType(): bool {

		$this->expects(['type', 'producerType']);

		return (
			$this->isProducer() and
			in_array($this['producerType'], StructureSetting::PRODUCER_TYPES_COLLECTIVE) === FALSE and
			$this['producerType'] !== Structure::TRAINING
		);

	}

	public function acceptUnits(): bool {
		return ($this->isAmap() === FALSE);
	}

	public function acceptDocuments(): bool {
		return ($this->isAmap() === FALSE);
	}

	public function acceptPaymentSettings(): bool {
		return ($this->isAmap() === FALSE);
	}

	public function acceptWalletSettings(): bool {
		return ($this->isAmap() === FALSE);
	}

	public function acceptExportSettings(): bool {
		return ($this->isAmap() === FALSE);
	}

	public function acceptMarketSettings(): bool {
		return ($this->isAmap() === FALSE);
	}

	public function hasTime(): bool {

		return $this->conf()['time'];

	}

	/**
	 * Les heures de travail de la journée (`hr\WorkingTime`). Le temps de travail les ouvre avec celui des
	 * interventions, le suivi RH sans lui : les heures supplémentaires et le récapitulatif du mois en ont besoin.
	 */
	public function hasWorkingTime(): bool {

		return (
			$this->hasTime() or
			$this->acceptHr()
		);

	}

	// Les horaires sont une forme de la journée : ils valent dès qu'elle se saisit, et éteindre le temps de travail
	// comme le suivi RH ne perd pas ce choix.
	public function hasTimeRange(): bool {

		return (
			$this->hasWorkingTime() and
			$this->conf()['timeRange']
		);

	}

	public function hasAccounting(): bool {

		return (
			StructureSetting::hasAccountingApp() and
			$this->conf(Feature::class)['hasAccounting']
		);

	}

	public function isCashReceipts(string $date): bool {
		return StructureLib::isCashReceipts($this, $date);
	}

	public function getLegalAddress(string $type = 'text'): ?string {

		if($this->hasLegalAddress() === FALSE) {
			return NULL;
		}

		$address = $this['legalStreet1']."\n";
		if($this['legalStreet2'] !== NULL) {
			$address .= $this['legalStreet2']."\n";
		}
		$address .= $this['legalPostcode'].' '.$this['legalCity'];

		if($this['legalCountry']->notEmpty()) {
			$address .= "\n".\user\Country::ask($this['legalCountry'])['name'];
		}

		return ($type === 'text') ? $address : nl2br(encode($address));

	}

	public function hasLegalAddress(): bool {
		return ($this['legalCity'] !== NULL);
	}

	public function getUrl(?string $section): string {

		return match($section) {
			'amap' => $this->getAmapUrl(),
			'dashboard' => \dashboard\DashboardUi::url($this),
			'settings' => $this->getSettingsUrl(),
			'accounting' => $this->getAccountingUrl(),
			'commercialisation' => $this->getCommercialisationUrl(),
			default => $this->getProductionUrl()
		};

	}

	/**
	 * L'accueil du bureau est son tableau de bord, qui porte la mise en route tant qu'elle n'est pas
	 * faite.
	 */
	public function getAmapUrl(): string {
		return StructureUi::urlAmapDashboard($this);
	}

	public function getAccessUrl(): string {

		$section = $this->getAccessSection();

		if($section === NULL) {
			return '';
		}

		return $this->getUrl($section);

	}

	public function getAccessSection(): ?string {

		if(\dashboard\DashboardSetting::isLanding($this)) {
			// assertSection() lève un TypeError sur cette chaîne, acceptFeatureSection() étant un switch sans défaut typé bool
			return 'dashboard';
		}

		return $this->getDefaultSection();

	}

	// 'dashboard' n'est pas une section des onglets. Ce que rend cette méthode se pose en jeton de gabarit
	// (structure-<section>), et celui du tableau de bord masque la barre de navigation de la structure
	public function getDefaultSection(): ?string {

		if($this->isAmap() and $this->canManage()) {
			return 'amap';
		}

		if($this->assertProduction()) {
			return 'production';
		}

		if($this->assertCommercialisation()) {
			return 'commercialisation';
		}

		if($this->assertAccounting()) {
			return 'accounting';
		}

		return NULL;

	}

	public function getMenuSection(?string $sectionCurrent): ?string {

		if(
			$this->isAmap() and
			$this->canAmapCollective() and
			($this->canManage() === FALSE or \dashboard\DashboardSetting::isLanding($this) === FALSE)
		) {
			return 'amap';
		}

		if($sectionCurrent !== NULL and $this->assertSection($sectionCurrent)) {
			return $sectionCurrent;
		}

		if(\dashboard\DashboardSetting::isLanding($this)) {
			return 'dashboard';
		}

		if(
			$this->acceptCommercialisation() and
			$this->canCommercialisation()
		) {
			return 'commercialisation';
		}

		if(
			$this->acceptProduction() and
			$this->canProduction()
		) {
			return 'production';
		}

		if(
			$this->acceptAccounting() and
			$this->canAccounting()
		) {
			return 'accounting';
		}

		return NULL;

	}

	public function getProductionUrl(?int $season = NULL): ?string {

		if($this->canPlanningLimited() or $this->canManage()) {
			return StructureUi::urlPlanningWeekly($this);
		} else {
			return StructureUi::urlCultivationSeries($this, season: $season);
		}

	}

	public function getCommercialisationUrl(): ?string {

		if($this->canSelling() or $this->canManage()) {
			return StructureUi::urlSellingSales($this);
		} else if($this->canAnalyzeCommercialisation()) {
			return StructureUi::urlAnalyzeCommercialisation($this);
		} else {
			return NULL;
		}

	}

	/**
	 * Où mène la section Gestion : la première page que celui qui entre a le droit de voir.
	 *
	 * La banque pour qui a un accès comptable, la synthèse pour qui n'a que le droit de
	 * la consulter. Sans cette distinction, la page d'accueil et le bouton « Gestion » envoyaient ce
	 * dernier sur un écran que sa garde refuse, et le parcours s'arrêtait là.
	 */
	public function getAccountingUrl(): string {

		if($this->canAccounting()) {
			return StructureUi::urlBankOperations($this);
		}

		if($this->canAnalyzeAccounting()) {
			return StructureUi::urlOverview($this);
		}

		return StructureUi::urlInvoicingBuy($this);

	}

	public function getSettingsUrl(): string {
		return StructureUi::urlStructureGeneral($this);
	}

	public function getCampaignLimit(): int {
		return StructureLib::getCampaignLimit($this);
	}

	public static function getQualities(): array {

		return [
			Structure::DEMETER => 'demeter',
			Structure::NATURE_PROGRES => 'nature-progres',
			Structure::BIO_COHERENCE => 'bio-coherence',
		];

	}

	public static function getCampaignBasicLimit(): int {
		return 50;
	}

	public static function getCampaignMemberLimit(): int {
		return 2000;
	}

	public static function getCampaignExpertLimit(): int {
		return 5000;
	}

	public function getContactLimit(): int {
		return StructureLib::getContactLimit($this);
	}

	public static function getContactMemberLimit(): int {
		return 3;
	}

	public static function properties(\PropertyList $l): void {

		$l->property('producerType')
			->addAfterCast('chosen', fn(?string $producerType) => (
				$this['type'] !== Structure::PRODUCER or
				$producerType !== NULL
			))
			->addAfterCast('type', function(?string &$producerType): bool {

				if($this['type'] !== Structure::PRODUCER) {
					$producerType = NULL;
					return TRUE;
				} else {
					// ⚠️ Les types qu'une ferme choisit pour elle-même, et non l'enum entier : PROJECT et
					// SCHOOL sont posés par le groupe qui l'affilie, jamais postés. Un type ajouté au
					// schéma et offert à l'écran se déclare donc ici aussi, sinon il est refusé.
					return in_array($producerType, [Structure::REAL, Structure::TEST, Structure::LEARNER, Structure::INSTALLATION]);
				}

			});

		$l->property('communityType')
			->addAfterCast('type', function(?string &$communityType): bool {

				if($this['type'] !== Structure::COMMUNITY) {
					$communityType = NULL;
					return TRUE;
				} else {
					// ⚠️ La liste des types créables, et non l'enum entier : ACCOUNTING y figure encore
					// mais aucun écran ne le crée, et un POST forgé ne doit pas ouvrir un type que
					// le produit ne sait pas servir.
					return in_array($communityType, [Structure::PRODUCER, Structure::EDUCATION, Structure::NETWORK]);
				}

			});

		$l->property('isLegal')
			->addAfterCast('type', function(mixed &$isLegal) {

				switch($this['type']) {

					case Structure::PRODUCER :
					case Structure::AMAP :
						$isLegal = TRUE;
						break;

					case Structure::SHOP :
						$isLegal = (bool)$isLegal;
						break;

					case Structure::COMMUNITY :
						$isLegal = FALSE;
						break;

				}

			});

		$l->property('legalCountry')
			->addAfterCast('legal', function(\user\Country &$eCountry): bool {

				if($this->isCommunity()) {
					$eCountry = new \user\Country();
					return TRUE;
				}

				if(
					$this->isLegal() and
					$this->isVerifiedCountry()
				) {
					return FALSE;
				}

				return \user\Country::model()->exists($eCountry);

			});

		$l->property('legalCategory')
			->addAfterCheck('known', function(?int $category): bool {

				return ($category !== NULL and in_array($category, StructureSetting::CATEGORIES_JURIDIQUES, TRUE));

			});

		$l->property('legalEmail')
			->addAfterCheck('empty', function(?string $email) {
				return ($email !== NULL);
			});

		$l->property('legalName')
			->addAfterCheck('empty', function(?string $legalName) {
				return ($legalName !== NULL);
			});

		$l->property('siret')
			->replaceCheck(fn(?string &$siret) => Structure::checkSiret($siret))
			->addAfterCheck('empty', function(?string &$siret) {

				if($this->isFrance()) {
					return ($siret !== NULL);
				} else {
					$siret = NULL;
					return TRUE;
				}

			});

		$l->property('legalCity')
			->addAfterCheck('empty', function(?string $legalCity) {

				if($this['legalStreet1'] === NULL) {
					Structure::fail('legalStreet1.empty');
				}

				if($this['legalPostcode'] === NULL) {
					Structure::fail('legalPostcode.empty');
				}

				return ($legalCity !== NULL);

			});

		$l->property('organic')
			->addAfterCast('required', function(?string $organic): bool {
				// Dès lors que organic est demandé, il ne peut être NULL
				return ($organic !== NULL);
			});

		$l->property('qualities')
			->addAfterCast('default', function(?\Set &$qualities): bool {

				$qualities ??= new \Set();

				return TRUE;

			});

	}

	public static function prepareSiret(\Element $e, ?string &$siret) {

		$e->expects(['type', 'invoiceCountry']);

		if(
			$e['type'] === \user\User::PRIVATE or
			$e['invoiceCountry']->isFrance() === FALSE
		) {
			$siret = NULL;
		}

	}

	public static function checkSiret(?string &$siret): bool {

		if($siret === NULL) {
			return TRUE;
		} else {
			$siret = preg_replace('/\s+/i', '', $siret);
			return preg_match('/^[0-9]{14}$/', $siret) > 0;
		}

	}

	public static function checkSiren(?string &$siren): bool {

		if($siren === NULL) {
			return TRUE;
		} else {
			$siren = preg_replace('/\s+/i', '', $siren);
			return preg_match('/^[0-9]{9}$/', $siren) > 0;
		}

	}

	public static function checkVatNumber(string $element, \Element $e, ?string &$vat, bool $isNullable): bool {

		if($e->isFrance()) {

			if($vat === NULL) {
				if($isNullable) {
					return TRUE;
				} else {
					\Fail::log($element.'::vatNumber.check', ['FR']);
					return TRUE;
				}
			}
			
			$vat = preg_replace('/\s+/i', '', $vat ?? '');
			$vat = strtoupper($vat);

			if(preg_match('/^FR[0-9a-z]{2}[0-9]{9}$/i', $vat) === 0) {
				\Fail::log($element.'::vatNumber.check', ['FR']);
			}

			return TRUE;

		} else if($e->isBelgium()) {

			if($vat === NULL) {
				if($isNullable) {
					return TRUE;
				} else {
					\Fail::log($element.'::vatNumber.check', ['BE']);
					return TRUE;
				}
			}

			$vat = preg_replace('/\s+/i', '', $vat);
			$vat = strtoupper($vat);

			if(preg_match('/^BE[01]{1}[0-9]{9}$/', $vat) === 0) {
				\Fail::log($element.'::vatNumber.check', ['BE']);
			}

		} else if($e->isEuropeanUnion()) {
			return TRUE;
		} else {

			if($vat !== NULL) {

				if($e->hasVatCountry()) {
					\Fail::log($element.'::vatNumber.country');
				} else {
					\Fail::log($element.'::vatNumber.noCountry');
				}

			}

		}

		return TRUE;

	}

}
?>
