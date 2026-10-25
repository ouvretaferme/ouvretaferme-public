<?php
namespace structure;

/**
 * Ce que le package `structure` apporte au centre de notifications côté affichage : les mots et
 * l'icône des arrivées et des départs d'une équipe, et de ceux des fermes d'un groupe.
 *
 * Pendant du `NotificationLib` du même package. Le contrat de la paire est écrit dans
 * `base/notification/CLAUDE.md` et tenu par `base/notification/test/NotificationTypeSentinel.t.php`.
 * Nom fixe et sans suffixe `Ai`, pour la même raison que le Lib.
 *
 * Les phrases sont celles de la planche du lot 4, validée le 2026-09-21 : le mot qui
 * nomme la structure suit son type (« le réseau », « le groupe », « la ferme »), et c'est
 * `StructureUi::getText()` qui le donne, comme partout ailleurs dans le produit.
 */
class NotificationUi {

	public function getTypeName(string $type): string {

		return match($type) {
			NotificationLib::MEMBER_JOINED => s("Nouveau membre"),
			NotificationLib::MEMBER_LEFT => s("Départ d'un membre"),
			NotificationLib::PRODUCER_JOINED => s("Nouvelle ferme dans le groupe"),
			NotificationLib::PRODUCER_LEFT => s("Départ d'une ferme du groupe"),
			NotificationLib::PRODUCER_EXCLUDED => s("Retrait d'un groupe")
		};

	}

	public function getTypeDescription(string $type, Structure $eStructure): string {

		return match($type) {
			NotificationLib::MEMBER_JOINED => s("Une personne accepte une invitation et rejoint {the}.", ['the' => StructureUi::getText($eStructure, 'the')]),
			NotificationLib::MEMBER_LEFT => s("Une personne quitte {the}.", ['the' => StructureUi::getText($eStructure, 'the')]),
			NotificationLib::PRODUCER_JOINED => s("Une ferme rejoint {the}.", ['the' => StructureUi::getText($eStructure, 'the')]),
			NotificationLib::PRODUCER_LEFT => s("Une ferme quitte {the}.", ['the' => StructureUi::getText($eStructure, 'the')]),
			NotificationLib::PRODUCER_EXCLUDED => s("Un groupe vous retire de ses fermes.")
		};

	}

	public function getLock(string $right): string {

		return match($right) {
			'admin' => s("Réservé aux administrateurs")
		};

	}

	/** @return array [classes, svg] */
	public function getIcon(\notification\Notification $eNotification): array {

		return match($eNotification['type']) {
			NotificationLib::MEMBER_JOINED, NotificationLib::MEMBER_LEFT => ['util-icon-settings', \Asset::icon('person')],
			NotificationLib::PRODUCER_JOINED, NotificationLib::PRODUCER_LEFT, NotificationLib::PRODUCER_EXCLUDED => ['util-icon-settings', \Asset::icon('house')]
		};

	}

	public function getText(\notification\Notification $eNotification): string {

		$eStructure = $this->getStructure($eNotification);

		return match($eNotification['type']) {
			NotificationLib::MEMBER_JOINED => s("{user} a rejoint l'équipe {of}", [
				'user' => $this->getActor($eNotification),
				'of' => StructureUi::getText($eStructure, 'of')
			]),
			NotificationLib::MEMBER_LEFT => s("{user} a quitté l'équipe {of}", [
				'user' => $this->getActor($eNotification),
				'of' => StructureUi::getText($eStructure, 'of')
			]),
			NotificationLib::PRODUCER_JOINED => s("{structure} a rejoint {the}", [
				'structure' => $this->getActor($eNotification),
				'the' => StructureUi::getText($eStructure, 'the')
			]),
			NotificationLib::PRODUCER_LEFT => s("{structure} a quitté {the}", [
				'structure' => $this->getActor($eNotification),
				'the' => StructureUi::getText($eStructure, 'the')
			]),
			NotificationLib::PRODUCER_EXCLUDED => s("Vous ne faites plus partie de {community}", [
				'community' => $this->getActor($eNotification)
			])
		};

	}

	/**
	 * Ces types ne se groupent pas (`group` à NULL) : une tête de groupe n'en porte jamais qu'une, et
	 * le texte d'un groupe ne s'affiche pas. Il existe pour le contrat de la paire.
	 */
	public function getGroupText(\notification\Notification $eNotificationHead): string {

		return $this->getText($eNotificationHead);

	}

	public function getActor(\notification\Notification $eNotification): string {

		return match($eNotification['type']) {
			NotificationLib::MEMBER_JOINED, NotificationLib::MEMBER_LEFT => \user\UserUi::name(new \user\User([
				'firstName' => $eNotification['content']['firstName'],
				'lastName' => $eNotification['content']['lastName']
			])),
			NotificationLib::PRODUCER_JOINED, NotificationLib::PRODUCER_LEFT => encode($eNotification['content']['structure']),
			NotificationLib::PRODUCER_EXCLUDED => encode($eNotification['content']['community'])
		};

	}

	/**
	 * La ligne grise de la planche : le rôle de la personne qui arrive, les productions de la ferme
	 * qui entre quand elle les ouvre à l'équipe du groupe. Un départ n'a plus rien à montrer.
	 */
	public function getDetails(\notification\Notification $eNotification): array {

		return match($eNotification['type']) {
			NotificationLib::MEMBER_JOINED => [encode($eNotification['content']['role'])],
			NotificationLib::PRODUCER_JOINED => ($eNotification['content']['productions'] === [])
				? []
				: [implode(', ', array_map(fn(string $fqn) => ProductionUi::get($fqn), $eNotification['content']['productions']))],
			NotificationLib::MEMBER_LEFT, NotificationLib::PRODUCER_LEFT, NotificationLib::PRODUCER_EXCLUDED => []
		};

	}

	public function getGroupDetails(\notification\Notification $eNotificationHead): array {

		return $this->getDetails($eNotificationHead);

	}

	/**
	 * La structure qui reçoit, telle que ses mots en dépendent : son type suffit, et il voyage dans le
	 * contenu pour qu'aucune relecture ne se fasse ici.
	 */
	private function getStructure(\notification\Notification $eNotification): Structure {

		return new Structure([
			'type' => $eNotification['content']['structureType'],
			'communityType' => $eNotification['content']['communityType']
		]);

	}

}
?>
