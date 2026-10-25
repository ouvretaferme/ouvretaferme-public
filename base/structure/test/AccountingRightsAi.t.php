<?php
namespace structure;

/**
 * Droits d'écriture du rôle comptable sur les numéros de compte.
 *
 * Trois écrans ont été ouverts au rôle `accounting = FULL` : la précomptabilité de la structure, les
 * numéros de compte des produits, et ceux des lignes de vente. Ce test vérifie les deux moitiés de
 * l'affaire, l'ouverture et sa borne : le comptable écrit les comptes, et rien d'autre.
 *
 * Aucune écriture en base. Le membre connecté est injecté dans le cache de MemberLib par réflexion,
 * ce qui donne la vraie chaîne (Structure::canManageAccounting → isRole → Member::isRole → Role)
 * sans créer de rôle, donc sans toucher au cache Redis de RoleLib.
 *
 * Lancement : dev/test package=structure file=AccountingRightsAi
 */
class AccountingRightsAiTest extends \Test {

	/** Structure de référence, la même que les tests d'API */
	const STRUCTURE = FixtureAiLib::F1;

	protected Structure $eStructure;

	/** Un identifiant d'utilisateur par jeu de droits : MemberLib::$cMemberOnline est indexé par
	 * utilisateur, deux jeux portés par le même id se masqueraient l'un l'autre. */
	private int $userId = 900000;

	/** État à restaurer : ce test connecte des utilisateurs fictifs dans des caches de processus. */
	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;

	public function init(): void {

		$this->eStructure = Structure::model()
			->select(Structure::getSelection())
			->whereId(self::STRUCTURE)
			->get();

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.self::STRUCTURE.' introuvable : sème le décor par structure/configure/fixtureAi write=1, ou importe la copie de production pour une structure réelle');
		}

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();

	}

	/**
	 * Les caches sont des propriétés de processus, pas des lignes en base : la transaction ne les
	 * annule pas, et un utilisateur fictif laissé connecté suivrait les tests suivants du package.
	 */
	public function finalize(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	private static function getMemberCache(): \ReflectionProperty {

		$property = new \ReflectionProperty(MemberLib::class, 'cMemberOnline');
		$property->setAccessible(TRUE);

		return $property;

	}

	/**
	 * Connecte un membre porteur du rôle demandé et rend la structure telle qu'il la voit.
	 *
	 * L'injection passe par la propriété privée de MemberLib plutôt que par une écriture en base :
	 * c'est le même procédé que \dev\ApiTest::callApi(), qui force Route::$requestMethod par
	 * réflexion pour rejouer une requête sans serveur.
	 */
	private function connectAs(string $accounting, bool $admin = FALSE, bool $selling = FALSE): Structure {

		$eUser = new \user\User(['id' => $this->userId++]);
		\user\ConnectionLib::setOnline($eUser);

		$eMember = new Member([
			'structure' => $this->eStructure['id'],
			'role' => new Role([
				'admin' => $admin,
				'accounting' => $accounting,
				'selling' => $selling,
				'planning' => Role::DENIED,
				'productionAnalyze' => FALSE,
				'productionObserve' => FALSE,
				'commercialisationAnalyze' => FALSE,
			]),
		]);

		$property = self::getMemberCache();
		$property->setValue(NULL, $property->getValue() + [
			$eUser['id'] => new \Collection([$this->eStructure['id'] => $eMember]),
		]);

		return $this->eStructure;

	}

	/** Le contrôle sait-il distinguer ? Sans rôle comptable, tout doit être fermé. */
	public function testWithoutAccountingRoleEverythingIsClosed(): void {

		$eStructure = $this->connectAs(Role::DENIED);

		$this->assertFalse($eStructure->canObserveAccounting(), 'sans rôle : ne consulte pas la comptabilité');
		$this->assertFalse($eStructure->canManageAccounting(), 'sans rôle : ne tient pas la comptabilité');
		$this->assertFalse($eStructure->canManage(), 'sans rôle : n\'administre pas');

	}

	/** Le comptable en lecture seule consulte, mais n'écrit pas les comptes. */
	public function testLimitedObservesButDoesNotWrite(): void {

		$eStructure = $this->connectAs(Role::LIMITED);

		$this->assertTrue($eStructure->canObserveAccounting(), 'LIMITED : consulte');
		$this->assertFalse($eStructure->canManageAccounting(), 'LIMITED : n\'écrit pas');

	}

	/** Le comptable FULL tient la comptabilité sans être administrateur. */
	public function testFullManagesAccountingWithoutBeingAdmin(): void {

		$eStructure = $this->connectAs(Role::FULL);

		$this->assertTrue($eStructure->canObserveAccounting(), 'FULL : consulte');
		$this->assertTrue($eStructure->canManageAccounting(), 'FULL : tient la comptabilité');
		$this->assertFalse($eStructure->canManage(), 'FULL : n\'est pas administrateur');

	}

	/** L'administrateur passe partout, quel que soit son rôle comptable déclaré. */
	public function testAdminPassesEverywhere(): void {

		$eStructure = $this->connectAs(Role::DENIED, admin: TRUE);

		$this->assertTrue($eStructure->canManageAccounting(), 'admin : tient la comptabilité malgré accounting = DENIED');
		$this->assertTrue($eStructure->canManage(), 'admin : administre');

	}

	/**
	 * Le produit : le comptable écrit le numéro de compte et rien d'autre.
	 *
	 * canWrite() garde les prix et le reste de la fiche, canUpdateAccounting() n'ouvre que les deux
	 * colonnes de compte. C'est la borne du chantier : si canWrite() passait à TRUE ici, le comptable
	 * pourrait changer un prix de vente.
	 */
	public function testAccountantWritesProductAccountOnly(): void {

		$eStructure = $this->connectAs(Role::FULL);

		$eProduct = new \product\Product([
			'structure' => $eStructure,
			'status' => \product\Product::ACTIVE,
		]);

		$this->assertTrue($eProduct->canUpdateAccounting(), 'FULL : écrit le compte du produit');
		$this->assertFalse($eProduct->canWrite(), 'FULL : n\'écrit pas le reste de la fiche produit');

	}

	/** Sans rôle comptable, le compte du produit reste fermé. */
	public function testProductAccountClosedWithoutAccountingRole(): void {

		$eStructure = $this->connectAs(Role::DENIED, selling: TRUE);

		$eProduct = new \product\Product([
			'structure' => $eStructure,
			'status' => \product\Product::ACTIVE,
		]);

		$this->assertFalse($eProduct->canUpdateAccounting(), 'vendeur seul : n\'écrit pas le compte du produit');

	}

	/**
	 * La ligne de vente reste au vendeur.
	 *
	 * Décision du 01/09/2026 : le rôle selling garde la main sur accountNumber par le formulaire de
	 * ligne, le comptable passe par le panneau de la précomptabilité, qui est borné côté page.
	 */
	public function testSellingRoleStillWritesItem(): void {

		$eStructure = $this->connectAs(Role::DENIED, selling: TRUE);

		$this->assertTrue($eStructure->canSelling(), 'vendeur : écrit les lignes de vente');

		$eStructureAccountant = $this->connectAs(Role::FULL);

		$this->assertFalse($eStructureAccountant->canSelling(), 'comptable seul : n\'écrit pas les lignes de vente');

	}

}
?>
