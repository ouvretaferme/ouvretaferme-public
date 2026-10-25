<?php
namespace work;

class Tool extends ToolElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'action' => ['fqn', 'name'],
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public function isStandaloneRoutine(): bool {

		$this->expects(['routineName']);

		if($this['routineName'] !== NULL) {
			return RoutineLib::get($this['routineName'])['standalone'];
		} else {
			return FALSE;
		}

	}

	/**
	 * Un matériel sans routine n'est jamais un produit, alors que `getProductByAction()` rend aussi
	 * NULL pour une action sans produit.
	 */
	public function isProductFor(Action $eAction): bool {

		$this->expects(['routineName']);

		return (
			$this['routineName'] !== NULL and
			RoutineLib::getProductByAction($eAction) === $this['routineName']
		);

	}

	/**
	 * Range ce matériel à la place de `$eToolFrom` dans une intervention, une répétition ou un
	 * itinéraire : en produit s'il en est un pour l'action, en matériel sinon. L'écriture reste au
	 * Lib de l'élément.
	 */
	public function applyMove(\Element $e, Tool $eToolFrom): void {

		$this->expects(['id', 'routineName']);
		$e->expects(['tools', 'products', 'action' => ['fqn']]);

		$e['tools'] = array_values(array_diff($e['tools'], [$eToolFrom['id']]));
		$e['products'] = array_values(array_diff($e['products'], [$eToolFrom['id']]));

		if($this->isProductFor($e['action'])) {
			$e['products'] = array_values(array_unique([...$e['products'], $this['id']]));
		} else {
			$e['tools'] = array_values(array_unique([...$e['tools'], $this['id']]));
		}

	}

	/**
	 * Le produit que ce matériel peut devenir : celui dont l'action est la sienne (un matériel de
	 * traitement devient un produit de traitement, un matériel de fertilisation un intrant).
	 */
	public function getProductRoutine(): ?string {

		$this->expects(['routineName', 'action']);

		if($this['routineName'] !== NULL or $this['action']->empty()) {
			return NULL;
		}

		return RoutineLib::getProductByAction($this['action']);

	}

	public function getStandaloneRoutine(string $key): mixed {

		$this->expects(['routineName']);

		if($this->isStandaloneRoutine()) {
			return $this->getRoutine($key);
		} else {
			return NULL;
		}

	}

	public function getRoutine(string $key): mixed {

		$this->expects(['routineName']);

		return RoutineUi::get($this['routineName'])[$key] ?? throw new \Exception('Invalid key "'.$key.'"');

	}

	public function getActionFromRoutine(\structure\Structure $eStructure): mixed {

		$this->expects(['routineName']);

		if($this['routineName'] === NULL) {
			return new Action();
		} else {
			return Action::model()
				->select(Action::getSelection())
				->whereStructure($eStructure)
				->whereFqn(RoutineLib::get($this['routineName'])['action'])
				->get();
		}

	}

	public static function properties(\PropertyList $l): void {

		$l->property('action')
			->replaceCheck(function(\work\Action $eAction): bool {
				$this->expects(['structure']);
				return (
					$eAction->empty() or
					\work\ActionLib::canUse($eAction, $this['structure'])
				);
			});

		$l->property('stock')
			->addAfterCheck('followed', function(?int $stock): bool {
				return (
					$this->exists() === FALSE or
					\toolbox\EquipmentLib::isFollowed($this) === FALSE
				);
			});

		// Le compteur d'un matériel suivi au carnet se règle ici, à la place du stock, avec son
		// immatriculation. Ce ne sont pas des colonnes du matériel mais de son suivi (`toolbox\Equipment`),
		// construit ici pour que son refus arrête l'enregistrement avant toute écriture, puis écrit par
		// `ToolLib::update()`.
		$l->property('meter')
			->replaceCheck(function(mixed $meter, \PropertyList $l): bool {

				$this['eEquipment'] = \toolbox\EquipmentLib::getByTool($this);
				$this['eEquipment']->validate('canUpdate', 'acceptUpdate');
				$this['eEquipment']->build(['meter', 'registration'], $l->input);

				return TRUE;

			});

		$l->property('routineName')
			->addAfterCheck('routine', function(?string $routineName): bool {

				if($routineName === NULL) {
					return TRUE;
				}

				if($this['action']->empty()) {
					return FALSE;
				}

				$this->expects([
					'action' => ['fqn']
				]);

				return (
					RoutineLib::exists($routineName) and
					$this['action']['fqn'] === RoutineLib::get($routineName)['action']
				);

			});

		$l->property('routineValue')
			->addAfterCast('routine', function(?array &$routineValue): bool {

				$this->expects([
					'routineName'
				]);

				if($this['routineName'] === NULL) {
					$routineValue = NULL;
					return TRUE;
				}

				if($routineValue === NULL) {
					return FALSE;
				}

				$fw = new \FailWatch();

				$routineValue = RoutineLib::get($this['routineName'])['check']($routineValue);

				return $fw->ok();

			});

	}

}
?>