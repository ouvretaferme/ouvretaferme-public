<?php
namespace product;

class VariantLib {

	use \Notifiable;

	/**
	 * Les cases cochées du panneau de duplication : elles désignent les variantes du produit
	 * dupliqué, qui deviennent des sœurs du produit créé, copiées telles quelles.
	 */
	public static function buildFromInput(Product $eProductPrincipal, ?array $variantList): \Collection {

		$cProductVariant = new \Collection();

		if(
			$variantList === NULL or
			$eProductPrincipal['variantOf']->notEmpty()
		) {
			return $cProductVariant;
		}

		$cProductSource = self::getByVariantOf($eProductPrincipal['duplicateOf']);

		// Une case décochée n'est pas postée : la présence de la clé EST la sélection, et
		// la ligne n'apporte aucune valeur à construire.
		foreach(array_keys($variantList) as $id) {

			if($cProductSource->offsetExists($id) === FALSE) {
				Product::fail('variantList.check');
				continue;
			}

			$eProductSourceVariant = $cProductSource[$id];

			// `variantOf` est posé dès la construction, bien que le principal n'ait pas
			// encore d'id : c'est lui qui route la ligne vers le régime de variante.
			// ProductLib::create() le repose ensuite avec l'id.
			$eProductVariant = new Product(
				$eProductSourceVariant->extracts(self::getPropertiesCopy()) + [
					'variantOf' => $eProductPrincipal,
					'duplicateOf' => $eProductSourceVariant,
					'reference' => NULL,
					'status' => $eProductSourceVariant['status']
				]
			);

			$cProductVariant[$id] = $eProductVariant;

		}

		return $cProductVariant;

	}

	// `structure` n'est pas recopiée bien qu'elle soit dans VARIANT_FIXED : `variantOf.check`
	// garantit déjà l'égalité, et l'écraser remplacerait l'élément complet par un identifiant.
	public static function prepareCreate(Product $e): void {

		// Rechargé et non lu sur $e['variantOf'] : variantOf.check n'en hydrate qu'une poignée
		// de propriétés, la copie en demande bien davantage.
		$eProductPrincipal = ProductLib::getById($e['variantOf']['id'], ProductElement::getSelection());

		// La variante naît copie du principal et vit sa vie ensuite : seul un champ que le
		// POST n'a pas construit prend la valeur du principal, le posté prime.
		foreach(Product::VARIANT_COPIED as $property) {

			if($e->offsetExists($property) === FALSE) {
				$e[$property] = $eProductPrincipal[$property];
			}

		}

		foreach(Product::VARIANT_PROPAGATED as $property) {
			$e[$property] = $eProductPrincipal[$property];
		}

		// En prix de famille, le prix s'impose comme un propagé, même posté autrement
		if($eProductPrincipal['priceMode'] === Product::SHARED) {

			foreach(Product::VARIANT_PRICE as $property) {
				$e[$property] = $eProductPrincipal[$property];
			}

		}

		// Lu après l'insertion par ProductLib::create() : les grilles de la famille
		// s'étendent à la ligne qui vient de naître
		$e['priceModePrincipal'] = $eProductPrincipal['priceMode'];

		foreach(Product::VARIANT_FORBIDDEN as $property) {
			$e[$property] = NULL;
		}

		// La référence n'est pas remise à NULL ici : c'est le seul champ propre qu'une création
		// de variante saisit. Une copie ne peut pas la cloner, UNIQUE : buildFromInput() la vide.
		$e['profile'] = $eProductPrincipal['profile'];
		$e['variantOf'] = $eProductPrincipal;
		$e['variantPosition'] = self::getNextPosition($eProductPrincipal);

		// Une variante naît active, une COPIE garde le statut de ce qu'elle copie : réactiver
		// une ligne éteinte serait une décision que le producteur n'a pas prise.
		if($e['duplicateOf']->empty()) {
			$e['status'] = Product::ACTIVE;
		}

		$e['variants'] = 0;
		$e['stock'] = NULL;
		$e['stockStatus'] = Product::INACTIVE;
		$e['stockLast'] = new Stock();
		$e['stockUpdatedAt'] = NULL;

	}

	// Un produit qui se rattache prend les champs inféodés de son nouveau principal — rien
	// d'autre ne les alignerait. Leurs effets de bord (relations, mercuriale) jouent ensuite
	// dans synchronizeDependencies().
	public static function prepareUpdate(Product $e, array &$properties): void {

		if($e['variantOf']->is($e['eProductVariantOfOld'])) {
			return;
		}

		$eProductPrincipal = ProductLib::getById($e['variantOf']['id'], array_merge(['priceMode'], Product::VARIANT_PROPAGATED, Product::VARIANT_PRICE));

		// La recopie change la catégorie sans passer par category.check, où update() prend
		// d'ordinaire l'ancienne valeur.
		$e['oldCategory'] ??= $e['category'];

		$propertiesCopied = Product::VARIANT_PROPAGATED;

		// En prix de famille, le prix appartient à la famille : miroir exact de prepareCreate().
		// Sans cette recopie, la ligne rattachée garde ses propres prix — que l'écran n'affiche
		// plus (la famille montre le prix commun), que ProductLib::update() refuse ensuite
		// d'écrire, et que la vente lit pourtant (selling/lib/Item.l.php, unitPrice).
		if($eProductPrincipal['priceMode'] === Product::SHARED) {
			$propertiesCopied = array_merge($propertiesCopied, Product::VARIANT_PRICE);
		}

		foreach($propertiesCopied as $property) {

			$e[$property] = $eProductPrincipal[$property];

			if(in_array($property, $properties) === FALSE) {
				$properties[] = $property;
			}

		}

	}

	// « Activer les variantes » : le produit devient sa première variante et un principal neuf
	// naît autour de lui. Il garde son id ; le principal n'accepte aucune vente (acceptSell()).
	/**
	 * Commun si les deux lignes portent le même prix sur les deux canaux ET la même unité : un prix
	 * commun entre la botte et le kilo vendrait une botte au prix d'un kilo.
	 */
	public static function getFirstPriceMode(Product $eProductFirst, Product $eProductSecond): string {

		$eProductFirst->expects(['privatePrice', 'proPrice', 'unit']);
		$eProductSecond->expects(['privatePrice', 'proPrice', 'unit']);

		return (
			$eProductFirst['privatePrice'] === $eProductSecond['privatePrice'] and
			$eProductFirst['proPrice'] === $eProductSecond['proPrice'] and
			$eProductFirst['unit']->is($eProductSecond['unit'])
		) ? Product::SHARED : Product::VARIANT;

	}

	/**
	 * Le nom qu'on donne à la famille en ajoutant sa première variante, construit avant qu'elle
	 * naisse : un nom refusé ne laisse aucune famille derrière lui. enable() le donne à la tête.
	 */
	public static function prepareFamilyName(array $input): Product {

		$eProductFamily = new Product();
		$eProductFamily->buildIndex(['name'], $input, 'family', new \PropertyList('create'));

		return $eProductFamily;

	}

	public static function enable(Product $e, string $priceMode = Product::VARIANT, ?string $name = NULL): Product {

		$e->expects(['id', 'structure', 'variantOf', 'variants']);

		if($e->acceptEnableVariants() === FALSE) {
			Product::fail('enableVariants');
			return new Product();
		}

		$eProduct = ProductLib::getById($e['id'], ProductElement::getSelection());

		Product::model()->beginTransaction();

		// La référence reste au produit : la déplacer repointerait les imports CSV vers le
		// principal. Les comptes sont copiés, synchronizeVariantOf() les abandonnant ensuite.
		// Le contenant ne détient aucun stock, le produit activé garde donc son solde, son
		// journal et ses signets.
		$eProductPrincipal = new Product(
			[
				'structure' => $e['structure'],
				'reference' => NULL,
				'status' => $eProduct['status'],
				'priceMode' => $priceMode,
				'stockStatus' => Product::INACTIVE,
				'stock' => NULL,
				'privateAccountNumber' => $eProduct['privateAccountNumber'],
				'proAccountNumber' => $eProduct['proAccountNumber'],
				'variantOf' => new Product(),
				// Le principal naît copie du produit : duplicateOf dit cette provenance, et
				// écarte au passage les comptes par défaut que create() poserait sinon.
				'duplicateOf' => $eProduct
			]
			+ $eProduct->extracts(self::getPropertiesCopy())
		);

		if($name !== NULL) {
			$eProductPrincipal['name'] = $name;
		}

		ProductLib::create($eProductPrincipal);

		// Les grilles de prix par client ne bougent pas : le chemin de lecture cherche la grille
		// sur le produit de la ligne vendue (GridLib::calculate(), whereProduct), posée sur le
		// contenant elle ne s'appliquerait à rien. Les signets de stock non plus.

		// synchronizeVariantOf() avant l'écriture de variantOf : getNextPosition() compterait
		// sinon la ligne elle-même et la première variante naîtrait en position 1.
		$eProduct['variantOf'] = $eProductPrincipal;

		self::synchronizeVariantOf($eProduct, new Product());

		// `variants + 1` vient d'être écrit en SQL sur une ligne qu'on ne détient pas : sans le
		// reporter ici, l'élément garde le 0 que l'insertion y a posé (Module::cbAddProperties()
		// remplit toute propriété absente avec son défaut), et Offer::isFamily() ne reconnaîtrait
		// pas la tête qui va naître au-dessus.
		$eProductPrincipal['variants'] = 1;

		Product::model()
			->select('variantOf')
			->update($eProduct);

		// Le produit garde son id en devenant sa première variante : ses offres de catalogue restent
		// les siennes, il ne leur manque que la famille au-dessus.
		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('enableVariants', $eProductPrincipal);
		}

		Product::model()->commit();

		return $eProductPrincipal;

	}

	// Le geste inverse : la dernière variante redevient le produit qu'elle était et le principal
	// disparaît en dur, n'ayant jamais rien vendu. Les grilles n'ont jamais quitté leur ligne.
	public static function disable(Product $e): Product {

		$e->expects(['id', 'variantOf', 'variants']);

		if($e->acceptDisableVariants() === FALSE) {
			Product::fail('disableVariants');
			return new Product();
		}

		$eProductPrincipal = ProductLib::getById($e['id'], ProductElement::getSelection());
		$eProductVariant = self::getByVariantOf($e)->first();

		Product::model()->beginTransaction();

		$properties = array_merge(
			['variantOf', 'variantPosition'],
			Product::VARIANT_FORBIDDEN
		);

		foreach(Product::VARIANT_FORBIDDEN as $property) {
			$eProductVariant[$property] = $eProductPrincipal[$property];
		}

		$eProductVariant['variantOf'] = new Product();
		$eProductVariant['variantPosition'] = 0;

		Product::model()
			->select($properties)
			->update($eProductVariant);

		self::synchronizeVariantOf($eProductVariant, $eProductPrincipal);

		// Rechargé : le compteur vient de redescendre à zéro, et delete() relit variants.
		// Des variantes supprimées en douceur peuvent rester rattachées : delete() les compte
		// parmi ce qui empêche la disparition franche et passe le principal en DELETED, ce qui
		// laisse leur rattachement valide (la famille ne doit pas se
		// retrouver indissoluble à cause de lignes déjà supprimées).
		ProductLib::delete(ProductLib::getById($e['id']));

		Product::model()->commit();

		return $eProductVariant;

	}

	// Libérer les prix d'une famille en prix commun : la colonne change, chaque variante
	// vit sa vie depuis ses valeurs du moment — elles portent déjà physiquement le prix
	// commun, rien à distribuer.
	public static function separatePrices(Product $e): Product {

		if($e->acceptSeparatePrices() === FALSE) {
			Product::fail('separatePrices');
			return new Product();
		}

		$e['priceMode'] = Product::VARIANT;

		Product::model()
			->select('priceMode')
			->update($e);

		// Le régime décide de ce que la tête d'offre porte : après la bascule elle n'a plus de prix
		// commun à annoncer, et chaque sous-ligne garde le sien.
		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('separatePrices', $e);
		}

		return $e;

	}

	/**
	 * Repli d'affichage pour une famille en priceMode SHARED : un prix
	 * personnalisé y écrit une ligne par variante (GridLib::synchronizeFamily()), toutes au
	 * même prix pour un même client — l'écran n'en montre qu'une, la base ne bouge pas.
	 * Un client dont les lignes DIVERGENT (état qu'aucune écriture normale ne produit,
	 * vérifié : synchronizeProducts() réécrit systématiquement toutes les sœurs à la valeur
	 * qu'on vient de poser, et unifyPrices() exige un choix puis aligne avant de laisser
	 * passer une famille en SHARED) n'est pas replié : masquer un désaccord serait pire que
	 * de le montrer en double.
	 */
	public static function applySharedGridDisplay(\Collection $cGrid): \Collection {

		$cGridByTarget = [];

		foreach($cGrid as $eGrid) {

			$target = $eGrid['customer']->notEmpty() ? 'customer-'.$eGrid['customer']['id'] : 'group-'.$eGrid['group']['id'];

			$cGridByTarget[$target][] = $eGrid;

		}

		$cGridDisplay = new \Collection();

		foreach($cGridByTarget as $cGridTarget) {

			$eGridFirst = $cGridTarget[0];
			$diverges = FALSE;

			foreach($cGridTarget as $eGrid) {

				if(
					$eGrid['price'] !== $eGridFirst['price'] or
					$eGrid['priceInitial'] !== $eGridFirst['priceInitial']
				) {
					$diverges = TRUE;
					break;
				}

			}

			if($diverges) {
				foreach($cGridTarget as $eGrid) {
					$cGridDisplay[$eGrid['id']] = $eGrid;
				}
			} else {
				$cGridDisplay[$eGridFirst['id']] = $eGridFirst;
			}

		}

		return $cGridDisplay;

	}

	/**
	 * Ce que l'unification va faire, LU AVANT d'écrire : le panneau annonce des valeurs, pas
	 * un « des données seront perdues ». Rend ['variants', 'values', 'properties',
	 * 'cProductVariant' dont chaque ligne porte `changes` (from, to, changed), 'grids' par
	 * client]. Une valeur absente COMPTE : elle ne détruit rien, mais elle ouvre un canal.
	 */
	public static function countUnifyPrices(Product $e): array {

		$e->expects(['id']);

		$eProductPrincipal = ProductLib::getById($e['id'], ProductElement::getSelection());

		$values = 0;
		$propertiesChanged = [];
		$cProductVariant = new \Collection();

		foreach(self::getByVariantOf($eProductPrincipal) as $eProductVariant) {

			$changes = [];
			$valuesVariant = 0;

			foreach(Product::VARIANT_PRICE as $property) {

				$changed = ($eProductVariant[$property] !== $eProductPrincipal[$property]);

				$changes[$property] = [
					'from' => $eProductVariant[$property],
					'to' => $eProductPrincipal[$property],
					'changed' => $changed
				];

				if($changed) {
					$valuesVariant++;
					$propertiesChanged[$property] = TRUE;
				}

			}

			if($valuesVariant > 0) {

				$eProductVariant['changes'] = $changes;
				$cProductVariant[$eProductVariant['id']] = $eProductVariant;

				$values += $valuesVariant;

			}

		}

		return [
			'variants' => $cProductVariant->count(),
			'values' => $values,
			'properties' => array_values(array_intersect(Product::VARIANT_PRICE, array_keys($propertiesChanged))),
			'cProductVariant' => $cProductVariant,
			'grids' => self::getGridDivergences($eProductPrincipal)
		];

	}

	/**
	 * Les prix personnalisés de la famille qui ne s'accordent pas, par client (`customer-<id>`) ou
	 * groupe (`group-<id>`), chacun avec `cGrid`. La vente y répond, et elle est absente de la
	 * version publique, où une famille n'a donc rien à trancher.
	 */
	private static function getGridDivergences(Product $eProductPrincipal): array {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return [];
		}

		return self::notify('getGridDivergences', $eProductPrincipal)[0] ?? [];

	}

	/**
	 * $gridChoices porte, par client en conflit (`customer-<id>` / `group-<id>`),
	 * l'id de la grille dont le prix l'emporte pour toute la famille. Un client dont
	 * un seul prix est en présence n'y figure pas : il n'y a rien à trancher, et le prix
	 * s'étend aux sœurs qui ne l'ont pas, ce que GridLib ferait de toute façon à la première
	 * écriture de cette grille.
	 */
	public static function unifyPrices(Product $e, array $gridChoices = []): Product {

		if($e->acceptUnifyPrices() === FALSE) {
			Product::fail('unifyPrices');
			return new Product();
		}

		Product::model()->beginTransaction();

		// Les divergences sont relues DANS la transaction : un contrôle qui lit hors de la
		// transaction qu'il garde décide sur un instantané que l'écriture ne partage pas.
		$divergences = self::getGridDivergences($e);

		$cGridRetained = new \Collection();

		foreach($divergences as $target => $divergence) {

			if($divergence['cGrid']->count() === 1) {
				$cGridRetained[] = $divergence['cGrid']->first();
				continue;
			}

			$gridId = $gridChoices[$target] ?? NULL;

			// Un prix personnalisé est négocié avec un client nommé : il ne s'écrase pas sur
			// une désignation qu'on ne peut pas rattacher à ce qui a été montré.
			if($gridId === NULL or $divergence['cGrid']->offsetExists($gridId) === FALSE) {

				// commit() et non rollBack() : rien n'a été écrit, et rollBack() remet la pile
				// de transactions du cadriciel à zéro (ModuleModel::rollBack()) au lieu de la
				// décrémenter — il emporterait la transaction d'un appelant qui nous englobe.
				Product::model()->commit();

				Product::fail('unifyPricesGrid');
				return new Product();

			}

			$cGridRetained[] = $divergence['cGrid'][$gridId];

			unset($gridChoices[$target]);

		}

		if($gridChoices !== []) {

			Product::model()->commit();

			Product::fail('unifyPricesGrid');
			return new Product();

		}

		$e['priceMode'] = Product::SHARED;

		Product::model()
			->select('priceMode')
			->update($e);

		// Relu après la bascule : propagate() lit priceMode pour décider si VARIANT_PRICE
		// descend, et l'élément de l'appelant peut venir d'avant.
		self::propagate(ProductLib::getById($e['id'], ProductElement::getSelection()), Product::VARIANT_PRICE);

		// Le régime décide de ce que la tête d'offre porte : après la bascule elle tient le prix
		// commun de sa famille, qu'elle ne portait pas avant. Après la bascule aussi pour les prix
		// personnalisés retenus : GridLib::getPriceFamily() ne reconnaît une famille de prix qu'en
		// priceMode SHARED, la synchronisation ne trouverait aucune sœur avant.
		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('unifyPrices', $e, $cGridRetained);
		}

		Product::model()->commit();

		return $e;

	}

	// Cette méthode est un pas de ProductLib::update() : la rappeler d'ici est un cycle, et il
	// ne s'arrête pas — le rattachement y paraît changer à chaque tour.
	// Les deux compteurs s'écrivent en SQL sur une ligne qu'on ne détient pas : `variants + 1`
	// est atomique, charger le principal pour lui poser une valeur en ferait un
	// lire-modifier-écrire.
	public static function synchronizeVariantOf(Product $e, Product $eProductVariantOfOld): void {

		if($eProductVariantOfOld->notEmpty()) {

			Product::model()
				->whereId($eProductVariantOfOld)
				->update([
					'variants' => new \Sql('variants - 1')
				]);

			if(\structure\StructureSetting::hasCommercialisationApp()) {
				self::notify('leaveFamily', $e, $eProductVariantOfOld);
			}

		}

		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('synchronizeVariantOf', $e);
		}

		if($e['variantOf']->notEmpty()) {

			Product::model()
				->whereId($e['variantOf'])
				->update([
					'variants' => new \Sql('variants + 1')
				]);

			$e['variantPosition'] = self::getNextPosition($e['variantOf']);

			// Les comptes d'une variante sont ceux du produit principal : la ligne qui vient
			// d'en devenir une abandonne les siens.
			foreach(Product::VARIANT_FORBIDDEN as $property) {
				$e[$property] = NULL;
			}

			Product::model()
				->select(array_merge(['variantPosition'], Product::VARIANT_FORBIDDEN))
				->update($e);

		}

	}

	// Seuls les champs inféodés descendent : une variante qui garderait l'ancien taux
	// facturerait faux. Écriture ORM directe et volontaire, comme OfferLib::setAvailable() :
	// la propagation ne rejoue pas les contrôles du principal, mais bien ses effets de bord.
	public static function propagate(Product $e, array $properties): void {

		if($e['variants'] === 0) {
			return;
		}

		$e->expects(['priceMode']);

		// `status` descend, mais hors de Product::VARIANT_PROPAGATED : la constante gouverne
		// aussi ce que ProductLib::update() retire des propriétés d'une variante, et l'y mettre
		// retirerait à une variante le droit d'être allumée ou éteinte seule (D10, validé le
		// 29/08 : basculer la ligne de tête bascule ses variantes).
		$propertiesShared = array_merge(Product::VARIANT_PROPAGATED, ['status']);

		// En prix de famille, le prix descend comme un propagé — et ses effets de bord
		// (offres catalogue en priceSource PRODUCT) rejouent pour chaque variante
		if($e['priceMode'] === Product::SHARED) {
			$propertiesShared = array_merge($propertiesShared, Product::VARIANT_PRICE);
		}

		$propertiesPropagated = array_values(array_intersect($propertiesShared, $properties));

		if($propertiesPropagated === []) {
			return;
		}

		// Lues avant les écritures : les effets de bord comparent l'ancienne catégorie à la
		// nouvelle, l'ancienne n'existe plus une fois la propagation passée.
		$cProductVariant = self::getByVariantOf($e);

		// Un tableau et non $e : les lignes écrites sont les variantes, et passer l'élément
		// ajouterait « AND id = <principal> » à la condition (Module.php:3989).
		// Le filtre de statut n'est pas cosmétique : ProductLib::delete() est une suppression
		// douce qui conserve `variantOf`, et propager `status` sans lui remettrait une variante
		// supprimée au catalogue.
		Product::model()
			->whereVariantOf($e)
			->whereStatus('IN', Product::getManipulable())
			->update($e->extracts($propertiesPropagated));

		foreach($cProductVariant as $eProductVariant) {

			$eProductVariant['oldCategory'] = $eProductVariant['category'];

			foreach($propertiesPropagated as $property) {
				$eProductVariant[$property] = $e[$property];
			}

			ProductLib::synchronizeDependencies($eProductVariant, $propertiesPropagated);

		}

	}

	/**
	 * Ce que le panneau de rattachement propose : les produits de la même structure et du même
	 * profil qui ne sont pas déjà des variantes, la famille elle-même exclue.
	 *
	 * Les contenants restent dans la liste, l'UI les rend non sélectionnables : les retirer
	 * ferait chercher en vain un produit qu'on voit sur son écran. Ce filtre est un confort,
	 * la porte est `variantOf.check`, qui repose les mêmes questions à l'écriture.
	 */
	public static function getAttachableFromQuery(Product $eProductPrincipal, ?string $query): \Collection {

		$eProductPrincipal->expects(['id', 'structure', 'profile']);

		if($query) {
			Product::model()->whereName('LIKE', '%'.$query.'%');
		}

		return Product::model()
			->select(Product::getSelection())
			->whereStructure($eProductPrincipal['structure'])
			->whereProfile($eProductPrincipal['profile'])
			->whereVariantOf(NULL)
			->whereId('!=', $eProductPrincipal)
			->whereStatus('IN', Product::getManipulable())
			->sort(['name' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(0, 30);

	}

	public static function getByVariantOf(Product $eProductPrincipal, ?array $properties = NULL): \Collection {

		if($eProductPrincipal->empty()) {
			return new \Collection();
		}

		return Product::model()
			->select($properties ?? Product::getSelection())
			->whereVariantOf($eProductPrincipal)
			->whereStatus('IN', Product::getManipulable())
			->sort(['variantPosition' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

	}

	/**
	 * Renumérotée en entier de 0 à n-1 et non déplacée ligne à ligne : l'index
	 * (variantOf, variantPosition) n'est pas unique, deux lignes peuvent porter la même
	 * position et getByVariantOf() les départage alors par leur id.
	 */
	public static function updatePositions(Product $eProductPrincipal, array $ids): void {

		if($eProductPrincipal->acceptUpdatePositions() === FALSE) {
			Product::fail('updatePositions');
			return;
		}

		$cProductVariant = self::getByVariantOf($eProductPrincipal, ['id', 'variantPosition']);

		if(
			count($ids) !== $cProductVariant->count() or
			count(array_unique($ids)) !== count($ids)
		) {
			Product::fail('updatePositionsIncomplete');
			return;
		}

		foreach($ids as $id) {

			if($cProductVariant->offsetExists($id) === FALSE) {
				Product::fail('updatePositionsIncomplete');
				return;
			}

		}

		Product::model()->beginTransaction();

		foreach(array_values($ids) as $position => $id) {

			$eProductVariant = $cProductVariant[$id];
			$eProductVariant['variantPosition'] = $position;

			Product::model()
				->select('variantPosition')
				->update($eProductVariant);

		}

		Product::model()->commit();

	}

	private static function getNextPosition(Product $eProductPrincipal): int {

		$eProductPosition = Product::model()
			->select([
				'position' => new \Sql('MAX(variantPosition)', 'int')
			])
			->whereVariantOf($eProductPrincipal)
			->get();

		if(
			$eProductPosition->empty() or
			$eProductPosition['position'] === NULL
		) {
			return 0;
		} else {
			return $eProductPosition['position'] + 1;
		}

	}

	private static function getPropertiesCopy(): array {

		return array_merge(
			['structure', 'profile'],
			Product::VARIANT_PROPAGATED,
			Product::VARIANT_COPIED
		);

	}

}
?>
