<?php
namespace structure;

class CommunityUi {

	public function __construct() {
		\Asset::css('structure', 'community.css');
	}

	/** L'écran du groupe, qui demande. */
	const POINT_COMMUNITY = 'community';

	/** L'écran de la ferme, qui accorde. */
	const POINT_PRODUCER = 'producer';

	/**
	 * Ce qu'un droit ouvre, dans les mots du groupe qui le demande ou de la ferme qui l'accorde.
	 *
	 * ⚠️ Publique parce que quatre écrans la lisent — le réglage du groupe, l'entrée d'une ferme,
	 * le panneau de la ferme et sa carte —, et qu'un droit nommé autrement d'un écran à l'autre
	 * ferait douter qu'il s'agit du même. Le second membre dit ce que le mot ne dit pas :
	 * « Production » seul ne porte pas la règle, et qui coche sans savoir ouvre les données de
	 * fermes qui ne sont pas les siennes.
	 */
	public static function getRightLabels(string $point = self::POINT_PRODUCER): array {

		// ⚠️ **Le nom ne change pas, la phrase si.** Le même texte ne peut pas dire à la fois ce
		// qu'un groupe demande et ce qu'une ferme donne : « votre plan de culture » n'a pas de sens
		// sur l'écran d'un groupe, et « le plan de culture de la ferme » en perd sur celui de la
		// ferme. Une seule table, deux colonnes de texte, le point de vue en argument — la source
		// reste unique, ce qui est ce qui a rendu la correction tenable en un endroit.
		//
		// ⚠️ Le système, le territoire et les ateliers n'y sont plus : depuis le 10/09/2026 ils
		// sont le socle, partagés dès le rattachement et sans case. Ce droit ne porte plus que ce
		// que la ferme **fait cette saison**.
		// ⚠️ **La description dit ce que le droit ouvre, jamais ce que son nom dit déjà.** Elle est
		// écrite depuis le code et non depuis la maquette : `shareProduction` ouvre `canRead()` sur
		// la ferme, donc `/ferme/{id}/series`, `/assolement` et `/rotation`, qui portent les
		// espèces, les variétés, les surfaces, les dates de semis, de plantation et de récolte, et
		// les planches occupées. Il n'ouvre ni le planning, ni le prévisionnel financier, ni les
		// semences et plants, ni les analyses, qui ont chacun leur propre porte.
		$texts = [
			'shareProduction' => [
				self::POINT_COMMUNITY => s("Les espèces et variétés cultivées, leurs surfaces, leurs dates et les planches qu'elles occupent, saison par saison."),
				self::POINT_PRODUCER => s("Vos espèces et variétés, leurs surfaces, leurs dates et les planches qu'elles occupent, saison par saison."),
			],
			'shareProductionExport' => [
				self::POINT_COMMUNITY => s("Copier les séries d'une ferme dans une autre, ou les télécharger au format CSV."),
				self::POINT_PRODUCER => s("Copier vos séries dans une autre ferme, ou les télécharger au format CSV."),
			],
			'shareProductionDuplicate' => [
				self::POINT_COMMUNITY => s("Dupliquer la production d'une ferme, toutes saisons comprises, sans le nom des personnes."),
				self::POINT_PRODUCER => s("Dupliquer la production de votre ferme, toutes saisons comprises, sans le nom des personnes."),
			],
			'shareSellingDuplicate' => [
				self::POINT_COMMUNITY => s("Dupliquer les produits, les rapports et les ventes regroupées par semaine d'une ferme, sans ses clients."),
				self::POINT_PRODUCER => s("Dupliquer vos produits, vos rapports et vos ventes regroupées par semaine, sans vos clients."),
			],
			'contributesQuotation' => [
				self::POINT_COMMUNITY => s("Les prix des fermes alimentent les médianes du groupe. Personne n'y voit le prix d'une ferme en particulier."),
				self::POINT_PRODUCER => s("Vos prix alimentent les médianes du groupe. Personne n'y voit le prix d'une ferme en particulier."),
			],
		];

		// ⚠️ Le possessif suit le point de vue comme la phrase : « Mon plan de culture » est juste
		// sur l'écran de la ferme **parce que** l'écran est le sien, et désignerait un plan que le
		// groupe n'a pas sur celui du groupe.
		$names = [
			'shareProduction' => [
				self::POINT_COMMUNITY => s("Plan de culture"),
				self::POINT_PRODUCER => s("Mon plan de culture"),
			],
			'shareProductionExport' => [
				self::POINT_COMMUNITY => s("Copie du plan de culture"),
				self::POINT_PRODUCER => s("Copie de mon plan de culture"),
			],
			'shareProductionDuplicate' => [
				self::POINT_COMMUNITY => s("Duplication de la production"),
				self::POINT_PRODUCER => s("Duplication de ma production"),
			],
			'shareSellingDuplicate' => [
				self::POINT_COMMUNITY => s("Duplication de la commercialisation"),
				self::POINT_PRODUCER => s("Duplication de ma commercialisation"),
			],
			'contributesQuotation' => [
				self::POINT_COMMUNITY => s("Relevés de prix"),
				self::POINT_PRODUCER => s("Mes relevés de prix"),
			],
		];

		$labels = [];

		foreach($names as $right => $name) {
			$labels[$right] = [$name[$point], $texts[$right][$point]];
		}

		return $labels;

	}

	/**
	 * Les deux publics dans les mots du type de groupe.
	 *
	 * Le vocabulaire vient de `getText()` comme partout ailleurs dans ce chantier : une promotion
	 * dit « les formateurs » et « les autres étudiants » là où un réseau dit autre chose.
	 *
	 * ⚠️ **Une colonne qu'on coche nomme le geste, pas le public.** Au-dessus d'une case, « l'équipe
	 * du réseau » ne dit pas ce que cocher fait ; « Permettre l'accès à l'équipe du réseau » le dit.
	 * La carte, elle, ne permet rien : elle rend un état, et garde le nom du public seul.
	 */
	public static function getAudienceLabels(Structure $eStructure, bool $allow = FALSE, string $point = self::POINT_COMMUNITY): array {

		$class = StructureUi::getClass($eStructure);

		// Trois formes et non deux : le seul nom du public là où l'écran rend un état, le geste
		// demandé là où le groupe coche, le geste accordé là où la ferme coche. Confondre les deux
		// derniers ferait dire au groupe qu'il s'autorise lui-même, et à la ferme qu'elle demande
		// ce qu'elle est en train de donner.
		if($allow === FALSE) {
			$suffix = '';
		} else if($point === self::POINT_PRODUCER) {
			$suffix = 'Grant';
		} else {
			$suffix = 'Allow';
		}

		return [
			Community::STAFF => $class::getText($eStructure, 'audienceStaff'.$suffix),
			Community::MEMBER => $class::getText($eStructure, 'audienceMember'.$suffix),
		];

	}

	public static function url(Community $eCommunity): string {

		$eCommunity->expects(['fqn']);

		return self::domain().'/'.$eCommunity['fqn'];

	}

	public static function domain(bool $showProtocol = TRUE): string {

		return $showProtocol ? match(LIME_ENV) {
			'dev' => 'http://'.StructureSetting::$communityDomain,
			'prod' => 'https://'.StructureSetting::$communityDomain
		} : StructureSetting::$communityDomain;

	}

	/**
	 * Les bornes qu'un champ de nombre porterait s'il était rendu par le cadriciel.
	 *
	 * `$d->field` court-circuite `FormUi::dynamicField()`, qui les pose depuis le modèle
	 * (`framework/util/ui/Form.u.php:743-773`) : un champ composé écrit à la main les
	 * perd, et le navigateur refuse alors la saisie **avant** l'événement `submit` que
	 * `form.js` écoute — sans `step`, un facteur décimal ne peut pas être posté du tout,
	 * et l'échec est muet. Lues sur le modèle et jamais recopiées.
	 */
	public static function getNumberRange(string $property): array {

		[$min, $max] = Community::model()->getPropertyRange($property);

		$attributes = ['min' => $min, 'max' => $max];

		if(Community::model()->getPropertyType($property) === 'decimal') {
			$attributes['step'] = (string)(1 / pow(10, Community::model()->getPropertyDecimal($property)));
		}

		return $attributes;

	}

	public static function p(string $property): \PropertyDescriber {

		// ⚠️ Les droits prennent leur nom de la table des libellés et ne le recopient pas :
		// c'est ce nom-là qu'un refus doit citer, puisque c'est celui que l'écran affiche. Une
		// copie ici aurait divergé au premier renommage, comme la table des jetons l'a fait.
		$names = CommunityUi::getRightLabels(CommunityUi::POINT_COMMUNITY);

		$d = Community::model()->describer($property, [
			'quotationMinimum' => s("Nombre de fermes requis pour publier un prix"),
			'quotationTrimLow' => s("Prix écartés du calcul"),
			'quotationRatioHigh' => s("Prix très éloignés de la moyenne"),
			'color' => s("Couleur du groupe"),
			'fqn' => s("Adresse de la page du réseau"),
			'quotationHelp' => s("Mode d'emploi affiché en tête de votre page de saisie"),
			'quotationResponseMinimum' => s("Nombre minimal de prix demandés sur votre page de saisie"),
			'quotationRegion' => s("Région de comparaison"),
			'visibility' => s("Qui peut voir ce groupe"),
			'shareProduction' => $names['shareProduction'][0],
			'shareProductionExport' => $names['shareProductionExport'][0],
			'shareProductionDuplicate' => $names['shareProductionDuplicate'][0],
			'shareSellingDuplicate' => $names['shareSellingDuplicate'][0],
			'contributesQuotation' => $names['contributesQuotation'][0],
			'rules' => s("Les règles de votre groupe que les producteurs doivent accepter à l'entrée"),
			'learningOnlineConfirm' => s("Inscription à une formation en ligne"),
			'learningConditions' => s("Conditions de participation"),
			'customDesign' => s("Thème"),
		]);

		switch($property) {

			// ⚠️ Ces deux-là ne se masquent plus quand les relevés sont éteints : leur écran
			// n'existe alors plus du tout, l'interrupteur ayant rejoint le paramétrage du groupe.
			// Ils disent comment la mercuriale se comporte une fois allumée, pas si elle l'est.
			case 'color' :
				$d->after = \util\FormUi::info(s("Elle peint le bandeau de vos écrans et celui de votre page de saisie publique."));
				break;

			case 'quotationResponseMinimum' :
				$d->after = \util\FormUi::info(s("Une réponse qui en renseigne moins est refusée à la première étape."));
				break;

			case 'quotationMinimum' :
				$d->after = \util\FormUi::info(s("En dessous de ce nombre, la cotation n'est pas publiée. Le plancher est de cinq : plus bas, un adhérent retrouve le prix de ses voisins à partir du sien et de la médiane."));
				break;

			// Les deux bornes dans un seul groupe, et le groupe nommé pour les deux : un refus
			// sur l'une ou sur l'autre se pose alors sur le champ qui le concerne, `data-wrapper`
			// se lisant en liste. Le describer de quotationTrimHigh n'est jamais demandé, son
			// champ étant rendu ici.
			case 'quotationTrimLow' :

				$d->wrapper = 'quotationTrimLow quotationTrimHigh';

				// Les bornes se lisent **avant** la fermeture et non dedans : `dynamicField()`
				// l'appelle par `Closure::call()` (`Form.u.php:522`), qui rebase la portée sur
				// `PropertyDescriber` — un `self::` y désignerait cette classe-là.
				$rangeLow = self::getNumberRange('quotationTrimLow');
				$rangeHigh = self::getNumberRange('quotationTrimHigh');

				$d->field = fn(\util\FormUi $form, Community $e) =>
					'<div class="flex-align-center flex-wrap">'.
						$form->inputGroup(
							$form->number('quotationTrimLow', $e['quotationTrimLow'], $rangeLow).
							$form->addon(s("% en bas"))
						).
						$form->inputGroup(
							$form->number('quotationTrimHigh', $e['quotationTrimHigh'], $rangeHigh).
							$form->addon(s("% en haut"))
						).
					'</div>';

				break;

			case 'quotationRegion' :
				$d->field = 'select';
				$d->placeholder = s("Aucune");
				break;

			// ⚠️ **Ni `field` ni `attributes` sur les trois droits de partage** : ils ne se rendent
			// plus par un groupe dynamique. Le tableau droit × public de `StructureCommunityUi`
			// les porte tous les trois d'un coup, parce qu'un droit se donne **à quelqu'un** et
			// qu'une case par droit ne sait pas dire à qui. Le describer garde leur libellé, qui
			// sert encore aux messages d'erreur.

			// Dans l'ordre de la planche validée : les deux régimes simples, puis le défaut, qui les
			// combine selon la réponse de la personne.
			case 'learningOnlineConfirm' :

				$d->field = 'radio';
				$d->required = TRUE;

				$d->values = [
					Community::IMMEDIATE => '<div class="field-radio-group-label">'.s("Acquise tout de suite").'</div><div class="field-radio-group-reason">'.s("La personne est inscrite dès l'envoi du formulaire et peut commencer.").'</div>',
					Community::ALWAYS => '<div class="field-radio-group-label">'.s("Demande à confirmer").'</div><div class="field-radio-group-reason">'.s("Chaque inscription attend d'être confirmée depuis le suivi de la formation.").'</div>',
					Community::FUNDING => '<div class="field-radio-group-label">'.s("Demande à confirmer en cas de financement").'</div><div class="field-radio-group-reason">'.s("La personne qui dit demander une prise en charge attend d'être confirmée. Les autres sont inscrites dès l'envoi.").'</div>',
				];

				break;

			case 'visibility' :
				
				$d->field = 'radio';
				$d->required = TRUE;

				$private = '<h4>'.s("Personne, sauf sur invitation").'</h4>';
				$private .= '<p>'.s("Le groupe n'apparaît nulle part. On y entre par un lien ou un code d'invitation.").'</p>';

				$public = '<h4>'.s("Tous les utilisateurs de {siteName}").'</h4>';
				$public .= '<p>'.s("Le groupe est visible, mais on y entre toujours sur invitation.").'</p>';

				$d->values = [
					Community::PRIVATE => $private,
					Community::PUBLIC => $public
				];
				
				break;

			case 'customDesign' :
				$d->values = [
					'ami' => s("Ami"),
					'bucolique' => s("Bucolique"),
					'bulles' => s("Bulles"),
					'classeur' => s("Classeur"),
					'craie' => s("Craie"),
					'diplome' => s("Diplôme"),
					'futur' => s("Futur"),
					'kraft' => s("Kraft"),
					'parcours' => s("Parcours"),
					'profil' => s("Profil"),
				];
				break;

			case 'fqn' :
				$d->field = fn(\util\FormUi $form, Community $e) => $form->inputGroup(
					$form->addon(CommunityUi::domain(FALSE).'/').
					$form->text('fqn', $e['fqn'])
				);
				$d->after = \util\FormUi::info(s("Uniquement des chiffres, des lettres ou des tirets"));
				break;

		}

		return $d;

	}

}
?>
