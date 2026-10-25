<?php
namespace aid;

class Aid extends AidElement {

	const HIDDEN_CLOSED = 'closed';
	const HIDDEN_SOURCE_DEAD = 'source-dead';

	const MISSING_RATE = 'rate';
	const MISSING_SOURCE = 'source';
	const MISSING_SOURCE_ALIVE = 'source-alive';

	// Pourquoi une aide est à revoir, dans l'ordre où la file de revue les range.
	const REVIEW_SOURCE = 'source';
	const REVIEW_CLOSING = 'closing';
	const REVIEW_SEED = 'seed';
	const REVIEW_CONFLICT = 'conflict';
	const REVIEW_UNDATED = 'undated';
	const REVIEW_UNREADABLE = 'unreadable';
	const REVIEW_CLOSED = 'closed';
	const REVIEW_STALE = 'stale';

	// Les deux critères « Relecture » de la liste de l'administration.
	const REVIEW_DUE = 'due';
	const REVIEW_NEVER = 'never';

	// Le catalogue ne s'ouvre qu'à qui est connecté (décidé), à la différence des référentiels
	// que tout visiteur lit (work\PhytoProduct) : la veille et la relecture qui le tiennent sont un
	// service du produit. Il ne demande aucun droit sur une structure, puisqu'il est commun à toutes ;
	// ce qu'il montre se borne par la condition d'affichage (acceptDisplay(), AidLib::applyDisplay()).
	public function canRead(): bool {
		return \user\ConnectionLib::isLogged();
	}

	// Seule l'équipe tient le catalogue.
	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	// Une aide se publie avec son taux, qui est ce qu'une structure vient y lire, sauf quand
	// l'organisme n'en publie aucun, et avec une source principale qui répond, la règle
	// d'acceptDisplay() : sinon elle serait publiée pour être aussitôt retirée de l'affichage. Les
	// éditions n'y comptent pas : une aide dont toutes sont closes se publie, cachée, et reste au
	// catalogue jusqu'à la relecture annuelle, qui la rouvre ou l'archive.
	public function acceptPublish(): bool {

		$this->expects(['status']);

		return (
			$this['status'] === Aid::DRAFT and
			$this->getPublishMissing() === []
		);

	}

	/**
	 * Ce qui manque à l'aide pour se publier, dans l'ordre où la page de l'administration le dit :
	 * son taux (que la relecture peut dire non publié par l'organisme), une source officielle, une
	 * source officielle qui répond.
	 */
	public function getPublishMissing(): array {

		$this->expects(['rate', 'rateUnpublished', 'cSource']);

		$missing = [];

		if($this['rate'] === NULL and $this['rateUnpublished'] === FALSE) {
			$missing[] = Aid::MISSING_RATE;
		}

		if($this['cSource']->find(fn(Source $eSource) => $eSource['kind'] === Source::PRIMARY)->empty()) {
			$missing[] = Aid::MISSING_SOURCE;
		} else if($this->hasSourcePrimaryAllDead()) {
			$missing[] = Aid::MISSING_SOURCE_ALIVE;
		}

		return $missing;

	}

	public function acceptUnpublish(): bool {

		$this->expects(['status']);

		return $this['status'] === Aid::PUBLISHED;

	}

	public function acceptArchive(): bool {

		$this->expects(['status']);

		return $this['status'] !== Aid::ARCHIVED;

	}

	// Une aide archivée ne se relit plus : elle n'a plus de structure devant elle.
	// Une aide dont la reprise a rapporté un écart ne se relit pas d'un « Marquer relue », qui laisserait
	// l'écart et l'aide dans la file : l'écart se tranche par « Reprendre la fiche » ou « Garder l'aide ».
	public function acceptCheck(): bool {

		$this->expects(['status', 'seedDifferences']);

		return ($this['status'] !== Aid::ARCHIVED and $this['seedDifferences'] === NULL);

	}

	/**
	 * Confirmer sur la source officielle, c'est la lire et aligner la fiche sur elle : ce que dit la
	 * confiance PRIMARY. Il faut donc une source officielle qui réponde, et une aide qu'on relit encore.
	 */
	public function acceptConfirm(): bool {

		$this->expects(['status', 'verification', 'cSource']);

		return (
			$this['status'] !== Aid::ARCHIVED and
			$this['verification'] !== Aid::PRIMARY and
			array_intersect($this->getPublishMissing(), [Aid::MISSING_SOURCE, Aid::MISSING_SOURCE_ALIVE]) === []
		);

	}

	public function assertConfirm(): bool {
		return ($this->acceptConfirm() and $this->canWrite());
	}

	/**
	 * La reprise ne réécrit qu'un brouillon que personne de l'équipe n'a relu : le statut protège ce
	 * qui est publié, la relecture ce que l'équipe a écrit, même dans une aide dépubliée le temps d'une
	 * refonte (arbitrage Q4 de L5). Sur les autres, elle rapporte un écart (ImportLib::seedCompared()).
	 */
	public function acceptSeedRewrite(): bool {

		$this->expects(['status', 'checkedBy']);

		return ($this['status'] === Aid::DRAFT and $this['checkedBy']->empty());

	}

	// Un écart rapporté par la reprise attend que l'équipe le tranche.
	public function acceptSeed(): bool {

		$this->expects(['seedDifferences']);

		return $this['seedDifferences'] !== NULL;

	}

	public function assertSeed(): bool {
		return ($this->acceptSeed() and $this->canWrite());
	}

	// Un écart qui ne porte que des lignes filles n'a rien à reprendre : elles se reprennent à la main.
	public function acceptSeedApply(): bool {
		return ($this->acceptSeed() and $this['seedDifferences']['properties'] !== []);
	}

	public function assertSeedApply(): bool {
		return ($this->acceptSeedApply() and $this->canWrite());
	}

	/**
	 * Une liste de pièces ne vaut que pour une aide que la structure dépose elle-même, par un dossier :
	 * un tiers dépose pour elle quand `direct` est faux, et une aide sans démarche (AUTOMATIC), un
	 * allègement fiscal ou social n'ont pas de dossier (règle 8 du cadrage de L6).
	 */
	public function acceptPieces(): bool {

		$this->expects(['direct', 'calendar', 'type']);

		return (
			$this['direct'] and
			$this['calendar'] !== Aid::AUTOMATIC and
			in_array($this['type'], [Aid::TAX, Aid::SOCIAL], TRUE) === FALSE
		);

	}

	public function assertPieces(): bool {
		return ($this->acceptPieces() and $this->canWrite());
	}

	/**
	 * Une aide à revoir pour sa seule liste des pièces : la relecture de la fiche n'a rien à y éteindre, la
	 * raison se lit dans la case de la liste (planche des réglages de L6, piste a). Masquée par la veille,
	 * l'aide le dit dans « Aux structures » et la file la range parmi les masquées (V-Sub, relecture de L5),
	 * mais « Marquer relue » n'y éteindrait rien de plus : la relecture suit la raison suivante.
	 */
	public function isReviewPiecesOnly(): bool {

		$this->expects(['review', 'reviewSourcePrimary']);

		return (
			$this['review'] === Aid::REVIEW_SOURCE and
			$this['reviewSourcePrimary'] === FALSE
		);

	}

	/**
	 * Des pièces saisies que la fiche tait, l'organisme ne publiant pas sa liste (AidUi::getDeposit()) :
	 * l'administration les montre encore, et dit qu'elles ne parviennent pas aux structures.
	 */
	public function isPiecesWithheld(): bool {

		$this->expects(['piecesUnpublished', 'cAidPiece']);

		return (
			$this->acceptPieces() and
			$this['piecesUnpublished'] and
			$this['cAidPiece']->notEmpty()
		);

	}

	public function assertPublish(): bool {
		return ($this->acceptPublish() and $this->canWrite());
	}

	public function assertUnpublish(): bool {
		return ($this->acceptUnpublish() and $this->canWrite());
	}

	public function assertArchive(): bool {
		return ($this->acceptArchive() and $this->canWrite());
	}

	public function assertCheck(): bool {
		return ($this->acceptCheck() and $this->canWrite());
	}

	/**
	 * Ce qui cache aux structures une aide publiée, dans l'ordre où acceptDisplay() le teste, ou NULL
	 * quand elle s'affiche ou qu'elle n'est pas publiée, son état le disant déjà. Une aide close ne
	 * reviendra pas, ce qui l'emporte sur une source morte : seule une aide ouverte est masquée par la
	 * veille (V-Sub, après le glaçon 1 de L5).
	 */
	public function getHiddenReason(): ?string {

		$this->expects(['status', 'calendar', 'cEdition', 'cSource']);

		if($this['status'] !== Aid::PUBLISHED or $this->acceptDisplay()) {
			return NULL;
		}

		return $this->isShownByCalendar() ? Aid::HIDDEN_SOURCE_DEAD : Aid::HIDDEN_CLOSED;

	}

	/**
	 * Des sources officielles qui sont toutes mortes pour la veille : la règle que getPublishMissing(),
	 * getHiddenReason() et acceptDisplay() partagent, écrite une fois. Une seule morte ne cache rien tant
	 * qu'une autre répond, puisque l'aide se lit encore sur celle-là (arbitrage Q2 b de L5).
	 */
	public function hasSourcePrimaryAllDead(): bool {

		$cSourcePrimary = $this['cSource']->find(fn(Source $eSource) => $eSource['kind'] === Source::PRIMARY);

		return (
			$cSourcePrimary->notEmpty() and
			$cSourcePrimary->find(fn(Source $eSource) => $eSource['status'] !== Source::DEAD)->empty()
		);

	}

	public function hasSourcePrimaryDead(): bool {
		return $this['cSource']->find(fn(Source $eSource) => $eSource['kind'] === Source::PRIMARY and $eSource['status'] === Source::DEAD)->notEmpty();
	}

	// La source officielle que la veille ne lit plus depuis le plus longtemps, celle qui met l'aide en file.
	public function getSourceUnreadable(): Source {

		return $this['cSource']
			->find(fn(Source $eSource) => $eSource['kind'] === Source::PRIMARY and $eSource->isUnreadable())
			->sort(['unreadSince' => SORT_ASC])
			->first() ?? new Source();

	}

	/**
	 * Ce que l'outil annonce aux structures sous la fiche : une aide lue sur sa source officielle et
	 * relue par une personne de l'équipe. La reprise date chaque aide sans que personne l'ait relue, et
	 * « Marquer relue » ne pose pas la confiance : sans l'une ou l'autre, le badge de confiance suffit.
	 * La date lue à côté vient avec son auteur, que AidLib::check() et AidLib::confirm() écrivent ensemble.
	 */
	public function isVerifiedByTeam(): bool {

		$this->expects(['verification', 'checkedBy']);

		return (
			$this['verification'] === Aid::PRIMARY and
			$this['checkedBy']->notEmpty()
		);

	}

	/**
	 * Pourquoi l'aide est à revoir, dans l'ordre de la file : une source officielle que la veille a vue
	 * déplacée ou morte d'abord, parce qu'une aide masquée cette nuit a déjà disparu pour les structures,
	 * puis la clôture proche, qui n'attend pas, l'écart d'une reprise, les sources divergentes jamais
	 * relues, l'appel ouvert sans date de fin, qui laisse la structure sans échéance, une source
	 * officielle que la veille ne lit plus, que la relecture de six mois suit déjà, l'appel clos depuis
	 * un an, et l'ancienneté. Une aide
	 * archivée ne se revoit plus. La règle s'écrit en SQL dans AidLib::getSelectionReview(), que la liste
	 * et la file posent en filtre.
	 */
	public function getReviewReason(): ?string {

		$this->expects(['status', 'reviewSource', 'reviewClosing', 'reviewSeed', 'reviewConflict', 'reviewUndated', 'reviewUnreadable', 'reviewClosed', 'reviewStale']);

		if($this['status'] === Aid::ARCHIVED) {
			return NULL;
		}

		if($this['reviewSource']) {
			return Aid::REVIEW_SOURCE;
		}

		return $this->getReviewReasonAfterSource();

	}

	/**
	 * La raison que « Marquer relue » éteint sur la fiche : celle de la file, sauf une liste des pièces seule
	 * à revoir, qui a sa case et son geste (isReviewPiecesOnly()) et laisse passer la raison suivante. Sans
	 * quoi une aide trop ancienne dont la liste ne répond plus se dirait relue.
	 */
	public function getCheckReason(): ?string {

		if($this->isReviewPiecesOnly()) {
			return $this->getReviewReasonAfterSource();
		}

		return $this['review'];

	}

	// Ce que « Marquer relue » éteint sur la fiche : une raison de la relire, ou une fiche que personne n'a
	// jamais relue. Une liste seule à revoir n'y est pas due, son geste est dans sa case (V-Sub, sur L6).
	public function isCheckDue(): bool {

		$this->expects(['reviewNever']);

		return ($this->getCheckReason() !== NULL or $this['reviewNever']);

	}

	// Les raisons de getReviewReason() qui suivent la source, dans le même ordre.
	private function getReviewReasonAfterSource(): ?string {

		$this->expects(['reviewClosing', 'reviewSeed', 'reviewConflict', 'reviewUndated', 'reviewUnreadable', 'reviewClosed', 'reviewStale']);

		if($this['reviewClosing']) {
			return Aid::REVIEW_CLOSING;
		}

		if($this['reviewSeed']) {
			return Aid::REVIEW_SEED;
		}

		if($this['reviewConflict']) {
			return Aid::REVIEW_CONFLICT;
		}

		if($this['reviewUndated']) {
			return Aid::REVIEW_UNDATED;
		}

		if($this['reviewUnreadable']) {
			return Aid::REVIEW_UNREADABLE;
		}

		if($this['reviewClosed']) {
			return Aid::REVIEW_CLOSED;
		}

		if($this['reviewStale']) {
			return Aid::REVIEW_STALE;
		}

		return NULL;

	}

	/**
	 * La condition d'affichage, sur une aide chargée avec ses éditions et ses sources. C'est la même
	 * règle que AidLib::applyDisplay() sur une requête, et les deux se lisent ensemble : une aide
	 * publiée, dont une édition ouverte ou à venir n'est pas encore close par sa date, ou permanente sans
	 * aucune édition qui la borne, ou annuelle, ou sans démarche, et dont les sources principales ne
	 * sont pas toutes mortes. Une aide sans démarche s'obtient sans dépôt, donc aucune édition ne la
	 * ferme. Une aide annuelle revient chaque année : entre deux campagnes, elle s'affiche sans dépôt
	 * possible (`isBetweenCampaigns`, AidLib::fill()), alors qu'un appel clos ne revient pas.
	 */
	public function acceptDisplay(): bool {

		$this->expects(['status', 'calendar', 'cEdition', 'cSource']);

		if($this['status'] !== Aid::PUBLISHED) {
			return FALSE;
		}

		return ($this->isShownByCalendar() and $this->hasSourcePrimaryAllDead() === FALSE);

	}

	// La moitié de la condition d'affichage qui tient au calendrier : une aide qui n'y répond plus est close.
	public function isShownByCalendar(): bool {

		$this->expects(['calendar', 'cEdition']);

		return (
			($this['calendar'] === Aid::PERMANENT and $this['cEdition']->empty()) or
			in_array($this['calendar'], [Aid::CAMPAIGN, Aid::AUTOMATIC], TRUE) or
			$this->hasEditionOpenOrUpcoming()
		);

	}

	/**
	 * Une édition pas encore close par sa date, ouverte ou à venir, comme AidLib::applyEditionOpenOrUpcoming()
	 * le pose sur une requête : la structure prépare son dossier avant l'ouverture du dépôt.
	 */
	private function hasEditionOpenOrUpcoming(): bool {

		return $this['cEdition']->find(fn(Edition $eEdition) => $eEdition->isOpenOrUpcoming())->notEmpty();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('productions')
			->addAfterCheck('unknown', fn(?array $productions): bool => (
				$productions === NULL or
				array_diff($productions, \structure\Production::getAll()) === []
			));

		// Une aide publiée garde son taux, que acceptPublish() a exigé, ou la mention que l'organisme n'en
		// publie aucun : c'est ce qu'une structure vient lire. Les deux valeurs se jugent ensemble, celles
		// qu'on vient de poster, d'où l'étape sur rateUnpublished, que la liste de l'équipe construit après
		// rate (AidLib::getPropertiesCreate()). La règle tient la main de l'équipe, en for 'update' ; le
		// semis ne construit pas rateUnpublished et repasse lui-même en brouillon une aide qui perd son
		// taux (ImportLib::seedAid()).
		$l->property('rateUnpublished')
			->addAfterCheck('published', function(bool $rateUnpublished, \PropertyList $l): bool {

				if($l->for !== 'update') {
					return TRUE;
				}

				$this->expects(['status', 'rate']);

				return ($this['status'] !== Aid::PUBLISHED or $this['rate'] !== NULL or $rateUnpublished);

			})
			// Un taux saisi dément la mention que l'organisme n'en publie aucun.
			->addAfterCheck('rate', function(bool $rateUnpublished): bool {

				$this->expects(['rate']);

				return ($rateUnpublished === FALSE or $this['rate'] === NULL);

			});

		// Un montant plafond ne dit rien sans son unité : 5 000 € et 5 000 € à l'hectare sont deux aides.
		$l->property('amountUnit')
			->required(fn(\PropertyList $l): bool => $this['amountMax'] !== NULL);

		$l->property('parent')
			->addAfterCheck('self', fn(Aid $eAidParent): bool => (
				$eAidParent->empty() or
				$this->exists() === FALSE or
				$eAidParent['id'] !== $this['id']
			));

	}

}
?>
