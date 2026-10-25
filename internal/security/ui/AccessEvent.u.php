<?php
namespace security;

/**
 * Les événements du journal d'accès Nginx sur l'écran « Accès » de l'administration : la recherche
 * et le tableau. Aucun geste : un bannissement du produit n'agit qu'à la connexion et à
 * l'inscription, il ne bloquerait rien devant Nginx.
 */
class AccessEventUi {

	public function getSearch(\Search $search, int $nAccessEvent): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/security/admin/access', ['method' => 'get', 'class' => 'util-search']);
			$h .= '<fieldset>';
				$h .= $form->select('type', [
					AccessEvent::SCAN => s("Balayages"),
					AccessEvent::FLOOD => s("Rafales"),
					AccessEvent::ERROR => s("Routes en erreur"),
				], $search->get('type'), ['placeholder' => s("Tous les événements")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->text('ip', $search->get('ip'), ['placeholder' => s("Adresse")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->text('host', $search->get('host'), ['placeholder' => s("Hôte")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->date('from', $search->get('from'));
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->date('to', $search->get('to'));
			$h .= '</fieldset>';
			$h .= '<div class="util-search-submit">';
				$h .= $form->submit(s("Chercher"));
				if($search->get('type') or $search->get('ip') or $search->get('host') or $search->get('from') or $search->get('to')) {
					$h .= ' <a href="/security/admin/access" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';
				}
				$h .= '<span class="badge badge-solid bg-primary">'.p("{value} événement", "{value} événements", $nAccessEvent).'</span>';
			$h .= '</div>';
		$h .= $form->close();

		return $h;

	}

	/**
	 * Les deux onglets de l'écran, les événements et le rapport GoAccess. Une recherche posée ramène
	 * sur les événements, ce qui vaut aussi pour le lien d'un message Discord ; sinon le dernier
	 * onglet ouvert est retenu dans le navigateur.
	 */
	public function getTabs(\Search $search, string $events, string $report): string {

		$forced = $search->notEmpty() ? 'logs' : '';

		$h = '<div class="tabs" id="security-access-tabs" onrender="'.encode('Lime.Tab.restore(this, "logs", "'.$forced.'")').'">';

			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item" data-tab="logs" onclick="Lime.Tab.select(this)">'.s("Logs").'</a>';
				$h .= '<a class="tab-item" data-tab="report" onclick="Lime.Tab.select(this)">'.s("Rapport GoAccess").'</a>';
			$h .= '</div>';

			$h .= '<div class="tab-panel" data-tab="logs">';
				$h .= $events;
			$h .= '</div>';

			$h .= '<div class="tab-panel" data-tab="report">';
				$h .= $report;
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getCollection(\Collection $cAccessEvent, bool $configured): string {

		if($configured === FALSE) {
			return '<div class="util-empty">'.s("Les événements ne sont pas relevés sur cette installation.").'</div>';
		}

		if($cAccessEvent->empty()) {
			return '<div class="util-empty">'.s("Aucun événement sur la période.").'</div>';
		}

		$h = $this->getStatusLegend();

		$h .= '<div class="util-overflow-sm">';
			$h .= '<table class="tr-even tr-hover">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Heure").'</th>';
						$h .= '<th>'.s("Événement").'</th>';
						$h .= '<th>'.s("Adresse").'</th>';
						$h .= '<th>'.s("Hôte").'</th>';
						$h .= '<th colspan="2">'.s("Chemin").'</th>';
						$h .= '<th class="text-end">'.s("Réponses").'</th>';
						$h .= '<th>'.s("Discord").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

				foreach($cAccessEvent as $eAccessEvent) {

					$h .= '<tr>';
						$h .= '<td>'.$this->getHour($eAccessEvent).'</td>';
						$h .= '<td>'.$this->getType($eAccessEvent).'</td>';
						$h .= '<td>'.encode($eAccessEvent['ip']).'</td>';
						$h .= '<td>'.$this->getHost($eAccessEvent).'</td>';
						$h .= '<td>'.$this->getStatusIcon($eAccessEvent).'</td>';
						$h .= '<td class="security-access-path">'.$this->getPath($eAccessEvent).'</td>';
						$h .= '<td class="text-end">'.s("{count} en {status}", ['count' => $eAccessEvent['count'], 'status' => $eAccessEvent['status']]).'</td>';
						$h .= '<td>'.$this->getNotified($eAccessEvent).'</td>';
					$h .= '</tr>';

				}

				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	protected function getHour(AccessEvent $eAccessEvent): string {

		$start = strtotime($eAccessEvent['hour']);

		return s("{date} de {from} à {to}", [
			'date' => \util\DateUi::numeric($eAccessEvent['hour'], \util\DateUi::DATE),
			'from' => date('H:i', $start),
			'to' => date('H:i', $start + 3600),
		]);

	}

	protected function getType(AccessEvent $eAccessEvent): string {

		$class = match($eAccessEvent['type']) {
			AccessEvent::SCAN => 'badge-neutral',
			AccessEvent::FLOOD => 'badge-warning',
			AccessEvent::ERROR => 'badge-danger',
		};

		return '<span class="badge '.$class.'">'.self::p('type')->values[$eAccessEvent['type']].'</span>';

	}

	protected function getHost(AccessEvent $eAccessEvent): string {

		$h = encode($eAccessEvent['host']);

		if($eAccessEvent['hosts'] !== NULL and $eAccessEvent['hosts'] > 1) {
			$h .= '<div class="font-sm color-muted">'.p("et {value} autre hôte", "et {value} autres hôtes", $eAccessEvent['hosts'] - 1).'</div>';
		}

		return $h;

	}

	/**
	 * 444 est le refus de Nginx avant PHP (`framework/dev/conf/rewrite/scan.cfg`), 429 celui de sa
	 * limite de débit, dont l'algorithme est un seau qui déborde. 499, le client parti avant la
	 * réponse, ne dit rien du serveur et n'a pas de picto.
	 */
	protected function getStatusIcon(AccessEvent $eAccessEvent): string {

		$status = $eAccessEvent['status'];

		if($status === 444) {
			$icon = 'shield-fill-check';
		} else if($status === 429) {
			$icon = 'bucket-fill';
		} else if($status === 400) {
			$icon = 'x-octagon';
		} else if($status === 404 or $status === 410) {
			$icon = 'question-circle';
		} else if($status >= 500) {
			$icon = 'server';
		} else {
			return '';
		}

		return \Asset::icon($icon);

	}

	protected function getStatusLegend(): string {

		$labels = [
			'shield-fill-check' => s("Refusée par Nginx avant PHP"),
			'bucket-fill' => s("Limite de débit dépassée"),
			'x-octagon' => s("Requête invalide"),
			'question-circle' => s("Page introuvable"),
			'server' => s("Erreur du serveur"),
		];

		$h = '<div class="util-legend mb-1">';

			foreach($labels as $icon => $label) {
				$h .= '<span class="util-legend-item">'.\Asset::icon($icon).$label.'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	protected function getPath(AccessEvent $eAccessEvent): string {

		$h = encode($eAccessEvent['path']);

		$h .= match($eAccessEvent['type']) {
			AccessEvent::SCAN => '<div class="font-sm color-muted">'.p("{value} chemin distinct", "{value} chemins distincts", $eAccessEvent['paths']).'</div>',
			AccessEvent::FLOOD => '<div class="font-sm color-muted">'.s("dernier chemin refusé").'</div>',
			AccessEvent::ERROR => '',
		};

		return $h;

	}

	protected function getNotified(AccessEvent $eAccessEvent): string {

		if($eAccessEvent['notifiedAt'] === NULL) {
			return '';
		}

		return '<span class="color-muted">'.s("Signalé à {time}", ['time' => \util\DateUi::numeric($eAccessEvent['notifiedAt'], \util\DateUi::TIME_HOUR_MINUTE)]).'</span>';

	}

	public static function p(string $property): \PropertyDescriber {

		$d = AccessEvent::model()->describer($property, [
			'type' => s("Événement"),
			'ip' => s("Adresse"),
			'host' => s("Hôte"),
			'path' => s("Chemin"),
			'status' => s("Statut"),
			'hour' => s("Heure"),
			'count' => s("Réponses"),
			'hosts' => s("Hôtes distincts"),
			'paths' => s("Chemins distincts"),
			'notifiedAt' => s("Signalé à"),
		]);

		switch($property) {

			case 'type' :
				$d->values = [
					AccessEvent::SCAN => s("Balayage"),
					AccessEvent::FLOOD => s("Rafale"),
					AccessEvent::ERROR => s("Route en erreur"),
				];
				break;

		}

		return $d;

	}

}
?>
