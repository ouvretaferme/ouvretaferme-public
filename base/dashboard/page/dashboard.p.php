<?php
/**
 * L'accueil d'un membre dans une structure, la route qui remplit l'emplacement d'un bloc lourd, les
 * deux qui remplissent le groupe Bureau du menu (« Du nouveau », puis la météo), celle de la page
 * « Du nouveau » et celle du panneau « Choisir » d'un bloc.
 *
 * Les six routes ne lisent qu'en GET et n'écrivent rien : l'accueil est la page où l'on arrive, elle
 * ne peut pas créer ce qu'elle affiche. Toutes s'ouvrent à `canRead()`, qui est plus large que le
 * membre : il couvre aussi `canSchool()` et le partage de communauté, si bien que
 * `/ferme/<école>/accueil` répond à tout utilisateur connecté. L'écran ne montre de lui-même que le jour
 * et la semaine ; **tout le reste est porté par la déclaration de chaque bloc**, dont le `right`
 * vaut `canRead` par défaut : un bloc qui n'est pas pour tout le monde nomme son droit.
 *
 * L'accueil **rend** l'écran, il ne redirige jamais : une redirection vers un écran qui renverrait un
 * jour vers l'accueil ferait tourner en rond une structure neuve.
 */
new \structure\StructurePage()
	->read('/ferme/{id}/accueil', function($data) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isEnabled($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Dashboard is closed for this structure');
		}

		$data->today = \dashboard\DashboardLib::getToday();

		$data->blocks = \dashboard\DashboardLib::load($data->eStructure);
		// Le groupe Bureau du menu se rend ici avec « Du nouveau » et la météo, sans aller-retour : c'est
		// l'écran où on les regarde. La météo se lit avec les emplacements du tableau, en un seul appel,
		// qui ne lit qu'une fois les réglages du membre.
		$homeSlots = [\dashboard\DashboardSetting::WEATHER => TRUE];

		$contributions = \dashboard\DashboardLib::loadContributions($data->eStructure, slots: [\dashboard\DashboardSetting::WEATHER, \dashboard\DashboardSetting::FIGURE, \dashboard\DashboardSetting::BILAN, \dashboard\DashboardSetting::QUEUE]);

		$data->contributions = array_diff_key($contributions, $homeSlots);
		$data->home = array_intersect_key($contributions, $homeSlots);
		$data->hasNews = \dashboard\DashboardLib::hasNews($data->eStructure);

		if(\dashboard\DashboardSetting::isTeam($data->eStructure)) {
			$data->team = \dashboard\DashboardLib::getTeam($data->eStructure, $data->eUserOnline, $data->hasAvatar, $data->eStructure->canPlanningLimited());
		} else {
			$data->team = [];
		}

		throw new ViewAction($data);

	})
	->read('/ferme/{id}/accueil/bloc/{name}', function($data) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isEnabled($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Dashboard is closed for this structure');
		}

		$data->block = GET('name');

		// L'inventaire des blocs est le même pour tout le parc : le refuser ici ne dit rien de cette
		// structure. Ce qu'elle voit, elle, se règle plus bas, par le retrait du nœud.
		if(\dashboard\DashboardLib::hasBlock($data->block) === FALSE) {
			throw new NotExistsAction('Unknown block');
		}

		// Un bloc que cette structure ne voit pas et un bloc sans rien à dire rendent tous deux NULL, et
		// la vue retire alors le nœud : un 404 ferait tomber la réponse dans `Ajax.Query.ko()`, qui
		// laisserait la carte titrée vide à l'écran et un bandeau d'erreur plein écran en dev.
		// Ce sur quoi le bloc se centre, transporté sans être interprété : le socle ne sait pas ce que
		// cette chaîne veut dire, et c'est le `load()` du package qui la lit, la vérifie contre ce qu'il
		// offre et retombe sur son défaut. Une valeur inventée par un tiers ne peut donc rien ouvrir que
		// le bloc n'ouvre déjà. Absent, il vaut `NULL` et non `''` : la clé de cache n'a de suffixe que pour
		// un centrage (`getCacheKey()`), et qui l'oublie la reconstruit sans.
		//
		// `setting` vient de l'emplacement que l'accueil en réglage a posé : le bloc revient alors avec
		// ses contrôles, et en cadre titré plutôt que retiré quand il n'a rien à dire.
		$data->setting = GET('setting', 'bool');
		$data->entry = \dashboard\DashboardLib::loadBlock($data->eStructure, $data->block, GET('focus', '?string'), $data->setting);

		throw new ViewAction($data);

	})
	/**
	 * « Du nouveau » dans le groupe Bureau du menu, qu'un écran qui ne lit pas les nouvelles demande au
	 * premier passage sur l'univers (`pilotage.js`) : le gabarit rend le panneau sur chaque page de
	 * structure, et lire le blog à chaque page le ferait payer à tous les écrans. Même barrière que
	 * l'onglet qui l'ouvre (`StructureTemplate::hasHome()`).
	 */
	->read('/ferme/{id}/accueil/pilotage', function($data) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Pilotage is closed for this structure');
		}

		$data->hasNews = \dashboard\DashboardLib::hasNews($data->eStructure);

		throw new ViewAction($data);

	})
	/**
	 * La météo du groupe Bureau, au même régime que « Du nouveau » et pour la même raison : la
	 * prévision coûte des requêtes que tous les écrans paieraient. Une route à
	 * part et non la précédente, parce que la page « Du nouveau » rend les nouvelles sans la météo : une
	 * route commune relirait le blog pour rien.
	 */
	->read('/ferme/{id}/accueil/menu', function($data) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Home is closed for this structure');
		}

		$data->home = \dashboard\DashboardLib::loadHome($data->eStructure);

		throw new ViewAction($data);

	})
	/**
	 * La page « Du nouveau », que l'entrée du groupe Bureau ouvre : même barrière que l'onglet qui la
	 * porte. L'entrée ne paraît que s'il y a une nouvelle, mais l'adresse se garde et se recharge : le
	 * blog qui se tait entre-temps laisse donc une page vide, pas un refus.
	 */
	->read('/ferme/{id}/accueil/nouveau', function($data) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Pilotage is closed for this structure');
		}

		$data->news = \dashboard\DashboardLib::loadNews($data->eStructure);
		$data->hasNews = ($data->news !== []);
		$data->newsUrls = \dashboard\DashboardLib::loadNewsUrls($data->eStructure);

		throw new ViewAction($data);

	})
	/**
	 * Le panneau du « Choisir » d'un bloc. Il ne fait que lire : le choix s'écrit par une route du
	 * package qui apporte le bloc, seul à savoir ce qu'il règle et où il le range.
	 */
	->read('/ferme/{id}/accueil/choisir/{name}', function($data) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isEnabled($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Dashboard is closed for this structure');
		}

		$data->block = GET('name');
		$data->choice = \dashboard\DashboardLib::loadChoice($data->eStructure, $data->block);

		if($data->choice === NULL) {
			throw new NotExpectedAction('Nothing to choose');
		}

		throw new ViewAction($data);

	});
?>
