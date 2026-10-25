<?php
namespace plant;

class VarietyRatingAiUi {

	public function __construct() {

		\Asset::css('plant', 'varietyRating.css');
		\Asset::js('plant', 'varietyRating.js');

	}

	public function display(\structure\Structure $eStructure, \Collection $cPlant, Plant $ePlantSelected): string {

		if($cPlant->empty()) {
			return $this->getEmpty($eStructure);
		}

		$cPlantShown = $ePlantSelected->notEmpty() ?
			new \Collection([$ePlantSelected]) :
			$cPlant;

		$h = $this->getTools($eStructure, $cPlant, $ePlantSelected);

		// util-table porte le châssis : la surface blanche, les filets, le survol et
		// les gouttières. Les colonnes se déclarent ici, les rangs les héritent en
		// subgrid.
		$h .= '<div id="variety-rating" class="util-table util-table-bordered util-table-hover">';

			foreach($cPlantShown as $ePlantShown) {
				$h .= $this->getPlant($ePlantShown);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le choix de l'espèce, posé juste au-dessus de la liste qu'il filtre : le titre
	 * de la page en est séparé par le bandeau et la frise de la campagne, et un
	 * contrôle qui agit sur une liste se pose contre elle.
	 *
	 * Le filtre vit dans l'url et non dans le navigateur : après une écriture, la
	 * page rechargée montre la même liste, et le lien se partage.
	 *
	 * Un sélecteur plutôt que des jetons : une structure cultive couramment
	 * cinquante espèces, qui ne tiennent pas sur une ligne d'options.
	 */
	private function getTools(\structure\Structure $eStructure, \Collection $cPlant, Plant $ePlantSelected): string {

		if($cPlant->empty()) {
			return '';
		}

		$form = new \util\FormUi();

		$values = [];

		foreach($cPlant as $ePlant) {
			$values[$ePlant['id']] = $ePlant['name'].' ('.$ePlant['cVariety']->count().')';
		}

		$h = '<div class="variety-rating-tools">';

			$h .= $form->inputGroup(
				$form->addon(s("Espèce")).
				$form->select('plant', $values, $ePlantSelected, [
					'placeholder' => s("Toutes les espèces"),
					// L'URL de base est portée ici : le JS n'a plus qu'à y accrocher
					// l'espèce choisie, sans rien savoir de la route.
					'data-url' => \structure\StructureUi::urlIntelligenceVarietyRating($eStructure),
					'onchange' => 'VarietyRating.changePlant(this)',
				])
			);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une espèce et ses variétés, rangées de la plus employée à la moins employée.
	 *
	 * Au-delà des premières, les lignes sont servies mais repliées : une structure
	 * qui cultive quarante-quatre variétés de tomate n'a pas d'avis sur les
	 * quarante-quatre, et les déplier ne vaut pas un aller-retour au serveur, toute
	 * la liste tenant déjà dans la seule requête de l'écran.
	 */
	private function getPlant(Plant $ePlant): string {

		$cVariety = $ePlant['cVariety'];

		// La bande est dans le groupe et non à côté : quand la dernière variété d'une
		// espèce est ignorée, l'intertitre s'en va avec elle, sans que le serveur ait à
		// dire lequel des deux retirer.
		$h = '<div id="variety-plant-'.$ePlant['id'].'" class="variety-plant">';

			$h .= '<div class="util-table-title variety-line-plant">';
				$h .= PlantUi::getVignette($ePlant, '2rem');
				$h .= '<span class="variety-line-plant-name">'.encode($ePlant['name']).'</span>';
			$h .= '</div>';

			$position = 0;

			foreach($cVariety as $eVariety) {
				$h .= $this->getLine($ePlant, $eVariety, ($position++ >= PlantSetting::RATING_VARIETIES));
			}

			$rest = $cVariety->count() - PlantSetting::RATING_VARIETIES;

			if($rest > 0) {

				$h .= '<div class="util-table-full variety-line-more">';
					$h .= '<a data-action="variety-rating-more" data-plant="'.$ePlant['id'].'">';
						$h .= p("Afficher la variété suivante", "Afficher les {value} variétés suivantes", $rest);
					$h .= '</a>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/** Une variété repliée : son nom, et sa note globale. */
	public function getLine(Plant $ePlant, Variety $eVariety, bool $isRest = FALSE): string {

		$h = '<div id="variety-line-'.$eVariety['id'].'" class="util-table-row variety-line'.($isRest ? ' variety-line-rest' : '').'" data-variety="'.$eVariety['id'].'">';

			// La ligne entière ouvre le tiroir : le chevron et le nom n'ont donc pas de
			// geste propre, ils disent seulement où cliquer. Le chevron pivote quand le
			// tiroir est là — l'état se lit sur le document, il n'est pas tenu en double.
			$h .= '<span class="variety-line-toggle">'.\Asset::icon('chevron-right').'</span>';

			$h .= '<span class="variety-line-name">';
				$h .= encode($eVariety['name']);
			$h .= '</span>';

			// Ce que la structure a déjà fait de la variété, en clair sur la ligne :
			// noter demande de se souvenir, et cette phrase est le souvenir.
			$h .= '<div class="variety-line-usage">'.$this->getUsageHeadline($eVariety['usage']).'</div>';

			$h .= $this->getOverall($eVariety);

		$h .= '</div>';

		return $h;

	}

	/**
	 * La note globale posée sur la ligne repliée : elle se donne sans rien ouvrir,
	 * et le clic ouvre le tiroir avec la valeur déjà écrite.
	 *
	 * Rendue seule après un enregistrement, où la note a pu changer dans le tiroir.
	 *
	 * Le tiroir ouvert, les étoiles cèdent la place à la croix qui le referme : la
	 * note se donne alors dans le tiroir, à côté des critères, et deux contrôles pour
	 * la même note à quelques centimètres l'un de l'autre feraient hésiter. Les deux
	 * sont servis, c'est le CSS qui montre l'un ou l'autre — l'état vit sur le
	 * document, la ligne n'a pas à repartir du serveur pour changer de contrôle.
	 */
	public function getOverall(Variety $eVariety): string {

		$h = '<div class="variety-line-rating">';

			// Cinq étoiles vides sur une variété jamais ouverte, répétées sur trois cents
			// lignes, ne disent rien et couvrent la liste. Elles n'apparaissent qu'une fois
			// qu'on s'est prononcé — vides comprises, si l'avis ne porte que des critères.
			if($eVariety['ratingStatus'] === Variety::RATED) {
				$h .= $this->getCriterion(new \util\FormUi(), NULL, '', $eVariety['ratingOverall']);
			}

			$h .= '<a class="variety-line-close" data-action="variety-rating-close" title="'.s("Fermer").'">'.\Asset::icon('x-lg').'</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le détail d'une variété, ouvert sous sa ligne.
	 *
	 * Deux colonnes sur grand écran : les notes d'un côté, ce qui décrit la variété
	 * de l'autre, pour que le tiroir tienne dans une hauteur d'écran et ne repousse
	 * pas la liste hors de vue.
	 */
	public function getDrawer(\structure\Structure $eStructure, Variety $eVariety, \Collection $cCriterion, \Collection $cCriterionCompletion, \Collection $cTag): string {

		// Intitulés au-dessus des champs : le tiroir tient dans une demi-largeur, et
		// une question de deux lignes posée à gauche d'une case y mange le tiers de la
		// place que le champ réclame.
		$form = new \util\FormUi(['columns' => 1]);

		$h = '<div id="variety-drawer-'.$eVariety['id'].'" class="util-table-full variety-drawer">';

			$h .= $form->openAjax('/plant/varietyRating:doRate');

				// doUpdateProperties() résout la variété sur POST('id') ; la structure n'a
				// pas à être postée, canUpdate la lit sur la variété elle-même.
				$h .= $form->hidden('id', $eVariety);

				$h .= $this->getUsage($eVariety['usage']);

				$h .= '<div class="util-info">'.s("Tous les champs sont facultatifs, ne remplissez que ceux pour lesquels vous avez réellement un avis.").'</div>';

				$h .= '<div class="variety-drawer-columns">';

					$h .= '<div>';
						$h .= $this->getCriteria($form, $eVariety, $cCriterion, $cCriterionCompletion);
					$h .= '</div>';

					$h .= '<div>';
						$h .= $this->getTags($form, $cTag, $eVariety['tags']);
						$h .= $this->getSeedWeight($form, $eVariety);
						$h .= $form->dynamicGroups($eVariety, ['ratingComment']);
					$h .= '</div>';

				$h .= '</div>';

				$h .= '<div class="variety-rating-actions">';

					$h .= $form->submit(s("Enregistrer la note"), ['data-waiter' => s("Enregistrement...")]);

					$h .= $this->getSecondary($eVariety);

				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le geste opposé à l'enregistrement, un seul des deux à la fois.
	 *
	 * Qui s'est prononcé peut retirer son avis ; qui ne s'est pas prononcé peut
	 * retirer la variété de sa liste. Proposer les deux ensemble ferait passer pour
	 * équivalents un retrait d'avis et un retrait de variété, alors qu'ignorer une
	 * variété notée jetterait la note avec elle.
	 *
	 * Ignorer est sans retour depuis cet écran, la variété n'y étant plus affichée :
	 * d'où la confirmation, qui le dit avant plutôt qu'après.
	 */
	private function getSecondary(Variety $eVariety): string {

		if($eVariety['ratingStatus'] === Variety::RATED) {

			$body = ['id' => $eVariety['id'], 'ratingOverall' => '', 'ratingComment' => ''];

			$h = '<a data-ajax="/plant/varietyRating:doDeleteRating" '.attrAjaxBody($body).' class="btn btn-outline-danger" data-confirm="'.s("Supprimer votre avis sur cette variété ?").'">';
				$h .= s("Supprimer mon avis");
			$h .= '</a>';

			return $h;

		}

		$body = ['id' => $eVariety['id'], 'ratingStatus' => Variety::SKIPPED];

		$h = '<a data-ajax="/plant/varietyRating:doSkip" '.attrAjaxBody($body).' class="btn btn-outline-primary" data-confirm="'.s("Cette variété ne vous sera plus proposée dans cette liste. Continuer ?").'">';
			$h .= s("Ignorer cette variété");
		$h .= '</a>';

		return $h;

	}

	private function getCriteria(\util\FormUi $form, Variety $eVariety, \Collection $cCriterion, \Collection $cCriterionCompletion): string {

		$cVarietyRating = $eVariety['cVarietyRating'];

		$h = '<div class="variety-rating-criteria">';

			// Critères propres à la plante, puis ceux de la communauté, puis la note
			// globale, qui reste transverse et n'est donc pas un critère.
			//
			// Chaque groupe porte son intitulé : les deux se notent de la même façon,
			// et rien d'autre ne dirait lesquels viennent de soi.
			if($cCriterion->notEmpty()) {
				$h .= '<div class="variety-rating-criteria-title">'.s("Vos critères").'</div>';
			}

			foreach($cCriterion as $eCriterion) {

				$eVarietyRating = $cVarietyRating[$eCriterion['id']] ?? NULL;

				// Nom indexé par critère : c'est ce que buildIndex() attend pour valider
				// chaque note contre le schéma.
				$h .= $this->getCriterion($form, 'value['.$eCriterion['id'].']', $eCriterion['name'], $eVarietyRating['value'] ?? NULL);

			}

			if($cCriterionCompletion->notEmpty()) {

				// Le filet ne sépare que de quelque chose : sans critère propre au-dessus,
				// il tomberait sous la frise d'usage et découperait le tiroir au hasard.
				$separatorCommunity = $cCriterion->notEmpty() ? ' is-separated' : '';

				$h .= '<div class="variety-rating-criteria-title'.$separatorCommunity.'">'.s("Les critères préférés de la communauté").'</div>';

				foreach($cCriterionCompletion as $eCriterion) {

					$eVarietyRating = $cVarietyRating[$eCriterion['id']] ?? NULL;

					$h .= $this->getCriterion($form, 'value['.$eCriterion['id'].']', $eCriterion['name'], $eVarietyRating['value'] ?? NULL);

				}

			}

			// Le séparateur ne se justifie que s'il y a des critères au-dessus.
			$separator = ($cCriterion->notEmpty() or $cCriterionCompletion->notEmpty()) ? 'variety-rating-criterion-overall' : '';

			$h .= $this->getCriterion($form, 'ratingOverall', '<b>'.VarietyUi::p('ratingOverall')->label.'</b>', $eVariety['ratingOverall'], $separator);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le poids de mille graines, demandé au passage.
	 *
	 * Pas sur les espèces plantées à partir d'un bulbe ou d'un tubercule : le
	 * produit leur réserve déjà « Nombre de plants pour 1 kg ».
	 */
	private function getSeedWeight(\util\FormUi $form, Variety $eVariety): string {

		if(in_array($eVariety['plant']['fqn'], PlantSetting::RATING_NO_SEED_WEIGHT, TRUE)) {
			return '';
		}

		// La mention « Adapté pour les graines » du describer distingue le champ de
		// son voisin « Nombre de plants pour 1 kg », qui n'est pas sur ce formulaire.
		return $form->dynamicGroup($eVariety, 'weightSeed1000', function(\PropertyDescriber $d) {
			$d->after = NULL;
		});

	}

	/**
	 * Les tags qui décrivent la variété, à cocher parmi ceux que la plante accepte.
	 *
	 * Rien du tout quand la plante n'en accepte aucun : la moitié du référentiel est
	 * dans ce cas, ses variétés n'étant décrites que par leur nom de cultivar, et un
	 * bloc vide n'apprendrait rien.
	 *
	 * Le champ porte le nom de la propriété, crochets compris — `tags[]` — parce que
	 * plusieurs valeurs remontent ensemble : c'est ce que build() attend pour valider
	 * la colonne contre le schéma, via le callback `tags.check` de l'élément.
	 */
	private function getTags(\util\FormUi $form, \Collection $cTag, array $tags): string {

		if($cTag->empty()) {
			return '';
		}

		$values = [];

		foreach($cTag as $eTag) {
			$values[$eTag['id']] = encode($eTag['name']);
		}

		return $form->group(
			s("Quels sont le ou les mots qui décrivent le mieux cette variété ?"),
			$form->checkboxes('tags[]', $values, $tags, [
				// Trois colonnes plutôt que la colonne unique par défaut : une plante en
				// accepte jusqu'à quinze, et le composant repasse seul à deux sous 575 px.
				'columns' => 3,
				// Pas de « tout cocher » : décrire une variété, c'est choisir, et l'inverse
				// de choisir n'a pas de sens ici.
				'all' => FALSE,
			])
		);

	}

	/**
	 * Ce que les cultures passées disent de la variété, pour noter en connaissance
	 * de cause plutôt que de mémoire.
	 */
	private function getUsage(array $usage): string {

		if($usage['cultivations'] === 0) {
			return '';
		}

		$h = '<div class="variety-rating-usage">';

			$h .= '<div class="variety-rating-usage-headline">'.$this->getUsageHeadline($usage).'</div>';

			foreach($usage['steps'] as $step) {
				$h .= $this->getUsageStep($step);
			}

			$h .= $this->getUsageMonths();

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que la structure a cultivé de cette variété, en une phrase.
	 *
	 * Écrite une seule fois pour la ligne et pour le tiroir : deux formulations du
	 * même relevé finiraient par annoncer deux chiffres différents au même endroit.
	 */
	private function getUsageHeadline(array $usage): string {

		if($usage['cultivations'] === 0) {
			return '';
		}

		$seasons = $usage['seasons'];

		$h = p("Cultivée {value} fois", "Cultivée {value} fois", $usage['cultivations']);

		if($seasons !== []) {

			$h .= ' · ';

			if(count($seasons) === 1) {
				$h .= s("{value}", $seasons[0]);
			} else {
				$h .= s("{first} à {last}", [
					'first' => $seasons[0],
					'last' => $seasons[count($seasons) - 1],
				]);
			}

		}

		return $h;

	}

	/**
	 * Une action posée sur la frise de l'année : chaque semaine est teintée selon
	 * la fréquence à laquelle l'action y a réellement eu lieu.
	 */
	private function getUsageStep(array $step): string {

		$h = '<div class="variety-rating-timeline-row">';

			$h .= '<span class="variety-rating-timeline-label">'.encode($step['label']).'</span>';

			$h .= '<span class="variety-rating-timeline-track">';

				foreach($step['weeks'] as $intensity) {

					$style = ($intensity > 0) ? ' style="background-color: '.$step['color'].'; opacity: '.$intensity.';"' : '';

					$h .= '<span class="variety-rating-timeline-week"'.$style.'></span>';

				}

			$h .= '</span>';

		$h .= '</div>';

		return $h;

	}

	private function getUsageMonths(): string {

		$h = '<div class="variety-rating-timeline-row">';

			$h .= '<span class="variety-rating-timeline-label"></span>';

			$h .= '<span class="variety-rating-timeline-months">';
				for($month = 1; $month <= 12; $month++) {
					$h .= '<span>'.mb_strtoupper(mb_substr(\util\DateUi::getMonthName($month), 0, 1)).'</span>';
				}
			$h .= '</span>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La structure n'utilise aucune variété de légume : il n'y a rien à noter, et la
	 * féliciter d'avoir tout noté serait doublement faux.
	 */
	private function getEmpty(\structure\Structure $eStructure): string {

		$h = '<div class="util-block-info">';
			$h .= \Asset::icon('stars', ['class' => 'util-block-icon']);
			$h .= '<p>'.s("Les variétés se notent à partir de ce que vous cultivez : affectez des variétés à vos séries, et elles apparaîtront ici.").'</p>';
			$h .= '<a href="'.\structure\StructureUi::urlCultivationSeries($eStructure).'" class="btn btn-primary">'.s("Voir mes séries").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Un critère noté en étoiles.
	 *
	 * Les étoiles ne portent pas la valeur soumise : zéro étoile doit rester NULL
	 * (« sans avis »), ce qu'un jeu de radios ne sait pas exprimer une fois coché.
	 * La valeur réelle vit donc dans un champ caché, vide par défaut.
	 *
	 * Sans nom de champ, sur une ligne de liste, le contrôle n'a rien à soumettre :
	 * il écrit tout seul et se passe du champ caché.
	 */
	private function getCriterion(\util\FormUi $form, ?string $name, string $label, ?int $value, string $class = ''): string {

		// Les bornes viennent du schéma : toutes les notes, critère de plante ou note
		// globale, partagent la même échelle.
		[$min, $max] = VarietyUi::p('ratingOverall')->range;

		// data-value doit toujours être posé, même vide : le CSS s'appuie dessus pour
		// n'afficher le bouton d'effacement que sur un critère noté.
		$h = '<div class="variety-rating-criterion '.$class.'" data-value="'.$value.'">';

			$h .= '<span class="variety-rating-criterion-label">'.$label.'</span>';

			if($name !== NULL) {
				$h .= $form->hidden($name, $value);
			}

			$h .= '<span class="variety-rating-stars">';

				for($star = $min; $star <= $max; $star++) {

					$on = ($value !== NULL and $star <= $value) ? ' variety-rating-star-on' : '';
					$title = p("{value} étoile", "{value} étoiles", $star);

					$h .= '<a class="variety-rating-star'.$on.'" data-star="'.$star.'" title="'.$title.'">'.\Asset::icon('star-fill').'</a>';

				}

			$h .= '</span>';

			// Rien à effacer sur la ligne repliée : sans champ à soumettre, le contrôle
			// écrirait dans la foulée du clic, et un avis se retire depuis le tiroir,
			// où l'on voit ce qu'on retire.
			if($name !== NULL) {
				$h .= '<a class="variety-rating-criterion-clear" data-action="variety-rating-clear" title="'.s("Revenir à sans avis").'">'.\Asset::icon('x-lg').'</a>';
			}

		$h .= '</div>';

		return $h;

	}

}
?>
