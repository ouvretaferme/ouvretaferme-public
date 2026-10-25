<?php
namespace hr;

class PresenceUi {

	public function show(\structure\Member $eMember, \Collection $cPresence): string {

		$eStructure = $eMember['structure'];

		$h = '<div class="util-title">';

			$h .= '<h2>'.s("Périodes de présence").'</h2>';

			// Une période ne s'ajoute qu'à quelqu'un de l'équipe : les autres y entrent par leur première période.
			if($eMember['isTeammate']) {

				$h .= '<a href="/hr/presence:create?structure='.$eStructure['id'].'&user='.$eMember['user']['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Nouvelle période de présence").'</a>';

			} else {
				$h .= '<a href="/hr/teammate:create?structure='.$eStructure['id'].'&user='.$eMember['user']['id'].'" class="btn btn-outline-primary">'.\Asset::icon('person-plus-fill').' '.s("Ajouter au planning").'</a>';
			}

		$h .= '</div>';

		if($cPresence->empty()) {
			$h .= '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucune période de présence").'</h3>';
				$h .= '<p>'.s("Une période de présence fait figurer cette personne au planning, de son arrivée à son départ.").'</p>';
			$h .= '</div>';
		} else {

			$hr = $eMember['structure']->acceptHr();

			$h .= '<table class="tr-even">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Période").'</th>';
						if($hr) {
							$h .= '<th>'.s("Contrat").'</th>';
						}
						$h .= '<th class="text-end"></th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

					foreach($cPresence as $ePresence) {

						$h .= '<tr>';

							$h .= '<td>'.$this->getPeriod($ePresence).'</td>';
							if($hr) {
								$h .= '<td>'.($ePresence['eContract']->empty() ? '<span class="color-muted">—</span>' : ContractUi::getText($ePresence['eContract'])).'</td>';
							}

							$h .= '<td class="text-end">';
								$h .= '<a href="/hr/presence:update?id='.$ePresence['id'].'" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('gear-fill').'</a> ';
								$h .= '<a data-ajax="/hr/presence:doDelete" post-id="'.$ePresence['id'].'" data-confirm="'.s("Supprimer cette période de présence ?").'" class="btn btn-only-icon btn-danger">'.\Asset::icon('trash').'</a>';
							$h .= '</td>';

						$h .= '</tr>';

					}

				$h .= '</tbody>';

			$h .= '</table>';

		}

		return $h;

	}

	public function getPeriod(Presence $ePresence): string {

		if($ePresence->empty()) {
			return s("Aucune période de présence");
		}

		if($ePresence['to'] === NULL) {
			if($ePresence->isComing()) {
				return s("à partir du {value}", \util\DateUi::numeric($ePresence['from']));
			} else {
				return s("depuis le {value}", \util\DateUi::numeric($ePresence['from']));
			}
		}

		return s("du {from} au {to}", ['from' => \util\DateUi::numeric($ePresence['from']), 'to' => \util\DateUi::numeric($ePresence['to'])]);

	}

	public function create(\structure\Structure $eStructure, Presence $ePresence, Contract $eContract): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/hr/presence:doCreate', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('structure', $eStructure['id']);

			$h .= $form->dynamicGroups($ePresence, ['user', 'from', 'to']);
			$h .= ContractUi::getFields($form, $eContract);

			$h .= $form->group(
				content: $form->submit(s("Ajouter la période de présence"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-presence-create',
			title: s("Ajouter une période de présence"),
			body: $h,
			close: 'reload'
		);

	}

	public function update(Presence $ePresence, Contract $eContract): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/hr/presence:doUpdate', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('id', $ePresence['id']);

			$h .= $form->group(
				s("Personne"),
				$ePresence['user']->getName()
			);

			$h .= $form->dynamicGroups($ePresence, ['from', 'to']);

			$h .= ContractUi::getFields($form, $eContract);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-presence-update',
			title: s("Modifier la période de présence"),
			body: $h,
			close: 'reload'
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Presence::model()->describer($property, [
			'from' => s("Jour d'arrivée"),
			'to' => s("Jour de départ"),
			'user' => s("Personne"),
		]);

		switch($property) {

			case 'user' :
				$d->values = fn(Presence $e) => $e['cUser'] ?? $e->expects(['cUser']);
				break;

			case 'to' :
				$d->after = \util\FormUi::info(s("Laisser vide si le départ n'est pas connu."));
				break;


		}

		return $d;

	}

}
?>
