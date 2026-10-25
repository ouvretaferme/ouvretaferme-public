<?php
namespace structure;

/**
 * L'onglet Données porte les intégrations à côté des imports et des exports, et les deux ne
 * s'ouvrent pas sur la même règle : les imports et les exports demandent une identité légale
 * (`acceptImportExport()`), l'API non (`acceptApi()`). L'onglet se posait sur la première seule, et un
 * groupement de producteurs qui n'a pas rempli son identité légale perdait ses clés d'API avec lui,
 * la page restant ouverte à qui en connaissait l'adresse (signalé par un utilisateur le 18/10).
 *
 * B1, groupement du décor sans identité légale, sous un administrateur fabriqué comme dans
 * `MenuEntriesTest` : B1 n'a aucun membre. Aucune écriture en base.
 *
 * Lancement : dev/test package=structure file=MenuDataAi
 */
class MenuDataAiTest extends \Test {

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();

	}

	public function finalize(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);
		\user\ConnectionLib::setOnline($this->eUserInitial);
		MemberLib::clearCache();

	}

	public function testDataTabOpensForApiWithoutLegalIdentity(): void {

		$eStructure = StructureLib::getById(FixtureAiLib::B1);

		$eMemberTemplate = Member::model()
			->select(Member::getSelection())
			->whereStructure(FixtureAiLib::F1)
			->whereStatus(Member::IN)
			->sort(['id' => SORT_ASC])
			->get();

		if($this->assertDecorPresent($eStructure->isShop() and $eMemberTemplate->notEmpty(), 'B1 est un groupement, et F1 a un membre dont l\'administrateur fabriqué reprend les vues') === FALSE) {
			return;
		}

		$this->assertFalse($eStructure->acceptImportExport(), 'contrôle du décor : B1 n\'a pas d\'identité légale, donc ni import ni export');
		$this->assertTrue($eStructure->acceptApi(), 'contrôle du décor : B1 a accès à l\'API');

		$this->connectAdmin($eMemberTemplate, $eStructure);
		Structure::setConnected($eStructure);

		$this->assertTrue(in_array('data', MenuLib::getMenus($eStructure)['settings'], TRUE), 'l\'administrateur de B1 a l\'onglet Données');
		$this->assertEquals(['api'], MenuLib::getCategories($eStructure, 'data'), 'l\'onglet Données de B1 ne porte que les intégrations');

	}

	private function connectAdmin(Member $eMemberTemplate, Structure $eStructure): void {

		$eUser = new \user\User(['id' => 930500]);
		\user\ConnectionLib::setOnline($eUser);

		$eMember = clone $eMemberTemplate;
		$eMember['structure'] = new Structure(['id' => $eStructure['id']]);
		$eMember['role'] = new Role([
			'admin' => TRUE,
			'planning' => Role::FULL,
			'selling' => TRUE,
			'productionAnalyze' => TRUE,
			'productionObserve' => FALSE,
			'productionExport' => FALSE,
			'commercialisationAnalyze' => TRUE,
			'accounting' => Role::FULL,
			'accountingAnalyze' => TRUE,
			'sections' => Role::ALL,
		]);

		MemberLib::clearCache();

		self::getMemberCache()->setValue(NULL, [
			$eUser['id'] => new \Collection([$eStructure['id'] => $eMember]),
		]);

	}

	private static function getMemberCache(): \ReflectionProperty {
		return new \ReflectionProperty(MemberLib::class, 'cMemberOnline');
	}

}
?>
