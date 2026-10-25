<?php
/**
 * Les interrupteurs de la page Fonctionnalités. Les étapes `production.producer` et `hasHr.production` lisent le
 * type et les drapeaux de la structure, qu'un délégué ne garantit pas au-delà de son id : la structure se relit.
 */
new \structure\FeaturePage()
	->applyElement(function($data, \structure\Feature $e) {
		$e['structure'] = \structure\StructureLib::getById($e['structure']);
	})
	// Une navigation complète et non un `ReloadAction` : la production ajoute ou retire son app du rail, qu'un
	// rechargement ajax ne redessine pas. La RH, plus bas, ne change que des onglets de l'écran.
	->doUpdateProperties('doUpdateProduction', ['production'], fn($data) => throw new RedirectAction(\structure\StructureUi::urlSettingsFeatures($data->e['structure']).'&success=structure\\'.($data->e['production'] ? 'Feature::enabledProduction' : 'Feature::disabledProduction')))
	/**
	 * Posté par la page Fonctionnalités et par l'astuce de l'équipe. La valeur arrive sous `value` et se construit
	 * sous le nom de la colonne : l'analyseur HTML abaisse les noms d'attributs, un `post-hasHr` posterait `hashr`.
	 */
	->write('doUpdateHr', function($data, \structure\Feature $e) {

		$fw = new \FailWatch();

		$e->build(['hasHr'], ['hasHr' => POST('value')]);

		$fw->validate();

		\structure\FeatureLib::update($e, ['hasHr']);

		throw new ReloadAction('structure', $e['hasHr'] ? 'Feature::enabledHr' : 'Feature::disabledHr');

	}, validate: ['canUpdate', 'acceptUpdate'])
	/**
	 * La fermeture du parcours d'un groupe, qui ramène à l'écran d'où on l'a ouvert. Une origine
	 * inconnue est refusée.
	 */
	->doUpdateProperties('doUpdateStart', ['hasStart'], function($data) {

		$origin = POST('origin', \structure\CommunityStartAiUi::ORIGINS, fn() => throw new NotExpectedAction('Origin'));

		throw new RedirectAction(\structure\CommunityStartAiUi::urlFinish($data->e['structure'], $origin));

	});

/**
 * La page Fonctionnalités d'un producteur, ouverte production éteinte : c'est là qu'on la rallume. Une AMAP a la
 * sienne (`amap/configuration`), un groupe aussi (`structure/community`).
 */
new Page()
	->get('manage', function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('id'))->validate('canManage', 'acceptProduction');

		$data->hrSubscription = (
			\structure\StructureSetting::hasSubscriptionPackage() and
			\Clock::date() < \structure\StructureSetting::HR_SUBSCRIPTION_FROM
		);

		throw new ViewAction($data);

	});
?>
