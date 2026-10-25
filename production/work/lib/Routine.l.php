<?php
namespace work;

class RoutineLib extends ToolCrud {

	// Une dose de produit de traitement se donne par hectare, en poids ou en volume. Les autres unités
	// du catalogue (L/hL, g/m²…) se convertissent à la saisie.
	const PHYTO_UNITS = ['kg/ha', 'L/ha'];

	public static function list(): array {

		return [
			'fertilizer' => [ // Apports en éléments nutritifs pour les intrant
				'action' => ACTION_FERTILISATION,
				'standalone' => TRUE,
				'check' => fn(array $values) => self::checkFertilizer($values),
			],
			'tray' => [ // Dimensionnement des plaques de semis
				'action' => ACTION_SEMIS_PEPINIERE,
				'standalone' => FALSE,
				'check' => fn(array $values) => self::checkTray($values),
			],
			'phyto' => [ // Produit de traitement, avec sa dose et son volume de bouillie par défaut
				'action' => ACTION_TRAITEMENT,
				'standalone' => TRUE,
				'check' => fn(array $values) => self::checkPhyto($values),
			],
			'sprayer' => [ // Contenance de la cuve d'un pulvérisateur
				'action' => ACTION_TRAITEMENT,
				'standalone' => FALSE,
				'check' => fn(array $values) => self::checkSprayer($values),
			],
		];

	}

	public static function exists(string $name): bool {
		return array_key_exists($name, self::list());
	}

	public static function get(string $name): array {
		return self::list()[$name] ?? throw new \Exception('Unknown routine "'.$name.'"');
	}

	public static function getByStandalone(bool $standalone): array {
		return array_filter(self::list(), fn($properties) => ($properties['standalone'] === $standalone));
	}

	public static function getProducts(): array {
		return array_keys(self::getByStandalone(TRUE));
	}

	public static function getProductByAction(\work\Action $eAction): ?string {
		return array_key_first(self::getByAction($eAction, TRUE));
	}

	public static function getByAction(\work\Action $eAction, ?bool $standalone = NULL): array {

		if($eAction->empty()) {
			return [];
		}

		$eAction->expects(['fqn']);

		return array_filter(self::list(), fn($properties) => (
			$eAction['fqn'] === $properties['action'] and
			($standalone === NULL or $properties['standalone'] === $standalone)
		));

	}

	public static function checkFertilizer(array $values): array {

		$mask = ['float32', 'min' => 0.1, 'max' => NULL, 'null' => TRUE];

		$check = function($value) use($mask) {
			if($value === NULL) {
				return NULL;
			} else {
				return \Filter::check($mask, $value) ? (float)$value : NULL;
			}
		};

		return [
			'n' => $check($values['n'] ?? NULL),
			'p' => $check($values['p'] ?? NULL),
			'k' => $check($values['k'] ?? NULL),
			'mgo' => $check($values['mgo'] ?? NULL)
		];

	}

	/**
	 * Ce que le catalogue dit du produit (fonctions, formulation, mention AB) se relit depuis l'AMM
	 * et ne se poste pas. Un produit sans AMM est une préparation absente du catalogue, saisie à la
	 * main. La dose est par hectare, en kg ou en L selon la formulation. Pas de bouillie pour un
	 * produit prêt à l'emploi.
	 */
	public static function checkPhyto(array $values): array {

		$amm = trim($values['amm'] ?? '');
		$ePhytoProduct = ($amm === '') ? new PhytoProduct() : PhytoProductLib::getByAmm($amm);

		if($amm !== '' and $ePhytoProduct->empty()) {
			\Fail::log('work\Tool::routineValue.phytoAmm');
			return [];
		}

		$dose = $values['dose'] ?? NULL;

		if(\Filter::check(['float32', 'min' => 0.001, 'max' => NULL], $dose) === FALSE) {
			\Fail::log('work\Tool::routineValue.phytoDose');
			return [];
		}

		$unit = $values['unit'] ?? NULL;

		if(in_array($unit, self::PHYTO_UNITS, TRUE) === FALSE) {
			\Fail::log('work\Tool::routineValue.phytoDose');
			return [];
		}

		$readyToUse = $ePhytoProduct->notEmpty() ? $ePhytoProduct->isReadyToUse() : FALSE;
		$water = $values['water'] ?? '';

		if($readyToUse or $water === '') {
			$water = NULL;
		} else if(\Filter::check(['int', 'min' => 1, 'max' => NULL], $water)) {
			$water = (int)$water;
		} else {
			\Fail::log('work\Tool::routineValue.phytoWater');
			return [];
		}

		return [
			'amm' => $ePhytoProduct->empty() ? NULL : $ePhytoProduct['amm'],
			'dose' => (float)$dose,
			'unit' => $unit,
			'water' => $water,
			'functions' => $ePhytoProduct->empty() ? NULL : $ePhytoProduct['functions'],
			'formulation' => $ePhytoProduct->empty() ? NULL : $ePhytoProduct['formulation'],
			'organic' => $ePhytoProduct->empty() ? FALSE : $ePhytoProduct['organic'],
			'readyToUse' => $readyToUse,
		];

	}

	/**
	 * La dose et la bouillie d'une intervention de traitement. NULL quand aucune n'est saisie : à la
	 * création, l'intervention prend alors celles de son produit (TaskLib::prepareTreatment()).
	 */
	public static function checkTreatment(array $values): ?array {

		$dose = $values['dose'] ?? '';
		$unit = $values['unit'] ?? '';
		$water = $values['water'] ?? '';

		if($dose === '') {
			$dose = NULL;
		} else if(\Filter::check(['float32', 'min' => 0.001, 'max' => NULL], $dose)) {
			$dose = (float)$dose;
		} else {
			\Fail::log('series\Task::treatment.dose');
			return NULL;
		}

		// L'unité ne vaut qu'avec la dose qu'elle mesure.
		if($dose === NULL or $unit === '') {
			$unit = NULL;
		} else if(in_array($unit, self::PHYTO_UNITS, TRUE) === FALSE) {
			\Fail::log('series\Task::treatment.dose');
			return NULL;
		}

		if($water === '') {
			$water = NULL;
		} else if(\Filter::check(['int', 'min' => 1, 'max' => NULL], $water)) {
			$water = (int)$water;
		} else {
			\Fail::log('series\Task::treatment.water');
			return NULL;
		}

		if($dose === NULL and $water === NULL) {
			return NULL;
		}

		return [
			'dose' => $dose,
			'unit' => $unit,
			'water' => $water,
		];

	}

	/**
	 * Des cuves pleines, puis une dernière qui prend le reste. Un reste sous 50 mL ne fait pas une
	 * cuve de plus : c'est l'arrondi de la surface, pas une bouillie à préparer.
	 */
	public static function getTanks(float $water, float $capacity): array {

		$tanks = [];

		for($left = $water; $left > 0.05; $left -= $capacity) {
			$tanks[] = min($capacity, $left);
		}

		return $tanks;

	}

	public static function checkSprayer(array $values): array {

		$value = $values['capacity'] ?? NULL;

		if(\Filter::check(['float32', 'min' => 0.1, 'max' => NULL], $value)) {
			return [
				'capacity' => (float)$value,
			];
		} else {
			\Fail::log('work\Tool::routineValue.sprayer');
			return [];
		}

	}

	public static function checkTray(array $values): array {

		$mask = ['int', 'min' => 1, 'max' => NULL, 'null' => TRUE];
		$value = $values['value'] ?? NULL;

		if($value and \Filter::check($mask, $value)) {

			return [
				'value' => (int)$value,
			];

		} else {
			\Fail::log('work\Tool::routineValue.tray');
			return [];
		}

	}

}
?>
