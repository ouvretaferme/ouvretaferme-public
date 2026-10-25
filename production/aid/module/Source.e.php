<?php
namespace aid;

class Source extends SourceElement {

	// Lu comme l'aide qu'il décrit, à qui est connecté (Aid::canRead()).
	public function canRead(): bool {
		return \user\ConnectionLib::isLogged();
	}

	// Seule l'équipe tient le catalogue.
	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	/**
	 * Une aide publiée ne perd pas sa dernière source officielle : l'outil la dirait encore vérifiée,
	 * sans plus rien pour le montrer. Comme son taux, elle se retire une fois l'aide dépubliée.
	 * SourceLib::delete() et update() refont la question sous verrou et disent le refus, que acceptDelete() ne fait que taire.
	 */
	public function acceptDelete(): bool {

		$this->expects(['kind']);

		if($this['kind'] !== Source::PRIMARY) {
			return TRUE;
		}

		$this->expects(['aid' => ['status']]);

		return (
			$this['aid']['status'] !== Aid::PUBLISHED or
			SourceLib::existsPrimaryOther($this)
		);

	}

	// Une source que la veille ne lit plus depuis AidSetting::WATCH_UNREAD_DAYS jours, la borne de la file (AidLib::getReviewUnreadableSql()).
	public function isUnreadable(): bool {

		$this->expects(['unreadSince']);

		return (
			$this['unreadSince'] !== NULL and
			$this['unreadSince'] <= date('Y-m-d', \Clock::shift('-'.AidSetting::WATCH_UNREAD_DAYS.' days'))
		);

	}

	// Une source redirigée dont la veille a relevé la nouvelle adresse, que l'équipe peut lui faire prendre.
	public function acceptMove(): bool {

		$this->expects(['status', 'movedUrl']);

		return ($this['status'] === Source::MOVED and $this['movedUrl'] !== NULL);

	}

	public function assertMove(): bool {
		return ($this->acceptMove() and $this->canWrite());
	}

	// Ce qui demande à l'équipe de revoir la source : une adresse changée, une page morte ou illisible.
	public function isAtIssue(): bool {

		$this->expects(['status']);

		return ($this['status'] !== Source::ALIVE or $this->isUnreadable());

	}

	public static function properties(\PropertyList $l): void {

		// Changer la nature de la dernière source officielle d'une aide publiée la lui retire autant que
		// la supprimer. L'étape tourne avant que la nouvelle nature soit posée : `kind` est encore l'ancienne.
		$l->property('kind')
			->addAfterCheck('published', function(string $kind): bool {

				if($this->exists() === FALSE or $kind === Source::PRIMARY) {
					return TRUE;
				}

				return $this->acceptDelete();

			});

		// Une adresse ne se cite qu'une fois par aide : l'outil des structures dirait autant de fois
		// qu'un relais diverge. Le semis construit les sources d'une fiche avant de les rattacher à leur
		// aide (ImportLib::buildChild()) : il n'y a alors aucune source à laquelle comparer.
		$l->property('url')
			->addAfterCheck('duplicate', function(string $url): bool {

				if($this->offsetExists('aid') === FALSE) {
					return TRUE;
				}

				return SourceLib::existsByUrl($this['aid'], $url, $this) === FALSE;

			});

	}

}
?>
