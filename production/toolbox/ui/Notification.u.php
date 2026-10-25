<?php
namespace toolbox;

/**
 * Ce que la Boite à outils apporte au centre de notifications côté affichage : les mots et l'icône
 * du point du lundi sur les entretiens, et du rappel des compteurs à relever, ceux du carnet comme
 * ceux de l'eau.
 *
 * Pendant du `NotificationLib` du même package. Le contrat de la paire est écrit dans
 * `base/notification/CLAUDE.md` et tenu par `base/notification/test/NotificationTypeSentinel.t.php`.
 *
 * Une ligne reste trente jours avec le contenu de son premier jour : les échéances se disent donc en
 * dates et en valeurs, jamais en « dans 3 jours », et le nom d'un entretien, saisi librement, ne
 * s'accorde avec rien.
 *
 * Un groupe déroulé ne montre que `getText()` (`\notification\NotificationUi::getGroup()`), jamais
 * `getDetails()` : ce qu'une ligne doit dire dans le déroulé va dans sa phrase. D'où le jour d'un
 * entretien à faire ; le retard et le compteur à relever s'en passent, le clic mène au carnet.
 */
class NotificationUi {

	public function getTypeName(string $type): string {

		return match($type) {
			NotificationLib::MAINTENANCE => s("Entretiens du matériel"),
			NotificationLib::READING => s("Compteurs à relever"),
			NotificationLib::WATER_READING => s("Compteurs d'eau à relever")
		};

	}

	public function getTypeDescription(string $type, \structure\Structure $eStructure): string {

		return match($type) {
			NotificationLib::MAINTENANCE => s("Chaque lundi, les entretiens en retard et ceux à faire dans la semaine."),
			NotificationLib::READING => s("Le 5 du mois, les compteurs qui n'ont pas été relevés. La fréquence se paramètre dans le <link>carnet d'entretien</link>.", ['link' => '<a href="'.EquipmentUi::url($eStructure).'">']),
			NotificationLib::WATER_READING => s("Le 5 du mois, les compteurs d'eau qui n'ont pas été relevés. La fréquence se paramètre dans la <link>consommation d'eau</link>.", ['link' => '<a href="'.WaterReadingUi::url($eStructure).'">'])
		};

	}

	public function getLock(string $right): string {

		return match($right) {
			'planning' => s("Réservé aux membres qui ont accès au planning")
		};

	}

	/** @return array [classes, svg] */
	public function getIcon(\notification\Notification $eNotification): array {

		return match($eNotification['type']) {
			NotificationLib::MAINTENANCE => ['util-icon-production', \Asset::icon('wrench')],
			NotificationLib::READING => ['util-icon-production', \Asset::icon('speedometer2')],
			NotificationLib::WATER_READING => ['util-icon-production', \Asset::icon('droplet-half')]
		};

	}

	public function getText(\notification\Notification $eNotification): string {

		$content = $eNotification['content'];

		return match($eNotification['type']) {
			NotificationLib::MAINTENANCE => $content['late'] ?
				s("{maintenance} en retard sur {equipment}", ['maintenance' => encode($content['maintenance']), 'equipment' => encode($content['equipment'])]) :
				s("{maintenance} à faire le {date} sur {equipment}", ['maintenance' => encode($content['maintenance']), 'date' => EquipmentUi::getDate($content['plannedDate']), 'equipment' => encode($content['equipment'])]),
			NotificationLib::READING => s("Compteur à relever sur {equipment}", ['equipment' => encode($content['equipment'])]),
			NotificationLib::WATER_READING => s("Compteur d'eau {meter} à relever", ['meter' => encode($content['meter'])])
		};

	}

	public function getGroupText(\notification\Notification $eNotificationHead): string {

		$number = $eNotificationHead['cNotificationGroup']->count();

		// La part reçue avant les nouvelles (`earlier`, cf. `\notification\NotificationLib::group()`) ne
		// redit pas le retard ni la semaine : elle se lirait comme un second point du lundi.
		if($eNotificationHead['earlier']) {

			return match($eNotificationHead['type']) {
				NotificationLib::MAINTENANCE => p("{value} entretien signalé plus tôt", "{value} entretiens signalés plus tôt", $number),
				NotificationLib::READING => p("{value} compteur signalé plus tôt", "{value} compteurs signalés plus tôt", $number),
				NotificationLib::WATER_READING => p("{value} compteur d'eau signalé plus tôt", "{value} compteurs d'eau signalés plus tôt", $number)
			};

		}

		return match($eNotificationHead['type']) {
			NotificationLib::MAINTENANCE => $this->getMaintenanceGroupText($eNotificationHead),
			NotificationLib::READING => p("{value} compteur à relever", "{value} compteurs à relever", $number),
			NotificationLib::WATER_READING => p("{value} compteur d'eau à relever", "{value} compteurs d'eau à relever", $number)
		};

	}

	/**
	 * Le point du lundi en une phrase : les retards d'abord, puis ce que la semaine demande, avec son
	 * dernier jour en date (« d'ici le 4 octobre »).
	 */
	private function getMaintenanceGroupText(\notification\Notification $eNotificationHead): string {

		// Une copie en tableau : le moteur peut être en train de parcourir le groupe, et une `Collection`
		// n'a qu'un curseur.
		$notifications = $eNotificationHead['cNotificationGroup']->getArrayCopy();

		$late = count(array_filter($notifications, fn($eNotification) => $eNotification['content']['late']));
		$week = count($notifications) - $late;

		$until = EquipmentUi::getDate($eNotificationHead['content']['until']);

		if($week === 0) {
			return p("{value} entretien en retard", "{value} entretiens en retard", $late);
		}

		if($late === 0) {
			return p("{value} entretien à faire d'ici le {date}", "{value} entretiens à faire d'ici le {date}", $week, ['date' => $until]);
		}

		return s("{late} et {week}", [
			'late' => p("{value} entretien en retard", "{value} entretiens en retard", $late),
			'week' => s("{value} à faire d'ici le {date}", ['value' => $week, 'date' => $until])
		]);

	}

	/**
	 * Ce qui nomme une ligne dans un groupe déroulé : l'entretien et son matériel, comme le bandeau des
	 * entretiens à réaliser du carnet ; le matériel seul pour un compteur.
	 */
	public function getActor(\notification\Notification $eNotification): string {

		$content = $eNotification['content'];

		return match($eNotification['type']) {
			NotificationLib::MAINTENANCE => encode($content['maintenance']).' · '.encode($content['equipment']),
			NotificationLib::READING => encode($content['equipment']),
			NotificationLib::WATER_READING => encode($content['meter'])
		};

	}

	public function getDetails(\notification\Notification $eNotification): array {

		$content = $eNotification['content'];

		return match($eNotification['type']) {
			// Un entretien à faire dit déjà son jour dans sa phrase, la seule que montre un groupe déroulé.
			NotificationLib::MAINTENANCE => $content['late'] ? [$this->getMaintenanceDue($content)] : [],
			NotificationLib::READING, NotificationLib::WATER_READING => [
				$content['lastDate'] === NULL ?
					s("Aucun relevé") :
					s("Dernier relevé le {value}", EquipmentUi::getDate($content['lastDate']))
			]
		};

	}

	/**
	 * L'échéance qui a décidé : au compteur, avec ce que le compteur affichait au dernier relevé ; à la
	 * date sinon.
	 */
	private function getMaintenanceDue(array $content): string {

		return match($content['by']) {
			'value' => s("Échéance à {planned}, compteur à {reading}", [
				'planned' => EquipmentUi::getValue(new Equipment(['meter' => $content['meter']]), $content['plannedValue']),
				'reading' => EquipmentUi::getValue(new Equipment(['meter' => $content['meter']]), $content['readingValue'])
			]),
			'date' => s("Échéance le {value}", EquipmentUi::getDate($content['plannedDate']))
		};

	}

	/** Rien de plus que les noms, que le moteur résume déjà sous la ligne du groupe. */
	public function getGroupDetails(\notification\Notification $eNotificationHead): array {

		return [];

	}

}
?>
