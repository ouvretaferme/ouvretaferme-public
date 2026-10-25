<?php
/**
 * Les questions d'utilisation de Ouvretaferme : la FAQ publiée du réseau des tutoriels
 * (`LearningSetting::$structureTutorial`), rangée par écran du logiciel.
 *
 * Ouverte à tout visiteur, connecté ou non, et la même pour tous : aucun brouillon, pas même pour
 * l'équipe du réseau. Le statut se juge dans la requête (`FaqLib::getPublishedByCommunity()`), et
 * la recherche se fait dans le navigateur, sur ce que la page porte déjà : aucun paramètre n'est lu.
 *
 * Sans réseau des tutoriels, ou sur un réseau fermé aux formations, l'adresse n'existe pas : la
 * réponse part avant toute lecture de la FAQ.
 */
new Page()
	->get('/faq', function($data) {

		if(\learning\LearningSetting::$structureTutorial === NULL) {
			throw new NotExistsAction();
		}

		$eStructureTutorial = \structure\StructureLib::getById(\learning\LearningSetting::$structureTutorial);

		if(
			$eStructureTutorial->empty() or
			$eStructureTutorial->acceptLearning() === FALSE
		) {
			throw new NotExistsAction();
		}

		$data->ccFaq = \learning\FaqLib::getPublishedByCommunity($eStructureTutorial);
		\learning\FaqLib::fillChapterPublic($data->ccFaq);

		$data->faqs = $data->ccFaq->countByDepth();
		// Les questions générales n'ont pas d'écran : la bande ne compte que les écrans du logiciel.
		$data->screens = $data->ccFaq->count() - ($data->ccFaq->offsetExists('') ? 1 : 0);

		throw new ViewAction($data);

	});
?>
