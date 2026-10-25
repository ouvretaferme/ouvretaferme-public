<?php
namespace aid;

class SourceUi {

	public function update(Source $eSource): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/aid/admin/source:doUpdate');

			$h .= $form->hidden('id', $eSource);

			$h .= $form->dynamicGroups($eSource, ['url*', 'kind*']);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-aid-source',
			title: s("Modifier la source"),
			body: $h
		);

	}

	/**
	 * L'état d'une source, tel que la veille l'a laissé. Un agrégateur ne se lit pas (cadrage de L5, R17).
	 * Une adresse changée ou une page morte passent avant une page illisible, qui garde son dernier état.
	 * Une source que la veille n'a jamais lue n'a que le défaut du schéma, « répond », qui ne dit rien.
	 */
	public static function getStatus(Source $eSource): string {

		if($eSource['kind'] === Source::AGGREGATOR) {
			return '<span class="badge badge-neutral badge-dot">'.s("non suivie").'</span>';
		}

		if($eSource['status'] === Source::ALIVE) {

			if($eSource->isUnreadable()) {
				return '<span class="badge badge-info badge-dot">'.s("illisible par la veille").'</span>';
			}

			if($eSource['checkedAt'] === NULL) {
				return '<span class="badge badge-neutral badge-dot">'.s("pas encore lue").'</span>';
			}

		}

		$class = match($eSource['status']) {
			Source::ALIVE => 'badge-success',
			Source::MOVED => 'badge-warning',
			Source::DEAD => 'badge-danger',
		};

		return '<span class="badge '.$class.' badge-dot">'.self::p('status')->values[$eSource['status']].'</span>';

	}

	/**
	 * Ce qui fonde l'état d'une source, sous son adresse (arbitrage Q2 B de la planche de L5) : depuis
	 * quand, et ce que la veille en a fait pour l'aide. Rien pour une page qui répond sans avoir changé.
	 */
	public static function getWatch(Aid $eAid, Source $eSource): string {

		$text = self::getWatchText($eAid, $eSource);

		if($text === NULL) {
			return '';
		}

		return '<div class="util-annotation text-break">'.$text.'</div>';

	}

	protected static function getWatchText(Aid $eAid, Source $eSource): ?string {

		if($eSource['kind'] === Source::AGGREGATOR) {
			return s("La veille ne lit pas les agrégateurs");
		}

		if($eSource['status'] === Source::DEAD) {

			if($eSource['kind'] !== Source::PRIMARY or $eAid['status'] !== Aid::PUBLISHED) {
				return p("Ne répond plus depuis {value} nuit", "Ne répond plus depuis {value} nuits", $eSource['failures']);
			} else if($eAid->hasSourcePrimaryAllDead()) {
				return p("Ne répond plus depuis {value} nuit, l'aide est masquée", "Ne répond plus depuis {value} nuits, l'aide est masquée", $eSource['failures']);
			} else {
				return p("Ne répond plus depuis {value} nuit, l'aide reste affichée par une autre source officielle", "Ne répond plus depuis {value} nuits, l'aide reste affichée par une autre source officielle", $eSource['failures']);
			}

		}

		if($eSource['status'] === Source::MOVED) {
			return s("Redirigée depuis le {date} vers {url}", [
				'date' => \util\DateUi::numeric($eSource['statusAt'], \util\DateUi::DATE),
				'url' => encode($eSource['movedUrl']),
			]);
		}

		// Une page introuvable, pas encore assez de nuits pour la dire morte (WatchLib::FAILURES_DEAD). Une
		// nuit illisible ne remet pas les échecs à zéro (R13) : c'est la plus récente des deux qui se dit.
		if(
			$eSource['failures'] > 0 and
			($eSource['unreadSince'] === NULL or $eSource['failedAt'] >= $eSource['unreadSince'])
		) {
			return p("Introuvable depuis {value} nuit", "Introuvable depuis {value} nuits", $eSource['failures']);
		}

		// Qu'elle n'ait jamais été lue, la colonne « Lue le » vide le dit déjà, et le badge « pas encore lue »
		// avant le seuil de la file (arbitrage de V-Sub sur le glaçon 1 de L5).
		if($eSource['unreadSince'] !== NULL) {
			return s("{reason} depuis le {date}", [
				'reason' => self::p('unreadReason')->values[$eSource['unreadReason']],
				'date' => \util\DateUi::numeric($eSource['unreadSince'], \util\DateUi::DATE),
			]);
		}

		if($eSource['checkedAt'] === NULL) {
			return s("Lue la nuit prochaine");
		}

		if($eSource['changedAt'] !== NULL) {
			return s("Page changée le {value}", \util\DateUi::numeric($eSource['changedAt'], \util\DateUi::DATE));
		}

		return NULL;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Source::model()->describer($property, [
			'url' => s("Adresse"),
			'kind' => s("Nature"),
			'status' => s("Page"),
			'checkedAt' => s("Lue le"),
		]);

		switch($property) {

			case 'kind' :
				$d->values = [
					Source::PRIMARY => s("Officielle"),
					Source::RELAY => s("Relais"),
					Source::AGGREGATOR => s("Agrégateur"),
					Source::PIECES => s("Liste des pièces"),
				];
				break;

			case 'status' :
				$d->values = [
					Source::ALIVE => s("répond"),
					Source::MOVED => s("a changé d'adresse"),
					Source::DEAD => s("ne répond plus"),
				];
				break;

			case 'unreadReason' :
				$d->values = [
					Source::CHALLENGE => s("Le site répond par une page de défi"),
					Source::FORBIDDEN => s("Le site refuse la veille"),
					Source::ROBOTS => s("Le robots.txt du site interdit la veille"),
					Source::TIMEOUT => s("Le site ne répond pas dans le délai"),
					Source::LIMITED => s("Le site limite le nombre de lectures"),
					Source::SERVER => s("Le site répond par une erreur"),
				];
				break;

		}

		return $d;

	}

}
?>
