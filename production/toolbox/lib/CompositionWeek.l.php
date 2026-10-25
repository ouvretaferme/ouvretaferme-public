<?php
namespace toolbox;

/**
 * Une semaine livrée d'une formule : ce que le panier contient cette semaine-là.
 *
 * Elle existe parce qu'un retrait de ligne ne laisse aucune ligne derrière lui. `amended` doit donc
 * vivre au-dessus des lignes, et c'est aussi l'unité de perte que l'avertissement nomme avant un
 * recalcul.
 */
class CompositionWeekLib extends CompositionWeekCrud {

	/**
	 * `amended` et `relaxedFloor` ne sont pas constructibles à la création, et ce n'est pas un
	 * oubli : une semaine naît telle que la règle l'a calculée, et ne se relâche qu'ensuite.
	 * `relaxedFloor` l'impose de toute façon, un `set` non posté étant refusé par le contrôle du
	 * schéma, là où un `bool` retombe sur son défaut.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['formula', 'week'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['amended', 'relaxedFloor'];
	}

	public static function getByFormula(CompositionFormula $eCompositionFormula): \Collection {

		return CompositionWeek::model()
			->select(CompositionWeek::getSelection())
			->whereFormula($eCompositionFormula)
			->sort(['week' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Les semaines de toutes les tailles d'un plan, le numéro de semaine seul : une semaine du plan
	 * est une ligne par taille qui la sert.
	 */
	public static function getByFormulas(\Collection $cCompositionFormula): \Collection {

		return CompositionWeek::model()
			->select(['id', 'week'])
			->whereFormula('IN', $cCompositionFormula)
			->getCollection();

	}

	/**
	 * Les semaines d'une formule enregistrée telles que l'écran les lit : chacune avec ses lignes,
	 * l'espèce et l'unité de chaque ligne, sa valeur, son écart au prix visé et son nombre de produits
	 * (`lines`), que la bulle de la semaine écrit (r30, #89).
	 *
	 * La valeur et l'écart sont calculés par `CompositionLib` avec les fonctions que la règle emploie
	 * pour relancer le nombre de lignes : l'écran ne compte pas une semaine hors de l'écart que la
	 * règle a cherché à réduire, et inversement.
	 *
	 * L'espèce de chaque ligne est prise dans `$cCompositionSpecies`, les espèces du plan indexées
	 * par leur id (`CompositionSpeciesLib::getByPlan()`) : l'écran les a déjà lues, et une ligne ne
	 * peut servir qu'une espèce de son plan (`CompositionLine::species.owned`). La relire par une
	 * sous-sélection coûtait deux requêtes par taille, l'espèce puis sa plante (chantier
	 * Performances, P7).
	 */
	public static function getByFormulaWithLines(CompositionFormula $eCompositionFormula, \Collection $cCompositionSpecies): \Collection {

		$eCompositionFormula->expects(['id', 'price', 'gap']);

		$cCompositionWeek = CompositionWeek::model()
			->select(CompositionWeek::getSelection())
			->select([
				'cCompositionLine' => CompositionLine::model()
					->select([
						'id', 'week', 'quantity', 'quantityProposed', 'unitPrice', 'origin',
						'species',
						'unit' => ['fqn', 'singular', 'plural', 'short', 'type'],
					])
					->sort(['id' => SORT_ASC])
					->delegateCollection('week')
			])
			->whereFormula($eCompositionFormula)
			->sort(['week' => SORT_ASC])
			->getCollection();

		// Chaque ligne porte sa semaine, et chaque semaine la formule reçue : c'est sur eux que se lit
		// le droit de régler une ligne sur la grille (`CompositionLine::canWrite()`).
		$cCompositionWeek->setColumn('formula', $eCompositionFormula);

		// Une semaine ne suit sa base, et une ligne n'en est reprise, que lorsque `applyBase()` le dit.
		foreach($cCompositionWeek as $eCompositionWeek) {
			$eCompositionWeek['cCompositionLine']->setColumn('week', $eCompositionWeek);

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
				$eCompositionLine['species'] = $cCompositionSpecies[$eCompositionLine['species']['id']];
			}

			$eCompositionWeek['cCompositionLine']->setColumn('inherited', FALSE);
			$eCompositionWeek['eCompositionFormulaFollowed'] = new CompositionFormula();
			$eCompositionWeek['eCompositionFormulaLeft'] = new CompositionFormula();
		}

		foreach($cCompositionWeek as $eCompositionWeek) {

			$values = [];

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
				$eCompositionLine['value'] = CompositionLib::getLineValue($eCompositionLine['quantity'], $eCompositionLine['unitPrice']);
				$values[] = $eCompositionLine['value'];
			}

			$eCompositionWeek['value'] = CompositionLib::getWeekValue($values);
			$eCompositionWeek['lines'] = count($values);
			$eCompositionWeek['gap'] = CompositionLib::isGap((float)$eCompositionFormula['price'], $eCompositionWeek['value'], $eCompositionFormula['gap']);

		}

		return $cCompositionWeek;

	}

	/**
	 * Range les lignes de chaque semaine dans l'ordre des rangs de la grille (`CompositionLib::getGrid()`),
	 * celui que la carte de la semaine écrit (planche r1) : on lit une carte à côté de la colonne de
	 * sa semaine, et les mêmes espèces y viennent dans le même ordre. Toute ligne a son rang, la grille
	 * se construisant d'abord sur les lignes.
	 */
	public static function sortByGrid(\Collection $cCompositionWeek, array $grid): void {

		$positions = [];

		foreach($grid as $group) {
			foreach($group['species'] as $row) {
				$positions[$row['species']['id']] = count($positions);
			}
		}

		foreach($cCompositionWeek as $eCompositionWeek) {
			$eCompositionWeek['cCompositionLine']->sort(fn(CompositionLine $eCompositionLine1, CompositionLine $eCompositionLine2) => $positions[$eCompositionLine1['species']['id']] <=> $positions[$eCompositionLine2['species']['id']]);
		}

	}

	/**
	 * Ce que chaque semaine d'une taille dérivée doit à sa base (lot r17, planche paniers-maquette-r16,
	 * question 3, option B), pour la carte de la semaine :
	 *
	 * - une semaine que la base sert et qui n'est pas corrigée suit la base
	 *   (`eCompositionFormulaFollowed`) : ses lignes dont l'espèce est au panier de la base ce
	 *   semaine-là sont reprises (`inherited`), et passent devant les autres, chacun des deux groupes
	 *   gardant l'ordre de la grille (`sortByGrid()`, le tri de `Collection::sort()` étant stable) ;
	 * - une semaine que la base sert et qu'on a corrigée ne la suit plus (`eCompositionFormulaLeft`) :
	 *   la recomposition de la base l'épargne (`CompositionLib::recomposeDerived()`), et « Revenir à la
	 *   proposition » la recale sur la base ;
	 * - une semaine que la base ne sert pas se compose seule, et ne porte ni l'un ni l'autre.
	 *
	 * `$cCompositionWeek` vient de `getByFormulaWithLines()`, déjà rangé par `sortByGrid()`, et
	 * `$eCompositionFormulaBase` porte ses propres semaines (`cCompositionWeek`).
	 *
	 * Rend le nombre médian de lignes qu'une semaine qui suit la base ajoute à ses espèces reprises,
	 * que l'annotation du prix écrit (r31, #97), NULL quand aucune semaine ne suit la base.
	 */
	public static function applyBase(\Collection $cCompositionWeek, CompositionFormula $eCompositionFormulaBase): ?int {

		$speciesBase = [];

		foreach($eCompositionFormulaBase['cCompositionWeek'] as $eCompositionWeekBase) {
			foreach($eCompositionWeekBase['cCompositionLine'] as $eCompositionLine) {
				$speciesBase[$eCompositionWeekBase['week']][] = $eCompositionLine['species']['id'];
			}
		}

		$added = [];

		foreach($cCompositionWeek as $eCompositionWeek) {

			if(array_key_exists($eCompositionWeek['week'], $speciesBase) === FALSE) {
				continue;
			}

			if($eCompositionWeek['amended']) {
				$eCompositionWeek['eCompositionFormulaLeft'] = $eCompositionFormulaBase;
				continue;
			}

			$eCompositionWeek['eCompositionFormulaFollowed'] = $eCompositionFormulaBase;

			$count = 0;

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
				$eCompositionLine['inherited'] = in_array($eCompositionLine['species']['id'], $speciesBase[$eCompositionWeek['week']], TRUE);
				if($eCompositionLine['inherited'] === FALSE) {
					$count++;
				}
			}

			$added[] = $count;

			$eCompositionWeek['cCompositionLine']->sort(fn(CompositionLine $eCompositionLine1, CompositionLine $eCompositionLine2) => $eCompositionLine2['inherited'] <=> $eCompositionLine1['inherited']);

		}

		if($added === []) {
			return NULL;
		}

		sort($added);

		return $added[intdiv(count($added), 2)];

	}

	/**
	 * Les quantités où mènent le moins et le plus d'une ligne (planche r5, question 2) : un pas de
	 * l'espèce de moins ou de plus, dans l'unité de la ligne (`CompositionLib::getLineStep()`, `$steps` venant de
	 * `CompositionSpeciesLib::getStepsByPlan()`). `quantityLess` est nul au minimum de l'espèce, que
	 * la quantité d'une ligne ne franchit pas (`CompositionLine::properties()`) ; `quantityMore` n'a
	 * pas de borne haute, et ne mène jamais sous le minimum : une ligne écrite sous celui que la
	 * communauté a donné depuis (lot r25), 0,1 kg de poireau pour un minimum de 0,5, monte d'un coup
	 * au minimum, que la quantité refuserait sinon. Le `round()` efface le reliquat binaire d'un pas décimal, comme dans
	 * `CompositionLib::roundToStep()`, sans quoi 0,3 moins 0,1 tomberait sous un minimum de 0,2.
	 *
	 * Une ligne hors du pas de son espèce, écrite avant que le pas change (le pas par défaut du kilo,
	 * lot r18, ou un pas réglé après coup sur une semaine corrigée), va au multiple du pas le plus
	 * proche de son côté : 0,5 kg au pas de 0,2 mène à 0,4 et à 0,6, et non à 0,3 et à 0,7, que la
	 * quantité refuserait (`CompositionLine::quantity.step`). Le millième de marge est celui de
	 * `CompositionLib::isStepMultiple()`.
	 */
	public static function applySteps(\Collection $cCompositionWeek, array $steps, array $source): void {

		foreach($cCompositionWeek as $eCompositionWeek) {

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

				$step = CompositionLib::getLineStep($eCompositionLine['species']['id'], $eCompositionLine['unit'], $source, $steps);

				$position = $eCompositionLine['quantity'] / $step['step'];

				$quantityLess = round((ceil($position - 0.001) - 1) * $step['step'], 2);

				$eCompositionLine['quantityLess'] = ($quantityLess >= $step['minimum']) ? $quantityLess : NULL;
				$eCompositionLine['quantityMore'] = max($step['minimum'], round((floor($position + 0.001) + 1) * $step['step'], 2));

			}

		}

	}

	/**
	 * La semaine livrée `$week` d'une formule, vide quand la formule n'y a pas de panier. Elle porte
	 * la formule reçue, sur laquelle se lit le droit de l'écrire.
	 */
	public static function getByFormulaAndWeek(CompositionFormula $eCompositionFormula, int $week): CompositionWeek {

		$eCompositionWeek = CompositionWeek::model()
			->select(CompositionWeek::getSelection())
			->whereFormula($eCompositionFormula)
			->whereWeek($week)
			->get();

		if($eCompositionWeek->notEmpty()) {
			$eCompositionWeek['formula'] = $eCompositionFormula;
		}

		return $eCompositionWeek;

	}

	/**
	 * La semaine porte une correction : une case allumée ou éteinte à la main
	 * (`CompositionLineLib::create()`, `delete()`). Rien ne l'efface qu'un retour à la proposition
	 * (`CompositionLib::recomposeWeeks()`) ou un recalcul de la formule.
	 *
	 * La correction d'une base vaut pour ses dérivées (lot r17) : la même semaine de chacune, si elle
	 * n'est pas corrigée, reprend le panier corrigé (`CompositionLib::recomposeDerived()`), dans la
	 * transaction de la ligne. Aucune autre semaine ne bouge.
	 */
	public static function amend(CompositionWeek $e): void {

		$e['amended'] = TRUE;

		self::update($e, ['amended']);

		// La semaine arrive de tout geste sur une ligne, qui ne la porte pas toujours avec sa formule :
		// sa formule et son plan se relisent ici, une requête, avant de chercher ses dérivées.
		$eCompositionWeek = CompositionWeek::model()
			->select([
				'week',
				'formula' => ['plan' => CompositionPlan::getSelection()],
			])
			->whereId($e)
			->get();

		$cCompositionFormulaDerived = CompositionFormulaLib::getByBase($eCompositionWeek['formula']);

		if($cCompositionFormulaDerived->notEmpty()) {

			$eCompositionPlan = $eCompositionWeek['formula']['plan'];

			$source = CompositionLib::getSourceByPlan($eCompositionPlan);

			CompositionLib::recomposeDerived($eCompositionPlan, $cCompositionFormulaDerived, [$eCompositionWeek['week']], $source, $source['pricesPlan']);

		}

	}

	/**
	 * Les semaines qu'un amendement a touchées, que l'avertissement avant perte énumère. Vide quand
	 * personne n'a rien corrigé, et c'est ce qui décide que l'avertissement ne paraît pas.
	 */
	public static function getAmendedByFormula(CompositionFormula $eCompositionFormula): \Collection {

		return CompositionWeek::model()
			->select(CompositionWeek::getSelection())
			->whereFormula($eCompositionFormula)
			->whereAmended(TRUE)
			->sort(['week' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Les semaines d'une formule, par leur numéro, qui servent l'espèce sans avoir été corrigées :
	 * celles qu'un retrait de l'espèce recompose (`CompositionSpeciesLib::exclude()`).
	 */
	public static function getUnamendedBySpecies(CompositionFormula $eCompositionFormula, CompositionSpecies $eCompositionSpecies): array {

		return CompositionWeek::model()
			->whereFormula($eCompositionFormula)
			->whereAmended(FALSE)
			->where(CompositionLine::model()->whereWeek(new \Sql('m1.id'))->whereSpecies($eCompositionSpecies)->existsSql())
			->sort(['week' => SORT_ASC])
			->getColumn('week');

	}

	/**
	 * La semaine emporte ses lignes.
	 */
	public static function delete(CompositionWeek $e): void {

		$e->expects(['id']);

		foreach(CompositionLineLib::getByWeek($e) as $eCompositionLine) {
			CompositionLineLib::delete($eCompositionLine, amend: FALSE);
		}

		parent::delete($e);

	}

}
?>
