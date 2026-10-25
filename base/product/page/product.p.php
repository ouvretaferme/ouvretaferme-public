<?php
new \product\ProductPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validateVerified();

		return new \product\Product([
			'structure' => $data->eStructure,
			'status' => \product\Product::ACTIVE,
			'variantOf' => new \product\Product()
		]);

	})
	->create(function($data) {

		// Ouverte depuis une recherche de produits, la création rend le produit au champ qui la cherchait,
		// et ne propose pas les profils que cette recherche écarte.
		$data->autocomplete = GET('autocomplete', '?string');
		$data->profilesExcluded = GET('withComposition', 'bool', TRUE) ? [] : [\product\Product::COMPOSITION];

		$data->e['cCategory'] = \product\CategoryLib::getByStructure($data->e['structure']);
		$data->e['cUnit'] = \product\UnitLib::getByStructure($data->e['structure']);
		$data->e['cProductVariant'] = new Collection();

		if(get_exists('from')) {

			$eProductDuplicate = \product\ProductLib::getById(GET('from'))
				->validateProperty('structure', $data->eStructure)
				->validate('acceptDuplicate');

			if($eProductDuplicate['variantOf']->notEmpty()) {
				\structure\StructureLib::validateFeature($data->eStructure, \structure\Structure::FEATURE_VARIANTS);
			}

			$data->e->merge($eProductDuplicate);

			$data->e['status'] = \product\Product::ACTIVE;
			$data->e['reference'] = NULL;
			$data->e['duplicateOf'] = $eProductDuplicate;

			if(
				$eProductDuplicate['variants'] > 0 and
				$data->eStructure->acceptFeature(\structure\Structure::FEATURE_VARIANTS)
			) {
				$data->e['cProductVariant'] = \product\VariantLib::getByVariantOf($eProductDuplicate);
			}

		} else if(get_exists('variantOf')) {

			$eProductPrincipal = \product\ProductLib::getById(GET('variantOf'))
				->validateProperty('structure', $data->eStructure)
				->validate('canWrite');

			\structure\StructureLib::validateFeature($data->eStructure, \structure\Structure::FEATURE_VARIANTS);

			if($eProductPrincipal['variantOf']->notEmpty()) {
				throw new NotExpectedAction('Product #'.$eProductPrincipal['id'].' is a variant');
			}

			if(
				$eProductPrincipal['variants'] === 0 and
				$eProductPrincipal->acceptEnableVariants() === FALSE
			) {
				throw new NotExpectedAction('Product #'.$eProductPrincipal['id'].' cannot hold variants');
			}

			$data->e->merge($eProductPrincipal);

			unset($data->e['id']);

			$data->e['variantOf'] = $eProductPrincipal;

			// Rechargée pour elle-même : isMembership() lit la structure, pas une propriété du produit.
			$data->e['isFreeTrial'] = (
				$eProductPrincipal['variants'] === 0 and
				FEATURE_FREE and
				\structure\StructureLib::getById($data->eStructure)->isMembership() === FALSE
			);
			$data->e['status'] = \product\Product::ACTIVE;
			$data->e['name'] = NULL;
			$data->e['reference'] = NULL;
			$data->e['duplicateOf'] = new \product\Product();

		} else {

			$data->e->merge([
				'profile' => \product\Product::GET('profile', 'profile'),
				'name' => GET('name', '?string'),
				'organic' => $data->eStructure['organic'],
				'qualities' => $data->eStructure['qualities'],
				'vat' => \product\ProductLib::getDefaultVat($data->eStructure),
				'private' => TRUE,
				'pro' => TRUE,
				'unit' => new \product\Unit(),
				'duplicateOf' => new \product\Product()
			]);

			switch($data->e['profile']) {

				case \product\Product::PLANT :
					$data->e['unit'] = \product\UnitLib::getByFqn('kg');
					break;

				case \product\Product::PRODUCT :
				case \product\Product::FOOD :
					$data->e['unit'] = \product\UnitLib::getByFqn('unit');
					break;

				case \product\Product::COMPOSITION :
					$data->e['unit'] = \product\UnitLib::getByFqn('unit');
					$data->e['private'] = FALSE;
					$data->e['pro'] = FALSE;
					break;

				case \product\Product::SERVICE :
					$data->e['vat'] = \product\ProductSetting::getStandardVat($data->eStructure);
					$data->e['organic'] = \product\Product::NO;
					$data->e['qualities'] = new Set();
					break;

			}

			if($data->e['profile'] === \product\Product::PLANT) {

				if(get_exists('plant')) {
					$data->e['plant'] = \plant\PlantLib::getById(GET('plant'))->validateProperty('structure', $data->eStructure);
					$data->e['name'] = $data->e['plant']['name'];
				}

				if(get_exists('unit')) {
					$data->e['unit'] = \product\UnitLib::getById(GET('unit'))->validateProperty('structure', $data->eStructure);
				}

				if(get_exists('privatePrice')) {
					$data->e['privatePrice'] = GET('privatePrice', 'float');
				}

				if(get_exists('proPrice')) {
					$data->e['proPrice'] = GET('proPrice', 'float');
				}

			}

		}

		throw new ViewAction($data);

	})
	->doCreate(function($data) {

		if(post_exists('autocomplete')) {

			$data->autocomplete = POST('autocomplete');
			$data->e = \product\ProductLib::getById($data->e['id']);

			throw new ViewAction($data);

		}

		if($data->e['variantOf']->notEmpty()) {
			throw new RedirectAction(\product\ProductUi::url($data->e['variantOf']).'?tab='.\product\ProductUi::TAB_VARIANTS.'&success=product\\Product::variantCreated');
		}

		$category = $data->e['category']->empty() ? '' : $data->e['category']['id'];
		throw new RedirectAction(\product\ProductUi::url($data->e).'?category='.$category.'&success=product\\Product::created');
	});

new \product\ProductPage()
	->applyElement(function($data, \product\Product $eProduct) {

		$eProduct['cCategory'] = \product\CategoryLib::getByStructure($eProduct['structure']);
		$eProduct['cUnit'] = \product\UnitLib::getByStructure($eProduct['structure']);

		if($eProduct['structure']->hasAccounting()) {

			\structure\StructureLib::connectDatabase($eProduct['structure']);

			if($eProduct['proAccountNumber'] !== NULL) {
				$eProduct['proAccount'] = \account\AccountLib::askByClass($eProduct['proAccountNumber']);
			}
			if($eProduct['privateAccountNumber'] !== NULL) {
				$eProduct['privateAccount'] = \account\AccountLib::askByClass($eProduct['privateAccountNumber']);
			}

			$eProduct['propertiesBreakdown'] = \account\SaleBreakdownAiLib::getRefusedProperties($eProduct['structure'], $eProduct->extracts(['proAccountNumber', 'privateAccountNumber']));

		}

	})
	->update(function($data) {

		if($data->e['variants'] > 0) {
			$data->e['cProductVariant'] = \product\VariantLib::getByVariantOf($data->e);
		} else {
			$data->e['cProductVariant'] = new Collection();
		}

		throw new ViewAction($data);

	})
	->doUpdate(fn($data) => throw new ReloadAction('product', 'Product::updated'));

new \product\ProductPage()
	->read('/produit/{id}', function($data) {

		// La fiche d'une variante n'existe plus pour elle-même : elle
		// redirige vers l'onglet Variantes de sa famille, qui couvre déjà tout ce qu'elle
		// montrait. Temporaire et non permanente (RedirectAction est un 301 par défaut) :
		// « Transformer en produit » détache une variante et lui rend une fiche propre, une
		// redirection permanente resterait mise en cache par le navigateur après ce geste.
		if($data->e['variantOf']->notEmpty()) {

			// ProductUi::url() rend déjà l'adresse de la famille pour une variante : ce
			// détour par le principal explicite n'est là que pour ajouter &variant=, qui
			// nomme la variante demandée dans le bandeau de la vue.
			throw new RedirectAction(
				\product\ProductUi::url($data->e).'&variant='.$data->e['id'],
				httpStatus: 'HTTP/1.1 302 Found'
			);

		}

		// Ce que la redirection ci-dessus a demandé : le nom de la variante visée, pour le
		// bandeau de la vue. Rattachement vérifié contre CE principal, un id de variante
		// arbitraire dans l'URL ne doit pas faire nommer un produit d'une autre famille.
		$data->eProductVariantRequested = new \product\Product();

		if(get_exists('variant')) {

			$eProductVariantRequested = \product\ProductLib::getById(GET('variant'), ['id', 'name', 'variety', 'frozen', 'variants', 'variantOf']);

			if(
				$eProductVariantRequested['variantOf']->notEmpty() and
				$eProductVariantRequested['variantOf']['id'] === $data->e['id']
			) {
				$data->eProductVariantRequested = $eProductVariantRequested;
			}

		}

		if($data->e['category']->notEmpty()) {
			$data->e['category'] = \product\CategoryLib::getById($data->e['category']);
		}

		$data->eStructure = \structure\StructureLib::getById($data->e['structure']);

		if($data->e['variants'] > 0) {
			$data->e['cProductVariant'] = \product\VariantLib::getByVariantOf($data->e);
			$data->e['cUnit'] = \product\UnitLib::getByStructure($data->e['structure']);
		}

		\product\ProductLib::loadDisplay($data->eStructure, $data->e);

		if($data->e['profile'] === \product\Product::PLANT) {
			$data->tip = \structure\TipLib::pickOne($data->eUserOnline, 'selling-photo', closedByUser: FALSE);
		}

		throw new ViewAction($data);

	})
	->doUpdateProperties('doUpdateStatus', ['status'], function($data) {

		if($data->e['variants'] > 0) {
			$data->cProductVariant = \product\VariantLib::getByVariantOf($data->e, ['id', 'status']);
		} else {
			$data->cProductVariant = new Collection();
		}

		throw new ViewAction($data);

	})
	->read('attachVariant', function($data) {

		\structure\StructureLib::validateFeature($data->e['structure'], \structure\Structure::FEATURE_VARIANTS);

		throw new ViewAction($data);

	}, validate: ['canWrite', 'acceptAttachVariant'])
	->doUpdateProperties('doUpdateVariantOf', ['variantOf'], function($data) {

		if($data->e['variantOf']->notEmpty()) {
			throw new RedirectAction(\product\ProductUi::url($data->e['variantOf']).'?tab='.\product\ProductUi::TAB_VARIANTS.'&success=product\\Product::variantAttached');
		}

		throw new RedirectAction(\product\ProductUi::url($data->e).'?success=product\\Product::variantDetached');

	})
	->write('doCreateFirstVariant', function($data) {

		\structure\StructureLib::validateFeature($data->e['structure'], \structure\Structure::FEATURE_VARIANTS);

		$fw = new FailWatch();

		// ⚠️ Tout se construit et se contrôle AVANT que la famille naisse : activer d'abord
		// laisserait une famille derrière chaque formulaire refusé, que le même panneau ne saurait
		// plus reprendre. Seul l'onglet choisi poste ses champs (fieldset désactivé), et
		// `variantSource` dit lequel.
		$eProductFamily = \product\VariantLib::prepareFamilyName($_POST);

		$attach = (POST('variantSource') === 'attach');

		if($attach) {

			// La question est celle de acceptVariantOf(), posée contre la famille telle qu'elle va
			// naître ; un produit déjà variante est refusé à part, acceptVariantOf() ne pesant que
			// la cible.
			$eProductVariant = \product\ProductLib::getById(POST('product', 'int'));

			$eProductPrincipal = new \product\Product([
				'structure' => $data->e['structure'],
				'profile' => $data->e['profile'],
				'variantOf' => new \product\Product(),
				'variants' => 1,
				'status' => $data->e['status']
			]);

			if(
				$eProductVariant->empty() or
				$eProductVariant['variantOf']->notEmpty() or
				in_array($eProductVariant['status'], \product\Product::getManipulable()) === FALSE or
				$eProductVariant->acceptVariantOf($eProductPrincipal) === FALSE
			) {
				\product\Product::fail('attachFirst.product');
			}

		} else {

			// `variantOf` est posé et non construit : le produit n'est pas encore un contenant, et
			// `variantOf.check` refuserait le rattachement (une famille ne naît que par enable()).
			// Posé, il gouverne quand même les champs inféodés, dont les callbacks `.raw` le lisent.
			$eProductVariant = new \product\Product([
				'structure' => $data->e['structure'],
				'variantOf' => $data->e
			]);

			// Deux noms dans le formulaire, donc deux champs `name[…]` : la famille et la variante 2.
			$eProductVariant->buildIndex(['name'], $_POST, 'new', new \PropertyList('create'));

			$eProductVariant->build(
				array_values(array_diff(\product\ProductLib::getPropertiesCreate()($eProductVariant), ['variantOf', 'name'])),
				$_POST,
				new \PropertyList('create')
			);

		}

		$fw->validate();

		// Décidé avant enable(), qui prend le régime à la naissance : rien à unifier après coup,
		// prepareCreate() impose déjà le prix de famille.
		$eProductPrincipal = \product\VariantLib::enable(
			$data->e,
			\product\VariantLib::getFirstPriceMode($data->e, $eProductVariant),
			$eProductFamily['name']
		);

		$fw->validate();

		if($attach) {

			// Construit et non posé : variantOf.check pèse le rattachement contre la famille qui vient de naître.
			$eProductVariant->build(['variantOf'], ['variantOf' => $eProductPrincipal['id']], new \PropertyList('update'));

			$fw->validate();

			\product\ProductLib::update($eProductVariant, ['variantOf']);

		} else {

			$eProductVariant['variantOf'] = $eProductPrincipal;

			\product\ProductLib::create($eProductVariant);

		}

		$fw->validate();

		throw new RedirectAction(\product\ProductUi::url($eProductPrincipal).'?tab='.\product\ProductUi::TAB_VARIANTS.'&success=product\\Product::variantsEnabled');

	}, validate: ['canWrite', 'acceptEnableVariants'])
	->write('doSeparatePrices', function($data) {

		$fw = new FailWatch();

		\product\VariantLib::separatePrices($data->e);

		$fw->validate();

		throw new RedirectAction(\product\ProductUi::url($data->e).'?success=product\\Product::pricesSeparated');

	}, validate: ['canWrite', 'acceptSeparatePrices'])
	->read('unifyPrices', function($data) {

		$data->unify = \product\VariantLib::countUnifyPrices($data->e);

		throw new ViewAction($data);

	}, validate: ['canWrite', 'acceptUnifyPrices'])
	->write('doUnifyPrices', function($data) {

		$fw = new FailWatch();

		// Le panneau ne poste que des désignations parmi les grilles que le serveur a
		// lui-même énumérées : leur légitimité est recontrôlée par unifyPrices(), dans la
		// transaction, contre les divergences du moment.
		$gridChoices = array_map('intval', POST('grid', 'array', []));

		\product\VariantLib::unifyPrices($data->e, $gridChoices);

		$fw->validate();

		throw new RedirectAction(\product\ProductUi::url($data->e).'?tab='.\product\ProductUi::TAB_GRID.'&success=product\\Product::pricesUnified');

	}, validate: ['canWrite', 'acceptUnifyPrices'])
	->write('doUpdatePositions', function($data) {

		$fw = new FailWatch();

		$positions = json_decode(POST('positions'), TRUE);

		if(is_array($positions) === FALSE) {
			throw new NotExpectedAction('Positions');
		}

		\product\VariantLib::updatePositions($data->e, array_map('intval', $positions));

		$fw->validate();

		throw new VoidAction();

	}, validate: ['canWrite', 'acceptUpdatePositions'])
	->write('doDisableVariants', function($data) {

		$fw = new FailWatch();

		$eProduct = \product\VariantLib::disable($data->e);

		$fw->validate();

		throw new RedirectAction(\product\ProductUi::url($eProduct).'?success=product\\Product::variantsDisabled');

	}, validate: ['canWrite', 'acceptDisableVariants'])
	// La suppression rend la main là où le geste a été fait, et l'origine voyage plutôt qu'elle
	// ne se devine (CLAUDE.md) : depuis la grille des variantes d'une fiche, on y revient — la
	// famille existe toujours, elle a juste une ligne de moins. Ailleurs (liste des produits,
	// en-tête d'une fiche), la page qu'on quitte est celle du produit supprimé, d'où la liste.
	->doDelete(function($data) {

		if(
			POST('origin') === 'variants' and
			$data->e['variantOf']->notEmpty()
		) {
			throw new RedirectAction(\product\ProductUi::url($data->e['variantOf']).'?tab='.\product\ProductUi::TAB_VARIANTS.'&success=product\\Product::variantDeleted');
		}

		throw new RedirectAction(\structure\StructureUi::urlSellingProducts($data->e['structure']).'?success=product\\Product::deleted');

	});

new \product\ProductPage()
	->applyElement(function($data, \product\Product $eProduct) {

		if($eProduct['privatePriceInitial'] !== NULL) {
			$eProduct['privatePriceDiscount'] = $eProduct['privatePrice'];
		}
		if($eProduct['proPriceInitial'] !== NULL) {
			$eProduct['proPriceDiscount'] = $eProduct['proPrice'];
		}

		if($eProduct['structure']->hasAccounting()) {

			\structure\StructureLib::connectDatabase($eProduct['structure']);

			$eProduct['proAccount'] = $eProduct['proAccountNumber'] !== NULL ? \account\AccountLib::askByClass($eProduct['proAccountNumber']) : new \account\Account();
			$eProduct['privateAccount'] = $eProduct['privateAccountNumber'] !== NULL ? \account\AccountLib::askByClass($eProduct['privateAccountNumber']) : new \account\Account();

			$eProduct['propertiesBreakdown'] = \account\SaleBreakdownAiLib::getRefusedProperties($eProduct['structure'], $eProduct->extracts(['proAccountNumber', 'privateAccountNumber']));
		}

	})
	->quick([
		'name',
		'reference',
		'unit',
		'privatePrice' => ['privatePrice', 'privatePriceDiscount'],
		'privateStep',
		'proPrice' => ['proPrice', 'proPriceDiscount'],
		'proPackaging',
		'proStep',
		'proAccountNumber', 'privateAccountNumber',
	], [
		'unit' => fn($data) => $data->e['cUnit'] = \product\UnitLib::getByStructure($data->e['structure']),
	], validate: fn(string $property) => match($property) {
		'proAccountNumber', 'privateAccountNumber' => ['canUpdateAccounting', 'acceptUpdate', 'acceptUpdateAccounting'],
		default => ['canUpdate', 'acceptUpdate'],
	});

new \product\ProductPage()
	->applyCollection(function($data, Collection $c) {
		$c->validateProperty('structure', $c->first()['structure']);
	})
	->doUpdateCollectionProperties('doUpdateStatusCollection', ['status'], fn($data) => throw new ReloadAction())
	->doUpdateCollectionProperties('doUpdateCategoryCollection', ['category'], fn($data) => throw new ReloadAction('product', 'Product::categoryUpdated'));

new Page()
	->post('query', function($data) {

		$data->eStructure = \structure\StructureLib::getById(POST('structure', '?int'))->validate('canWrite');;
		$type = POST('type', '?string');
		$stock = POST('stock', '?string');
		$withComposition = POST('withComposition', 'bool', TRUE);
		$exclude = post_exists('exclude') ? explode(',', POST('exclude')) : [];
		$distribution = POST('distribution', [\product\Product::PRODUCTION, \product\Product::RESALE], NULL);
		// Chaîne vide = aucune unité compatible connue : on ne borne pas plutôt que
		// de ne rien proposer du tout.
		$unitIds = POST('unit') ? explode(',', POST('unit')) : NULL;

		// `post_exists` et non la valeur : une liste d'espèces vide est une consigne,
		// pas une absence de consigne — l'appelant demande alors les seuls produits
		// sans espèce. La lire comme « pas de filtre » proposerait tout l'inverse.
		$plantIds = post_exists('plant') ? array_filter(explode(',', POST('plant')), fn($plantId) => $plantId !== '') : NULL;

		$data->cProduct = \product\ProductLib::getFromQuery(POST('query'), $data->eStructure, $type, excludeIds: $exclude, stock: $stock, withComposition: $withComposition, distribution: $distribution, unitIds: $unitIds, plantIds: $plantIds);

		$data->query = POST('query');
		$data->autocomplete = POST('autocomplete', '?string');
		$data->withComposition = $withComposition;

		// Une recherche qui reçoit le produit créé dans son champ ne propose pas d'en créer un qui porte
		// le nom d'un résultat : rien ne distinguerait les deux dans ce champ.
		$data->hasNew = (
			post_exists('new') and
			($data->autocomplete === NULL or \product\ProductLib::hasName($data->cProduct, $data->query) === FALSE)
		);

		// L'intertitre « Produits sans espèce » n'a de sens que face à une liste
		// bornée par l'espèce : c'est ce qui distingue un produit retenu parce qu'il
		// correspond d'un produit retenu faute de pouvoir l'écarter.
		$data->withPlantTitle = ($plantIds !== NULL);

		throw new \ViewAction($data);

	});

new Page()
	->get('updateAccountNumber', function($data) {

		$ids = GET('ids', 'array');

		$data->cProduct = \product\ProductLib::getByIds($ids);

		if($data->cProduct->empty()) {
			throw new NotExpectedAction('Unknown products to update');
		}

		$data->eStructure = $data->cProduct->first()['structure']->validate('canManageAccounting');

		\structure\StructureLib::connectDatabase($data->eStructure);

		$data->cProduct->first()->validate('acceptUpdateAccounting');

		throw new \ViewAction($data);

	});

new \product\ProductPage()
	->applyCollection(function($data, Collection $c) {

		$eStructure = $c->first()['structure']->validate('hasAccounting');
		$c->validateProperty('structure', $eStructure);
		\structure\StructureLib::connectDatabase($eStructure);

	})
	->doUpdateCollectionProperties('doUpdateAccountCollection', ['proAccountNumber', 'privateAccountNumber'], function($data) {

			throw new ReloadAction('product', 'Product::updatedSeveral');
	}, validate: ['canUpdateAccounting', 'acceptUpdate', 'acceptUpdateAccounting'])
;

?>
