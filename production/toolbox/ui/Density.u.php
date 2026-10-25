<?php
namespace toolbox;

/**
 * Le calculateur des densités (arbitrage Q3 A de la planche pf-t3) : les champs à gauche, le
 * résultat à droite dans `util-layout-aside-side`, qui reste en vue pendant qu'on saisit. Le
 * résultat se rend ici et revient du serveur à chaque saisie (`ToolboxDensity.refresh`), pour que
 * les chiffres et le plan soient ceux de la série, arrondis au même endroit.
 */
class DensityUi {

	public function __construct() {

		\Asset::css('toolbox', 'calculator.css');
		\Asset::css('toolbox', 'density.css');
		\Asset::js('toolbox', 'calculator.js');
		\Asset::js('toolbox', 'density.js');

	}

	public static function url(\structure\Structure $eStructure): string {
		return ToolUi::url($eStructure).'/densites';
	}

	/**
	 * La roue ne porte que ce que `ToolUi` donne à tout outil, l'étoile et « Ne plus utiliser cet
	 * outil » : ouvert, le calculateur est un outil de la structure (`DensityLib::open()`), et il n'a
	 * pas d'autre réglage.
	 */
	public function getHeader(\structure\Structure $eStructure, \Collection $cFavorite): string {

		return new ToolUi()->getHeader($eStructure, Favorite::DENSITY, $cFavorite, menu: '');

	}

	/**
	 * L'accueil d'un outil que la structure n'a pas encore ouvert, comme le registre phytosanitaire et
	 * la pluviométrie. Le bouton porte `open`, qui fait du calculateur un outil de la structure.
	 */
	public function getStart(\structure\Structure $eStructure): string {

		$promises = [
			['grid-3x3-gap', s("Le plan de la planche"), s("en perspective et en coupe")],
			['calculator', s("Le nombre de plants"), s("au m², par mètre et sur toute la longueur")],
		];

		$content = '<p>'.s("Saisissez la largeur de vos planches, le nombre de rangs et l'espacement des plantes, et voyez la densité que cela donne en un coup d'oeil.").'</p>';
		$content .= new ToolUi()->getPromises($promises);

		return new \main\DesignUi()->getStart(
			\Asset::icon('grid-3x3-gap'),
			s("Les densités de plantation"),
			$content,
			image: \Asset::image('main', 'friends/ver-rose.svg'),
			buttons: '<a href="'.self::url($eStructure).'?open" class="btn btn-primary btn-xl">'.s("Ouvrir le calculateur").'</a>'
		);

	}

	public function get(\structure\Structure $eStructure, \series\Series $eSeries, \series\Cultivation $eCultivation, array $plan): string {

		$h = $this->getForm($eStructure, $eSeries, $eCultivation);
		$h .= $this->getResult($plan);

		return $h;

	}

	/**
	 * Les champs du panneau de série, sauf la bascule vers la densité au m² : le calculateur part de
	 * l'espacement. Les libellés changent, on mesure une planche, on ne vise pas un objectif.
	 *
	 * Planche pf-t3v2, arbitrée le 28/09 (Q5 B) : dans la carte deux notions côte à côte,
	 * la surface et les plantes, libellés au-dessus des champs (`columns` à 1). La planche ou la
	 * surface libre se choisit en tête de la surface (`getSurface()`) : les onglets que la planche
	 * posait au-dessus de la carte pesaient autant que ceux de l'outil, alors qu'ils ne gouvernent que
	 * la surface (« hiérarchise », le 03/10). Les champs des plantes sont communs aux deux
	 * chemins, seuls les rangs et l'inter-rang s'échangent (`ToolboxDensity.changeUse`).
	 */
	protected function getForm(\structure\Structure $eStructure, \series\Series $eSeries, \series\Cultivation $eCultivation): string {

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->open('density-form', ['oninput' => 'ToolboxDensity.refresh(this)']);

			$h .= $form->hidden('id', $eStructure);

			$h .= '<ol class="util-block util-procedure calculator-form-groups">';

				$h .= '<li class="util-procedure-step">';
					$h .= '<h4 class="util-procedure-title">'.s("La surface").'</h4>';
					$h .= self::getSurface($form, $eSeries, 'ToolboxDensity.changeUse(this)');
				$h .= '</li>';

				$h .= '<li class="util-procedure-step">';
					$h .= '<h4 class="util-procedure-title">'.s("Les plantes").'</h4>';
					$h .= new \sequence\CropUi()->getDistanceField($form, $eCultivation, $eSeries['use'], density: FALSE);
					// L'interrupteur écrit son champ caché sans événement `input` : le formulaire ne le
					// verrait pas changer.
					$h .= $form->dynamicGroup($eCultivation, 'stagger', function(\PropertyDescriber $d) {
						$d->attributes['onchange'] = 'ToolboxDensity.refresh(input.form)';
					});
				$h .= '</li>';

			$h .= '</ol>';

		$h .= $form->close();

		return $h;

	}

	/**
	 * La surface, que le calculateur des besoins en fertilisation reprend sous son propre libellé : la planche ou
	 * la surface libre en deux radios, comme l'utilisation du sol d'une série, puis les champs du
	 * choix. Les deux panneaux restent dans la page, celui qui n'est pas choisi a son `fieldset`
	 * désactivé et ne poste rien (`ToolboxCalculator.changeUse`, que `$onchange` appelle). Le `.tabs`
	 * borne les panneaux : sans lui, ceux de la fertilisation appartiendraient aux onglets de l'outil,
	 * dont `Lime.Tab.select` retirerait leur sélection.
	 */
	public static function getSurface(\util\FormUi $form, \series\Series $eSeries, string $onchange): string {

		$bed = ($eSeries['use'] === \series\Series::BED);

		$h = $form->group(
			content: $form->dynamicField($eSeries, 'use', function(\PropertyDescriber $d) use($onchange) {
				$d->values = [
					\series\Series::BED => s("Sur planches"),
					\series\Series::BLOCK => s("Sur surface libre"),
				];
				$d->attributes['onchange'] = $onchange;
			}),
			attributes: ['data-wrapper' => 'use']
		);

		$h .= '<div class="tabs tabs-exclusive calculator-surface">';
			$h .= '<div class="tab-panel '.($bed ? 'selected' : '').'" data-tab="bed">';
				$h .= '<fieldset '.($bed ? '' : 'disabled').'>';
					$h .= self::getBedFields($form, $eSeries);
				$h .= '</fieldset>';
			$h .= '</div>';
			$h .= '<div class="tab-panel '.($bed ? '' : 'selected').'" data-tab="block">';
				$h .= '<fieldset '.($bed ? 'disabled' : '').'>';
					$h .= $form->dynamicGroup($eSeries, 'areaTarget', function(\PropertyDescriber $d) {
						$d->label = s("Surface");
						unset($d->attributes['oninput']);
					});
				$h .= '</fieldset>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	protected static function getBedFields(\util\FormUi $form, \series\Series $eSeries): string {

		// Les deux largeurs se lisent ensemble, comme l'espacement des plantes (retour du
		// 28/09, « groupe ça comme les espacements ») : la planche et son passe-pied font le format.
		$fields = $form->dynamicField($eSeries, 'bedWidth', function(\PropertyDescriber $d) {
			unset($d->attributes['oninput']);
			$d->append = s("cm travaillés");
		});
		$fields .= '<span>&nbsp;&nbsp;+&nbsp;&nbsp;</span>';
		$fields .= $form->dynamicField($eSeries, 'alleyWidth', function(\PropertyDescriber $d) {
			unset($d->attributes['oninput']);
			$d->append = s("cm de passe-pied");
		});

		$h = $form->group(
			s("Largeur de planche"),
			'<div class="calculator-form-width">'.$fields.'</div>',
			['data-wrapper' => 'bedWidth alleyWidth']
		);

		$h .= $form->dynamicGroup($eSeries, 'lengthTarget', function(\PropertyDescriber $d) {
			unset($d->attributes['oninput']);
			$d->label = s("Longueur de planches");
			$d->append = s("mL");
		});

		return $h;

	}

	/**
	 * Le résultat vient sous la carte, dans une zone teintée (planche pf-t3v2, Q5 B) : le titre et
	 * « Enregistrer le plan », les chiffres, puis le plan sur toute la largeur. Le PNG est dessiné sur
	 * un canvas depuis la géométrie que le bouton porte, jamais depuis la page : il a le même rendu à
	 * toutes les largeurs d'écran. Tant que la saisie ne dessine rien, la zone le dit.
	 */
	public function getResult(array $plan): string {

		$h = '<div id="density-result" class="calculator-result">';

			if($plan === []) {
				$h .= '<div class="util-empty">'.s("Complétez la saisie pour voir la densité et son plan.").'</div>';
			} else {

				$length = \sequence\DensityPlanAiUi::getLength([$plan]);

				$h .= '<div class="util-title">';
					$h .= '<h2>'.match($plan['use']) {
						\series\Series::BED => s("Votre planche"),
						\series\Series::BLOCK => s("Votre surface")
					}.'</h2>';
					$h .= '<div>';
						$h .= '<a class="btn btn-primary" onclick="ToolboxDensity.download(this)" '.attr('data-title', $this->getPngTitle($plan)).' '.attr('data-subtitle', $this->getPngSubtitle($plan)).'>'.\Asset::icon('download').' '.s("Enregistrer le plan").'</a>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= $this->getFigures($plan);
				$h .= $this->getViews($plan, $length);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le Plan en perspective, et pour une planche sa Coupe dessous, dans le même cadre et sans
	 * onglet (retours du 28/09 : « appli la planche dans les deux vues pour tester »,
	 * « Isométrique est renommé Plan », puis « enchaine plan et coupe sans onglet »). Une surface
	 * libre n'a pas de planche à couper.
	 */
	protected function getViews(array $plan, int $length): string {

		$uiDensityView = new \sequence\DensityViewAiUi();

		$h = '<div class="density-result-plan" id="density-view">';

			$h .= $uiDensityView->getIsometric($plan, $length);

			if($plan['use'] === \series\Series::BED) {
				$h .= $uiDensityView->getCut($plan, $length);
			}

		$h .= '</div>';

		return $h;

	}

	protected function getFigures(array $plan): string {

		$figures = [\main\DesignUi::getFigure(\sequence\DensityPlanAiUi::getDensity($plan['density']), s("plants / m²"))];

		switch($plan['use']) {

			case \series\Series::BED :
				$figures[] = \main\DesignUi::getFigure(\sequence\DensityPlanAiUi::getDensity($plan['perMeter']), s("plants par mètre de planche"));
				if($plan['total'] !== NULL) {
					$figures[] = \main\DesignUi::getFigure(\util\TextUi::number($plan['total'], 0), s("plants pour {value} mL", $plan['length']));
				}
				break;

			case \series\Series::BLOCK :
				if($plan['total'] !== NULL) {
					$figures[] = \main\DesignUi::getFigure(\util\TextUi::number($plan['total'], 0), s("plants pour {value} m²", $plan['area']));
				}
				break;

		}

		$figures[] = \main\DesignUi::getFigure(s("{value} cm", \util\TextUi::number($plan['plantSpacing'], NULL)), s("sur le rang"));

		return \main\DesignUi::getFigures($figures);

	}

	/**
	 * Le PNG sort de la page, et doit se lire seul : son titre dit le format et l'espacement, son
	 * sous-titre les deux chiffres qui comptent.
	 */
	protected function getPngTitle(array $plan): string {

		$parts = [\sequence\DensityPlanAiUi::getTitle($plan)];

		if($plan['use'] === \series\Series::BED) {
			$parts[] = p("{value} rang", "{value} rangs", $plan['rows']);
			$parts[] = s("plants à {value} cm", \util\TextUi::number($plan['plantSpacing'], NULL));
		} else {
			$parts[] = s("rangs à {value} cm", \util\TextUi::number($plan['rowSpacing'], NULL));
			$parts[] = s("plants à {value} cm", \util\TextUi::number($plan['plantSpacing'], NULL));
		}

		if($plan['stagger']) {
			$parts[] = s("en quinconce");
		}

		return implode(', ', $parts);

	}

	protected function getPngSubtitle(array $plan): string {

		return s("{density} plants / m², {spacing} cm sur le rang", [
			'density' => \sequence\DensityPlanAiUi::getDensity($plan['density']),
			'spacing' => \util\TextUi::number($plan['plantSpacing'], NULL),
		]);

	}

}
?>
