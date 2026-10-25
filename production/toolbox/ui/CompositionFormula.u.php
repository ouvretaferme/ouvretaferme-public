<?php
namespace toolbox;

/**
 * Une taille de panier, que le code appelle une formule (`CompositionFormula`). L'écran dit toujours
 * « panier » (r51b, planche r51 : « taille » a quitté tous les textes), et le mot nomme donc à la
 * fois ce qu'on vend et ce qu'on livre une semaine donnée : une phrase n'en garde qu'un sens.
 */
class CompositionFormulaUi {

	/**
	 * « Ajouter un panier » : le panneau de création du dépôt (`selling\CustomerGroupUi::create()`),
	 * qui pose les trois questions d'une taille au départ, le nom, le prix et les paniers par
	 * semaine, et la taille sur laquelle elle s'appuie (lot r17), aucune par défaut. Une base plus
	 * chère que le prix tapé se refuse à l'envoi, sous le champ (`CompositionFormula::base.cheaper`).
	 *
	 * Le prix est vide : c'est à la structure de le donner (arbitrage sur la planche v10,
	 * « prix proposé : aucun »). Les repères du prix d'un panier partagé sont écrits sous le champ, là
	 * où le prix se choisit ; sans panier partagé, `$prices` est `NULL` et il n'y en a aucun.
	 *
	 * Il s'ouvre depuis « Modifier les paniers » (r51b, `CompositionUi::getSettingsPanel()`), par-dessus
	 * lui, et lui rend ce qu'il en a reçu : `$formula`, la taille ouverte, et `$origin`, l'onglet d'où
	 * part la roue, que la réponse rend.
	 */
	public function create(CompositionFormula $eCompositionFormula, ?array $prices, ?int $formula, string $origin): \Panel {

		$eCompositionPlan = $eCompositionFormula['plan'];

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/compositionFormula:doCreate');

			$h .= $form->asteriskInfo();

			$h .= $form->hidden('structure', $eCompositionPlan['structure']['id']);
			$h .= $form->hidden('formula', $formula);
			$h .= $form->hidden('origin', $origin);

			$h .= $form->dynamicGroups($eCompositionFormula, ['name*', 'price*', 'baskets*', 'base'], [
				'price*' => function(\PropertyDescriber $d) use($prices) {
					if($prices !== NULL) {
						$d->after = \util\FormUi::info(CompositionUi::getPrices($prices));
					}
				},
			]);

			$h .= $form->group(
				content: $form->submit(s("Ajouter"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'toolbox-composition-formula-create',
			title: s("Ajouter un panier"),
			body: $h
		);

	}

	/**
	 * Le prix, l'écart, les bornes du nombre de produits et la base recomposent toute la taille
	 * (`CompositionFormulaLib::updateByPlan()`) : une semaine corrigée à la main y perd ses corrections.
	 * « Modifier les paniers » ne se confirme que dans ce cas, et le message dit lesquelles partent,
	 * parce que « vos modifications seront perdues » ne le dit pas (planche v11, « avertissement »).
	 * `NULL` pour une taille sans correction, qui se recompose sans question.
	 *
	 * Le panneau pose la phrase sur la section de la taille (`data-loss`), et l'envoi l'ajoute à sa
	 * confirmation si l'un de ces champs a changé (`composition.js`). Les semaines corrigées sont
	 * préparées à l'ouverture du panneau (`CompositionPlanLib::loadSettings()`).
	 */
	public static function getRecomposeLoss(CompositionFormula $eCompositionFormula): ?string {

		$weeks = $eCompositionFormula['weeksAmended'];

		if($weeks === []) {
			return NULL;
		}

		return s("Changer le prix, l'écart, le nombre de produits ou l'appui de « {name} » recompose ses semaines.", ['name' => encode($eCompositionFormula['name'])]).' '.p(
			"Votre correction de la semaine {weeks} reviendra à la proposition.",
			"Vos corrections des semaines {weeks} reviendront à la proposition.",
			count($weeks),
			['weeks' => self::getEnumeration($weeks)]
		);

	}

	/**
	 * « Rejouer les paniers » (`CompositionFormulaLib::replay()`) : même règle que le prix et l'écart,
	 * il ne se confirme que si la formule porte une correction faite à la main. Il ne la rend pas à
	 * la proposition, la proposition elle-même change : le message dit qu'elle s'efface (planche v11,
	 * É13). Les semaines corrigées et les autres formules sont préparées avec l'écran
	 * (`CompositionPlanLib::getForScreen()`).
	 */
	public static function getReplayConfirm(CompositionFormula $eCompositionFormula): ?string {

		$weeks = $eCompositionFormula['weeksAmended'];

		if($weeks === []) {
			return NULL;
		}

		$message = s("{recompose} et efface {corrections}.", [
			'recompose' => p(
				"Rejouer « {name} » recompose sa semaine",
				"Rejouer « {name} » recompose ses {value} semaines",
				$eCompositionFormula['summary']['weeks'],
				['name' => encode($eCompositionFormula['name'])]
			),
			'corrections' => p(
				"votre correction de la semaine {weeks}",
				"vos corrections des semaines {weeks}",
				count($weeks),
				['weeks' => self::getEnumeration($weeks)]
			),
		]);

		return $message.self::getOthersStay($eCompositionFormula).' '.s("Voulez-vous continuer ?");

	}

	/**
	 * Les autres formules du plan, qu'un geste sur celle-ci laisse telles quelles, en une phrase
	 * précédée de son espace ; rien quand la formule est seule.
	 */
	protected static function getOthersStay(CompositionFormula $eCompositionFormula): string {

		$names = array_map(fn(string $name) => s("« {name} »", ['name' => encode($name)]), $eCompositionFormula['formulasOther']);

		if($names === []) {
			return '';
		}

		return ' '.p(
			"{names} ne bouge pas.",
			"{names} ne bougent pas.",
			count($names),
			['names' => self::getEnumeration($names)]
		);

	}

	public static function getEnumeration(array $words): string {

		if(count($words) === 1) {
			return reset($words);
		}

		$last = array_pop($words);

		return s("{list} et {last}", ['list' => implode(', ', $words), 'last' => $last]);

	}

	/**
	 * Les tailles proposées au champ « S'appuie sur » de « Modifier les paniers » et du panneau d'ajout,
	 * « nom, prix », avec « Aucun » en tête. `$name` est le nom du champ, que « Modifier les paniers »
	 * indexe par taille (`base[<id>]`). Celles qu'une règle refuse restent, désactivées, avec leur raison sous leur
	 * nom (`field-radio-group-reason`) : on voit pourquoi elles ne se choisissent pas. Les choix et
	 * leurs refus sont préparés par la page (`CompositionFormulaLib::getBaseChoices()`).
	 */
	public static function getBaseField(\util\FormUi $form, CompositionFormula $eCompositionFormula, string $name = 'base'): string {

		$values = [];
		$closed = [];

		foreach($eCompositionFormula['baseChoices'] as $id => ['eCompositionFormula' => $eCompositionFormulaChoice, 'refusal' => $refusal, 'cited' => $cited]) {

			$label = s("{name}, {price}", [
				'name' => encode($eCompositionFormulaChoice['name']),
				'price' => \util\TextUi::money((float)$eCompositionFormulaChoice['price'], precision: 0),
			]);

			if($refusal === NULL) {
				$values[$id] = $label;
				continue;
			}

			$names = self::getEnumeration(array_map(fn(string $name) => encode($name), $cited));

			$reason = match($refusal) {
				'serving' => p("{names} s'appuie déjà sur ce panier", "{names} s'appuient déjà sur ce panier", count($cited), ['names' => $names]),
				'derived' => s("S'appuie déjà sur {names}", ['names' => $names]),
				'pricier' => s("Plus cher que {names}", ['names' => $names]),
				'priceSame' => s("Au même prix que {names}", ['names' => $names]),
				'lines' => s("Plus de produits que {names}", ['names' => $names]),
			};

			$values[$id] = \util\FormUi::radioOption($label, $reason);
			$closed[] = $id;

		}

		return $form->radios($name, $values, $eCompositionFormula['base']->empty() ? NULL : $eCompositionFormula['base']['id'], [
			'placeholder' => s("Aucun"),
			'callbackRadioAttributes' => fn($option, $key) => in_array($key, $closed, TRUE) ? ['disabled' => 'disabled'] : [],
		]);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = CompositionFormula::model()->describer($property, [
			'base' => s("S'appuie sur"),
			'name' => s("Nom"),
			'price' => s("Prix du panier"),
			'baskets' => s("Paniers vendus par semaine"),
			'gap' => s("Écart maximum"),
			'linesMin' => s("Minimum de produits"),
			'linesMax' => s("Maximum de produits"),
		]);

		switch($property) {

			case 'base' :
				$d->field = fn(\util\FormUi $form, CompositionFormula $e) => CompositionFormulaUi::getBaseField($form, $e);
				break;

			case 'name' :
				$d->attributes = ['placeholder' => s("Ex. : Petit panier")];
				break;

			case 'baskets' :
				$d->append = s("paniers");
				break;

			case 'price' :
				$d->append = s("€");
				break;

			// Le pourcentage ne dit pas ce qu'il vaut pour ce prix : le montant est préparé par la page
			// (`gapAmount`), l'étendue vient du schéma.
			case 'gap' :
				$d->append = s("%");
				$d->after = fn(\util\FormUi $form, CompositionFormula $e) => \util\FormUi::info(s("De {min} à {max} %, soit {amount} autour de {price} à {gap} %.", [
					'min' => CompositionFormula::model()->getPropertyRange('gap')[0],
					'max' => CompositionFormula::model()->getPropertyRange('gap')[1],
					'amount' => \util\TextUi::money($e['gapAmount'], precision: 2),
					'price' => \util\TextUi::money((float)$e['price'], precision: 0),
					'gap' => $e['gap'],
				]));
				break;

			// L'autre borne est rappelée sous le champ, puisque c'est elle qui la limite (r11).
			case 'linesMin' :
				$d->append = s("produits");
				$d->after = fn(\util\FormUi $form, CompositionFormula $e) => \util\FormUi::info(s("De {min} à {max}, sans dépasser le maximum de {linesMax}.", [
					'min' => CompositionFormula::model()->getPropertyRange('linesMin')[0],
					'max' => CompositionFormula::model()->getPropertyRange('linesMin')[1],
					'linesMax' => $e['linesMax'],
				]));
				break;

			case 'linesMax' :
				$d->append = s("produits");
				$d->after = fn(\util\FormUi $form, CompositionFormula $e) => \util\FormUi::info(s("De {min} à {max}, sans descendre sous le minimum de {linesMin}.", [
					'min' => CompositionFormula::model()->getPropertyRange('linesMax')[0],
					'max' => CompositionFormula::model()->getPropertyRange('linesMax')[1],
					'linesMin' => $e['linesMin'],
				]));
				break;

		}

		return $d;

	}

}
?>
