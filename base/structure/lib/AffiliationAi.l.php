<?php
namespace structure;

/**
 * Le rattachement d'une ferme à un groupe, au nom d'un utilisateur.
 *
 * Une ligne par (utilisateur, ferme, groupe) et non par (ferme, groupe) : on
 * rejoint un groupe **au nom** d'une ferme, et deux associés peuvent y avoir
 * rattaché la même. La ferme alimente donc le groupe tant qu'une affiliation
 * active subsiste — chacun retire son propre rattachement, jamais celui du
 * voisin.
 */
class AffiliationAiLib extends AffiliationCrud {

	/** Le périmètre gelé au rattachement, dans l'ordre où le produit le nomme. */
	const SHARES = ['shareProduction', 'shareProductionExport', 'shareProductionDuplicate', 'shareSellingDuplicate'];

	/**
	 * Ce qu'un rattachement ouvre, périmètre et prix ensemble.
	 *
	 * ⚠️ `contributesQuotation` n'est pas un partage de production et ne rejoint donc pas
	 * `SHARES`, qui décide de ce que le rattachement gèle : c'est une bascule à part, réglable
	 * après coup depuis les relevés. Les trois se lisent pourtant côte à côte dans la colonne du
	 * lecteur, d'où cette seconde liste.
	 */
	const FLAGS = [...self::SHARES, 'contributesQuotation'];

	private static array $cCommunityByStructure = [];

	/**
	 * Rattache une ferme à un groupe au nom d'un utilisateur.
	 *
	 * La garde répète ce que la lecture vérifie déjà par jointure, et ce n'est pas une
	 * duplication : la jointure répond « ce rattachement vaut-il encore », celle-ci
	 * répond « cette personne a-t-elle qualité pour engager cette ferme ». Un filtre
	 * d'affichage n'est pas un contrôle d'accès, et le POST est contournable.
	 *
	 * Le périmètre est posé ici, et pas chez l'appelant : la charte que la ferme vient d'accepter
	 * énonce ce que le groupe lira, et un point d'entrée qui l'oublierait créerait un rattachement
	 * muet — la ferme apparaîtrait dans le groupe sans rien lui donner.
	 */
	public static function create(Affiliation $e, ?array $rights = NULL): void {

		$e->expects(['user', 'structureProducer', 'structureCommunity']);
		$e['structureProducer']->expects(['type', 'producerType']);
		$e['structureCommunity']->expects(['type', 'communityType']);

		if(
			$e['structureProducer']['type'] !== Structure::PRODUCER or
			in_array($e['structureProducer']['producerType'], StructureSetting::PRODUCER_TYPES_COLLECTIVE)
		) {
			Affiliation::fail('structureProducer.check');
			return;
		}

		if($e['structureCommunity']->acceptGroupStructure() === FALSE) {
			Affiliation::fail('structureCommunity.check');
			return;
		}

		if(MemberLib::isMember($e['user'], $e['structureCommunity']) === FALSE) {
			Affiliation::fail('structureCommunity.notMember');
			return;
		}

		if(MemberLib::isMember($e['user'], $e['structureProducer']) === FALSE) {
			Affiliation::fail('structureProducer.notMember');
			return;
		}

		if(MemberLib::isAdmin($e['user'], $e['structureProducer']) === FALSE) {
			Affiliation::fail('structureProducer.notAdmin');
			return;
		}

		// Lu avant l'écriture : un second associé qui rattache la même ferme ne l'annonce pas une
		// seconde fois au groupe, la ferme y étant déjà.
		$isNewCouple = (self::isAffiliated($e['structureProducer'], $e['structureCommunity']) === FALSE);

		foreach(self::getSharesForCouple($e['structureProducer'], $e['structureCommunity'], $rights) as $property => $share) {
			$e[$property] = $share;
		}

		try {
			parent::create($e);
		}
		catch(\DuplicateException) {
			Affiliation::fail('structureProducer.duplicate');
			return;
		}

		if($isNewCouple) {
			NotificationLib::producerJoined($e['structureProducer'], $e['structureCommunity']);
		}

	}

	public static function isAffiliated(Structure $eStructureProducer, Structure $eStructureCommunity): bool {

		return Affiliation::model()
			->whereStructureProducer($eStructureProducer)
			->whereStructureCommunity($eStructureCommunity)
			->exists();

	}

	/**
	 * Le périmètre que ce couple a déjà gelé, ou celui que le groupe propose s'il est neuf.
	 *
	 * Le périmètre appartient au couple (ferme, groupe) et non au triplet : un second associé qui
	 * rattache la même ferme reprend ce qu'elle a accepté, il ne le rouvre pas. Sans cette reprise
	 * il défairait, de bonne foi, la fermeture du premier — qui continuerait de lire sur son
	 * propre écran un périmètre que le groupe a dépassé.
	 *
	 * L'écran ne mène plus ici : getShareableByUser() écarte les fermes déjà rattachées. La
	 * reprise vaut donc contre un POST forgé, et contre le prochain appelant de create().
	 */
	private static function getSharesForCouple(Structure $eStructureProducer, Structure $eStructureCommunity, ?array $chosen = NULL): array {

		$cAffiliation = Affiliation::model()
			->select(self::SHARES)
			->whereStructureProducer($eStructureProducer)
			->whereStructureCommunity($eStructureCommunity)
			->getCollection();

		if($cAffiliation->empty()) {

			// ⚠️ **Ce que la ferme a coché, borné à ce que le groupe demande.** L'écran d'entrée
			// présente le tableau droit × public précoché sur la demande du groupe, et la ferme
			// décoche ce qu'elle ne donne pas. Un public hors de la demande tombe ici et pas
			// seulement à l'écran, le POST étant contournable.
			//
			// ⚠️ Sans choix — le semis de fixtures, une reprise — la ferme accorde ce que le groupe
			// demande : c'est le comportement d'avant le 10/09/2026, gardé pour les appelants qui
			// n'ont pas d'écran où choisir.
			$audiences = [];

			foreach(self::SHARES as $right) {

				$asked = $eStructureCommunity->askedCommunityAudiences($right);

				$audiences[$right] = ($chosen === NULL)
					? $asked
					: array_values(array_intersect($asked, array_map('intval', (array)($chosen[$right] ?? []))));

			}

			// ⚠️ Les cases de l'écran d'entrée sont indépendantes : une copie cochée pour un public
			// dont la lecture est décochée tombe ici, et non seulement un POST forgé.
			$granted = fn(string $property) => $eStructureCommunity->getGrantedCommunityAudiences($property, \Set::fromArray($audiences[$property]));

			$shares = [];

			foreach(self::SHARES as $right) {
				$shares[$right] = \Set::fromArray(CommunityRightAiLib::getBoundToRequired($right, $audiences[$right], $granted));
			}

			return $shares;

		}

		$shares = [];

		foreach(self::SHARES as $right) {
			$shares[$right] = new \Set();
		}

		// Accumulé et non recopié, comme partout ailleurs sur cette table : deux associés peuvent
		// avoir rattaché la même ferme, et ce que le groupe en lit est l'union de ce qu'ils ont
		// ouvert.
		foreach($cAffiliation as $eAffiliation) {

			foreach(self::SHARES as $right) {

				foreach(CommunityRightAiLib::getAudiences($right) as $audience) {

					if($eAffiliation[$right]->value($audience)) {
						$shares[$right]->value($audience, TRUE);
					}

				}

			}

		}

		return $shares;

	}

	/**
	 * La ferme se retire du groupe : le groupe le sait, par la cloche et par l'e-mail à son adresse
	 * légale.
	 *
	 * ⚠️ Rien n'est annoncé quand rien n'a été retiré : un double envoi du formulaire trouve le
	 * couple déjà défait, et annoncer deux départs pour un seul serait faux.
	 */
	public static function removeStructure(Structure $eStructureProducer, Structure $eStructureCommunity): void {

		if(self::deleteByStructures($eStructureProducer, $eStructureCommunity) === 0) {
			return;
		}

		NotificationLib::producerLeft($eStructureProducer, $eStructureCommunity);

		$content = StructureCommunityUi::getRemovedByStructureMail($eStructureCommunity, $eStructureProducer);

		\main\MailLib::getInstance()
			->setTo($eStructureCommunity['legalEmail'])
			->setContent(...$content)
			->send();

	}

	/**
	 * Le groupe retire une ferme : la ferme le sait, par la cloche et par l'e-mail à son adresse
	 * légale. Même garde que `removeStructure()`.
	 */
	public static function excludeStructure(Structure $eStructureProducer, Structure $eStructureCommunity): void {

		if(self::deleteByStructures($eStructureProducer, $eStructureCommunity) === 0) {
			return;
		}

		NotificationLib::producerExcluded($eStructureProducer, $eStructureCommunity);

		$content = StructureCommunityUi::getRemovedByCommunityMail($eStructureCommunity, $eStructureProducer);

		\main\MailLib::getInstance()
			->setTo($eStructureProducer['legalEmail'])
			->setContent(...$content)
			->send();

	}

	/**
	 * Retire une ferme d'un groupe, quel que soit l'associé qui l'y a rattachée.
	 *
	 * La portée est celle du couple et non de la ligne : une affiliation par associé, et n'en
	 * retirer qu'une laisserait la ferme dans le groupe alors que l'écran vient d'annoncer le
	 * contraire. C'est déjà la portée de setContribution(), et pour la même raison — le
	 * rattachement est celui de la ferme, pas de l'associé qui l'a ouvert.
	 *
	 * Le retrait arrête la lecture et ne défait rien : un relevé de prix auquel la ferme a
	 * contribué reste ce qu'il est.
	 */
	public static function deleteByStructures(Structure $eStructureProducer, Structure $eStructureCommunity): int {

		$cAffiliation = Affiliation::model()
			->select(['id'])
			->whereStructureProducer($eStructureProducer)
			->whereStructureCommunity($eStructureCommunity)
			->getCollection();

		foreach($cAffiliation as $eAffiliation) {
			self::delete($eAffiliation);
		}

		return $cAffiliation->count();

	}

	/**
	 * Combien de fermes de ce groupe **ce lecteur** voit.
	 *
	 * ⚠️ Ce n'est pas le nombre de fermes du groupe. Un rôle borné à ses sous-groupes n'en voit
	 * que l'union — un étudiant voit sa promotion, pas l'établissement. Compter sans la portée
	 * rend un chiffre juste du point de vue du groupe et faux du point de vue de qui le lit, et
	 * c'est le piège le plus régulier de ce chantier : mesuré sur la promotion 6005, le groupe
	 * partage 9 fermes et l'étudiant en voit 4.
	 *
	 * ⚠️ La portée seulement, jamais le sous-groupe choisi à l'écran : celui-là est un filtre de
	 * confort qui ne change pas ce qu'on perd en partant.
	 */
	public static function countStructuresByReader(Structure $eStructureCommunity, \user\User $eUser): int {

		$scope = \structure\SectionLib::getScope($eStructureCommunity);

		$cAffiliation = self::getByCommunity($eStructureCommunity);
		$cStructure = self::findVisible($eStructureCommunity, self::groupByStructure($cAffiliation, $eUser));

		if($scope === NULL) {
			return $cStructure->count();
		}

		self::fillStructureMembers($cAffiliation, $cStructure);

		return \structure\SectionLib::countStructures($eStructureCommunity, $cStructure, \structure\SectionLib::getByStructure($eStructureCommunity), $scope, TRUE);

	}

	/**
	 * Combien de fermes d'un groupe ont accordé chacun de ses périmètres, sur combien.
	 *
	 * ⚠️ C'est **le** chiffre qui manque à un administrateur : il règle ce que le groupe demande
	 * et croit régler ce que le groupe lit. Le gel au rattachement fait que les deux divergent dès
	 * qu'il élargit son périmètre — les fermes déjà là gardent celui qu'elles ont accepté. Sans ce
	 * compte, rien à l'écran ne le dit, et une phrase d'explication ne se vérifie pas.
	 *
	 * ⚠️ Par **ferme** et non par rattachement : deux associés qui ont rattaché la même ferme ne
	 * la font pas compter deux fois, et il suffit que l'un ait accordé pour que le groupe lise.
	 * C'est la même union que `getSharesForCouple()`, exprimée en SQL.
	 */
	public static function countSharesByCommunity(Structure $eStructureCommunity): array {

		$rights = array_keys(CommunityRightAiLib::RIGHTS);

		$counts = ['total' => 0] + array_fill_keys($rights, 0);

		$cAffiliation = self::applyStillValid(Affiliation::model())
			->select(['structureProducer', ...$rights])
			->where('m1.structureCommunity', $eStructureCommunity)
			->getCollection();

		$granted = [];

		foreach($cAffiliation as $eAffiliation) {

			$id = $eAffiliation['structureProducer']['id'];

			$granted[$id] ??= array_fill_keys($rights, FALSE);

			// ⚠️ Accordé à au moins un public : le compteur de l'écran du groupe dit combien de
			// fermes ont donné, pas à qui. Le détail public par public vit sur la carte de chaque
			// ferme. Un droit sans public porte un booléen et se lit tel quel.
			foreach($rights as $right) {

				$value = $eAffiliation[$right];
				$given = ($value instanceof \Set) ? ($value->get() > 0) : (bool)$value;

				if($given) {
					$granted[$id][$right] = TRUE;
				}

			}

		}

		$counts['total'] = count($granted);

		foreach($granted as $shares) {
			foreach($rights as $right) {
				if($shares[$right]) {
					$counts[$right]++;
				}
			}
		}

		return $counts;

	}

	/**
	 * Les fermes d'un groupe qui n'ont pas accordé un périmètre, et à qui l'écrire.
	 *
	 * ⚠️ Regroupées par **destinataire** et non par ferme : quelqu'un qui a rattaché quatre fermes
	 * au même groupe recevait quatre courriels dans la même seconde, ce qui se lit comme une
	 * insistance et non comme une demande. Un seul message nomme ses fermes.
	 *
	 * ⚠️ Une ferme n'est comptée qu'une fois même si deux associés l'ont rattachée, et c'est
	 * l'auteur du rattachement qu'on écrit — celui qui a accepté la charte, donc celui qui peut la
	 * compléter.
	 *
	 * ⚠️ Seules celles qui n'ont pas tout accordé : le message demande, il n'informe pas. Une
	 * ferme qui a déjà donné n'a rien à faire et ne doit rien recevoir.
	 */
	public static function getStructuresToAsk(Structure $eStructureCommunity, array $shares): array {

		$cAffiliation = self::applyStillValid(Affiliation::model())
			->select([
				'structureProducer' => ['id', 'name'],
				'user' => ['email', 'firstName', 'lastName'],
				...self::SHARES
			])
			->where('m1.structureCommunity', $eStructureCommunity)
			->getCollection();

		$seen = [];
		$recipients = [];

		foreach($cAffiliation as $eAffiliation) {

			$eStructureProducer = $eAffiliation['structureProducer'];
			$id = $eStructureProducer['id'];

			if(array_key_exists($id, $seen)) {
				continue;
			}

			$granted = TRUE;

			foreach($shares as $share) {
				if($eAffiliation[$share] === FALSE) {
					$granted = FALSE;
				}
			}

			if($granted or $eAffiliation['user']['email'] === NULL) {
				continue;
			}

			$seen[$id] = TRUE;

			$email = $eAffiliation['user']['email'];

			$recipients[$email] ??= [];
			$recipients[$email][] = $eStructureProducer['name'];

		}

		return $recipients;

	}

	/**
	 * Ce qu'une ferme accorde à un groupe, droit par droit et public par public.
	 *
	 * ⚠️ **Toutes les lignes du couple et non celle de l'appelant.** Le périmètre appartient au
	 * couple (ferme, groupe) et se lit en union entre associés (`getSharesForCouple()`) : n'écrire
	 * que sa propre ligne rendrait le même écran vrai pour l'un et faux pour l'autre, alors que le
	 * groupe, lui, lirait la ferme dans les deux cas. Corollaire assumé : deux associés partagent
	 * ce réglage, et l'un peut reprendre ce que l'autre a accordé — c'est déjà la portée de
	 * `deleteByStructures()`, et l'écran ne doit pas laisser croire qu'on règle « son »
	 * rattachement.
	 *
	 * ⚠️ **Sans effet rétroactif.** Retirer une autorisation arrête la lecture, elle ne défait pas
	 * ce qui a été lu : les arrêtés de prix déjà publiés ne se réécrivent pas, et ce que le groupe
	 * a déjà agrégé lui reste.
	 *
	 * ⚠️ **Borné à ce que le groupe demande.** Un public que le groupe ne demande pas ne s'accorde
	 * pas : la ferme ne peut donner que ce qui lui est demandé, sans quoi un POST forgé écrirait un
	 * droit que l'écran du groupe ne montre pas et que personne ne saurait retirer.
	 *
	 * L'appelant a vérifié qu'il administre la ferme : c'est elle qui accorde, jamais le groupe,
	 * et c'est tout ce que le gel protège.
	 */
	public static function setRights(Structure $eStructureProducer, Structure $eStructureCommunity, array $rights): int {

		// ⚠️ **Un droit absent du tableau ne se touche pas**, et c'est ce qui sépare « ne pas
		// accorder » de « ne pas en parler » : l'écran des relevés de prix ne règle que le sien, et
		// une liste vide y effacerait les deux autres. Décocher un public se dit donc par une liste
		// vide, jamais par une clé manquante.
		$properties = array_values(array_filter(
			array_keys(CommunityRightAiLib::RIGHTS),
			fn(string $right) => array_key_exists($right, $rights)
		));

		if($properties === []) {
			return 0;
		}

		// Les copies de ce qui s'écrit, et les copies qui s'écrivent elles-mêmes.
		$propertiesBound = [];

		foreach($properties as $property) {

			if(CommunityRightAiLib::getRequired($property) !== NULL) {
				$propertiesBound[] = $property;
			}

			array_push($propertiesBound, ...CommunityRightAiLib::getRequiring($property));

		}

		$propertiesBound = array_values(array_unique($propertiesBound));

		$propertiesRequired = array_map(fn(string $property) => CommunityRightAiLib::getRequired($property), $propertiesBound);

		$cAffiliation = Affiliation::model()
			->select(['id', ...array_unique([...$properties, ...$propertiesBound, ...$propertiesRequired])])
			->whereStructureProducer($eStructureProducer)
			->whereStructureCommunity($eStructureCommunity)
			->getCollection();

		$updated = 0;

		foreach($cAffiliation as $eAffiliation) {

			$propertiesChanged = [];

			foreach($properties as $right) {

				// ⚠️ Un droit que le groupe ne demande pas ne se **touche** pas, il ne se met pas à
				// zéro : l'écran ne le montre pas, donc personne n'a rien décidé à son sujet, et
				// l'effacer reviendrait à retirer en silence un droit que le lecteur n'a pas vu.
				// La copie qui perd sa lecture fait exception, plus bas.
				if(CommunityRightAiLib::isAsked($eStructureCommunity, $right) === FALSE) {
					continue;
				}

				$asked = $eStructureCommunity->askedCommunityAudiences($right);

				$before = self::readRight($eAffiliation, $right);

				// ⚠️ **L'écriture passe par `build()` et non par une affectation.** Les deux bornes
				// sont indépendantes et aucune ne remplace l'autre : l'intersection dit ce que ce
				// groupe-ci demande, que le schéma ne peut pas connaître ; `build()` dit ce que la
				// colonne accepte, et c'est la seule porte qui lise le `.yml` — celle que l'API
				// emprunte aussi. Le jour où la déclaration des publics et le `set` du `.yml`
				// divergeraient, l'intersection laisserait passer une valeur que la colonne ne
				// sait pas porter.
				$posted = (CommunityRightAiLib::getAudiences($right) === [])
					? (bool)$rights[$right]
					: array_values(array_intersect($asked, array_map('intval', (array)$rights[$right])));

				$eAffiliation->build([$right], [$right => $posted]);

				if(self::readRight($eAffiliation, $right) === $before) {
					continue;
				}

				$propertiesChanged[] = $right;

			}

			// ⚠️ **Retirer la lecture retire ses copies, que le groupe les demande encore ou non.**
			// Gardée parce qu'elle n'était plus demandée au moment du retrait, une copie redevient
			// accordée sans la lecture le jour où le groupe la redemande, sans geste de la ferme.
			// Réduire n'ouvre rien, ce n'est pas le cas que la borne ci-dessus protège.
			$granted = fn(string $property) => $eStructureCommunity->getGrantedCommunityAudiences($property, $eAffiliation[$property]);

			foreach($propertiesBound as $right) {

				$before = self::readRight($eAffiliation, $right);

				$audiences = array_values(array_filter(
					CommunityRightAiLib::getAudiences($right),
					fn(int $audience) => $eAffiliation[$right]->value($audience)
				));

				$eAffiliation->build([$right], [$right => CommunityRightAiLib::getBoundToRequired($right, $audiences, $granted)]);

				if(
					self::readRight($eAffiliation, $right) !== $before and
					in_array($right, $propertiesChanged, TRUE) === FALSE
				) {
					$propertiesChanged[] = $right;
				}

			}

			if($propertiesChanged === []) {
				continue;
			}

			self::update($eAffiliation, $propertiesChanged);

			$updated++;

		}

		return $updated;

	}

	/**
	 * La valeur d'un droit sous une forme comparable, que le droit porte des publics ou non.
	 *
	 * Elle existe parce que la comparaison se fait deux fois autour de `build()`, et que les deux
	 * natures ne se comparent pas de la même façon : un `Set` par son masque, un booléen par
	 * lui-même. Écrite deux fois, elle aurait divergé.
	 */
	protected static function readRight(Affiliation $eAffiliation, string $right): int {

		$value = $eAffiliation[$right];

		return ($value instanceof \Set) ? $value->get() : (int)(bool)$value;

	}

	/**
	 * Les deux jointures qui décident si un rattachement vaut encore.
	 *
	 * Elles ne sont pas des optimisations : elles décident qui voit les données d'une ferme.
	 * Un rattachement ne vaut que si son auteur est encore membre **des deux** côtés — du
	 * groupe à qui il ouvre la ferme, et de la ferme qu'il ouvre. Les deux jambes se perdent
	 * séparément : `deleteByMember()` n'est appelé que sur une suppression de ligne, alors
	 * que `member.p.php:108` sait exclure quelqu'un en passant son adhésion à `OUT`, ce qui
	 * laisserait un rattachement sans auteur légitime.
	 *
	 * L'invariant vit ici seul, et **toute lecture qui décide d'un accès l'emprunte** : une
	 * divergence entre deux de ces lectures ouvrirait un écran que l'autre a fermé, sans
	 * qu'aucune des deux ne paraisse fautive.
	 */
	private static function applyStillValid(AffiliationModel $mAffiliation): AffiliationModel {

		return $mAffiliation
			->join(Member::model(), 'm1.user = m2.user AND m2.structure = m1.structureCommunity')
			->join(Member::model(), 'm1.user = m3.user AND m3.structure = m1.structureProducer')
			->where('m2.status', Member::IN)
			->where('m3.status', Member::IN);

	}

	/**
	 * Le public de celui qui a apporté la ferme, d'après son rôle **actuel** dans le groupe.
	 *
	 * Lu sur `fqnBringing`, que chaque lecture sélectionne sur l'adhésion au groupe déjà jointe
	 * par `applyStillValid()`.
	 */
	private static function getAudienceBringing(Affiliation $eAffiliation): int {

		return CommunityRightAiLib::getAudience(new Role(['fqn' => $eAffiliation['fqnBringing']]));

	}

	/**
	 * Les rattachements qui valent encore sur un groupe, l'auteur et la ferme chargés.
	 *
	 * Une ferme fermée ne reparaît pas dans un groupe. Ce dernier filtre se pose en PHP et
	 * non en SQL : la condition porte sur la table déléguée, et la lecture est bornée aux
	 * fermes d'un seul groupe.
	 *
	 * Le gabarit d'un groupe et la route qu'il habille regroupent les mêmes rattachements de
	 * trois façons (`groupByStructure()`, `groupStructuresByUser()`, `groupUsersByStructure()`) :
	 * une réponse les lit ici une fois et passe la collection, que la route pose sur
	 * `$data->cAffiliation`. La ferme porte donc toujours la sélection complète, celle du
	 * regroupement qui en demande le plus.
	 */
	public static function getByCommunity(Structure $eStructureCommunity): \Collection {

		return self::applyStillValid(Affiliation::model())
			->join(Role::model(), 'm2.role = m4.id')
			->select([
				// Le nom de l'auteur, et pas seulement son identifiant : c'est lui qui répond de la
				// ferme dans ce groupe, et la carte le nomme.
				'user' => ['firstName', 'lastName'],
				'structureProducer' => Structure::getSelection(),
				'fqnBringing' => new \Sql('m4.fqn'),
				...self::FLAGS
			])
			->where('m1.structureCommunity', $eStructureCommunity)
			->getCollection()
			->find(fn($eAffiliation) => $eAffiliation['structureProducer']['status'] === Structure::ACTIVE);

	}

	/**
	 * Les fermes rattachées à un groupe, avec `isMine` pour celles de l'utilisateur.
	 *
	 * `isMine` et le périmètre s'accumulent et ne se recopient pas : deux associés peuvent avoir
	 * rattaché la même ferme, et la collection n'en garde qu'une entrée. Poser la valeur de la
	 * ligne courante rendrait un résultat dépendant de l'ordre du parcours — la ferme de
	 * l'utilisateur passerait pour celle d'un autre dès qu'un associé l'a rattachée après lui.
	 *
	 * Le périmètre garde le nom de la colonne dont il vient : sur une ferme, `shareProduction`
	 * ne peut désigner que ce qu'un groupe en lit, elle n'a pas de bascule à elle.
	 */
	public static function getStructuresByCommunity(Structure $eStructureCommunity, \user\User $eUser): \Collection {

		return self::groupByStructure(self::getByCommunity($eStructureCommunity), $eUser);

	}

	/**
	 * Le regroupement de getStructuresByCommunity(), sur des rattachements déjà lus.
	 *
	 * ⚠️ Chaque ferme est une copie : la même collection sert aussi `groupStructuresByUser()`,
	 * qui pose sur la ferme un `audiencesBringing` d'une autre nature (le public d'un seul
	 * rattachement, et non l'union), et les écrans complètent ensuite chaque ferme pour eux seuls.
	 */
	public static function groupByStructure(\Collection $cAffiliation, \user\User $eUser): \Collection {

		$cStructure = new \Collection();

		foreach($cAffiliation as $eAffiliation) {

			$id = $eAffiliation['structureProducer']['id'];

			if($cStructure->offsetExists($id) === FALSE) {

				$eStructure = clone $eAffiliation['structureProducer'];

				$eStructure['isMine'] = FALSE;
				$eStructure['contributesQuotation'] = FALSE;
				$eStructure['audiencesBringing'] = [];

				// Une instance par droit : `array_fill_keys()` n'évaluerait `new Set()` qu'une fois
				// et les deux clés porteraient le même masque.
				foreach(self::SHARES as $right) {
					$eStructure[$right] = new \Set();
				}

				$cStructure[$id] = $eStructure;

			}

			if($eAffiliation['user']['id'] === $eUser['id']) {
				$cStructure[$id]['isMine'] = TRUE;
			}

			// Accumulé sur les trois : deux associés peuvent avoir rattaché la même ferme, et ce
			// que le groupe en lit est l'union de ce qu'ils ont ouvert — public par public sur les
			// deux droits qui en ont.
			if($eAffiliation['contributesQuotation']) {
				$cStructure[$id]['contributesQuotation'] = TRUE;
			}

			$audienceBringing = self::getAudienceBringing($eAffiliation);

			if(in_array($audienceBringing, $cStructure[$id]['audiencesBringing'], TRUE) === FALSE) {
				$cStructure[$id]['audiencesBringing'] = [...$cStructure[$id]['audiencesBringing'], $audienceBringing];
			}

			foreach(self::SHARES as $right) {

				foreach(CommunityRightAiLib::getAudiences($right) as $audience) {

					if($eAffiliation[$right]->value($audience)) {
						$cStructure[$id][$right]->value($audience, TRUE);
					}

				}

			}

		}

		return $cStructure;

	}

	/**
	 * Une ferme du groupe dans le périmètre du lecteur, ou un élément vide.
	 *
	 * ⚠️ La ferme se lit parmi les rattachements et jamais par son seul id, qui désigne n'importe
	 * quelle ferme du site. Et `getStructuresByCommunity()` ignore les sous-groupes : un conseiller
	 * borné aux siens atteindrait par l'adresse une ferme que son écran ne lui montre pas. La ferme
	 * est dans le périmètre quand l'un de ceux qui l'ont rattachée est rangé dans l'un des
	 * sous-groupes du lecteur, la règle de `SectionLib::keepsStructure()`. Une ferme hors de
	 * portée (`findVisible()`) n'y est jamais : l'adresse ne rend pas ce que l'écran tait.
	 *
	 * `$scope` est celui de `SectionLib::getScope()`, que la page lit : NULL ne borne rien.
	 */
	public static function getStructureInScope(Structure $eStructureCommunity, \user\User $eUser, ?array $scope, int $structure): Structure {

		$cAffiliation = self::getByCommunity($eStructureCommunity);
		$cStructure = self::findVisible($eStructureCommunity, self::groupByStructure($cAffiliation, $eUser));

		if($cStructure->offsetExists($structure) === FALSE) {
			return new Structure();
		}

		if($scope !== NULL) {

			self::fillStructureMembers($cAffiliation, $cStructure);

			$users = SectionLib::getUsers(SectionLib::getByStructure($eStructureCommunity), $scope);

			if(SectionLib::keepsStructure($eStructureCommunity, $cStructure[$structure], $users, TRUE) === FALSE) {
				return new Structure();
			}

		}

		return $cStructure[$structure];

	}

	/**
	 * Celles de ces fermes qui accordent `$right` au lecteur, sous le régime du groupe.
	 *
	 * ⚠️ **Rattachement et partage ne sont pas la même chose.** Rejoindre un groupe ne lui donne
	 * pas ses cultures : `shareProduction` est gelé au rattachement, ferme par ferme, et une ferme
	 * qui ne l'a pas accordé alimente le groupe pour les prix sans rien lui montrer de son plan.
	 * `getStructuresByCommunity()` rend donc **tout** le rattachement, ce dont l'écran des fermes a
	 * besoin, et tout écran qui montre de la production passe par ici.
	 *
	 * ⚠️ Un filtre sur une collection déjà chargée et non une seconde lecture : la comparaison
	 * borne d'abord ses fermes au sous-groupe regardé, et refaire la requête ici perdrait ce
	 * filtre-là.
	 *
	 * ⚠️ **Le public du lecteur décide, et l'écran n'est donc plus le même pour tout le monde.**
	 * Une ferme qui n'ouvre sa production qu'à l'équipe du groupe ne paraît pas à un producteur, et
	 * paraît à un enseignant. C'est le prix des publics : tant qu'un droit était un booléen, « le
	 * même tableau pour tous » était tenable ; avec deux publics, tenir cette promesse demanderait
	 * soit de montrer au producteur ce que la ferme lui a refusé, soit de cacher à l'équipe ce
	 * qu'elle a reçu. Aucune des deux n'est acceptable, donc la promesse tombe.
	 */
	public static function findSharing(Structure $eStructureCommunity, \Collection $cStructureProducer, string $right): \Collection {

		$audience = CommunityRightAiLib::getAudience($eStructureCommunity->getMember()['role']);

		// ⚠️ La sienne se juge sur ce qu'elle partage au groupe, et non sur ce qu'elle partage au
		// lecteur : dans un groupe de formation, la ferme d'un étudiant n'atteint que l'équipe, et
		// son étudiant la lit par son rôle sur la ferme. La juger sur son public la ferait passer
		// pour muette sur sa propre carte, et la sortirait de la comparaison.
		return $cStructureProducer
			->find(fn(Structure $eStructureProducer) => (
				$eStructureProducer['isMine'] and
				CommunityRightAiLib::getGranted($eStructureCommunity, $right, $eStructureProducer[$right]) !== []
			) or CommunityRightAiLib::grants(
				$eStructureCommunity,
				$right,
				$eStructureProducer[$right],
				$audience,
				$eStructureProducer['audiencesBringing']
			));

	}

	/**
	 * Pose sur chaque ferme ce qu'elle accorde au lecteur, droit par droit, sous `sharing`.
	 *
	 * ⚠️ Un droit imposé ne se lit pas sur la colonne, qui vaut souvent 0 : l'écran qui la lirait
	 * annoncerait comme refusé ce que le groupe reçoit d'office.
	 *
	 * ⚠️ Tous les partages, et non ceux que le type du groupe porte : un droit absent vaut FALSE
	 * (`getGranted()` rend `[]`), et les écrans lisent `sharing['shareProduction']` sur tout
	 * groupe, comptable compris, où la clé manquerait sinon.
	 */
	public static function fillSharing(Structure $eStructureCommunity, \Collection $cStructureProducer): void {

		foreach($cStructureProducer as $eStructureProducer) {
			$eStructureProducer['sharing'] = [];
		}

		foreach(self::SHARES as $right) {

			$cStructureSharing = self::findSharing($eStructureCommunity, $cStructureProducer, $right);

			foreach($cStructureProducer as $eStructureProducer) {
				$eStructureProducer['sharing'][$right] = $cStructureSharing->offsetExists($eStructureProducer['id']);
			}

		}

	}

	/**
	 * Les fermes que le lecteur voit dans ce groupe : les siennes, et celles qu'il peut atteindre.
	 *
	 * ⚠️ Les siennes d'abord, parce que la portée ne les atteint pas forcément : dans un groupe de
	 * formation, la ferme qu'un étudiant apporte n'atteint que l'équipe, et c'est pourtant la
	 * sienne. Il la lit par son rôle sur la ferme, pas par le groupe.
	 */
	public static function findVisible(Structure $eStructureCommunity, \Collection $cStructureProducer): \Collection {

		$cStructureReachable = self::findReachable($eStructureCommunity, $cStructureProducer);

		return $cStructureProducer->find(fn(Structure $eStructureProducer) => (
			$eStructureProducer['isMine'] or
			$cStructureReachable->offsetExists($eStructureProducer['id'])
		));

	}

	/**
	 * Pose sur chaque ferme ceux qui l'ont apportée au groupe, ce que le filtre des sous-groupes lit.
	 *
	 * `$cAffiliation` vient de getByCommunity(), et c'est en général la lecture dont `$cStructure`
	 * est déjà tirée.
	 */
	public static function fillStructureMembers(\Collection $cAffiliation, \Collection $cStructure): void {

		$ccUser = self::groupUsersByStructure($cAffiliation);

		foreach($cStructure as $eStructure) {
			$eStructure['cUser'] = $ccUser[$eStructure['id']] ?? new \Collection();
		}

	}

	/**
	 * Celles de ces fermes que le lecteur peut atteindre, qu'elles partagent ou non.
	 *
	 * ⚠️ **Hors de portée n'est pas refusé.** Une ferme qui refuse le partage garde sa carte
	 * atténuée : la décision est la sienne, et le groupe a le droit de savoir qu'elle est là. Une
	 * ferme hors de portée n'est pas au lecteur de connaître : dans un groupe de formation, la
	 * ferme d'un étudiant n'existe pas pour un autre étudiant.
	 */
	public static function findReachable(Structure $eStructureCommunity, \Collection $cStructureProducer): \Collection {

		$audience = CommunityRightAiLib::getAudience($eStructureCommunity->getMember()['role']);

		return $cStructureProducer
			->find(fn(Structure $eStructureProducer) => CommunityRightAiLib::reaches(
				$eStructureCommunity,
				'shareProduction',
				$audience,
				$eStructureProducer['audiencesBringing']
			));

	}

	/**
	 * Qui, dans ce groupe, répond de chaque ferme — indexé par ferme.
	 *
	 * C'est le regroupement miroir de groupStructuresByUser(), et la réponse est dans la table du
	 * lien et non dans une intersection : une affiliation est un triplet (groupe, utilisateur, ferme),
	 * donc l'auteur du rattachement est par construction membre du groupe. Chercher les
	 * administrateurs de la ferme qui sont aussi membres du groupe répond à une autre question et
	 * peut ne rien rendre — rattacher une ferme n'a jamais demandé d'en être administrateur.
	 *
	 * `$cAffiliation` vient de getByCommunity().
	 */
	public static function groupUsersByStructure(\Collection $cAffiliation): \Collection {

		$ccUser = new \Collection();

		foreach($cAffiliation as $eAffiliation) {

			$structure = $eAffiliation['structureProducer']['id'];

			if($ccUser->offsetExists($structure) === FALSE) {
				$ccUser[$structure] = new \Collection();
			}

			$ccUser[$structure][$eAffiliation['user']['id']] = $eAffiliation['user'];

		}

		return $ccUser;

	}

	/**
	 * Les comptes des fermes rattachées à un groupe, indexés par ferme puis par compte, chaque
	 * adhésion portant le nom de sa ferme. C'est ce qu'un réseau ouvre quand il partage une formation
	 * à ses fermes : toute adhésion en cours, et pas seulement l'auteur du rattachement.
	 *
	 * Deux requêtes et non une jointure sur les trois tables : les rattachements valides d'abord,
	 * que `applyStillValid()` juge, puis une seule lecture des adhésions bornée aux fermes trouvées.
	 * `$cStructureProducer` restreint aux fermes données, pour un lecteur qui ne s'intéresse qu'aux
	 * siennes.
	 */
	public static function getMembersByAffiliate(Structure $eStructureCommunity, \Collection $cStructureProducer = new \Collection()): \Collection {

		$mAffiliation = self::applyStillValid(Affiliation::model())
			->select(['structureProducer' => ['status']])
			->where('m1.structureCommunity', $eStructureCommunity);

		if($cStructureProducer->notEmpty()) {
			$mAffiliation->where('m1.structureProducer', 'IN', $cStructureProducer);
		}

		$cStructure = $mAffiliation
			->getCollection()
			->find(fn(Affiliation $eAffiliation) => $eAffiliation['structureProducer']['status'] === Structure::ACTIVE)
			->getColumnCollection('structureProducer');

		if($cStructure->empty()) {
			return new \Collection();
		}

		return Member::model()
			->select([
				'structure' => ['name'],
				'user' => ['firstName', 'lastName', 'email']
			])
			->whereStructure('IN', $cStructure)
			->whereStatus(Member::IN)
			->getCollection(index: ['structure', 'user']);

	}

	/**
	 * Les rattachements qui valent encore pour ces fermes, quel que soit le groupe, indexés par
	 * ferme puis par groupe, le groupe portant son nom. Un compte qui lit ce que ses fermes lui
	 * ouvrent part d'ici : la ferme d'un rattachement dont l'auteur a quitté le groupe ou la ferme
	 * n'y figure plus.
	 */
	public static function getValidByStructureProducers(\Collection $cStructureProducer): \Collection {

		if($cStructureProducer->empty()) {
			return new \Collection();
		}

		return self::applyStillValid(Affiliation::model())
			->select([
				'structureProducer',
				'structureCommunity' => ['name']
			])
			->where('m1.structureProducer', 'IN', $cStructureProducer)
			->getCollection(index: ['structureProducer', 'structureCommunity']);

	}

	/**
	 * Qui répond de cette ferme, sur l'union des groupes que le lecteur partage avec elle.
	 *
	 * Même donnée que groupUsersByStructure(), autre portée : la carte d'un groupe montre les
	 * auteurs de ce groupe, la fiche montre ceux de tous les groupes par lesquels le lecteur
	 * l'atteint. Les deux propriétés de la règle tiennent : jamais vide — une ferme n'est visible
	 * que parce qu'une affiliation la rend visible — et aucun rôle exigé dans la ferme.
	 */
	public static function getUsersByStructure(Structure $eStructureProducer, \Collection $cStructureCommunity): \Collection {

		if($cStructureCommunity->empty()) {
			return new \Collection();
		}

		$cAffiliation = self::applyStillValid(Affiliation::model())
			->select(['user' => ['firstName', 'lastName']])
			->where('m1.structureProducer', $eStructureProducer)
			->where('m1.structureCommunity', 'IN', $cStructureCommunity)
			->getCollection();

		$cUser = new \Collection();

		foreach($cAffiliation as $eAffiliation) {
			$cUser[$eAffiliation['user']['id']] = $eAffiliation['user'];
		}

		return $cUser;

	}

	/**
	 * Les fermes que chaque membre apporte à un groupe, indexées par utilisateur.
	 *
	 * Le rail des membres nomme les fermes sous chaque personne : la collection est
	 * donc rendue par utilisateur et non dédoublonnée, contrairement à
	 * getStructuresByCommunity(). Deux associés qui ont rattaché la même ferme la
	 * voient nommée sous chacun d'eux, ce qui est exact — ils l'apportent tous deux.
	 *
	 * `$cAffiliation` vient de getByCommunity(), dont la sélection porte ce que l'infobulle du
	 * rail ajoute au nom : le logo, la commune, et `profileVisibility`, qui décide du lien vers
	 * la fiche dans StructureUi::urlProfile().
	 *
	 * ⚠️ Chaque ferme est une copie, pour la raison dite sur groupByStructure().
	 */
	public static function groupStructuresByUser(\Collection $cAffiliation): \Collection {

		$ccStructure = new \Collection();

		foreach($cAffiliation as $eAffiliation) {

			$eStructure = clone $eAffiliation['structureProducer'];
			$userId = $eAffiliation['user']['id'];

			if($ccStructure->offsetExists($userId) === FALSE) {
				$ccStructure[$userId] = new \Collection();
			}

			// Aucune accumulation ici, contrairement à getStructuresByCommunity() : le triplet
			// (groupe, utilisateur, ferme) est unique, donc chaque case du rail a exactement un
			// rattachement derrière elle.
			$eStructure['granted'] = [];
			$eStructure['audiencesBringing'] = [self::getAudienceBringing($eAffiliation)];

			foreach(self::SHARES as $right) {
				$eStructure['granted'][$right] = $eAffiliation[$right];
			}

			$ccStructure[$userId][$eStructure['id']] = $eStructure;

		}

		return $ccStructure->sort(['name' => SORT_ASC], depth: 2);

	}

	/**
	 * Les groupes qu'une ferme alimente, tous utilisateurs confondus.
	 */
	public static function getCommunities(Structure $eStructure): \Collection {

		$cAffiliation = Affiliation::model()
			->select(['structureCommunity' => Structure::getSelection()])
			->whereStructureProducer($eStructure)
			->whereContributesQuotation(TRUE)
			->getCollection();

		$cCommunity = new \Collection();

		foreach($cAffiliation as $eAffiliation) {
			$cCommunity[$eAffiliation['structureCommunity']['id']] = $eAffiliation['structureCommunity'];
		}

		return $cCommunity;

	}

	/**
	 * Les groupes d'une ferme, avec l'état de sa contribution.
	 *
	 * Contrairement à getCommunities(), rend **aussi** ceux qu'elle n'alimente
	 * pas : c'est ce qui permet à l'écran de saisie de proposer de les rejoindre.
	 * Chaque groupe porte `contributes`, vrai dès qu'une seule affiliation active
	 * subsiste — la ferme alimente le groupe tant qu'un de ses associés l'y a
	 * rattachée.
	 */
	public static function getCommunityStates(Structure $eStructure): \Collection {

		$cAffiliation = Affiliation::model()
			->select(['structureCommunity' => Structure::getSelection(), 'contributesQuotation'])
			->whereStructureProducer($eStructure)
			->getCollection();

		$cCommunity = new \Collection();

		foreach($cAffiliation as $eAffiliation) {

			$eStructureCommunity = $eAffiliation['structureCommunity'];
			$id = $eStructureCommunity['id'];

			if($cCommunity->offsetExists($id) === FALSE) {
				$eStructureCommunity['contributes'] = FALSE;
				$cCommunity[$id] = $eStructureCommunity;
			}

			if($eAffiliation['contributesQuotation']) {
				$cCommunity[$id]['contributes'] = TRUE;
			}

		}

		return $cCommunity;

	}

	/**
	 * Bascule la contribution d'une ferme à un groupe.
	 *
	 * Porte sur **toutes** les affiliations de la ferme à ce groupe : le réglage
	 * est celui de la ferme, pas de l'associé qui l'ouvre. Un producteur qui coupe
	 * doit couper, sans quoi le prix continuerait de partir au nom d'un collègue.
	 *
	 * Sans effet rétroactif : les arrêtés déjà publiés ne se réécrivent pas.
	 */
	public static function setContribution(Structure $eStructureProducer, Structure $eStructureCommunity, bool $contributes): int {

		return self::setRights($eStructureProducer, $eStructureCommunity, ['contributesQuotation' => $contributes]);

	}

	/**
	 * Les affiliations d'un utilisateur sur un groupe, pour l'écran de réglage.
	 */
	public static function getByUser(\user\User $eUser, Structure $eStructureCommunity): \Collection {

		return Affiliation::model()
			->select(Affiliation::getSelection())
			->whereUser($eUser)
			->whereStructureCommunity($eStructureCommunity)
			->getCollection(index: 'structureProducer');

	}

	/**
	 * Les groupes par lesquels ce lecteur atteint cette ferme, chacun portant son `Community`.
	 *
	 * Mémoïsée parce que deux appelants la demandent et qu'aucun ne peut la passer à l'autre :
	 * \ModulePage valide le droit de lecture avant d'exécuter le corps de la page
	 * (`framework/core/Module.php:5646` puis `:5649`), et un `can*()` ne rend qu'un booléen.
	 * La clé est le couple (ferme, lecteur) et jamais un scalaire : rien ne garantit qu'une
	 * requête ne touche qu'une structure, `StructureLib::runAll()` existe.
	 *
	 * Les bascules de partage vivent sur `Community` et non sur la ligne de `structure` : la
	 * seconde requête les pose sur chaque groupe, ce qui rend `Structure::community()` gratuit
	 * pour tous les appelants suivants.
	 *
	 * Chaque groupe porte en plus `granted`, un masque de publics par droit, qui n'est pas la
	 * demande du groupe mais ce que **cette ferme** a accordé en le rejoignant. Les deux jeux se
	 * ressemblent et ne disent pas la même chose : `community()['shareProduction']` énonce ce que
	 * le groupe demandera aux rattachements suivants, `granted` ce qui est déjà donné et gelé.
	 */
	public static function getCommunitiesByStructure(Structure $eStructureProducer, \user\User $eUser): \Collection {

		$key = $eStructureProducer['id'].'-'.$eUser['id'];

		if(array_key_exists($key, self::$cCommunityByStructure) === FALSE) {
			self::fillCommunitiesByStructures(new \Collection([$eStructureProducer]), $eUser);
		}

		return self::$cCommunityByStructure[$key];

	}

	/**
	 * La même lecture pour plusieurs fermes à la fois, posée dans la mémoïsation du couple.
	 *
	 * ⚠️ La mémoïsation ci-dessus est par (ferme, lecteur) : un écran qui pose une question de
	 * droit sur N fermes la manque N fois, et `Structure::canRead()` ne rend qu'un booléen, donc
	 * rien ne se passe d'un appel à l'autre. Remplir les N clés d'une requête rend les `can*()`
	 * suivants gratuits. Appelée par la comparaison, qui décide ferme par ferme si la ligne mène
	 * au plan de culture.
	 */
	public static function fillCommunitiesByStructures(\Collection $cStructureProducer, \user\User $eUser): void {

		$cStructureProducerMissing = $cStructureProducer->find(
			fn(Structure $eStructureProducer) => array_key_exists($eStructureProducer['id'].'-'.$eUser['id'], self::$cCommunityByStructure) === FALSE
		);

		if($cStructureProducerMissing->empty()) {
			return;
		}

		// Posées avant la lecture : une ferme sans rattachement n'a aucune ligne, et sa clé doit
		// exister quand même sous peine d'être relue à chaque appel.
		foreach($cStructureProducerMissing as $eStructureProducerMissing) {
			self::$cCommunityByStructure[$eStructureProducerMissing['id'].'-'.$eUser['id']] = new \Collection();
		}

		// Un lecteur anonyme n'a aucun groupe : la jointure sur son adhésion chercherait un
		// utilisateur sans identifiant.
		if($eUser->empty()) {
			return;
		}

		$cAffiliation = self::applyStillValid(Affiliation::model())
			->join(Member::model(), 'm1.structureCommunity = m4.structure')
			->join(Role::model(), 'm2.role = m5.id')
			->select(['structureProducer', 'structureCommunity' => Structure::getSelection(), 'fqnBringing' => new \Sql('m5.fqn'), ...self::SHARES])
			->where('m1.structureProducer', 'IN', $cStructureProducerMissing)
			->where('m4.user', $eUser)
			->where('m4.status', Member::IN)
			->getCollection();

		$cStructureCommunityAll = new \Collection();

		// Deux associés peuvent avoir rattaché la même ferme au même groupe : l'indexation par
		// id de groupe est ce qui rend la liste des bascules, et non celle des rattachements.
		// Le périmètre s'accumule alors au lieu de se recopier, comme `contributes` dans
		// getCommunityStates() : la ferme donne au groupe ce que l'un ou l'autre a ouvert, et
		// poser la valeur de la ligne courante ferait dépendre le résultat de l'ordre du parcours.
		foreach($cAffiliation as $eAffiliation) {

			$cStructureCommunity = self::$cCommunityByStructure[$eAffiliation['structureProducer']['id'].'-'.$eUser['id']];

			$eStructureCommunityAffiliated = $eAffiliation['structureCommunity'];
			$id = $eStructureCommunityAffiliated['id'];

			if($cStructureCommunity->offsetExists($id) === FALSE) {

				// ⚠️ Un tableau sous une seule clé, et non une propriété par droit : un quatrième
				// droit se déclare dans `CommunityRightAiLib` et arrive ici sans qu'aucune ligne
				// le nomme.
				// ⚠️ Une instance par droit, donc une boucle et jamais `array_fill_keys()` : celui-ci
				// n'évalue sa valeur qu'une fois, et les deux clés porteraient le même `Set` — ouvrir
				// la production ouvrirait la copie, sans erreur.
				$eStructureCommunityAffiliated['granted'] = [];

				foreach(self::SHARES as $right) {
					$eStructureCommunityAffiliated['granted'][$right] = new \Set();
				}

				$eStructureCommunityAffiliated['audiencesBringing'] = [];

				$cStructureCommunity[$id] = $eStructureCommunityAffiliated;

				// ⚠️ Un élément par ferme et non un par groupe : le périmètre accumulé ci-dessous
				// appartient au couple, deux fermes du même groupe n'ont pas accepté la même chose.
				$cStructureCommunityAll[] = $eStructureCommunityAffiliated;

			}

			$audienceBringing = self::getAudienceBringing($eAffiliation);

			if(in_array($audienceBringing, $cStructureCommunity[$id]['audiencesBringing'], TRUE) === FALSE) {
				$cStructureCommunity[$id]['audiencesBringing'] = [...$cStructureCommunity[$id]['audiencesBringing'], $audienceBringing];
			}

			// Les périmètres s'accumulent public par public : deux associés peuvent avoir rattaché
			// la même ferme en n'ouvrant pas aux mêmes, et ce que le groupe en lit est l'union.
			foreach(self::SHARES as $right) {

				foreach(CommunityRightAiLib::getAudiences($right) as $audience) {

					if($eAffiliation[$right]->value($audience)) {
						$cStructureCommunity[$id]['granted'][$right]->value($audience, TRUE);
					}

				}

			}

		}

		CommunityLib::prepareByStructures($cStructureCommunityAll);

	}

	/**
	 * Les fermes que ce lecteur atteint par un rattachement qui partage la production.
	 *
	 * C'est l'ensemble du voisinage, et il n'est pas celui des fermes auxquelles le lecteur a
	 * accès : une ferme dont aucun rattachement ne lève `shareProduction` cache sa position sur
	 * sa propre fiche, elle n'a pas à la voir déduite depuis la fiche d'une autre.
	 *
	 * ⚠️ Le périmètre se lit sur le rattachement et non sur le groupe, donc le filtre se pose sur
	 * les rattachements et non sur les groupes qu'on va chercher. Retenir d'abord les groupes qui
	 * lèvent la production reviendrait à montrer toutes les fermes d'un groupe qui vient
	 * d'ouvrir la production, y compris celles qui n'ont accepté que la vente.
	 */
	public static function getSharedStructuresByUser(\user\User $eUser): \Collection {

		$structures = MemberLib::getOnline()->getColumnCollection('structure')->getIds();

		if($structures === []) {
			return new \Collection();
		}

		// Le type se relit ici : la sélection d'un Member ne charge de sa structure que l'id, et
		// un test sur une propriété absente laisserait passer un ensemble vide sans rien dire.
		// `communityType` en plus du type : `CommunityRightAiLib` en a besoin pour dire sous quel
		// régime chaque groupe place le partage de production.
		$cStructureCommunity = Structure::model()
			->select(['id', 'name', 'type', 'communityType'])
			->whereId('IN', $structures)
			->whereType(Structure::COMMUNITY)
			->whereStatus(Structure::ACTIVE)
			->getCollection(index: 'id');

		if($cStructureCommunity->empty()) {
			return new \Collection();
		}

		$cAffiliation = self::applyStillValid(Affiliation::model())
			->join(Role::model(), 'm2.role = m4.id')
			->select(['structureProducer' => Structure::getSelection(), 'structureCommunity', 'shareProduction', 'fqnBringing' => new \Sql('m4.fqn')])
			->where('m1.structureCommunity', 'IN', $cStructureCommunity)
			// ⚠️ `> 0` et non `TRUE` : la colonne est un masque de publics, et `TRUE` la
			// comparerait à 1, c'est-à-dire à « l'équipe du groupe et elle seule ». Le SQL ne fait
			// ici que dégrossir, le public du lecteur se pèse ensuite groupe par groupe.
			->where('m1.shareProduction > 0')
			->getCollection();

		$cStructure = new \Collection();

		foreach($cAffiliation as $eAffiliation) {

			$eStructureCommunityAffiliated = $cStructureCommunity[$eAffiliation['structureCommunity']['id']];

			$audience = CommunityRightAiLib::getAudience($eStructureCommunityAffiliated->getMember()['role']);

			if(CommunityRightAiLib::grants($eStructureCommunityAffiliated, 'shareProduction', $eAffiliation['shareProduction'], $audience, [self::getAudienceBringing($eAffiliation)]) === FALSE) {
				continue;
			}

			$eStructure = $eAffiliation['structureProducer'];

			if($eStructure['status'] === Structure::ACTIVE) {
				$cStructure[$eStructure['id']] = $eStructure;
			}

		}

		return $cStructure;

	}

	/**
	 * Efface les affiliations qu'une sortie rend caduques.
	 *
	 * La même requête couvre les deux cas : quitter le groupe et quitter la ferme.
	 * La structure qu'on quitte est tantôt l'un, tantôt l'autre, et une affiliation
	 * ne survit ni à la perte de l'un ni à celle de l'autre.
	 */
	public static function deleteByMember(\user\User $eUser, Structure $eStructure): int {

		$cAffiliation = Affiliation::model()
			->select('id')
			->whereUser($eUser)
			->or(
				fn() => $this->whereStructureCommunity($eStructure),
				fn() => $this->whereStructureProducer($eStructure)
			)
			->getCollection();

		foreach($cAffiliation as $eAffiliation) {
			self::delete($eAffiliation);
		}

		return $cAffiliation->count();

	}

}
?>
