<?php
/**
 * La liste du sélecteur de structure, chargée à l'ouverture plutôt que rendue avec chaque page :
 * un compte de cent structures pesait sur toutes les pages qu'il ouvrait, pour une liste ouverte rarement.
 *
 * Tout ce qu'elle montre est déjà en mémoire : `Page.l.php` charge les structures du compte, et
 * `MemberLib::getOnline()` ses adhésions, avec la date de la dernière visite. Aucune requête de plus, et
 * aucune base de structure ouverte, d'où une page simple plutôt que `StructurePage`, dont `load()` lirait
 * la structure une seconde fois.
 */
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->get('index', function($data) {

		$structureId = GET('structure', 'int');

		$data->eStructure = new \structure\Structure();

		// La structure ouverte se cherche parmi celles du compte, sur les deux niveaux de la collection
		// (type, puis id) : un identifiant posté hors du compte ne désigne rien ici.
		foreach($data->ccStructureUser as $cStructure) {

			if($cStructure->offsetExists($structureId)) {
				$data->eStructure = $cStructure[$structureId];
			}

		}

		if($data->eStructure->empty()) {
			throw new NotExpectedAction('Structure '.$structureId.' outside the account');
		}

		// Les univers que le gabarit peut tenir pour courants, et eux seuls : la section choisit l'adresse
		// de chaque entrée.
		$section = GET('section');

		if($section === '') {
			$section = NULL;
		} else if(in_array($section, \dashboard\DashboardSetting::SECTIONS, TRUE) === FALSE) {
			throw new NotExpectedAction('Section '.$section);
		}

		// Une structure sans univers ouvert à ce compte n'a pas d'adresse : elle ne s'affiche pas, et ne
		// compte pas dans les nombres des replis.
		$data->cStructureOwn = new \Collection();
		$data->cStructureCommunity = new \Collection();

		foreach($data->ccStructureUser as $cStructure) {

			foreach($cStructure as $eStructure) {

				$url = \structure\StructureUi::getMenuUrl($eStructure, $section);

				if($url === NULL) {
					continue;
				}

				$eStructure['menuUrl'] = $url;

				if($eStructure->isCommunity()) {
					$data->cStructureCommunity[$eStructure['id']] = $eStructure;
				} else {
					$data->cStructureOwn[$eStructure['id']] = $eStructure;
				}

			}

		}

		// Le lien vers l'accueil du compte ne s'offre qu'à qui a plusieurs structures (décidé le
		// 2026-10-15), comptées comme les replis : une structure sans adresse ne compte pas.
		$data->home = ($data->cStructureOwn->count() + $data->cStructureCommunity->count() > 1);

		$data->search = ($data->nStructureUser > \structure\SelectorAiUi::SEARCH);

		if($data->search) {

			// Les récentes se prennent parmi les structures qui ont une adresse, sur la même forme à deux
			// niveaux que `ccStructureUser`.
			$data->cStructureRecent = \structure\MemberLib::getRecent($data->eStructure, new \Collection([$data->cStructureOwn, $data->cStructureCommunity]), \structure\SelectorAiUi::RECENT);

		} else {
			$data->cStructureRecent = new \Collection();
		}

		throw new ViewAction($data);

	});
?>
