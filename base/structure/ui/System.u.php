<?php
namespace structure;

class SystemUi {

	public function __construct() {

		\Asset::css('structure', 'system.css');
		\Asset::js('structure', 'system.js');

	}

	private const SELLING = [
		'sellingDirect' => 'direct',
		'sellingSemi' => 'semi',
		'sellingLong' => 'long',
	];

	/**
	 * `$inviteKey`, la clé de l'invitation d'AMAP qui a fait créer la structure : les ateliers la
	 * transmettent à la page d'arrivée (`StructureUi::urlStart()`).
	 */
	public static function urlUpdateProductions(System $eSystem, ?string $inviteKey = NULL): string {
		return '/structure/system:updateProductions?id='.$eSystem['id'].(($inviteKey !== NULL) ? '&inviteKey='.urlencode($inviteKey) : '');
	}

	public function getStart(System $eSystem) {

		$start = '<p>';
			$start .= s("Renseignez les ateliers de votre ferme pour mieux profiter des fonctionnalités à venir sur {siteName} !<br/>À venir d'ici la fin de l'année, de nouvelles fonctionnalités et la possibilité de créer des groupes de producteurs !");
		$start .= '</p>';

		return new \main\DesignUi()->getStart(
			\Asset::icon('leaf'),
			s("Les ateliers"),
			$start,
			buttons: '<a href="/structure/system:update?id='.$eSystem['id'].'" class="btn btn-xl btn-outline-primary">'.s("Démarrer").'</a>'
		);

	}

	public function display(Structure $eStructure, System $eSystem, \Collection $cProduction): string {

		if(
			FEATURE_SYSTEM_PROMO and
			$cProduction->empty()
		) {
			return $this->getStart($eSystem);
		}

		$h = '<div class="util-block">';

			if($cProduction->notEmpty()) {

				$h .= '<div class="util-title">';
					$h .= '<h4>';
						if($cProduction->count() > 1) {
							$h .= p("Atelier", "Ateliers", $cProduction->count());
						} else {
							$h .= s("Atelier");
						}
					$h .= '</h4>';
					$h .= '<a href="/structure/system:update?id='.$eSystem['id'].'" class="btn btn-outline-primary">'.\Asset::icon('gear-fill').' '.s("Modifier").'</a>';
				$h .= '</div>';


				$h .= '<dl class="system-productions">';

					foreach($cProduction as $eProduction) {

						$h .= '<dt>';
							$h .= ProductionUi::get($eProduction['fqn']);
						$h .= '</dt>';

						$h .= '<dd>';
							$h .= new ProductionUi()->getDetails($eProduction, $eStructure);
						$h .= '</dd>';

					}

				$h .= '</dl>';

			}
			
			$list = '';

			if($eSystem['place'] !== NULL) {
				$list .= '<dt>'.s("Lieu de production").'</dt>';
				$list .= '<dd>';
					$list .= encode($eSystem['place']);
					if($eSystem['placeDepartment'] !== NULL) {
						$list .= '<div class="util-presentation-detail font-sm">'.\main\FranceUi::getDepartmentName($eSystem['placeDepartment']).' ('.$eSystem['placeDepartment'].')</div>';
					}
				$list .= '</dd>';
			}

			if($eSystem['altitude']) {
				$list .= '<dt>'.s("Altitude").'</dt>';
				$list .= '<dd>'.s("{value} m", encode($eSystem['altitude'])).'</dd>';
			}

			if($eSystem['totalArea']) {
				$list .= '<dt>'.s("Surface agricole utile").'</dt>';
				$list .= '<dd>'.s("{value} ha", encode($eSystem['totalArea'])).'</dd>';
			}

			if($eSystem['climate']) {
				$list .= '<dt>'.s("Climat").'</dt>';
				$list .= '<dd>'.self::p('climate')->values[$eSystem['climate']].'</dd>';
			}

			if($eSystem['soil'] or $eSystem['ph']) {

				$list .= '<dt>'.s("Sol").'</dt>';
				$list .= '<dd>';

					$values = [];

					if($eSystem['soil']) {
						$values[] = self::p('soil')->values[$eSystem['soil']];
					}

					if($eSystem['ph']) {
						$values[] = s("pH {value}", $eSystem['ph']);
					}

					$list .= implode(' &middot; ', $values);

				$list .= '</dd>';

			}

			if($eSystem['sellingDirect'] or $eSystem['sellingSemi'] or $eSystem['sellingLong']) {

				$list .= '<dt>'.s("Commercialisation").'</dt>';
				$list .= '<dd>';

					$values = [];

					foreach(array_keys(self::SELLING) as $property) {

						if($eSystem[$property]) {
							$values[] = \main\DesignUi::getSplitBadge(self::p($property)->label, s("{value} %", $eSystem[$property]), 'settings');
						}

					}

					$list .= implode(' &middot; ', $values);

				$list .= '</dd>';

			}

			if($eSystem['hrFarmer'] or $eSystem['hrEmployee'] or $eSystem['hrOther']) {

				$list .= '<dt>'.s("Main d'oeuvre").'</dt>';
				$list .= '<dd>';

					$values = [];

					if($eSystem['hrFarmer']) {
						$values[] = \main\DesignUi::getSplitBadge(s("Exploitant"), $eSystem['hrFarmer'], 'settings').'</span>';
					}

					if($eSystem['hrEmployee']) {
						$values[] = \main\DesignUi::getSplitBadge(s("Salarié"), $eSystem['hrEmployee'], 'settings').'</span>';
					}

					if($eSystem['hrOther']) {
						$values[] = \main\DesignUi::getSplitBadge(s("Stagiaire / Bénévole"), $eSystem['hrOther'], 'settings').'</span>';
					}

					$list .= implode(' &middot; ', $values);

				$list .= '</dd>';

			}

			if($list) {

				$h .= '<hr/>';

				$h .= '<h4>';
					$h .= s("Système");
				$h .= '</h4>';
				$h .= '<dl class="util-presentation util-presentation-2">';
					$h .= $list;
				$h .= '</dl>';

			}

		$h .= '</div>';


		return $h;

	}

	public function updateProductions(System $eSystem): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/system:doUpdateProductions', ['class' => 'system-update-form']);

			$h .= $form->hidden('id', $eSystem);

			if($eSystem['inviteKey'] !== NULL) {
				$h .= $form->hidden('inviteKey', $eSystem['inviteKey']);
			}

			$h .= '<div class="util-info">';
				$h .= s("Votre structure a bien été créée sur {siteName} !<br/>Donnez quelques informations sur vos ateliers de production afin que nous puissions vous proposer des fonctionnalités adaptées à votre activité.");
			$h .= '</div>';

			$h .= new ProductionUi()->choose($form, $eSystem['structure'], $eSystem['cProduction'], FALSE);

			$h .= $form->group(
				content: $form->submit(s("Terminer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-structure-create',
			title: s("Renseigner les ateliers de la ferme"),
			body: $h
		);

	}

	public function update(System $eSystem): string {
		return $this->getUpdateForm($eSystem, '/structure/system:doUpdate', s("Enregistrer"), workforce: TRUE, collapse: FALSE);
	}

	/**
	 * Le même formulaire, posé dans le panneau de partage d'une ferme à un groupe.
	 *
	 * Sans la main d'oeuvre : le temps de travail ne s'affiche jamais sur la fiche publique,
	 * et un écran de consentement ne réclame pas ce qu'il s'engage à ne pas montrer.
	 */
	public function updateShare(System $eSystem, string $skip): string {
		return $this->getUpdateForm($eSystem, '/structure/system:doUpdateShare', s("Enregistrer et continuer"), workforce: FALSE, collapse: TRUE, skip: $skip);
	}

	protected function getUpdateForm(System $eSystem, string $action, string $submit, bool $workforce, bool $collapse, string $skip = ''): string {

		$form = new \util\FormUi();

		$h = $form->openAjax($action, ['class' => 'system-update-form']);

			$h .= $form->hidden('id', $eSystem);

			$h .= $form->group(
				content: '<h3>'.s("Ateliers").'</h3>'
			);

			$h .= new ProductionUi()->choose($form, $eSystem['structure'], $eSystem['cProduction'], TRUE, $collapse);

			$h .= '<br/>';

			$h .= $form->group(
				content: '<h3>'.s("Système de production").'</h3>'
			);

			$h .= $form->dynamicGroups($eSystem, ['place', 'placeLngLat']);

			// L'altitude se déduit des coordonnées et ne se saisit pas : sans lieu il n'y a rien à
			// montrer. Le climat, lui, ne dépend ni du lieu connu ni du métier exercé.
			$h .= '<div class="place-located'.($eSystem['placeLngLat'] ? '' : ' hide').'">';
				$h .= $form->dynamicGroups($eSystem, ['altitude']);
			$h .= '</div>';

			$h .= $form->dynamicGroups($eSystem, ['climate']);

			$h .= '<div data-for-global="'.implode(' ', Production::getOptions('systemFarming')).'">';
				$h .= $form->group(
					s("Informations sur le système"),
					$form->dynamicFieldsets($eSystem, ['totalArea', 'soil', 'ph'])

				);
			$h .= '</div>';

			// La pastille de couleur n'est ajoutée qu'ici : elle relie le champ à son segment de
			// la barre de répartition, qui n'existe que dans ce formulaire.
			$sellingLegends = [];

			foreach(array_keys(self::SELLING) as $property) {
				$sellingLegends[$property] = fn(\PropertyDescriber $d) => $d->label = self::getSellingLegend($property);
			}

			$h .= $form->group(
				s("Commercialisation"),
				$form->dynamicFieldsets($eSystem, array_keys(self::SELLING), $sellingLegends).
				$this->getSellingRepartition($eSystem)
			);

			if($workforce) {
				$h .= $form->group(
					s("Main d'oeuvre disponible"),
					$form->dynamicFieldsets($eSystem, ['hrFarmer', 'hrEmployee', 'hrOther'])

				);
			}

			if($skip === '') {

				$h .= $form->group(
					content: $form->submit($submit)
				);

			} else {

				$h .= '<div class="onboarding-footer">';
					$h .= '<div class="onboarding-footer-side">'.$skip.'</div>';
					$h .= '<div class="onboarding-footer-main">'.$form->submit($submit, ['class' => 'btn btn-primary']).'</div>';
				$h .= '</div>';

			}

		$h .= $form->close();

		return $h;

	}

	/**
	 * Barre de répartition des ventes, ajustable à la souris et synchronisée avec les trois champs
	 */
	public function getSellingRepartition(System $eSystem): string {

		$values = [];

		foreach(self::SELLING as $property => $mode) {
			$values[$mode] = (int)$eSystem[$property];
		}

		$unset = (array_sum($values) === 0);

		$h = '<div class="system-selling '.($unset ? 'system-selling-unset' : '').'" onrender="StructureSystem.init(this)">';

			$h .= '<div class="system-selling-bar">';

				$h .= '<div class="system-selling-parts">';

					$offset = 0;

					foreach($values as $mode => $value) {
						$h .= '<div class="system-selling-part system-selling-'.$mode.'" data-selling="'.$mode.'" style="left: '.$offset.'%; width: '.$value.'%">'.($value >= 10 ? $value.'&nbsp;%' : '').'</div>';
						$offset += $value;
					}

				$h .= '</div>';

				$h .= '<div class="system-selling-handle" data-selling-handle="0" style="left: '.$values['direct'].'%"></div>';
				$h .= '<div class="system-selling-handle" data-selling-handle="1" style="left: '.($values['direct'] + $values['semi']).'%"></div>';

			$h .= '</div>';

			$h .= '<div class="system-selling-footer">';

				$h .= '<a class="system-selling-reset" data-action="selling-reset">'.s("Effacer la répartition").'</a>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function updatePlace(System $eSystem): \Panel {

		$eSystem->expects([
			'structure' => ['legalCountry']
		]);

		$eStructure = $eSystem['structure'];

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/system:doUpdatePlace');

			$h .= $form->hidden('id', $eSystem);

			$h .= $form->group(
				s("Pays"),
				content: '<b>'.\user\Country::ask($eStructure['legalCountry'])['name'].'</b>'
			);
			$h .= $form->dynamicGroups($eSystem, ['place', 'placeLngLat']);

			$h .= '<div class="place-located '.($eSystem['placeLngLat'] ? '' : 'hide').'">';
				$h .= $form->dynamicGroup($eSystem, 'altitude');
			$h .= '</div>';

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-structure-place',
			title: s("Lieu de production"),
			body: $h
		);

	}

	/**
	 * Le point de couleur relie le champ à son segment dans la barre de répartition.
	 *
	 * Posé sur le libellé du champ au moment de le rendre, et non dans le describer : celui-ci
	 * est la source du libellé pour tout le monde, y compris la fiche de lecture, où une
	 * pastille aux couleurs de la barre n'aurait rien à faire.
	 */
	private static function getSellingLegend(string $property): string {

		return '<span class="system-selling-dot system-selling-'.self::SELLING[$property].'"></span>'.self::p($property)->label;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = System::model()->describer($property, [
			'place' => s("Lieu de production"),
			'totalArea' => s("Surface agricole utile <i>(SAU)</i>"),
			'altitude' => s("Altitude de la production"),
			'climate' => s("Contexte climatique"),
			'soil' => s("Texture du sol"),
			'ph' => s("pH"),
			'hrFarmer' => s("Exploitant"),
			'hrEmployee' => s("Salarié"),
			'hrOther' => s("Stagiaire / Bénévole"),
			'sellingDirect' => s("Vente directe"),
			'sellingSemi' => s("Vente demi-gros"),
			'sellingLong' => s("Circuits longs"),
		]);

		switch($property) {

			case 'place' :
				$d->autocompleteBody = function(\util\FormUi $form, System $e) {
					return [
						'structure' => $e['structure']['id']
					];
				};
				new \main\PlaceUi()->query($d, draggable: TRUE);
				break;

			case 'placeLngLat' :
				$d->field = NULL;
				break;

			case 'totalArea' :
				$d->append = s("ha");
				break;

			case 'altitude' :
				$d->append = s("m");
				break;

			case 'hrFarmer' :
			case 'hrEmployee' :
			case 'hrOther' :
				$d->append = '<div class="text-start font-xs" style="line-height: 1">'.s("temps<br/>plein").'</div>';
				break;

			case 'sellingDirect' :
			case 'sellingSemi' :
			case 'sellingLong' :
				$d->append = s("%");
				break;

			case 'climate' :
				$d->field = 'select';
				$d->values = [
					System::OCEANIC => s("Océanique"),
					System::CONTINENTAL => s("Continental"),
					System::MEDITERRANEAN => s("Méditerranéen"),
					System::MOUNTAIN => s("Montagnard"),
				];
				break;

			case 'soil' :
				$d->field = 'select';
				$d->values = [
					System::SABLEUX => 'Sable',
					System::LIMONEUX => 'Limon',
					System::ARGILEUX => 'Argile',
					['label' => s("Autres textures"), 'attributes' => ['disabled']],
					System::SABLO_LIMONEUX => 'Sable limoneux',
					System::LIMONO_SABLEUX => 'Limon sableux',
					System::SABLO_ARGILEUX => 'Sable argileux',
					System::SABLO_ARGILO_LIMONEUX => 'Sable argilo-limoneux',
					System::ARGILO_SABLEUX => 'Argile sableuse',
					System::LIMONO_ARGILEUX => 'Limon argileux',
					System::LIMONO_ARGILO_SABLEUX => 'Limon argilo-sableux',
					System::ARGILO_LIMONEUX => 'Argile limoneuse',
				];
				break;

		}

		return $d;

	}

}
