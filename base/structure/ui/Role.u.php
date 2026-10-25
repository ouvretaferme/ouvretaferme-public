<?php
namespace structure;

class RoleUi {

	public function __construct() {

		\Asset::css('structure', 'role.css');
		\Asset::js('main', 'stickyColumnAi.js');

	}

	public function getManage(Structure $eStructure, \Collection $cRole): string {

		\Asset::css('util', 'form.css');

		$h = '<div class="util-block-info">';
			$h .= \Asset::icon('info-circle', ['class' => 'util-block-icon']);
			$h .= '<div class="util-block-info-text">';
				$h .= '<div class="util-block-info-title">'.s("Décidez de ce que chacun peut faire").'</div>';
				$h .= '<div class="util-block-info-help">';
					$h .= '<p>'.s("Vous pouvez attribuer aux membres de votre équipe des rôles pour déterminer ce que chacun peut faire et ne pas faire sur votre structure. Le rôle <b>Administrateur</b> permet d'accéder à toutes les fonctionnalités et ne peut pas être modifié, tandis que les autres rôles sont personnalisables.").'</p>';
					$h .= '<p>'.s("Les manipulations les plus critiques ne peuvent pas être déléguées aux membres de votre équipe qui ne sont pas administrateurs, et notamment l'accès au paramétrage, l'édition de votre comptabilité, la facturation et la communication. Cette liste n'est pas exhaustive.").'</p>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-title mt-2">';
			$h .= '<h2>'.s("Les rôles et leurs accès").'</h2>';
			$h .= '<a href="/structure/role:create?structure='.$eStructure['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter un rôle").'</a>';
		$h .= '</div>';

		if($cRole->empty()) {

			$h .= '<div class="util-empty">'.s("Vous n'avez pas défini de rôle dans votre ferme.").'</div>';
			return $h;

		}

		$h .= '<div class="role-list-overflow stick-xs">';
			$h .= '<table class="tr-even td-sticky-first" style="min-width: calc(24rem + 8rem * '.$cRole->count().')">';

				$h .= '<thead class="role-list-header">';

					$h .= '<th>';
					$h .= '</th>';

					$position = 0;

					foreach($cRole as $eRole) {

						$h .= '<td class="text-center '.($position % 2 ? 't-highlight' : '').'">';
							$h .= '<h3>'.encode($eRole['name']).'</h3>';
							if($eRole->acceptUpdate()) {
								$h .= '<a href="/structure/role:update?id='.$eRole['id'].'" class="btn btn-outline-primary btn-sm mb-1">'.s("Modifier").'</a>';
							}
						$h .= '</td>';

					}

				$h .= '</thead>';

				$eRoleFirst = $cRole->first();

				foreach($this->getProperties() as [$title, $properties]) {

					$h .= '<tbody>';
						$h .= '<tr class="tr-title">';

							$h .= '<td>'.$title.'</td>';
							$h .= '<td colspan="'.$cRole->count().'"></td>';

						$h .= '</tr>';
					$h .= '</tbody>';
					$h .= '<tbody>';

						foreach($properties as $property) {

							$h .= '<tr>';

								$h .= '<td>';
									$h .= self::p($property)->label;
									$labelAfter = self::p($property)->labelAfter;
									$h .= is_closure($labelAfter) ? $labelAfter($eRoleFirst) : $labelAfter;
								$h .= '</td>';

								$position = 0;

								foreach($cRole as $eRole) {

									$h .= '<td class="text-center '.($position++ % 2 ? 't-highlight' : '').'">';
										$h .= self::getValue($eRole[$property]);
									$h .= '</td>';

								}

							$h .= '</tr>';

						}

					$h .= '</tbody>';

				}

			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * ⚠️ `sections` n'y figure pas, et n'a pas à y figurer : `Structure::acceptRoles()` rend
	 * `isProducer()`, donc cet écran n'existe que pour une ferme, et une ferme n'a pas de
	 * sous-groupes.
	 *
	 * La portée d'un rôle de groupe se fixe à la création du groupe (`RoleLib::createForStructure()`)
	 * et ne se change pas : arbitrage rendu le 2026-09-06, l'écran des rôles n'est pas ouvert aux
	 * groupes. L'ajouter ici demanderait donc d'ouvrir cet écran d'abord.
	 */
	public function getProperties(): array {
		return [
			[
				s("Production"),
				['planning', 'productionObserve', 'productionAnalyze']
			],
			[
				s("Commercialisation"),
				['selling', 'commercialisationAnalyze']
			],
			[
				s("Gestion"),
				['accounting', 'accountingAnalyze']
			],
		];
	}

	protected static function getValue(mixed $value): string {
		return match($value) {

			Role::LIMITED => '<span class="color-warning">'.\Asset::icon('exclamation-circle').' '.s("Restreint").'</span>',
			Role::FULL => '<span class="color-success">'.\Asset::icon('check-circle').' '.s("Intégral").'</span>',

			TRUE => '<span class="color-success">'.\Asset::icon('check-circle').' '.s("Oui").'</span>',
			FALSE, Role::DENIED => '<span class="color-danger">'.\Asset::icon('x-circle').' '.s("Non").'</span>',

		};
	}

	public function create(Role $eRole): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/role:doCreate');

			$h .= $form->asteriskInfo();

			$h .= $form->hidden('structure', $eRole['structure']);

			$h .= $this->getWrite($form, $eRole);

			$h .= $form->group(
				content: $form->submit(s("Ajouter"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-role-create',
			title: s("Ajouter un rôle"),
			body: $h
		);

	}

	public function update(Role $eRole): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/role:doUpdate');

			$h .= $form->hidden('id', $eRole['id']);
			$h .= $this->getWrite($form, $eRole);

			$submit = '<div class="util-action">';
				$submit .= $form->submit(s("Enregistrer"));
				$submit .= '<a data-ajax="/structure/role:doDelete" class="btn btn-danger" post-id="'.$eRole['id'].'" data-confirm="'.s("Ce rôle sera supprimé et ne pourra plus être attribué à votre équipe, voulez-vous continuer ?").'">'.s("Supprimer ce rôle").'</a>';
			$submit .= '</div>';

			$h .= $form->group(
				content: $submit
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-role-update',
			title: s("Modifier un rôle"),
			body: $h
		);

	}

	public function getWrite(\util\FormUi $form, Role $eRole): string {

		$h = $form->dynamicGroups($eRole, ['name*']);

		$h .= '<h2 class="mt-2">'.s("Quels accès pour ce rôle ?").'</h2>';

		foreach($this->getProperties() as [$title, $properties]) {

			$h .= '<div class="util-block">';
				$h .= $form->group(content: '<h3>'.$title.'</h3>');
				$h .= $form->dynamicGroups($eRole, $properties);
			$h .= '</div>';

		}

		return $h;

	}


	public static function getOwner(): string {
		return s("Administrateur");
	}


	public static function getEducationAdmin(): string {
		return s("Formateur");
	}

	public static function getDefaultTemporary(): string {
		return s("Saisonnier");
	}

	public static function getDefaultPermanent(): string {
		return s("Salarié");
	}

	public static function getDefaultObserverProduction(): string {
		return s("Observateur");
	}

	public static function getDefaultAccountant(): string {
		return s("Comptable");
	}

	public static function getFqn(string $fqn, int $number = 1): string {
		return match($fqn) {
			'admin' => p("Administrateur", "Administrateurs", $number),
			'ghost' => p("Sans compte", "Sans compte", $number),
			// « Membre » nommait deux portées sur le même écran : le rail dit « Membres » pour
			// tout le monde — producteurs, administrateurs, conseillers —, la section ne range
			// que les adhérents. Le rail a raison, c'est la section qui se renomme.
			'member' => p("Producteur", "Producteurs", $number),
			'producer' => p("Producteur", "Producteurs", $number),
			'referent' => p("Référent", "Référents", $number),
			'advisor' => p("Conseiller", "Conseillers", $number),
			'student' => p("Étudiant", "Étudiants", $number),
		};
	}

	public static function p(string $property): \PropertyDescriber {

		$d = Role::model()->describer($property, [
			'name' => s("Nom du rôle"),
			'planning' => s("Planning"),
			'selling' => s("Gestion des ventes"),
			'productionAnalyze' => s("Analyse de la production"),
			'productionObserve' => s("Consultation des cultures"),
			'commercialisationAnalyze' => s("Analyse des ventes"),
			'accounting' => s("Comptabilité"),
			'accountingAnalyze' => s("Analyse comptable"),
		]);

		$production = '<span class="badge badge-solid badge-production">'.\Asset::icon('leaf').' '.s("Production").'</span>';
		$productionPlanning = '<span class="badge badge-solid badge-production">'.\Asset::icon('calendar3').' '.s("Planning").'</span>';
		$productionCrops = '<span class="badge badge-solid badge-production">'.\Asset::icon('journals').' '.s("Cultures").'</span>';
		$productionAnalyze = '<span class="badge badge-solid badge-production">'.\Asset::icon('bar-chart').' '.s("Analyse").'</span>';

		$commercialisation = '<span class="badge badge-solid bg-commercialisation">'.\Asset::icon('basket').' '.s("Vente").'</span>';
		$commercialisationSales = '<span class="badge badge-solid bg-commercialisation">'.\Asset::icon('wallet').' '.s("Commercialisation").'</span>';
		$commercialisationAnalyze = '<span class="badge badge-solid bg-commercialisation">'.\Asset::icon('bar-chart').' '.s("Analyse").'</span>';

		$accounting = '<span class="badge badge-solid bg-accounting">'.\Asset::icon('piggy-bank').' '.s("Gestion").'</span>';
		$accountingAnalyze = '<span class="badge badge-solid bg-accounting">'.\Asset::icon('bar-chart').' '.s("Analyse").'</span>';

		switch($property) {

			case 'planning' :

				$d->values = [
					Role::LIMITED => '<span class="color-warning">'.\Asset::icon('exclamation-circle').' '.s("Accès restreint").'</span>',
					Role::FULL => '<span class="color-success">'.\Asset::icon('check-circle').' '.s("Accès intégral").'</span>',
					Role::DENIED => '<span class="color-danger">'.\Asset::icon('x-circle').' '.s("Accès interdit").'</span>'
				];

				$info = '<ul>';
					$info .= '<li>'.s("<u>Accès restreint</u> : consulter l'intégralité du planning et saisir uniquement son temps de travail et commenter les interventions").'</li>';
					$info .= '<li>'.s("<u>Accès intégral</u> : consulter et modifier l'intégralité du planning").'</li>';
				$info .= '</ul>';

				$d->labelAfter = '<div>'.$production.' '.\Asset::icon('chevron-right').' '.$productionPlanning.'</div>';
				$d->labelAfter .= \util\FormUi::info($info);

				break;

			case 'selling' :

				$d->field = 'yesNo';
				$d->labelAfter = '<div>'.$commercialisation.' '.\Asset::icon('chevron-right').' '.$commercialisationSales.'</div>';
				$d->labelAfter .= \util\FormUi::info(s("Consulter les pages sur la commercialisation, gérer le stock, manipuler les bons de livraison et modifier les ventes, les boutiques en ligne et les clients"));
				break;

			case 'productionObserve' :
				$d->field = 'yesNo';
				$d->labelAfter = '<div>'.$production.' '.\Asset::icon('chevron-right').' '.$productionCrops.'</div>';
				$d->labelAfter .= \util\FormUi::info(s("Consulter sans modifier les pages relatives aux cultures"));
				break;

			case 'productionAnalyze' :
				$d->field = 'yesNo';
				$d->labelAfter = '<div>'.$production.' '.\Asset::icon('chevron-right').' '.$productionAnalyze.'</div>';
				$d->labelAfter .= \util\FormUi::info(s("Consulter les pages d'analyse de la production et le prévisionnel financier"));
				break;

			case 'commercialisationAnalyze' :
				$d->field = 'yesNo';
				$d->labelAfter = '<div>'.$commercialisation.' '.\Asset::icon('chevron-right').' '.$commercialisationAnalyze.'</div>';
				$d->labelAfter .= \util\FormUi::info(s("Consulter les pages d'analyse de la commercialisation à l'exception de celles mentionnant vos clients"));
				break;

			case 'accountingAnalyze' :
				$d->field = 'yesNo';
				$d->labelAfter = '<div>'.$accounting.' '.\Asset::icon('chevron-right').' '.$accountingAnalyze.'</div>';
				$d->labelAfter .= \util\FormUi::info(s("Consulter la synthèse comptable et ses documents : bilan, compte de résultat, soldes intermédiaires de gestion, archives, fichier FEC et attestation, ainsi que les analyses financières. Ne donne accès ni à la saisie, ni aux déclarations de TVA, ni à la tenue des exercices."));
				break;

			case 'accounting' :

				$d->values = [
					Role::LIMITED => '<span class="color-warning">'.\Asset::icon('exclamation-circle').' '.s("Accès restreint").'</span>',
					Role::FULL => '<span class="color-success">'.\Asset::icon('check-circle').' '.s("Accès intégral").'</span>',
					Role::DENIED => '<span class="color-danger">'.\Asset::icon('x-circle').' '.s("Accès interdit").'</span>'
				];

				$d->labelAfter = function(Role $e) use($accounting) {

					$info = '<ul>';
						if($e['structure']->acceptProof()) {
							$info .= '<li>'.s("<u>Accès restreint</u> : consulter sans les modifier les pages de la facturation électronique, la banque, les cahiers de caisse et la comptabilité, et déposer des justificatifs").'</li>';
						} else {
							$info .= '<li>'.s("<u>Accès restreint</u> : consulter sans les modifier les pages de la facturation électronique, la banque, les cahiers de caisse et la comptabilité").'</li>';
						}
						$info .= '<li>'.s("<u>Accès intégral</u> : consulter et tenir la comptabilité, saisir les écritures, les cahiers de caisse et les rapprochements bancaires").'</li>';
					$info .= '</ul>';

					return '<div>'.$accounting.'</div>'.\util\FormUi::info($info);

				};

				break;

		}

		return $d;

	}

}
