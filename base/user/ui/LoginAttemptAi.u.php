<?php
namespace user;

/**
 * L'écran « Tentatives » de l'administration : les séries de connexions échouées, leurs filtres
 * et le geste de bannir.
 */
class LoginAttemptAiUi {

	public function getSearch(\Search $search, int $nLoginAttempt): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/user/admin/loginAttemptAi', ['method' => 'get', 'class' => 'util-search']);
			$h .= '<fieldset>';
				$h .= $form->select('status', [
					LoginAttemptAiLib::FILTER_THRESHOLD => p("{value} échec et plus", "{value} échecs et plus", LoginAttemptAiLib::THRESHOLD),
					LoginAttemptAiLib::FILTER_THROTTLED => s("Refusées"),
					LoginAttemptAiLib::FILTER_SUCCEEDED => s("Réussies après des échecs"),
				], $search->get('status'), ['placeholder' => s("Toutes les séries")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->text('login', $search->get('login'), ['placeholder' => s("Identifiant")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->text('ip', $search->get('ip'), ['placeholder' => s("Adresse")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->date('from', $search->get('from'));
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->date('to', $search->get('to'));
			$h .= '</fieldset>';
			$h .= '<div class="util-search-submit">';
				$h .= $form->submit(s("Chercher"));
				if($search->get('login') or $search->get('ip') or $search->get('from') or $search->get('to') or $search->get('status')) {
					$h .= ' <a href="/user/admin/loginAttemptAi" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';
				}
				$h .= '<span class="badge badge-solid bg-primary">'.p("{value} série", "{value} séries", $nLoginAttempt).'</span>';
			$h .= '</div>';
		$h .= $form->close();

		return $h;

	}

	public function getCollection(\Collection $cLoginAttempt): string {

		if($cLoginAttempt->empty()) {
			return '<div class="util-empty">'.s("Aucune série de tentatives à afficher.").'</div>';
		}

		$h = '<div class="util-overflow-md">';
			$h .= '<table class="tr-even tr-hover">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Identifiant tenté").'</th>';
						$h .= '<th>'.s("Adresse").'</th>';
						$h .= '<th class="text-end">'.s("Échecs").'</th>';
						$h .= '<th class="hide-sm-down">'.s("Première").'</th>';
						$h .= '<th>'.s("Dernière").'</th>';
						$h .= '<th>'.s("Issue").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

				foreach($cLoginAttempt as $eLoginAttempt) {

					$h .= '<tr>';
						$h .= '<td>'.$this->getLogin($eLoginAttempt).'</td>';
						$h .= '<td>'.encode($eLoginAttempt['ip']).'</td>';
						$h .= '<td class="text-end">'.$eLoginAttempt['failures'].'</td>';
						$h .= '<td class="hide-sm-down">'.\util\DateUi::numeric($eLoginAttempt['firstAt']).'</td>';
						$h .= '<td>'.\util\DateUi::numeric($eLoginAttempt['lastAt']).'</td>';
						$h .= '<td>'.$this->getIssue($eLoginAttempt).'</td>';
						$h .= '<td class="text-end">'.$this->getBan($eLoginAttempt).'</td>';
					$h .= '</tr>';

				}

				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	protected function getLogin(LoginAttempt $eLoginAttempt): string {

		if($eLoginAttempt['user']->empty()) {
			return encode($eLoginAttempt['login']).'<div class="font-sm color-muted">'.s("Aucun compte").'</div>';
		}

		$h = '<a href="/user/admin/?id='.$eLoginAttempt['user']['id'].'">'.encode($eLoginAttempt['user']['email'] ?? $eLoginAttempt['user']->getName()).'</a>';

		if($eLoginAttempt['user']->isAdmin()) {
			$h .= '<div class="font-sm"><span class="badge badge-warning">'.s("Administrateur").'</span></div>';
		}

		return $h;

	}

	protected function getIssue(LoginAttempt $eLoginAttempt): string {

		return match($eLoginAttempt['status']) {
			LoginAttempt::THROTTLED => '<span class="badge badge-danger">'.s("Refusée").'</span>',
			LoginAttempt::SUCCEEDED => '<span class="badge badge-warning">'.\Asset::icon('key').' '.p("Réussie après {value} échec", "Réussie après {value} échecs", $eLoginAttempt['failures']).'</span>',
			LoginAttempt::LINK => '<span class="badge badge-warning">'.\Asset::icon('envelope').' '.p("Réussie après {value} échec", "Réussie après {value} échecs", $eLoginAttempt['failures']).'</span>',
			LoginAttempt::RUNNING => '<span class="color-muted">'.($eLoginAttempt['isOpen'] ? s("En cours") : s("Sans suite")).'</span>',
		};

	}

	protected function getBan(LoginAttempt $eLoginAttempt): string {

		$href = '/user/admin/ban:form?ip='.encode($eLoginAttempt['ip']);

		if($eLoginAttempt['user']->notEmpty()) {
			$href .= '&userToBan='.$eLoginAttempt['user']['id'];
		}

		return '<a href="'.$href.'" class="btn btn-sm btn-outline-danger">'.s("Bannir").'</a>';

	}

}
?>
