<?php
namespace structure;

/**
 * Ce que `structure` montre sur l'accueil.
 *
 * La météo est dans le groupe Bureau du menu (L52) : le moment, puis une tuile par jour qui vient (L81).
 * « À essayer » reprend les cartes de la page d'arrivée (L12, piste A).
 */
class DashboardUi {

	public function __construct() {
		\Asset::css('structure', 'weatherAi.css');
	}

	public function getTitle(string $name, ?array $values = NULL): string {

		return match($name) {
			'structure-weather' => s("Météo"),
			'structure-start' => s("À essayer")
		};

	}

	public function getUrlLabel(string $name): string {

		return match($name) {
			'structure-weather' => s("Open-Meteo.com"),
			'structure-start' => s("Tout ce que vous pouvez démarrer")
		};

	}

	public function render(string $name, Structure $eStructure, array $values): string {

		return match($name) {
			'structure-weather' => $this->getWeather($eStructure, $values),
			'structure-start' => $this->getStart($eStructure, $values)
		};

	}

	/**
	 * Les cartes du catalogue de la page d'arrivée, dans son ordre, réduites à ce que la ferme n'a pas
	 * encore essayé. Le catalogue n'est pas recopié : une entrée qui y change change ici aussi.
	 *
	 * Le premier pas sort des cartes et passe devant, en bande de mise en route dans l'univers de son
	 * entrée, que la feuille lit pour le trait et le bouton (planche L67, B7, arbitrée par V-Dashboard
	 * le 19/10/2026).
	 *
	 * `util-cards-2` aligne les hauteurs des cartes d'une même rangée, que des textes de longueurs
	 * différentes laissaient inégales sur la planche.
	 */
	private function getStart(Structure $eStructure, array $values): string {

		$band = '';
		$cards = '';

		foreach(new StructureUi()->getStartedFeatures($eStructure) as $feature) {

			if(($feature['display'] ?? TRUE) === FALSE or in_array($feature['name'], $values['remaining'], TRUE) === FALSE) {
				continue;
			}

			if($feature['name'] === $values['step']) {
				$band = '<div class="util-domain-'.$feature['domain'].'">';
					$band .= new \main\DesignUi()->getStart(
						\Asset::icon($feature['icon']),
						$feature['title'],
						'<p>'.$feature['text'].'</p>',
						buttons: '<a href="'.($feature['stepUrl'] ?? $feature['url']).'" class="btn btn-primary">'.$feature['button'].'</a>'
					);
				$band .= '</div>';
			} else {
				$cards .= \main\DesignUi::getDeckEntry($feature['url'], $feature['icon'], $feature['domain'], $feature['title'], $feature['text']);
			}

		}

		return $band.(($cards === '') ? '' : '<div class="util-cards util-cards-2">'.$cards.'</div>');

	}

	/**
	 * Le moment, avec le minimum et le maximum du jour au bout de sa ligne, la phrase du ciel et la
	 * marque du modèle dessous, puis une tuile par jour qui vient (planche L81, piste A). Les tuiles sont
	 * côte à côte et de même largeur, pour que les jours se comparent d'un coup d'œil.
	 */
	private function getWeather(Structure $eStructure, array $values): string {

		$now = $values['now'];
		[$icon, $word] = $this->getSky($now['code'], $now['day']);
		$rain = $this->getRain($values['days'][0]);

		$h = '<div class="structure-weather">';

			$h .= '<div class="structure-weather-now">';
				$h .= \Asset::icon($icon);
				$h .= '<div class="structure-weather-temperature">'.s("{value}°", (int)round($now['temperature'])).'</div>';
				$h .= '<div class="structure-weather-today">';
					$h .= $this->getRange($values['days'][0]);
					// Sans pluie, pas de cellule : la colonne en garderait l'écart sous les températures.
					if($rain !== '') {
						$h .= '<span class="structure-weather-rain">'.$rain.'</span>';
					}
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="structure-weather-sky">';
				$h .= '<span class="util-annotation">';
					$h .= $this->getSentence($values['place'], $word, $now);
				$h .= '</span>';
				$h .= $this->getModel($values);
			$h .= '</div>';

			$h .= $this->getForecast($eStructure, $values);

			// Le visiteur n'a pas de menu où la lire (`getModel()`).
			if($values['member'] === NULL) {
				$h .= $this->getSource('structure-weather-source');
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La phrase du moment. La rafale suit le vent quand le modèle la donne pour l'heure (planche L96,
	 * piste R2).
	 */
	private function getSentence(?string $place, string $word, array $now): string {

		$arguments = [
			'place' => ($place === NULL) ? NULL : encode($place),
			'sky' => ($place === NULL) ? $word : mb_strtolower($word),
			'wind' => (int)round($now['wind']),
			'gust' => ($now['gust'] === NULL) ? NULL : (int)round($now['gust'])
		];

		if($place === NULL) {
			return ($now['gust'] === NULL) ?
				s("{sky}, vent {wind} km/h", $arguments) :
				s("{sky}, vent {wind} km/h, rafales {gust} km/h", $arguments);
		} else {
			return ($now['gust'] === NULL) ?
				s("{place}, {sky}, vent {wind} km/h", $arguments) :
				s("{place}, {sky}, vent {wind} km/h, rafales {gust} km/h", $arguments);
		}

	}

	/**
	 * Les tuiles des jours qui viennent, puis, sous elles, le détail du jour ouvert (planche L96, piste
	 * C3). Une tuile ouvre son jour, celle qui est ouverte le referme : son lien ne porte pas de date.
	 * La route rend toute la rangée, tuiles comprises, pour que le jour cerné et le détail montré ne
	 * puissent pas se contredire quand deux clics se suivent (`view/structure.v.php`).
	 *
	 * @param array|null $day le `day` que rend `WeatherAiLib::getByStructure()`, `NULL` quand aucun jour n'est ouvert
	 */
	public function getForecast(Structure $eStructure, array $values, ?array $day = NULL): string {

		$h = '<div class="structure-weather-forecast">';

			$h .= '<div class="structure-weather-days">';

				foreach(array_slice($values['days'], 1, WeatherAiLib::DETAIL_DAYS) as $tile) {

					$selected = ($day !== NULL and $day['date'] === $tile['date']);
					$url = '/structure/structure:weatherDay?id='.$eStructure['id'].($selected ? '' : '&date='.$tile['date']);

					// Le minimum se lit sous le maximum, sans flèche : dans une tuile, leur place suffit à
					// les distinguer (planche L81, piste A).
					$h .= '<a data-ajax="'.$url.'" data-ajax-method="get" class="structure-weather-day'.($selected ? ' selected' : '').'">';
						$h .= '<div class="structure-weather-day-name">'.\util\DateUi::getDayNameShort((int)date('N', strtotime($tile['date']))).'</div>';
						$h .= \Asset::icon($this->getSky($tile['code'])[0]);
						$h .= '<div class="structure-weather-max">'.s("{value}°", (int)round($tile['max'])).'</div>';
						$h .= '<div class="structure-weather-min">'.s("{value}°", (int)round($tile['min'])).'</div>';
						$h .= '<div class="structure-weather-rain">'.$this->getRain($tile, '<br/>').'</div>';
						if($tile['gust'] !== NULL) {
							$h .= '<div class="structure-weather-gust">'.\Asset::icon('wind').'<span class="util-unit">'.s("{value} km/h", (int)round($tile['gust'])).'</span></div>';
						}
					$h .= '</a>';

				}

			$h .= '</div>';

			if($day !== NULL) {
				$h .= $this->getDay($day);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un moment par diapositive, six heures chacune, en colonnes : l'heure, le ciel, la température et
	 * la rafale, que l'icône du vent nomme en tête de sa ligne. Deux chevrons mènent d'un moment à
	 * l'autre, le doigt fait aussi glisser la piste. Le matin se montre d'abord, ou le premier moment
	 * que le modèle couvre.
	 */
	private function getDay(array $day): string {

		$current = array_key_exists(WeatherAiLib::MOMENT_FIRST, $day['moments']) ? WeatherAiLib::MOMENT_FIRST : array_key_first($day['moments']);

		\Asset::js('main', 'carouselAi.js');

		$h = '<div class="util-carousel structure-weather-hours" onrender="MainCarousel.start(this)">';

			$h .= '<div class="util-carousel-nav">';
				$h .= '<button type="button" class="btn btn-only-icon btn-xs btn-outline-secondary" data-carousel-step="-1">'.\Asset::icon('chevron-left').'</button>';
				$h .= '<span class="util-carousel-label">'.$this->getMoment($current).'</span>';
				$h .= '<button type="button" class="btn btn-only-icon btn-xs btn-outline-secondary" data-carousel-step="1">'.\Asset::icon('chevron-right').'</button>';
			$h .= '</div>';

			$h .= '<div class="util-carousel-track">';

				foreach($day['moments'] as $start => $hours) {

					$h .= '<div class="util-carousel-slide structure-weather-moment" data-carousel-label="'.encode($this->getMoment($start)).'"'.(($start === $current) ? ' data-carousel-current' : '').'>';

						$h .= '<span class="structure-weather-moment-wind">'.\Asset::icon('wind').'</span>';

						foreach($hours as $hour) {
							$h .= '<span>'.s("{value} h", $hour['hour']).'</span>';
							$h .= \Asset::icon($this->getSky($hour['code'], $hour['day'])[0]);
							$h .= '<span class="structure-weather-moment-temperature">'.s("{value}°", (int)round($hour['temperature'])).'</span>';
							$h .= '<span class="structure-weather-moment-gust">'.(($hour['gust'] === NULL) ? '' : (int)round($hour['gust'])).'</span>';
						}

					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	private function getMoment(int $start): string {

		return match($start) {
			0 => s("Nuit"),
			6 => s("Matin"),
			12 => s("Après-midi"),
			18 => s("Soir")
		};

	}

	/**
	 * La source des prévisions, que leur licence demande de citer là où elles paraissent. Toute la ligne
	 * fait lien : un lien posé dans la phrase prendrait la couleur des liens, illisible sur le fond du menu.
	 */
	private function getSource(string $class): string {
		return '<a href="'.WeatherAiLib::SOURCE_URL.'" target="_blank" class="'.$class.'">'.s("Données météo fournies par {source}", ['source' => $this->getUrlLabel('structure-weather')]).'</a>';
	}

	/**
	 * Le maximum puis le minimum, dans l'ordre des jours dessous (L92), aux classes des tuiles, donc au
	 * même poids et le minimum en bleu (planche L85, piste A). Chacun se reconnaît à sa flèche plutôt
	 * qu'à une légende.
	 */
	private function getRange(array $day): string {

		$h = '<span class="structure-weather-range">';
			$h .= '<span class="structure-weather-max">'.\Asset::icon('arrow-up').s("{value}°", (int)round($day['max'])).'</span>';
			$h .= '<span class="structure-weather-min">'.\Asset::icon('arrow-down').s("{value}°", (int)round($day['min'])).'</span>';
		$h .= '</span>';

		return $h;

	}

	/**
	 * Le risque de pluie quand le modèle le donne (Météo-France ne le donne jamais), puis les millimètres
	 * quand il en tombe assez pour qu'ils se lisent. La goutte précède le pourcentage, qui sans elle ne
	 * dit pas de quoi il est la probabilité (remarque sur la planche L52) ; les millimètres
	 * suivent derrière elle, sous la même goutte, sur la même ligne ou dessous selon `$separator`. Un
	 * jour sec d'un modèle sans risque n'a rien.
	 */
	private function getRain(array $day, string $separator = ' · '): string {

		$details = [];

		if($day['rain'] !== NULL) {
			$details[] = '<span class="util-unit">'.s("{value} %", $day['rain']).'</span>';
		}

		if($day['mm'] !== NULL) {
			$details[] = '<span class="util-unit">'.s("{value} mm", \util\TextUi::number($day['mm'], 1)).'</span>';
		}

		if($details === []) {
			return '';
		}

		return \Asset::icon('droplet').implode($separator, $details);

	}

	/**
	 * Le modèle montré, en marque qu'on ouvre pour en choisir un autre (« Un état qu'on peut changer »
	 * sur la page du design system). La marque nomme le modèle lu et le menu coche celui que le membre a
	 * choisi : les deux diffèrent quand le choix ne couvre pas l'heure et que le bloc s'est replié sur
	 * ICON. Un membre de l'équipe en visite n'a pas de ligne où écrire son choix, sa marque ne s'ouvre
	 * pas, et la source se lit sous les jours.
	 *
	 * Le menu se clôt sur la source des prévisions (L82), en texte et non en entrée, pour ne pas se lire
	 * comme un modèle de plus.
	 *
	 * Le choix ne renvoie que la météo du menu (`view/member.v.php`).
	 */
	private function getModel(array $values): string {

		$labels = MemberUi::p('viewWeatherModel')->values;
		$label = $labels[$values['model']];

		if($values['member'] === NULL) {
			return '<span class="btn btn-badge btn-xs btn-readonly badge badge-neutral">'.$label.'</span>';
		}

		$h = '<a class="btn btn-badge btn-xs badge badge-neutral dropdown-toggle" data-dropdown="bottom-end">'.$label.'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.s("Modèle de prévision").'</div>';

			foreach($labels as $model => $modelLabel) {

				$h .= '<a data-ajax="/structure/member:doUpdateWeatherModel" post-id="'.$values['member'].'" post-view-weather-model="'.$model.'" class="dropdown-item'.($model === $values['chosen'] ? ' selected' : '').'">';
					$h .= $modelLabel;
					if($model === Member::METEOFRANCE) {
						$h .= '<div class="util-annotation">'.s("4 jours, sans probabilité de pluie").'</div>';
					}
				$h .= '</a>';

			}

			$h .= '<div class="dropdown-divider"></div>';
			$h .= $this->getSource('dropdown-text dropdown-info');

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le pictogramme et le mot d'un code WMO, la table qu'Open-Meteo documente. Le mot dit le ciel en
	 * une fois, comme la planche (« Couvert »), et ne cherche pas à rendre les nuances d'intensité que
	 * la température et la pluie disent déjà.
	 *
	 * ⚠️ Un `default`, contre la règle des `match` sur un enum : la table n'est pas un enum du dépôt, et
	 * un code que la source ajouterait ferait lever le rendu, donc toute la page d'accueil, le bloc étant
	 * léger. Il rend le ciel couvert, sans mot plus précis.
	 *
	 * La nuit, le soleil laisse la place à la lune, et un ciel sans nuage se dit « Dégagé ». Une tuile
	 * parle d'un jour entier, et se dessine de jour.
	 */
	private function getSky(int $code, bool $day = TRUE): array {

		return match($code) {
			0, 1 => $day ? ['sun', s("Ensoleillé")] : ['moon', s("Dégagé")],
			2 => [$day ? 'cloud-sun' : 'cloud-moon', s("Éclaircies")],
			3 => ['cloud', s("Couvert")],
			45, 48 => ['cloud-fog', s("Brouillard")],
			51, 53, 55, 56, 57 => ['cloud-drizzle', s("Bruine")],
			61, 63, 66 => ['cloud-rain', s("Pluie")],
			65, 67 => ['cloud-rain-heavy', s("Forte pluie")],
			71, 73, 75, 77 => ['cloud-snow', s("Neige")],
			80, 81, 82 => ['cloud-drizzle', s("Averses")],
			85, 86 => ['cloud-snow', s("Averses de neige")],
			95 => ['cloud-lightning-rain', s("Orage")],
			96, 99 => ['cloud-hail', s("Orage et grêle")],
			default => ['cloud', s("Couvert")]
		};

	}

}
?>
