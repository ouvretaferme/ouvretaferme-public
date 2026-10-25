<?php
namespace plant;

/**
 * Notation des variétés.
 *
 * L'avis d'une structure sur une variété vit sur la variété elle-même
 * (`ratingOverall`, `ratingComment`) : une variété appartient toujours à une
 * structure, celles qui n'en ont pas ne sont jamais évaluées. Ce module ne porte
 * que le détail par critère.
 */
class VarietyRatingAiLib extends VarietyRatingCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['variety', 'criterion', 'value'];
	}

	/**
	 * Les espèces légumières que la structure cultive, chacune portant ses variétés
	 * dans `cVariety`, de la plus employée à la moins employée.
	 *
	 * L'usage se lit sur l'espèce, pas sur la variété : la campagne ne couvre que le
	 * légume, et ce qui n'en est pas (l'arbre fruitier, l'aromatique, l'engrais vert
	 * qui n'est même pas une espèce) n'a rien à faire dans la liste de notation.
	 *
	 * Une seule requête pour tout l'écran : `series\Slice` n'a pas d'index
	 * exploitable ici, chaque passage est un scan complet de la table. Les variétés
	 * déjà notées sont rendues elles aussi : elles ne coûtent rien de plus, et la
	 * liste ne change pas d'ordre quand on note.
	 */
	public static function getByStructure(\structure\Structure $eStructure): \Collection {

		$select = Variety::getSelection();
		$select['plant'] = ['id', 'fqn', 'name', 'vignette', 'usageVegetable', 'criteria', 'reference'];

		$cSlice = \series\Slice::model()
			->select([
				'variety' => $select,
				'total' => new \Sql('COUNT(DISTINCT series)', 'int'),
			])
			->whereStructure($eStructure)
			->whereVariety('NOT IN', [PlantSetting::VARIETY_UNKNOWN, PlantSetting::VARIETY_MIX])
			->group(['variety'])
			// Départage nécessaire : sans lui, les égalités d'usage laissent MySQL
			// libre de changer l'ordre d'une requête à l'autre, et l'écran ne montre
			// pas deux fois les mêmes variétés en tête d'espèce.
			->sort(new \Sql('total DESC, variety ASC'))
			->getCollection();

		// Le relevé d'usage de toutes les variétés en une requête : la ligne repliée
		// l'annonce, et l'index (structure, variety) le rend au même prix que la
		// liste elle-même.
		$usages = VarietyLib::getUsageByStructure($eStructure);

		$cPlant = new \Collection();

		foreach($cSlice as $eSlice) {

			$eVariety = $eSlice['variety'];
			$ePlant = $eVariety['plant'];

			if($ePlant->acceptRating() === FALSE) {
				continue;
			}

			// Ignorée une fois, ignorée pour de bon : la liste ne la propose plus, et
			// c'est la seule façon pour une structure de sortir de sa liste une variété
			// sur laquelle elle n'a rien à dire.
			if($eVariety['ratingStatus'] === Variety::SKIPPED) {
				continue;
			}

			$eVariety['usage'] = $usages[$eVariety['id']];

			if($cPlant->offsetExists($ePlant['id']) === FALSE) {
				$ePlant['cVariety'] = new \Collection();
				$cPlant[$ePlant['id']] = $ePlant;
			}

			$cPlant[$ePlant['id']]['cVariety'][] = $eVariety;

		}

		return $cPlant->sort(['name' => SORT_ASC]);

	}

	/**
	 * Les notes par critère d'une variété, indexées par critère.
	 */
	public static function getByVariety(\structure\Structure $eStructure, Variety $eVariety): \Collection {

		if($eVariety->empty()) {
			return new \Collection();
		}

		return VarietyRating::model()
			->select(['id', 'criterion', 'value'])
			->whereStructure($eStructure)
			->whereVariety($eVariety)
			->getCollection(index: 'criterion');

	}

	/**
	 * Les variétés que cette structure préfère, pour sa fiche publique.
	 *
	 * « Préférée » veut dire au moins une note de 4 ou 5, et chaque variété s'affiche avec son
	 * critère le mieux noté. **Aucune moyenne** : `plantCriterion` porte des critères de quatre
	 * familles, et moyenner un rendement avec une facilité de taille ne mesure rien. L'ordre
	 * suit le nombre de critères notés, décroissant — une variété jugée sur six critères en dit
	 * plus qu'une notée 5/5 sur un seul.
	 *
	 * Ne passe pas par getByStructure(), qui rend la file de notation de l'écran de la ferme :
	 * des variétés à noter, pas des notes, et une agrégation sur `series\Slice`.
	 */
	public static function getPreferredByStructure(\structure\Structure $eStructure): \Collection {

		$cVarietyRating = VarietyRating::model()
			->select([
				'variety' => ['id', 'name'],
				'criterion' => ['id', 'name'],
				'value'
			])
			->whereStructure($eStructure)
			->sort(['value' => SORT_DESC])
			->getCollection();

		$cVariety = new \Collection();

		foreach($cVarietyRating as $eVarietyRating) {

			$eVariety = $eVarietyRating['variety'];
			$id = $eVariety['id'];

			if($cVariety->offsetExists($id) === FALSE) {
				$eVariety['ratings'] = 0;
				$eVariety['preferred'] = FALSE;
				$eVariety['eCriterion'] = new Criterion();
				$eVariety['value'] = NULL;
				$cVariety[$id] = $eVariety;
			}

			$cVariety[$id]['ratings']++;

			// La collection est triée par note décroissante : la première rencontrée est la
			// meilleure, et les suivantes ne la remplacent pas.
			if($cVariety[$id]['value'] === NULL) {
				$cVariety[$id]['eCriterion'] = $eVarietyRating['criterion'];
				$cVariety[$id]['value'] = $eVarietyRating['value'];
			}

			if($eVarietyRating['value'] >= 4) {
				$cVariety[$id]['preferred'] = TRUE;
			}

		}

		return $cVariety
			->find(fn($eVariety) => $eVariety['preferred'])
			->sort([
				'ratings' => SORT_DESC,
				'name' => SORT_ASC
			]);

	}

	/**
	 * Remplace les notes par critère d'une variété par celles qu'on lui donne : table
	 * rase puis recréation, ce qui traite d'un coup les ajouts, les modifications et
	 * les critères repassés à zéro étoile.
	 *
	 * Appelée depuis VarietyLib::update(), qui porte déjà la transaction.
	 */
	public static function replaceCollection(\structure\Structure $eStructure, Variety $eVariety, \Collection $cVarietyRating): void {

		foreach(self::getByVariety($eStructure, $eVariety) as $eVarietyRatingExisting) {
			self::delete($eVarietyRatingExisting);
		}

		foreach($cVarietyRating as $eVarietyRating) {
			self::create($eVarietyRating);
		}

	}

	public static function buildFromInput(Variety $eVariety, array $input): \Collection {

		$eVariety->expects(['structure']);

		// Exactement ce que la carte propose : ses critères et ceux de la communauté.
		// Une note portée sur un critère absent d'ici est écartée sans un mot, donc les
		// deux listes doivent rester celles de l'écran.
		$cCriterion = CriterionLib::getByPlant($eVariety['plant']);

		$cCriterion->mergeCollection(
			CriterionLib::getCompletion($eVariety['plant'], $cCriterion, PlantSetting::PLANT_CRITERIA_RATING_MAX),
			reindex: FALSE
		);

		$cVarietyRating = new \Collection();

		foreach((array)($input['value'] ?? []) as $criterionId => $value) {

			if($cCriterion->offsetExists((int)$criterionId) === FALSE) {
				continue;
			}

			if($value === '') {
				continue;
			}

			$eVarietyRating = new VarietyRating([
				'structure' => $eVariety['structure'],
				'variety' => $eVariety,
				'criterion' => $cCriterion[(int)$criterionId],
			]);

			$eVarietyRating->buildIndex(['value'], $input, $criterionId, new \PropertyList('create'));

			$cVarietyRating[(int)$criterionId] = $eVarietyRating;

		}

		return $cVarietyRating;

	}

	/**
	 * Le nom annonce-t-il un hybride ?
	 *
	 * La règle vit ici seule : elle sert à précocher le mot sur une variété, et à
	 * décider quelles espèces le proposent. Deux écritures de la même règle
	 * divergeraient au premier ajustement.
	 */
	public static function isHybridName(string $name): bool {
		return (preg_match('/f1(?![0-9])/i', $name) === 1);
	}

	/**
	 * Précoche « F1 » sur une variété dont le nom l'annonce, tant qu'elle n'a pas été
	 * notée.
	 *
	 * La règle refuse « F1 » suivi d'un chiffre : sans elle « BF15 » et « Antillais
	 * F10 » passeraient pour des hybrides. Elle accepte en revanche le nom collé, très
	 * répandu — « ShakiraF1 », « Cauralina HF1 » — qu'une limite de mot manquerait.
	 * Mesuré le 2026-09-01 : 6 490 variétés retenues sur 82 230, contre 6 496 pour un
	 * simple « contient F1 » et 6 464 pour la limite de mot.
	 *
	 * Rien dès que la structure s'est prononcée : son choix fait foi, et reposer un
	 * mot qu'elle a retiré serait le lui imposer. S'être prononcé, c'est avoir laissé
	 * un mot, une note ou un commentaire ; une variété qui n'en porte aucun n'a pas
	 * encore d'avis, et le mot par défaut peut lui être proposé.
	 */
	public static function applyDefaultTags(Variety $eVariety, \Collection $cTag): void {

		$eVariety->expects(['name', 'tags', 'ratingOverall', 'ratingComment']);

		if(
			$eVariety['tags'] !== [] or
			$eVariety['ratingOverall'] !== NULL or
			$eVariety['ratingComment'] !== NULL
		) {
			return;
		}

		if(self::isHybridName($eVariety['name']) === FALSE) {
			return;
		}

		foreach($cTag as $eTag) {

			if($eTag['fqn'] === PlantSetting::TAG_F1) {

				$eVariety['tags'] = array_values(array_unique(array_merge($eVariety['tags'], [$eTag['id']])));

				return;

			}

		}

	}

	public static function deleteByVariety(Variety $eVariety): void {

		VarietyRating::model()
			->whereVariety($eVariety)
			->delete();

	}

}
?>
