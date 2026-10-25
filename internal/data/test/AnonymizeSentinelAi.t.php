<?php
namespace data;

/**
 * Test-sentinelle : chaque colonne qui peut porter une donnée personnelle ou un secret a sa règle
 * d'anonymisation (`AnonymizeAiSetting`, et le `Data.o.php` de chaque package fermé).
 *
 * La copie anonymisée part chez une développeuse qui n'a le droit de lire en clair que la structure
 * 7. Une colonne ajoutée demain sans règle partirait en clair, sans erreur et sans que personne le
 * voie : c'est ce test qui l'apprend à celui qui l'ajoute, au moment où il l'ajoute. Le script
 * d'export refuse de son côté toute colonne réelle sans règle, mais il ne tourne que sur le poste qui exporte,
 * longtemps après.
 *
 * Il compare dans les deux sens : la colonne sans règle, et la règle dont la colonne a disparu. Une
 * règle peut nommer une colonne d'un type qui ne se déclare pas (une date de naissance) : elle
 * s'applique, mais son absence ne serait pas vue.
 *
 * Lancement : dev/test package=data file=AnonymizeSentinelAi
 */
class AnonymizeSentinelAiTest extends \Test {

	const RULES = [
		AnonymizeAiSetting::KEEP,
		AnonymizeAiSetting::FAKE,
		AnonymizeAiSetting::FAKE_SLUG,
		AnonymizeAiSetting::FAKE_EMAIL,
		AnonymizeAiSetting::FAKE_PHONE,
		AnonymizeAiSetting::CLEAR,
		AnonymizeAiSetting::SECRET,
		AnonymizeAiSetting::ACCOUNT,
		AnonymizeAiSetting::REFERENCE,
	];

	const TRUNCATES = [
		AnonymizeAiSetting::TRUNCATE_ALL,
		AnonymizeAiSetting::TRUNCATE_EXCEPT_7,
	];

	/**
	 * @return array<string, \ModuleModel>
	 */
	private function getModels(): array {
		return AnonymizeAiLib::getModels(FALSE) + AnonymizeAiLib::getModels(TRUE);
	}

	public function testEveryColumnHasRule(): void {

		$rules = AnonymizeAiLib::getRules();
		$modulesTruncate = AnonymizeAiLib::getModulesTruncate();
		$undeclared = [];

		foreach($this->getModels() as $class => $model) {

			if(array_key_exists($class, $modulesTruncate)) {
				continue;
			}

			foreach(AnonymizeAiLib::getPropertiesDeclared($model) as $property) {

				if(array_key_exists($property, $rules[$class] ?? []) === FALSE) {
					$undeclared[] = $class.'::'.$property.' ('.$model->getPropertyType($property).')';
				}

			}

		}

		$this->assertEquals([], $undeclared, count($undeclared).' colonne(s) sans règle d\'anonymisation, à déclarer dans AnonymizeAiSetting::RULES, ou dans le Data.o.php du package fermé : '.implode(', ', $undeclared));

	}

	public function testEveryRuleHasColumn(): void {

		$models = $this->getModels();
		$rulesAll = AnonymizeAiLib::getRules();
		$stale = [];

		foreach($rulesAll as $class => $rules) {

			if(array_key_exists($class, $models) === FALSE) {
				$stale[] = $class.' (module disparu)';
				continue;
			}

			$properties = $models[$class]->getProperties();

			foreach(array_keys($rules) as $property) {

				if(in_array($property, $properties) === FALSE) {
					$stale[] = $class.'::'.$property;
				}

			}

		}

		foreach(array_keys(AnonymizeAiLib::getModulesTruncate()) as $class) {

			if(array_key_exists($class, $models) === FALSE) {
				$stale[] = $class.' (module vidé disparu)';
			}

			if(array_key_exists($class, $rulesAll)) {
				$stale[] = $class.' (vidé et pourtant déclaré colonne par colonne)';
			}

		}

		[$referenceClass, $referenceProperty] = AnonymizeAiSetting::REFERENCE_SOURCE;

		if(
			array_key_exists($referenceClass, $models) === FALSE or
			in_array($referenceProperty, $models[$referenceClass]->getProperties()) === FALSE
		) {
			$stale[] = $referenceClass.'::'.$referenceProperty.' (référence de REFERENCE disparue)';
		}

		$this->assertEquals([], $stale, 'Règles périmées, la colonne a disparu : '.implode(', ', $stale));

	}

	public function testRulesAreWellFormed(): void {

		$malformed = [];

		foreach(AnonymizeAiLib::getRules() as $class => $rules) {

			foreach($rules as $property => $rule) {

				$name = $class.'::'.$property;

				if(in_array($rule[0] ?? NULL, self::RULES, TRUE) === FALSE) {
					$malformed[] = $name.' (règle inconnue)';
				} else if(trim($rule[1] ?? '') === '') {
					$malformed[] = $name.' (raison absente)';
				} else if(in_array($rule[0], AnonymizeAiSetting::RULES_LABELLED) and trim($rule[2] ?? '') === '') {
					$malformed[] = $name.' ('.$rule[0].' sans libellé)';
				} else if(in_array($rule[0], AnonymizeAiSetting::RULES_LABELLED) === FALSE and count($rule) !== 2) {
					$malformed[] = $name.' ('.$rule[0].' ne prend pas de libellé)';
				}

			}

		}

		foreach(AnonymizeAiLib::getModulesTruncate() as $class => $truncate) {

			if(in_array($truncate[0] ?? NULL, self::TRUNCATES, TRUE) === FALSE) {
				$malformed[] = $class.' (portée de vidage inconnue)';
			} else if(trim($truncate[1] ?? '') === '') {
				$malformed[] = $class.' (vidé sans raison)';
			}

		}

		$this->assertEquals([], $malformed, 'Règles mal formées : '.implode(', ', $malformed));

	}

	/**
	 * Les lignes des administrateurs se rattachent par une propriété qui existe, et un secret gardé
	 * pour eux est bien déclaré secret : sinon il n'y aurait rien à garder, ou la colonne aurait
	 * disparu sans que la déclaration le dise.
	 */
	public function testAdminRowsAreDeclared(): void {

		$models = $this->getModels();
		$rules = AnonymizeAiLib::getRules();
		$wrong = [];

		foreach(AnonymizeAiSetting::ADMIN_ROWS as $class => $admin) {

			if(array_key_exists($class, $models) === FALSE) {
				$wrong[] = $class.' (module disparu)';
			} else if(in_array($admin[0] ?? NULL, $models[$class]->getProperties(), TRUE) === FALSE) {
				$wrong[] = $class.'::'.($admin[0] ?? '?').' (propriété de rattachement disparue)';
			} else if(trim($admin[1] ?? '') === '') {
				$wrong[] = $class.' (raison absente)';
			}

		}

		foreach(AnonymizeAiSetting::ADMIN_SECRETS_KEPT as $class => $properties) {

			if(array_key_exists($class, AnonymizeAiSetting::ADMIN_ROWS) === FALSE) {
				$wrong[] = $class.' (secret gardé pour des lignes qui ne sont pas déclarées)';
				continue;
			}

			foreach($properties as $property) {

				if(($rules[$class][$property][0] ?? NULL) !== AnonymizeAiSetting::SECRET) {
					$wrong[] = $class.'::'.$property.' (gardé pour les administrateurs sans être déclaré SECRET)';
				}

			}

		}

		$this->assertEquals([], $wrong, 'Comptes administrateurs mal déclarés : '.implode(', ', $wrong));

	}

	/**
	 * Un module qui garde les lignes de la 7 sait les borner : il vit dans une base `structure_<id>`,
	 * ou porte une colonne `structure`.
	 */
	public function testExcept7CanBeBounded(): void {

		$modelsStructure = AnonymizeAiLib::getModels(TRUE);
		$models = $this->getModels();
		$unbounded = [];

		foreach(AnonymizeAiLib::getModulesTruncate() as $class => [$truncate]) {

			if(
				$truncate === AnonymizeAiSetting::TRUNCATE_EXCEPT_7 and
				array_key_exists($class, $models) and
				array_key_exists($class, $modelsStructure) === FALSE and
				in_array('structure', $models[$class]->getProperties()) === FALSE
			) {
				$unbounded[] = $class;
			}

		}

		$this->assertEquals([], $unbounded, 'Modules qui gardent la 7 sans pouvoir la borner (ni base structure_<id>, ni colonne structure) : '.implode(', ', $unbounded));

	}

}
?>
