<?php
namespace series;

/**
 * La comparaison d'une espèce entre les fermes d'un groupe.
 *
 * ⚠️ **Le calendrier passe devant les chiffres**, et ce n'est pas une préférence de mise en page.
 * Trois semaines d'écart entre deux semis se **voient** sur une frise et se **calculent** sur un
 * tableau : c'est l'écart qui enseigne, pas la valeur. Les chiffres viennent dessous, pour qui
 * veut ensuite le nombre exact.
 */
class CompareAiUi {

	/**
	 * ⚠️ La feuille du plan de culture en plus de la nôtre : la barre de saison, la grille et le
	 * placement des pastilles sont les siens. Vérifié, ses 2 466 lignes sont toutes bornées par
	 * `.series-*` ou `.flow-timeline-*`, aucune ne déborde sur le reste de l'écran du groupe.
	 */
	public function __construct() {
		\Asset::css('series', 'series.css');
		\Asset::css('series', 'compare.css');
		\Asset::js('series', 'compareAi.js');
	}

	/**
	 * Les choix de l'écran sous la bande : la ferme de départ, celle d'en face, et l'espèce.
	 *
	 * ⚠️ Ils voyagent dans l'URL avec le sous-groupe, jamais en session : un lien envoyé montre la
	 * même comparaison, et c'est déjà la façon dont ce produit fait voyager un contexte.
	 *
	 * ⚠️ **Pas d'espèce à choisir sur la frise des espèces.** Partie d'une ferme sans espèce, la
	 * page montre toutes les espèces de la ferme (planche L2 arbitrée le 16/09/2026), et le nom de
	 * chacune ouvre son détail : un champ de recherche y proposerait un choix que la page a déjà
	 * fait à sa place. Il revient dès qu'une espèce est ouverte.
	 *
	 * « Comparer à » n'existe qu'avec une ferme de départ : sans elle, il n'y a rien à mettre en
	 * face.
	 */
	public function getSelectors(\structure\Structure $eStructure, \Collection $cSpecies, \plant\Plant $ePlant, int $season, int $structures, \Collection $cStructureFarm, \structure\Structure $eStructureFarm, \structure\Structure $eStructureAgainst, \structure\Section $eSection = new \structure\Section()): string {

		$url = $this->getUrl($eStructure, $ePlant, $season, $eStructureFarm, $eStructureAgainst, $eSection);

		// ⚠️ La ferme vient avant l'espèce : elle borne l'échantillon, quand l'espèce ne fait que le
		// découper. C'est la règle écrite sur `util-filters` dans le design system. La saison, qui
		// change l'échantillon, les précède dans la bande de l'écran (`getSeason()`).
		$h = '<div class="util-filters mb-2">';

			$h .= $this->getFarmFilter($url, $cStructureFarm, $eStructureFarm);

			if($eStructureFarm->notEmpty()) {
				$h .= $this->getAgainstFilter($url, $cStructureFarm, $eStructureFarm, $eStructureAgainst);
			}

			if($eStructureFarm->empty() or $ePlant->notEmpty()) {
				$h .= $this->getSpeciesFilter($url, $cSpecies, $ePlant);
			}

		$h .= '</div>';

		$h .= $this->getScope($cStructureFarm->count(), $structures);

		return $h;

	}

	/**
	 * La saison, sur la rangée des onglets de l'écran (`CommunityTemplate::$mainFilter`) : elle
	 * change l'échantillon, les espèces proposées étant celles que le groupe cultive cette
	 * saison-là, et passe donc avant les choix qui le découpent.
	 */
	public function getSeason(\structure\Structure $eStructure, \plant\Plant $ePlant, array $seasons, int $season, \structure\Structure $eStructureFarm, \structure\Structure $eStructureAgainst, \structure\Section $eSection = new \structure\Section()): string {

		return $this->getSeasonFilter($this->getUrl($eStructure, $ePlant, $season, $eStructureFarm, $eStructureAgainst, $eSection), $seasons, $season);

	}

	/**
	 * L'adresse d'un choix de l'écran.
	 *
	 * ⚠️ La portée est un segment de l'adresse et se pose donc **avant** la requête : l'ancien
	 * paramètre s'ajoutait à la fin, derrière `season` et `plant`, et se lisait comme une
	 * option de plus. C'est l'url helper qui la porte, l'adresse ne se compose plus par ajout.
	 *
	 * Chaque filtre change sa valeur et garde les autres : `$change` ne porte que ce qui
	 * bouge, une valeur NULL retire le paramètre. Changer de ferme retire celle d'en face, qui
	 * pourrait être la nouvelle ferme de départ.
	 */
	protected function getUrl(\structure\Structure $eStructure, \plant\Plant $ePlant, int $season, \structure\Structure $eStructureFarm, \structure\Structure $eStructureAgainst, \structure\Section $eSection): \Closure {

		$query = [
			'season' => $season,
			'farm' => $eStructureFarm->notEmpty() ? $eStructureFarm['id'] : NULL,
			'against' => $eStructureAgainst->notEmpty() ? $eStructureAgainst['id'] : NULL,
			'plant' => $ePlant->notEmpty() ? $ePlant['id'] : NULL
		];

		return fn(array $change) => \structure\StructureUi::urlCommunityCompare($eStructure, $eSection)
			.'?'.http_build_query(array_filter(array_merge($query, $change), fn(?int $value) => $value !== NULL));

	}

	/**
	 * Le périmètre comparé, quand il est plus étroit que le groupe.
	 *
	 * ⚠️ **Un compte et jamais une phrase qui nomme.** L'écran diffère d'un lecteur à l'autre
	 * depuis que le partage porte des publics : une ferme qui n'ouvre qu'à l'équipe manque au
	 * producteur, qui prendrait sinon une comparaison partielle pour celle du groupe entier. Un
	 * chiffre le lui dit sans désigner personne — nommer les fermes absentes reviendrait à lui
	 * apprendre laquelle lui a refusé la lecture.
	 *
	 * Rien quand les deux comptes coïncident : un écran complet n'a pas à se justifier.
	 */
	protected function getScope(int $compared, int $structures): string {

		if($compared >= $structures) {
			return '';
		}

		return '<p class="color-muted font-sm mb-1">'.p(
			"{value} ferme comparée sur les {structures} du groupe",
			"{value} fermes comparées sur les {structures} du groupe",
			$compared,
			['structures' => $structures]
		).'</p>';

	}

	/**
	 * La saison, en déroulant.
	 *
	 * Au-delà de deux valeurs la rangée d'options mange la largeur, et neuf saisons se
	 * parcourent très bien dans une liste. Même forme que le filtre des semis, `HeaderUi`.
	 */
	protected function getSeasonFilter(\Closure $url, array $seasons, int $season): string {

		$h = '<div class="util-filter">';

			$h .= '<div class="util-filter-label">'.s("Saison").'</div>';

			$h .= '<div>';

				// util-chip sans dropdown-toggle : le jeton porte déjà son chevron en
				// background-image, quand dropdown-toggle en poserait un second en :after.
				$h .= '<a class="util-chip is-active" data-dropdown="bottom-start">'.$season.'</a>';

				$h .= '<div class="dropdown-list">';

					foreach($seasons as $seasonOption) {
						$h .= '<a href="'.$url(['season' => $seasonOption]).'" class="dropdown-item'.(($seasonOption === $season) ? ' selected' : '').'">'.$seasonOption.'</a>';
					}

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La ferme dont on part, en déroulant au balisage du filtre de saison.
	 *
	 * La liste est celle des fermes comparables, déjà bornée au sous-groupe et au partage : une
	 * ferme qui n'y figure pas ne peut pas être choisie, quelle que soit l'adresse.
	 */
	protected function getFarmFilter(\Closure $url, \Collection $cStructureFarm, \structure\Structure $eStructureFarm): string {

		$options = [
			[$url(['farm' => NULL, 'against' => NULL]), s("Toutes les fermes"), $eStructureFarm->empty()]
		];

		foreach($cStructureFarm as $eStructureOption) {
			$options[] = [$url(['farm' => $eStructureOption['id'], 'against' => NULL]), encode($eStructureOption['name']), $eStructureOption->is($eStructureFarm)];
		}

		$label = $eStructureFarm->notEmpty() ? encode($eStructureFarm['name']) : s("Toutes les fermes");

		return $this->getDropdownFilter(s("Ferme"), $label, $eStructureFarm->notEmpty(), $options);

	}

	/**
	 * Ce qu'on met en face de la ferme de départ : le réseau, ou une autre ferme.
	 */
	protected function getAgainstFilter(\Closure $url, \Collection $cStructureFarm, \structure\Structure $eStructureFarm, \structure\Structure $eStructureAgainst): string {

		$options = [
			[$url(['against' => NULL]), s("Tout le réseau"), $eStructureAgainst->empty()]
		];

		foreach($cStructureFarm as $eStructureOption) {

			if($eStructureOption->is($eStructureFarm)) {
				continue;
			}

			$options[] = [$url(['against' => $eStructureOption['id']]), encode($eStructureOption['name']), $eStructureOption->is($eStructureAgainst)];

		}

		$label = $eStructureAgainst->notEmpty() ? encode($eStructureAgainst['name']) : s("Tout le réseau");

		return $this->getDropdownFilter(s("Comparer à"), $label, TRUE, $options);

	}

	/**
	 * Un filtre en déroulant, au balisage de `getSeasonFilter()`.
	 *
	 * ⚠️ `is-active` dit qu'une valeur est choisie : « Toutes les fermes » n'en est pas une, et le
	 * jeton reste alors au repos, comme un filtre qu'on n'a pas touché. « Tout le réseau » en est
	 * une, la cible de la comparaison.
	 *
	 * @param array $options `[adresse, libellé déjà encodé, choisie]`
	 */
	protected function getDropdownFilter(string $title, string $label, bool $active, array $options): string {

		$h = '<div class="util-filter">';

			$h .= '<div class="util-filter-label">'.$title.'</div>';

			$h .= '<div>';

				$h .= \main\DesignUi::getChipDropdown($label, $options, $active);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'espèce, dans l'omnibox du produit.
	 *
	 * ⚠️ Ni déroulant ni liste : mesuré sur un réseau de dev, **93 espèces** se comparent sur une
	 * saison. Une liste de 93 lignes ne se parcourt pas, on y cherche un nom — et chercher un nom
	 * est exactement ce que fait ce composant.
	 *
	 * ⚠️ Suggestions **locales** et non une route de recherche : la page porte déjà la liste, que
	 * `CommunityAction` a bornée au sous-groupe du lecteur. Une route aurait dû refaire ce bornage
	 * de son côté, et deux façons de calculer le même ensemble finissent toujours par diverger —
	 * c'est le défaut qu'on vient de retirer entre le rail et l'écran des membres.
	 */
	protected function getSpeciesFilter(\Closure $url, \Collection $cSpecies, \plant\Plant $ePlant): string {

		$suggestions = [];

		foreach($cSpecies as $ePlantOption) {

			// La vignette range la liste plus vite que le nom seul : c'est ainsi que l'omnibox
			// propose déjà les espèces sur la bibliothèque de cultures.
			$suggestions[] = [
				'group' => s("Espèces"),
				'value' => (string)$ePlantOption['id'],
				'label' => $ePlantOption['name'],
				'icon' => \plant\PlantUi::getVignetteHref($ePlantOption['fqn']),
				'url' => $url(['plant' => $ePlantOption['id']])
			];

		}

		$h = '<div class="util-filter compare-species">';

			$h .= '<div class="util-filter-label">'.s("Espèce comparée").'</div>';

			// Le jeton porte l'espèce ouverte quand il y en a une, et rien tant qu'on n'a pas
			// choisi : l'écran s'ouvre sans espèce, et un jeton posé d'office ferait croire à un
			// choix que personne n'a fait.
			$tokens = $ePlant->notEmpty() ? [[
				'group' => s("Espèces"),
				'value' => (string)$ePlant['id'],
				'label' => $ePlant['name']
			]] : [];

			$h .= \main\OmniboxUi::get(
				suggestions: $suggestions,
				tokens: $tokens,
				placeholder: s("Chercher une espèce...")
			);

		$h .= '</div>';

		return $h;

	}

	/**
	 * La frise et le tableau, ou le bloc qui dit pourquoi il n'y a rien à montrer.
	 */
	public function getCompare(\map\Configuration $eConfiguration, \Collection $cStructure, \Collection $cByStructure, \plant\Plant $ePlant, int $season): string {

		// ⚠️ Vide veut dire « cette espèce, cette saison, ces fermes : rien » — et non « rien à
		// afficher ». Rendre une chaîne vide laissait deux sélecteurs au-dessus du néant, sans
		// dire si l'écran cherchait encore ou s'il avait répondu.
		if($cByStructure->empty()) {
			return self::getEmpty($ePlant);
		}

		// La légende avant la frise : sous elle, elle se lisait après la barre de défilement, donc
		// séparée de ce qu'elle nomme par le seul élément de l'écran qui n'est pas la frise.
		$h = self::getLegend();

		$h .= self::getTimeline($eConfiguration, $cStructure, $cByStructure, $season);

		$h .= self::getFigures($cStructure, $cByStructure, $season);

		return $h;

	}

	/**
	 * ⚠️ Une légende, et c'est le seul texte que cette frise porte : des formes qui ne se
	 * distinguent que par leur couleur ne se distinguent pas du tout. Ce n'est pas expliquer
	 * l'interface, c'est nommer un signe que rien d'autre ne peut nommer.
	 *
	 * ⚠️ **Deux rangées, un axe chacune** (passe ui du 16/09/2026) : la couleur dit le geste, la
	 * forme dit l'état. Six entrées à plat mêlaient les deux, et un « S » vert en contour posé à
	 * côté du « S » vert plein se lisait comme un quatrième geste. La seconde rangée porte donc des
	 * pastilles neutres : une couleur de geste la ferait lire comme un geste.
	 *
	 * ⚠️ `mb-2` sur la première rangée, au-dessus du `gap` de 1 rem qui sépare les entrées d'une
	 * même rangée quand elle passe à la ligne : les deux axes doivent rester plus loin l'un de
	 * l'autre que deux lignes d'un même axe.
	 *
	 * ⚠️ Une seule entrée de récolte par état, et non une pour « sans date de semis » : les hachures
	 * qui la distinguaient étaient invisibles sur la pastille de 23 px, et une ligne sans semis
	 * n'affiche déjà ni « S » ni « P ».
	 */
	protected static function getLegend(): string {

		$h = '<div class="compare-legend mb-2">';

			foreach([
				[ACTION_SEMIS_DIRECT, s("S"), s("Semis direct")],
				[ACTION_SEMIS_PEPINIERE, s("S"), s("Semis en pépinière")],
				[ACTION_PLANTATION, s("P"), s("Plantation")]
			] as [$action, $letter, $label]) {
				$h .= '<span><span class="util-timeline-event compare-event" style="background-color: '.self::COLORS[$action].'">'.$letter.'</span>'.$label.'</span>';
			}

			$h .= '<span><span class="util-timeline-event compare-event-harvest-done">'.s("R").'</span>'.s("Récolte").'</span>';

		$h .= '</div>';

		$h .= '<div class="compare-legend">';
			$h .= '<span><span class="util-timeline-event compare-event" style="background-color: var(--muted)"></span>'.s("Réalisé").'</span>';
			$h .= '<span><span class="util-timeline-event util-timeline-event-expected compare-event" style="color: var(--muted)"></span>'.s("Prévu").'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'écran quand le groupe n'a aucune culture à mettre en regard.
	 *
	 * ⚠️ Il remplace l'écran entier, sélecteurs compris : sans espèce à proposer, le champ de
	 * recherche et l'invitation à s'en servir désigneraient tous les deux un choix qui n'existe
	 * pas.
	 *
	 * Dans un sous-groupe, le bloc offre la même sortie que ses voisins, les fermes et les
	 * statistiques : la comparaison de tout le groupe, quand elle a une espèce à proposer et que le
	 * lecteur peut y aller (`$hasCompareCommunity`, `CommunityAction::hasCompareCommunity()`).
	 */
	public function getNoCultivation(\structure\Structure $eStructure, bool $hasSharing, int $structures = 0, \structure\Section $eSection = new \structure\Section(), bool $hasCompareCommunity = FALSE): string {

		// Un sous-groupe sans aucune ferme rattachée : personne n'a de lecture à accorder, et l'écran
		// dit ce que disent ses voisins, les fermes et les statistiques du même sous-groupe
		// (`VegetableDashboardAiUi::getNoFarm()`).
		if($eSection->notEmpty() and $structures === 0) {
			[$title, $reason] = VegetableDashboardAiUi::getNoFarm($eStructure, $eSection);
		} else {
			$title = s("Rien à comparer pour l'instant");
			$reason = $this->getNoCultivationReason($eStructure, $hasSharing, $eSection);
		}

		$h = '<div class="util-block util-block-empty">';

			$h .= '<h3>'.$title.'</h3>';
			$h .= '<p>'.$reason.'</p>';

			if($hasCompareCommunity) {

				$h .= '<div class="util-buttons">';
					$h .= '<a href="'.\structure\StructureUi::urlCommunityCompare($eStructure).'" class="btn btn-primary">'.s("Comparer les cultures de tout {the}", ['the' => \structure\StructureUi::getText($eStructure, 'the')]).'</a>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Deux causes, deux phrases : le lecteur d'un groupe dont personne n'a encore accordé la lecture
	 * chercherait sinon des plans de culture qui existent bel et bien.
	 *
	 * Un sous-groupe se nomme, comme sur l'onglet « Statistiques » : « aucune ferme de ce groupe »
	 * y serait faux, les autres fermes du groupe ayant un plan de culture. Le groupe entier se dit
	 * par le mot que le produit donne à son type (`VegetableDashboardAiUi::getNoFarm()`).
	 */
	protected function getNoCultivationReason(\structure\Structure $eStructure, bool $hasSharing, \structure\Section $eSection): string {

		if($eSection->notEmpty()) {

			$section = '<b>'.encode($eSection['name']).'</b>';

			return $hasSharing
				? s("Aucune ferme partagée par les membres de {value} n'a de plan de culture sur les saisons disponibles.", $section)
				: s("Aucune ferme partagée par les membres de {value} n'a accordé la lecture de sa production.", $section);

		}

		$community = \structure\StructureUi::getText($eStructure, 'this');

		return $hasSharing
			? s("Aucune ferme de {this} n'a de plan de culture sur les saisons disponibles.", ['this' => $community])
			: s("Aucune ferme de {this} n'a accordé la lecture de sa production.", ['this' => $community]);

	}

	/**
	 * L'écran tant qu'aucune espèce n'est demandée.
	 *
	 * ⚠️ Aucun bouton : le geste est le champ juste au-dessus, et un bouton qui y renverrait
	 * serait la rustine posée sur un défaut de conception. Le blanc seul ne convient pas non plus,
	 * il ne se distingue pas d'un écran qui charge.
	 */
	public function getNoSpecies(): string {

		return '<div class="util-block util-block-empty">'
			.'<h3>'.s("Choisissez une ferme ou une espèce à comparer").'</h3>'
			.'<p>'.s("La comparaison met en regard, pour les fermes du groupe, les semaines de semis et de plantation, les périodes de récolte prévues et obtenues, et les chiffres du plan de culture.").'</p>'
			.'</div>';

	}

	protected static function getEmpty(\plant\Plant $ePlant): string {

		return '<div class="util-block util-block-empty">'
			.'<h3>'.s("Rien à comparer sur {value}", encode($ePlant['name'])).'</h3>'
			.'<p>'.s("Aucune des fermes de ce groupe n'a daté ses interventions pour cette espèce et cette saison.").'</p>'
			.'</div>';

	}

	/**
	 * Une ligne par culture, groupées par ferme sous leur intertitre.
	 *
	 * ⚠️ **La barre de saison n'est pas écrite ici, elle est appelée.** `CultivationUi::getListSeason()`
	 * et `getListGrid()` sont celles du plan de culture, et elles ne demandent qu'une
	 * `map\Configuration` : la fenêtre de cet écran en est une, 9/4. Deux tracés du même axe
	 * finissaient par diverger — mesuré à 1,03 % d'écart entre celui d'ici et celui du plan —, et
	 * c'est ce qui rendait les deux écrans impossibles à superposer.
	 *
	 * ⚠️ Par **culture** et non par ferme, et c'est ce qui rend le style du plan de culture
	 * reprenable : une pastille fait 23 px, donc elle ne peut pas entrer dans une bande qui agrège
	 * toutes les cultures d'une ferme. Les gestes d'une même culture, eux, sont éloignés dans le
	 * temps — mesuré sur les 472 cultures du groupe qui portent les deux dates, l'écart médian
	 * entre semis et plantation est de **5 semaines**.
	 *
	 * ⚠️ Le tableau juste en dessous a la même structure, intertitre par ferme et une ligne par
	 * culture, et le même ordre chronologique : deux représentations du même objet se répondent
	 * ligne pour ligne, sinon celui qui suit une ligne de la frise ne retrouve pas son rang.
	 */
	protected static function getTimeline(\map\Configuration $eConfiguration, \Collection $cStructure, \Collection $cByStructure, int $season): string {

		$uiCultivation = new CultivationUi();

		[$startTs, $stopTs] = new PlaceUi()->getBounds($eConfiguration, $season);

		// `util-overflow-md` comme le plan de culture, et non `util-overflow` : au-dessus de 767 px
		// il n'y a pas de conteneur défilant, donc pas de barre de 2 px née du seul dépassement de
		// glyphe du dernier numéro de semaine. En dessous, le composant pose aussi le plancher de
		// largeur qui empêche la frise de se comprimer sans fin.
		$h = '<div class="compare-plan series-item-planning-wrapper util-overflow-md">';

			// L'en-tête ne se colle pas : ce qui défile ici est le conteneur horizontal, pas la
			// page, et un `position: sticky` y resterait sans effet visible.
			$h .= '<div class="series-item-header series-item-header-not-sticky series-item-planning">';

				$h .= '<div class="util-table-header"></div>';

				$h .= '<div class="util-table-header series-item-planning-timeline">';
					// `sparse` : nos vingt mois tombent dans la largeur qui reste à côté du rail des
					// membres, soit 7,8 px la semaine quand le plan de culture en a 12 pour les
					// mêmes semaines. Un numéro sur cinq suffit à se repérer, quatre-vingt-sept
					// numéros collés ne se lisent pas du tout.
					$h .= $uiCultivation->getListSeason($eConfiguration, $season, hasWeeks: TRUE, sparse: TRUE);
				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="series-item-body">';

				// La grille est posée une fois pour tout le corps et non ligne par ligne : elle
				// est en absolu sur lui, comme sur le plan de culture.
				$h .= $uiCultivation->getListGrid($eConfiguration, $season, hasWeeks: TRUE);

				foreach($cStructure as $eStructureFarm) {

					$cCultivation = $cByStructure[$eStructureFarm['id']] ?? new \Collection();

					if($cCultivation->empty()) {
						continue;
					}

					// La même ferme paraît deux fois sur l'écran, ici et dans le tableau : elle
					// s'y présente de la même façon, logo compris et à la même taille.
					$h .= '<div class="compare-timeline-group">';
						$h .= self::getFarmLogo($eStructureFarm);
						$h .= self::getFarmName($eStructureFarm, $season);
					$h .= '</div>';

					// ⚠️ Plus de rangs alternés : la grille passe derrière le tableau, donc tout
					// fond opaque la masque. Les rayures cachaient la grille exactement sur les
					// lignes qu'on lit contre elle, et la laissaient traverser les noms de ferme.
					// Le plan de culture ne peint que ses lignes de titre, pour cette raison.
					$h .= '<div>';

					foreach($cCultivation as $eCultivation) {

						$h .= '<div class="series-item series-item-planning">';

							// Le nom se coupe plutôt que de passer sur deux lignes : une ligne de
							// frise est un rang, pas un paragraphe, et une hauteur qui varie fait
							// perdre la ligne à l'oeil qui la suit. Le nom entier reste au survol.
							$name = encode($eCultivation['series']['name'] ?? '');

							$h .= '<div class="series-item-planning-details" title="'.$name.'">'.$name.'</div>';

							$h .= '<div class="series-item-planning-timeline">';
								$h .= self::getCultivationMarks($eCultivation, $startTs, $stopTs);
							$h .= '</div>';

						$h .= '</div>';

					}

					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La palette, en dur et par nature de geste.
	 *
	 * ⚠️ Jamais la couleur de l'intervention de chaque ferme, bien qu'elle existe : les actions
	 * sont clonées à la création d'une ferme, donc chacune peut recolorer son semis. Sur un écran
	 * qui compare des fermes, une teinte qui change de sens d'une ligne à l'autre ne compare plus
	 * rien.
	 *
	 * ⚠️ Ce sont les valeurs que le produit pose à la création, et elles sont de fait stables :
	 * mesuré sur les 5 927 fermes de la base, 99,4 à 99,6 % portent encore la couleur d'origine
	 * pour chacune des quatre interventions. Reprendre la palette du plan de culture, c'est donc
	 * reprendre ce que presque tout le monde voit — et les rares fermes qui ont recoloré leur
	 * action gardent leur choix chez elles, où il ne trompe personne.
	 */
	const COLORS = [
		ACTION_SEMIS_DIRECT => '#0D7A11',
		ACTION_SEMIS_PEPINIERE => '#C2005E',
		ACTION_PLANTATION => '#099A89',
		// ⚠️ La récolte n'est plus dans la frise sous cette couleur : son ruban a repris la forme
		// et le jeton `--harvest` du plan de culture. La valeur reste ici parce que la table dit la
		// palette des quatre interventions, et qu'un trou dedans se lirait comme un oubli.
		ACTION_RECOLTE => '#3076BD'
	];

	/**
	 * Les gestes d'une culture : le ruban de la culture, celui de la récolte, puis les pastilles.
	 *
	 * ⚠️ Le placement est celui du plan de culture, `PlaceUi::getPositionStyle()` : un pourcentage
	 * de jours sur la fenêtre, et non une colonne de semaine. C'est la même méthode qui place les
	 * pastilles de l'autre écran, donc les deux frises tombent au même endroit par construction.
	 *
	 * ⚠️ **Rien n'est coupé ni écarté en PHP quand une date sort de la fenêtre.** La bande porte
	 * `series-item-timeline`, dont le `overflow: hidden` traite déjà ce cas sur le plan de culture :
	 * le ruban meurt au bord, la pastille hors fenêtre devient invisible. Mesuré, 17 des 1 073
	 * cultures de la saison 2026 du réseau de dev récoltent au-delà du 30 avril N+1.
	 *
	 * ⚠️ Le ruban de fond court du premier geste à la dernière semaine de récolte, prévue ou obtenue. C'est lui qui
	 * fait la ligne : sans lui, une frise n'est qu'une suite de pastilles séparées par du vide et
	 * l'oeil ne suit plus rien.
	 *
	 * ⚠️ **La récolte reste un ruban et ne redevient pas une pastille par semaine.** Mesuré sur le
	 * plan de culture, 100 de ses 362 paires de pastilles voisines se chevauchent — 28 %, pire cas
	 * une pastille couvrant entièrement l'autre —, et ces collisions viennent toutes de ses 114
	 * pastilles de récolte hebdomadaires. Un ruban n'a pas ce défaut, et la période est de toute
	 * façon ce qu'on compare entre fermes : quand la récolte commence et combien de temps elle dure.
	 *
	 * ⚠️ Semis et plantation la même semaine ne font qu'**une** pastille, portant les deux lettres.
	 * Deux pastilles superposées, la seconde effaçant la première, c'est le défaut qu'on a retiré
	 * de cette frise ; il reviendrait en petit. Mesuré, 3 cultures sur 472 sont dans ce cas.
	 *
	 * ⚠️ **Et la fusion s'arrête là : à une semaine d'écart, les deux pastilles se recouvrent en
	 * partie et on les laisse.** Les fondre dirait « le même jour » d'un geste survenu sept jours
	 * plus tard, sur l'écran dont le sujet est justement l'écart entre les dates.
	 */
	protected static function getCultivationMarks(Cultivation $eCultivation, int $startTs, int $stopTs): string {

		$sowing = $eCultivation['sowingWeek'];
		$planting = $eCultivation['plantingWeek'];

		$sowingAction = $eCultivation['sowingAction'];

		// ⚠️ Semis et plantation la même semaine ne font qu'une pastille : pleine si les deux
		// gestes sont faits, en contour sinon. Une pastille pleine dit « fait » ; la poser pleine
		// sur un geste à moitié fait mentirait sur l'autre moitié.
		$gestures = [];

		if($sowing !== NULL and $sowing === $planting) {
			$done = ($eCultivation['sowingDone'] and $eCultivation['plantingDone']);
			$gestures[] = [$sowingAction, s("SP"), self::getGestureTitle($sowingAction, $sowing, $done, withPlanting: TRUE), $sowing, $done];
		} else {

			if($sowing !== NULL) {
				$gestures[] = [$sowingAction, s("S"), self::getGestureTitle($sowingAction, $sowing, $eCultivation['sowingDone']), $sowing, $eCultivation['sowingDone']];
			}

			if($planting !== NULL) {
				$gestures[] = [ACTION_PLANTATION, s("P"), self::getGestureTitle(ACTION_PLANTATION, $planting, $eCultivation['plantingDone']), $planting, $eCultivation['plantingDone']];
			}

		}

		$dated = ($sowing !== NULL or $planting !== NULL);

		$harvestTitle = $dated ? s("Récolte prévue") : s("Récolte prévue, sans date de semis ni de plantation");

		// Un ruban par campagne de récolte, prévue comme obtenue, découpées par le Lib.
		return self::getMarks($gestures, $eCultivation['harvestExpected'], $eCultivation['harvestDone'], $harvestTitle, $startTs, $stopTs);

	}

	/**
	 * La bande d'une ligne de frise : le ruban de fond, la récolte prévue, la récolte obtenue, puis
	 * les pastilles, dans cet ordre de peinture.
	 *
	 * ⚠️ Le ruban de fond court du premier geste à la fin de la dernière récolte, prévue **ou**
	 * obtenue : une récolte obtenue qui déborde la prévue sortirait sinon du ruban qui fait la
	 * ligne.
	 *
	 * ⚠️ Une récolte se termine au dimanche de sa dernière semaine, comme le ruban du plan de
	 * culture : une semaine est une durée, pas son lundi.
	 *
	 * ⚠️ **La récolte obtenue est pleine, par-dessus la prévue en contour** (planche L2, R1) : c'est
	 * ce qui laisse voir d'un coup d'oeil l'écart entre les deux, et un écart nul se lit comme un
	 * ruban plein sans contour autour. Son `z-index` la pose au-dessus de la prévue, dont le fond
	 * est blanc, et sous les pastilles.
	 *
	 * @param array $gestures `[intervention, lettre, titre, semaine, fait]`
	 * @param array $harvestExpected `[[première semaine, dernière semaine], …]`
	 * @param array $harvestDone Idem, pour la récolte obtenue
	 */
	protected static function getMarks(array $gestures, array $harvestExpected, array $harvestDone, string $harvestTitle, int $startTs, int $stopTs): string {

		$uiPlace = new PlaceUi();

		$period = fn(array $campaign) => [
			strtotime(week_date_starts($campaign[0])),
			strtotime(week_date_ends($campaign[1]).' + 1 DAY')
		];

		$bounds = [];

		foreach($gestures as [, , , $week]) {
			$bounds[] = strtotime(week_date_starts($week));
		}

		foreach([...$harvestExpected, ...$harvestDone] as $campaign) {
			$bounds = [...$bounds, ...$period($campaign)];
		}

		$h = '<div class="series-item-timeline compare-events">';

			if($bounds !== []) {
				$h .= '<div class="util-timeline-event util-timeline-event-frame compare-event-frame" style="'.$uiPlace->getPositionStyle($startTs, $stopTs, min($bounds), max($bounds)).'"></div>';
			}

			foreach($harvestExpected as $campaign) {

				[$from, $to] = $period($campaign);

				$h .= '<div class="util-timeline-event util-timeline-event-expected compare-event-harvest"'
					.' style="'.$uiPlace->getPositionStyle($startTs, $stopTs, $from, $to).'"'
					.' title="'.$harvestTitle.'">'.s("R").'</div>';

			}

			foreach($harvestDone as $campaign) {

				[$from, $to] = $period($campaign);

				$h .= '<div class="util-timeline-event compare-event-harvest-done"'
					.' style="'.$uiPlace->getPositionStyle($startTs, $stopTs, $from, $to).'"'
					.' title="'.s("Récolte obtenue").'">'.s("R").'</div>';

			}

			foreach($gestures as [$action, $letter, $title, $week, $done]) {
				$h .= self::getEvent($uiPlace, self::COLORS[$action], $letter, $title, $week, $done, $startTs, $stopTs);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une pastille, comme sur le plan de culture — même largeur posée, donc même chasse sur les
	 * deux écrans : pleine à lettre blanche quand le geste est fait, en contour de sa couleur quand
	 * il ne l'est pas encore.
	 *
	 * ⚠️ Le contour garde `compare-event` pour son `z-index`, qui la pose au-dessus des rubans de
	 * récolte : la couleur écrite en `style` l'emporte sur son blanc, et `util-timeline-event-expected`
	 * trace le contour dans cette couleur (`currentColor`).
	 */
	protected static function getEvent(PlaceUi $uiPlace, string $color, string $letter, string $title, string $week, bool $done, int $startTs, int $stopTs): string {

		$ts = strtotime(week_date_starts($week));

		$class = $done ? 'util-timeline-event compare-event' : 'util-timeline-event util-timeline-event-expected compare-event';
		$style = $done ? 'background-color: '.$color.';' : 'color: '.$color.';';

		return '<div class="'.$class.'"'
			.' style="'.$style.' '.$uiPlace->getPositionStyle($startTs, $stopTs, $ts, $ts, width: '1.2rem').'"'
			.' title="'.$title.'">'.$letter.'</div>';

	}

	/**
	 * Le titre d'une pastille, une phrase entière par geste et par état : l'accord change d'un geste
	 * à l'autre (« réalisé », « réalisée », « réalisés »), et une phrase assemblée en morceaux ne se
	 * traduit pas.
	 *
	 * `$withPlanting` dit que le semis et la plantation tombent la même semaine et ne font qu'une
	 * pastille.
	 */
	protected static function getGestureTitle(string $action, string $week, bool $done, bool $withPlanting = FALSE): string {

		$arguments = ['week' => week_number($week), 'year' => week_year($week)];

		if($withPlanting) {
			return match($action) {
				ACTION_SEMIS_DIRECT => $done
					? s("Semis direct et plantation réalisés en semaine {week}, {year}", $arguments)
					: s("Semis direct et plantation prévus en semaine {week}, {year}", $arguments),
				ACTION_SEMIS_PEPINIERE => $done
					? s("Semis en pépinière et plantation réalisés en semaine {week}, {year}", $arguments)
					: s("Semis en pépinière et plantation prévus en semaine {week}, {year}", $arguments),
			};
		}

		return match($action) {
			ACTION_SEMIS_DIRECT => $done
				? s("Semis direct réalisé en semaine {week}, {year}", $arguments)
				: s("Semis direct prévu en semaine {week}, {year}", $arguments),
			ACTION_SEMIS_PEPINIERE => $done
				? s("Semis en pépinière réalisé en semaine {week}, {year}", $arguments)
				: s("Semis en pépinière prévu en semaine {week}, {year}", $arguments),
			ACTION_PLANTATION => $done
				? s("Plantation réalisée en semaine {week}, {year}", $arguments)
				: s("Plantation prévue en semaine {week}, {year}", $arguments),
		};

	}

	/**
	 * Partie d'une ferme : une bande par espèce qu'elle cultive, et sous chacune une ligne par ferme
	 * comparée qui la cultive aussi, la ferme de départ en tête.
	 *
	 * ⚠️ **Une ligne par ferme et non par culture** (planche L2, R1) : sur cet écran on compare des
	 * conduites d'espèce, et les cultures d'une ferme se réunissent en un premier geste et des
	 * campagnes de récolte. Le détail par culture est l'écran par espèce, que le nom de l'espèce
	 * ouvre, borné aux mêmes fermes.
	 *
	 * ⚠️ Le premier geste seul, et non tous : une ferme qui sème six fois la même espèce ferait six
	 * pastilles serrées sur une seule ligne, et c'est le défaut de chevauchement que la frise par
	 * culture a retiré.
	 */
	public function getSpeciesTimeline(\map\Configuration $eConfiguration, \structure\Structure $eStructure, \Collection $cStructure, \Collection $cPlant, \structure\Structure $eStructureFarm, \structure\Structure $eStructureAgainst, int $season, \structure\Section $eSection = new \structure\Section()): string {

		if($cPlant->empty()) {
			return '<div class="util-block util-block-empty">'
				.'<h3>'.s("Rien à comparer pour {value}", encode($eStructureFarm['name'])).'</h3>'
				.'<p>'.s("Cette ferme n'a aucune culture d'une espèce du référentiel sur cette saison.").'</p>'
				.'</div>';
		}

		$uiCultivation = new CultivationUi();

		[$startTs, $stopTs] = new PlaceUi()->getBounds($eConfiguration, $season);

		$h = self::getLegend();

		$h .= '<div class="compare-plan series-item-planning-wrapper util-overflow-md">';

			$h .= '<div class="series-item-header series-item-header-not-sticky series-item-planning">';
				$h .= '<div class="util-table-header"></div>';
				$h .= '<div class="util-table-header series-item-planning-timeline">';
					$h .= $uiCultivation->getListSeason($eConfiguration, $season, hasWeeks: TRUE, sparse: TRUE);
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="series-item-body">';

				$h .= $uiCultivation->getListGrid($eConfiguration, $season, hasWeeks: TRUE);

				// Face au réseau, les autres fermes sont servies mais repliées sous chaque espèce,
				// même quand il n'y en a qu'une : l'écran s'ouvre sur une ligne par espèce, quelle
				// que soit la taille du réseau (mesure 24, arbitrée le 16/09/2026 : au
				// pire 858 lignes dépliées). Les déplier ne vaut pas un aller-retour au serveur,
				// tout tient déjà dans la requête de l'écran. Ferme à ferme, rien ne se replie :
				// la seconde ferme est l'objet même de la comparaison.
				$fold = $eStructureAgainst->empty();

				foreach($cPlant as $ePlant) {

					$url = \structure\StructureUi::urlCommunityCompare($eStructure, $eSection).'?'.http_build_query(array_filter([
						'season' => $season,
						'farm' => $eStructureFarm['id'],
						'against' => $eStructureAgainst->notEmpty() ? $eStructureAgainst['id'] : NULL,
						'plant' => $ePlant['id']
					], fn(?int $value) => $value !== NULL));

					$h .= '<div class="compare-timeline-group">';
						$h .= \plant\PlantUi::getVignette($ePlant, '2rem');
						$h .= '<a href="'.$url.'">'.encode($ePlant['name']).'</a>';
					$h .= '</div>';

					$h .= '<div class="compare-timeline-species">';

						$rest = 0;

						foreach($cStructure as $eStructureCompared) {

							$farm = $ePlant['farms'][$eStructureCompared['id']] ?? NULL;

							if($farm === NULL) {
								continue;
							}

							$gestures = [];

							if($farm['gesture'] !== NULL) {

								$gesture = $farm['gesture'];

								$letter = match($gesture['action']) {
									ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE => s("S"),
									ACTION_PLANTATION => s("P"),
								};

								$gestures[] = [$gesture['action'], $letter, self::getGestureTitle($gesture['action'], $gesture['week'], $gesture['done']), $gesture['week'], $gesture['done']];

							}

							$harvestTitle = ($farm['gesture'] !== NULL) ? s("Récolte prévue") : s("Récolte prévue, sans date de semis ni de plantation");

							$name = encode($eStructureCompared['name']);

							$folded = ($fold and $eStructureCompared->is($eStructureFarm) === FALSE);

							if($folded) {
								$rest++;
							}

							$h .= '<div class="series-item series-item-planning'.($folded ? ' compare-timeline-rest' : '').'">';
								$h .= '<div class="series-item-planning-details" title="'.$name.'">'.$name.'</div>';
								$h .= '<div class="series-item-planning-timeline">';
									$h .= self::getMarks($gestures, $farm['harvestExpected'], $farm['harvestDone'], $harvestTitle, $startTs, $stopTs);
								$h .= '</div>';
							$h .= '</div>';

						}

						if($rest > 0) {
							$h .= '<div class="util-table-full compare-timeline-more">';
								$h .= '<a data-action="series-compare-more">'.p("Voir l'autre ferme", "Voir les {value} autres fermes", $rest).'</a>';
							$h .= '</div>';
						}

					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les chiffres, sous la frise : une ligne par culture, sous l'intertitre de leur ferme.
	 *
	 * ⚠️ La ferme est un **intertitre** et non une cellule. Écrite dans la première cellule de sa
	 * première culture, les suivantes laissant la cellule vide, le tableau se lisait comme une
	 * liste de carottes dont la première aurait appartenu à une ferme. `tr-title` est le composant
	 * du dépôt pour ça, employé par `Role.u.php`, `Member.u.php` et `Csv.u.php`.
	 *
	 * ⚠️ Le nom de la série s'écrit alors **toujours**, y compris sur une ferme qui n'en a qu'une :
	 * il ne s'affichait sous condition que parce qu'il partageait la cellule du nom de ferme. Il a
	 * sa colonne maintenant, et une cellule vide y serait un trou.
	 *
	 * Deux séries d'une même espèce ne sont pas une erreur, ce sont deux conduites — et c'est
	 * justement ce qu'un formateur vient regarder, donc elles gardent leur ligne au lieu d'être
	 * additionnées.
	 */
	protected static function getFigures(\Collection $cStructure, \Collection $cByStructure, int $season): string {

		// Les deux blocs se touchaient, et le tableau se lisait comme la suite de la frise.
		// ⚠️ `mt-2` et non la marge par défaut : les titres du dépôt n'ont **aucune** marge haute
		// (`h1, h2, h3 { margin: 0 0 1rem }`, reboot.css:315), donc un `h3` posé entre deux blocs
		// colle à celui qu'il ne titre pas. C'est la forme majoritaire, 24 `h3` sur les 40 qui
		// portent une classe.
		$h = '<h3 class="mt-2">'.s("Liste des cultures").'</h3>';

		$h .= '<div class="util-overflow">';

			$h .= '<table class="tr-even">';

				$h .= '<thead><tr>';
					$h .= '<th>'.s("Culture").'</th>';
					$h .= '<th>'.s("Semis").'</th>';
					$h .= '<th>'.s("Plantation").'</th>';
					$h .= '<th class="text-end">'.s("Densité").'</th>';
					$h .= '<th class="text-end">'.s("Écartement").'</th>';
					$h .= '<th class="text-end">'.s("Rendement attendu").'</th>';
					$h .= '<th class="text-end">'.s("Surface").'</th>';
				$h .= '</tr></thead>';

				$h .= '<tbody>';

					foreach($cStructure as $eStructureFarm) {

						$cCultivation = $cByStructure[$eStructureFarm['id']] ?? new \Collection();

						if($cCultivation->empty()) {
							continue;
						}

						[$area, $yield] = self::getFarmTotals($cCultivation);

						// ⚠️ `td-vertical-align-middle` : `table td` est en `vertical-align: baseline`
						// (reboot.css:526), et le logo de 2,5 rem pousse la ligne de base de la
						// première cellule sous les chiffres des deux dernières.
						$h .= '<tr class="tr-title td-vertical-align-middle">';

							$h .= '<td colspan="5">';
								$h .= '<div class="compare-farm">';
									$h .= self::getFarmLogo($eStructureFarm);
									$h .= self::getFarmName($eStructureFarm, $season);
								$h .= '</div>';
							$h .= '</td>';

							$h .= '<td class="text-end">'.self::getFarmYield($yield).'</td>';
							$h .= '<td class="text-end">'.self::getNumber($area, s("m²")).'</td>';

						$h .= '</tr>';

						foreach($cCultivation as $eCultivation) {

							$h .= '<tr>';

								$h .= '<td>'.self::getCultivationName($eStructureFarm, $eCultivation).'</td>';

								$h .= '<td>'.self::getWeek($eCultivation['sowingWeek']).'</td>';
								$h .= '<td>'.self::getWeek($eCultivation['plantingWeek']).'</td>';
								// L'unité sans le mot : la colonne s'appelle DENSITÉ, et « plants »
								// répété soixante fois allonge chaque cellule d'un tiers sans rien
								// ajouter après la première ligne.
								$h .= '<td class="text-end">'.self::getNumber($eCultivation['density'], s("/m²")).'</td>';
								$h .= '<td class="text-end">'.self::getSpacing($eCultivation).'</td>';
								$h .= '<td class="text-end">'.self::getNumber($eCultivation['yieldExpected'], s("kg/m²")).'</td>';
								$h .= '<td class="text-end">'.self::getNumber($eCultivation['area'], s("m²")).'</td>';
							$h .= '</tr>';

						}

					}

				$h .= '</tbody>';

			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le nom d'une ferme, en lien vers son plan de culture sur la saison comparée.
	 *
	 * ⚠️ Même garde que le nom d'une culture : un rôle sans `productionObserve` voit la comparaison
	 * et ne peut pas ouvrir ce qu'elle met en regard. Le nom cesse alors d'être un lien plutôt que
	 * d'en rendre un qui refuse.
	 */
	protected static function getFarmName(\structure\Structure $eStructureFarm, int $season): string {

		$name = encode($eStructureFarm['name']);

		if($eStructureFarm['canReadSeries'] === FALSE) {
			return '<span title="'.s("Votre rôle dans ce groupe ne donne pas accès aux cultures de cette ferme").'">'.$name.'</span>';
		}

		return '<a href="'.\structure\StructureUi::urlCultivationSeries($eStructureFarm, season: $season).'">'.$name.'</a>';

	}

	/**
	 * Le logo d'une ferme, ou la pastille qui en tient la place.
	 *
	 * La forme des fermes d'un groupe et des boutiques : `StructureUi::getLogo()` à 2,5 rem, dont
	 * `media-rectangle-view` porte le `background-size: contain` qui empêche qu'un logo soit rogné.
	 * La pastille est le repli du design system pour une ferme sans logo, au même encombrement.
	 */
	protected static function getFarmLogo(\structure\Structure $eStructureFarm): string {

		return ($eStructureFarm['logo'] !== NULL)
			? \structure\StructureUi::getLogo($eStructureFarm, '2.5rem', 'util-icon-round')
			: '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon('house').'</div>';

	}

	/**
	 * Le nom d'une culture, en lien vers sa série quand le lecteur peut l'ouvrir.
	 *
	 * ⚠️ Le droit se lit sur la ferme et non sur la série : `Series::canRead()` délègue à
	 * `Structure::canRead()`, dont la troisième porte est « cette ferme partage sa production avec
	 * un groupe où je suis membre **et** mon rôle y porte `productionObserve` ». Un rôle où la case
	 * est décochée voit donc la comparaison sans pouvoir ouvrir une série.
	 *
	 * ⚠️ Le nom reste, il cesse seulement d'être un lien, et il porte alors sa raison en `title` :
	 * la forme du dépôt pour un geste refusé est l'entrée de déroulant atténuée avec sa raison en
	 * dessous, et il n'y a pas de menu sur un nom de tableau. Un nom qui n'est pas un lien ne
	 * promet rien ; un lien qui refuse ment.
	 *
	 * ⚠️ Le cas n'a **aucune instance** dans la base : mesuré, zéro adhésion de groupe non
	 * administratrice porte un rôle sans `productionObserve`. Il reste atteignable — un
	 * administrateur peut décocher la case sur un rôle qu'il crée —, d'où la garde, mais rien de
	 * plus visible qu'un `title` tant que personne ne s'y trouve.
	 */
	protected static function getCultivationName(\structure\Structure $eStructureFarm, Cultivation $eCultivation): string {

		$name = encode($eCultivation['series']['name'] ?? '');

		if($eStructureFarm['canReadSeries'] === FALSE) {
			return '<span title="'.s("Votre rôle dans ce groupe ne donne pas accès aux cultures de cette ferme").'">'.$name.'</span>';
		}

		return '<a href="/serie/'.$eCultivation['series']['id'].'">'.$name.'</a>';

	}

	/**
	 * Le total de surface d'une ferme et son rendement moyen, pondéré par la surface.
	 *
	 * ⚠️ **Les deux n'ont pas le même dénominateur, et c'est voulu.** Le total est un vrai total.
	 * La moyenne ne pèse que les cultures qui portent **à la fois** un rendement et une surface :
	 * diviser par la surface totale ferait entrer dans le poids des cultures sans rendement et
	 * tirerait la moyenne vers le bas sans que rien ne le dise. Mesuré sur les 161 290 cultures de
	 * la base, ce n'est pas marginal — 21 % portent une surface sans rendement.
	 *
	 * ⚠️ La surface que la moyenne couvre reste le diviseur et ne sort plus d'ici : la cellule la
	 * disait en atténué — « 1,56 kg/m² sur 231 m² » —, deux chiffres pour une valeur là où la
	 * colonne d'à côté porte déjà le total.
	 */
	protected static function getFarmTotals(\Collection $cCultivation): array {

		$area = 0.0;
		$covered = 0.0;
		$weighted = 0.0;

		foreach($cCultivation as $eCultivation) {

			if($eCultivation['area'] !== NULL) {
				$area += $eCultivation['area'];
			}

			if($eCultivation['area'] !== NULL and $eCultivation['yieldExpected'] !== NULL) {
				$covered += $eCultivation['area'];
				$weighted += $eCultivation['yieldExpected'] * $eCultivation['area'];
			}

		}

		return [
			($area > 0) ? $area : NULL,
			($covered > 0) ? $weighted / $covered : NULL
		];

	}

	protected static function getFarmYield(?float $yield): string {

		if($yield === NULL) {
			return '<span class="color-muted">—</span>';
		}

		// ⚠️ Deux décimales et non le format libre des autres cellules : celles-ci rendent une
		// valeur saisie, celle-ci une division. Sans borne, une moyenne pondérée sort « 6,668 »,
		// trois décimales que rien ne justifie sur un rendement.
		return \util\TextUi::number($yield, 2).' <span class="util-annotation">'.s("kg/m²").'</span>';

	}

	protected static function getWeek(?string $week): string {

		if($week === NULL) {
			return '<span class="color-muted">—</span>';
		}

		[$year, $number] = explode('-W', $week);

		return s("S{value}", (int)$number).' <span class="util-annotation">'.$year.'</span>';

	}

	/**
	 * ⚠️ `precision: NULL` et non le défaut : `'auto'` force deux décimales sur un flottant, et
	 * rendait « 60,00 cm » pour un écartement qui se saisit au centimètre — deux décimales qui
	 * annoncent une précision que la donnée n'a pas. À NULL, le formateur de la locale rend « 60 »,
	 * « 3 » ou « 60,5 » selon la valeur.
	 */
	protected static function getNumber(mixed $value, string $unit): string {

		if($value === NULL) {
			return '<span class="color-muted">—</span>';
		}

		return \util\TextUi::number($value, NULL).' <span class="util-annotation">'.$unit.'</span>';

	}

	/**
	 * L'écartement, qui ne se lit pas de la même façon selon ce que la culture a choisi.
	 */
	protected static function getSpacing(Cultivation $eCultivation): string {

		if($eCultivation['distance'] === Cultivation::DENSITY) {
			return '<span class="color-muted">—</span>';
		}

		$parts = [];

		if($eCultivation['rows'] !== NULL) {
			$parts[] = p("{value} rang", "{value} rangs", $eCultivation['rows']);
		}

		if($eCultivation['plantSpacing'] !== NULL) {
			$parts[] = s("{value} cm", \util\TextUi::number($eCultivation['plantSpacing'], NULL));
		}

		return ($parts === []) ? '<span class="color-muted">—</span>' : implode(' · ', $parts);

	}

}
?>
