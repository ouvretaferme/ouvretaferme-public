<?php
namespace structure;

/**
 * Ce que le package `structure` apporte au centre de notifications, hors affichage : les arrivées et
 * les départs d'une équipe, et ceux des fermes d'un groupe.
 *
 * Le contrat de la paire `NotificationLib` + `NotificationUi` est écrit dans
 * `base/notification/CLAUDE.md` et tenu par `base/notification/test/NotificationTypeSentinel.t.php`.
 *
 * ⚠️ Le nom de cette classe est fixe, parce que c'est lui que la convention résout depuis le segment
 * qui précède le premier tiret du type (`structure-member-joined` → `structure`) : elle ne porte donc
 * pas le suffixe `Ai` des fichiers écrits par une IA (décidé le 2026-09-20, « Sans
 * suffixe, commentaires Ai »).
 *
 * Tout ce que ces types relisent vit dans la base commune (adhésions, rattachements, utilisateurs) :
 * aucune relecture ne touche une base `structure_<id>`, et chacune est bornée par la structure qui
 * reçoit, un identifiant seul désignant la ligne de n'importe quel tenant.
 */
class NotificationLib {

	/**
	 * Une personne entre dans l'équipe, par une invitation acceptée. `reference` porte l'adhésion.
	 * Obligatoire : une équipe qui s'agrandit se sait toujours, c'est un accès qui s'ouvre.
	 */
	const string MEMBER_JOINED = 'structure-member-joined';

	/**
	 * Une personne quitte l'équipe d'un groupe d'elle-même (`MemberLib::leave()`, que seul un groupe
	 * permet). `reference` porte l'utilisateur, l'adhésion n'existant plus au traitement. Obligatoire,
	 * pour la même raison qu'une arrivée.
	 */
	const string MEMBER_LEFT = 'structure-member-left';

	/**
	 * Une ferme est rattachée au groupe. `reference` porte la ferme : un rattachement par associé
	 * (`AffiliationAiLib`), et c'est la ferme qui entre, pas la ligne.
	 */
	const string PRODUCER_JOINED = 'structure-producer-joined';

	/**
	 * Une ferme se retire d'elle-même du groupe. `reference` porte la ferme.
	 */
	const string PRODUCER_LEFT = 'structure-producer-left';

	/**
	 * Un groupe retire une ferme, reçu par la ferme. `reference` porte le groupe. Obligatoire : la
	 * ferme apprend qu'un groupe ne lit plus ses données, ce qu'elle ne peut pas vouloir ignorer.
	 */
	const string PRODUCER_EXCLUDED = 'structure-producer-excluded';

	/**
	 * Les types que ce package émet (le détail des clés est dans `shop\NotificationLib::getTypes()`
	 * et `notification\NotificationLib::getTypes()`).
	 *
	 * Tous réservés à qui administre : c'est lui qui gère l'équipe et les rattachements.
	 *
	 * `production` pour tous : c'est la fonctionnalité que porte toute structure qui a une équipe, les
	 * groupes compris (mesuré en dev le 2026-09-21, les 58 groupes l'ont). Une ferme qui l'a coupée
	 * (27 structures sur 6 459) ne reçoit donc pas le retrait d'un groupe, écart accepté au plan.
	 *
	 * Bornés au type de structure qui les vit : l'équipe d'une structure s'agrandit partout, mais seul
	 * un groupe se quitte d'un geste et reçoit des fermes (`MemberLib::leave()`,
	 * `AffiliationAiLib::create()`), et seule une ferme en est retirée.
	 *
	 * `once` à FALSE pour un départ, et pour une arrivée de ferme à TRUE : partir est un geste unique
	 * dont un aller-retour dans le mois compte deux fois, alors qu'une ferme rattachée par un second
	 * associé n'entre pas une seconde fois. L'émission de ce dernier cas est déjà gardée par
	 * `AffiliationAiLib::create()`, la déduplication du moteur couvre le rejeu.
	 */
	public static function getTypes(): array {

		$declaration = [
			'right' => 'admin',
			'feature' => 'production',
			'family' => \notification\NotificationSetting::STRUCTURE,
			'values' => [\notification\NotificationSetting::OFF, \notification\NotificationSetting::ON],
			'default' => \notification\NotificationSetting::ON
		];

		return [
			self::MEMBER_JOINED => $declaration + [
				'once' => TRUE,
				'mandatory' => TRUE
			],
			self::MEMBER_LEFT => $declaration + [
				'structureTypes' => [Structure::COMMUNITY],
				'once' => FALSE,
				'mandatory' => TRUE
			],
			self::PRODUCER_JOINED => $declaration + [
				'structureTypes' => [Structure::COMMUNITY],
				'once' => TRUE
			],
			self::PRODUCER_LEFT => $declaration + [
				'structureTypes' => [Structure::COMMUNITY],
				'once' => FALSE
			],
			self::PRODUCER_EXCLUDED => $declaration + [
				'structureTypes' => [Structure::PRODUCER],
				'once' => FALSE,
				'mandatory' => TRUE
			]
		];

	}

	public static function memberJoined(Member $eMember): void {

		\notification\NotificationLib::emit($eMember['structure'], self::MEMBER_JOINED, $eMember['id']);

	}

	public static function memberLeft(Member $eMember): void {

		\notification\NotificationLib::emit($eMember['structure'], self::MEMBER_LEFT, $eMember['user']['id']);

	}

	public static function producerJoined(Structure $eStructureProducer, Structure $eStructureCommunity): void {

		\notification\NotificationLib::emit($eStructureCommunity, self::PRODUCER_JOINED, $eStructureProducer['id']);

	}

	public static function producerLeft(Structure $eStructureProducer, Structure $eStructureCommunity): void {

		\notification\NotificationLib::emit($eStructureCommunity, self::PRODUCER_LEFT, $eStructureProducer['id']);

	}

	public static function producerExcluded(Structure $eStructureProducer, Structure $eStructureCommunity): void {

		\notification\NotificationLib::emit($eStructureProducer, self::PRODUCER_EXCLUDED, $eStructureCommunity['id']);

	}

	/**
	 * Relit l'objet au moment du traitement, et vérifie que ce qu'on annonce est encore vrai : une
	 * ferme revenue avant le passage de la file n'est pas annoncée partie, une personne ressortie
	 * n'est pas annoncée entrée.
	 *
	 * Le contenu porte tout ce que l'écran montre, le type de la structure qui reçoit compris (le mot
	 * « réseau » ou « groupe » en dépend) : `NotificationUi` ne relit rien.
	 */
	public static function describe(string $type, Structure $eStructure, ?int $reference): \notification\Notification {

		$content = match($type) {
			self::MEMBER_JOINED => self::describeMemberJoined($eStructure, $reference),
			self::MEMBER_LEFT => self::describeMemberLeft($eStructure, $reference),
			self::PRODUCER_JOINED => self::describeProducerJoined($eStructure, $reference),
			self::PRODUCER_LEFT => self::describeProducerLeft($eStructure, $reference),
			self::PRODUCER_EXCLUDED => self::describeProducerExcluded($eStructure, $reference)
		};

		if($content === NULL) {
			return new \notification\Notification();
		}

		return new \notification\Notification([
			'group' => NULL,
			'content' => $content + [
				'structureType' => $eStructure['type'],
				'communityType' => $eStructure['communityType']
			]
		]);

	}

	private static function describeMemberJoined(Structure $eStructure, ?int $reference): ?array {

		$eMember = Member::model()
			->select([
				'user' => ['firstName', 'lastName'],
				'role' => ['name']
			])
			->whereId($reference)
			->whereStructure($eStructure)
			->whereStatus(Member::IN)
			->get();

		if($eMember->empty()) {
			return NULL;
		}

		return [
			'firstName' => $eMember['user']['firstName'],
			'lastName' => $eMember['user']['lastName'],
			'role' => $eMember['role']['name']
		];

	}

	/**
	 * La personne n'a plus d'adhésion active au groupe : revenue entre le départ et le traitement, elle
	 * n'est pas annoncée partie.
	 */
	private static function describeMemberLeft(Structure $eStructure, ?int $reference): ?array {

		$eUser = \user\User::model()
			->select(['id', 'firstName', 'lastName'])
			->whereId($reference)
			->get();

		if(
			$eUser->empty() or
			MemberLib::isMember($eUser, $eStructure)
		) {
			return NULL;
		}

		return [
			'firstName' => $eUser['firstName'],
			'lastName' => $eUser['lastName']
		];

	}

	/**
	 * La ferme est encore rattachée. Ses productions ne s'écrivent que si la ferme les ouvre à
	 * l'équipe du groupe, la seule qui reçoit ce type : c'est la règle de la colonne des fermes
	 * (`ProfileAiLib::prepareRailProductions()`), et une notification ne doit pas en montrer plus.
	 */
	private static function describeProducerJoined(Structure $eStructure, ?int $reference): ?array {

		$cAffiliation = Affiliation::model()
			->join(Member::model(), 'm1.user = m2.user AND m2.structure = m1.structureCommunity')
			->join(Role::model(), 'm2.role = m3.id')
			->select([
				'shareProduction',
				'structureProducer' => ['name'],
				'fqnBringing' => new \Sql('m3.fqn')
			])
			->where('m1.structureProducer', $reference)
			->where('m1.structureCommunity', $eStructure)
			->getCollection();

		if($cAffiliation->empty()) {
			return NULL;
		}

		$eStructureProducer = $cAffiliation->first()['structureProducer'];

		$shared = FALSE;

		foreach($cAffiliation as $eAffiliation) {

			if(CommunityRightAiLib::grants($eStructure, 'shareProduction', $eAffiliation['shareProduction'], Community::STAFF, [CommunityRightAiLib::getAudience(new Role(['fqn' => $eAffiliation['fqnBringing']]))])) {
				$shared = TRUE;
			}

		}

		$productions = [];

		if($shared) {

			$ccProduction = ProductionLib::getByStructures(new \Collection([$eStructureProducer['id'] => $eStructureProducer]));

			if($ccProduction->offsetExists($eStructureProducer['id'])) {
				$productions = $ccProduction[$eStructureProducer['id']]
					->find(fn(Production $eProduction) => $eProduction['status'] === Production::ACTIVE)
					->getColumn('fqn');
			}

		}

		return [
			'structure' => $eStructureProducer['name'],
			'productions' => $productions
		];

	}

	/**
	 * Plus aucun rattachement du couple : une ferme revenue avant le traitement n'est pas annoncée
	 * partie.
	 */
	private static function describeProducerLeft(Structure $eStructure, ?int $reference): ?array {

		if(AffiliationAiLib::isAffiliated(new Structure(['id' => $reference]), $eStructure)) {
			return NULL;
		}

		$eStructureProducer = StructureLib::getById($reference);

		if($eStructureProducer->empty()) {
			return NULL;
		}

		return [
			'structure' => $eStructureProducer['name']
		];

	}

	private static function describeProducerExcluded(Structure $eStructure, ?int $reference): ?array {

		if(AffiliationAiLib::isAffiliated($eStructure, new Structure(['id' => $reference]))) {
			return NULL;
		}

		$eStructureCommunity = StructureLib::getById($reference);

		if(
			$eStructureCommunity->empty() or
			$eStructureCommunity['type'] !== Structure::COMMUNITY
		) {
			return NULL;
		}

		return [
			'community' => $eStructureCommunity['name']
		];

	}

	public static function getAmount(\notification\Notification $eNotification): ?float {

		return NULL;

	}

	public static function getGroupName(\notification\Notification $eNotification): ?string {

		return NULL;

	}

	/**
	 * L'équipe pour une personne, les fermes du groupe pour une ferme. Côté ferme, le groupe qui l'a
	 * retirée ne se lit plus forcément : le clic mène à l'accueil de la ferme.
	 *
	 * ⚠️ Construite depuis la structure qui reçoit (`eStructure`), jamais depuis la structure
	 * connectée : le clic se traite sur une route du moteur, qui n'est d'aucune structure. Son type
	 * se lit dans le contenu, qui le porte pour les mots de l'écran : l'adresse d'une équipe n'est
	 * pas la même pour une ferme et pour un groupe.
	 */
	public static function getUrl(\notification\Notification $eNotification): string {

		$eStructure = new Structure([
			'id' => $eNotification['eStructure']['id'],
			'type' => $eNotification['content']['structureType']
		]);

		return match($eNotification['type']) {
			self::MEMBER_JOINED, self::MEMBER_LEFT => StructureUi::urlMembers($eStructure),
			self::PRODUCER_JOINED, self::PRODUCER_LEFT => StructureUi::urlCommunityFarms($eStructure),
			self::PRODUCER_EXCLUDED => StructureUi::url($eStructure)
		};

	}

}
?>
