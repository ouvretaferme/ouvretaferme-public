<?php
/**
 * Ce que le tiroir d'une variété demande, qu'on vienne de l'ouvrir ou d'y écrire.
 *
 * Six requêtes par variété — les notes, les critères, ceux de la communauté, les
 * tags, et deux pour le relevé d'usage. La liste porte plusieurs centaines de
 * lignes : les préparer toutes reviendrait à en payer un millier pour la seule
 * variété qu'on ouvre.
 */
$prepareDrawer = function(\stdClass $data): void {

	$eVariety = $data->e;

	$data->eStructure = $eVariety['structure'];

	$eVariety['usage'] = \plant\VarietyLib::getUsageByVariety($data->eStructure, $eVariety);
	$eVariety['cVarietyRating'] = \plant\VarietyRatingAiLib::getByVariety($data->eStructure, $eVariety);

	$data->cCriterion = \plant\CriterionLib::getByPlant($eVariety['plant']);

	// Les critères de la communauté complètent les siens sans les remplacer : ils
	// sont le seul socle partagé entre deux structures, donc la seule matière que
	// la page de résultats pourra moyenner.
	$data->cCriterionCompletion = \plant\CriterionLib::getCompletion(
		$eVariety['plant'],
		$data->cCriterion,
		\plant\PlantSetting::PLANT_CRITERIA_RATING_MAX
	);

	// Les tags proposés sont ceux que la plante accepte : le vocabulaire est commun,
	// mais toutes ses entrées ne décrivent pas toutes les plantes.
	$data->cTag = \plant\TagLib::getByPlant($eVariety['plant']);

	\plant\VarietyRatingAiLib::applyDefaultTags($eVariety, $data->cTag);

};

/*
 * Deux adresses pour un seul écran : la notation est ce qu'on vient faire ici, et
 * c'est donc elle que sert la racine de la section, celle que porte le menu.
 * /voter reste l'adresse explicite, celle que la frise donne à l'étape.
 */
new \structure\StructurePage()
	->read(['/ferme/{id}/varietes', '/ferme/{id}/varietes/voter'], function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = $data->e->validate('canManage');

		$data->votes = \plant\VarietyLib::countVotes();

		// Noter, c'est partager : sans consentement, l'écran n'a que le formulaire de
		// consentement à montrer, et la liste n'a pas lieu d'être interrogée.
		$data->cPlant = new \Collection();

		// Filtrer sur une espèce ne fait que réduire la liste : tout le reste de
		// l'écran, tiroir compris, fonctionne à l'identique.
		$data->ePlantSelected = new \plant\Plant();

		if($data->eStructure['consentData']) {

			$data->cPlant = \plant\VarietyRatingAiLib::getByStructure($data->e);

			if(get_exists('plant')) {

				$plantId = GET('plant', 'int');

				if($data->cPlant->offsetExists($plantId)) {
					$data->ePlantSelected = $data->cPlant[$plantId];
				}

			}

		}

		throw new \ViewAction($data);

	});

new \plant\VarietyPage()
	->read('drawer', function($data) use($prepareDrawer) {

		$prepareDrawer($data);

		throw new \ViewAction($data);

	})

	/*
	 * La note globale donnée depuis la ligne repliée.
	 *
	 * Elle ouvre le tiroir dans la foulée, la valeur déjà posée : c'est le même
	 * champ que celui du tiroir, atteint par un geste plus court.
	 */
	->doUpdateProperties('doRateOverall', ['ratingOverall'], function($data) use($prepareDrawer) {

		\plant\VarietyLib::updateRatingStatus($data->e, \plant\Variety::RATED);

		$prepareDrawer($data);

		throw new \ViewAction($data);

	})

	/*
	 * Le drapeau se pose ici et non par build() : il n'est pas une saisie mais la
	 * conséquence de l'enregistrement, et le client n'a pas à décider qu'il s'est
	 * prononcé.
	 */
	->doUpdateProperties('doRate', ['tags', 'ratingOverall', 'ratingComment', 'ratingList', 'weightSeed1000'], function($data) {

		\plant\VarietyLib::updateRatingStatus($data->e, \plant\Variety::RATED);

		throw new \ViewAction($data);

	})

	/*
	 * Retirer son avis : la note globale, le commentaire et les notes par critère.
	 *
	 * Les mots-clés et le poids de mille graines restent : ils décrivent la variété,
	 * ils ne disent pas ce qu'on en pense.
	 *
	 * Le lien poste les trois champs vides plutôt que de les remettre à NULL ici :
	 * la remise à zéro emprunte ainsi le même build() que l'enregistrement, et les
	 * deux chemins ne peuvent pas diverger.
	 */
	->doUpdateProperties('doDeleteRating', ['ratingOverall', 'ratingComment', 'ratingList'], function($data) {

		\plant\VarietyLib::updateRatingStatus($data->e, \plant\Variety::WAITING);

		throw new \ViewAction($data);

	})

	/*
	 * Ignorer une variété : elle sort de la liste et n'y revient pas.
	 *
	 * Le statut est posté plutôt qu'écrit ici, comme la remise à zéro d'un avis :
	 * build() le valide contre l'énumération du schéma, et l'API empruntera le même
	 * chemin le jour où elle ouvrira cette action.
	 */
	->doUpdateProperties('doSkip', ['ratingStatus'], fn($data) => throw new \ViewAction($data));
?>
