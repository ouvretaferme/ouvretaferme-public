<?php
namespace toolbox;

/**
 * Une espèce du plan, depuis son nom ou sa roue crantée dans la grille : le panneau « Régler une
 * espèce » (lot r22e, planche r22, question 1, verdict du 27/09), qui remplace son menu.
 * Son formulaire dit l'état de l'espèce et le règle en un seul enregistrement : l'unité
 * au panier et le poids de cette unité (lot r14, lot L3), le prix (lot M), le pas de vente et la
 * quantité minimale (planche r1), la fréquence (lot L9) ; la conservation se lit, et se règle sur la
 * fiche de l'espèce (lot r9).
 *
 * Le prix est celui de la semaine de référence de l'espèce
 * (`CompositionSpeciesLib::getWithPrices()`), `reference` et `unit` préparés avec l'écran. Il se lit
 * dans le panneau, d'où il vient, et au bout du nom dans la grille (planche r1, « quantité et prix
 * ensemble »), où il n'est que le montant et se règle sur place (lot r21).
 *
 * Aucune mention de la mercuriale générale dans ce projet (verdict sur la planche r22) :
 * un prix qui en vient se dit « prix par défaut », sans en dire l'origine (arbitrage du chef de
 * projet sur r22e, lot r22f).
 */
class CompositionSpeciesUi {

	/**
	 * Le panneau « Régler une espèce » s'ouvre aussi en page complète, par son adresse (un
	 * rechargement, un lien partagé), sans que l'écran des paniers ait chargé composition.js avant
	 * lui : sans ce script, rien ne demande confirmation avant qu'un changement d'unité remplace les
	 * valeurs posées, et le prix, le pas et le minimum ne suivent pas l'unité choisie (lot r22g).
	 */
	public function __construct() {
		\Asset::css('toolbox', 'composition.css');
		\Asset::js('toolbox', 'composition.js');
	}

	/**
	 * Le panneau « Régler une espèce » : le formulaire sur deux colonnes, comme les formulaires du
	 * dépôt. Le nom de l'espèce est dans l'en-tête : les
	 * libellés ne le répètent pas, là où les formulaires rapides du prix, du poids et de la fréquence,
	 * seuls sous la grille, le portent.
	 *
	 * La saison se règle en tête, aux poignées, comme à l'ajout (r61, le 03/10/2026,
	 * « permettre de régler la saisonnalité ici », `CompositionUi::getSeasonField()`).
	 *
	 * Le pas ne se règle que sur une unité qui se coupe : à la pièce ou à la botte, il reste d'une
	 * unité. Une espèce retirée ne règle ni sa saison, ni sa fréquence, ni sa conservation, et ne se
	 * retire pas une seconde fois : son rang reste dans la grille tant qu'une semaine corrigée la sert.
	 *
	 * Le formulaire poste la structure, que la page charge avant l'espèce, et la taille que l'écran
	 * lit ; l'état de l'écran s'ajoute à l'envoi (`composition.js`). Changer d'unité se confirme à
	 * l'envoi quand l'unité cochée efface un réglage tapé (`getUnitLoss()`).
	 *
	 * Ouvert depuis l'onglet « Espèces » (r29, #84, r48a), le panneau réémet `$origin`, qui nomme
	 * l'onglet, à l'enregistrement comme au retrait : la réponse rend alors le rang de l'espèce dans
	 * son tableau, ou le retire.
	 *
	 * Les boutons sont dans le corps, sous le dernier champ : le panneau est court, et le pied ne sert
	 * qu'à un panneau long d'habitude (décidé le 2026-09-29).
	 */
	public function update(CompositionSpecies $eCompositionSpecies, ?int $formula, ?string $origin): \Panel {

		$excluded = ($eCompositionSpecies['status'] === CompositionSpecies::EXCLUDED);

		$form = new \util\FormUi();

		// Pas de tuiles en tête : elles répétaient ce que chaque champ dit déjà en attente ou dessous
		// (r29, #86, « aucun intérêt de rappeler des trucs qui sont déjà dans le formulaire »).
		$h = $form->hidden('id', $eCompositionSpecies);
		$h .= $form->hidden('structure', $eCompositionSpecies['plan']['structure']['id']);
		$h .= $form->hidden('formula', $formula);
		$h .= $form->hidden('origin', $origin);

		// Le nom d'une variante (r76 lot 2) se corrige ici, en tête, là où « Ajouter une espèce » l'a
		// demandé.
		if($eCompositionSpecies['name'] !== NULL) {
			$h .= $form->dynamicGroup($eCompositionSpecies, 'name*');
		}

		// La saison aux poignées (r61), à la place qu'elle a dans « Ajouter une espèce », avant les
		// réglages. Sans astérisque : une saison réglée à la main peut être vide (r30, #93).
		if($excluded === FALSE) {
			$h .= $form->group(s("Saison"), CompositionUi::getSeasonField($eCompositionSpecies['plan'], $eCompositionSpecies['windowWeeks']), ['data-wrapper' => 'windowWeeks']);
		}

		$h .= self::getSettings($form, $eCompositionSpecies, $excluded === FALSE, added: FALSE);

		$buttons = '<div class="flex-justify-space-between">';

			$buttons .= $form->submit(s("Enregistrer"));

			if($excluded === FALSE) {

				$posts = self::setQuickPosts($eCompositionSpecies, $eCompositionSpecies['plan']['structure']['id'], $formula);
				$posts['origin'] = $origin;

				// Le message passe par `attr()`, qui l'encode : le nom y entre tel quel.
				$confirm = s("{plant} sort de tous vos paniers, sauf aux semaines que vous avez corrigées. Voulez-vous continuer ?", ['plant' => $eCompositionSpecies->getLabel()]);

				$buttons .= '<a data-ajax="/toolbox/compositionSpecies:doExclude" post-id="'.$eCompositionSpecies['id'].'" '.CompositionUi::getPosts($posts).' '.attr('data-confirm', $confirm).' class="btn btn-outline-danger">'.s("Retirer de mes cultures").'</a>';

			}

		$buttons .= '</div>';

		$h .= $form->group(content: $buttons);

		return new \Panel(
			id: 'toolbox-composition-species-panel',
			title: s("Régler une espèce"),
			// Le nom de l'espèce du plan et non celui de sa fiche, que l'espèce nue et ses variantes
			// partagent (r76) : sur « Carotte vrac », on sait ce qu'on règle.
			subTitle: '<div class="panel-header-subtitle">'.\plant\PlantUi::getVignette($eCompositionSpecies['plant'], '2rem').'  '.encode($eCompositionSpecies->getLabel()).'</div>',
			dialogOpen: $form->openAjax('/toolbox/compositionSpecies:doUpdate', ['class' => 'panel-dialog toolbox-composition-species-form']),
			dialogClose: $form->close(),
			body: $h
		);

	}

	/**
	 * Les réglages d'une espèce, dans l'ordre de « Régler une espèce », que « Ajouter une espèce » pose
	 * aussi sous l'espèce et la saison (r60, le 03/10/2026, « manque la fréquence et
	 * globalement les champs devraient être raccord ») : un seul rendu, pour que les deux panneaux ne
	 * puissent pas diverger. `$cultivated` dit si l'espèce règle sa fréquence et montre sa
	 * conservation : une espèce retirée ne le fait pas, une espèce qu'on ajoute le fait toujours.
	 *
	 * À l'ajout (`$added`), deux choses changent :
	 * - le produit vendu se cherche par l'espèce de la structure (`composition:queryAddProduct`),
	 *   l'espèce n'ayant pas encore d'id. Une espèce typée n'en a pas à chercher
	 *   (`CompositionSpeciesLib::loadAddTyped()`), et le champ ne se pose pas ;
	 * - la conservation se lit sans le lien vers la fiche de l'espèce : la fiche s'enregistre en
	 *   rechargeant l'écran, et l'ajout en cours se perdrait sans que rien ne soit écrit.
	 *
	 * Une espèce typée n'a pas encore d'unité (`eUnit` vide) : les libellés et les suffixes partent
	 * sans mot d'unité, que l'unité cochée leur donne (`composition.js`, `data-follow-text`), et le
	 * prix se dit « Prix », que le suffixe complète en « € / botte ».
	 */
	protected static function getSettings(\util\FormUi $form, CompositionSpecies $eCompositionSpecies, bool $cultivated, bool $added): string {

		$h = '';

		// Le produit vendu (r39b) se place avant le prix, parce qu'il le décide : une espèce liée prend
		// le prix de son produit (`CompositionLib::PRICE_PRODUCT`), qui se lit alors en texte figé. Le
		// champ du prix reste dans le formulaire, masqué, et poste le prix tapé tel quel : « Aucun » le
		// rend avec sa valeur, et le choix d'un produit, sans enregistrer, le masque au profit du prix
		// que le panier retiendra (`composition.js`).
		$linked = ($eCompositionSpecies['reference']['from'] === CompositionLib::PRICE_PRODUCT);

		// Le pas se pose toujours, masqué et sans rien poster quand l'unité ne se coupe pas : cocher une
		// unité qui se coupe le rend (`composition.js`).
		$properties = ['price', 'step', 'minimum', 'quantity'];

		if($cultivated) {
			$properties[] = 'frequency';
		}

		// Le prix, le pas, le minimum, la quantité par défaut et le produit vendu se disent dans l'unité
		// cochée : en changer vide leurs champs et y écrit la nouvelle unité (`composition.js`), et ce
		// qu'on y retape part avec elle (`CompositionSpeciesLib::getPropertiesPanel()`). `data-follow`
		// marque les groupes qui la suivent, `data-follow-text` les mots qui la nomment, posé sur le
		// suffixe lui-même : vide, la feuille le masque.
		$eUnit = $eCompositionSpecies['eUnit'];

		$singular = $eUnit->empty() ? '' : encode($eUnit['singular']);
		$plural = $eUnit->empty() ? '' : encode($eUnit['plural']);
		$by = s("€").\product\UnitUi::getBy($eUnit);

		$addon = fn(string $text, string $follow) => fn(\util\FormUi $form) => $form->addon($text, ['data-follow-text' => $follow]);

		$h .= $form->dynamicGroup($eCompositionSpecies, 'unit');

		// La quantité au panier d'une espèce que la communauté ne compte pas (r48c) se dit dans son
		// unité, et la suit comme le prix : en changer la vide, et elle se retape.
		if($eCompositionSpecies->isCommunity() === FALSE) {

			$h .= $form->dynamicGroup($eCompositionSpecies, 'quantityBasket*', function(\PropertyDescriber $d) use($addon, $plural) {
				$d->append = $addon($plural, 'plural');
				$d->group = ['data-follow' => 'quantityBasket'];
			});

		}

		// Le groupe garde le produit d'ouverture et ce qu'il dit du prix, que revenir à l'unité d'ouverture
		// rend (`composition.js`) : le champ caché de l'autocomplétion ne survit pas à une recherche.
		// À l'ajout (`data-follow-unit`), la recherche suit l'unité cochée au lieu de se fermer, et le
		// groupe attend qu'il y en ait une, comme le pas : une espèce typée part sans unité.
		if($eUnit->notEmpty() or $added) {

			$h .= $form->dynamicGroup($eCompositionSpecies, 'product', function(\PropertyDescriber $d) use($eCompositionSpecies, $eUnit, $added) {

				$d->group = ['data-follow' => 'product'];

				if($added) {
					$d->group['data-follow-unit'] = '';
					$d->group['class'] = $eUnit->empty() ? 'hide' : '';
					$d->autocompleteUrl = '/toolbox/composition:queryAddProduct';
					$d->autocompleteBody = fn(\util\FormUi $form, CompositionSpecies $e) => [
						'id' => $e['plan']['structure']['id'],
						'plant' => $e['ePlantStructure']['id'],
					];
				}

				if($eCompositionSpecies['eProductLinked']->notEmpty()) {

					$result = self::getProductAutocomplete($eCompositionSpecies['eProductLinked']);

					$d->group['data-value'] = $result['value'];

					if(isset($result['price'])) {
						$d->group['data-price'] = $result['price'];
						$d->group['data-from'] = $result['from'];
					}

				}

			});

		}

		// Le prix d'un produit lié ne se change pas ici : il se lit, avec d'où il vient, et chaque choix du
		// produit vendu y écrit le sien (`data-price`, `data-from`). Pas de `data-follow` : une autre
		// unité délie le produit, et le groupe se masque avec lui.
		$h .= $form->group(
			s("Prix par {unit}", ['unit' => '<span data-follow-text="singular">'.$singular.'</span>']),
			'<div data-price-value>'.($linked ? self::getPrice($eCompositionSpecies['reference']['unitPrice'], $eUnit) : '').'</div>'.\util\FormUi::info($linked ? self::getFromProduct($eCompositionSpecies['product']) : ''),
			['data-price-product' => '', 'class' => $linked ? '' : 'hide']
		);

		$h .= $form->dynamicGroups($eCompositionSpecies, $properties, [
			'price' => function(\PropertyDescriber $d) use($eUnit, $singular, $by, $addon, $linked) {
				// Sans unité (une espèce typée à l'ajout), « Prix par » attendrait son unité : le libellé
				// dit « Prix » et porte celui qui le remplace dès qu'on en coche une (`composition.js`).
				$label = s("Prix par {unit}", ['unit' => '<span data-follow-text="singular">'.$singular.'</span>']);
				$d->label = $eUnit->empty() ? '<span data-follow-label="'.encode($label).'">'.s("Prix").'</span>' : $label;
				$d->append = $addon($by, 'by');
				$d->group = ['data-follow' => 'price', 'class' => $linked ? 'hide' : ''];
			},
			'step' => function(\PropertyDescriber $d) use($eCompositionSpecies, $plural, $addon) {

				$d->append = $addon($plural, 'plural');
				$d->group = ['data-follow' => 'step'];

				if($eCompositionSpecies['divisible'] === FALSE) {

					$attributes = $d->attributes;

					$d->group['class'] = 'hide';
					$d->attributes = fn(...$arguments) => $attributes(...$arguments) + ['disabled' => 'disabled'];

				}

			},
			'minimum' => function(\PropertyDescriber $d) use($plural, $addon) {
				$d->append = $addon($plural, 'plural');
				$d->group = ['data-follow' => 'minimum'];
			},
			'quantity' => function(\PropertyDescriber $d) use($plural, $addon) {
				$d->append = $addon($plural, 'plural');
				$d->group = ['data-follow' => 'quantity'];
			},
			'frequency' => function(\PropertyDescriber $d) {
				$d->label = s("Fréquence");
			},
		]);

		if($cultivated) {

			$storage = '<div>'.self::getStorage($eCompositionSpecies).'</div>';

			// La durée vit sur l'espèce de la structure (`plant\Plant.storage`), que la fiche règle :
			// sans copie de l'espèce, il n'y a rien à régler, et la durée habituelle s'applique.
			if($added === FALSE and $eCompositionSpecies['storage']['plant']->notEmpty()) {
				$storage .= \util\FormUi::info('<a href="/plant/plant:update?id='.$eCompositionSpecies['storage']['plant']['id'].'&origin=composition">'.s("Changer la durée de conservation après récolte").'</a>');
			}

			$h .= $form->group(s("Conservation"), $storage);

		}

		return $h;

	}

	/**
	 * Le panneau « Ajouter une espèce » (r48c, planche r48), ouvert depuis le titre de l'onglet
	 * « Espèces ». Deux formulaires s'y suivent, parce qu'ils ne font pas le même geste :
	 *
	 * - le premier choisit, et se renvoie en GET à chaque choix (`composition:addSpecies`) : l'espèce,
	 *   parmi celles de la structure (`plantIdsAddable`), les siennes propres comprises, celles que le
	 *   plan livre déjà s'ajoutant en variante (r76 lot 2), puis le climat quand le plan n'en a pas, que le calendrier type date l'espèce et que
	 *   ni son plan de culture ni la communauté ne le font (`climateAsked`). Le panneau revient alors
	 *   avec ce que ce choix donne ;
	 * - le second ajoute (`composition:doAddSpecies`), et reprend l'espèce et le climat choisis. Il
	 *   n'existe qu'une fois l'espèce choisie.
	 *
	 * Le champ montre et renvoie l'espèce de la structure (`ePlantStructure`), là où `plant` est celle
	 * que le plan écrit, du référentiel partagé quand l'espèce choisie en recopie une.
	 *
	 * Quand la structure n'a aucune espèce en activité, le panneau le dit à la place du champ : la
	 * recherche d'espèces ne borne rien sur une liste vide, et proposerait des espèces que l'envoi
	 * refuse.
	 *
	 * Une espèce que le plan livre déjà s'ajoute en variante (r76 lot 2) : le panneau demande son nom,
	 * avant la saison, et ne coche aucune unité (`CompositionSpeciesLib::loadAddVariant()`).
	 *
	 * La saison se règle ici aux poignées, à la semaine, comme sur la grille (r59, le
	 * 03/10/2026, « à l'ajout permet de régler la saison avec les poignées »,
	 * `CompositionUi::getSeasonField()`) : elle part de celle de la source de l'espèce
	 * (`CompositionSpeciesLib::getSeasonRestored()`), vide quand aucune ne la date, et il faut alors la
	 * tracer. Tant qu'un climat demandé n'est pas choisi, il n'y a pas de saison à proposer.
	 *
	 * Sous la saison, les réglages de « Régler une espèce », mêmes champs dans le même ordre
	 * (`getSettings()`, r60), partis de ce que l'outil donnerait à l'espèce : le prix de la mercuriale,
	 * l'unité et les quantités de la communauté, la fréquence selon la saison. Une espèce que le plan
	 * portait retirée revient avec ce qu'on lui avait réglé. Une espèce que la communauté ne compte pas
	 * (`typed`) demande ce qu'aucun relevé ne lui donne, son unité, sa quantité au panier et son prix.
	 * Aucune unité n'y part cochée (planche r48, banc « ajout-pourpier ») : rien ne dit laquelle est la
	 * sienne, et une unité précochée s'enregistrerait sans avoir été choisie.
	 *
	 * Le bouton est dans le corps, comme celui de « Régler une espèce ». Il n'y a pas de « Retirer de
	 * mes cultures » : l'espèce n'est pas encore livrée.
	 *
	 * Ouvert par le « + » d'un rang de l'onglet (`$variant`, r76 lot 2, piste C, planche r76 banc
	 * « c-onglet »), le panneau devient « Ajouter une variante » : l'espèce est celle du rang, il n'y a
	 * donc rien à choisir, et le sous-titre la nomme comme celui de « Régler une espèce ». Choisie dans
	 * « Ajouter une espèce » (piste B), la même variante garde le titre et le choix de l'espèce, qu'on
	 * peut encore changer.
	 */
	public function add(CompositionSpecies $eCompositionSpecies, bool $variant = FALSE): \Panel {

		return $this->getAdd($eCompositionSpecies, '/toolbox/composition:addSpecies', '/toolbox/composition:doAddSpecies', start: FALSE, variant: $variant, hidden: [
			'id' => $eCompositionSpecies['plan']['structure']['id'],
		]);

	}

	/**
	 * Le même panneau, ouvert depuis la liste du départ (r48e, planche r48, banc « ajout-depart »).
	 * Rien n'est écrit à ce moment : chacun de ses deux formulaires réémet ce que le départ montrait
	 * à son ouverture, la saison, la source des saisons et son climat, et les espèces que sa liste
	 * livre (`$delivered`), sur lesquels la page relit ce que l'espèce choisie demande
	 * (`CompositionSpeciesLib::loadStartAdd()`). Le climat n'y est jamais demandé : c'est une question
	 * du départ. La saison s'y lit sans se régler (r58) quand une source du départ la date, et se
	 * règle une fois les paniers composés. Quand aucune ne la date (`undated`), elle se trace aux
	 * poignées, sur le rang de l'onglet Espèces (r67, le 03/10/2026, « la saison se demande au
	 * départ ») : sans elle, l'espèce partirait sans saison et ne serait servie par aucun panier.
	 *
	 * Les réglages de « Régler une espèce » ne s'y posent pas non plus (r60) : ils se lisent sur un plan
	 * écrit, que le départ n'a pas encore. Une espèce que la communauté ne compte pas (`typed`) y
	 * demande seulement ce sans quoi elle n'entre dans aucun panier, son unité, sa quantité au panier et
	 * son prix, sans unité cochée d'avance : le prix se dit donc « Prix », en euros, et les mots de
	 * l'unité paraissent quand on en coche une (`composition.js`, `data-follow-text`).
	 */
	public function addStart(CompositionSpecies $eCompositionSpecies, string $delivered): \Panel {

		$eCompositionPlan = $eCompositionSpecies['plan'];

		return $this->getAdd($eCompositionSpecies, '/toolbox/composition:addStartSpecies', '/toolbox/composition:doAddStartSpecies', start: TRUE, variant: FALSE, hidden: [
			'id' => $eCompositionPlan['structure']['id'],
			'season' => $eCompositionPlan['season'],
			'reference' => $eCompositionPlan['reference'],
			'climate' => $eCompositionPlan['climate'],
			'delivered' => $delivered,
		]);

	}

	protected function getAdd(CompositionSpecies $eCompositionSpecies, string $url, string $urlDo, bool $start, bool $variant, array $hidden): \Panel {

		$eCompositionPlan = $eCompositionSpecies['plan'];

		$chosen = $eCompositionSpecies['plant']->notEmpty();
		$climateAsked = ($chosen and $eCompositionSpecies['climateAsked']);

		$form = new \util\FormUi();

		$fields = $form->hiddens($hidden);

		$h = '';

		if($variant === FALSE) {

			$h .= $form->openAjax($url, ['method' => 'get', 'data-ajax-class' => 'Ajax.Query']);

				$h .= $fields;

				if($chosen === FALSE and $eCompositionSpecies['plantIdsAddable'] === []) {
					$h .= '<div class="util-empty">'.s("Aucune de vos espèces n'est en activité.").'</div>';
				} else {
					$h .= $form->dynamicGroup($eCompositionSpecies, 'plant');
				}

				if($climateAsked) {

					// Le formulaire est en ajax : son écouteur attend l'événement, que submit() ne lève pas.
					$h .= $form->group(
						CompositionUi::p('climate')->label.\util\FormUi::asterisk(),
						$form->select('climate', CompositionUi::p('climate')->values, $eCompositionPlan['climate'], [
							'onchange' => 'this.form.dispatchEvent(new CustomEvent("submit"))',
						]).\util\FormUi::info(s("Vos cultures {season} ne portent pas cette espèce. Sa saison sera celle du calendrier type de votre climat, qui servira aussi aux prochaines espèces ajoutées.", ['season' => $eCompositionPlan['season']])),
						['data-wrapper' => 'climate']
					);

				}

			$h .= $form->close();

		}

		if($chosen) {

			$h .= $form->openAjax($urlDo, ['class' => $start ? '' : 'toolbox-composition-species-form']);

				$h .= $fields;
				$h .= $form->hidden('plant', $eCompositionSpecies['ePlantStructure']);

				if($climateAsked) {
					$h .= $form->hidden('climate', $eCompositionPlan['climate']);
				}

				if($start and $eCompositionSpecies['undated']) {
					$h .= $form->group(s("Saison").\util\FormUi::asterisk(), CompositionUi::getSeasonField($eCompositionPlan, []), ['data-wrapper' => 'windowWeeks']);
				} else if($start) {

					// Les barres d'abord, l'axe dessous : le libellé du groupe tombe alors en face des
					// barres et non des initiales des mois.
					$season = '<div class="toolbox-composition-calendar toolbox-composition-calendar-field">';
						$season .= CompositionUi::getSeasonBars($eCompositionSpecies['months']);
						$season .= CompositionUi::getMonthsAxis('toolbox-composition-calendar-axis');
					$season .= '</div>';

					$h .= $form->group(s("Saison"), $season);

				} else if($climateAsked === FALSE or $eCompositionPlan['climate'] !== NULL) {

					// Une espèce que le plan livre déjà s'ajoute en variante (r76 lot 2, piste B), qui se
					// distingue d'elle par son nom.
					if($eCompositionSpecies['eCompositionSpeciesBare']->notEmpty()) {
						$h .= $form->dynamicGroup($eCompositionSpecies, 'name*');
					}

					$h .= $form->group(s("Saison").\util\FormUi::asterisk(), CompositionUi::getSeasonField($eCompositionPlan, $eCompositionSpecies['seasonAdded']['windowWeeks']), ['data-wrapper' => 'windowWeeks']);
					$h .= self::getSettings($form, $eCompositionSpecies, TRUE, added: TRUE);

				}

				if($start and $eCompositionSpecies['typed']) {

					$h .= $form->dynamicGroup($eCompositionSpecies, 'unit*', function(\PropertyDescriber $d) {

						$d->field = function(\util\FormUi $form, CompositionSpecies $e) {

							$values = [];

							foreach($e['cUnitOffered'] as $eUnitOffered) {
								$values[$eUnitOffered['id']] = CompositionSpeciesUi::getUnitLabel($eUnitOffered);
							}

							return $form->radios('unit', $values, NULL, [
								'required' => TRUE,
								'callbackRadioAttributes' => fn($option, $key) => [
									'data-singular' => $e['cUnitOffered'][$key]['singular'],
									'data-plural' => $e['cUnitOffered'][$key]['plural'],
									'data-by' => s("€").\product\UnitUi::getBy($e['cUnitOffered'][$key]),
								],
							]);

						};

					});

					// Sans unité cochée, la quantité n'a pas de mot à porter : son suffixe est vide, et la
					// feuille ne le montre qu'une fois rempli.
					$h .= $form->dynamicGroup($eCompositionSpecies, 'quantityBasket*', function(\PropertyDescriber $d) {
						$d->append = fn(\util\FormUi $form) => $form->addon('', ['data-follow-text' => 'plural']);
					});

					// Le prix d'une espèce neuve n'a ni cascade à lire en attente ni provenance à dire : les
					// attributs et la note du champ de « Régler une espèce » ne valent pas ici.
					$h .= $form->dynamicGroup($eCompositionSpecies, 'price*', function(\PropertyDescriber $d) {
						$d->label = s("Prix");
						$d->attributes = [];
						$d->append = fn(\util\FormUi $form) => $form->addon(s("€"), ['data-follow-text' => 'by']);
						$d->after = NULL;
					});

				}

				$h .= $form->group(content: $form->submit(s("Ajouter")));

			$h .= $form->close();

		}

		if($variant) {

			return new \Panel(
				id: 'toolbox-composition-add-panel',
				title: s("Ajouter une variante"),
				subTitle: '<div class="panel-header-subtitle">'.\plant\PlantUi::getVignette($eCompositionSpecies['plant'], '2rem').'  '.s("Variante de {plant}", ['plant' => encode($eCompositionSpecies['plant']['name'])]).'</div>',
				body: $h
			);

		}

		return new \Panel(
			id: 'toolbox-composition-add-panel',
			title: s("Ajouter une espèce"),
			body: $h
		);

	}

	/**
	 * Pose les attributs que postent les `util-quick` de l'espèce dans la grille (`getGridPriceQuick()`)
	 * et rend les mêmes valeurs pour les gestes qui ne passent pas par eux, « Retirer de mes cultures »
	 * au pied du panneau et les choix du menu de la fréquence (`getGridFrequency()`).
	 *
	 * Les attributs d'un `util-quick` sont communs à tous les éléments (`Data::setQuickAttribute()`) :
	 * ils se posent juste avant chaque geste, et non une fois pour la grille. Le cadriciel ne sait pas
	 * en retirer un : ceux qu'un autre geste pose (`origin`, que pose le poids de la Synthèse,
	 * `CompositionUi::getQuantitiesWeight()`) se remettent à `NULL`, qu'`attrs()` n'écrit pas, et
	 * toutes les clés se posent dans le même ordre, qui fixe leur place dans `post-list`. Sans cela, le
	 * prix porterait ce que l'élément rendu avant lui a posé, que la grille entière rend et qu'un rang
	 * rendu seul n'a pas.
	 */
	private static function setQuickPosts(CompositionSpecies $eCompositionSpecies, int $structureId, ?int $formulaId): array {

		$posts = [
			'structure' => $structureId,
			'formula' => $formulaId,
			'week' => NULL,
			'species' => NULL,
			'origin' => NULL,
		];

		foreach($posts as $name => $value) {
			CompositionSpecies::setQuickAttribute($name, $value);
		}

		return $posts;

	}

	/**
	 * Une unité du panier en tête de phrase, « Au kilo », « À la pièce ». L'unité de la communauté est
	 * celle que le plus de structures emploient pour l'espèce, qui peut être une barquette ou un
	 * sachet : elle se dit alors par son nom.
	 */
	public static function getUnitLabel(\product\Unit $eUnit): string {

		return match($eUnit['fqn']) {
			'kg' => s("Au kilo"),
			'one' => s("À la pièce"),
			'bunch' => s("À la botte"),
			default => s("Par {unit}", ['unit' => encode($eUnit['singular'])]),
		};

	}

	/**
	 * La même unité au fil d'une phrase, « converti du prix au kilo ».
	 */
	public static function getUnitIn(\product\Unit $eUnit): string {

		return match($eUnit['fqn']) {
			'kg' => s("au kilo"),
			'one' => s("à la pièce"),
			'bunch' => s("à la botte"),
			default => s("par {unit}", ['unit' => encode($eUnit['singular'])]),
		};

	}

	/**
	 * Ce que changer l'unité efface de ce que la structure a tapé (lot r14) : le prix, le pas, le
	 * minimum et la quantité par défaut (r30, #92), qui se disent dans l'unité d'avant, et son poids, « Votre prix de 14,00 € / kg ne vaut
	 * pas à la pièce et sera effacé. Voulez-vous continuer ? » (planche r14). NULL quand rien de tapé
	 * ne se perd : l'enregistrement ne se confirme pas. Le texte part dans un attribut, par `attr()`,
	 * qui l'encode : le nom de l'unité y entre tel quel.
	 */
	public static function getUnitLoss(CompositionSpecies $eCompositionSpecies, \product\Unit $eUnitTarget): ?string {

		$eUnit = $eCompositionSpecies['eUnit'];

		// Une espèce sans unité (r60, une espèce typée que « Ajouter une espèce » ajoute) n'a rien tapé
		// qu'une unité effacerait.
		if($eUnit->empty() or $eUnitTarget['id'] === $eUnit['id']) {
			return NULL;
		}

		$losses = [];

		// La quantité au panier d'une espèce que la communauté ne compte pas (r48c), nulle pour toute autre.
		if($eCompositionSpecies['quantityBasket'] !== NULL) {
			$losses[] = s("votre quantité par panier de {quantity}", ['quantity' => CompositionUi::getQuantity($eCompositionSpecies['quantityBasket'], $eUnit)]);
		}

		if($eCompositionSpecies['price'] !== NULL) {
			$losses[] = s("votre prix de {price}", ['price' => self::getPrice($eCompositionSpecies['price'], $eUnit)]);
		}

		if($eCompositionSpecies['step'] !== NULL) {
			$losses[] = s("votre pas de vente de {step}", ['step' => CompositionUi::getQuantity($eCompositionSpecies['step'], $eUnit)]);
		}

		if($eCompositionSpecies['minimum'] !== NULL) {
			$losses[] = s("votre quantité minimale de {minimum}", ['minimum' => CompositionUi::getQuantity($eCompositionSpecies['minimum'], $eUnit)]);
		}

		if($eCompositionSpecies['quantity'] !== NULL) {
			$losses[] = s("votre quantité par défaut de {quantity}", ['quantity' => CompositionUi::getQuantity($eCompositionSpecies['quantity'], $eUnit)]);
		}

		if($eCompositionSpecies['weight'] !== NULL) {
			$losses[] = s("votre poids de {weight} pour une {unit}", ['weight' => self::getKilos($eCompositionSpecies['weight']), 'unit' => $eUnit['singular']]);
		}

		// Le produit vendu se vend dans l'unité d'avant (r39b) : `CompositionSpeciesLib::update()` le délie.
		if($eCompositionSpecies['product']->notEmpty()) {
			$losses[] = s("votre produit vendu {product}", ['product' => CompositionSaleUi::getCandidateName($eCompositionSpecies['product'])]);
		}

		if($losses === []) {
			return NULL;
		}

		$list = ucfirst(CompositionFormulaUi::getEnumeration($losses));
		$unit = self::getUnitIn($eUnitTarget);

		if(count($losses) > 1) {
			return s("{list} ne valent pas {unit} et seront effacés. Voulez-vous continuer ?", ['list' => $list, 'unit' => $unit]);
		} else if($eCompositionSpecies['quantityBasket'] !== NULL or $eCompositionSpecies['minimum'] !== NULL or $eCompositionSpecies['quantity'] !== NULL) {
			return s("{list} ne vaut pas {unit} et sera effacée. Voulez-vous continuer ?", ['list' => $list, 'unit' => $unit]);
		} else {
			return s("{list} ne vaut pas {unit} et sera effacé. Voulez-vous continuer ?", ['list' => $list, 'unit' => $unit]);
		}

	}

	/**
	 * Combien de semaines une récolte de l'espèce sert les paniers après sa semaine (lot r9, planche
	 * r9, question 5) : « Se conserve 8 semaines, durée habituelle de l'espèce » tant que la structure
	 * suit la durée du référentiel, sans la provenance quand elle a posé la sienne sur la fiche de
	 * l'espèce.
	 */
	public static function getStorage(CompositionSpecies $eCompositionSpecies): string {

		['weeks' => $weeks, 'typed' => $typed] = $eCompositionSpecies['storage'];

		if($weeks === 0) {
			return s("Ne se conserve pas");
		}

		$storage = p("Se conserve {value} semaine", "Se conserve {value} semaines", $weeks);

		return $typed ? $storage : s("{storage}, durée habituelle de l'espèce", ['storage' => $storage]);

	}

	/**
	 * La fréquence de l'espèce dite par son rythme (retour du 26/09), au survol de son picto :
	 * « Dans les paniers 1 semaine sur 2, comme vous l'avez choisi » quand elle en a choisi une, sinon
	 * ce que la grille lui sert réellement (`served` sur `candidate`), selon la saison, le chiffre que
	 * le formulaire écrit sous « Selon la saison » : la catégorie de ce rythme l'arrondirait, et les
	 * deux se contrediraient.
	 */
	public static function getFrequency(CompositionSpecies $eCompositionSpecies): string {

		if($eCompositionSpecies['frequency'] === NULL) {

			if($eCompositionSpecies['served'] === 0) {
				return s("Dans aucun panier, selon la saison");
			} else {
				return s("Dans les paniers {weeks}, selon la saison", ['weeks' => self::getWeeks($eCompositionSpecies['served'], $eCompositionSpecies['candidate'])]);
			}

		} else {
			return s("Dans les paniers {rhythm}, comme vous l'avez choisi", ['rhythm' => self::getRhythm($eCompositionSpecies['frequency'])]);
		}

	}

	/**
	 * Le picto de la fréquence au bout du nom de l'espèce dans la grille (planche r6), à la manière des
	 * gouttes du pluviomètre (`RainUi::getDrops()`) : des barres qui montent, quatre pour la base,
	 * une pour la diversification, les barres éteintes montrant l'échelle. Il suit la fréquence que
	 * la grille applique, celle que l'espèce a choisie, sinon celle que sa part lui donne
	 * (`frequencyCurrent`). Le survol le dit en toutes lettres (`getFrequency()`).
	 *
	 * Le picto ouvre un menu déroulant (r34c, #114, « dropdwn pour ça »), titré « Fréquence
	 * d'apparition » (r60, le 03/10/2026) : la phrase du survol y redisait ce que le choix
	 * marqué montre déjà. Le menu porte chaque choix du champ que le panneau « Régler une espèce » écrit aussi : « Selon la saison »,
	 * puis chaque rythme (`CompositionLib::FREQUENCIES`), le choix en cours marqué `selected` comme les
	 * menus du dépôt. Un clic l'écrit en un geste, par l'écriture rapide du module
	 * (`\util\FormUi::getQuickUrl()`), qui passe par `build()` sur `frequency` et rend ce que la
	 * fréquence a recomposé, comme le formulaire rapide qu'il remplace. Aux mêmes conditions : qui peut
	 * régler l'espèce, tant qu'elle n'est pas retirée. Sinon il reste une image.
	 */
	public static function getGridFrequency(CompositionSpecies $eCompositionSpecies, CompositionFormula $eCompositionFormula): string {

		$frequency = ($eCompositionSpecies['frequency'] === NULL) ? $eCompositionSpecies['frequencyCurrent'] : $eCompositionSpecies['frequency'];

		$bars = match($frequency) {
			CompositionSpecies::BASE => 4,
			CompositionSpecies::MEDIUM => 3,
			CompositionSpecies::LOW => 2,
			CompositionSpecies::DIVERSIFICATION => 1,
		};

		$title = encode(self::getFrequency($eCompositionSpecies));
		$icon = \Asset::icon('reception-'.$bars);

		if($eCompositionSpecies['status'] !== CompositionSpecies::EXCLUDED and $eCompositionSpecies->assertUpdate()) {

			$posts = self::setQuickPosts($eCompositionSpecies, $eCompositionSpecies['plan']['structure']['id'], $eCompositionFormula['id']);
			$posts['property'] = 'frequency';

			$h = '<a class="toolbox-composition-grid-frequency" data-dropdown="bottom-start" title="'.$title.'">'.$icon.'</a>';
			$h .= '<div class="dropdown-list">';
				$h .= '<div class="dropdown-title">'.s("Fréquence d'apparition").'</div>';
				foreach(self::getFrequencyChoices($eCompositionSpecies) as $choice => $label) {
					$h .= '<a data-ajax="'.\util\FormUi::getQuickUrl($eCompositionSpecies).'" post-id="'.$eCompositionSpecies['id'].'" '.CompositionUi::getPosts($posts + ['frequency' => $choice]).' class="dropdown-item'.(self::isFrequencyChoice($eCompositionSpecies, $choice) ? ' selected' : '').'">'.$label.'</a>';
				}
			$h .= '</div>';

			return $h;

		}

		return '<span class="toolbox-composition-grid-frequency" title="'.$title.'">'.$icon.'</span>';

	}

	/**
	 * Le menu de la fréquence tel que la grille l'ouvre (`getGridFrequency()`), déjà ouvert et sans
	 * geste, pour la présentation de l'outil (`CompositionUi::getNoticePanel()`) : il se montre aussi
	 * à qui ne peut pas écrire le plan, à qui la grille ne l'ouvre pas.
	 */
	public static function getFrequencyList(CompositionSpecies $eCompositionSpecies): string {

		$h = '<div class="dropdown-list dropdown-list-open">';
			$h .= '<div class="dropdown-title">'.s("Fréquence d'apparition").'</div>';
			foreach(self::getFrequencyChoices($eCompositionSpecies) as $choice => $label) {
				$h .= '<a class="dropdown-item'.(self::isFrequencyChoice($eCompositionSpecies, $choice) ? ' selected' : '').'">'.$label.'</a>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les choix du menu de la fréquence : « Selon la saison », qui se poste vide, puis chaque rythme.
	 * Une variante n'a pas « Selon la saison » (r76 lot 2, `frequency.seasonal`).
	 */
	protected static function getFrequencyChoices(CompositionSpecies $eCompositionSpecies): array {

		$choices = ($eCompositionSpecies['name'] === NULL) ? ['' => s("Selon la saison")] : [];

		foreach(array_keys(CompositionLib::FREQUENCIES) as $choice) {
			$choices[$choice] = self::getRhythm($choice);
		}

		return $choices;

	}

	protected static function isFrequencyChoice(CompositionSpecies $eCompositionSpecies, string $choice): bool {
		return ($eCompositionSpecies['frequency'] === (($choice === '') ? NULL : $choice));
	}

	/**
	 * Le rythme d'une fréquence, « 3 semaines sur 4 » (`CompositionLib::FREQUENCIES`).
	 */
	public static function getRhythm(string $frequency): string {

		[$served, $weeks] = CompositionLib::FREQUENCIES[$frequency];

		return self::getWeeks($served, $weeks);

	}

	/**
	 * « 1 semaine sur 5 », « 9 semaines sur 20 ».
	 */
	public static function getWeeks(int $served, int $weeks): string {
		return p("{value} semaine sur {weeks}", "{value} semaines sur {weeks}", $served, ['weeks' => $weeks]);
	}

	/**
	 * Un poids en kilos, « 0,5 kg », sans les zéros que la colonne garde au gramme.
	 */
	public static function getKilos(float $kilos, int|string|null $precision = NULL): string {
		return \util\TextUi::nonBreaking(s("{value} kg", ['value' => \util\TextUi::number($kilos, $precision)]));
	}

	/**
	 * Le prix de l'espèce au bout de son nom dans la grille, « 3,00 € / kg » (planche r1), dans
	 * l'unité où elle entre au panier et avec son nom court. Rien quand l'espèce n'a pas de prix
	 * connu : son panneau le dit.
	 */
	public static function getGridPrice(CompositionSpecies $eCompositionSpecies, bool $breakable = FALSE): string {

		$unitPrice = $eCompositionSpecies['reference']['unitPrice'];

		if($unitPrice === NULL) {
			return '';
		}

		$amount = str_replace(' ', "\u{a0}", \util\TextUi::money($unitPrice, precision: 2));
		$by = str_replace(' ', "\u{a0}", ltrim(\product\UnitUi::getBy($eCompositionSpecies['eUnit'], short: TRUE)));

		// Le prix empilé sous la quantité, sous 575 px, peut passer l'unité à la ligne (r71) : d'un
		// seul tenant, il élargissait sa colonne aux dépens du nom, que la largeur restante coupait
		// au milieu du mot à 360 px.
		return $amount.($breakable ? ' ' : "\u{a0}").$by;

	}

	/**
	 * Le prix au bout du nom, réglable sur place (lot r21, retour du 27/09, « prix modifiable
	 * en quick ») : le champ du prix du panneau « Régler une espèce », en formulaire rapide, qui pose
	 * ses attributs juste avant lui (`setQuickPosts()`). Une espèce sans prix connu ne montre rien :
	 * son panneau le dit et le règle.
	 */
	public static function getGridPriceQuick(CompositionSpecies $eCompositionSpecies, CompositionFormula $eCompositionFormula): string {

		$price = self::getGridPrice($eCompositionSpecies);

		// Le prix d'une espèce liée à son produit se change sur le produit (r39b) : il ne se tape pas ici.
		if(
			$price === '' or
			$eCompositionSpecies['reference']['from'] === CompositionLib::PRICE_PRODUCT
		) {
			return $price;
		}

		self::setQuickPosts($eCompositionSpecies, $eCompositionSpecies['plan']['structure']['id'], $eCompositionFormula['id']);

		return $eCompositionSpecies->quick('price', $price);

	}

	public static function getPrice(float $price, \product\Unit $eUnit): string {
		return str_replace(' ', "\u{a0}", \util\TextUi::money($price).\product\UnitUi::getBy($eUnit));
	}

	/**
	 * D'où vient un prix, « par défaut, le prix de votre mercuriale » : le prix de la mercuriale n'est
	 * qu'une valeur par défaut, que le champ remplace (r53). Le prix de la communauté se dit sans nommer
	 * la mercuriale générale, dont ce projet ne parle pas (verdict sur la planche r22).
	 */
	protected static function getFrom(string $from, CompositionSpecies $eCompositionSpecies): string {

		return match($from) {
			CompositionLib::PRICE_PRODUCT => s("prix de {product}", ['product' => encode(CompositionSaleUi::getCandidateName($eCompositionSpecies['product']))]),
			CompositionLib::PRICE_TYPED => s("votre prix"),
			CompositionLib::PRICE_DECLARED, CompositionLib::PRICE_CATALOG => s("par défaut, le prix de votre mercuriale"),
			CompositionLib::PRICE_FORECAST => s("prix de votre prévisionnel"),
			CompositionLib::PRICE_SALES => s("prix de vos ventes"),
			CompositionLib::PRICE_NATIONAL => s("prix par défaut"),
			CompositionLib::PRICE_CONVERTED => s("converti du prix {unit}", ['unit' => self::getUnitIn($eCompositionSpecies['eUnitCommunity'])]),
		};

	}

	/**
	 * Ce que la recherche du produit vendu rend (r53), dans « Régler une espèce » comme dans « Ajouter
	 * une espèce » (r60) : les candidats trouvés, puis la création d'un produit.
	 */
	public static function getProductResults(\Collection $cProduct, CompositionSpecies $eCompositionSpecies): array {

		$results = $cProduct->makeArray(fn(\product\Product $eProduct) => self::getProductAutocomplete($eProduct));
		$results[] = self::getProductCreate($eCompositionSpecies);

		return $results;

	}

	/**
	 * Un produit candidat dans l'autocomplétion du produit vendu (r53) : son choix tel que la page
	 * « Mettre en vente la semaine » le dit (`CompositionSaleUi::getCandidateLabel()`), et, quand il a
	 * un prix sur son canal, `price`, le prix que le panier retiendra, et `from`, d'où il vient, que
	 * le prix figé reprend dès qu'on le choisit (`composition.js`, qui les lit dans le détail de
	 * `autocompleteSelect`). Sans prix, la cascade décide, et le champ du prix reste.
	 */
	public static function getProductAutocomplete(\product\Product $eProduct): array {

		$label = CompositionSaleUi::getCandidateLabel($eProduct);

		$result = [
			'value' => $eProduct['id'],
			'itemHtml' => encode($label),
			'itemText' => $label,
		];

		if($eProduct['price'] !== NULL) {
			$result['price'] = self::getPrice($eProduct['price'], $eProduct['unit']);
			$result['from'] = self::getFromProduct($eProduct);
		}

		return $result;

	}

	/**
	 * La dernière entrée de l'autocomplétion du produit vendu (r53), comme celle des produits
	 * (`ProductUi::getAutocompleteCreate()`) : elle ouvre la création de produit avec l'espèce et
	 * l'unité de la structure et le prix que le panier emploie pour l'espèce, comme « Créer le
	 * produit » de la page de vente (`CompositionSaleUi::urlCreateProduct()`).
	 */
	public static function getProductCreate(CompositionSpecies $eCompositionSpecies): array {

		$item = \Asset::icon('plus-circle');
		$item .= '<div>'.s("Ajouter un nouveau produit").'</div>';

		return [
			'type' => 'link',
			'link' => CompositionSaleUi::urlCreateProduct($eCompositionSpecies['plan']['structure'], $eCompositionSpecies['ePlantCreate'], $eCompositionSpecies['eUnitCreate'], $eCompositionSpecies['reference']['unitPrice'], NULL),
			'itemHtml' => $item,
		];

	}

	/**
	 * D'où vient le prix d'une espèce liée à `$eProduct`, sous le prix figé : la phrase que
	 * `getPriceInfo()` écrit pour `CompositionLib::PRICE_PRODUCT`, que chaque choix du produit vendu
	 * porte aussi (`data-from`).
	 */
	public static function getFromProduct(\product\Product $eProduct): string {
		return s("{from}.", ['from' => ucfirst(s("prix de {product}", ['product' => encode(CompositionSaleUi::getCandidateName($eProduct))]))]);
	}

	/**
	 * Sous le champ du prix, d'où vient celui qui s'applique aujourd'hui, ou qu'aucun n'est connu. Un
	 * prix tapé n'en dit rien : il est dans le champ.
	 *
	 * Un prix converti (lot r14) dit son calcul, le prix de l'unité de la communauté et le poids qui le
	 * convertit, « Converti du prix au kilo, 12,00 € / kg pour une pièce de 0,05 kg. », puis d'où vient
	 * ce prix.
	 */
	protected static function getPriceInfo(CompositionSpecies $eCompositionSpecies): string {

		$reference = $eCompositionSpecies['reference'];
		$eUnit = $eCompositionSpecies['eUnit'];

		// Une espèce que « Ajouter une espèce » ajoute sans que la communauté la compte (r60) n'a pas
		// encore de prix tapé : il n'y a aucune semaine dont dire qu'aucun prix n'est connu.
		if($reference['from'] === CompositionLib::PRICE_TYPED) {
			return '';
		}

		if($reference['unitPrice'] === NULL) {
			return \util\FormUi::info(s("Pas de prix connu la semaine {week}.", ['week' => $reference['week']]));
		}

		$sentences = [];

		if($reference['from'] === CompositionLib::PRICE_CONVERTED) {

			$converted = $reference['converted'];
			$eUnitCommunity = $eCompositionSpecies['eUnitCommunity'];

			$weights = [];

			foreach([[$eUnit, $eCompositionSpecies['conversion']['kilos']], [$eUnitCommunity, $eCompositionSpecies['conversion']['kilosCommunity']]] as [$eUnitWeighed, $kilos]) {

				if($eUnitWeighed['fqn'] !== 'kg') {
					$weights[] = s("une {unit} de {weight}", ['unit' => encode($eUnitWeighed['singular']), 'weight' => self::getKilos($kilos)]);
				}

			}

			$sentences[] = s("Converti du prix {unit}, {price} pour {weights}.", [
				'unit' => self::getUnitIn($eUnitCommunity),
				'price' => self::getPrice(round($converted['unitPrice'], 2), $eUnitCommunity),
				'weights' => CompositionFormulaUi::getEnumeration($weights),
			]);

			$sentences[] = s("{from}.", ['from' => ucfirst(self::getFrom($converted['from'], $eCompositionSpecies))]);

		} else {
			$sentences[] = s("{from}.", ['from' => ucfirst(self::getFrom($reference['from'], $eCompositionSpecies))]);
		}

		return \util\FormUi::info(implode(' ', $sentences));

	}

	public static function p(string $property): \PropertyDescriber {

		$d = CompositionSpecies::model()->describer($property, [
			'price' => fn(CompositionSpecies $e) => s("{plant}, prix par {unit}", ['plant' => encode($e->getLabel()), 'unit' => encode($e['eUnit']['singular'])]),
			'step' => s("Pas de vente"),
			'minimum' => s("Quantité minimale lorsque le produit est proposé dans un panier"),
			'quantity' => s("Quantité par défaut"),
			'weight' => fn(CompositionSpecies $e) => s("{plant}, poids d'une {unit}", ['plant' => encode($e->getLabel()), 'unit' => encode($e['eUnit']['singular'])]),
			'frequency' => fn(CompositionSpecies $e) => s("{plant}, fréquence", ['plant' => encode($e->getLabel())]),
			'unit' => s("Unité"),
			'quantityBasket' => s("Quantité par panier"),
			'product' => s("Produit vendu"),
			'plant' => s("Espèce"),
			'name' => s("Nom"),
		]);

		switch($property) {

			// L'espèce de « Ajouter une espèce » (r48c) : la recherche d'espèces du dépôt, sur celles de
			// la structure (`structure`), bornée à celles qui sont en activité
			// (`CompositionSpeciesLib::getAddable()`), et dont le choix renvoie le panneau. Le champ porte
			// l'espèce de la structure, pas celle que le plan écrit.
			case 'plant' :
				new \plant\PlantUi()->query($d);
				$d->autocompleteBody = fn(\util\FormUi $form, CompositionSpecies $e) => ['structure' => $e['plan']['structure']['id'], 'ids' => $e['plantIdsAddable']];
				$d->autocompleteDefault = fn(CompositionSpecies $e) => $e['ePlantStructure'];
				$d->attributes = ['data-autocomplete-select' => 'submit'];
				break;

			case 'quantityBasket' :
				$d->append = fn(\util\FormUi $form, CompositionSpecies $e) => $form->addon(encode($e['eUnit']['plural']));
				break;

			// Le champ vide laisse la cascade décider : le prix qu'elle donne s'y lit en attente, et
			// l'enregistrer sans le changer ne le fige pas sur l'espèce.
			// Pour une espèce liée (r39b), le champ masqué ne dit rien de la cascade : le prix qu'elle
			// donnerait sans le produit n'est pas calculé, et celui du produit se lit au-dessus, figé.
			case 'price' :
				$d->attributes = fn(\util\FormUi $form, CompositionSpecies $e) => [
					'placeholder' => (in_array($e['reference']['from'], [CompositionLib::PRICE_TYPED, CompositionLib::PRICE_PRODUCT], TRUE) or $e['reference']['unitPrice'] === NULL) ? '' : \util\TextUi::number($e['reference']['unitPrice'], 2),
				];
				$d->append = fn(\util\FormUi $form, CompositionSpecies $e) => $form->addon(s("€").\product\UnitUi::getBy($e['eUnit']));
				$d->after = fn(\util\FormUi $form, CompositionSpecies $e) => ($e['reference']['from'] === CompositionLib::PRICE_PRODUCT) ? '' : self::getPriceInfo($e);
				break;

			// Vide, le pas est celui de l'unité, qui s'y lit en attente : l'enregistrer sans le changer
			// ne le fige pas sur l'espèce, comme le prix.
			case 'step' :
				$d->attributes = fn(\util\FormUi $form, CompositionSpecies $e) => [
					'placeholder' => \util\TextUi::number($e['stepUnit'], NULL),
				];
				$d->append = fn(\util\FormUi $form, CompositionSpecies $e) => $form->addon(encode($e['eUnit']['plural']));
				break;

			// Vide, le minimum est celui des paniers de la communauté, arrondi au pas, ou un pas quand elle
			// n'en donne pas dans cette unité (lot r25) : il s'y lit en attente, et l'enregistrer sans le
			// changer ne le fige pas sur l'espèce, qui suit la communauté d'une nuit à l'autre.
			case 'minimum' :
				$d->attributes = fn(\util\FormUi $form, CompositionSpecies $e) => [
					'placeholder' => \util\TextUi::number($e['minimumDefault'], NULL),
				];
				$d->append = fn(\util\FormUi $form, CompositionSpecies $e) => $form->addon(encode($e['eUnit']['plural']));
				break;

			// Vide, la quantité d'un ajout à la main est le minimum qui s'applique (r30, #92), réglé ou non :
			// il s'y lit en attente, et suit le champ du minimum et l'unité cochée (`composition.js`).
			case 'quantity' :
				$d->attributes = fn(\util\FormUi $form, CompositionSpecies $e) => [
					'placeholder' => \util\TextUi::number($e['quantityDefault'], NULL),
				];
				$d->append = fn(\util\FormUi $form, CompositionSpecies $e) => $form->addon(encode($e['eUnit']['plural']));
				break;

			// Vide, le poids est celui des cultures de la structure ou le poids habituel retenu par
			// Ouvretaferme, qui s'y lit en attente : l'enregistrer sans le changer ne le fige pas sur
			// l'espèce, comme le prix. Le suffixe est celui du formulaire de culture. Le poids habituel
			// se dit en phrase, du point de vue de la structure : « Poids courant. » ne disait pas d'où
			// venait le chiffre (verdict sur la planche r22).
			case 'weight' :
				$d->attributes = fn(\util\FormUi $form, CompositionSpecies $e) => [
					'placeholder' => in_array($e['weightReference']['from'], [CompositionLib::WEIGHT_CULTIVATED, CompositionLib::WEIGHT_BASE], TRUE) ? \util\TextUi::number($e['weightReference']['weight'], NULL) : '',
				];
				$d->append = fn(\util\FormUi $form, CompositionSpecies $e) => $form->addon(s("kg / {unit}", ['unit' => encode($e['eUnit']['singular'])]));
				$d->after = fn(\util\FormUi $form, CompositionSpecies $e) => match($e['weightReference']['from']) {
					CompositionLib::WEIGHT_CULTIVATED => \util\FormUi::info(s("D'après vos cultures {season}.", ['season' => $e['plan']['season']])),
					CompositionLib::WEIGHT_BASE => \util\FormUi::info(s("Ouvretaferme retient ce poids habituel d'une {unit} tant que vous n'indiquez pas le vôtre et que vos cultures {season} n'en donnent pas.", ['unit' => encode($e['eUnit']['singular']), 'season' => $e['plan']['season']])),
					CompositionLib::WEIGHT_TYPED, NULL => '',
				};
				break;

			// Vide, l'espèce garde la fréquence que la grille lui donne selon la saison, que le choix
			// vide écrit avec ce qu'elle sert aujourd'hui : c'est le `placeholder` de `radios()`
			// (planche L9). Chaque fréquence se dit par son rythme, du point de vue de la structure :
			// les noms de la règle, « Base » ou « Diversification », ne disaient pas combien de
			// paniers (verdict sur la planche r22).
			case 'frequency' :
				$d->values = [
					CompositionSpecies::BASE => CompositionSpeciesUi::getRhythm(CompositionSpecies::BASE),
					CompositionSpecies::MEDIUM => CompositionSpeciesUi::getRhythm(CompositionSpecies::MEDIUM),
					CompositionSpecies::LOW => CompositionSpeciesUi::getRhythm(CompositionSpecies::LOW),
					CompositionSpecies::DIVERSIFICATION => CompositionSpeciesUi::getRhythm(CompositionSpecies::DIVERSIFICATION),
				];
				// Un champ rendu par `$d->field` ne nomme pas son groupe : le refus `frequency.seasonal`
				// s'affiche sous lui par ce wrapper.
				$d->group = ['data-wrapper' => 'frequency'];
				// `Closure::call()` lie le champ au describer : `self` y désignerait `PropertyDescriber`.
				$d->field = function(\util\FormUi $form, CompositionSpecies $e) use($d) {

					if($e['served'] === 0) {
						$served = s("Dans aucun panier aujourd'hui");
					} else {
						$served = s("Dans les paniers {weeks} aujourd'hui", ['weeks' => CompositionSpeciesUi::getWeeks($e['served'], $e['candidate'])]);
					}

					$placeholder = s("Selon la saison").'<div class="util-annotation">'.$served.'</div>';

					// Une variante n'a pas « Selon la saison » (r76 lot 2, `frequency.seasonal`) : `radios()`
					// ne rend pas le choix vide d'un champ obligatoire.
					return $form->radios('frequency', $d->values, $e['frequency'], [
						'placeholder' => $placeholder,
						'required' => ($e['seasonalOffered'] === FALSE),
					]);

				};
				break;

			// Les produits de la structure qui peuvent porter l'espèce dans l'unité du panier
			// (`CompositionSpeciesLib::loadProducts()`), dits comme sur la page « Mettre en vente la
			// semaine », à chercher par leur nom (r53, « remplacer par un autocomplete avec Ajouter un
			// nouveau produit comme on fait pour les variétés ») : la recherche ne parcourt que ces
			// candidats (`compositionSpecies:queryProduct`), et sa dernière entrée crée le produit
			// (`getProductCreate()`). Vider le champ délie : la croix poste un produit vide, et
			// `composition.js` fait de même quand le texte est effacé au clavier. Un produit lié qui ne
			// porte plus l'espèce (désactivé, passé dans une autre unité) n'est pas proposé : le champ
			// s'ouvre vide.
			case 'product' :
				$d->field = 'autocomplete';
				$d->prepend = \Asset::icon('box');
				$d->placeholder = s("Tapez un nom de produit");
				$d->autocompleteUrl = '/toolbox/compositionSpecies:queryProduct';
				$d->autocompleteBody = fn(\util\FormUi $form, CompositionSpecies $e) => [
					'id' => $e['id'],
					'structure' => $e['plan']['structure']['id'],
				];
				$d->autocompleteDefault = fn(CompositionSpecies $e) => $e['eProductLinked'];
				$d->autocompleteResults = fn(\product\Product $eProduct) => CompositionSpeciesUi::getProductAutocomplete($eProduct);
				break;

			// Vide, l'espèce entre au panier dans l'unité de la communauté : c'est le `placeholder` de
			// `radios()`, comme « Selon la saison » pour la fréquence, et y revenir, c'est suivre la
			// communauté (planche r14). Chaque choix porte ce qu'il efface de ce que la structure a tapé
			// (`data-loss`), que l'envoi confirme (`composition.js`).
			//
			// Le poids d'une pièce ou d'une botte se règle dans le même geste : un groupe par unité pesée,
			// celui de l'unité cochée seul visible, les autres désactivés pour ne rien poster. Chacun est
			// le champ du poids tel que son formulaire rapide le rend, dans son unité : le poids des
			// cultures ou le poids habituel en attente, et d'où il vient.
			//
			// Une variante qu'on ajoute (`unitAsked`, r76 lot 2) part sans unité cochée, pas même celle de
			// la communauté, que `radios()` coche d'office sur une valeur vide et ne propose que sur un
			// champ facultatif : `unit.chosen` tient donc l'obligation à l'envoi. Chaque choix porte alors
			// le prix de la mercuriale de l'espèce dans son unité (`data-price`), et celui de l'unité de
			// l'espèce nue rend les valeurs que la variante en a reprises (`data-copied`, `composition.js`).
			//
			// Un champ rendu par `$d->field` ne nomme pas son groupe : le wrapper est posé ici, pour que
			// les refus de l'unité s'affichent sous elle et non dans le bandeau.
			case 'unit' :
				$d->group = ['data-wrapper' => 'unit'];
				$d->field = function(\util\FormUi $form, CompositionSpecies $e) {

					$values = [];

					foreach($e['cUnitOffered'] as $eUnit) {
						$values[$eUnit['id']] = CompositionSpeciesUi::getUnitLabel($eUnit);
					}

					// Une espèce que la communauté ne compte pas (r48c) n'a pas d'unité à suivre : pas de choix
					// vide, son unité est toujours une de celles qu'on coche.
					$community = $e['eUnitCommunity']->notEmpty();
					$unitCommunityId = $community ? $e['eUnitCommunity']['id'] : NULL;

					if($e['unitAsked']) {
						$selected = NULL;
					} else {
						$selected = $e['unit']->empty() ? '' : $e['unit']['id'];
					}

					$h = '<div class="toolbox-composition-unit">';

						$h .= $form->radios('unit', $values, $e['unit']->empty() ? NULL : $e['unit']['id'], [
							'placeholder' => $community ? CompositionSpeciesUi::getUnitLabel($e['eUnitCommunity']).'<div class="util-annotation">'.s("Unité de la communauté").'</div>' : NULL,
							'required' => ($community === FALSE),
							'callbackRadioAttributes' => function($option, $key) use($e) {

								$eUnit = ($key === NULL) ? $e['eUnitCommunity'] : $e['cUnitOffered'][$key];

								$attributes = [
									'data-loss' => CompositionSpeciesUi::getUnitLoss($e, $eUnit),
									'data-singular' => $eUnit['singular'],
									'data-plural' => $eUnit['plural'],
									'data-by' => s("€").\product\UnitUi::getBy($eUnit),
									'data-step' => \util\TextUi::number($e['stepUnits'][$eUnit['id']]['step'], NULL),
									'data-divisible' => $e['stepUnits'][$eUnit['id']]['divisible'] ? '1' : '0',
									'data-minimum' => \util\TextUi::number($e['stepUnits'][$eUnit['id']]['minimum'], NULL),
								];

								if($e['unitAsked']) {

									$price = $e['pricesUnits'][($key === NULL) ? '' : $key];
									$copied = ($key === NULL) ? $e['eUnitCopied']->empty() : ($e['eUnitCopied']->notEmpty() and $e['eUnitCopied']['id'] === $key);

									$attributes['checked'] = FALSE;
									$attributes['data-price'] = ($price === NULL) ? '' : \util\TextUi::number($price, 2);
									$attributes['data-copied'] = $copied ? '' : NULL;

								}

								return $attributes;

							},
						]);

						foreach($e['weightReferences'] as $unitId => $weightReference) {

							$eUnit = ($unitId === $unitCommunityId) ? $e['eUnitCommunity'] : $e['cUnitOffered'][$unitId];
							$value = ($unitId === $unitCommunityId) ? '' : $unitId;
							$hidden = ($value !== $selected);

							$eCompositionSpeciesWeighed = new CompositionSpecies([
								'plan' => $e['plan'],
								'plant' => $e['plant'],
								'eUnit' => $eUnit,
								'weight' => ($weightReference['from'] === CompositionLib::WEIGHT_TYPED) ? $weightReference['weight'] : NULL,
								'weightReference' => $weightReference,
							]);

							$h .= $form->dynamicGroup($eCompositionSpeciesWeighed, 'weight', function(\PropertyDescriber $d) use($eUnit, $value, $hidden) {

								$attributes = $d->attributes;

								$d->label = s("Poids d'une {unit}", ['unit' => encode($eUnit['singular'])]);
								$d->attributes = fn(...$arguments) => $attributes(...$arguments) + ['disabled' => $hidden ? 'disabled' : NULL];
								$d->group = [
									'data-unit' => $value,
									'class' => $hidden ? 'hide' : '',
								];

							});

						}

					$h .= '</div>';

					return $h;

				};
				break;

		}

		return $d;

	}

}
?>
