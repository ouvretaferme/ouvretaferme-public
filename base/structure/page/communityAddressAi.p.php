<?php
/**
 * La racine du domaine des réseaux, reseau.<domaine>, que le vhost réécrit en /reseau/…
 *
 * `/{fqn}` est la page du réseau quand il propose au moins une date à venir d'une formation publiée :
 * le bandeau, « Prochaines formations », puis l'accès aux relevés de prix s'il a une collecte ouverte
 * au public, et l'accès à ses questions fréquentes s'il en a publié. C'est la première dépendance
 * de `base/structure` vers le package `learning` dans une vue :
 * la liste des dates est lue ici et rendue par `learning\DatePublicUi`, comme la carte des relevés
 * vient de `quotation`.
 *
 * Sans date à venir, `/{fqn}` mène aux relevés de prix du réseau quand il les tient, en 302 et non en
 * 301, parce que la page peut revenir dès qu'une date est publiée ; une page qui ne porterait que la
 * carte des relevés n'aurait pas de sens. Une ancienne adresse mène à l'adresse actuelle dans les deux
 * cas, vers la page du réseau ou vers ses relevés.
 *
 * Le gérant y arrive aussi par la carte « Personnaliser l'apparence » du paramétrage (`?customize`),
 * qui ouvre la barre du thème : sans date à venir, il suit la redirection avec le mode et les
 * réglages essayés, vers les relevés, ou vers la liste des formations d'un réseau qui n'a pas de
 * relevés, où le public trouve une 404 faute de page à lui montrer. Le réseau des tutoriels, qui n'a
 * ni accueil ni formations à montrer, mène le gérant à la fiche d'un tutoriel.
 *
 * Planche : https://claude.ai/code/artifact/6cfbb53a-b71e-45d3-8727-440f1e1f5694
 */
new Page()
	->get('/reseau/', function($data) {
		throw new TemporaryRedirectAction(Lime::getUrl());
	})
	->get([[
		'/reseau/robots.txt',
		'@priority' => 1
	]], function($data) {

		// Les pages des réseaux se diffusent par leurs liens, elles n'ont rien à faire dans un moteur de recherche.
		$data = 'User-agent: *'."\n";
		$data .= 'Disallow: /'."\n";

		throw new DataAction($data, 'text/txt');

	})
	->get('/reseau/{fqn}', function($data) {

		$data->eCommunity = \structure\CommunityLib::getByAddress(GET('fqn'));
		$data->eStructure = \structure\StructureLib::getById($data->eCommunity->empty() ? NULL : $data->eCommunity['structure']['id']);

		$data->withQuotation = (
			$data->eStructure->notEmpty() and
			$data->eStructure->acceptGroupQuotationSetting() and
			$data->eStructure->acceptGroupQuotation()
		);

		$data->customize = (
			$data->eStructure->notEmpty() and
			\learning\CommunityLib::hasPublic($data->eStructure) and
			get_exists('customize') and
			$data->eStructure->canManage()
		);

		if($data->customize) {

			// Le réseau des tutoriels règle son thème sur la fiche d'un tutoriel, où mène la carte du
			// paramétrage : la redirection plus bas le mènerait à ses formations, qui lui répondent
			// 404. Un lien en mode qui date d'avant la carte y mène aussi.
			$eCourseCustomize = \learning\CommunityLib::getCustomizeCourse($data->eStructure);

			if($eCourseCustomize->notEmpty()) {
				throw new TemporaryRedirectAction(Lime::getUrl().\learning\CustomizeUi::urlCard($data->eCommunity, $eCourseCustomize));
			}

			\learning\CommunityLib::applyPreview($data->eCommunity, $_GET, onKo: fn() => throw new TemporaryRedirectAction(\structure\CommunityUi::url($data->eCommunity).'?customize=1'));

		}

		$data->customizeQuery = $data->customize ? \learning\CustomizeUi::getQuery($data->eCommunity->extracts(\learning\CommunityLib::getPropertiesCustomize())) : [];

		if(
			$data->eStructure->notEmpty() and
			$data->eStructure->isNetwork() and
			$data->eStructure->acceptLearning()
		) {

			$data->cDate = \learning\DateLib::getPublicByCommunity($data->eStructure);

			if($data->cDate->notEmpty()) {

				if(GET('fqn') !== $data->eCommunity['fqn']) {
					throw new TemporaryRedirectAction(\structure\CommunityUi::url($data->eCommunity));
				}

				\learning\DateEnrollmentLib::fillForCourse($data->cDate, \user\ConnectionLib::getOnline());

				$data->settings = \learning\CommunityLib::getSettings($data->eCommunity);

				// La FAQ du réseau des tutoriels se lit sur `/faq`, son adresse de réseau répond 404.
				$data->faqs = ($data->eStructure['id'] === \learning\LearningSetting::$structureTutorial) ? 0 : \learning\FaqLib::countPublishedByCommunity($data->eStructure);

				if($data->withQuotation) {
					\structure\StructureLib::loadCommunityPublic($data->eStructure);
				}

				throw new ViewAction($data);

			}

		}

		$query = $data->customize ? '?'.http_build_query($data->customizeQuery) : '';

		if($data->withQuotation === FALSE) {

			if($data->customize) {
				throw new TemporaryRedirectAction(\learning\DatePublicUi::urlList($data->eCommunity).$query);
			}

			$action = new ViewAction($data, '/error:404');
			$action->setStatusCode(404);
			throw $action;

		}

		throw new TemporaryRedirectAction(\structure\StructureUi::getObserverEntries('getCommunityPublicUrl', $data->eCommunity)['quotation'].$query);

	});
?>
