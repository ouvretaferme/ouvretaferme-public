<?php
namespace toolbox;

class CompositionSpecies extends CompositionSpeciesElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'plant' => ['fqn', 'name', 'vignette', 'organ'],
		];

	}

	public function canRead(): bool {

		$this->expects(['plan']);

		return $this['plan']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['plan']);

		return $this['plan']->canWrite();

	}

	public function acceptWrite(): bool {

		$this->expects(['plan']);

		return $this['plan']->acceptWrite();

	}

	/**
	 * Le nom sous lequel l'espèce se montre (r76) : celui de l'espèce du référentiel qu'elle lit,
	 * suivi du nom de la variante (« Carotte · Kg », r76 lot 2, sous mandat le 04/10/2026), que la
	 * grille, l'onglet « Espèces » et le panneau disent pareil. Le nom seul ne dirait pas de quelle
	 * espèce la ligne est une variante, une fois la grille triée ou filtrée.
	 */
	public function getLabel(): string {

		$this->expects(['name', 'plant' => ['name']]);

		return ($this['name'] === NULL) ? $this['plant']['name'] : $this['plant']['name'].' · '.$this['name'];

	}

	/**
	 * La communauté sait-elle compter l'espèce, c'est-à-dire lui donne-t-elle une unité et une
	 * quantité au panier (`CompositionLib::getSource()`, `units`) ? Une espèce qu'elle ne compte pas
	 * (r48c, « Ajouter une espèce ») tient les deux de la structure, avec son prix : son unité se
	 * choisit, et sa quantité au panier se tape (`quantityBasket`).
	 */
	public function isCommunity(): bool {

		$this->expects(['plant']);

		return array_key_exists($this['plant']['id'], CompositionLib::getSource()['units']);

	}

	public static function properties(\PropertyList $l): void {

		// Une saison réglée à la main, écrite depuis le plan de culture ou depuis le calendrier type
		// (r48b) ne se déclare qu'avec sa saison : construite dans le même `build()`, c'est le contrôle de `windowWeeks` qui en répond ;
		// sinon, l'espèce doit déjà en porter une. `NATURAL` se poste seul au départ
		// (`composition:doStart`), et c'est le Lib qui lui écrit alors sa saison.
		// Un plan porte une espèce nue et des variantes d'une même espèce, chacune sous son nom (r76) :
		// la paire (espèce, nom) y est unique. Le schéma le tient par `UNIQUE (plan, plant, name)`,
		// sauf pour l'espèce nue, dont le nom est NULL : MySQL ne compare pas deux NULL, et deux
		// espèces nues de la même espèce passeraient l'index. Le nom se compare dans la collation de
		// la colonne, comme l'index le ferait. L'élément porte son nom avant que l'espèce se
		// construise, posé par le Lib ou construit plus tôt dans le même `build()`.
		$l->property('plant')
			->addAfterCheck('duplicate', function(\plant\Plant $ePlant): bool {

				$this->expects(['plan', 'name']);

				return CompositionSpecies::model()
					->wherePlan($this['plan'])
					->wherePlant($ePlant)
					->whereName($this['name'])
					->whereId('!=', $this, if: $this->exists())
					->exists() === FALSE;

			});

		// Le nom d'une variante (r76 lot 2) : seule une variante le construit, à l'ajout comme dans son
		// panneau, et elle en a toujours un, sans quoi elle se confondrait avec son espèce nue. Deux
		// variantes de la même espèce ne portent pas le même nom : le refus se dit sur le champ du nom,
		// avant celui de `plant.duplicate`, qui tient la même paire pour tout autre chemin.
		$l->property('name')
			->addAfterCheck('required', fn(?string $name): bool => ($name !== NULL))
			->addAfterCheck('duplicate', function(?string $name): bool {

				$this->expects(['plan', 'plant']);

				return CompositionSpecies::model()
					->wherePlan($this['plan'])
					->wherePlant($this['plant'])
					->whereName($name)
					->whereId('!=', $this, if: $this->exists())
					->exists() === FALSE;

			});

		// Une variante a toujours une fréquence (r76 lot 2, sous mandat le 04/10/2026) : la part de
		// fréquence que la source tire de la communauté va à l'espèce, jamais à ses variantes
		// (`CompositionSpeciesLib::getVariant()`), donc une variante « selon la saison » n'entrerait
		// dans les paniers que par le bruit du tirage.
		$l->property('frequency')
			->addAfterCheck('seasonal', function(?string $frequency): bool {

				$this->expects(['name']);

				return ($frequency !== NULL or $this['name'] === NULL);

			});

		$l->property('status')
			->addAfterCheck('window', function(string $status, \PropertyList $l): bool {

				if(
					in_array($status, [CompositionSpecies::CUSTOM, CompositionSpecies::CULTIVATION, CompositionSpecies::CALENDAR], TRUE) === FALSE or
					$l->hasProperty('windowWeeks')
				) {
					return TRUE;
				}

				$this->expects(['windowWeeks']);

				return ($this['windowWeeks'] !== NULL);

			});

		// La saison (lot r27) : les poignées de la grille postent des intervalles qui se chevauchent ou
		// se touchent le temps d'un geste, et un formulaire poste des chaînes. Les deux se normalisent
		// avant le contrôle, qui juge donc la forme écrite (`CompositionLib::checkWindowWeeks()`).
		//
		// L'état vit dans l'enum, la donnée dans le json, et le contrôle est un match exhaustif : une
		// espèce retirée n'a pas de saison (`NULL`), et toute autre en a une. Seule une saison réglée à
		// la main peut être vide (`[]`, r30, #93) : la personne en a retiré le dernier intervalle sur la
		// grille, l'espèce reste cultivée et n'entre plus dans aucun panier. Les deux absences ne se
		// rencontrent donc jamais sous un même état, et on ne remet pas au panier ce que quelqu'un a
		// retiré : `NULL` sur une espèce cultivée est une saison jamais écrite, que
		// `CompositionSpeciesLib::applySeasonsUnwritten()` remplit, là où `[]` est une saison écrite
		// vide, qui se garde. Le contrôle remplace celui du schéma : il traite les deux formes de
		// l'absence, que la nullabilité seule laisse passer.
		$l->property('windowWeeks')
			// Seuls les chiffres se convertissent : « 12a » reste une chaîne, et le contrôle la refuse.
			->addAfterCast('integers', function(?array &$windowWeeks): bool {

				foreach($windowWeeks ?? [] as $key => $window) {

					if(is_array($window) === FALSE) {
						continue;
					}

					foreach($window as $position => $week) {

						if(is_string($week) and ctype_digit($week)) {
							$windowWeeks[$key][$position] = (int)$week;
						}

					}

				}

				return TRUE;

			})
			->addAfterCast('merge', function(?array &$windowWeeks): bool {

				if($windowWeeks !== NULL) {
					$windowWeeks = CompositionLib::mergeWindowWeeks($windowWeeks);
				}

				return TRUE;

			})
			->replaceCheck(function(?array $windowWeeks): bool {

				$this->expects(['status']);

				return match($this['status']) {
					CompositionSpecies::EXCLUDED => $windowWeeks === NULL,
					CompositionSpecies::NATURAL, CompositionSpecies::CULTIVATION, CompositionSpecies::CALENDAR => CompositionLib::checkWindowWeeks($windowWeeks),
					CompositionSpecies::CUSTOM => ($windowWeeks === [] or CompositionLib::checkWindowWeeks($windowWeeks)),
				};

			})
			// Une saison réglée à la main n'enregistre aucun morceau de moins de trois semaines, le seuil
			// des saisons du calendrier type (r72 : « vire les semaines isolées ») : un clic
			// relâché sur la grille posait une semaine isolée, que rien ne distinguait ensuite d'une
			// saison voulue (Fève [[4,4],[18,26]] sur la 7).
			//
			// Un morceau que l'espèce porte déjà se garde, pour que ces saisons se nettoient geste par
			// geste et que « Régler une espèce » enregistre ses autres réglages sans y toucher. L'étape
			// le lit sur `$this` : `build()` écrit chaque propriété à son étape `set`, après les
			// contrôles de cette propriété, donc `windowWeeks` y est encore la saison enregistrée, alors
			// que `status`, construit avant elle, y est déjà la valeur postée.
			// Le merge a déjà réuni les intervalles qui se touchent : un morceau se mesure entier, et avec
			// celui qui le prolonge de l'autre côté du nouvel an (`CompositionLib::isWindowShort()`).
			->addAfterCheck('short', function(?array $windowWeeks): bool {

				$this->expects(['status']);

				if($this['status'] !== CompositionSpecies::CUSTOM) {
					return TRUE;
				}

				// Une espèce en création n'a rien d'enregistré, une espèce retirée une saison NULL.
				if($this->exists() === FALSE) {
					$windowWeeksStored = [];
				} else {
					$this->expects(['windowWeeks']);
					$windowWeeksStored = ($this['windowWeeks'] === NULL) ? [] : $this['windowWeeks'];
				}

				foreach($windowWeeks as $window) {

					if(
						CompositionLib::isWindowShort($window, $windowWeeks) and
						in_array($window, $windowWeeksStored, TRUE) === FALSE
					) {
						return FALSE;
					}

				}

				return TRUE;

			});

		// Une structure choisit l'unité d'une espèce parmi celles que le poids sait convertir (lot r14,
		// `CompositionLib::readSource()`, `offered`). L'unité de la communauté ne s'écrit pas : c'est
		// l'absence de choix, NULL, qui la suit, y compris le jour où le relevé de la nuit en change.
		// Une espèce que la communauté ne compte pas (r48c) n'a pas d'unité à suivre : la sienne se
		// choisit toujours.
		//
		// Une variante choisit son unité à l'ajout (r76 lot 2, le 04/10/2026, « l'unité reste
		// à choisir ») : aucune n'est cochée au départ, et l'unité de la communauté poste une chaîne
		// vide. Rien de coché ne poste rien, et le cast ferait des deux le même élément vide : le refus
		// se lit donc sur la valeur brute, que la page désigne par l'origine `variant`.
		$l->property('unit')
			->addBeforeCast('chosen', fn(mixed $unit, \PropertyList $l): bool => ($unit !== NULL or $l->origin !== 'variant'))
			->addAfterCheck('offered', function(\product\Unit $eUnit): bool {

				if($eUnit->empty()) {
					return $this->isCommunity();
				}

				$source = CompositionLib::getSource();

				if(array_key_exists($eUnit['id'], $source['offered']) === FALSE) {
					return FALSE;
				}

				return (
					$this->isCommunity() === FALSE or
					$eUnit['id'] !== $source['units'][$this['plant']['id']]['unit']
				);

			});

		// La quantité au panier d'une espèce que la communauté ne compte pas (r48c) : sans elle,
		// l'espèce n'entre dans aucun panier (`CompositionLib::applyUnitsTyped()`). Elle se dit dans
		// l'unité que le même `build()` vient de poser ou que l'espèce porte déjà, en pas entiers du pas
		// réglé sur l'espèce, celui que le même `build()` vient de poser ou que l'espèce porte, sur
		// lequel la proposition l'arrondit (`CompositionLib::getStep()`), à défaut du pas de son unité
		// (r68) : contre le seul pas de l'unité, 0,75 kg était refusé sous un pas réglé à 0,25 kg. Le
		// pas se construit donc avant elle (`CompositionSpeciesLib::getPropertiesPanel()`). Une espèce
		// de la communauté n'en construit pas : la sienne suit les paniers partagés.
		$l->property('quantityBasket')
			->addAfterCheck('required', function(?float $quantityBasket): bool {
				return ($quantityBasket !== NULL or $this->isCommunity());
			})
			->addAfterCheck('multiple', function(?float $quantityBasket): bool {
				$this->expects(['unit', 'step']);

				// Sans unité, le contrôle de `unit` a déjà refusé l'envoi, et il n'y a pas de pas à lire.
				return (
					$quantityBasket === NULL or
					$this['unit']->empty() or
					CompositionLib::isStepMultiple($quantityBasket, $this['step'] ?? $this->getUnitSource()['step'])
				);
			});

		// Aucun relevé de la communauté ne chiffre une espèce qu'elle ne compte pas (r48c) dans l'unité
		// que la structure lui choisit : son prix se tape, et c'est lui qui la rend candidate
		// (`CompositionLib::getOrder()`, `priced`).
		$l->property('price')
			->addAfterCheck('required', function(?float $price): bool {
				return ($price !== NULL or $this->isCommunity());
			});

		// Une unité entière, la botte ou la pièce, ne se coupe pas : son pas est un nombre entier.
		$l->property('step')
			->addAfterCheck('whole', function(?float $step): bool {

				if($step === NULL) {
					return TRUE;
				}

				$this->expects(['plant']);

				return (
					$this->getUnitSource()['step'] !== 1.0 or
					CompositionLib::isStepMultiple($step, 1.0)
				);

			})
			// Le pas peut se construire sans le minimum (planche r1) : le minimum que l'espèce porte
			// déjà doit rester un nombre entier de ce pas, que le contrôle de `minimum` ne vérifie que
			// lorsqu'il est construit avec lui. Sans pas réglé, le pas est celui de l'unité, qui
			// divise tout minimum accepté par ce même contrôle.
			->addAfterCheck('minimum', function(?float $step, \PropertyList $l): bool {

				if($step === NULL or $l->hasProperty('minimum')) {
					return TRUE;
				}

				$this->expects(['minimum']);

				return (
					$this['minimum'] === NULL or
					CompositionLib::isStepMultiple($this['minimum'], $step)
				);

			});

		// Un minimum se compte en pas, celui que le même `build()` vient de poser ou celui que l'espèce
		// porte déjà, à défaut le pas par défaut de son unité dans le plan (`getStepDefault()`) : la
		// quantité proposée s'arrondit au pas, et un minimum entre deux pas ne serait jamais servi tel
		// quel.
		$l->property('minimum')
			->addAfterCheck('multiple', function(?float $minimum): bool {

				if($minimum === NULL) {
					return TRUE;
				}

				$this->expects(['step']);

				return CompositionLib::isStepMultiple($minimum, $this['step'] ?? $this->getStepDefault());

			});

		// La quantité par défaut d'un ajout à la main (r30, #92) tient les bornes d'une ligne ajoutée
		// (`CompositionLine::properties()`) : jamais sous le minimum de l'espèce, et en pas entiers. Le
		// minimum et le pas sont ceux que le même `build()` vient de poser ou que l'espèce porte déjà,
		// à défaut ceux de son unité (`getStepEffective()`).
		$l->property('quantity')
			->addAfterCheck('minimum', function(?float $quantity): bool {
				return ($quantity === NULL or $quantity >= $this->getStepEffective()['minimum']);
			})
			->addAfterCheck('multiple', function(?float $quantity): bool {
				return ($quantity === NULL or CompositionLib::isStepMultiple($quantity, $this->getStepEffective()['step']));
			});

		// Un poids ne se dit que d'une unité du panier qui n'est pas déjà un poids, la pièce ou la
		// botte (lot L3) : sur une espèce servie au kilo, il ne servirait jamais et resterait en base.
		$l->property('weight')
			->addAfterCheck('weighed', function(?float $weight): bool {

				if($weight === NULL) {
					return TRUE;
				}

				return array_key_exists($this->getUnitSource()['fqn'], CompositionLib::WEIGHED);

			})
			// Passer à la pièce ou à la botte convertit la quantité et les prix par le poids (lot r14,
			// `CompositionLib::applyUnits()`) : le changement d'unité exige un poids quand ni les cultures
			// de la saison ni le poids habituel n'en disent un. Seul le geste qui choisit l'unité le
			// demande ; un poids effacé plus tard sur une unité déjà choisie laisse l'espèce sans
			// quantité, hors des paniers, comme une espèce sans prix. Une espèce que la communauté ne
			// compte pas (r48c) n'a rien à convertir, sa quantité et son prix étant tapés dans son unité.
			->addAfterCheck('missing', function(?float $weight, \PropertyList $l): bool {

				if($weight !== NULL or $l->isBuilt('unit') === FALSE or $this->isCommunity() === FALSE) {
					return TRUE;
				}

				$this->expects(['plant', 'unit']);

				$unitFqn = $this->getUnitSource()['fqn'];

				if(array_key_exists($unitFqn, CompositionLib::WEIGHED) === FALSE) {
					return TRUE;
				}

				$this->expects(['plan' => ['structure', 'season']]);

				$plantId = $this['plant']['id'];

				return (
					(CompositionLib::getCultivatedWeights($this['plan']['structure'], $this['plan']['season'])[$plantId][$unitFqn] ?? NULL) !== NULL or
					(CompositionLib::getSource()['weights'][$plantId][$unitFqn] ?? NULL) !== NULL
				);

			});

		// Le produit retenu pour la vente (r37b) : un des produits que la page « Mettre en vente » propose
		// pour la ligne, de la même espèce et de la même unité qu'elle, en vente, de la structure
		// (`CompositionSaleLib::applyLines()`, qui pose `cProductCandidate` avant le `build()`). Le
		// schéma vérifie seulement que le produit existe, et un POST forgé peut en désigner un autre.
		// Vide, c'est une espèce sans produit vendu (« Régler une espèce », ou une ligne de la page sans
		// candidat, qui ne poste rien), que rien n'empêche.
		$l->property('product')
			->addAfterCheck('candidate', function(\product\Product $eProduct): bool {

				if($eProduct->empty()) {
					return TRUE;
				}

				$this->expects(['cProductCandidate']);

				return $this['cProductCandidate']->offsetExists($eProduct['id']);

			});

	}

	/**
	 * Les semaines de la saison de l'espèce (lot r27), dans l'ordre, aucune pour une espèce retirée.
	 */
	public function getSeasonWeeks(): array {

		$this->expects(['status', 'windowWeeks']);

		return match($this['status']) {
			CompositionSpecies::EXCLUDED => [],
			CompositionSpecies::NATURAL, CompositionSpecies::CULTIVATION, CompositionSpecies::CALENDAR, CompositionSpecies::CUSTOM => CompositionLib::getWindowWeeksList($this['windowWeeks']),
		};

	}

	/**
	 * L'unité où l'espèce entre au panier, `['fqn' => string, 'step' => float]` : celle que la
	 * structure a choisie (lot r14), à défaut celle de la communauté. Le pas, le minimum et le poids
	 * se disent dans cette unité, et se contrôlent donc contre elle.
	 */
	/**
	 * Le pas que l'espèce prend sans pas réglé, dans l'unité où elle entre au panier : au kilo,
	 * 0,2 ou 0,1 selon son prix, que seule la source du plan connaît (lot r18,
	 * `CompositionLib::applyKiloSteps()`) ; dans une autre unité, le pas de l'unité. Une unité que le
	 * même `build()` vient de choisir n'est pas encore celle de la source du plan : le kilo s'y lit
	 * alors au pas de `CompositionLib::KILO_STEP`, celui d'une espèce dont le prix n'est pas connu.
	 */
	private function getStepDefault(): float {

		$unit = $this->getUnitSource();

		if($unit['fqn'] !== 'kg') {
			return $unit['step'];
		}

		$this->expects(['plan' => ['structure', 'season', 'reference', 'weeks']]);

		// Une espèce que « Ajouter une espèce » construit (r48c) n'est pas encore dans le plan écrit,
		// donc pas dans sa source : son prix n'y est pas connu non plus.
		if($this->exists() === FALSE) {
			return CompositionLib::KILO_STEP;
		}

		$unitsPlan = CompositionLib::getSourceByPlan($this['plan'])['units'];
		$speciesId = $this['id'];

		if(array_key_exists($speciesId, $unitsPlan) === FALSE) {
			return CompositionLib::KILO_STEP;
		}

		return ($unitsPlan[$speciesId]['fqn'] === 'kg') ? $unitsPlan[$speciesId]['step'] : CompositionLib::KILO_STEP;

	}

	/**
	 * Le pas et le minimum qui s'appliquent à l'espèce, `['step' => float, 'minimum' => float]` :
	 * ceux qu'elle règle, à défaut le pas de son unité (`getStepDefault()`) et le minimum de la
	 * communauté à ce pas (`CompositionLib::getMinimumCommunity()`), que le panneau montre en attente
	 * (`minimumDefault`). Le minimum de la communauté ne se dit que dans son unité : dans une autre, le
	 * minimum est d'un pas, comme le champ le propose quand on la choisit
	 * (`CompositionSpeciesLib::loadUnits()`, `stepUnits`).
	 */
	private function getStepEffective(): array {

		$this->expects(['plant', 'unit', 'step', 'minimum']);

		$step = $this['step'] ?? $this->getStepDefault();

		if($this['minimum'] !== NULL) {
			return ['step' => $step, 'minimum' => $this['minimum']];
		}

		// Une espèce que la communauté ne compte pas (r48c) n'a pas de minimum à en tenir.
		if($this->isCommunity() === FALSE) {
			return ['step' => $step, 'minimum' => $step];
		}

		$unitCommunity = CompositionLib::getSource()['units'][$this['plant']['id']];

		$minimum = ($this['unit']->empty() or $this['unit']['id'] === $unitCommunity['unit']) ?
			CompositionLib::getMinimumCommunity($unitCommunity['minimum'], $step) :
			$step;

		return ['step' => $step, 'minimum' => $minimum];

	}

	/**
	 * L'unité où l'espèce entre au panier se coupe-t-elle ? À la pièce ou à la botte, le pas reste
	 * d'une unité et ne se règle pas. Lue sur l'unité construite, elle vaut aussi pour une unité que
	 * le même envoi vient de choisir (`CompositionSpeciesLib::getPropertiesPanel()`).
	 */
	public function isDivisible(): bool {
		return ($this->getUnitSource()['step'] !== 1.0);
	}

	/**
	 * Le `fqn` de l'unité où l'espèce entre au panier, y compris celle que le même `build()` vient de
	 * choisir : les produits qui peuvent la porter se vendent dans cette unité (r39b,
	 * `CompositionSpeciesLib::loadProducts()`).
	 */
	public function getUnitFqn(): string {
		return $this->getUnitSource()['fqn'];
	}

	private function getUnitSource(): array {

		$this->expects(['plant', 'unit']);

		$source = CompositionLib::getSource();

		return $this['unit']->empty() ?
			$source['units'][$this['plant']['id']] :
			$source['offered'][$this['unit']['id']];

	}

}
?>
