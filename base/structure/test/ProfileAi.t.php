<?php
namespace structure;

/**
 * Les lectures d'affiliations qui décident de l'accès aux fiches publiques du lot 6.
 *
 * Trois choses s'y prouvent, et la troisième est la seule qui compte vraiment :
 *
 * 1. la refactorisation de l'invariant dans applyStillValid() ne change pas ce que rend
 *    getStructuresByCommunity(), qui sert déjà l'onglet Fermes ;
 * 2. l'invariant est bien emprunté par les deux lectures neuves — un auteur exclu du groupe
 *    par un simple passage à OUT ferme le rattachement qu'il portait ;
 * 3. le contrôle d'accès de la fiche d'une ferme : ses trois chemins, montrés capables d'ouvrir
 *    et de fermer, et le gate à trois valeurs.
 *
 * Chaque contrôle qui attend un ensemble vide est précédé de son pendant non vide : un test
 * qui ne trouve rien ne prouve rien tant qu'il n'a pas montré qu'il sait trouver.
 *
 * Décor : les trois groupes et les fermes du décor des tests que lit `CommunityFixtureAiTest`, et
 * la ferme école 2 avec sa source, empruntées à la copie de production. Isolation : une transaction
 * par méthode, annulée en tearDown(), qui purge ensuite les caches de 24 h que les ateliers posés
 * dans la transaction ont remplis.
 *
 * Lancement : dev/test package=structure file=ProfileAi
 */
class ProfileAiTest extends CommunityFixtureAiTest {

	/**
	 * La ferme école et sa source, empruntées à la copie de production : une ferme école naît de la
	 * copie de sa source par `SchoolAiLib`, qui ne se rejoue pas sur une structure du décor.
	 */
	const STRUCTURE_SCHOOL = 2;
	const STRUCTURE_SCHOOL_SOURCE = StructureSetting::SCHOOL_STRUCTURES[self::STRUCTURE_SCHOOL]['source'];

	private function connectAs(int $id): \user\User {

		$eUser = \user\UserLib::getById($id, \user\User::getSelection());

		\user\ConnectionLib::setOnline($eUser);

		return $eUser;

	}

	public function init(): void {

		parent::init();

		BorrowedAiLib::assert('base/structure/test/ProfileAi.t.php');

	}

	public function setUp(): void {
		Affiliation::model()->beginTransaction();
	}

	public function tearDown(): void {

		Affiliation::model()->rollBack();

		// ⚠️ Les caches de 24 h du système et des ateliers survivent à l'annulation : lus dans la
		// transaction, ils garderaient ce qu'elle a posé.
		$eStructure = new Structure(['id' => self::STRUCTURE_SHARED]);

		SystemLib::deleteCache($eStructure);
		ProductionLib::deleteCache($eStructure);

	}

	private function getUser(int $id): \user\User {
		return new \user\User(['id' => $id]);
	}

	private function getUserElement(int $id): \user\User {
		return \user\UserLib::getById($id, \user\User::getSelection());
	}

	/** L'extraction de l'invariant ne doit rien changer à ce que lit l'onglet Fermes. */
	public function testCommunityStructuresUnchanged(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eUser = $this->getUser($this->user);

		$cStructureSharing = AffiliationAiLib::getStructuresByCommunity($this->getStructure($this->communitySharing), $eUser);
		$cStructureSilent = AffiliationAiLib::getStructuresByCommunity($this->getStructure($this->communitySilent), $eUser);
		$cStructureBis = AffiliationAiLib::getStructuresByCommunity($this->getStructure($this->communitySharingBis), $eUser);

		foreach([$cStructureSharing, $cStructureSilent, $cStructureBis] as $cStructure) {

			$this->assertTrue($cStructure->notEmpty());

			foreach($cStructure as $eStructure) {
				$this->assertEquals(Structure::ACTIVE, $eStructure['status']);
			}

		}

		// La ferme fermée porte pourtant un rattachement valide : c'est son état à elle qui l'écarte.
		$this->assertFalse($cStructureSharing->offsetExists($this->structureClosed));

		$this->assertTrue($cStructureSharing->offsetExists(self::STRUCTURE_SHARED));
		$this->assertTrue($cStructureSilent->offsetExists(self::STRUCTURE_SILENT));

	}

	/**
	 * Une ferme atteinte par plusieurs groupes les rend tous, chacun portant ses bascules et le
	 * périmètre que cette ferme lui a accepté.
	 */
	public function testCommunitiesByStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$cStructureCommunity = AffiliationAiLib::getCommunitiesByStructure(
			$this->getStructure(self::STRUCTURE_SHARED),
			$this->getUser($this->user)
		);

		$communities = $cStructureCommunity->getKeys();
		sort($communities);

		$this->assertEquals([$this->communitySharing, $this->communitySharingBis], $communities);

		// Chaque groupe rendu porte son Community, sans quoi la fiche ne saurait rien dire de ce
		// qu'il partage.
		foreach([$this->communitySharing, $this->communitySharingBis] as $community) {
			$this->assertTrue($cStructureCommunity[$community]['community']->notEmpty(), 'le groupe '.$community.' porte son Community');
		}

		// Le périmètre se lit sur le rattachement et non sur le groupe : ces deux-là ouvrent la
		// production de cette ferme.
		//
		// ⚠️ `granted` et non une propriété par droit depuis que chacun porte des publics : un
		// quatrième droit se déclare dans `CommunityRightAiLib` et arrive ici sans qu'aucune
		// ligne le nomme.
		foreach([$this->communitySharing, $this->communitySharingBis] as $community) {
			$this->assertTrue($cStructureCommunity[$community]['granted']['shareProduction']->get() > 0);
		}

	}

	/** Le lecteur ne voit que les groupes dont il est lui-même membre. */
	public function testCommunitiesByStructureBoundToReader(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure(self::STRUCTURE_SILENT);

		$cStructureCommunityOwner = AffiliationAiLib::getCommunitiesByStructure($eStructure, $this->getUser($this->user));
		$cStructureCommunityOther = AffiliationAiLib::getCommunitiesByStructure($eStructure, $this->getUser($this->userOther));

		$this->assertEquals([$this->communitySilent], $cStructureCommunityOwner->getKeys());
		$this->assertEquals(0, $cStructureCommunityOwner[$this->communitySilent]['community']['shareProduction']->get());

		$this->assertTrue($cStructureCommunityOther->empty());

	}

	/**
	 * Un auteur exclu du groupe ferme le rattachement qu'il portait, sans qu'aucune ligne
	 * d'affiliation ne soit supprimée.
	 */
	public function testInvariantOnExcludedAuthor(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eUser = $this->getUser($this->user);

		$cCommunity = new \Collection([
			$this->communitySharing => $this->getStructure($this->communitySharing)
		]);

		$eStructure = $this->getStructure(self::STRUCTURE_SHARING_ONLY);

		$this->assertEquals(1, AffiliationAiLib::getUsersByStructure($eStructure, $cCommunity)->count());

		$eMember = Member::model()
			->select(['id', 'status'])
			->whereUser($eUser)
			->whereStructure($this->communitySharing)
			->get();

		$eMember['status'] = Member::OUT;

		Member::model()->select('status')->update($eMember);

		$this->assertEquals(0, AffiliationAiLib::getUsersByStructure($eStructure, $cCommunity)->count());

		// L'exclusion ferme le rattachement sans effacer sa ligne : c'est la jambe que
		// deleteByMember() ne coupe pas, et la raison d'être des deux jointures.
		$this->assertEquals(
			1,
			Affiliation::model()
				->whereStructureProducer(self::STRUCTURE_SHARING_ONLY)
				->whereStructureCommunity($this->communitySharing)
				->count()
		);

	}

	/**
	 * Les trois chemins d'accès à la fiche d'une ferme, chacun montré capable d'ouvrir et de fermer.
	 *
	 * Le premier est le plus important : 5 548 des 5 557 fermes producer actives n'ont aucune
	 * affiliation, et c'est leur exploitant qui suit le lien depuis l'onglet Fermes.
	 */
	public function testCanReadProfilePaths(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureSilent = $this->getStructure(self::STRUCTURE_SILENT);
		$eStructureShared = $this->getStructure(self::STRUCTURE_SHARED);

		// Le lecteur principal est membre de la ferme silencieuse et du seul groupe qui la porte.
		$this->connectAs($this->user);
		$this->assertTrue($eStructureSilent->canReadProfile());
		$this->assertTrue($eStructureShared->canReadProfile());

		// Le second lecteur n'est ni membre de la ferme silencieuse ni du groupe qui la porte, mais il
		// partage les deux groupes partageants avec F6.
		$this->connectAs($this->userOther);
		$this->assertFalse($eStructureSilent->canReadProfile());
		$this->assertTrue($eStructureShared->canReadProfile());

	}

	/**
	 * Le socle se donne entre fermes d'un groupe commun, et le droit de partage n'y entre plus.
	 *
	 * ⚠️ Depuis le 10/09/2026 la fiche ne dépend plus de `shareProduction` : elle montre ce que la
	 * ferme **est**, quand le droit porte ce qu'elle **fait cette saison**. La ferme silencieuse,
	 * qui n'ouvre sa production à personne, est donc lue par qui partage un groupe avec elle — et
	 * reste fermée à qui n'en partage aucun. La troisième valeur, `PROFILE_SELF`, a disparu avec
	 * son bandeau : il disait « le groupe ne voit pas ceci », ce qui est devenu faux.
	 *
	 * Tranché le 10/09/2026, « ok pour le 22 » — `decisions.md`.
	 */
	public function testProfileShareIsTheGroupAndNotTheRight(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureSilent = $this->getStructure(self::STRUCTURE_SILENT);
		$eStructureShared = $this->getStructure(self::STRUCTURE_SHARED);

		// Le lecteur partage un groupe avec les deux, dont le silencieux qui n'ouvre rien.
		$this->connectAs($this->user);
		$this->assertEquals(Structure::PROFILE_SHARED, $eStructureShared->getProfileShare());
		$this->assertEquals(Structure::PROFILE_SHARED, $eStructureSilent->getProfileShare());

		// L'autre ne partage aucun groupe avec le silencieux : le socle lui reste fermé.
		$this->connectAs($this->userOther);
		$this->assertEquals(Structure::PROFILE_SHARED, $eStructureShared->getProfileShare());
		$this->assertEquals(Structure::PROFILE_HIDDEN, $eStructureSilent->getProfileShare());

	}

	/** Une boutique et une ferme fermée n'ont pas de fiche, une ferme active en a une. */
	public function testAcceptReadProfile(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertTrue($this->getStructure(self::STRUCTURE_SHARED)->acceptReadProfile());
		$this->assertFalse($this->getStructure($this->structureClosed)->acceptReadProfile());
		$this->assertFalse($this->getStructure($this->communitySharing)->acceptReadProfile());

	}

	/**
	 * Le voisinage se borne au partagé, et la distance prouve l'ordre des axes.
	 *
	 * Tallende (F6) et Beaulieu (F7) sont à 53,6 km l'une de l'autre, points déclarés par le décor.
	 * Lues dans l'ordre que le nom de la colonne suggère en base — latitude d'abord — les
	 * mêmes coordonnées rendraient environ 73 km, sans la moindre erreur.
	 */
	public function testNeighboursBoundToShared(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->connectAs($this->user);

		$eStructure = $this->getStructure(self::STRUCTURE_SHARED);
		$eSystem = SystemLib::getByStructure($eStructure);

		// La limite est relevée : ce contrôle porte sur la règle, une ferme partagée par un groupe
		// commun est une voisine, et non sur le classement par distance.
		$cStructureNeighbour = ProfileAiLib::getNeighbours($eStructure, $eSystem, $this->getUserElement($this->user), limit: 100);

		$this->assertTrue($cStructureNeighbour->offsetExists(self::STRUCTURE_SHARING_ONLY));
		$this->assertFalse($cStructureNeighbour->offsetExists(self::STRUCTURE_SILENT));
		$this->assertFalse($cStructureNeighbour->offsetExists(self::STRUCTURE_SHARED));

		$distance = $cStructureNeighbour[self::STRUCTURE_SHARING_ONLY]['distance'];

		$this->assertTrue($distance > 52000 and $distance < 55000, 'Distance Tallende-Beaulieu : '.round($distance).' m');

		$this->assertEquals('Beaulieu', $cStructureNeighbour[self::STRUCTURE_SHARING_ONLY]['place']);

	}

	/**
	 * Le périmètre du groupe se recopie sur le rattachement au moment où il se crée.
	 *
	 * Le groupe témoin ouvre la production, et elle seule : un périmètre nul se confondrait avec
	 * le défaut de la colonne, un périmètre plein avec une recopie en bloc. Les trois valeurs
	 * doivent donc être lues séparément pour que le contrôle prouve qu'il sait distinguer.
	 */
	public function testShareCopiedAtCreation(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eUser = $this->getUserElement($this->user);
		$eStructureCommunity = $this->getStructure($this->communitySilent);

		$eCommunity = CommunityLib::getByStructure($eStructureCommunity);
		$eCommunity['shareProduction'] = \Set::fromArray(CommunityRightAiLib::AUDIENCES);
		$eCommunity['shareProductionExport'] = new \Set();

		Community::model()->select(['shareProduction', 'shareProductionExport'])->update($eCommunity);

		AffiliationAiLib::create(new Affiliation([
			'user' => $eUser,
			'structureProducer' => $this->getStructure(self::STRUCTURE_SHARED),
			'structureCommunity' => $eStructureCommunity
		]));

		$eAffiliation = Affiliation::model()
			->select(['shareProduction', 'shareProductionExport'])
			->whereUser($eUser)
			->whereStructureProducer(self::STRUCTURE_SHARED)
			->whereStructureCommunity($this->communitySilent)
			->get();

		$this->assertTrue($eAffiliation->notEmpty());

		// Les deux publics du groupe se recopient sur le rattachement neuf, l'export reste vide.
		$this->assertEquals(CommunityRightAiLib::AUDIENCES, CommunityRightAiLib::getGranted($eStructureCommunity, 'shareProduction', $eAffiliation['shareProduction']));
		$this->assertEquals(0, $eAffiliation['shareProductionExport']->get());

	}

	/**
	 * Le périmètre accepté au rattachement ne suit pas la bascule du groupe.
	 *
	 * Le contrôle porte dans les deux sens : la ferme dont le rattachement se referme sort du
	 * voisinage, celle dont il reste ouvert y demeure. Le dernier contrôle est celui qui donne
	 * son sens aux deux autres — sans lui, le vert prouverait seulement que le groupe s'est
	 * fermé, ce qui n'est pas ce que le lot promet.
	 */
	public function testShareFrozenAtAffiliation(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->connectAs($this->user);

		$eUser = $this->getUserElement($this->user);
		$eStructureSharingOnly = $this->getStructure(self::STRUCTURE_SHARING_ONLY);

		$cStructureShared = AffiliationAiLib::getSharedStructuresByUser($eUser);

		$this->assertTrue($cStructureShared->offsetExists(self::STRUCTURE_SHARING_ONLY));
		$this->assertTrue($cStructureShared->offsetExists(self::STRUCTURE_SHARED));

		$eAffiliation = Affiliation::model()
			->select(['id', 'shareProduction'])
			->whereUser($eUser)
			->whereStructureProducer(self::STRUCTURE_SHARING_ONLY)
			->whereStructureCommunity($this->communitySharing)
			->get();

		$eAffiliation['shareProduction'] = new \Set();

		Affiliation::model()->select('shareProduction')->update($eAffiliation);

		// ⚠️ **Le refermement se lit sur le périmètre de ce groupe-ci, pas sur l'absence de la
		// ferme.** La liste des groupes ne se filtre pas sur le droit : elle porte le périmètre sous
		// `granted`, ce qui est exactement la valeur que ce contrôle doit lire.
		//
		// ⚠️ Un seul appel, et après l'écriture : `getCommunitiesByStructure()` est mémoïsée par
		// couple (ferme, lecteur), donc un appel posé avant rendrait le même objet après. Le
		// pendant non vide est cinq lignes plus haut, sur la liste des fermes partagées.
		$cStructureCommunity = AffiliationAiLib::getCommunitiesByStructure($eStructureSharingOnly, $eUser);

		$this->assertEquals(0, $cStructureCommunity[$this->communitySharing]['granted']['shareProduction']->get());

		$this->assertTrue(AffiliationAiLib::getSharedStructuresByUser($eUser)->offsetExists(self::STRUCTURE_SHARED));

		$this->assertTrue(CommunityLib::getByStructure($this->getStructure($this->communitySharing))['shareProduction']->get() > 0);

	}

	/**
	 * Un second associé reprend le périmètre gelé du couple, il ne rouvre pas ce qui est fermé.
	 *
	 * Le décor se pose dans le test et ne se lit pas en base : la divergence entre le groupe et
	 * le rattachement est ce que le test mesure, l'attendre des données de dev le rendrait vert
	 * ou rouge selon ce qu'une autre session y a écrit.
	 */
	public function testShareNotReopenedBySecondAssociate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eUser = $this->getUserElement($this->user);
		$eStructureCommunity = $this->getStructure($this->communitySharing);
		$eStructureProducer = $this->getStructure($this->structureOtherAssociate);

		$this->assertTrue(CommunityLib::getByStructure($eStructureCommunity)['shareProduction']->get() > 0);

		$eAffiliationFirst = Affiliation::model()
			->select(['id', 'user', 'shareProduction', 'shareProductionExport'])
			->whereStructureProducer($eStructureProducer)
			->whereStructureCommunity($eStructureCommunity)
			->get();

		$this->assertTrue($eAffiliationFirst->notEmpty());
		$this->assertFalse($eAffiliationFirst['user']['id'] === $eUser['id']);

		// ⚠️ Les deux valeurs se posent, aucune ne se lit : opposées l'une à l'autre, elles
		// prouvent que la reprise recopie le couple et non un défaut. Attendre l'état des données
		// de dev a viré au rouge le 2026-09-04 quand une fixture de recette avait ouvert le second
		// périmètre — le test accusait le code d'un changement de données.
		$eAffiliationFirst['shareProduction'] = new \Set();
		$eAffiliationFirst['shareProductionExport'] = \Set::fromArray(CommunityRightAiLib::AUDIENCES);

		Affiliation::model()->select(['shareProduction', 'shareProductionExport'])->update($eAffiliationFirst);

		AffiliationAiLib::create(new Affiliation([
			'user' => $eUser,
			'structureProducer' => $eStructureProducer,
			'structureCommunity' => $eStructureCommunity
		]));

		$eAffiliationSecond = Affiliation::model()
			->select(['shareProduction', 'shareProductionExport'])
			->whereUser($eUser)
			->whereStructureProducer($eStructureProducer)
			->whereStructureCommunity($eStructureCommunity)
			->get();

		$this->assertTrue($eAffiliationSecond->notEmpty());

		// Le groupe lève la production, le couple ne l'ouvre pas : c'est le couple qui gagne,
		// sans quoi le premier associé verrait sa fermeture défaite par le second.
		$this->assertEquals(0, $eAffiliationSecond['shareProduction']->get());

		// Et la reprise est bien une reprise, pas une remise à zéro : l'autorisation de copie,
		// ouverte sur le couple, le reste — alors que le groupe, lui, ne la lève pas.
		$this->assertTrue($eAffiliationSecond['shareProductionExport']->get() > 0);

	}

	/**
	 * Les deux listes du panneau se partagent le parc sans recouvrement, sur le couple.
	 *
	 * Une ferme déjà rattachée par un associé n'est plus « à partager », elle est partagée ; et
	 * la liste des partagées se borne à ce que ce lecteur administre aujourd'hui, faute de quoi
	 * elle proposerait un retrait que l'action refuse.
	 */
	public function testSharedListBoundToCouple(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eUser = $this->getUserElement($this->user);
		$eStructureCommunity = $this->getStructure($this->communitySharing);

		$cStructureShared = StructureLib::getSharedByUser($eUser, $eStructureCommunity);
		$cStructureShareable = StructureLib::getShareableByUser($eUser, $eStructureCommunity);

		$this->assertTrue($cStructureShared->notEmpty());
		$this->assertTrue($cStructureShareable->notEmpty());

		$this->assertTrue($cStructureShared->offsetExists($this->structureOtherAssociate));
		$this->assertFalse($cStructureShareable->offsetExists($this->structureOtherAssociate));

		// La règle, et non un effectif : ce que le panneau montre est ce que le geste peut
		// atteindre. Aucun élément ne s'y trouve sans que l'action l'accepte.
		foreach($cStructureShared as $eStructure) {
			$this->assertTrue(MemberLib::isAdmin($eUser, $eStructure));
		}

	}

	/** Sans coordonnées, aucun voisin : le bloc disparaît plutôt que de nommer tout le parc. */
	public function testNeighboursWithoutCoordinates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->connectAs($this->user);

		$eStructure = $this->getStructure(self::STRUCTURE_SHARED);

		$this->assertEquals(0, ProfileAiLib::getNeighbours($eStructure, new System(), $this->getUserElement($this->user))->count());

	}

	/**
	 * La liste des propriétés du profil ne déborde jamais du formulaire qui les poste.
	 *
	 * ⚠️ Ce contrôle existe pour une raison précise : `profileVisibility` est passé derrière
	 * `FEATURE_INRAE`, et le champ a été retiré du formulaire sans que la liste bouge. Une
	 * propriété construite mais jamais postée n'est pas une propriété ignorée — `build()` écrit
	 * NULL dessus (`framework/core/Data.php:1795`), donc chaque enregistrement du profil aurait
	 * effacé la visibilité de toutes les fermes, en silence et sur une réponse 200.
	 *
	 * Le contrôle vaut sous les deux valeurs du drapeau : les deux endroits dérivent de la même
	 * condition, et c'est leur accord qui est vérifié ici, pas la valeur du drapeau.
	 */
	public function testProfilePropertiesMatchTheForm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure(self::STRUCTURE_SHARED);

		$form = new StructureUi()->updateProfileForm($eStructure);

		foreach(StructureLib::getPropertiesProfile() as $property) {
			$this->assertTrue(str_contains($form, 'data-wrapper="'.$property.'"'));
		}

	}

	/**
	 * La ferme école porte son **département**, jamais sa commune ni le point de sa source.
	 *
	 * ⚠️ C'est la propriété d'anonymisation elle-même, et elle a remplacé le 2026-09-05 une
	 * première version qui retirait purement la localisation. On brouille par le flou plutôt que
	 * par le vide : le département garde la région, le climat et le contexte que la suppression
	 * détruisait, et il compte des centaines de fermes.
	 *
	 * Le contrôle porte sur une **distance** et non sur des noms : un centre départemental écrit
	 * dans le bon ordre d'axes et posé dans le mauvais donnerait un point en pleine mer sans
	 * qu'aucune erreur ne se lève. Il exige donc que le point soit loin de la ferme réelle sans
	 * l'être absurdement — hors de sa commune, dans son département.
	 */
	public function testSchoolCarriesItsDepartment(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eSystemSchool = SystemLib::getByStructure($this->getStructure(self::STRUCTURE_SCHOOL));
		$eSystemSource = SystemLib::getByStructure($this->getStructure(self::STRUCTURE_SCHOOL_SOURCE));

		$this->assertEquals('Puy-de-Dôme', $eSystemSchool['place']);
		$this->assertDiffer($eSystemSource['place'], $eSystemSchool['place']);

		$this->assertDiffer($eSystemSource['placeLngLat'], $eSystemSchool['placeLngLat']);

		$distance = $this->getDistance($eSystemSource['placeLngLat'], $eSystemSchool['placeLngLat']);

		$this->assertTrue($distance > 1);
		$this->assertTrue($distance < 150);

	}

	/**
	 * Le département se déduit du code postal, et refuse plutôt que d'inventer.
	 *
	 * ⚠️ Les deux premiers caractères ne suffisent pas : la Corse porte `2A`/`2B` sur des codes en
	 * `20…` et l'outre-mer tient sur trois chiffres. Un `substr()` rendrait « 20 » et « 97 », deux
	 * départements qui n'existent pas — donc un centre faux au lieu d'un refus.
	 */
	public function testDepartmentRefusesRatherThanGuesses(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertEquals('63', \structure\SchoolAiLib::getDepartment('63450'));
		$this->assertEquals('2A', \structure\SchoolAiLib::getDepartment('20137'));
		$this->assertEquals('2B', \structure\SchoolAiLib::getDepartment('20250'));
		$this->assertEquals('974', \structure\SchoolAiLib::getDepartment('97400'));

		foreach([NULL, '', '6345', '634505', 'ABCDE', '99999'] as $postcode) {
			$this->assertEquals(NULL, \structure\SchoolAiLib::getDepartment($postcode));
		}

	}

	/** La distance en kilomètres entre deux points rendus en (longitude, latitude). */
	private function getDistance(array $point1, array $point2): float {

		[$longitude1, $latitude1] = $point1;
		[$longitude2, $latitude2] = $point2;

		$deltaLatitude = deg2rad($latitude2 - $latitude1);
		$deltaLongitude = deg2rad($longitude2 - $longitude1);

		$haversine = sin($deltaLatitude / 2) ** 2
			+ cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($deltaLongitude / 2) ** 2;

		return 2 * 6371.0 * asin(sqrt($haversine));

	}


	/**
	 * Le fond de courbes est le portage exact d'une référence JS.
	 *
	 * ⚠️ Ce contrôle n'est pas du zèle : le portage repose sur de l'arithmétique 32 bits que PHP
	 * n'a pas nativement, et une multiplication naïve déborde l'entier 64 bits vers un flottant.
	 * Le dessin change alors entièrement, sans la moindre erreur — juste un autre relief. Un seul
	 * bit modifié dans le générateur décale la sortie dès le 156e octet, donc seule une égalité
	 * stricte sait voir la divergence.
	 *
	 * Les empreintes viennent des cinq SVG livrés avec la référence
	 * (`.claude/state/INRAE/placeholder-carte-courbes-A-*.svg`), et le portage leur a été comparé
	 * **octet pour octet** le 2026-09-05. Le test porte l'empreinte et non le fichier : ces SVG
	 * sont des notes de travail, pas du code déployé, et un contrôle qui dépendrait de leur
	 * présence passerait au vert le jour où le dossier disparaîtrait.
	 *
	 * Les cinq couvrent les deux formats et trois graines : elles disent aussi qu'un identifiant
	 * donne toujours le même fond.
	 */
	public function testNoiseContoursMatchTheReference(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$references = [
			['26ccc3b42ee62cc227c4491c357b6cf3', 4301, 480, 300],
			['9f9fb9a9ba1f0feba3a01423d29be4d1', 4301, 350, 100],
			['86751a0e106aee0c0939931ac711a182', 1, 480, 300],
			['012a6a6c923fb447a51a658d43dabbf1', 2, 480, 300],
			['9424af8301f244b49091fee5fd49913e', 3, 480, 300]
		];

		foreach($references as [$fingerprint, $seed, $width, $height]) {
			$this->assertEquals($fingerprint, md5(\main\DesignUi::getNoiseContours($seed, $width, $height)));
		}

		// Deux graines donnent deux fonds : sans quoi les égalités ci-dessus passeraient aussi sur
		// un générateur qui rendrait toujours la même chose.
		$this->assertDiffer(
			\main\DesignUi::getNoiseContours(1, 480, 300),
			\main\DesignUi::getNoiseContours(2, 480, 300)
		);

	}

	/**
	 * La copie d'un plan de culture, accordée d'office aux formateurs et à eux seuls.
	 *
	 * Le second lecteur administre les deux groupes partageants, où F6 est rattachée sans avoir
	 * ouvert la copie. Trois temps sur les mêmes groupes :
	 * - en groupe de producteurs puis en réseau, le groupe se propose mais la ferme décide, et
	 *   elle n'a rien ouvert : c'est le pendant qui prouve que la porte sait refuser ;
	 * - en formation, le formateur copie sans que la ferme ait rien à ouvrir (décidé le
	 *   2026-09-27, après que l'écran eut reproché à chaque ferme d'étudiant de ne pas l'avoir fait) ;
	 * - un étudiant ne copie pas : son public ne reçoit pas le droit, et la route de copie, qui ne
	 *   lit que `canExportProductionByCommunity()`, le refuse aussi quand il nomme le groupe dans
	 *   un POST forgé.
	 */
	public function testExportInEducationGoesToStaffOnly(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->connectAs($this->userOther);

		$ids = [$this->communitySharing, $this->communitySharingBis];

		foreach([NULL, Structure::NETWORK, Structure::EDUCATION] as $communityType) {

			if($communityType !== NULL) {
				$this->setCommunityType($ids, $communityType);
			}

			$eStructureShared = $this->getStructure(self::STRUCTURE_SHARED);
			$eStructureCommunityExport = $eStructureShared->getExportCommunity();

			$this->assertTrue($eStructureCommunityExport->notEmpty(), ($communityType ?? 'producteurs').' : le groupe se propose');
			$this->assertTrue(in_array($eStructureCommunityExport['id'], $ids, TRUE));

			$expected = ($communityType === Structure::EDUCATION);

			$this->assertEquals($expected, $eStructureCommunityExport->grantsCommunityRight('shareProductionExport'), ($communityType ?? 'producteurs').' : copie accordée');
			$this->assertEquals($expected, $eStructureShared->canExportProductionByCommunity($eStructureCommunityExport), ($communityType ?? 'producteurs').' : route de copie');

		}

		// Le public ne se lit que sur l'adhésion, et le formateur est pris ici avant de devenir
		// étudiant : la collection des groupes se relit ensuite sous son nouveau rôle.
		$eStructureCommunityEducation = $this->getStructure(self::STRUCTURE_SHARED)->getExportCommunity();

		$this->assertFalse(
			CommunityRightAiLib::grants($eStructureCommunityEducation, 'shareProductionExport', new \Set(), Community::MEMBER, $eStructureCommunityEducation['audiencesBringing']),
			'le public des étudiants ne reçoit pas la copie'
		);

		foreach($ids as $id) {

			Member::model()
				->whereUser($this->userOther)
				->whereStructure($id)
				->update(['role' => RoleLib::getOne($this->getStructure($id), 'member')]);

		}

		$this->forgetMemoized();

		$eStructureShared = $this->getStructure(self::STRUCTURE_SHARED);

		$this->assertTrue($eStructureShared->getExportCommunity()->empty(), 'l\'étudiant ne voit ni le geste ni la phrase de refus');
		$this->assertFalse($eStructureShared->canExportProductionByCommunity($eStructureCommunityEducation), 'la route de copie refuse l\'étudiant qui nomme le groupe');

	}

	/**
	 * ⚠️ Les groupes d'une ferme et les adhésions du lecteur sont mémoïsés par processus : sans cet
	 * oubli, le temps suivant relirait les groupes sous leur type précédent.
	 */
	private function setCommunityType(array $ids, string $communityType): void {

		foreach($ids as $id) {

			$eStructureCommunity = $this->getStructure($id);
			$eStructureCommunity['communityType'] = $communityType;

			Structure::model()->select(['communityType'])->update($eStructureCommunity);

		}

		$this->forgetMemoized();

	}

}
?>