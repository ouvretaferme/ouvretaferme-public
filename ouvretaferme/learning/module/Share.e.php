<?php
namespace learning;

/**
 * Une formation de groupe ouverte à une promotion ou aux membres d'un réseau.
 *
 * `audience` dit à qui. Une promotion d'un groupe de formation (`SECTION`, la promotion dans
 * `section`) lit la formation d'une date d'ouverture à une échéance facultative. Les membres d'un
 * réseau (`MEMBERS`, une ligne au plus par formation) n'en reçoivent aucune lecture, qui vient de
 * l'inscription : l'ouverture les prévient par e-mail et pose la formation dans l'onglet du réseau
 * et dans « Mes formations », et sa période ne se lit pas. `section` ne se construit jamais : le
 * Lib la pose d'après l'audience.
 *
 * Le partage n'a pas de droit propre : il s'écrit depuis sa formation, que `Course::acceptShare()`
 * garde, et se lit par `ShareLib`, qui ne rend jamais que les partages du compte connecté ou d'une
 * formation qu'il écrit.
 */
class Share extends ShareElement {

	public function canRead(): bool {
		return TRUE;
	}

	public static function getSelection(): array {

		return parent::getSelection() + [
			'section' => ['name', 'structure', 'status', 'users'],
		];

	}

	/**
	 * Ce qui distingue deux partages d'une même formation, et permet de comparer ceux qu'elle porte
	 * à ceux qu'on lui poste : la promotion, ou l'audience seule pour les membres d'un réseau.
	 */
	public function getKey(): string {

		$this->expects(['audience']);

		return match($this['audience']) {
			Share::SECTION => 'section-'.$this['section']['id'],
			Share::MEMBERS => 'members'
		};

	}

	/**
	 * La formation se lit à partir du jour d'ouverture, ce jour compris.
	 */
	public function isOpen(): bool {

		$this->expects(['opensAt']);

		return ($this['opensAt'] <= \Clock::date());

	}

	/**
	 * Après l'échéance, la formation se relit mais ne se coche plus : un état de la fenêtre, qui ne
	 * regarde pas le lecteur. Le jour de l'échéance compte encore.
	 */
	public function acceptProgress(): bool {

		$this->expects(['dueAt']);

		return (
			$this['dueAt'] === NULL or
			$this['dueAt'] >= \Clock::date()
		);

	}

	public static function properties(\PropertyList $l): void {

		// Seul le panneau d'un réseau construit l'audience, et n'en a qu'une à poster : la promotion
		// d'un groupe de formation ne se poste pas, `ShareLib::getNewElementForSection()` la pose, et
		// « Personne pour l'instant » s'écarte avant `build()` (`ShareLib::buildFromInputNetwork()`).
		$l->property('audience')
			->addAfterCheck('chosen', fn(string $audience): bool => $audience === Share::MEMBERS);

		// `build()` suit l'ordre de la liste qu'on lui passe, et `ShareLib::buildFromInput()` pose
		// `opensAt` avant `dueAt` : l'échéance se juge sur l'ouverture postée. Un partage neuf porte
		// d'office l'ouverture du jour (`ShareLib::getNewElementForSection()`), celle que le panneau
		// préremplit.
		$l->property('dueAt')
			->addAfterCheck('consistent', fn(?string $dueAt): bool => (
				$dueAt === NULL or
				$dueAt >= $this['opensAt']
			));

	}

}
?>
