<?php
namespace toolbox;

/**
 * Une ligne de panier : une espèce, une unité, une quantité.
 *
 * `quantityProposed` et `unitPrice` sont figés à l'écriture de la proposition, et c'est le point du
 * modèle qu'il ne faut pas défaire : le cron de 4 h déplace `aggregate\Price` et
 * `aggregate\Cultivation`, donc une quantité ou une valeur recalculée à la lecture ferait bouger
 * d'une nuit à l'autre un plan que personne n'a touché. L'annotation « proposé 0,8 kg », dont le
 * rôle est de dire ce que l'utilisateur a corrigé, dirait alors ce que la règle proposerait
 * aujourd'hui.
 */
class CompositionLineLib extends CompositionLineCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return array_merge(['week', 'species', 'unit', 'quantityProposed', 'unitPrice', 'origin'], self::getPropertiesUpdate());
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['quantity'];
	}

	/**
	 * Une ligne proposée s'écrit telle que la règle l'a composée (`CompositionLib::compose()`).
	 *
	 * Une ligne ajoutée à la main (`USER`) prend son prix de la règle
	 * (`CompositionLib::getLineReference()`), jamais de ce que le navigateur enverrait, et
	 * `quantityProposed` reste nul, puisque personne ne l'a proposée. Le menu d'une case de saison
	 * (lot r24b) lui donne sa quantité, que `build()` a bornée au minimum et au pas de l'espèce, et
	 * son unité, qui est celle de l'espèce (`CompositionLine::unit.species`). Une ligne écrite sans
	 * elles prend l'unité et la quantité de référence. Sa semaine prend `amended` dans la même
	 * transaction : c'est ce que l'avertissement avant un recalcul lit.
	 *
	 * Deux envois simultanés de la même case passent tous deux `species.duplicate`, et l'unicité
	 * `(week, species)` du schéma refuse le second (lot r40) : il rend le même refus que le contrôle, et
	 * sa semaine ne prend pas `amended`, rien n'y ayant changé. Une ligne proposée ne s'en garde pas :
	 * la règle ne sert jamais deux fois une espèce dans une semaine, et un doublon y serait un défaut
	 * à voir, pas un refus à dire.
	 */
	public static function create(CompositionLine $e): void {

		$e->expects(['origin']);

		if($e['origin'] === CompositionLine::PROPOSED) {
			parent::create($e);
			return;
		}

		$e->expects(['species', 'week' => ['week', 'formula' => ['plan']]]);

		$eCompositionPlan = $e['week']['formula']['plan'];

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$steps = CompositionSpeciesLib::getStepsByPlan($eCompositionPlan);

		$reference = CompositionLib::getLineReference($e['week']['week'], $e['species']['id'], $source, $source['pricesPlan'], $steps);

		$e->add([
			'unit' => new \product\Unit(['id' => $reference['unit']]),
			'quantity' => $reference['quantity'],
		]);

		$e['quantityProposed'] = NULL;
		$e['unitPrice'] = $reference['unitPrice'];

		CompositionLine::model()->beginTransaction();

			try {
				parent::create($e);
			} catch(\DuplicateException) {

				CompositionLine::fail('species.duplicate');

				// L'insertion refusée n'a rien écrit, et `rollBack()` viderait toute la pile, transaction
				// de l'appelant comprise : la transaction ouverte ici se referme par un `commit()`.
				CompositionLine::model()->commit();

				return;

			}

			CompositionWeekLib::amend($e['week']);

		CompositionLine::model()->commit();

	}

	/**
	 * Une quantité réglée sur la grille est une correction, comme une case allumée ou éteinte : sa
	 * semaine prend `amended` dans la même transaction. `quantityProposed` ne bouge pas, et c'est
	 * ce que le panneau de la case rappelle sous la quantité (« Proposé 0,8 kg »).
	 */
	public static function update(CompositionLine $e, array $properties): void {

		$e->expects(['week']);

		CompositionLine::model()->beginTransaction();

			parent::update($e, $properties);

			if(in_array('quantity', $properties)) {
				CompositionWeekLib::amend($e['week']);
			}

		CompositionLine::model()->commit();

	}

	/**
	 * Le prix d'une espèce a changé (`CompositionSpeciesLib::update()`) : ses lignes des semaines
	 * `$cCompositionWeek`, dans l'unité `$eUnit` où ce prix se lit, prennent `$unitPrice`. Ni la
	 * quantité ni `amended` ne bougent (Q9, un prix ne recompose rien), là où `update()` marquerait
	 * la semaine corrigée : c'est la valeur des semaines qui change, pas les paniers.
	 *
	 * Une écriture de masse, donc un tableau de valeurs et non un élément : avec un élément porteur
	 * d'un `id`, `update()` du modèle ajoute `AND id = <id>` et n'écrit qu'une ligne
	 * (`Module::getUpdateCondition()`).
	 */
	public static function updateUnitPrice(\Collection $cCompositionWeek, CompositionSpecies $eCompositionSpecies, \product\Unit $eUnit, ?float $unitPrice): void {

		CompositionLine::model()
			->whereWeek('IN', $cCompositionWeek)
			->whereSpecies($eCompositionSpecies)
			->whereUnit($eUnit)
			->update(['unitPrice' => $unitPrice]);

	}

	/**
	 * Le pas ou la quantité minimale d'une espèce ont changé (`CompositionSpeciesLib::update()`) : ses
	 * lignes des semaines non corrigées des tailles `$cCompositionFormula`, dans l'unité `$eUnit` où le
	 * pas se compte, s'arrondissent au pas `$step` et montent à son minimum
	 * (`CompositionLib::roundToStep()`), sans que rien ne se recompose (planche r1). Les semaines
	 * corrigées gardent ce qu'on y a réglé à la main, comme après un changement de prix, et aucune
	 * semaine ne prend `amended` : c'est la règle qui s'applique de nouveau, pas une correction.
	 *
	 * `quantityProposed` s'arrondit avec `quantity` : une semaine non corrigée ne porte que des lignes
	 * proposées, puisque toute ligne ajoutée ou réglée à la main marque sa semaine
	 * (`create()`, `update()`), et la proposition suit la règle, dont le pas vient de changer. Sans
	 * cela, le panneau de la case annoncerait « Proposé 0,3 kg » sur une ligne que personne n'a
	 * corrigée et qui sert 0,4 kg.
	 *
	 * Les lignes qui prennent la même quantité s'écrivent ensemble, en une écriture de masse, donc un
	 * tableau de valeurs et non un élément : avec un élément porteur d'un `id`, `update()` du modèle
	 * ajoute `AND id = <id>` et n'écrit qu'une ligne (`Module::getUpdateCondition()`). L'arrondi se
	 * calcule ici et non en SQL, pour être exactement celui de la règle : `ROUND()` sur un décimal
	 * exact et `round()` sur un flottant ne tombent pas toujours du même côté d'une moitié de pas.
	 */
	public static function updateStep(\Collection $cCompositionFormula, CompositionSpecies $eCompositionSpecies, \product\Unit $eUnit, array $step): void {

		$cCompositionLine = CompositionLine::model()
			->select(['id', 'quantity'])
			->whereWeek('IN', CompositionWeek::model()
				->select('id')
				->whereFormula('IN', $cCompositionFormula)
				->whereAmended(FALSE)
				->getSql())
			->whereSpecies($eCompositionSpecies)
			->whereUnit($eUnit)
			->getCollection();

		// Une quantité n'est pas une clé de tableau : elle y entre écrite, et reste à côté telle quelle.
		$linesByQuantity = [];

		foreach($cCompositionLine as $eCompositionLine) {

			$quantity = CompositionLib::roundToStep($eCompositionLine['quantity'], $step);

			if($quantity === $eCompositionLine['quantity']) {
				continue;
			}

			$key = var_export($quantity, TRUE);

			$linesByQuantity[$key] ??= ['quantity' => $quantity, 'ids' => []];
			$linesByQuantity[$key]['ids'][] = $eCompositionLine['id'];

		}

		foreach($linesByQuantity as ['quantity' => $quantity, 'ids' => $ids]) {

			CompositionLine::model()
				->whereId('IN', $ids)
				->update([
					'quantity' => $quantity,
					'quantityProposed' => $quantity,
				]);

		}

	}

	/**
	 * Éteindre une case supprime sa ligne, qu'elle soit proposée ou ajoutée à la main, et ne laisse
	 * aucune trace qu'elle a existé (Q2) ; sa semaine prend `amended`, et c'est la seule mémoire du
	 * retrait. La semaine ne se recompose pas (Q5) : sa valeur baisse de ce que la ligne valait.
	 *
	 * `amend: FALSE` quand la ligne part avec sa semaine ou avec la proposition qu'on réécrit
	 * (`CompositionWeekLib::delete()`, `CompositionLib::recomposeWeeks()`) : ce n'est pas une
	 * correction, et la marquer coûterait une écriture par ligne à chaque recalcul.
	 */
	public static function delete(CompositionLine $e, bool $amend = TRUE): void {

		$e->expects(['id', 'week']);

		CompositionLine::model()->beginTransaction();

			parent::delete($e);

			if($amend) {
				CompositionWeekLib::amend($e['week']);
			}

		CompositionLine::model()->commit();

	}

	/**
	 * La ligne qui sert une espèce une semaine donnée, vide quand l'espèce n'y est pas servie : une
	 * semaine ne porte jamais deux lignes de la même espèce (`CompositionLine::properties()`,
	 * `duplicate`, et l'unicité `(week, species)` du schéma).
	 */
	public static function getByWeekAndSpecies(CompositionWeek $eCompositionWeek, CompositionSpecies $eCompositionSpecies): CompositionLine {

		return CompositionLine::model()
			->select(CompositionLine::getSelection())
			->whereWeek($eCompositionWeek)
			->whereSpecies($eCompositionSpecies)
			->get();

	}

	public static function getByWeek(CompositionWeek $eCompositionWeek): \Collection {

		return CompositionLine::model()
			->select(CompositionLine::getSelection())
			->whereWeek($eCompositionWeek)
			->getCollection();

	}

	/**
	 * Les lignes écrites d'une formule, dans l'ordre où elles sont nées, avec ce que sa dérivée en
	 * reprend (lot r17, `CompositionLib::getBaseLines()`) : le numéro de la semaine, l'organe de
	 * l'espèce, qui compte au plafond et au plancher du tirage, et le type de l'unité, qui donne le
	 * pas d'une ligne restée dans une autre unité que celle de l'espèce (lot r14).
	 */
	public static function getByFormula(CompositionFormula $eCompositionFormula): \Collection {

		return CompositionLine::model()
			->select([
				'quantity', 'unitPrice',
				'week' => ['week'],
				'species' => ['plant' => ['organ']],
				'unit' => ['type'],
			])
			->whereWeek('IN', CompositionWeek::model()->select(['id'])->whereFormula($eCompositionFormula)->getSql())
			->sort(['id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Les lignes des semaines données, en une requête : la grille espèces × semaines se construit
	 * ensuite en mémoire sur ces lignes, et c'est pour cela que `species` ne porte d'index que dans
	 * l'unicité `(week, species)`.
	 */
	public static function getByWeeks(\Collection $cCompositionWeek): \Collection {

		if($cCompositionWeek->empty()) {
			return new \Collection();
		}

		return CompositionLine::model()
			->select(CompositionLine::getSelection())
			->whereWeek('IN', $cCompositionWeek)
			->getCollection();

	}

}
?>
