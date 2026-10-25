<?php
namespace plant;

class CommunityAiUi {

	public function __construct() {

		\Asset::css('plant', 'communityAi.css');

	}

	/**
	 * Le bandeau d'ouverture des trois écrans de la campagne.
	 *
	 * Écrit une fois pour les trois : la racine, les critères et la notation portent
	 * la même en-tête, et trois copies d'un même texte finiraient par diverger.
	 */
	public function getIntro(\structure\Structure $eStructure, int $votes): string {

		$h = '<p>'.s("Chaque ferme utilise des centaines de variétés et faire le bon choix est crucial pour réussir une culture.").'</p>';
		$h .= '<p>'.s("Nous avons rassemblé au sein d'une seule page <mark>le choix variétal de centaines de fermes</mark> que nous avons combiné avec les rendements et l'avis des producteurs et productrices qui utilisent {siteName}. Cherchez, comparez et trouvez les variétés les plus adaptées à votre ferme !", ['mark' => '<b class="util-mark">']).'</p>';

		$h .= '<div class="util-block-info mt-2">';
			$h .= \Asset::icon('star', ['class' => 'util-block-icon']);
			$h .= s("Jusqu'au 4 novembre, contribuez au lancement de cette nouvelle fonctionnalité en donnant votre avis sur les variétés que vous utilisez !<br/>À partir du 5 novembre, vous pourrez consulter les résultats si vous avez participé à la première phase.");
		$h .= '</div>';

		$h .= $this->getGoal($votes);

		$h .= '<a href="'.\structure\StructureUi::urlIntelligenceCropPlan($eStructure).'">'.s("Découvrez aussi la bibliothèque de cultures").'</a>';

		return new \main\DesignUi()->getStart(
			\Asset::icon('stars'),
			s("Les variétés préférées de la communauté").' '.\Asset::image('main', 'favicon.svg'),
			$h,
			upcoming: '<b>'.s("Nouveau").'</b><small>'.s("Contribuez !").'</small>'
		);

	}

	/**
	 * Où en est la communauté de l'objectif de la campagne : le décompte des votes et sa barre.
	 *
	 * L'objectif atteint, la barre reste, pleine : le décompte continue de monter et
	 * dit que la communauté a répondu (planche du 02/10/2026).
	 */
	protected function getGoal(int $votes): string {

		$goal = PlantSetting::CAMPAIGN_VOTES_GOAL;

		$h = '<div class="plant-campaign-goal">';
			$h .= '<div class="plant-campaign-goal-count">';
				$h .= '<span>'.p("<b>{value}</b> vote", "<b>{value}</b> votes", $votes, ['value' => \util\TextUi::number($votes)]).'</span>';
				$h .= '<span class="color-muted">';
					$h .= ($votes >= $goal) ?
						s("Objectif de {value} atteint", \util\TextUi::number($goal)) :
						s("Objectif {value}", \util\TextUi::number($goal));
				$h .= '</span>';
			$h .= '</div>';
			$h .= '<div class="util-progress">';
				$h .= '<div class="util-progress-part bg-production" style="width: '.min(100, round($votes / $goal * 100, 1)).'%"></div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les trois temps de la campagne d'évaluation, en frise, et la navigation entre
	 * eux : chaque étape ouverte mène à son écran, et la frise reste en tête de ces
	 * écrans pour passer de l'une à l'autre.
	 *
	 * Les deux premières étapes sont ouvertes en permanence : indiquer ses critères et
	 * noter ses variétés se font dans l'ordre qu'on veut, et rien ne gagne à interdire
	 * la seconde tant qu'on n'a pas fini la première. Seuls les résultats attendent
	 * leur date, faute d'être calculés avant.
	 *
	 * $current dit sur quel écran on se trouve : c'est ce qui distingue « où j'en
	 * suis » de « où je peux aller », l'étape en cours gardant seule son cadre.
	 */
	public function getCalendar(\structure\Structure $eStructure, ?string $current = NULL): string {

		$results = (int)mb_substr(\Clock::date(), 0, 4).'-'.PlantSetting::CAMPAIGN_RESULTS_FROM;

		$locked = (\Clock::date() < $results);

		$steps = [
			[
				'date' => s("Dès maintenant"),
				'label' => s("Indiquez vos critères"),
				'description' => s("Choisissez ce qui compte pour vous sur chaque espèce : le rendement, la résistance au froid, la qualité gustative…"),
				'url' => \structure\StructureUi::urlIntelligenceVarietyCriteria($eStructure),
				'state' => ($current === 'criteria') ? 'active' : '',
			],
			[
				'date' => s("Dès maintenant"),
				'label' => s("Évaluez les variétés"),
				'description' => s("Notez les variétés que vous cultivez, une par une, sur les critères que vous avez retenus et les préférés des producteurs et productrices."),
				'url' => \structure\StructureUi::urlIntelligenceVarietyRating($eStructure),
				'state' => ($current === 'rating') ? 'active' : '',
			],
			[
				'date' => s("À partir du {value}", \util\DateUi::textual($results, \util\DateUi::DAY_MONTH)),
				'label' => s("Consultez les résultats"),
				'description' => s("Parcourez les variétés avec les rendements et l'avis des producteurs et productrices qui utilisent Ouvretaferme."),
				// Les résultats n'ont pas encore d'écran : l'étape informe, elle ne mène
				// nulle part, et le cadenas dit qu'elle attend sa date.
				'url' => NULL,
				'state' => $locked ? 'todo' : '',
			],
		];

		$h = '<div class="plant-campaign">';

			$h .= '<div class="util-campaign">';

				foreach($steps as $step) {

					// La carte entière est le lien : un bouton à l'intérieur donnerait deux
					// cibles pour un seul geste, et le composant sait déjà se soulever au
					// survol quand il est posé sur un <a>.
					$class = 'util-campaign-step'.($step['state'] === '' ? '' : ' is-'.$step['state']);

					$h .= ($step['url'] === NULL) ?
						'<div class="'.$class.'">' :
						'<a href="'.$step['url'].'" class="'.$class.'">';

						$h .= '<div class="util-campaign-marker">';
							$h .= match($step['state']) {
								'todo' => \Asset::icon('lock-fill'),
								default => \Asset::icon('star-fill'),
							};
						$h .= '</div>';

						$h .= '<div class="util-campaign-content">';

							$h .= '<div class="util-campaign-date">'.$step['date'].'</div>';
							$h .= '<div class="util-campaign-label">'.$step['label'].'</div>';
							$h .= '<div class="util-campaign-description">'.$step['description'].'</div>';

						$h .= '</div>';

					$h .= ($step['url'] === NULL) ? '</div>' : '</a>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le plan de culture agrégé d'une espèce : filtres, calendrier, créneaux.
	 *
	 * ⚠ Cet écran dit « ferme » là où le reste du produit dit « structure ». C'est une
	 * exception assumée, demandée pour cette page seule, et non un changement de la
	 * règle générale — qui reste celle de CLAUDE.md, « jamais ferme dans un texte
	 * rédigé ». Ne pas « corriger » ces libellés, et ne pas s'en servir de précédent
	 * ailleurs. Le reste de la classe (la frise de campagne, servie sur la page des
	 * variétés préférées) suit bien la règle générale.
	 */
	/**
	 * Le verrou d'adhésion, servi sous le bandeau d'introduction à la place du plan.
	 *
	 * Même gabarit que le formulaire de consentement (`StructureUi::getConsentDataForm()`)
	 * qui lui succède une fois adhérent : un titre, un encart, un appel à l'action.
	 * Le lien mène à la page d'adhésion de la structure, pas au site de l'association :
	 * c'est là que l'adhésion se règle, et qu'elle se constate ensuite.
	 */
	public function getMembershipRequired(\structure\Structure $eStructure): string {

		$h = '<h3>'.s("Pour commencer").'</h3>';

		$h .= '<div class="util-block-info">';
			$h .= \Asset::icon('star-fill', ['class' => 'util-block-icon']);
			$h .= s("La bibliothèque de cultures est réservée aux adhérents de l'association {siteName}.<br/>L'adhésion finance le développement du logiciel.");
		$h .= '</div>';

		$h .= '<p>';
			$h .= '<a href="/ferme/'.$eStructure['id'].'/adherer" class="btn btn-secondary">'.s("Adhérer à l'association").'</a>';
		$h .= '</p>';

		return $h;

	}

	public function getCropPlan(\structure\Structure $eStructure, \plant\Plant $ePlant, array $cropPlan, array $filters, \Collection $cPlant): string {

		\Asset::js('plant', 'communityAi.js');

		// La conduite retenue teinte toute la page : implantation, créneaux et frises
		// suivent la couleur que le produit donne déjà à cette intervention.
		$h = '<div class="community-crop is-'.$filters['action'].'">';

			$h .= $this->getFilters($eStructure, $cPlant, $ePlant, $filters);

			if($cropPlan['cultivations'] === 0) {
				$h .= $this->getEmpty($filters);
			} else {
				$h .= $this->getSummary($ePlant, $cropPlan, $filters);
				$h .= $this->getYearCalendar($cropPlan, $filters);
				$h .= $this->getTabs($eStructure, $ePlant, $cropPlan, $filters);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Espèce et variété dans un seul champ.
	 *
	 * Les deux se cherchent — cent cinquante-neuf espèces au référentiel, plusieurs
	 * centaines de noms de variétés par espèce — et aucune liste ne tiendrait à
	 * l'écran. C'est exactement ce que l'omnibox du design system sait faire, et le
	 * reste du bandeau garde ce qui se parcourt : altitude, mode, conduite.
	 *
	 * En mode navigation : chaque suggestion est une URL. L'état reste dans la requête,
	 * le lien partagé et le bouton retour restent justes, et rien ne dépend du JS.
	 *
	 * L'espèce ouverte y figure en jeton, y compris celle de l'avant-première : c'est
	 * le seul endroit qui dit sur quoi la page est posée en même temps qu'il permet
	 * d'en changer.
	 */
	protected function getOmnibox(\structure\Structure $eStructure, \Collection $cPlant, \plant\Plant $ePlant, array $filters): string {

		return \main\OmniboxUi::get(
			suggestionsUrl: '/plant/communityAi:query',
			// L'espèce ouverte voyage avec la requête : les suggestions d'espèce doivent
			// écarter celle qu'on regarde déjà, et celles de variété repartir des mêmes
			// filtres pour que le lien mène là où on croit.
			suggestionsBody: [
				'structure' => $eStructure['id'],
				'plant' => $ePlant['fqn'],
			] + array_filter($filters, fn($value) => is_scalar($value)),
			tokens: self::getTokens($eStructure, $cPlant, $ePlant, $filters),
			placeholder: ($cPlant->empty() ? s("Chercher une variété...") : s("Chercher une espèce, une variété...")),
			clear: self::urlCropPlan($eStructure, $filters, ['plant' => NULL, 'action' => NULL, 'seedling' => NULL, 'variety' => NULL]),
			class: 'util-omnibox-lg community-filters-search'
		);

	}

	/**
	 * Ce sur quoi la page est posée : l'espèce ouverte, puis la variété cherchée.
	 *
	 * L'espèce porte un jeton même quand c'est celle de l'avant-première. Le champ est
	 * le seul endroit d'où l'on change d'espèce : le laisser vide sur l'espèce par
	 * défaut donnait un sélecteur qui n'affiche jamais ce qu'il a sélectionné.
	 */
	protected static function getTokens(\structure\Structure $eStructure, \Collection $cPlant, \plant\Plant $ePlant, array $filters): array {

		$url = fn(array $changes) => self::urlCropPlan($eStructure, $filters, $changes);

		$tokens = [];

		if($cPlant->notEmpty()) {
			$tokens[] = [
				'group' => s("Espèces"),
				'label' => $ePlant['name'],
				'url' => $url(['plant' => NULL, 'action' => NULL, 'seedling' => NULL, 'variety' => NULL]),
			];
		}

		if($filters['variety'] !== NULL) {
			$tokens[] = [
				'group' => s("Variétés"),
				'label' => $filters['variety'],
				'url' => $url(['variety' => NULL]),
			];
		}

		return $tokens;

	}

	/**
	 * Ce que la recherche propose : les espèces du référentiel, puis les variétés.
	 *
	 * Les variétés ne se bornent pas à l'espèce ouverte, et c'est tout l'intérêt : il
	 * existe une « Black Beauty » d'aubergine et une de courgette, et personne ne sait
	 * d'avance sur quelle page il faut se placer pour trouver la sienne. Chacune porte
	 * donc la vignette et le nom de son espèce, et la choisir bascule d'espèce en même
	 * temps qu'elle pose le filtre.
	 *
	 * Une suggestion est une URL, jamais un filtrage local : l'état reste dans la
	 * requête, le lien partagé et le bouton retour restent justes.
	 */
	public static function getSuggestions(\structure\Structure $eStructure, \Collection $cPlant, \plant\Plant $ePlant, array $filters, \Collection $cCultivationVariety, string $query): array {

		$url = fn(array $changes) => self::urlCropPlan($eStructure, $filters, $changes);

		// Changer d'espèce n'emporte ni la conduite ni la recherche variétale : la
		// conduite ouvrirait la carotte sur la plantation parce qu'on venait de la
		// courgette, et « Black Beauty » ne trouverait rien sur la carotte.
		$urlPlant = fn(?string $fqn) => $url([
			'plant' => $fqn,
			'action' => NULL,
			'seedling' => NULL,
			'variety' => NULL,
		]);

		$icon = fn(?string $fqn) => ($fqn !== NULL and \plant\PlantUi::hasVignette($fqn)) ? \plant\PlantUi::getVignetteHref($fqn) : NULL;

		$needle = toFqn($query);

		$suggestions = [];

		foreach($cPlant as $ePlantOption) {

			if(
				$ePlantOption['fqn'] === $ePlant['fqn'] or
				($needle !== '' and str_contains(toFqn($ePlantOption['name']), $needle) === FALSE)
			) {
				continue;
			}

			$suggestion = [
				'group' => s("Espèces"),
				'label' => $ePlantOption['name'],
				'url' => $urlPlant($ePlantOption['fqn']),
			];

			if($icon($ePlantOption['fqn']) !== NULL) {
				$suggestion['icon'] = $icon($ePlantOption['fqn']);
			}

			$suggestions[] = $suggestion;

		}

		// La recherche est bornée à l'espèce ouverte : toutes ces variétés sont les
		// siennes. Le nom de l'espèce n'est donc pas répété en fin de ligne — il serait
		// le même partout — mais la vignette reste, qui rappelle d'un coup d'œil sur
		// quelle espèce on cherche sans rien coûter à la lecture.
		foreach($cCultivationVariety as $eCultivationVariety) {

			// Le lien ne remet pas la conduite à zéro : filtrer par variété n'est pas
			// changer de sujet.
			$suggestion = [
				'group' => s("Variétés"),
				'label' => $eCultivationVariety['name'],
				'url' => $url(['variety' => $eCultivationVariety['name']]),
			];

			if($icon($ePlant['fqn']) !== NULL) {
				$suggestion['icon'] = $icon($ePlant['fqn']);
			}

			$suggestions[] = $suggestion;

		}

		return $suggestions;

	}

	/**
	 * Le bandeau de filtres : mode de culture, conduite, puis recherche par variété.
	 *
	 * Chaque filtre est un jeu de liens et non un formulaire : l'état est dans
	 * l'URL, donc partageable et navigable en arrière, et rien n'a besoin de JS.
	 */
	protected function getFilters(\structure\Structure $eStructure, \Collection $cPlant, \plant\Plant $ePlant, array $filters): string {

		$url = fn(array $changes) => self::urlCropPlan($eStructure, $filters, $changes);

		$h = '<div class="util-filters">';

			// La barre de recherche ne s'affiche que là où elle a de quoi chercher, c'est-à-dire
			// là où le sélecteur d'espèce est servi : le développement et les comptes de
			// l'avant-première. Le test porte sur ce qu'elle contient et non sur l'environnement,
			// pour que la page ne décide pas deux fois, ici et dans la page, de la même chose.
			if($cPlant->notEmpty()) {
				$h .= $this->getOmnibox($eStructure, $cPlant, $ePlant, $filters);
			}

			$h .= $this->getAltitudeFilter($eStructure, $filters);

			$h .= '<div class="util-filter">';

				$h .= '<div class="util-filter-label">'.s("Mode de culture").'</div>';

				$h .= '<div class="util-filter-options">';

					$h .= $this->getFilterOption(s("Tous"), $url(['mode' => NULL]), $filters['mode'] === NULL);
					$h .= $this->getFilterOption(s("Sous abri"), $url(['mode' => \series\Series::GREENHOUSE]), $filters['mode'] === \series\Series::GREENHOUSE);
					$h .= $this->getFilterOption(s("Plein champ"), $url(['mode' => \series\Series::OPEN_FIELD]), $filters['mode'] === \series\Series::OPEN_FIELD);

				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="util-filter">';

				$h .= '<div class="util-filter-label">'.s("Implantation").'</div>';

				// Choix exclusif : les deux itinéraires n'ont ni les mêmes créneaux ni
				// les mêmes variétés, et les mêler dans une seule vue reviendrait à
				// moyenner deux conduites qui ne se ressemblent pas.
				$h .= '<div class="util-filter-options">';

					$h .= $this->getFilterOption(s("Plantation"), $url(['action' => \series\Cultivation::PLANTING]), $filters['action'] === \series\Cultivation::PLANTING);
					$h .= $this->getFilterOption(s("Semis direct"), $url(['action' => \series\Cultivation::SOWING]), $filters['action'] === \series\Cultivation::SOWING);

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le palier d'altitude.
	 *
	 * Premier filtre du bandeau parce qu'il ne restreint pas la culture regardée
	 * mais les structures dont on regarde les cultures : il change l'échantillon
	 * avant que le mode et la conduite ne le découpent.
	 *
	 * En menu déroulant et non en jeu de liens comme ses voisins : cinq valeurs
	 * dont aucune n'est un choix courant, là où le mode et la conduite en ont deux
	 * ou trois qu'on bascule d'un clic.
	 */
	protected function getAltitudeFilter(\structure\Structure $eStructure, array $filters): string {

		$form = new \util\FormUi();

		$values = ['' => s("Toutes")];

		foreach([
			'0-400' => s("0 - 400 m"),
			'400-700' => s("400 - 700 m"),
			'700-1000' => s("700 - 1000 m"),
			'1000-plus' => s("+ 1000 m"),
		] as $key => $label) {
			$values[$key] = $label;
		}

		$h = '<div class="util-filter">';

			$h .= '<div class="util-filter-label">'.s("Altitude").'</div>';

			$h .= $form->select('altitude', $values, $filters['altitude'] ?? '', [
				// Le palier vide vaut « toutes » : il est dans la liste, le champ n'a donc
				// pas à s'en voir ajouter un second par le framework.
				'required' => TRUE,
				'data-url' => self::urlCropPlan($eStructure, $filters, ['altitude' => NULL]),
				'onchange' => 'PlantCommunity.change(this)',
			]);

		$h .= '</div>';

		return $h;

	}

	protected function getFilterOption(string $label, string $url, bool $selected): string {

		return '<a href="'.$url.'" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.$label.'</a>';

	}

	/**
	 * L'URL de la page avec un jeu de filtres donné.
	 *
	 * Les valeurs nulles sont omises plutôt que sérialisées : l'URL sans paramètre
	 * est celle du cas général, et c'est elle qu'on veut voir quand rien n'est filtré.
	 */
	public static function urlCropPlan(\structure\Structure $eStructure, array $filters, array $changes): string {

		$query = [];

		foreach(['altitude', 'mode', 'action', 'seedling', 'variety', 'plant', 'view', 'bedWidth'] as $name) {

			$value = array_key_exists($name, $changes) ? $changes[$name] : ($filters[$name] ?? NULL);

			// La vue est toujours résolue, jamais nulle : c'est sa valeur par défaut qui
			// tient lieu d'absence, sans quoi elle s'écrirait dans toutes les URL.
			if($name === 'view' and $value === PlantSetting::COMMUNITY_VIEW_DEFAULT) {
				continue;
			}

			if($value !== NULL) {
				$query[$name] = $value;
			}

		}

		return '/ferme/'.$eStructure['id'].'/bibliotheque'.($query ? '?'.http_build_query($query) : '');

	}

	/**
	 * Ce que pèse l'échantillon et ce qu'il dit en une ligne.
	 *
	 * La taille de l'échantillon est mise au même rang que les résultats : une
	 * médiane calculée sur douze fermes et une calculée sur neuf cents ne s'utilisent
	 * pas de la même façon, et le lecteur doit pouvoir en juger.
	 */
	protected function getSummary(\plant\Plant $ePlant, array $cropPlan, array $filters): string {

		// Trois nombres, et trois seulement : ce que pèse l'échantillon, et l'ampleur
		// du choix variétal qu'il recouvre. Les durées médianes ont leur place dans
		// chaque créneau, où elles se rapportent à une période précise — en tête de
		// page, moyennées sur toute la saison, elles ne décrivaient personne.
		$figures = [
			[
				'value' => \util\TextUi::number($cropPlan['structures']),
				'label' => p("ferme", "fermes", $cropPlan['structures']),
			],
			[
				'value' => \util\TextUi::number($cropPlan['cultivations']),
				'label' => p("culture suivie", "cultures suivies", $cropPlan['cultivations']),
			],
			[
				'value' => \util\TextUi::number($cropPlan['varieties']),
				'label' => ($filters['variety'] === NULL) ?
					p("variété différente", "variétés différentes", $cropPlan['varieties']) :
					p("variété employée dans ces cultures", "variétés employées dans ces cultures", $cropPlan['varieties']),
			],
		];

		return $this->getFigures($figures);

	}

	/**
	 * Une rangée de chiffres clés.
	 *
	 * `util-figures` est le composant du socle pour cela, et `component.css:3905` dit
	 * qu'il a remplacé le `community-summary` que cet écran portait en propre.
	 */
	protected function getFigures(array $figures): string {

		$h = '<div class="util-figures">';

			foreach($figures as $figure) {

				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-value">'.$figure['value'].'</div>';
					$h .= '<div class="util-figure-label">'.$figure['label'].'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les deux lectures de l'espèce sous le calendrier, en onglets (le
	 * 2026-09-21) : comment on la conduit, puis ses créneaux.
	 *
	 * Le choix de vue est posé dans la tête des onglets et non dans un panneau : il vaut
	 * pour les deux, la simplifiée résumant chacun, l'experte en montrant le détail.
	 * L'onglet des créneaux n'existe que s'il en a à montrer.
	 */
	protected function getTabs(\structure\Structure $eStructure, \plant\Plant $ePlant, array $cropPlan, array $filters): string {

		$h = '<div class="community-block">';

			// Les créneaux ouvrent l'écran quand l'espèce en a (2026-09-21) ; sans
			// eux, les conduites restent seules.
			$hasSlots = ($cropPlan['slots'] !== []);
			$selected = $hasSlots ? 'slots' : 'conducts';

			$h .= '<div class="tabs tabs-h" id="community-tabs" onrender="'.encode('Lime.Tab.restore(this, "'.$selected.'")').'">';

				$h .= '<div class="tabs-item-wrapper">';

					$h .= '<div class="tabs-item">';
						if($hasSlots) {
							$h .= '<a class="tab-item selected" data-tab="slots" onclick="PlantCommunity.selectTab(this)">'.s("Créneaux de culture").'</a>';
						}
						$h .= '<a class="tab-item'.($hasSlots ? '' : ' selected').'" data-tab="conducts" onclick="PlantCommunity.selectTab(this)">'.s("Conduite technique").'</a>';
					$h .= '</div>';

					$h .= $this->getViewFilter($eStructure, $filters);

				$h .= '</div>';

				$h .= '<div>';

					if($hasSlots) {
						$h .= '<div data-tab="slots" class="tab-panel selected">';
							$h .= $this->getSlots($eStructure, $cropPlan, $filters);
						$h .= '</div>';
					}

					$h .= '<div data-tab="conducts" class="tab-panel'.($hasSlots ? '' : ' selected').'">';
						$h .= $this->getConducts($eStructure, $cropPlan['conducts'], $filters);
					$h .= '</div>';

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Comment les autres montent cette espèce, sur une largeur de planche.
	 *
	 * Une conduite n'est pas une moyenne : c'est un cas réel — une largeur, un nombre
	 * de rangs, un écartement, un mode — que plusieurs structures pratiquent à
	 * l'identique. La largeur ouverte est celle qu'a choisie le Lib : la plus proche de
	 * celle demandée ou réglée, et une largeur gérée qui ne porte rien reste ouverte,
	 * l'écran le constate au lieu d'en montrer une autre sous son titre.
	 *
	 * D'abord les trois conduites les plus pratiquées de chaque mode, planche
	 * dessinée, puis le diagramme des densités, qui les situe parmi toutes les autres
	 * (2026-09-21).
	 */
	protected function getConducts(\structure\Structure $eStructure, array $conducts, array $filters): string {

		$h = '<div class="util-filters mb-1">';
			$h .= $this->getBedWidthFilter($eStructure, $filters, $conducts['widths']);
		$h .= '</div>';

		if($conducts['bedWidth'] === NULL) {

			$h .= '<div class="util-block-empty">';
				$h .= s("Aucune conduite n'atteint {floor} fermes, quelle que soit la largeur de planche.", [
					'floor' => \aggregate\CultivationAiSetting::CONDUCT_STRUCTURES_MIN,
				]);
			$h .= '</div>';

		} else if($conducts['bedWidth'] === \aggregate\CultivationAiSetting::BED_WIDTH_ALL) {

			$h .= '<h3 class="mt-2">'.s("Diagramme des densités").'</h3>';
			$h .= $this->getConductBands($conducts['bands']);

		} else if($conducts['modes'] === []) {

			$h .= '<div class="util-block-empty">';
				$h .= s("Aucune conduite sur une planche de {width} n'atteint {floor} fermes.", [
					'width' => self::getBedWidthLabel($conducts['widths'][$conducts['bedWidth']]['bedWidths']),
					'floor' => \aggregate\CultivationAiSetting::CONDUCT_STRUCTURES_MIN,
				]);
			$h .= '</div>';

		} else {

			$h .= '<h3 class="mt-2">'.s("Espacements les plus populaires").'</h3>';

			if($filters['view'] === PlantSetting::COMMUNITY_VIEW_EXPERT) {
				$h .= $this->getConductMap($conducts['map'], $conducts['peak']);
			}

			foreach($conducts['modes'] as $mode => $conductsMode) {
				$h .= $this->getConductMode($mode, $conductsMode, $conducts['peak'], $conducts['yieldUnit']);
			}

			$h .= '<h3 class="mt-2">'.s("Diagramme des densités").'</h3>';
			$h .= $this->getConductBands($conducts['bands']);

		}

		$h .= '<div class="util-annotation mt-1">';
			$h .= s("Une conduite s'affiche à partir de {floor} fermes qui la pratiquent, et son objectif de rendement à partir de {yieldFloor} cultures qui l'annoncent.", [
				'floor' => \aggregate\CultivationAiSetting::CONDUCT_STRUCTURES_MIN,
				'yieldFloor' => \aggregate\CultivationAiSetting::CONDUCT_YIELD_CULTIVATIONS_MIN,
			]);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le choix de la largeur de planche, en tête du panneau.
	 *
	 * Les largeurs de la liste fermée sont toujours proposées, et elles seules, rangées
	 * par valeur croissante et non par fréquence : une échelle de mesure lue dans le
	 * désordre se parcourt deux fois, et la largeur ouverte se repère à sa pastille,
	 * pas à sa place. Une largeur que l'espèce ne porte pas reste cliquable et mène à
	 * un écran qui le dit.
	 */
	protected function getBedWidthFilter(\structure\Structure $eStructure, array $filters, array $widths): string {

		$url = fn(array $changes) => self::urlCropPlan($eStructure, $filters, $changes);

		$h = '<div class="util-filter">';
			$h .= '<div class="util-filter-label">'.s("Largeur de planche").'</div>';
			$h .= '<div class="util-filter-options">';

				$h .= $this->getFilterOption(
					s("Toutes"),
					$url(['bedWidth' => \aggregate\CultivationAiSetting::BED_WIDTH_ALL]),
					$filters['bedWidth'] === \aggregate\CultivationAiSetting::BED_WIDTH_ALL
				);

				foreach($widths as $bedWidth => $width) {
					$h .= $this->getFilterOption(
						self::getBedWidthLabel($width['bedWidths']),
						$url(['bedWidth' => $bedWidth]),
						$filters['bedWidth'] === $bedWidth
					);
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Un groupe de largeurs se lit par ses bornes.
	 */
	protected static function getBedWidthLabel(array $bedWidths): string {

		if(count($bedWidths) === 1) {
			return s("{value} cm", $bedWidths[0]);
		}

		return s("{from} - {to} cm", ['from' => min($bedWidths), 'to' => max($bedWidths)]);

	}

	/**
	 * Toutes les conduites de la largeur d'un coup d'œil : une bande par mode, à la
	 * manière du calendrier, qui dit où se tiennent les cultures selon la densité.
	 * L'axe est commun aux deux bandes, c'est ce qui fait de leur décalage un constat.
	 *
	 * Chaque barre de l'histogramme couvre un intervalle de la grille, qui découpe
	 * l'axe comme les semaines découpent le calendrier, plus marquée sur les
	 * graduations. L'échelle à gauche compte les cultures, et ses graduations
	 * tirent le quadrillage horizontal (2026-09-21).
	 */
	protected function getConductBands(array $bands): string {

		$axis = $bands['axis'];
		$span = $axis['max'] - $axis['min'];

		$h = '<div class="community-chart conduct-bands" onrender="'.encode('PlantCommunity.fitAxis(this)').'">';

			foreach($bands['modes'] as $mode => $band) {

				$scale = $band['scale'];

				$h .= '<div class="community-chart-row conduct-mode-'.$mode.'">';

					$h .= '<div class="community-chart-label">';
						$h .= '<div class="community-chart-title">'.self::getModeName($mode).'</div>';
						$h .= '<div class="community-chart-scale">'.p("{value} conduite", "{value} conduites", $band['conducts']).'</div>';
						$h .= '<div class="community-chart-scale">'.p("{value} culture", "{value} cultures", $band['cultivations']).'</div>';
					$h .= '</div>';

					$h .= '<div class="conduct-scale">';
						foreach($scale['ticks'] as $tick) {
							$h .= '<span style="bottom: '.self::plotNumber($tick / $scale['max'] * 100).'%">'.\util\TextUi::number($tick).'</span>';
						}
					$h .= '</div>';

					$h .= '<div class="community-chart-body">';

						$h .= '<div class="community-chart-track">';

							$h .= '<div class="conduct-grid">';
								foreach($axis['grid'] as $line) {
									$h .= '<div class="conduct-grid-line'.($line['tick'] ? ' is-tick' : '').'" style="left: '.self::plotNumber(($line['value'] - $axis['min']) / $span * 100).'%"></div>';
								}
								foreach($scale['ticks'] as $tick) {
									$h .= '<div class="conduct-grid-level" style="bottom: '.self::plotNumber($tick / $scale['max'] * 100).'%"></div>';
								}
							$h .= '</div>';

							$h .= '<div class="conduct-histogram">';
								foreach($band['bars'] as $bar) {
									$title = p("{value} culture entre {from} et {to} plants/m²", "{value} cultures entre {from} et {to} plants/m²", $bar['cultivations'], [
										'value' => \util\TextUi::number($bar['cultivations']),
										'from' => self::getTick($bar['from']),
										'to' => self::getTick($bar['to']),
									]);
									$h .= '<div class="conduct-histogram-bar" '.attr('title', $title).' style="left: '.self::plotNumber(($bar['from'] - $axis['min']) / $span * 100).'%; width: '.self::plotNumber(($bar['to'] - $bar['from']) / $span * 100).'%; height: '.self::plotNumber($bar['cultivations'] / $scale['max'] * 100).'%"></div>';
								}
							$h .= '</div>';

							$median = ($band['density'] - $axis['min']) / $span * 100;

							// Au-delà du milieu de la bande, l'étiquette passe à gauche du trait : à
							// droite, elle sortirait du bloc sur un écran étroit.
							$h .= '<div class="conduct-curve-median'.(($median > 50) ? ' conduct-curve-median-end' : '').'" style="left: '.self::plotNumber($median).'%">';
								$h .= '<span>'.s("médiane {value} plants/m²", self::getDensity($band['density'])).'</span>';
							$h .= '</div>';

						$h .= '</div>';

						$h .= '<div class="conduct-axis">';
							foreach($axis['ticks'] as $tick) {
								$h .= '<span data-rank="'.(int)round($tick / $axis['step']).'" style="left: '.self::plotNumber(($tick - $axis['min']) / $span * 100).'%">'.s("{value} / m²", self::getTick($tick)).'</span>';
							}
						$h .= '</div>';

					$h .= '</div>';

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La vue experte : la grille nombre de rangs × écartement, un rond par conduite dont
	 * la surface suit le nombre de structures, plein champ et sous abri dans la même
	 * case. Elle montre toutes les conduites de la largeur, là où les cartes n'en
	 * gardent que trois par mode.
	 */
	protected function getConductMap(array $map, int $peak): string {

		$conducts = [];

		foreach($map['conducts'] as $conduct) {
			$conducts[$conduct['rows']][(string)$conduct['plantSpacing']][$conduct['mode']] = $conduct['structures'];
		}

		$h = '<div class="conduct-overview mb-1">';

			$h .= '<div class="conduct-map" style="--conduct-map-columns: '.count($map['spacings']).'">';

				$h .= '<div class="conduct-map-head"></div>';

				foreach($map['spacings'] as $spacing) {
					$h .= '<div class="conduct-map-head">'.s("{value} cm", self::getSpacing($spacing)).'</div>';
				}

				foreach($map['rows'] as $rows) {

					$h .= '<div class="conduct-map-rows">'.p("{value} rang", "{value} rangs", $rows).'</div>';

					foreach($map['spacings'] as $spacing) {

						$h .= '<div>';

							foreach(\aggregate\CultivationConductAiLib::MODES as $mode) {

								$structures = $conducts[$rows][(string)$spacing][$mode] ?? NULL;

								if($structures === NULL) {
									continue;
								}

								$size = self::plotNumber(3 * sqrt($structures / $peak));

								$h .= '<span class="conduct-map-dot conduct-mode-'.$mode.'" '.attr('title', p("{value} ferme", "{value} fermes", $structures)).' style="width: '.$size.'rem; height: '.$size.'rem">'.$structures.'</span>';

							}

						$h .= '</div>';

					}

				}

			$h .= '</div>';

			$h .= '<div class="conduct-overview-legend">';
				foreach($map['modes'] as $mode) {
					$h .= '<span class="conduct-mode-title conduct-mode-'.$mode.'">'.self::getModeName($mode).'</span>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une pièce se compte, un kilo et une botte se fractionnent.
	 */
	protected static function getYieldExpected(float $yield, string $unit): string {

		return match($unit) {
			\aggregate\Cultivation::KG => s("objectif {value} kg/m²", \util\TextUi::number(round($yield, 1), NULL)),
			\aggregate\Cultivation::UNIT => s("objectif {value} pièces/m²", \util\TextUi::number(round($yield), 0)),
			\aggregate\Cultivation::BUNCH => s("objectif {value} bottes/m²", \util\TextUi::number(round($yield, 1), NULL)),
		};

	}

	/**
	 * Les conduites les plus pratiquées d'un mode, une carte chacune, sous un titre
	 * dans la couleur du mode.
	 */
	protected function getConductMode(string $mode, array $conductsMode, int $peak, string $yieldUnit): string {

		$h = '<div class="conduct-mode-'.$mode.'">';

			$h .= '<div class="conduct-mode-title">';
				$h .= self::getModeName($mode);
				if($conductsMode['total'] > count($conductsMode['top'])) {
					$h .= ' <small>'.s("les {value} plus pratiquées", count($conductsMode['top'])).'</small>';
				}
			$h .= '</div>';

			$h .= '<div class="util-cards util-cards-3 conduct-cards">';

				foreach($conductsMode['top'] as $conduct) {

					$h .= '<div class="util-card">';

						$h .= $this->getConductPlot($conduct);
						$h .= '<div class="util-card-title">'.p("{value} rang", "{value} rangs", $conduct['rows']).' '.s("à {value} cm", self::getSpacing($conduct['plantSpacing'])).'</div>';

						// La popularité est la raison pour laquelle la conduite est là : rapportée
						// à la plus pratiquée des deux modes, elle se compare d'une rangée à l'autre.
						$h .= '<div class="conduct-share">';
							$h .= '<span class="util-progress">';
								$h .= '<span class="util-progress-part" style="width: '.round($conduct['structures'] / $peak * 100).'%"></span>';
							$h .= '</span>';
							$h .= '<span class="conduct-share-value">'.p("{value} ferme", "{value} fermes", $conduct['structures'], ['value' => \util\TextUi::number($conduct['structures'])]).'</span>';
						$h .= '</div>';

						$h .= '<div class="util-metric-value"><span class="util-unit">'.self::getDensity($conduct['density']).' '.s("plants/m²").'</span><small class="conduct-density-note color-muted"> '.s("passe-pieds inclus").'</small></div>';

						$h .= '<div class="util-metric-variation">';
							$h .= p("{value} culture", "{value} cultures", $conduct['cultivations'], ['value' => \util\TextUi::number($conduct['cultivations'])]);
							if($conduct['yieldExpected'] !== NULL) {
								$h .= ' · '.self::getYieldExpected($conduct['yieldExpected'], $yieldUnit);
							}
						$h .= '</div>';

					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une graduation écrite à la précision de son pas : un pas de 0,25 porte deux
	 * décimales, et 2,25 arrondi à une seule se lirait 2,3.
	 */
	protected static function getTick(float $tick): string {

		if(floor($tick) === $tick) {
			$decimals = 0;
		} else if(round($tick, 1) === $tick) {
			$decimals = 1;
		} else {
			$decimals = 2;
		}

		return \util\TextUi::number($tick, $decimals);

	}

	protected static function getModeName(string $mode): string {

		return match($mode) {
			\aggregate\Cultivation::OPEN_FIELD => s("Plein champ"),
			\aggregate\Cultivation::GREENHOUSE => s("Sous abri"),
		};

	}

	/**
	 * Un écartement au centimètre, et au demi quand la saisie en porte un : arrondi à
	 * l'entier, un écartement de 2,5 cm se lirait comme celui d'une autre conduite.
	 */
	protected static function getSpacing(float $spacing): string {

		return \util\TextUi::number($spacing, (floor($spacing) === $spacing) ? 0 : 1);

	}

	/**
	 * Le plan d'une planche, en perspective, sur deux mètres de long. Les passe-pieds ne se
	 * dessinent pas ici (2026-09-21) : la densité d'une conduite du parc les inclut,
	 * mais leur largeur n'est pas agrégée.
	 */
	protected function getConductPlot(array $conduct): string {

		return new \sequence\DensityPlanAiUi()->get([
			'use' => \sequence\Sequence::BED,
			'bedWidth' => $conduct['bedWidth'],
			'alleyWidth' => 0,
			'rows' => $conduct['rows'],
			'plantSpacing' => $conduct['plantSpacing'],
			'rowSpacing' => NULL,
			'stagger' => FALSE
		], 200);

	}

	/**
	 * Une densité écrite à la précision que son ordre de grandeur porte.
	 *
	 * Deux décimales valent quelque chose sur une tomate à 2,08 plants/m², où elles
	 * séparent deux conduites réelles ; sur une carotte à 148, la seconde décimale est du
	 * bruit, et « 125,00 » annonce une exactitude que la médiane de densités saisies à la
	 * main n'a pas. Le nombre de décimales suit donc la grandeur, et non le champ, et
	 * au-delà de 20 plants/m² on arrondit à l'entier (2026-09-21).
	 */
	protected static function getDensity(float $density): string {

		if($density < 10) {
			$decimals = 2;
		} else if($density <= 20) {
			$decimals = 1;
		} else {
			$decimals = 0;
		}

		return \util\TextUi::number($density, $decimals);

	}

	/**
	 * Un nombre destiné à une propriété CSS : le séparateur décimal y est le point, et
	 * jamais la virgule que la locale pose pour l'œil.
	 */
	protected static function plotNumber(float $value): string {

		return number_format($value, 2, '.', '');

	}

	/**
	 * Le calendrier de l'espèce : implantations et récoltes, semaine par semaine.
	 *
	 * Les courbes sont l'une au-dessus de l'autre sur le même axe, parce que c'est
	 * leur décalage qui porte l'information — superposées elles se masqueraient,
	 * côte à côte il faudrait les comparer de mémoire.
	 *
	 * Deux courbes seulement : ce que la ferme met en terre et ce qu'elle en tire.
	 * La pépinière est une étape de conduite, pas une occupation de planche ; elle se
	 * lit au créneau, là où l'avance à prendre se décide.
	 */
	protected function getYearCalendar(array $cropPlan, array $filters): string {

		$isPlanting = ($cropPlan['action'] === \series\Cultivation::PLANTING);

		$rows = [
			['type' => 'implantation', 'label' => $isPlanting ? s("Plantations") : s("Semis directs")],
			['type' => 'harvest', 'label' => s("Récoltes")],
		];

		$h = '<div class="community-block">';

			// Filtré sur une variété, ce n'est plus le calendrier de l'espèce : le dire
			// évite de lire une courbe de variété comme une courbe d'espèce.
			$h .= '<h3>'.($filters['variety'] === NULL ? s("Le calendrier de l'espèce") : s("Le calendrier de la variété")).'</h3>';

			// Le survol d'une semaine d'implantation montre dans la bande des récoltes où
			// récoltent les cultures implantées cette semaine-là : toutes les cohortes sont
			// dans la page, le script ne fait que les montrer.
			$h .= '<div class="community-chart" onrender="'.encode('PlantCommunity.listenCohorts(this)').'">';

				foreach($rows as $row) {

					// Une bande vide n'apprend rien que le filtre ne dise déjà.
					if($cropPlan['series'][$row['type']]['total'] === 0) {
						continue;
					}

					$h .= $this->getChartRow($row['label'], $cropPlan, $row['type']);

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'axe des mois, éventuellement gradué en semaines.
	 *
	 * Les deux graduations sont deux rangées superposées sur la même grille de 52
	 * colonnes : le mois s'étale sur ses semaines, chaque semaine tient sa colonne.
	 * Numéroter les cinquante-deux évite d'avoir à compter les traits depuis le
	 * dernier repère — sur une barre qui court de la S19 à la S33, ce comptage était
	 * tout ce qui séparait le lecteur de sa date.
	 */
	protected function getMonthAxis(array $months, bool $withWeeks = FALSE): string {

		$names = \util\DateUi::months('short');

		$h = '<div class="community-axis">';

			$h .= '<div class="community-axis-months">';

				foreach($months as $month => $position) {
					$h .= '<div class="community-axis-month" '.attr('style', 'grid-column: '.$position['from'].' / span '.$position['span']).'>'.$names[$month].'</div>';
				}

			$h .= '</div>';

			if($withWeeks) {

				$h .= '<div class="community-axis-weeks">';

					for($week = 1; $week <= 52; $week++) {
						$h .= '<div class="community-axis-week">'.$week.'</div>';
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une bande : la courbe, puis son axe des mois juste dessous.
	 *
	 * L'axe est répété sous chaque bande plutôt que posé une fois en tête du
	 * graphique : sur trois bandes empilées, lire une courbe du bas obligeait sinon à
	 * remonter chercher les mois tout en haut.
	 *
	 * L'intitulé porte l'effectif, faute de quoi trois bandes d'échelles différentes
	 * se liraient comme trois grandeurs comparables.
	 */
	protected function getChartRow(string $label, array $cropPlan, string $type): string {

		$serie = $cropPlan['series'][$type];

		$h = '<div class="community-chart-row is-'.$type.'">';

			$h .= '<div class="community-chart-label">';
				$h .= '<div class="community-chart-title">'.$label.'</div>';
				$h .= '<div class="community-chart-scale">'.p("{value} culture", "{value} cultures", $serie['total']).'</div>';
				if($type === 'harvest') {
					foreach($cropPlan['cohorts'] as $week => $cohort) {
						$h .= '<div class="community-chart-scale community-cohort-scale" data-cohort="'.$week.'" hidden>'.$this->getCohortScale($cropPlan['action'], $week, $cohort).'</div>';
					}
				}
			$h .= '</div>';

			$h .= '<div class="community-chart-body">';

				$h .= '<div class="community-chart-track">';

					$h .= $this->getMonthGrid($cropPlan['months'], TRUE);
					$h .= $this->getCurve($serie, $type);

					if($type === 'harvest') {

						foreach($cropPlan['cohorts'] as $week => $cohort) {

							if($cohort['status'] !== \aggregate\CultivationCropPlanAiLib::COHORT_SHOWN) {
								continue;
							}

							// Chaque cohorte à sa propre échelle : c'est sa forme qu'on lit, et
							// une cohorte d'une trentaine de cultures ne serait qu'un trait au
							// pied d'une courbe d'espèce qui en compte deux mille.
							$h .= '<div class="community-cohort" data-cohort="'.$week.'" hidden>'.$this->getCurveSvg(self::getCurvePoints($cohort['curve'], $cohort['max']), 52).'</div>';

							if($cohort['delay'] > 0) {
								$h .= $this->getCohortDelay($cropPlan['action'], $week, $cohort['delay']);
							}

						}

					}

				$h .= '</div>';

				$h .= $this->getMonthAxis($cropPlan['months']);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La courbe d'une bande, en SVG : une aire remplie surmontée de son trait.
	 *
	 * Le tracé est en coordonnées 0-52 sur 0-100 et le SVG est étiré à la taille de
	 * la bande (`preserveAspectRatio="none"`) : la courbe suit ainsi la largeur
	 * disponible sans qu'il faille connaître les pixels au moment de la construire,
	 * et reste calée sur la même grille de 52 semaines que le reste de la page.
	 *
	 * Les semaines gardent une zone de survol invisible chacune, sinon le détail
	 * hebdomadaire — le seul endroit où l'effectif brut est lisible — disparaîtrait
	 * avec les barres.
	 */
	protected function getCurve(array $serie, string $type): string {

		$h = $this->getCurveSvg(self::getCurvePoints($serie['curve'], $serie['max']), 52);

		$h .= '<div class="community-chart-weeks">';

			foreach($serie['counts'] as $week => $count) {

				// La redistribution des récoltes déclarées au mois donne des comptes
				// fractionnaires : on rend un effectif, pas une décimale.
				$count = (int)round($count);

				$title = match($type) {
					'seedling' => s("Semaine {week} : {value} semis en pépinière", ['week' => $week, 'value' => $count]),
					'implantation' => s("Semaine {week} : {value} cultures implantées", ['week' => $week, 'value' => $count]),
					'harvest' => s("Semaine {week} : {value} cultures en récolte", ['week' => $week, 'value' => $count]),
				};

				$h .= '<div class="community-chart-week" '.attr('title', $title).(($type === 'implantation') ? ' data-week="'.$week.'"' : '').'></div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les points d'une courbe de 52 semaines, chaque semaine à son milieu et la hauteur
	 * rapportée au maximum de la courbe.
	 */
	protected static function getCurvePoints(array $curve, float $max): array {

		$max = ($max > 0) ? $max : 1;

		$points = [];

		foreach($curve as $week => $value) {
			$points[] = [$week - 0.5, $value / $max];
		}

		return $points;

	}

	/**
	 * Ce que dit la bande des récoltes pendant le survol d'une semaine d'implantation, à
	 * la place de son effectif.
	 */
	protected function getCohortScale(string $action, int $week, array $cohort): string {

		return match($cohort['status']) {
			\aggregate\CultivationCropPlanAiLib::COHORT_SHOWN => match($action) {
				\series\Cultivation::SOWING => p("{value} culture semée en S{week}", "{value} cultures semées en S{week}", $cohort['cultivations'], ['week' => $week]),
				\series\Cultivation::PLANTING => p("{value} culture implantée en S{week}", "{value} cultures implantées en S{week}", $cohort['cultivations'], ['week' => $week]),
			},
			\aggregate\CultivationCropPlanAiLib::COHORT_FEW => p("Moins de {value} ferme en S{week}", "Moins de {value} fermes en S{week}", \aggregate\CultivationAiSetting::COHORT_STRUCTURES_MIN, ['week' => $week]),
			\aggregate\CultivationCropPlanAiLib::COHORT_EMPTY => match($action) {
				\series\Cultivation::SOWING => s("Aucune culture semée en S{week}", ['week' => $week]),
				\series\Cultivation::PLANTING => s("Aucune culture implantée en S{week}", ['week' => $week]),
			},
		};

	}

	/**
	 * Le lien de la semaine survolée au début de récolte de sa cohorte, dessiné comme les
	 * délais des créneaux : un point à l'implantation, le pointillé et son nombre de
	 * semaines, un point à la récolte (2026-09-21). Il se pose en haut de la piste des récoltes,
	 * par-dessus la cohorte (2026-09-21).
	 */
	protected function getCohortDelay(string $action, int $week, int $delay): string {

		$h = '<div class="community-steps community-cohort-delay" data-cohort="'.$week.'" hidden>';
			$h .= '<div class="community-steps-delays">';

				$h .= '<div class="community-range-median is-implantation" '.attr('style', 'grid-row: 1; grid-column: '.(2 * $week - 1).' / span 2').'></div>';
				$h .= '<div class="community-range-median is-harvest" '.attr('style', 'grid-row: 1; grid-column: '.(2 * $this->getCycleWeek($week + $delay) - 1).' / span 2').'></div>';

				$h .= $this->getDelay([
					'from' => $week,
					'to' => $week + $delay,
					'weeks' => $delay,
					'title' => $this->getHarvestDelayTitle($action === \series\Cultivation::PLANTING, $delay),
				]);

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le tracé d'une courbe, commun au calendrier et aux bandes de densité : une aire
	 * remplie surmontée de son trait, `$points` étant des couples (abscisse, hauteur de
	 * 0 à 1) sur un axe de `$width` unités.
	 */
	protected function getCurveSvg(array $points, int $width): string {

		$coordinates = array_map(fn(array $point) => round($point[0], 2).','.round(100 - $point[1] * 100, 2), $points);

		$line = 'M'.implode(' L', $coordinates);
		$area = $line.' L'.$width.',100 L0,100 Z';

		$h = '<svg class="community-curve" viewBox="0 0 '.$width.' 100" preserveAspectRatio="none">';
			$h .= '<path class="community-curve-area" '.attr('d', $area).'/>';
			$h .= '<path class="community-curve-line" '.attr('d', $line).'/>';
		$h .= '</svg>';

		return $h;

	}

	/**
	 * Les séparations de mois, posées derrière les barres.
	 *
	 * Un mois ne fait pas un douzième de l'année en semaines ISO : les traits sont
	 * calés sur le même découpage que l'axe, faute de quoi ils tomberaient à côté des
	 * intitulés qu'ils sont censés délimiter.
	 */
	protected function getMonthGrid(array $months, bool $withWeeks = FALSE): string {

		// Un seul jeu de cellules pour les deux graduations : chaque semaine porte son
		// trait, et celles qui ouvrent un mois le portent plus marqué. Deux couches
		// superposées auraient fait deux traits au même endroit sur douze colonnes.
		if($withWeeks) {

			$starts = array_flip(array_column($months, 'from'));

			$h = '<div class="community-grid">';

				for($week = 1; $week <= 52; $week++) {
					$h .= '<div class="community-grid-week'.(isset($starts[$week]) ? ' is-month' : '').'"></div>';
				}

			$h .= '</div>';

			return $h;

		}

		$h = '<div class="community-grid">';

			foreach($months as $position) {
				$h .= '<div class="community-grid-month" '.attr('style', 'grid-column: '.$position['from'].' / span '.$position['span']).'></div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les créneaux, un par mois d'implantation, sur l'axe du calendrier.
	 *
	 * Chaque créneau affiche son cœur de pratique — la moitié centrale des cultures
	 * — et non ses extrêmes : ce qui intéresse celui qui planifie, c'est où se situe
	 * le gros de la troupe, pas jusqu'où sont allés les deux plus téméraires.
	 *
	 * Aucun créneau ne tient le seuil du nombre de fermes : l'onglet entier disparaît
	 * (`getTabs()`). L'espèce est bien cultivée, mais trop peu de fermes s'accordent
	 * sur un même mois pour qu'un créneau veuille dire quelque chose. N'en montrer que
	 * l'axe des mois et la note de bas de bloc laisserait croire à un défaut
	 * d'affichage, et la note elle-même renverrait à des créneaux qui ne sont nulle
	 * part.
	 */
	protected function getSlots(\structure\Structure $eStructure, array $cropPlan, array $filters): string {

		$names = \util\DateUi::months();

		$h = '';

		$seedling = $this->getSeedlingFilter($eStructure, $filters);

		if($seedling !== '') {
			$h .= '<div class="community-block-filters mb-1">'.$seedling.'</div>';
		}

		$isSimple = ($filters['view'] === PlantSetting::COMMUNITY_VIEW_SIMPLE);

		// Les barres ne portent plus leur nom : c'est la légende qui les nomme, une
		// fois pour toutes, au lieu de répéter « plantation » sur chaque ligne.
		$h .= $this->getBarLegend($cropPlan);

		$h .= '<div class="community-slots'.($isSimple ? ' is-simple' : '').'">';

			$h .= '<div class="community-slot is-header">';
				// La gouttière de l'en-tête est vide et se trouve juste au-dessus des
				// barres de mode : elle leur sert de légende sans rien coûter en place.
				$h .= '<div class="community-slot-head">'.$this->getModeLegend().'</div>';
				$h .= $this->getMonthAxis($cropPlan['months'], TRUE);
			$h .= '</div>';

			foreach($cropPlan['slots'] as $slot) {
				$h .= $this->getSlot($slot, $names, $cropPlan['months'], $cropPlan['action'], $isSimple, $cropPlan['rangePeak'], $filters);
			}

		$h .= '</div>';

		if($cropPlan['aside'] > 0) {
			$h .= '<p class="community-block-note">';
				$h .= p(
					"{value} culture implantée en dehors de ces créneaux n'est pas représentée, faute d'un nombre de fermes suffisant.",
					"{value} cultures implantées en dehors de ces créneaux ne sont pas représentées, faute d'un nombre de fermes suffisant.",
					$cropPlan['aside']
				);
			$h .= '</p>';
		}

		return $h;

	}

	protected function getSlot(array $slot, array $names, array $months, string $action, bool $isSimple, array $peaks, array $filters): string {

		$isPlanting = ($action === \series\Cultivation::PLANTING);

		$h = '<div class="community-slot is-'.$action.'">';

			$h .= '<div class="community-slot-head">';

				$h .= '<div class="community-slot-month">'.mb_ucwords($names[$slot['month']]).'</div>';
				$h .= '<div class="community-slot-count">'.p("{value} ferme", "{value} fermes", $slot['structures']).'</div>';

				// Sous abri ou dehors : la question se pose avant de lire les dates, pas
				// après. Elle appartient donc à l'identité du créneau, avec le mois et
				// l'effectif, et non au détail qui suit la frise.
				$h .= $this->getSlotModes($slot['modes']);

			$h .= '</div>';

			$h .= '<div class="community-slot-timeline">';

				$h .= $this->getMonthGrid($months, TRUE);
				$h .= $this->getSlotSteps($slot, $isPlanting);
				$h .= $isSimple ?
					$this->getSlotRange($slot, $isPlanting, $peaks) :
					$this->getSlotRelief($slot, $isPlanting);

			$h .= '</div>';

			$h .= '<div class="community-slot-detail">';

				// Une culture peut porter plusieurs variétés : filtrer sur « Futsu Black »
				// garde les cultures qui en contiennent, donc aussi les variétés qui les
				// accompagnent. Elles sont justes dans l'échantillon, mais les afficher
				// sous un filtre variétal fait lire « Bleu de Hongrie » comme un résultat.
				$varieties = ($filters['variety'] === NULL) ?
					$slot['varieties'] :
					array_values(array_filter($slot['varieties'], fn($variety) => $variety['name'] === $filters['variety']));

				if($varieties) {

					$h .= '<div class="community-slot-varieties">';

						foreach($varieties as $variety) {

							$h .= '<span class="community-variety" '.attr('title', p("{value} ferme utilise cette variété sur ce créneau", "{value} fermes utilisent cette variété sur ce créneau", $variety['structures'])).'>';
								$h .= '<span class="community-variety-name">'.encode($variety['name']).'</span>';
								$h .= '<span class="community-variety-count">'.$variety['structures'].'</span>';
							$h .= '</span>';

						}

					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les étapes d'un créneau : leurs médianes nommées, et les délais qui les séparent.
	 *
	 * Le relief dit quand les choses se passent, pas combien de temps sépare deux
	 * étapes — et c'est ce délai qui se décide au moment de poser un plan de culture.
	 * Chaque barre court d'une médiane à l'autre et porte son nombre de semaines.
	 *
	 * Un repère par médiane, et un nom par étape — mais les deux ne tombent pas
	 * toujours au même endroit. Le semis et la plantation sont des instants : leur nom
	 * s'écrit à l'aplomb de leur repère. La récolte dure : son repère ouvre la période,
	 * un second la ferme, et le nom se pose entre les deux, au milieu de ce qu'il
	 * désigne. La fin de récolte n'est donc pas nommée, son trait suffit.
	 */
	protected function getSlotSteps(array $slot, bool $isPlanting): string {

		$steps = [];

		if($isPlanting and $slot['seedlingWeek'] !== NULL and $slot['seedlingGap'] !== NULL) {
			$steps[] = ['week' => $slot['seedlingWeek']['median'], 'label' => s("semis")];
		}

		$steps[] = [
			'week' => $slot['startWeek']['median'],
			'label' => $isPlanting ? s("plantation") : s("semis"),
		];

		if($slot['harvestFrom'] !== NULL) {

			// Le milieu se calcule sur l'axe continu, avant tout retour dans l'année :
			// une récolte qui court de la semaine 44 à la semaine 58 a son milieu en
			// semaine 51, quand la moyenne des semaines calendaires l'aurait posé en
			// semaine 25, en plein été et à l'opposé de la période.
			$harvestTo = ($slot['harvestTo'] !== NULL) ?
				$slot['harvestTo']['median'] :
				$slot['harvestFrom']['median'];

			// Le milieu se pose sur la grille des demi-semaines, où la semaine N a pour
			// centre la ligne 2N : le milieu des lignes 2×début et 2×fin vaut donc
			// début + fin, sans arrondi. Ramené à une semaine pleine, il tomberait
			// jusqu'à une demi-semaine à côté du milieu des deux repères.
			$steps[] = [
				'week' => $slot['harvestFrom']['median'],
				'label' => s("récolte"),
				'labelLine' => $slot['harvestFrom']['median'] + $harvestTo,
			];

		}

		// La fin de récolte borne la période sans la nommer : « récolte » est déjà écrit
		// au milieu, et un second intitulé se bousculerait avec le nombre de semaines.
		if($slot['harvestTo'] !== NULL) {
			$steps[] = ['week' => $slot['harvestTo']['median'], 'label' => NULL];
		}

		// Le nombre de semaines est celui que la barre dessine, l'écart entre les deux
		// médianes qu'elle relie — et non la médiane des écarts individuels. Les deux
		// statistiques diffèrent d'une ou deux semaines, et la seconde, si juste
		// soit-elle, contredisait la frise : le lecteur compte les semaines sur l'axe
		// gradué, il doit retrouver le chiffre écrit.
		$delays = [];

		if($isPlanting and $slot['seedlingWeek'] !== NULL and $slot['seedlingGap'] !== NULL) {

			$weeks = $slot['startWeek']['median'] - $slot['seedlingWeek']['median'];

			if($weeks > 0) {
				$delays[] = [
					'from' => $slot['seedlingWeek']['median'],
					'to' => $slot['startWeek']['median'],
					'weeks' => $weeks,
					'title' => p("{value} semaine entre le semis en pépinière et la plantation", "{value} semaines entre le semis en pépinière et la plantation", $weeks),
				];
			}

		}

		if($slot['harvestFrom'] !== NULL) {

			$weeks = $slot['harvestFrom']['median'] - $slot['startWeek']['median'];

			if($weeks > 0) {
				$delays[] = [
					'from' => $slot['startWeek']['median'],
					'to' => $slot['harvestFrom']['median'],
					'weeks' => $weeks,
					'title' => $this->getHarvestDelayTitle($isPlanting, $weeks),
				];
			}

		}

		// La récolte est la seule étape qui dure, et sa durée est ce qu'on cherche à
		// savoir en dernier : jusqu'à quand cette série produit-elle.
		if($slot['harvestFrom'] !== NULL and $slot['harvestTo'] !== NULL) {

			$weeks = $slot['harvestTo']['median'] - $slot['harvestFrom']['median'];

			if($weeks > 0) {
				$delays[] = [
					'from' => $slot['harvestFrom']['median'],
					'to' => $slot['harvestTo']['median'],
					'weeks' => $weeks,
					'title' => p("{value} semaine de récolte", "{value} semaines de récolte", $weeks),
				];
			}

		}

		$h = '<div class="community-steps">';

			$h .= '<div class="community-steps-labels">';

				foreach($steps as $step) {

					if($step['label'] === NULL) {
						continue;
					}

					// Le nom est centré sur sa ligne et déborde de part et d'autre : une
					// semaine fait vingt pixels, « plantation » en fait trois fois plus.
					// Cette ligne est celle du repère, sauf pour une période, qui se nomme
					// en son milieu.
					$line = isset($step['labelLine']) ?
						$this->getCycleLine($step['labelLine']) :
						2 * $this->getCycleWeek($step['week']);

					$h .= '<div class="community-step" '.attr('style', 'grid-column: '.max(1, $line - 1).' / '.min(105, $line + 1)).'>';
						$h .= '<span>'.$step['label'].'</span>';
					$h .= '</div>';

				}

			$h .= '</div>';

			$h .= '<div class="community-steps-delays">';

				// Le repère d'une étape appartient à l'étape, pas aux délais qu'elle
				// borne : deux délais qui se touchent en poseraient deux, encadrant la
				// semaine au lieu de la désigner. Posé ici, il est unique et tombe au
				// milieu de sa semaine — celle de la médiane, et non celle du nom, qui
				// s'écarte quand il désigne une période.
				foreach($steps as $step) {

					$week = $this->getCycleWeek($step['week']);

					$h .= '<div class="community-tick" '.attr('style', 'grid-column: '.(2 * $week)).'></div>';

				}

				foreach($delays as $delay) {
					$h .= $this->getDelay($delay);
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getHarvestDelayTitle(bool $isPlanting, int $weeks): string {

		return $isPlanting ?
			p("{value} semaine entre la plantation et la première récolte", "{value} semaines entre la plantation et la première récolte", $weeks) :
			p("{value} semaine entre le semis et la première récolte", "{value} semaines entre le semis et la première récolte", $weeks);

	}

	/**
	 * Un délai de frise : un trait pointillé d'un milieu de semaine à l'autre, sur la
	 * grille des demi-semaines, et son nombre de semaines. Les repères qui le bornent
	 * appartiennent aux étapes, pas à lui.
	 */
	protected function getDelay(array $delay): string {

		$h = '';

		// Un délai qui traverse le Nouvel An se dessine en deux morceaux, à la fin puis
		// au début de l'année : la culture ne s'interrompt pas.
		$segments = $this->getCycleSegments($delay['from'], $delay['to']);

		$longest = 0;

		foreach($segments as $position => $segment) {
			if($segment[1] - $segment[0] > $segments[$longest][1] - $segments[$longest][0]) {
				$longest = $position;
			}
		}

		foreach($segments as $position => $segment) {

			[$start, $stop] = $segment;

			$h .= '<div class="community-delay" ';
				$h .= attr('style', 'grid-column: '.$start.' / '.$stop).' ';
				$h .= attr('title', $delay['title']);
			$h .= '>';

				// Le nombre déborde de son trait quand celui-ci est court — la laitue de
				// février ne récolte que deux semaines — et c'est préférable à ne rien
				// écrire : son fond opaque le garde lisible par-dessus ce qu'il recouvre.
				// En deçà de deux semaines il masquerait ses propres repères, et le trait
				// seul dit alors l'essentiel, qui est que la durée est négligeable.
				if($position === $longest and $stop - $start >= 4) {
					$h .= '<span>'.s("{value} sem.", $delay['weeks']).'</span>';
				}

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Ramène une semaine de l'axe continu sur les 52 colonnes de l'année.
	 */
	protected function getCycleWeek(int $week): int {

		// Le modulo travaille dans les deux sens : un index négatif désigne l'année
		// précédente — la pépinière d'un poireau planté en février —, un index au-delà
		// de 52 l'année suivante.
		return ((($week - 1) % 52) + 52) % 52 + 1;

	}

	/**
	 * Ramène une ligne de la grille des demi-semaines dans l'année.
	 *
	 * Même rôle que `getCycleWeek()` un cran plus fin : la grille compte 104 colonnes,
	 * et une position calculée sur l'axe continu peut tomber avant ou après l'année.
	 */
	protected function getCycleLine(int $line): int {

		return ((($line - 1) % 104) + 104) % 104 + 1;

	}

	/**
	 * Découpe une période en morceaux visibles sur les 52 colonnes de l'année.
	 *
	 * Les périodes sont calculées sur un axe continu, où une récolte de carotte semée
	 * en octobre s'étend de la semaine 70 à la semaine 77. Ramenées telles quelles sur
	 * un calendrier, elles se tasseraient contre le bord droit ; ici elles repassent
	 * par janvier, ce qui est ce que fait la culture.
	 */
	protected function getCycleSegments(int $from, int $to): array {

		// Le découpage se fait en demi-semaines : un délai court d'un milieu de semaine
		// à l'autre, là où sont les repères, et non d'un bord à l'autre. La grille
		// compte donc 104 colonnes, et le milieu de la semaine N y est la ligne 2N.
		$span = max(0, $to - $from);
		$from = $this->getCycleWeek($from);

		if($span >= 51) {
			return [[1, 105]];
		}

		$start = 2 * $from;
		$stop = $start + 2 * $span;

		if($stop <= 105) {
			return [[$start, $stop]];
		}

		// Un délai qui déborde reprend au premier jour de l'année : les deux morceaux
		// se partagent la ligne 105, qui est aussi la ligne 1 de l'année suivante.
		return [[$start, 105], [1, $stop - 104]];

	}

	/**
	 * Le relief hebdomadaire d'un créneau : pépinière, implantation et récolte.
	 *
	 * Chaque semaine porte une colonne dont la hauteur dit combien de fermes du
	 * créneau y font l'action. Les trois séries partagent la même bande et sont
	 * superposées, non empilées : ce ne sont pas des parts d'un tout, ce sont trois
	 * choses qui arrivent en même temps, et les empiler ferait lire une somme qui
	 * n'existe pas. Les recouvrements sont d'ailleurs rares, l'implantation d'un
	 * créneau ne tenant que sur les semaines de son mois.
	 *
	 * Chaque série est mise à sa propre échelle. L'implantation tient sur quelques
	 * semaines quand la récolte s'étale sur dix ou vingt, et une échelle commune
	 * écraserait la première : ce sont les formes et leur décalage qui se comparent,
	 * pas les hauteurs entre elles.
	 */
	protected function getSlotRelief(array $slot, bool $isPlanting): string {

		// Sans pépinière en semis direct : la série est vide, la dessiner ajouterait
		// une ligne plate sans objet.
		$types = $isPlanting ?
			['harvest', 'seedling', 'implantation'] :
			['harvest', 'implantation'];

		$labels = [
			'seedling' => s("semis en pépinière"),
			'implantation' => $isPlanting ? s("plantation") : s("semis direct"),
			'harvest' => s("récolte"),
		];

		// Une seule échelle pour les trois étapes. Chaque semaine compte des cultures
		// qui font quelque chose — semer, planter, récolter — et ces effectifs se
		// comparent directement : sur un radis de février, 181 cultures semées la
		// semaine du pic contre 408 en récolte. Des échelles séparées faisaient monter
		// le semis au plafond de la bande comme la récolte, et laissaient croire à
		// deux volumes égaux.
		//
		// Les aires, elles, ne se comparent pas : une culture pèse une semaine en
		// implantation et une dizaine en récolte. C'est une lecture instantanée, semaine
		// par semaine, pas une lecture de surface.
		$scale = max(
			$slot['curves']['implantation']['max'],
			$slot['curves']['seedling']['max'],
			$slot['curves']['harvest']['max']
		);

		$scales = [
			'seedling' => $scale,
			'implantation' => $scale,
			'harvest' => $scale,
		];

		// Échelles adaptatives : une étape très étalée finit en liseré illisible — la
		// pépinière d'une aubergine de juin, répartie sur dix-sept semaines, tombait à
		// un quart de la bande. Celles qui n'atteignent pas la hauteur minimale sont
		// remontées jusqu'à elle, au prix d'une hauteur qui cesse d'être strictement
		// proportionnelle à l'effectif. La lisibilité prime, dès lors que l'infobulle
		// rend l'effectif exact de chaque semaine.
		$floor = PlantSetting::COMMUNITY_RELIEF_PEAK_MIN_SHARE / 100;

		foreach($types as $type) {

			$serie = $slot['curves'][$type];

			if($serie['max'] == 0 or $scales[$type] == 0) {
				continue;
			}

			if($serie['max'] / $scales[$type] < $floor) {
				$scales[$type] = $serie['max'] / $floor;
			}

		}

		$h = '<div class="community-relief">';

			$h .= '<svg class="community-relief-svg" viewBox="0 0 52 100" preserveAspectRatio="none">';

				// La récolte est tracée en premier, donc au fond : c'est la plus large,
				// et les deux autres doivent rester lisibles par-dessus.
				foreach($types as $type) {

					$serie = $slot['curves'][$type];

					if($scales[$type] === 0) {
						continue;
					}

					$occupied = array_keys(array_filter($serie['curve'], fn($value) => $value > 0));

					if($occupied === []) {
						continue;
					}

					$first = min($occupied);
					$last = max($occupied);

					$y = fn($week) => round(100 - $serie['curve'][$week] / $scales[$type] * 100, 2);

					// La courbe s'ouvre et se referme à sa hauteur réelle, sur le bord de
					// sa première et de sa dernière semaine — pas par une pente vers zéro,
					// qui inventerait une montée en puissance et une extinction que la
					// donnée ne dit pas. La période commence et s'arrête net.
					$points = [($first - 1).','.$y($first)];

					for($week = $first; $week <= $last; $week++) {
						$points[] = round($week - 0.5, 2).','.$y($week);
					}

					$points[] = $last.','.$y($last);

					$line = 'M'.implode(' L', $points);

					$h .= '<path class="community-relief-area is-'.$type.'" '.attr('d', $line.' L'.$last.',100 L'.($first - 1).',100 Z').'/>';
					$h .= '<path class="community-relief-line is-'.$type.'" '.attr('d', $line).'/>';

				}

			$h .= '</svg>';

			$h .= '<div class="community-relief-weeks">';

				for($week = 1; $week <= 52; $week++) {

					$title = [];

					foreach($types as $type) {

						// La redistribution des récoltes déclarées au mois donne des
						// comptes fractionnaires : on rend un effectif, pas une décimale.
						$count = (int)round($slot['curves'][$type]['counts'][$week]);

						if($count > 0) {
							$title[] = p("{value} culture en {action}", "{value} cultures en {action}", $count, ['action' => $labels[$type]]);
						}

					}

					$h .= '<div class="community-relief-week" '.attr('title', s("Semaine {value}", $week).($title ? ' · '.implode(' · ', $title) : '')).'></div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le choix de l'origine du plant, à droite du titre des créneaux.
	 *
	 * Plant produit sur place ou plant acheté : ce sont deux itinéraires, et le
	 * premier passe par une pépinière que le second n'a pas. Les mêler donnait un
	 * relief de pépinière calculé sur la moitié des cultures seulement, sans que rien
	 * ne le dise.
	 *
	 * Rien à proposer en semis direct, où la question ne se pose pas.
	 */
	protected function getSeedlingFilter(\structure\Structure $eStructure, array $filters): string {

		if($filters['action'] !== \series\Cultivation::PLANTING) {
			return '';
		}

		$url = fn(array $changes) => self::urlCropPlan($eStructure, $filters, $changes);

		// Choix exclusif : un plant acheté n'a pas de pépinière, un plant produit sur
		// place en a une. Les agréger donnait une étape de pépinière calculée sur la
		// moitié des cultures, mêlée à une moitié qui n'en a jamais eu.
		$h = '<div class="util-filter-options">';

			$h .= $this->getFilterOption(s("Plant autoproduit"), $url(['seedling' => \series\Cultivation::YOUNG_PLANT]), $filters['seedling'] === \series\Cultivation::YOUNG_PLANT);
			$h .= $this->getFilterOption(s("Plant acheté"), $url(['seedling' => \series\Cultivation::YOUNG_PLANT_BOUGHT]), $filters['seedling'] === \series\Cultivation::YOUNG_PLANT_BOUGHT);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le choix entre les deux lectures, qui vaut pour les deux onglets.
	 *
	 * Les deux vues portent exactement le même calcul : la simplifiée en donne le
	 * résumé — un point par étape et l'intervalle où se tient le gros des fermes, les
	 * bandes de densité des conduites —, l'experte le détail dont ce résumé est tiré,
	 * le relief hebdomadaire des créneaux et la grille des conduites.
	 */
	protected function getViewFilter(\structure\Structure $eStructure, array $filters): string {

		$url = fn(array $changes) => self::urlCropPlan($eStructure, $filters, $changes);

		$h = '<div class="util-filter-options">';

			$h .= $this->getFilterOption(s("Vue simplifiée"), $url(['view' => PlantSetting::COMMUNITY_VIEW_SIMPLE]), $filters['view'] === PlantSetting::COMMUNITY_VIEW_SIMPLE);
			$h .= $this->getFilterOption(s("Vue experte"), $url(['view' => PlantSetting::COMMUNITY_VIEW_EXPERT]), $filters['view'] === PlantSetting::COMMUNITY_VIEW_EXPERT);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le résumé d'un créneau : une barre par étape, autour de sa médiane.
	 *
	 * Là où le relief montre l'année entière, cette vue s'en tient au cœur de chaque
	 * étape : la barre couvre la part de fermes réglée en configuration, le point
	 * marque la médiane. Une barre courte est une pratique resserrée, une barre longue
	 * une étape que chacun place où il veut.
	 *
	 * Son épaisseur suit l'effectif de la semaine, sur une échelle commune à tous les
	 * créneaux : elle distingue une pratique qui culmine en son milieu d'une autre
	 * étalée à plat, et un mois de pointe d'un mois secondaire.
	 *
	 * Seul le *début* de récolte est représenté, pas sa durée : c'est la date qu'on
	 * cherche en posant un plan de culture, et la seule des deux que l'échantillon
	 * mesure sans biais connu.
	 */
	protected function getSlotRange(array $slot, bool $isPlanting, array $peaks): string {

		$h = '<div class="community-ranges">';

			foreach($slot['ranges'] as $row => $range) {

				$from = $this->getCycleWeek($range['from']);
				$to = $this->getCycleWeek($range['to']);
				$median = $this->getCycleWeek($range['median']);

				$arguments = ['share' => $range['share'], 'from' => $from, 'to' => $to, 'median' => $median];

				// À 100 %, l'intervalle n'écarte plus personne et n'est plus un intervalle :
				// il couvre tout le créneau, et l'annoncer en pourcents laisserait croire
				// qu'un tri a eu lieu.
				$title = ($range['share'] >= 100) ?
					match($range['type']) {
						'seedling' => s("Toutes les fermes sèment en pépinière entre la semaine {from} et la semaine {to}, la moitié avant la semaine {median}", $arguments),
						'implantation' => $isPlanting ?
							s("Toutes les fermes plantent entre la semaine {from} et la semaine {to}, la moitié avant la semaine {median}", $arguments) :
							s("Toutes les fermes sèment entre la semaine {from} et la semaine {to}, la moitié avant la semaine {median}", $arguments),
						'harvest' => s("Toutes les fermes commencent à récolter entre la semaine {from} et la semaine {to}, la moitié avant la semaine {median}", $arguments),
					} :
					match($range['type']) {
						'seedling' => s("{share} % des fermes sèment en pépinière entre la semaine {from} et la semaine {to}, la moitié avant la semaine {median}", $arguments),
						'implantation' => $isPlanting ?
							s("{share} % des fermes plantent entre la semaine {from} et la semaine {to}, la moitié avant la semaine {median}", $arguments) :
							s("{share} % des fermes sèment entre la semaine {from} et la semaine {to}, la moitié avant la semaine {median}", $arguments),
						'harvest' => s("{share} % des fermes commencent à récolter entre la semaine {from} et la semaine {to}, la moitié avant la semaine {median}", $arguments),
					};

				// L'échelle vient de l'ensemble des créneaux : c'est ce qui rend l'épaisseur
				// comparable d'un mois à l'autre. Sans effectif nulle part, le diviseur ne
				// sert qu'à ne pas diviser par zéro.
				$peak = ($peaks[$range['type']] ?? 0) ?: 1;

				// Une semaine par cellule : une plage à cheval sur le Nouvel An se répartit
				// d'elle-même aux deux bouts de l'axe, sans découpage.
				foreach($range['values'] as $index => $value) {

					$week = $this->getCycleWeek($index);

					// Le plancher est en pixels et vit dans la feuille de style : une
					// semaine creuse ne doit pas disparaître, mais c'est une contrainte de
					// lisibilité à l'écran, pas une correction de la donnée.
					$height = round($value / $peak * 100);


					$h .= '<div class="community-range is-'.$range['type'].'" ';
						$h .= attr('style', 'grid-row: '.($row + 1).'; grid-column: '.(2 * $week - 1).' / span 2; height: '.$height.'%').' ';
						$h .= attr('title', $title);
					$h .= '></div>';

				}

				// La médiane par-dessus la barre, au milieu de sa semaine — le même point
				// d'ancrage que le repère et le nom de l'étape, en haut du créneau.
				$h .= '<div class="community-range-median is-'.$range['type'].'" ';
					$h .= attr('style', 'grid-row: '.($row + 1).'; grid-column: '.(2 * $median - 1).' / span 2').' ';
					$h .= attr('title', $title);
				$h .= '></div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La légende des couleurs de frise, sous le titre du bloc.
	 *
	 * Nommer chaque barre sur chaque ligne revenait à écrire douze fois « plantation »
	 * pour une information qui ne change jamais. La légende le dit une fois, et rend
	 * aux barres la place d'afficher ce qui, lui, varie.
	 */
	protected function getBarLegend(array $cropPlan): string {

		$items = [];

		if($cropPlan['action'] === \series\Cultivation::PLANTING) {

			// Rien à nommer quand aucune culture ne passe par la pépinière : c'est le
			// cas de la vue « plant acheté », où l'étape n'existe pas.
			if($cropPlan['series']['seedling']['total'] > 0) {
				$items['seedling'] = s("Semis en pépinière");
			}

			$items['implantation'] = s("Plantation");

		} else {
			$items['implantation'] = s("Semis direct");
		}

		$items['harvest'] = s("Récolte");

		$h = '<div class="community-legend">';

			foreach($items as $type => $label) {
				$h .= '<span class="community-legend-item is-'.$type.'">'.$label.'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La légende des barres de mode, posée dans la gouttière de l'en-tête.
	 *
	 * Elle tombe juste au-dessus de la colonne des barres de mode : chaque créneau
	 * n'a donc plus qu'à porter ses deux pourcentages, sans répéter les mots.
	 */
	protected function getModeLegend(): string {

		$h = '<div class="community-modes-legend">';
			$h .= '<span class="community-modes-label is-'.\series\Series::OPEN_FIELD.'">'.s("plein champ").'</span>';
			$h .= '<span class="community-modes-label is-'.\series\Series::GREENHOUSE.'">'.s("abri").'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La répartition d'un créneau entre plein champ et sous abri.
	 *
	 * Rendue en barre proportionnelle, le pourcentage écrit dans la part qu'il
	 * mesure : c'est un partage d'un tout, et poser le chiffre sur sa part évite
	 * d'avoir à rapprocher une longueur d'une légende chiffrée posée à côté.
	 *
	 * Les deux parts font toujours cent et remplissent toute la barre. Sur un partage
	 * très déséquilibré, la part mince ne peut pas porter son chiffre — elle reste
	 * alors muette, et le chiffre de l'autre suffit à le déduire.
	 */
	protected function getSlotModes(array $modes): string {

		if($modes === []) {
			return '';
		}

		$labels = [
			\series\Series::OPEN_FIELD => s("plein champ"),
			\series\Series::GREENHOUSE => s("sous abri"),
		];

		$h = '<div class="community-modes-bar">';

			foreach($modes as $mode => $share) {

				$h .= '<div class="community-modes-part is-'.$mode.'" ';
					$h .= attr('style', 'flex-grow: '.$share).' ';
					$h .= attr('title', $share.' % '.$labels[$mode]);
				$h .= '>';

					// En deçà, la part est trop étroite pour son chiffre : l'écrire le
					// ferait déborder sur la part voisine.
					if($share >= 12) {
						$h .= '<span>'.$share.' %</span>';
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getEmpty(array $filters): string {

		$h = '<div class="community-empty">';
			$h .= '<p>'.s("Aucune culture ne correspond à ces filtres.").'</p>';
			$h .= '<p>'.s("Changez de mode de culture, de conduite, ou retirez la recherche par variété pour retrouver un échantillon.").'</p>';
		$h .= '</div>';

		return $h;

	}

}
?>
