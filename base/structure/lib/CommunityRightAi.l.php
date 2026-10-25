<?php
namespace structure;

/**
 * Ce qu'un groupe peut demander aux fermes qui le rejoignent, et à qui.
 *
 * ⚠️ **Un droit n'est pas un booléen, c'est un public.** Une ferme peut ouvrir sa production à
 * l'équipe qui anime le groupe sans l'ouvrir aux autres fermes, et l'inverse. Les colonnes portent
 * donc un `set(STAFF, MEMBER)` et non un `bool` — une colonne par droit, quel que soit le nombre de
 * publics, parce que d'autres droits viendront et qu'une colonne par couple droit-public en
 * demanderait deux à chaque fois.
 *
 * ⚠️ **Des régimes et non un booléen, et c'est ce qui justifie ce fichier.** La déclaration
 * qu'il remplace (`CommunityFeatureAiLib::TYPES['shares']`) ne savait dire que « demandable » ou
 * non : l'obligation de partager en formation vivait donc en dur dans `Structure::hasCommunityShare()`,
 * hors de toute déclaration, invisible à qui lisait la table. Un `if` sur `communityType` ailleurs
 * dans le produit est le signe qu'un cas manque ici, où il s'ajoute en régime.
 *
 * ⚠️ **Deux natures de droit.** Le partage de production ouvre une lecture, donc il a un public.
 * La contribution aux relevés de prix n'en ouvre aucune — les prix n'y entrent qu'agrégés, et
 * personne ne lit celui d'une ferme —, donc lui demander « qui peut voir » n'aurait pas de réponse.
 * La déclaration porte cette nature plutôt que de lui inventer un public qu'on cocherait pour rien.
 */
class CommunityRightAiLib {

	/** Ce droit ouvre une lecture, donc il se donne à des publics. */
	const AUDIENCE = 'audience';

	/** Ce droit n'ouvre aucune lecture : il se donne ou non, sans public. */
	const SILENT = 'silent';

	/** Ce type de groupe n'a pas ce droit : il n'apparaît nulle part, pas même barré. */
	const ABSENT = 'absent';

	/** Accordé d'office à tous les publics du droit, affiché en lecture avec sa raison. */
	const IMPOSED = 'imposed';

	/** Le groupe demande, et la ferme accorde à l'intérieur de ce qui est demandé. */
	const OPTIONAL = 'optional';

	/**
	 * Les publics d'un droit qui en a, dans l'ordre où les écrans les montrent.
	 *
	 * L'équipe d'abord : c'est elle qui anime, et c'est le public que la ferme accorde le plus
	 * volontiers. Les valeurs sont celles du `set` des deux tables, engendrées dans les `.m.php`.
	 */
	const AUDIENCES = [Community::STAFF, Community::MEMBER];

	/**
	 * La mise en route d'un réseau ne demande la production des fermes que si Maraîchage est allumé.
	 *
	 * ⚠️ Parmi les entrées de `CommunityFeatureAiLib::FEATURES`, c'est la seule qui lit ces droits
	 * (`CommunityAction::prepareVegetable()`). Les relevés de prix lisent `contributesQuotation`,
	 * qui n'a pas de public et n'entre donc pas dans l'étape, et les formations ne lisent aucun
	 * droit. L'onglet Fermes et sa comparaison (`prepareCompare()`) les lisent aussi, mais ils sont
	 * le socle de tout réseau et ne sont pas une fonctionnalité qu'on allume : la question reste
	 * posée par le paramétrage, et un réseau qui n'a allumé que ses formations n'a pas à y répondre
	 * au démarrage (décidé par V-Learning le 12/10, Q2 A de la mise en route). La règle ne vaut que
	 * pour le réseau : une communauté de producteurs et un groupe de formation gardent leur
	 * parcours, et leur ajouter cette condition est une décision.
	 */
	const START_WITH_VEGETABLE = [
		Structure::NETWORK => ['hasCommunityVegetable'],
	];

	/**
	 * Chaque droit, sa nature, et ce que chaque type de groupe en fait.
	 *
	 * ⚠️ **La clé est le nom de la colonne**, sur `structureCommunity` comme sur
	 * `structureAffiliation`, qui la portent sous le même nom. Un second vocabulaire aurait demandé
	 * une table d'appariement, donc un inventaire de plus à tenir juste ; c'est aussi ce qui permet
	 * à la sentinelle de vérifier que la colonne existe encore.
	 *
	 * Six clés facultatives disent ce qu'un `if` sur le nom du droit dirait ailleurs, et que
	 * personne ne retrouverait en lisant cette table :
	 * - `audiences` : les publics qui peuvent recevoir le droit, `AUDIENCES` sinon ;
	 * - `requires` : le droit sans lequel celui-ci n'a pas de sens, pour le même public. L'écran de
	 *   la ferme le verrouille tant que le parent n'est pas accordé, et retirer le parent le retire ;
	 * - `enabledBy` : la colonne booléenne de `Community` sans laquelle le droit est absent du
	 *   groupe, quel que soit son type ;
	 * - `reach` : par type de groupe, puis par public de celui qui a apporté la ferme, les publics
	 *   que la ferme peut atteindre. Un public qui n'y figure pas atteint tous les publics
	 *   accordés. La portée suit le rôle **courant** de l'apporteur et non celui qu'il avait au
	 *   rattachement : un étudiant devenu administrateur ouvre ses fermes à tout le groupe ;
	 * - `imposedTo` : par type de groupe où le droit est imposé, les publics qui le reçoivent,
	 *   ceux du droit sinon. Contrairement à `reach`, la réponse ne dépend pas de qui a apporté la
	 *   ferme ;
	 * - `startWith` : par type de groupe, les fonctionnalités dont l'une au moins doit être
	 *   allumée pour que la mise en route pose ce droit. Le paramétrage le pose toujours : la clé
	 *   dit quand la question vaut d'être posée au démarrage, pas ce que le groupe peut demander.
	 */
	const RIGHTS = [

		// ⚠️ Imposé en formation, et sur les deux publics : la ferme ne choisit rien. Ce qui
		// atteint les étudiants se règle par `reach`, et non par le régime, parce que la réponse
		// dépend de qui a apporté la ferme et pas de ce qu'elle accorde.
		'shareProduction' => [
			'nature' => self::AUDIENCE,
			'types' => [
				Structure::PRODUCER => self::OPTIONAL,
				Structure::NETWORK => self::OPTIONAL,
				Structure::EDUCATION => self::IMPOSED,
				Structure::ACCOUNTING => self::ABSENT,
			],
			'startWith' => self::START_WITH_VEGETABLE,
			// Les étudiants ne se lisent pas entre eux : une ferme apportée par un étudiant
			// n'atteint que l'équipe, celle de l'équipe atteint tout le groupe (décidé
			// le 25/09/2026, « retire le droit, les étudiants ne se voient pas entre eux »).
			'reach' => [
				Structure::EDUCATION => [
					Community::MEMBER => [Community::STAFF],
				],
			],
		],

		// ⚠️ Imposé en formation, et aux seuls formateurs, comme la duplication (décidé
		// le 27/09/2026, « shareProductionExport devrait être autorisé aux formateur au même titre
		// que shareProductionDuplicate ») : les étudiants ne copient rien. Il y était absent depuis
		// le 09/09/2026, et l'écran reprochait alors à chaque ferme d'étudiant de ne pas l'avoir
		// ouvert. Le régime imposé ne lit pas la colonne, que la migration du 09/09 a éteinte.
		'shareProductionExport' => [
			'nature' => self::AUDIENCE,
			'requires' => 'shareProduction',
			'types' => [
				Structure::PRODUCER => self::OPTIONAL,
				Structure::NETWORK => self::OPTIONAL,
				Structure::EDUCATION => self::IMPOSED,
				Structure::ACCOUNTING => self::ABSENT,
			],
			'startWith' => self::START_WITH_VEGETABLE,
			'imposedTo' => [
				Structure::EDUCATION => [Community::STAFF],
			],
		],

		// ⚠️ Pour l'équipe seule. Le public des producteurs n'est pas refusé : il est sans objet, et
		// les écrans rendent un tiret à sa place. Imposé en formation, comme la lecture (décidé par
		// le 25/09/2026, « les droits de lecture du plan de culture et de duplication sont
		// automatiquement accordés ») : la colonne des rattachements écrits depuis y vaut 0, et un
		// retour au régime au choix les ferait toutes refuser d'un coup.
		'shareProductionDuplicate' => [
			'nature' => self::AUDIENCE,
			'audiences' => [Community::STAFF],
			'requires' => 'shareProduction',
			'types' => [
				Structure::PRODUCER => self::ABSENT,
				Structure::NETWORK => self::OPTIONAL,
				Structure::EDUCATION => self::IMPOSED,
				Structure::ACCOUNTING => self::ABSENT,
			],
			'startWith' => self::START_WITH_VEGETABLE,
		],

		// ⚠️ Fermé partout tant que `hasSellingDuplicate` n'est pas posé en base sur le groupe :
		// l'ouverture se décide groupe par groupe, hors de tout écran. Il ne dépend d'aucun autre
		// droit, les produits et les ventes ne passant pas par le plan de culture.
		'shareSellingDuplicate' => [
			'nature' => self::AUDIENCE,
			'audiences' => [Community::STAFF],
			'enabledBy' => 'hasSellingDuplicate',
			'types' => [
				Structure::PRODUCER => self::ABSENT,
				Structure::NETWORK => self::OPTIONAL,
				Structure::EDUCATION => self::OPTIONAL,
				Structure::ACCOUNTING => self::ABSENT,
			],
		],

		'contributesQuotation' => [
			'nature' => self::SILENT,
			'types' => [
				Structure::PRODUCER => self::OPTIONAL,
				Structure::NETWORK => self::OPTIONAL,
				Structure::EDUCATION => self::ABSENT,
				Structure::ACCOUNTING => self::ABSENT,
			],
		],

	];

	/**
	 * Ce que ce type de groupe fait de ce droit.
	 *
	 * ⚠️ Répond pour **toute** chaîne et non pour les seules clés déclarées : le nom vient parfois
	 * d'un POST, et le refus est ce que l'appelant attend d'une valeur forgée — pas une exception
	 * sur une clé absente.
	 */
	public static function getRegime(Structure $eStructureCommunity, string $right): string {

		$eStructureCommunity->expects(['type', 'communityType']);

		if(array_key_exists($right, self::RIGHTS) === FALSE) {
			return self::ABSENT;
		}

		if($eStructureCommunity['type'] !== Structure::COMMUNITY or $eStructureCommunity['communityType'] === NULL) {
			return self::ABSENT;
		}

		$regime = self::RIGHTS[$right]['types'][$eStructureCommunity['communityType']];

		// ⚠️ La ligne `Community` ne se lit qu'une fois le type ouvert, et pour le seul droit qui en
		// dépend : les autres ne coûtent aucune requête. La comparaison porte sur ce qui ouvre, pour
		// la raison écrite sur `accepts()`.
		if(
			$regime !== self::ABSENT and
			array_key_exists('enabledBy', self::RIGHTS[$right]) and
			$eStructureCommunity->community()[self::RIGHTS[$right]['enabledBy']] !== TRUE
		) {
			return self::ABSENT;
		}

		return $regime;

	}

	/**
	 * Ce droit existe-t-il sur ce groupe, imposé ou au choix ?
	 *
	 * ⚠️ La comparaison porte sur ce qui **ouvre**, jamais sur ce qui refuse. Écrite `!== ABSENT`,
	 * elle traiterait toute valeur inconnue comme ouvrante : un quatrième régime ajouté demain, ou
	 * une faute de frappe dans la table, ouvrirait le droit au type qui ne devait pas l'avoir. La
	 * forme ci-dessous se trompe dans l'autre sens, et un manque se voit quand un accès de trop ne
	 * se voit pas.
	 */
	public static function accepts(Structure $eStructureCommunity, string $right): bool {

		return in_array(self::getRegime($eStructureCommunity, $right), [self::IMPOSED, self::OPTIONAL], TRUE);

	}

	/**
	 * La mise en route de ce groupe pose-t-elle ce droit ?
	 *
	 * Ne dit rien de ce que le groupe peut demander : `accepts()` en décide, et le paramétrage le
	 * pose toujours. Un type que `startWith` ne nomme pas pose le droit dès qu'il l'a.
	 */
	public static function acceptsStart(Structure $eStructureCommunity, string $right): bool {

		if(self::accepts($eStructureCommunity, $right) === FALSE) {
			return FALSE;
		}

		$features = self::RIGHTS[$right]['startWith'][$eStructureCommunity['communityType']] ?? [];

		if($features === []) {
			return TRUE;
		}

		foreach($features as $feature) {

			if(CommunityFeatureAiLib::has($eStructureCommunity, $feature)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Ce droit se règle-t-il par le groupe ?
	 */
	public static function isOptional(Structure $eStructureCommunity, string $right): bool {

		return self::getRegime($eStructureCommunity, $right) === self::OPTIONAL;

	}

	/**
	 * Le droit sans lequel celui-ci n'a pas de sens, NULL s'il n'en a pas.
	 */
	public static function getRequired(string $right): ?string {

		return self::RIGHTS[$right]['requires'] ?? NULL;

	}

	/**
	 * Les droits qui n'ont pas de sens sans celui-ci.
	 */
	public static function getRequiring(string $right): array {

		return array_keys(array_filter(
			self::RIGHTS,
			fn(array $definition) => ($definition['requires'] ?? NULL) === $right
		));

	}

	/**
	 * Une ferme peut-elle accorder ce droit à ce public, compte tenu de ce qu'elle accorde déjà ?
	 *
	 * `$granted` rend, pour un droit, les publics que la ferme accorde aujourd'hui : la page le lit
	 * sur la ferme telle qu'elle la voit, ce Lib n'a pas à savoir d'où.
	 */
	public static function acceptsGrant(string $right, int $audience, \Closure $granted): bool {

		$required = self::getRequired($right);

		return ($required === NULL or in_array($audience, $granted($required), TRUE));

	}

	/**
	 * Ce qu'écrit une bascule de la ferme : les publics du droit touché, un public ajouté ou retiré.
	 *
	 * ⚠️ Les dépendants ne sont pas ici : retirer la lecture retire ses copies dans
	 * `AffiliationAiLib::setRights()`, par où passent toutes les écritures d'un rattachement
	 * existant, et non dans ce calcul que seule la bascule appelle.
	 */
	public static function getGrantChange(string $right, int $audience, bool $on, \Closure $granted): array {

		$audiences = $on
			? [...$granted($right), $audience]
			: array_diff($granted($right), [$audience]);

		return [$right => array_values(array_unique($audiences))];

	}

	/**
	 * Les publics d'un droit, réduits à ceux qui ont aussi le droit qu'il suppose.
	 *
	 * ⚠️ **Retirer suit, accorder ne suit pas.** Une copie perd les publics qui n'ont plus la
	 * lecture ; accorder la lecture ne rend pas la copie, ce sont deux décisions. `$granted` lit
	 * le droit supposé sous son régime : un groupe de formation impose la lecture sans que la
	 * colonne la porte, et la lire nue retirerait toutes les copies de ses fermes.
	 */
	public static function getBoundToRequired(string $right, array $audiences, \Closure $granted): array {

		$required = self::getRequired($right);

		if($required === NULL) {
			return $audiences;
		}

		return array_values(array_intersect($audiences, $granted($required)));

	}

	/**
	 * Les publics de ce droit : ceux qu'il déclare, les deux sinon, aucun s'il n'ouvre aucune lecture.
	 */
	public static function getAudiences(string $right): array {

		if(array_key_exists($right, self::RIGHTS) === FALSE) {
			return [];
		}

		return (self::RIGHTS[$right]['nature'] === self::AUDIENCE) ? (self::RIGHTS[$right]['audiences'] ?? self::AUDIENCES) : [];

	}

	/**
	 * Le public auquel un rôle appartient dans un groupe.
	 *
	 * ⚠️ Deux publics et pas trois : tout rôle est de l'un ou de l'autre, et la partition est celle
	 * de `Role::isStaff()`, tranchée le 09/09/2026. Un rôle neuf tombe donc du côté des
	 * producteurs, ce qui est le sens le plus restrictif — un droit accordé à l'équipe ne s'étend
	 * pas à un rôle dont personne n'a encore dit s'il l'anime.
	 */
	public static function getAudience(Role $eRole): int {

		return $eRole->isStaff() ? Community::STAFF : Community::MEMBER;

	}

	/**
	 * Ce que porte une colonne de droit, quel que soit le régime du type.
	 *
	 * ⚠️ **`IMPOSED` ne lit pas la colonne.** Un groupe de formation partage la production parce
	 * qu'il est un groupe de formation, pas parce qu'une case est cochée : la colonne y vaut ce
	 * qu'elle veut, souvent zéro, et la lire ferait dire non à ce que le type impose. C'est
	 * exactement le défaut que `hasCommunityShare()` rattrapait par un `if` sur le type.
	 */
	public static function getGranted(Structure $eStructureCommunity, string $right, \Set $granted): array {

		return match(self::getRegime($eStructureCommunity, $right)) {

			self::ABSENT => [],
			self::IMPOSED => self::getImposedAudiences($eStructureCommunity, $right),
			self::OPTIONAL => array_values(array_filter(
				self::getAudiences($right),
				fn(int $audience) => $granted->value($audience)
			))

		};

	}

	/**
	 * Les publics pour lesquels ce groupe **demande** ce droit.
	 *
	 * ⚠️ La colonne ne se lit que sous `OPTIONAL`, et c'est pour cela que cette méthode existe à
	 * côté de `getGranted()` : sous `IMPOSED` il n'y a rien à lire, et sous `ABSENT` l'élément
	 * n'est peut-être même pas un groupe. Lire avant de savoir sous quel régime on est ferait
	 * chercher une colonne sur une ferme.
	 */
	public static function getAsked(Structure $eStructureCommunity, string $right): array {

		// ⚠️ Vide pour un droit sans public, et ce n'est pas « non demandé » : la question « à qui »
		// n'a pas de réponse pour lui. C'est `isAsked()` qui dit s'il est demandé, et les deux
		// méthodes existent parce que confondre les deux fait passer un booléen là où le typage
		// attend un `Set` — vu à l'écran, pas aux tests.
		if(self::getAudiences($right) === []) {
			return [];
		}

		return match(self::getRegime($eStructureCommunity, $right)) {

			self::ABSENT => [],
			self::IMPOSED => self::getImposedAudiences($eStructureCommunity, $right),
			self::OPTIONAL => self::getGranted($eStructureCommunity, $right, $eStructureCommunity->community()[$right])

		};

	}

	/**
	 * Les publics qui reçoivent ce droit là où le type l'impose.
	 */
	private static function getImposedAudiences(Structure $eStructureCommunity, string $right): array {

		return self::RIGHTS[$right]['imposedTo'][$eStructureCommunity['communityType']] ?? self::getAudiences($right);

	}

	/**
	 * Ce groupe demande-t-il ce droit, quelle que soit sa nature ?
	 */
	public static function isAsked(Structure $eStructureCommunity, string $right): bool {

		return match(self::getRegime($eStructureCommunity, $right)) {

			self::ABSENT => FALSE,
			self::IMPOSED => TRUE,
			self::OPTIONAL => (self::getAudiences($right) === [])
				? (bool)$eStructureCommunity->community()[$right]
				: $eStructureCommunity->community()[$right]->get() > 0

		};

	}

	/**
	 * Ce droit est-il ouvert à ce public sur ce groupe ?
	 *
	 * `$audiencesBringing` porte les publics de ceux qui ont apporté la ferme au groupe, union
	 * sur ses rattachements : la ferme accorde, mais c'est la portée de l'apporteur qui dit
	 * jusqu'où l'accord va. Le paramètre est obligatoire pour qu'aucun appelant n'ouvre une
	 * lecture sans s'être demandé qui a apporté la ferme.
	 */
	public static function grants(Structure $eStructureCommunity, string $right, \Set $granted, int $audience, array $audiencesBringing): bool {

		return (
			in_array($audience, self::getGranted($eStructureCommunity, $right, $granted), TRUE) and
			self::reaches($eStructureCommunity, $right, $audience, $audiencesBringing)
		);

	}

	/**
	 * Une ferme apportée par ces publics atteint-elle ce public, indépendamment de ce qu'elle accorde ?
	 */
	public static function reaches(Structure $eStructureCommunity, string $right, int $audience, array $audiencesBringing): bool {

		$reach = self::RIGHTS[$right]['reach'][$eStructureCommunity['communityType']] ?? [];

		foreach($audiencesBringing as $audienceBringing) {

			if(in_array($audience, $reach[$audienceBringing] ?? self::AUDIENCES, TRUE)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * La portée de ce droit dépend-elle, sur ce groupe, de qui a apporté la ferme ?
	 */
	public static function restrictsReach(Structure $eStructureCommunity, string $right): bool {

		return (self::RIGHTS[$right]['reach'][$eStructureCommunity['communityType']] ?? []) !== [];

	}

	/**
	 * Les droits à public que ce groupe porte, dans l'ordre de la déclaration.
	 *
	 * ⚠️ **Un droit sans public ne se règle pas sur un écran de droits.** Il n'a personne à qui
	 * s'ouvrir : la question « à qui » n'a pas de réponse, et une ligne qui la pose quand même
	 * demande de cocher dans le vide. Il se règle là où il produit son effet — la contribution aux
	 * relevés se décide sur les relevés. La condition se dérive de la nature déclarée, sans
	 * attribut de plus, et un quatrième droit sans public suivra la même règle sans qu'on y pense.
	 */
	public static function getAudienceRights(Structure $eStructureCommunity): array {

		return array_values(array_filter(
			self::getRights($eStructureCommunity),
			fn(string $right) => self::getAudiences($right) !== []
		));

	}

	/**
	 * Les droits que ce groupe porte, dans l'ordre de la déclaration.
	 *
	 * L'ordre est celui des écrans : il vaut mieux qu'il vienne d'un seul endroit, sinon deux
	 * tableaux qui montrent les mêmes droits les rangent différemment.
	 */
	public static function getRights(Structure $eStructureCommunity): array {

		return array_values(array_filter(
			array_keys(self::RIGHTS),
			fn(string $right) => self::accepts($eStructureCommunity, $right)
		));

	}

}
?>
