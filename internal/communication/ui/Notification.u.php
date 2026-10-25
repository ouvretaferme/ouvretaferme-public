<?php
namespace communication;

/**
 * Les notes de l'équipe dans la cloche et le centre (N15, D8-C) : le logo du site à la place de
 * l'icône d'une famille, « Équipe Ouvretaferme » en méta, et dans le centre le texte entier suivi de
 * son bouton.
 *
 * Seules les méthodes que le moteur appelle sur une ligne sans groupe sont écrites ici : une note
 * n'a ni groupe, ni réglage, ni verrou, n'étant pas un type (`NotificationLib`).
 *
 * Ce fichier ne porte pas le suffixe Ai : son nom est celui que le moteur résout
 * (`base/notification/CLAUDE.md`).
 */
class NotificationUi {

	public function getIcon(\notification\Notification $eNotification): array {

		return match($eNotification['type']) {
			NotificationLib::NOTE => ['util-icon-brand', \Asset::svg('main', 'svg/logo-otf.svg')]
		};

	}

	public function getText(\notification\Notification $eNotification): string {
		return encode($eNotification['content']['title']);
	}

	public function getDetails(\notification\Notification $eNotification): array {
		return [s("Équipe Ouvretaferme")];
	}

	public function getCommonBody(\notification\Notification $eNotification): string {
		return new \editor\EditorUi()->value($eNotification['content']['content']);
	}

	public function getCommonButton(\notification\Notification $eNotification): ?string {

		if($eNotification['content']['buttonLabel'] === NULL) {
			return NULL;
		}

		return encode($eNotification['content']['buttonLabel']);

	}

}
?>
