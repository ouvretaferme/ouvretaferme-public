<?php
namespace structure;

/**
 * Le menu rend ce que `MenuLib` dit ouvert, et le lire là plutôt que de le recalculer ne change rien au
 * rendu. Les conditions de droit et d'ouverture des univers, des onglets et de leurs sous-entrées ont
 * quitté `MenuUi` pour `MenuLib` (lot L42b du tableau de bord) : ce test tient l'empreinte de ce que le
 * menu rendait avant ce déplacement, et la compare à ce qu'il rend aujourd'hui.
 *
 * Une empreinte par structure du décor et par profil : le rendu de `getMainTabs()` tel que le gabarit
 * le demande (univers nommés, groupe Bureau, ce qui fait aussi le panneau du téléphone), puis sans
 * univers nommés sur chaque onglet de `NAVS`, pour que le fil d'Ariane lise les sous-entrées de chacun.
 *
 * Les profils sont les membres de la structure, puis un membre fabriqué par jeu de droits de `ROLES`,
 * injecté dans le cache de `MemberLib` comme le fait `AccessSectionAiTest` : F3 n'a aucun membre, et
 * les membres du décor ne couvrent pas chaque droit que le menu interroge. Aucune écriture en base.
 *
 * Une empreinte qui change dit qu'un rendu a changé, sans dire lequel. Pour le voir, comparer le rendu
 * de la structure et du profil nommés avant et après le diff, dans `getRender()`.
 *
 * Lancement : dev/test package=structure file=MenuEntries
 */
class MenuEntriesTest extends \Test {

	const STRUCTURES = [
		FixtureAiLib::F1 => 'F1',
		FixtureAiLib::F2 => 'F2',
		FixtureAiLib::F3 => 'F3',
		FixtureAiLib::A1 => 'A1',
		FixtureAiLib::B1 => 'B1',
	];

	/**
	 * Un onglet par menu qui a des sous-entrées, avec l'une d'elles, et deux onglets sans sous-entrée.
	 */
	const NAVS = [
		['planning', Member::WEEKLY],
		['cultivation', 'series'],
		['analyze-production', 'cultivation'],
		['selling', 'sale'],
		['communications', 'website'],
		['analyze-commercialisation', 'sales'],
		['accounting', 'operations'],
		['structure', 'general'],
		['feature', 'settings-production'],
		['data', 'export'],
		['team', NULL],
		['planning-v2', NULL],
	];

	/**
	 * Les jeux de droits fabriqués, un par question que le menu pose au rôle.
	 */
	const ROLES = [
		'admin' => ['admin' => TRUE, 'planning' => Role::FULL, 'selling' => TRUE, 'accounting' => Role::FULL, 'productionAnalyze' => TRUE, 'commercialisationAnalyze' => TRUE, 'accountingAnalyze' => TRUE],
		'planning-full' => ['planning' => Role::FULL],
		'planning-limited' => ['planning' => Role::LIMITED],
		'selling' => ['selling' => TRUE],
		'accounting-full' => ['accounting' => Role::FULL],
		'accounting-limited' => ['accounting' => Role::LIMITED],
		'accounting-analyze' => ['accountingAnalyze' => TRUE],
		'production-observe' => ['productionAnalyze' => TRUE, 'productionObserve' => TRUE],
		'commercialisation-analyze' => ['commercialisationAnalyze' => TRUE],
		'none' => [],
	];

	/**
	 * Prises avant le déplacement des conditions dans `MenuLib`, le 15/10, sur le décor tel que
	 * `FixtureAiLib::DECOR` le décrit, et reprises le 17/10 quand la colonne de l'AMAP a laissé place
	 * aux menus de son bandeau (`MenuLib::getAmapGroupMenus()`) : rendues avant et après sur les mêmes
	 * `NAVS`, elles ne diffèrent que pour trois profils de A1, ceux dont la colonne portait l'AMAP.
	 * Reprises toutes le 17/10 au retrait des raccourcis et des nombres (L49) : le rendu ne porte plus
	 * `getEntries()`, et le groupe Bureau n'a plus que ses entrées `data-home-nav` et l'emplacement
	 * de « Du nouveau ».
	 * Reprises le 18/10 pour les administrateurs de F1, F2 et F3, qui gagnent l'entrée « Fonctionnalités » de la
	 * catégorie Général (lot 5-fonctionnalites de la RH).
	 * Reprise le 18/10 pour l'administrateur de B1, qui gagne l'onglet Données et ses intégrations sans
	 * identité légale (`MenuDataAiTest`).
	 * Reprises le 18/10 pour les profils qui voient la section des réglages à la plume, renommée
	 * « Modules » (lot 5-fonctionnalites de la RH).
	 * Clé : `<structure>/<profil>`, le profil étant l'adresse d'un membre ou un jeu de `ROLES`.
	 */
	const FINGERPRINTS = [
		'F1/decor.f1.admin@example.org' => '6aa51d25d5edb1c598cd474174df7c05',
		'F1/decor.f1.planning@example.org' => 'b30b7476543492bb64555b593af08f12',
		'F1/decor.f1.comptable@example.org' => '660e0005b91eb1811154f5493df65dbb',
		'F1/decor.f1.observateur@example.org' => 'b4e1fef945808cdeb3f2b44dc0a3fc32',
		'F1/admin' => '6aa51d25d5edb1c598cd474174df7c05',
		'F1/planning-full' => 'b30b7476543492bb64555b593af08f12',
		'F1/planning-limited' => 'b30b7476543492bb64555b593af08f12',
		'F1/selling' => '1b94b7ce09be9f35711d4f73636ca75d',
		'F1/accounting-full' => '660e0005b91eb1811154f5493df65dbb',
		'F1/accounting-limited' => '68368a12d9778752cbb79f609be7f149',
		'F1/accounting-analyze' => '71b478cfcc8543df6d67ea01fd952602',
		'F1/production-observe' => 'b4e1fef945808cdeb3f2b44dc0a3fc32',
		'F1/commercialisation-analyze' => 'e3b7f2413b5aa6695af9a742e2cfc70c',
		'F1/none' => 'adc1942ea27c1b644c15d5729f4513d6',
		'F2/decor.f2.admin@example.org' => 'd9826696cd9b6cbb98b100e8655617c3',
		'F2/admin' => 'd9826696cd9b6cbb98b100e8655617c3',
		'F2/planning-full' => '833d47f5e4d0944584374e78f9371310',
		'F2/planning-limited' => '833d47f5e4d0944584374e78f9371310',
		'F2/selling' => '55d5b207317895f1149cd47191ca7e9f',
		'F2/accounting-full' => 'b97c5ce79adeb56cb650d35b8e733f02',
		'F2/accounting-limited' => '5617099b6fff8e9d513ec1c78d0e5c7a',
		'F2/accounting-analyze' => '4031e1162b35345dbf576bda3a8b0540',
		'F2/production-observe' => 'ac0659f8676171596b5cbae73cf6c74e',
		'F2/commercialisation-analyze' => 'a1cfbf3c6273a900af80af723aafa885',
		'F2/none' => 'e94683787b097940a7d21c2927238a42',
		'F3/admin' => '6172c8730b6971abb38c1fcb5611430d',
		'F3/planning-full' => 'c3cffa01e123a9a70226d474de7b2165',
		'F3/planning-limited' => 'c3cffa01e123a9a70226d474de7b2165',
		'F3/selling' => 'ea036ed44d6ac8b9aa6c721f1d854094',
		'F3/accounting-full' => 'e14fb1cb54db6ef0c1b6c3299cba9cdc',
		'F3/accounting-limited' => 'e91e958f67887e1611d3fe8fcdfacff8',
		'F3/accounting-analyze' => '7606656fc3164bff437e7f1dbbd0e816',
		'F3/production-observe' => 'cd2fdbe0d0e8d17068a2b37c328632c4',
		'F3/commercialisation-analyze' => 'fae642b498f4f3d40701a43a911d03ff',
		'F3/none' => '5acc4b5d70899a007984a066bb21b202',
		'A1/decor.a1.referent@example.org' => '173fb4c143d87e9673ea6e97ae8e5998',
		'A1/admin' => '17b3a4dd624906682eda653dd86b9495',
		'A1/planning-full' => '173fb4c143d87e9673ea6e97ae8e5998',
		'A1/planning-limited' => '173fb4c143d87e9673ea6e97ae8e5998',
		'A1/selling' => 'a041ef84d33c8fa6fd3445531fb9e724',
		'A1/accounting-full' => 'f13f344d7b2152cb9bbcbd58cbf3a44e',
		'A1/accounting-limited' => 'f13f344d7b2152cb9bbcbd58cbf3a44e',
		'A1/accounting-analyze' => '173fb4c143d87e9673ea6e97ae8e5998',
		'A1/production-observe' => '8630322eb5b29da3a0f1b4a95a92995d',
		'A1/commercialisation-analyze' => '1b5c7f760c79f8de143898e746bbf595',
		'A1/none' => '173fb4c143d87e9673ea6e97ae8e5998',
		'B1/admin' => '119a8d422524d7771045d386bdea5db2',
		'B1/planning-full' => '65d969f14e49bcfdbfceb20b33368e96',
		'B1/planning-limited' => '65d969f14e49bcfdbfceb20b33368e96',
		'B1/selling' => 'f70c61d0feff3245689fa37938613c7b',
		'B1/accounting-full' => '9823dcf0db832ba838fc3d3abbfb6c4b',
		'B1/accounting-limited' => 'bc4619c4a7febdaa5e2319f3af973034',
		'B1/accounting-analyze' => '65d969f14e49bcfdbfceb20b33368e96',
		'B1/production-observe' => '9ac8327ee9d65e00e87624380abd7fa3',
		'B1/commercialisation-analyze' => '6b3be451b2381714137cfc178ec50dd9',
		'B1/none' => '65d969f14e49bcfdbfceb20b33368e96',
	];

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;

	private int $userFabricated = 930000;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();

	}

	public function finalize(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);
		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function testRenderIsUnchanged(): void {

		$eMemberTemplate = Member::model()
			->select(Member::getSelection())
			->whereStructure(FixtureAiLib::F1)
			->whereStatus(Member::IN)
			->sort(['id' => SORT_ASC])
			->get();

		if($this->assertDecorPresent($eMemberTemplate->notEmpty(), 'un membre de F1, dont les membres fabriqués reprennent les vues') === FALSE) {
			return;
		}

		$found = [];

		foreach(self::STRUCTURES as $structureId => $code) {

			foreach($this->getMembers($structureId) as $email => $eUser) {
				\user\ConnectionLib::setOnline($eUser);
				MemberLib::clearCache();
				$found[$code.'/'.$email] = md5($this->getRender($structureId));
			}

			foreach(self::ROLES as $name => $role) {
				$this->connectAs($eMemberTemplate, $structureId, $role);
				$found[$code.'/'.$name] = md5($this->getRender($structureId));
			}

		}

		$changed = array_keys(array_diff_assoc($found, self::FINGERPRINTS));
		$missing = array_keys(array_diff_key(self::FINGERPRINTS, $found));

		$this->assertEquals([], $changed, 'le menu ne rend plus la même chose pour '.implode(', ', array_map(fn(string $key) => $key.' ('.$found[$key].')', $changed)));
		$this->assertEquals([], $missing, 'profils de l\'empreinte qui ne sont plus rendus, à retirer de FINGERPRINTS s\'ils ont quitté le décor : '.implode(', ', $missing));

	}

	/**
	 * La structure est relue à chaque rendu : le menu y pose l'exercice qu'il affiche.
	 */
	public function getRender(int $structureId): string {

		$eStructure = $this->getStructure($structureId);

		$sections = ['home', 'production', 'commercialisation', 'accounting', 'settings'];

		$h = new MenuUi()->getMainTabs(
			$eStructure,
			NULL,
			NULL,
			array_combine($sections, $sections),
			array_fill_keys($sections, ''),
			'<div>home</div>'
		);

		foreach(self::NAVS as [$nav, $subNav]) {
			$h .= "\n".new MenuUi()->getMainTabs($this->getStructure($structureId), $nav, $subNav);
		}

		// La version d'une image change à chaque requête en dev : elle n'est pas le menu.
		return preg_replace('#(/static/[^"?]+)\?[0-9]+#', '$1', $h);

	}

	/**
	 * Sans migration : ce test ne fait que lire, et `connectDatabase()` migre par défaut la base qu'il
	 * ouvre. Une structure sans comptabilité n'a pas de base à ouvrir, et le menu n'en lit rien.
	 */
	private function getStructure(int $structureId): Structure {

		$eStructure = StructureLib::getById($structureId);

		if($eStructure->hasAccounting()) {
			StructureLib::connectDatabase($eStructure, migrate: FALSE);
		} else {
			Structure::setConnected($eStructure);
		}

		return $eStructure;

	}

	/**
	 * @return array [adresse => utilisateur]
	 */
	private function getMembers(int $structureId): array {

		$cMember = Member::model()
			->select([
				'user' => ['email']
			])
			->whereStructure($structureId)
			->whereStatus(Member::IN)
			->sort(['id' => SORT_ASC])
			->getCollection();

		$members = [];

		foreach($cMember as $eMember) {
			$members[$eMember['user']['email']] = $eMember['user'];
		}

		return $members;

	}

	private function connectAs(Member $eMemberTemplate, int $structureId, array $role): void {

		$eUser = new \user\User(['id' => $this->userFabricated++]);
		\user\ConnectionLib::setOnline($eUser);

		$eMember = clone $eMemberTemplate;
		$eMember['structure'] = new Structure(['id' => $structureId]);
		$eMember['role'] = new Role($role + [
			'admin' => FALSE,
			'planning' => Role::DENIED,
			'selling' => FALSE,
			'productionAnalyze' => FALSE,
			'productionObserve' => FALSE,
			'productionExport' => FALSE,
			'commercialisationAnalyze' => FALSE,
			'accounting' => Role::DENIED,
			'accountingAnalyze' => FALSE,
			'sections' => Role::ALL,
		]);

		MemberLib::clearCache();

		self::getMemberCache()->setValue(NULL, [
			$eUser['id'] => new \Collection([$structureId => $eMember]),
		]);

	}

	private static function getMemberCache(): \ReflectionProperty {
		return new \ReflectionProperty(MemberLib::class, 'cMemberOnline');
	}

}
?>
