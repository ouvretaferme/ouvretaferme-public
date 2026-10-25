<?php
namespace aid;

/**
 * Le catalogue des aides dans l'administration : la liste filtrée, puis la page d'une aide, sa fiche
 * en tête et ses listes filles dessous, une par titre.
 */
class AdminUi {

	public function __construct() {
		\Asset::css('aid', 'admin.css');
		\Asset::js('aid', 'admin.js');
	}

	public function getSearch(\Search $search, int $nAid): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/aid/admin/', ['method' => 'get', 'class' => 'util-search']);
			$h .= '<fieldset>';
				$h .= $form->select('status', AidUi::p('status')->values, $search->get('status'), ['placeholder' => s("Tous les états")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->select('level', AidUi::p('level')->values, $search->get('level'), ['placeholder' => s("Tous les échelons")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->select('verification', AidUi::p('verification')->values, $search->get('verification'), ['placeholder' => s("Toute vérification")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->select('review', [
					Aid::REVIEW_DUE => s("À revoir"),
					Aid::REVIEW_NEVER => s("Jamais relue"),
				], $search->get('review'), ['placeholder' => s("Toute relecture")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->select('region', AidSetting::REGIONS, $search->get('region'), ['placeholder' => s("Toutes les régions")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->select('department', AreaUi::getDepartments(), $search->get('department'), ['placeholder' => s("Tous les départements")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->select('project', TagUi::p('projects')->values, $search->get('project'), ['placeholder' => s("Tous les projets")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->text('organization', $search->get('organization'), ['placeholder' => s("Organisme")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->text('text', $search->get('text'), ['placeholder' => s("Nom ou mot-clé")]);
			$h .= '</fieldset>';

			if($search->get('closed')) {
				$h .= $form->hidden('closed', 1);
			}

			$h .= '<div class="util-search-submit">';
				$h .= $form->submit(s("Chercher"));
				if($search->notEmpty()) {
					$h .= ' <a href="/aid/admin/" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';
				}
				$h .= '<span class="badge badge-solid bg-primary">'.p("{value} aide", "{value} aides", $nAid).'</span>';
			$h .= '</div>';
		$h .= $form->close();

		return $h;

	}

	// La liste de tout le catalogue, la file de ce qui est à revoir, et la couverture des listes officielles.
	public function getTabs(string $selected, int $nReview): string {

		$tabs = '<a href="/aid/admin/" class="tab-item'.($selected === 'index' ? ' selected' : '').'">'.s("Aides").'</a>';
		$tabs .= '<a href="/aid/admin/:review" class="tab-item'.($selected === 'review' ? ' selected' : '').'">'.s("À revoir").' <span class="util-counter">'.$nReview.'</span></a>';
		$tabs .= '<a href="/aid/admin/:coverage" class="tab-item'.($selected === 'coverage' ? ' selected' : '').'">'.s("Couverture").'</a>';

		return \main\DesignUi::getPageTabs($tabs);

	}

	/**
	 * La file de revue à gauche, l'aide ouverte à droite, avec sa fiche et l'écart de la reprise. Une
	 * ligne ouvre son aide sans quitter la file. Sous 992 px la colonne de côté tomberait sous toute la
	 * file : la ligne y ouvre la page de l'aide, qui ramène à la file, et la colonne ne s'affiche pas.
	 * Chaque aide a donc deux lignes, une par largeur, la ligne entière étant le lien.
	 */
	public function getReview(\Collection $cAid, int $nFound, int $page, ?string $reason, Aid $eAidSelected): string {

		$h = '';

		$all = '<a href="/aid/admin/:review" class="btn btn-outline-primary">'.s("Toute la file").'</a>';

		// Une file filtrée vide garde le chemin vers toute la file à côté de son constat, pas au-dessus.
		if($reason !== NULL) {

			$h .= '<div class="util-title">';
				$h .= '<h2>'.$this->getReviewReasonWatch($reason).' <span class="util-counter">'.$nFound.'</span></h2>';
				if($cAid->notEmpty()) {
					$h .= '<div>'.$all.'</div>';
				}
			$h .= '</div>';

		}

		if($cAid->empty()) {

			if($reason === NULL) {
				return $h.'<div class="util-empty">'.s("Aucune aide n'est à revoir.").'</div>';
			} else {
				return $h.'<div class="util-empty">'.s("Aucune aide n'est à revoir pour cette raison.").' '.$all.'</div>';
			}

		}

		$query = ($reason === NULL) ? '' : '&reason='.$reason;

		$h .= '<div class="util-layout-aside">';

			$h .= '<div>';

				$h .= '<div class="util-table util-table-hover aid-table-review">';

					$h .= '<div class="util-table-head">';
						$h .= '<div>'.s("Aide").'</div>';
						$h .= '<div>'.s("À revoir").'</div>';
						$h .= '<div class="hide-sm-down">'.s("Vérification").'</div>';
					$h .= '</div>';

					foreach($cAid as $eAid) {

						$isSelected = ($eAidSelected->notEmpty() and $eAidSelected['id'] === $eAid['id']);

						$cells = '<div>';
							$cells .= '<b>'.encode($eAid['name']).'</b>';
							$cells .= '<div class="util-annotation">'.encode($eAid['organization']['name']).'</div>';
						$cells .= '</div>';
						$cells .= '<div>'.$this->getReviewReason($eAid).'</div>';
						$cells .= '<div class="hide-sm-down">'.$this->getVerification($eAid).'</div>';

						$h .= '<a href="/aid/admin/:review?id='.$eAid['id'].($page > 0 ? '&page='.$page : '').$query.'" class="util-table-row hide-md-down'.($isSelected ? ' util-tr-group-selected' : '').'">'.$cells.'</a>';
						$h .= '<a href="/aid/admin/aid?id='.$eAid['id'].'&origin=review&page='.$page.$query.'" class="util-table-row hide-lg-up">'.$cells.'</a>';

					}

				$h .= '</div>';

				$h .= \util\TextUi::pagination($page, $nFound / AidSetting::PER_ADMIN);

			$h .= '</div>';

			$h .= '<div class="util-layout-aside-side hide-md-down">';

				if($eAidSelected->notEmpty()) {
					$h .= $this->getReviewAid($eAidSelected);
					$h .= $this->getSeedDifferences($eAidSelected);
				} else {
					$h .= '<div class="util-empty">'.s("Aucune autre aide à revoir sur cette page.").'</div>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	// Ce qu'un geste de relecture d'une aide ouverte depuis la file poste pour y revenir, sur l'aide
	// suivante (AidLib::getReviewQueue()) ; rien hors de la file, où la page de l'aide se recharge.
	protected function getQueuePost(Aid $eAid): string {

		if($eAid['queue'] === NULL) {
			return '';
		}

		$h = ' post-origin="review" post-view="'.$eAid['queue']['view'].'" post-page="'.$eAid['queue']['page'].'"';

		if($eAid['queue']['reason'] !== NULL) {
			$h .= ' post-reason="'.$eAid['queue']['reason'].'"';
		}

		if($eAid['queue']['next'] !== NULL) {
			$h .= ' post-next="'.$eAid['queue']['next'].'"';
		}

		return $h;

	}

	// La raison qui restreint la file voyage sur chaque lien entre la file et la page de l'aide : sans elle,
	// l'aide suivante et le retour sortent du filtre.
	protected function getQueueReason(Aid $eAid): string {
		return ($eAid['queue']['reason'] === NULL) ? '' : '&reason='.$eAid['queue']['reason'];
	}

	// La raison d'une ligne de la file, en quelques mots : la case Relecture de la fiche la dit en entier.
	protected function getReviewReason(Aid $eAid): string {

		return match($eAid['review']) {
			Aid::REVIEW_CLOSING => s("Clôture le {value}", \util\DateUi::numeric($eAid['endDate'], \util\DateUi::DATE)),
			Aid::REVIEW_SOURCE => $this->getReviewSource($eAid, s("Ne répond plus, masquée"), s("Ne répond plus"), s("Une source ne répond plus"), s("A changé d'adresse"), s("Liste des pièces à revoir")),
			Aid::REVIEW_SEED => s("Écart avec la fiche"),
			Aid::REVIEW_CONFLICT => s("Divergence jamais relue"),
			Aid::REVIEW_UNREADABLE => $this->getReviewUnreadable($eAid),
			Aid::REVIEW_CLOSED => s("Close depuis le {value}", \util\DateUi::numeric($eAid['endDateLast'], \util\DateUi::DATE)),
			Aid::REVIEW_UNDATED => s("Sans date de fin"),
			Aid::REVIEW_STALE => $eAid['reviewNever'] ? s("Jamais relue") : s("Relue le {value}", \util\DateUi::numeric($eAid['checkedAt'], \util\DateUi::DATE)),
		};

	}

	// Une source à revoir, la plus grave d'abord : morte au point de masquer l'aide, toutes mortes, une
	// morte, déplacée. Une aide close n'est pas masquée par la veille, même quand ses sources sont toutes
	// mortes (Aid::getHiddenReason()). Une aide masquée se dit masquée, comme le chiffre la compte
	// (AidLib::applyReviewReason()), même quand seule sa liste des pièces la met en file (V-Sub, relecture
	// de L5). Sinon, la page de la liste des pièces ne se dit que lorsqu'elle met seule l'aide en file :
	// elle pèse moins qu'une source officielle (AidLib::getReviewSourcePiecesSql()).
	protected function getReviewSource(Aid $eAid, string $hidden, string $deadAll, string $dead, string $moved, string $pieces): string {

		if($eAid['hidden'] === Aid::HIDDEN_SOURCE_DEAD) {
			return $hidden;
		} else if($eAid['reviewSourcePrimary'] === FALSE) {
			return $pieces;
		} else if($eAid->hasSourcePrimaryAllDead()) {
			return $deadAll;
		} else if($eAid->hasSourcePrimaryDead()) {
			return $dead;
		} else {
			return $moved;
		}

	}

	protected function getReviewUnreadable(Aid $eAid): string {

		$eSource = $eAid->getSourceUnreadable();

		if($eSource['checkedAt'] === NULL) {
			return s("Illisible, jamais lue");
		} else {
			return s("Illisible, lue le {value}", \util\DateUi::numeric($eSource['checkedAt'], \util\DateUi::DATE));
		}

	}

	// Le titre de la file restreinte à une raison de la veille (AidLib::getReviewReasonsWatch()).
	protected function getReviewReasonWatch(string $reason): string {

		return match($reason) {
			Aid::HIDDEN_SOURCE_DEAD => s("Aides masquées par la veille"),
			Aid::REVIEW_SOURCE => s("Sources à revoir"),
			Aid::REVIEW_UNREADABLE => s("Sources illisibles"),
		};

	}

	/**
	 * Ce que la veille a vu, en tête de la file : trois chiffres, chacun vers la file restreinte à sa
	 * raison, puis les pages changées que le mois de mesure compte sans les y faire entrer.
	 */
	public function getReviewFigures(array $figures): string {

		$labels = [
			Aid::HIDDEN_SOURCE_DEAD => p("Aide masquée par la veille", "Aides masquées par la veille", $figures[Aid::HIDDEN_SOURCE_DEAD]),
			Aid::REVIEW_SOURCE => p("Aide dont une source a changé d'adresse ou ne répond plus", "Aides dont une source a changé d'adresse ou ne répond plus", $figures[Aid::REVIEW_SOURCE]),
			Aid::REVIEW_UNREADABLE => p("Aide que la veille n'a pas pu lire depuis {days} nuits", "Aides que la veille n'a pas pu lire depuis {days} nuits", $figures[Aid::REVIEW_UNREADABLE], ['days' => AidSetting::WATCH_UNREAD_DAYS]),
		];

		if($figures['watched'] === FALSE) {
			$annotation = s("La veille n'a encore lu aucune source.");
		} else if($figures['changed'] > 0) {
			$annotation = p(
				"{value} page a changé depuis sa première lecture ({aids}), comptée sans entrer dans la file.",
				"{value} pages ont changé depuis leur première lecture ({aids}), comptées sans entrer dans la file.",
				$figures['changed'],
				['aids' => p("{value} aide", "{value} aides", $figures['changedAids'])]
			);
		} else {
			$annotation = NULL;
		}

		$h = '<div class="util-figures util-figures-3 '.($annotation === NULL ? 'mb-2' : 'mb-1').'">';

			foreach($labels as $reason => $label) {
				$h .= '<a href="/aid/admin/:review?reason='.$reason.'" class="util-figure"><div class="util-figure-value">'.$figures[$reason].'</div><div class="util-figure-label">'.$label.'</div></a>';
			}

		$h .= '</div>';

		if($annotation !== NULL) {
			$h .= '<p class="util-annotation mb-2">'.$annotation.'</p>';
		}

		return $h;

	}

	/**
	 * Ce que la reprise rapporte d'une aide relue sans la réécrire : pour chaque propriété, la valeur de
	 * l'aide et celle de la fiche, et pour chaque liste ce que l'aide a seule et ce que la fiche a seule.
	 * Le texte vient de sites tiers : tout s'échappe, et les conditions passent par l'éditeur.
	 */
	protected function getSeedDifferences(Aid $eAid): string {

		if($eAid['seedDifferences'] === NULL) {
			return '';
		}

		$actions = '';

		if($eAid->assertSeed()) {

			if($eAid->acceptSeedApply()) {
				$actions .= '<a data-ajax="/aid/admin/aid:doSeedApply" post-id="'.$eAid['id'].'" post-seed="'.$eAid['seedFingerprint'].'"'.$this->getQueuePost($eAid).' class="btn btn-outline-primary" data-confirm="'.s("Les valeurs de la fiche remplaceront celles de l'aide. Les listes se reprennent à la main. Continuer ?").'">'.s("Reprendre la fiche").'</a> ';
			}

			$actions .= '<a data-ajax="/aid/admin/aid:doSeedKeep" post-id="'.$eAid['id'].'" post-seed="'.$eAid['seedFingerprint'].'"'.$this->getQueuePost($eAid).' class="btn btn-outline-primary">'.s("Garder l'aide").'</a>';

		}

		$h = '<h3>'.s("Écart avec la fiche de recherche").'</h3>';

		$h .= '<div class="util-table aid-table-seed mb-2">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Sur l'aide").'</div>';
				$h .= '<div>'.s("Dans la fiche").'</div>';
			$h .= '</div>';

			// La fiche retient l'aide avant publication, alors que l'équipe l'a publiée ou relue : la reprise
			// ne réécrit ni ne dépublie, l'équipe tranche (arbitrages Q4 et point 4 de L5).
			if($eAid['seedHeld']) {

				$h .= '<div class="util-table-title">'.s("Mention de la fiche").'</div>';
				$h .= '<div class="util-table-row">';
					$h .= '<div>'.AidUi::p('status')->values[$eAid['status']].'</div>';
					$h .= '<div>'.s("À vérifier avant toute publication").'</div>';
				$h .= '</div>';

			}

			foreach($eAid['seedProperties'] as $property => $value) {

				$h .= '<div class="util-table-title">'.$this->getSeedRow($property)['label'].'</div>';
				$h .= '<div class="util-table-row">';
					$h .= '<div>'.$this->getSeedValue($eAid, $property, $eAid[$property]).'</div>';
					$h .= '<div>'.$this->getSeedValue($eAid, $property, $value).'</div>';
				$h .= '</div>';

			}

			foreach($eAid['seedChildren'] as $module => $sides) {

				$h .= '<div class="util-table-title">'.$this->getSeedRow($module)['label'].'</div>';
				$h .= '<div class="util-table-row">';
					$h .= '<div>'.$this->getSeedChildren($eAid, $module, $sides['aid'], $eAid['seedNational']['aid']).'</div>';
					$h .= '<div>'.$this->getSeedChildren($eAid, $module, $sides['seed'], $eAid['seedNational']['seed']).'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		// On lit l'écart, puis on le tranche : les gestes viennent sous le tableau.
		if($actions !== '') {
			$h .= '<div class="flex-buttons mb-2">'.$actions.'</div>';
		}

		return $h;

	}

	// Une valeur de l'aide, ou celle que la fiche donne en entrée : un identifiant pour un organisme,
	// un tableau pour un ensemble.
	protected function getSeedValue(Aid $eAid, string $property, mixed $value): string {

		if($value instanceof \Set) {
			$value = $value->values();
		}

		if($value === NULL or $value === []) {
			return '<span class="util-empty">'.$this->getSeedRow($property)['empty'].'</span>';
		}

		return match($property) {
			'organization' => ($value instanceof Organization) ? encode($value['name']) : $this->getSeedName($eAid['cOrganizationSeed'], $value),
			'conditions' => new \editor\EditorUi()->value($value),
			'level', 'type', 'calendar' => AidUi::p($property)->values[$value],
			'labels', 'labelsBonus', 'beneficiaries' => implode(', ', array_map(fn($item) => AidUi::p($property)->values[$item], $value)),
			'productions' => encode(implode(', ', $value)),
			'direct' => $value ? s("Oui") : s("Non"),
			'ratePercentMax' => s("{value} %", \util\TextUi::number($value)),
			'floor', 'ceiling' => s("{value} €", \util\TextUi::number($value)),
			'name', 'rate', 'notes' => nl2br(encode($value)),
		};

	}

	// L'écart garde l'identifiant d'un organisme ou d'un tag qu'aucune clé étrangère ne retient : l'équipe
	// a pu le supprimer depuis la reprise.
	protected function getSeedName(\Collection $cElement, int $id): string {

		if($cElement->offsetExists($id)) {
			return encode($cElement[$id]['name']);
		}

		return s("Supprimé depuis la reprise");

	}

	protected function getSeedEdition(array $key): string {

		$state = mb_strtolower(EditionUi::p('state')->values[$key['state']]);

		if($key['startDate'] !== NULL and $key['endDate'] !== NULL) {
			return s("Du {start} au {end}, {state}", ['start' => $this->getDate($key['startDate']), 'end' => $this->getDate($key['endDate']), 'state' => $state]);
		} else if($key['endDate'] !== NULL) {
			return s("Jusqu'au {end}, {state}", ['end' => $this->getDate($key['endDate']), 'state' => $state]);
		} else if($key['startDate'] !== NULL) {
			return s("À partir du {start}, {state}", ['start' => $this->getDate($key['startDate']), 'state' => $state]);
		} else {
			return s("Sans date, {state}", ['state' => $state]);
		}

	}

	/**
	 * Chaque rangée de l'écart, propriété de l'aide ou liste fille, avec son libellé et ce qu'elle dit
	 * quand elle est vide, accordé au libellé. Le nom et la case de dépôt ne sont jamais vides.
	 */
	protected function getSeedRow(string $name): array {

		[$label, $empty] = match($name) {
			'name', 'direct', 'organization', 'level', 'calendar', 'rate', 'ratePercentMax', 'floor', 'ceiling', 'labels', 'labelsBonus', 'beneficiaries' => [AidUi::p($name)->label, s("Aucun")],
			'conditions', 'type', 'productions', 'notes' => [AidUi::p($name)->label, s("Aucune")],
			'Area' => [s("Départements"), s("Aucun")],
			'Edition' => [s("Éditions"), s("Aucune")],
			'Source' => [s("Sources"), s("Aucune")],
			'AidOrganization' => [s("Organismes"), s("Aucun")],
			'AidTag' => [s("Tags"), s("Aucun")],
		};

		return ['label' => $label, 'empty' => $empty];

	}

	// Un côté d'une liste fille en entier (AidLib::fillSeed()) : ce qui diffère de l'autre côté reste à
	// l'encre, ce qui est commun aux deux passe en retrait. Un côté sans département sur une aide
	// nationale ou PAC vaut dans toute la France.
	protected function getSeedChildren(Aid $eAid, string $module, array $lines, bool $isNational): string {

		if($lines === []) {
			return '<span class="util-empty">'.(($module === 'Area' and $isNational) ? s("Aucun, l'aide est nationale") : $this->getSeedRow($module)['empty']).'</span>';
		}

		$items = array_map(function(array $line) use ($eAid, $module) {

			$item = $this->getSeedChild($eAid, $module, $line['key'], $line['changed']);

			return $line['changed'] ? $item : '<span class="color-muted">'.$item.'</span>';

		}, $lines);

		return implode('<br/>', $items);

	}

	// Le badge d'un département commun s'éteint avec sa ligne : il porte sa propre couleur, que le
	// retrait de la ligne n'atteint pas.
	protected function getSeedChild(Aid $eAid, string $module, array $key, bool $changed): string {

		return match($module) {
			'Area' => AreaUi::getDepartmentBadge($key['department'], $changed === FALSE),
			'Edition' => $this->getSeedEdition($key),
			'Source' => encode($key['url']).' <span class="color-muted">'.SourceUi::p('kind')->values[$key['kind']].'</span>',
			'AidOrganization' => s("{organization}, {role}", [
				'organization' => $this->getSeedName($eAid['cOrganizationSeed'], $key['organization']),
				'role' => mb_strtolower(AidOrganizationUi::p('role')->values[$key['role']]),
			]),
			'AidTag' => $this->getSeedName($eAid['cTagSeed'], $key['tag']),
		};

	}

	/**
	 * Les trois compteurs de ce qui demande une relecture, chacun vers la liste qu'il annonce : la
	 * recherche posée, avec son critère à la place de celui de la vérification.
	 */
	public function getFigures(\Search $search, array $figures): string {

		$query = $search->toQuery(['verification', 'closed'], '&');

		return \main\DesignUi::getFigures([
			\main\DesignUi::getFigure($figures['conflict'], p("Aide aux sources divergentes", "Aides aux sources divergentes", $figures['conflict']), '/aid/admin/?verification='.Aid::CONFLICT.$query),
			\main\DesignUi::getFigure($figures['relay'], p("Aide lue sur un relais seulement", "Aides lues sur un relais seulement", $figures['relay']), '/aid/admin/?verification='.Aid::RELAY.$query),
			\main\DesignUi::getFigure($figures['closed'], p("Aide dont la dernière édition est close", "Aides dont la dernière édition est close", $figures['closed']), '/aid/admin/?closed=1'.$query),
		], 'util-figures-3 mb-2');

	}

	public function getList(\Collection $cAid, int $nAid, int $page): string {

		$h = '<div class="util-title">';
			$h .= '<h2>'.s("Aides").'</h2>';
			$h .= '<div>';
				$h .= '<a href="/aid/admin/aid:create" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Nouvelle aide").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		if($cAid->empty()) {
			$h .= '<div class="util-empty">'.s("Aucune aide ne répond à la recherche.").'</div>';
			return $h;
		}

		$h .= '<div class="util-table util-table-hover aid-table-list">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Aide").'</div>';
				$h .= '<div class="hide-md-down">'.s("Échelon").'</div>';
				$h .= '<div class="hide-md-down">'.s("Forme").'</div>';
				$h .= '<div class="hide-sm-down">'.s("Calendrier").'</div>';
				$h .= '<div class="hide-sm-down">'.s("Vérification").'</div>';
				$h .= '<div>'.s("État").'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($cAid as $eAid) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						$h .= '<a href="/aid/admin/aid?id='.$eAid['id'].'"><b>'.encode($eAid['name']).'</b></a>';
						$h .= '<div class="util-annotation">'.$this->getOrigin($eAid).'</div>';
					$h .= '</div>';
					$h .= '<div class="hide-md-down">'.AidUi::p('level')->values[$eAid['level']].'</div>';
					$h .= '<div class="hide-md-down">'.AidUi::p('type')->values[$eAid['type']].'</div>';
					$h .= '<div class="hide-sm-down">'.$this->getCalendar($eAid).'</div>';
					$h .= '<div class="hide-sm-down">'.$this->getVerification($eAid).'</div>';
					$h .= '<div>'.$this->getStatus($eAid).$this->getHiddenBySource($eAid).'</div>';
					$h .= '<div>'.$this->getAidGear($eAid).'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= \util\TextUi::pagination($page, $nAid / AidSetting::PER_ADMIN);

		return $h;

	}

	// L'organisme qui accorde l'aide, et le dispositif dont elle est un volet.
	protected function getOrigin(Aid $eAid): string {

		$h = encode($eAid['organization']['name']);

		if($eAid['parent']->notEmpty()) {
			$h .= ' · '.s("volet de {value}", '<a href="/aid/admin/aid?id='.$eAid['parent']['id'].'">'.encode($eAid['parent']['name']).'</a>');
		}

		return $h;

	}

	protected function getCalendar(Aid $eAid): string {

		// Une édition à venir se date par son ouverture, comme sur la carte des structures (AidUi::getDeadlineAside()).
		if($eAid['eEditionUpcoming']->notEmpty()) {
			return AidUi::getUpcoming($eAid['eEditionUpcoming']);
		}

		if($eAid['endDate'] === NULL) {
			return AidUi::p('calendar')->values[$eAid['calendar']];
		}

		$date = \util\DateUi::numeric($eAid['endDate'], \util\DateUi::DATE);

		return match($eAid['hidden']) {
			Aid::HIDDEN_CLOSED => s("Close le {value}", $date),
			NULL, Aid::HIDDEN_SOURCE_DEAD => s("Jusqu'au {value}", $date),
		};

	}

	protected function getStatus(Aid $eAid): string {

		$class = match($eAid['status']) {
			Aid::DRAFT => 'badge-draft',
			Aid::PUBLISHED => 'badge-success',
			Aid::ARCHIVED => 'badge-off',
		};

		return '<span class="badge '.$class.'">'.AidUi::p('status')->values[$eAid['status']].'</span>';

	}

	// Une aide relue sort de la file sans revenir aux structures : la liste la marque tant que sa source
	// officielle reste morte (arbitrage de V-Sub sur le glaçon 1 de L5).
	protected function getHiddenBySource(Aid $eAid): string {

		if($eAid['hidden'] === Aid::HIDDEN_SOURCE_DEAD) {
			return ' <span class="badge badge-warning">'.s("Masquée par la veille").'</span>';
		}

		return '';

	}

	// La confiance dans le contenu, sans date : la date de relecture et ce qui presse sont dans la case
	// Relecture de la fiche.
	protected function getVerification(Aid $eAid): string {

		$label = AidUi::p('verification')->values[$eAid['verification']];

		$class = match($eAid['verification']) {
			Aid::PRIMARY => 'badge-success',
			Aid::RELAY, Aid::TO_CHECK => 'badge-info',
			Aid::CONFLICT => 'badge-warning',
		};

		return '<span class="badge '.$class.' badge-dot">'.$label.'</span>';

	}

	protected function getAidGear(Aid $eAid): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.encode($eAid['name']).'</div>';
			$h .= '<a href="/aid/admin/aid:update?id='.$eAid['id'].'" class="dropdown-item">'.s("Modifier l'aide").'</a>';

			if($eAid->assertPublish()) {
				$h .= '<a data-ajax="/aid/admin/aid:doPublish" post-id="'.$eAid['id'].'" class="dropdown-item"'.$this->getPublishConfirm($eAid).'>'.s("Publier").'</a>';
			} else if($eAid->assertUnpublish()) {
				$h .= $this->getUnpublishItem($eAid);
			}

			if($eAid->assertArchive()) {
				$h .= '<div class="dropdown-divider"></div>';
				$h .= $this->getArchiveItem($eAid);
			}

		$h .= '</div>';

		return $h;

	}

	// Une aide aux sources divergentes se publie sur un avertissement : ce qu'elle dit n'est pas encore
	// tranché par la source officielle (« Confirmer sur la source officielle »).
	protected function getPublishConfirm(Aid $eAid): string {

		if($eAid['verification'] === Aid::CONFLICT) {
			return ' data-confirm="'.s("Les sources de cette aide divergent. Publier quand même ?").'"';
		}

		return '';

	}

	// Les deux gestes que partagent le menu d'une ligne de la liste et celui de la page de l'aide.
	protected function getUnpublishItem(Aid $eAid): string {
		return '<a data-ajax="/aid/admin/aid:doUnpublish" post-id="'.$eAid['id'].'" class="dropdown-item" data-confirm="'.s("Les structures ne verront plus cette aide. Continuer ?").'">'.s("Dépublier").'</a>';
	}

	protected function getArchiveItem(Aid $eAid): string {
		return '<a data-ajax="/aid/admin/aid:doArchive" post-id="'.$eAid['id'].'" class="dropdown-item" data-confirm="'.s("L'aide sortira du catalogue et de la relecture. Continuer ?").'">'.s("Archiver").'</a>';
	}

	public function getAid(Aid $eAid): string {

		// Ouverte depuis la file, l'aide y ramène, à la page d'où elle vient.
		if($eAid['queue'] === NULL) {
			$h = \main\DesignUi::getPageHeader(s("Aides"), back: '/aid/admin/');
		} else {
			$h = \main\DesignUi::getPageHeader(s("Aides à revoir"), back: '/aid/admin/:review?page='.$eAid['queue']['page'].$this->getQueueReason($eAid));
		}

		$h .= $this->getRecord($eAid);
		$h .= $this->getSeedDifferences($eAid);

		$h .= $this->getEditions($eAid);
		$h .= $this->getAreas($eAid);
		$h .= $this->getTags($eAid);
		$h .= $this->getOrganizations($eAid);
		$h .= $this->getPieces($eAid);
		$h .= $this->getLinks($eAid);
		$h .= $this->getSources($eAid);

		return $h;

	}

	protected function getRecord(Aid $eAid): string {

		$h = '<article class="util-record util-record-branches mb-2">';

			$h .= '<div class="util-record-main">';
				$h .= '<div class="util-record-head">';
					$h .= '<div class="util-record-identity">';
						$h .= '<h3 class="util-record-title">'.encode($eAid['name']).' '.$this->getStatus($eAid).' '.$this->getVerification($eAid).'</h3>';
						$h .= '<div class="util-record-context">'.$this->getContext($eAid).'</div>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-record-actions">';
					$h .= $this->getRecordActions($eAid);
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<ul class="util-record-list">';

				foreach($this->getItems($eAid) as $item) {
					$h .= \main\DesignUi::getRecordItem(...$item);
				}

				if($eAid['conditions'] !== NULL) {
					$h .= \main\DesignUi::getRecordItem(s("Conditions"), new \editor\EditorUi()->value($eAid['conditions']), class: 'util-record-item-wide');
				}

				if($eAid['notes'] !== NULL) {
					$h .= \main\DesignUi::getRecordItem(s("Notes de relevé"), nl2br(encode($eAid['notes'])), class: 'util-record-item-wide');
				}
			$h .= '</ul>';

		$h .= '</article>';

		return $h;

	}

	// L'aide condensée dans la colonne de côté de la file de revue : les informations courtes de la
	// fiche, les conditions et les notes restent sur la page de l'aide.
	protected function getReviewAid(Aid $eAid): string {

		$h = '<div class="util-card mb-2">';

			$h .= '<h3>'.encode($eAid['name']).'</h3>';
			$h .= '<div class="mb-1">'.$this->getStatus($eAid).' '.$this->getVerification($eAid).'</div>';
			$h .= '<p class="util-annotation">'.$this->getContext($eAid).'</p>';

			$h .= '<div class="flex-buttons mb-1">'.$this->getRecordActions($eAid).'</div>';

			// Un lien et non un bouton : il mène ailleurs, sans agir sur l'aide comme les gestes au-dessus.
			$h .= '<div class="mb-2">';
				$h .= '<a href="/aid/admin/aid?id='.$eAid['id'].'&origin=review&page='.$eAid['queue']['page'].$this->getQueueReason($eAid).'" class="util-more">'.s("Ouvrir la page de l'aide").'</a>';
			$h .= '</div>';

			$h .= '<dl class="util-presentation util-presentation-1">';

				foreach($this->getReviewItems($eAid) as $item) {
					$h .= '<dt>'.$item['label'].'</dt>';
					$h .= '<dd>';
						$h .= $item['value'];
						if($item['action'] !== '') {
							$h .= '<div class="mt-1">'.$item['action'].'</div>';
						}
					$h .= '</dd>';
				}

			$h .= '</dl>';

		$h .= '</div>';

		return $h;

	}

	// Les informations de la fiche dans la file, et juste sous la relecture, les sources que la veille met en cause.
	protected function getReviewItems(Aid $eAid): array {

		$items = $this->getItems($eAid);

		if($eAid['cSourceIssue']->empty()) {
			return $items;
		}

		$value = '';

		foreach($eAid['cSourceIssue'] as $eSource) {
			$value .= '<div class="mb-1">';
				$value .= '<a href="'.encode($eSource['url']).'" target="_blank" class="text-break">'.encode(parse_url($eSource['url'], PHP_URL_HOST)).'</a>';
				$value .= '<div>'.SourceUi::getStatus($eSource);
				if($eSource['checkedAt'] !== NULL) {
					$value .= ' <span class="util-annotation">'.s("lue le {value}", \util\DateUi::numeric($eSource['checkedAt'], \util\DateUi::DATE)).'</span>';
				}
				$value .= '</div>';
				$value .= SourceUi::getWatch($eAid, $eSource);
			$value .= '</div>';
		}

		// Une seule source en cause se corrige d'ici ; plusieurs, depuis la page de l'aide, qui les liste toutes.
		$action = ($eAid['cSourceIssue']->count() === 1) ?
			'<a href="/aid/admin/source:update?id='.$eAid['cSourceIssue']->first()['id'].'" class="btn btn-sm btn-outline-primary">'.\Asset::icon('pencil').' '.s("Modifier la source").'</a>' :
			'';

		$position = array_search('check', array_keys($items), TRUE) + 1;

		return array_slice($items, 0, $position, TRUE) + ['sources' => $this->getItem(s("Sources"), $value, $action)] + array_slice($items, $position, NULL, TRUE);

	}

	// Les informations courtes de la fiche, que la page et la file de revue partagent, chacune sous la
	// forme des arguments de DesignUi::getRecordItem(). Les rangées qui demandent un geste sont celles que
	// la page a préparées (AidLib::getAlerts()).
	protected function getItems(Aid $eAid): array {

		$items = [
			'display' => $this->getDisplay($eAid),
			'check' => $this->getCheck($eAid),
		];

		if($eAid->acceptPieces()) {
			$items['pieces'] = $this->getPiecesCheck($eAid);
		}

		$items['rate'] = $this->getRate($eAid);

		foreach($eAid['alerts'] as $alert) {
			$items[$alert]['class'] = implode(' ', array_filter([$items[$alert]['class'], 'util-record-item-alert']));
		}

		$bounds = $this->getBounds($eAid);

		if($bounds !== []) {
			$items['bounds'] = $bounds;
		}

		$items['form'] = $this->getItem(s("Forme"), AidUi::p('type')->values[$eAid['type']].', '.mb_strtolower(AidUi::p('calendar')->values[$eAid['calendar']]));
		$items['beneficiaries'] = $this->getBeneficiaries($eAid);
		$items['applicant'] = $this->getApplicant($eAid);

		if($eAid['depositUrl'] !== NULL) {
			$items['depositUrl'] = $this->getItem(AidUi::p('depositUrl')->label, '<a href="'.encode($eAid['depositUrl']).'" target="_blank" class="text-break">'.encode($eAid['depositUrl']).'</a>');
		}

		if($eAid['commitmentRule'] !== NULL) {
			$items['commitmentRule'] = $this->getItem(AidUi::p('commitmentRule')->label, nl2br(encode($eAid['commitmentRule'])), class: 'util-record-item-wide');
		}

		return $items;

	}

	// La case « Aux structures » seule, telle que getItems() la pose dans la fiche, alerte comprise.
	public function getDisplayItem(Aid $eAid): string {
		return \main\DesignUi::getRecordItem(...$this->getItems($eAid)['display']);
	}

	protected function getItem(string $label, string $value, string $action = '', string $class = ''): array {
		return ['label' => $label, 'value' => $value, 'action' => $action, 'class' => $class];
	}

	protected function getRate(Aid $eAid): array {

		if($eAid['rate'] !== NULL) {
			$value = nl2br(encode($eAid['rate']));
		} else if($eAid['rateUnpublished']) {
			$value = s("L'organisme ne publie pas de taux");
		} else {
			$value = '<span class="color-muted">'.s("Non renseigné").'</span>';
		}

		return $this->getItem(s("Taux ou montant"), $value, class: 'util-record-item-wide');

	}

	protected function getContext(Aid $eAid): string {

		$context = [encode($eAid['organization']['name'])];

		if($eAid['parent']->notEmpty()) {
			$context[] = s("volet de {value}", '<a href="/aid/admin/aid?id='.$eAid['parent']['id'].'">'.encode($eAid['parent']['name']).'</a>');
		}

		if($eAid['cAidChild']->notEmpty()) {
			$context[] = p("{value} volet", "{value} volets", $eAid['cAidChild']->count());
		}

		return implode(' · ', $context);

	}

	protected function getRecordActions(Aid $eAid): string {

		$h = '';

		if($eAid->assertPublish()) {
			$h .= '<a data-ajax="/aid/admin/aid:doPublish" post-id="'.$eAid['id'].'" class="btn btn-primary"'.$this->getPublishConfirm($eAid).'>'.s("Publier").'</a>';
		}

		$h .= '<a href="/aid/admin/aid:update?id='.$eAid['id'].'" class="btn btn-outline-primary">'.\Asset::icon('pencil').' '.s("Modifier l'aide").'</a>';

		if($eAid->assertUnpublish() or $eAid->assertArchive()) {

			$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
			$h .= '<div class="dropdown-list">';
				$h .= '<div class="dropdown-title">'.encode($eAid['name']).'</div>';

				if($eAid->assertUnpublish()) {
					$h .= $this->getUnpublishItem($eAid);
				}

				if($eAid->assertArchive()) {
					$h .= '<div class="dropdown-divider"></div>';
					$h .= $this->getArchiveItem($eAid);
				}

			$h .= '</div>';

		}

		return $h;

	}

	// Ce que voient les structures : rien d'un brouillon ni d'une aide archivée, et d'une aide publiée,
	// sa fiche ou la raison qui la cache. Un brouillon dit ce qui lui manque pour se publier.
	// `aid-admin-display` est la prise de la réponse à un ajout de pièce (aidPiece.v.php), que la première
	// pièce peut changer : getRecordItem() ne prend qu'une classe.
	protected function getDisplay(Aid $eAid): array {

		if($eAid['status'] === Aid::DRAFT and $eAid['publishMissing'] !== []) {

			$missing = array_map(fn(string $missing) => match($missing) {
				Aid::MISSING_RATE => s("son taux ou son montant"),
				Aid::MISSING_SOURCE => s("une source officielle"),
				Aid::MISSING_SOURCE_ALIVE => s("une source officielle qui répond"),
			}, $eAid['publishMissing']);

			return $this->getItem(s("Aux structures"), s("Pour être publiée, l'aide attend {value}.", implode(s(" et "), $missing)), class: 'aid-admin-display');

		}

		$value = match($eAid['status']) {
			Aid::DRAFT => s("Invisible tant qu'elle n'est pas publiée"),
			Aid::ARCHIVED => s("Invisible, l'aide est archivée"),
			Aid::PUBLISHED => match($eAid['hidden']) {
				NULL => $eAid->isPiecesWithheld() ?
					s("Affichée, sans sa liste des pièces, la fiche renvoie à l'organisme") :
					s("Affichée"),
				Aid::HIDDEN_CLOSED => ($eAid['endDate'] === NULL) ?
					s("Masquée, sa dernière édition est close") :
					s("Masquée, sa dernière édition est close depuis le {value}", \util\DateUi::numeric($eAid['endDate'], \util\DateUi::DATE)),
				Aid::HIDDEN_SOURCE_DEAD => s("Masquée, sa source officielle ne répond plus"),
			},
		};

		return $this->getItem(s("Aux structures"), $value, class: 'aid-admin-display');

	}

	// La raison la plus urgente, une seule : à revoir, sinon jamais relue, sinon relue. Une liste des pièces
	// à revoir se dit dans sa propre case (getPiecesCheck()) : seule à revoir, elle laisse à la fiche sa
	// relecture. Une aide semée porte la date de sa fiche sans que personne l'ait relue : elle ne se dit
	// jamais « relue ».
	protected function getCheck(Aid $eAid): array {

		$checkedAt = ($eAid['checkedAt'] === NULL) ? NULL : \util\DateUi::numeric($eAid['checkedAt'], \util\DateUi::DATE);

		$reason = $eAid->getCheckReason();

		if($reason !== NULL) {

			// Masquée ou close, la ligne dit la même chose : le masquage se lit dans l'affichage de l'aide (getDisplay()).
			$sourceDead = s("À revoir, sa source officielle ne répond plus");

			// La raison de la liste seule n'arrive pas jusqu'ici (Aid::getCheckReason()), mais getReviewSource()
			// la tient pour la file, qui n'a qu'une colonne de raisons.
			$value = match($reason) {
				Aid::REVIEW_CLOSING => s("À revoir, une édition ferme le {value}", \util\DateUi::numeric($eAid['endDate'], \util\DateUi::DATE)),
				Aid::REVIEW_SOURCE => $this->getReviewSource($eAid, $sourceDead, $sourceDead, s("À revoir, une source officielle ne répond plus"), s("À revoir, une source officielle a changé d'adresse"), s("À revoir, la page de la liste des pièces a changé d'adresse ou ne répond plus")),
				Aid::REVIEW_SEED => s("À revoir, la reprise rapporte un écart avec la fiche"),
				Aid::REVIEW_CONFLICT => s("À revoir, ses sources divergent").(($eAid['conflictSentence'] === NULL) ? '' : '<div class="util-annotation">'.s("« {value} »", encode($eAid['conflictSentence'])).'</div>'),
				Aid::REVIEW_UNREADABLE => ($eAid->getSourceUnreadable()['checkedAt'] === NULL) ? s("À revoir, la veille n'a jamais pu lire une source officielle") : s("À revoir, la veille ne lit plus une source officielle"),
				Aid::REVIEW_CLOSED => s("À revoir, close depuis plus d'un an"),
				Aid::REVIEW_UNDATED => s("À revoir, une édition ouverte n'a pas de date de fin"),
				Aid::REVIEW_STALE => $eAid['reviewNever'] ? s("À revoir, jamais relue") : s("À revoir, relue le {value}", $checkedAt),
			};

		} else if($eAid['reviewNever']) {
			$value = s("Jamais relue");
		} else {
			$value = s("Relue le {date} par {user}", [
				'date' => $checkedAt,
				'user' => $eAid['checkedBy']->getName(),
			]);
		}

		$action = '';

		if($eAid->isCheckDue() and $eAid->assertCheck()) {
			$action .= '<a data-ajax="/aid/admin/aid:doCheck" post-id="'.$eAid['id'].'"'.$this->getQueuePost($eAid).' class="btn btn-sm btn-outline-primary">'.s("Marquer relue").'</a>';
		}

		if($eAid->assertConfirm()) {
			$action .= ' <a data-ajax="/aid/admin/aid:doConfirm" post-id="'.$eAid['id'].'"'.$this->getQueuePost($eAid).' class="btn btn-sm btn-outline-primary" data-confirm="'.s("J'ai lu la source officielle et la fiche lui est conforme.").'">'.s("Confirmer sur la source officielle").'</a>';
		}

		return $this->getItem(s("Relecture"), $value, $action);

	}

	/**
	 * La relecture de la liste des pièces, juste sous celle de la fiche et avec le même geste (planche des
	 * réglages de L6, piste a). Sa raison d'être à revoir s'y lit, et la case passe en alerte
	 * (AidLib::getAlerts()) : « Marquer la liste relue » est à côté de ce qu'il éteint.
	 */
	protected function getPiecesCheck(Aid $eAid): array {

		if($eAid['reviewSourcePieces']) {
			$value = s("À revoir, la page de la liste des pièces a changé d'adresse ou ne répond plus");
		} else if($eAid['piecesCheckedAt'] === NULL) {
			$value = s("Jamais relue");
		} else {
			$value = s("Relue le {value}", \util\DateUi::numeric($eAid['piecesCheckedAt'], \util\DateUi::DATE));
		}

		return $this->getItem(s("Liste des pièces"), $value, $eAid->assertPieces() ? $this->getCheckPieces($eAid) : '');

	}

	protected function getBounds(Aid $eAid): array {

		$bounds = [];

		if($eAid['ratePercentMax'] !== NULL) {
			$bounds[] = s("jusqu'à {value} %", \util\TextUi::number($eAid['ratePercentMax']));
		}

		if($eAid['amountMax'] !== NULL) {
			$bounds[] = s("au plus {value} {unit}", [
				'value' => \util\TextUi::number($eAid['amountMax']),
				'unit' => AidUi::p('amountUnit')->values[$eAid['amountUnit']],
			]);
		}

		// Sans assiette, le plancher peut porter sur la dépense et le plafond sur l'aide : chacun se dit
		// seul, sans fourchette ni assiette devinée, pour que la relecture voie ce qui reste à poser.
		$boundsFloorCeiling = [];

		if($eAid['boundBase'] === NULL) {

			if($eAid['floor'] !== NULL) {
				$boundsFloorCeiling[] = s("plancher de {value} €", \util\TextUi::number($eAid['floor']));
			}

			if($eAid['ceiling'] !== NULL) {
				$boundsFloorCeiling[] = s("plafond de {value} €", \util\TextUi::number($eAid['ceiling']));
			}

		} else if($eAid['floor'] !== NULL or $eAid['ceiling'] !== NULL) {

			$base = match($eAid['boundBase']) {
				Aid::EXPENSE => s("de dépense"),
				Aid::AID => s("d'aide"),
			};

			if($eAid['floor'] !== NULL and $eAid['ceiling'] !== NULL) {
				$boundsFloorCeiling[] = s("de {floor} € à {ceiling} € {base}", ['floor' => \util\TextUi::number($eAid['floor']), 'ceiling' => \util\TextUi::number($eAid['ceiling']), 'base' => $base]);
			} else if($eAid['floor'] !== NULL) {
				$boundsFloorCeiling[] = s("{value} € {base} au moins", ['value' => \util\TextUi::number($eAid['floor']), 'base' => $base]);
			} else {
				$boundsFloorCeiling[] = s("{value} € {base} au plus", ['value' => \util\TextUi::number($eAid['ceiling']), 'base' => $base]);
			}

		}

		if($eAid['boundPeriod'] !== NULL) {
			$period = AidUi::p('boundPeriod')->values[$eAid['boundPeriod']];
			$boundsFloorCeiling = array_map(fn(string $bound) => s("{bound} {period}", ['bound' => $bound, 'period' => $period]), $boundsFloorCeiling);
		}

		$bounds = array_merge($bounds, $boundsFloorCeiling);

		if($eAid['boundBase'] === NULL and $boundsFloorCeiling !== []) {
			$bounds[] = s("assiette non précisée");
		}

		if($bounds === []) {
			return [];
		}

		return $this->getItem(s("Bornes"), implode(', ', $bounds));

	}

	protected function getBeneficiaries(Aid $eAid): array {

		$beneficiaries = AidUi::p('beneficiaries')->values;

		$who = ($eAid['beneficiaries'] === NULL) ? [] : array_map(fn(int $beneficiary) => $beneficiaries[$beneficiary], $eAid['beneficiaries']->values());

		if($who === []) {
			$value = s("Tout demandeur");
		} else {
			$value = implode(', ', $who);
		}

		return $this->getItem(s("Pour qui"), $value);

	}

	protected function getApplicant(Aid $eAid): array {

		$value = $eAid['direct'] ? s("La structure dépose elle-même") : s("Un tiers dépose pour la structure");

		return $this->getItem(s("Qui dépose"), $value);

	}

	protected function getEditions(Aid $eAid): string {

		$h = $this->getSectionTitle(s("Éditions"), $eAid['cEdition']->count(), '<a href="/aid/admin/edition:create?aid='.$eAid['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Nouvelle édition").'</a>');

		if($eAid['cEdition']->empty()) {
			$h .= '<div class="util-empty">'.s("Aucune édition ne borne le dépôt de cette aide.").'</div>';
			return $h;
		}

		$h .= '<div class="util-table aid-table-editions">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Édition").'</div>';
				$h .= '<div class="hide-xs-down">'.s("Du").'</div>';
				$h .= '<div>'.s("Au").'</div>';
				$h .= '<div>'.s("État").'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($eAid['cEdition'] as $eEdition) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						$h .= '<b>'.(($eEdition['name'] === NULL) ? s("Sans nom") : encode($eEdition['name'])).'</b>';
						if($eEdition['rate'] !== NULL) {
							$h .= '<div class="util-annotation">'.encode($eEdition['rate']).'</div>';
						}
					$h .= '</div>';
					$h .= '<div class="hide-xs-down">'.$this->getDate($eEdition['startDate']).'</div>';
					$h .= '<div>'.$this->getDate($eEdition['endDate']).'</div>';
					$h .= '<div>'.EditionUi::getState($eEdition).'</div>';
					$h .= '<div>';
						$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
						$h .= '<div class="dropdown-list">';
							$h .= '<a href="/aid/admin/edition:update?id='.$eEdition['id'].'" class="dropdown-item">'.s("Modifier l'édition").'</a>';
							$h .= '<div class="dropdown-divider"></div>';
							$h .= '<a data-ajax="/aid/admin/edition:doDelete" post-id="'.$eEdition['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer cette édition ?").'">'.s("Supprimer l'édition").'</a>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getDate(?string $date): string {
		return ($date === NULL) ? '' : \util\DateUi::numeric($date, \util\DateUi::DATE);
	}

	protected function getAreas(Aid $eAid): string {

		$h = $this->getSectionTitle(s("Périmètre"), $eAid['cArea']->count());

		$h .= '<div class="util-table aid-table-areas">';

			if($eAid['cArea']->notEmpty()) {

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Département").'</div>';
					$h .= '<div>'.s("Précision").'</div>';
					$h .= '<div></div>';
				$h .= '</div>';

			}

			foreach($eAid['cArea'] as $eArea) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>'.AreaUi::getDepartmentBadge($eArea['department']).'</div>';
					$h .= '<div>'.(($eArea['note'] === NULL) ? '' : encode($eArea['note'])).'</div>';
					$h .= '<div>'.$this->getRemove('/aid/admin/area:doDelete', $eArea['id'], s("Retirer ce département du périmètre ?")).'</div>';
				$h .= '</div>';

			}

			$form = new \util\FormUi();

			$h .= $this->getAddLink($eAid['cArea']->empty() ? s("Ajouter un département, l'aide est nationale tant qu'elle n'en a aucun") : s("Ajouter un département"));

			$h .= $form->openAjax('/aid/admin/area:doCreate', ['class' => 'util-table-row util-table-add-entry hide', 'autocomplete' => 'off']);
				$h .= '<div data-wrapper="department">'.$form->dynamicField(new Area(['aid' => $eAid]), 'department').'</div>';
				$h .= '<div data-wrapper="note">'.$form->dynamicField(new Area(), 'note').'</div>';
				$h .= $this->getAddActions($form, $eAid);
			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	protected function getTags(Aid $eAid): string {

		$h = $this->getSectionTitle(s("Ce qui est financé"), $eAid['cAidTag']->count(), '<a href="/aid/admin/aid:updateTags?id='.$eAid['id'].'" class="btn btn-outline-primary">'.\Asset::icon('pencil').' '.s("Modifier").'</a>');

		if($eAid['cAidTag']->empty()) {
			$h .= '<div class="util-empty">'.s("Aucun tag ne dit ce que finance cette aide.").'</div>';
			return $h;
		}

		$h .= '<div class="badges mb-2">';
			foreach($eAid['cAidTag'] as $eAidTag) {
				$h .= '<span class="badge badge-production">'.encode($eAidTag['tag']['name']).'</span>';
			}
		$h .= '</div>';

		return $h;

	}

	protected function getOrganizations(Aid $eAid): string {

		$h = $this->getSectionTitle(s("Organismes"), $eAid['cAidOrganization']->count());

		$h .= '<div class="util-table aid-table-roles">';

			if($eAid['cAidOrganization']->notEmpty()) {

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Organisme").'</div>';
					$h .= '<div>'.s("Rôle").'</div>';
					$h .= '<div></div>';
				$h .= '</div>';

			}

			foreach($eAid['cAidOrganization'] as $eAidOrganization) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>'.encode($eAidOrganization['organization']['name']).'</div>';
					$h .= '<div>'.AidOrganizationUi::p('role')->values[$eAidOrganization['role']].'</div>';
					$h .= '<div>'.$this->getRemove('/aid/admin/aidOrganization:doDelete', $eAidOrganization['id'], s("Retirer cet organisme de l'aide ?")).'</div>';
				$h .= '</div>';

			}

			$form = new \util\FormUi();
			$eAidOrganization = new AidOrganization(['aid' => $eAid]);

			$h .= $this->getAddLink(s("Ajouter un organisme"));

			$h .= $form->openAjax('/aid/admin/aidOrganization:doCreate', ['class' => 'util-table-row util-table-add-entry hide', 'autocomplete' => 'off']);
				$h .= '<div data-wrapper="organization">'.$form->dynamicField($eAidOrganization, 'organization').'</div>';
				$h .= '<div data-wrapper="role">'.$form->dynamicField($eAidOrganization, 'role').'</div>';
				$h .= $this->getAddActions($form, $eAid);
			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les pièces que la structure réunit pour déposer, dans une table dont la dernière ligne ajoute, triée
	 * par moment (arbitrage q2 a de la planche de L6). « L'organisme ne publie pas la liste » se tient
	 * seul au-dessus d'elle, parce qu'il se décide en la regardant ; sa relecture est dans la fiche
	 * (getPiecesCheck()). Un ajout redessine le titre, les pièces, la ligne d'ajout et la case « Aux
	 * structures », que la première pièce peut changer (aidPiece.v.php).
	 */
	protected function getPieces(Aid $eAid): string {

		$h = '<div id="aid-admin-pieces">';

			$h .= $this->getPiecesTitle($eAid);

			// Les pièces saisies avant que la forme de l'aide change restent à retirer : la fiche ne les
			// montre plus, et leur retrait reste ouvert (AidPiece::acceptCreate()).
			if($eAid->acceptPieces() === FALSE) {

				$h .= '<div class="util-empty">'.s("La structure ne dépose pas elle-même cette aide, ou l'aide n'a pas de dossier. Sa fiche ne montre pas de pièces.").'</div>';

				if($eAid['cAidPiece']->notEmpty()) {
					$h .= '<div class="util-table aid-table-pieces">'.$this->getPiecesRows($eAid).'</div>';
				}

			} else {

				$h .= '<div class="util-rows mb-1">';
					$h .= '<div class="util-row">';
						$h .= '<span>'.AidUi::p('piecesUnpublished')->label.'</span>';
						$h .= '<span>'.\util\TextUi::switch([
							'data-ajax' => '/aid/admin/aid:doUpdatePieces',
							'post-id' => $eAid['id'],
							'post-pieces-unpublished' => $eAid['piecesUnpublished'] ? 0 : 1,
						], $eAid['piecesUnpublished'], textOn: s("Oui"), textOff: s("Non")).'</span>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="util-table aid-table-pieces">';

					$h .= $this->getPiecesRows($eAid);

					$h .= $this->getAddLink(s("Ajouter une pièce"));
					$h .= $this->getPiecesEntry($eAid, FALSE);

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public function getPiecesTitle(Aid $eAid): string {
		return $this->getSectionTitle(s("Pièces à fournir"), $eAid['cAidPiece']->count());
	}

	/**
	 * La ligne d'ajout, fermée sous son lien, ou ouverte après un ajout, la saisie d'une liste se faisant
	 * d'une pièce à la suivante. Une pièce courante se choisit dans la liste commune, une pièce libre
	 * s'écrit à côté : la même cellule porte les deux champs, dont un seul se remplit (AidPiece::properties()).
	 */
	public function getPiecesEntry(Aid $eAid, bool $open): string {

		$form = new \util\FormUi();
		$eAidPiece = new AidPiece(['aid' => $eAid]);

		$h = $form->openAjax('/aid/admin/aidPiece:doCreate', ['class' => 'util-table-row util-table-add-entry'.($open ? '' : ' hide'), 'autocomplete' => 'off']);
			$h .= '<div>';
				$h .= '<div data-wrapper="piece">'.$form->dynamicField($eAidPiece, 'piece', function(\PropertyDescriber $d) use($eAid) {
					$d->field = 'select';
					$d->values = $eAid['cPiece'];
					$d->placeholder = s("Pièce courante");
				}).'</div>';
				$h .= '<div data-wrapper="name" class="mt-1">'.$form->dynamicField($eAidPiece, 'name', function(\PropertyDescriber $d) {
					$d->placeholder = s("Ou une pièce libre");
				}).'</div>';
			$h .= '</div>';
			$h .= '<div data-wrapper="moment">'.$form->dynamicField($eAidPiece, 'moment').'</div>';
			$h .= '<div data-wrapper="note">'.$form->dynamicField($eAidPiece, 'note').'</div>';
			$h .= $this->getAddActions($form, $eAid);
		$h .= $form->close();

		return $h;

	}

	protected function getPiecesRows(Aid $eAid): string {
		return implode('', $this->getPiecesItems($eAid));
	}

	/**
	 * L'en-tête et les pièces, un rang chacun, qu'un ajout remplace par la classe qu'ils partagent
	 * (aidPiece.v.php) : l'en-tête ne paraît qu'avec la première pièce.
	 */
	public function getPiecesItems(Aid $eAid): array {

		$items = [];

		if($eAid['cAidPiece']->notEmpty()) {

			$h = '<div class="util-table-head aid-table-pieces-item">';
				$h .= '<div>'.AidPieceUi::p('piece')->label.'</div>';
				$h .= '<div>'.AidPieceUi::p('moment')->label.'</div>';
				$h .= '<div>'.AidPieceUi::p('note')->label.'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			$items[] = $h;

		}

		foreach($eAid['cAidPiece'] as $eAidPiece) {

			$h = '<div class="util-table-row aid-table-pieces-item">';
				$h .= '<div>';
					$h .= encode(AidPieceUi::getName($eAidPiece));
					if($eAidPiece['piece']->empty()) {
						$h .= ' <span class="badge badge-outline badge-off">'.s("libre").'</span>';
					}
					if($eAidPiece['url'] !== NULL) {
						$h .= '<div class="util-annotation text-break"><a href="'.encode($eAidPiece['url']).'" target="_blank">'.encode($eAidPiece['url']).'</a></div>';
					}
				$h .= '</div>';
				$h .= '<div>'.AidPieceUi::p('moment')->values[$eAidPiece['moment']].'</div>';
				$h .= '<div>'.($eAidPiece['note'] === NULL ? '' : encode($eAidPiece['note'])).'</div>';
				$h .= '<div>';
					$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
					$h .= '<div class="dropdown-list">';
						$h .= '<a href="/aid/admin/aidPiece:update?id='.$eAidPiece['id'].'" class="dropdown-item">'.s("Modifier la pièce").'</a>';
						$h .= '<div class="dropdown-divider"></div>';
						$h .= '<a data-ajax="/aid/admin/aidPiece:doDelete" post-id="'.$eAidPiece['id'].'" class="dropdown-item" data-confirm="'.s("Retirer cette pièce de l'aide ?").'">'.s("Retirer la pièce").'</a>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

			$items[] = $h;

		}

		return $items;

	}

	protected function getCheckPieces(Aid $eAid): string {
		return '<a data-ajax="/aid/admin/aid:doCheckPieces" post-id="'.$eAid['id'].'"'.$this->getQueuePost($eAid).' class="btn btn-sm btn-outline-primary">'.s("Marquer la liste relue").'</a>';
	}

	protected function getLinks(Aid $eAid): string {

		$h = $this->getSectionTitle(s("Liens avec d'autres aides"), $eAid['cAidLink']->count());

		$h .= '<div class="util-table aid-table-links">';

			if($eAid['cAidLink']->notEmpty()) {

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Aide").'</div>';
					$h .= '<div>'.s("Lien").'</div>';
					$h .= '<div></div>';
				$h .= '</div>';

			}

			foreach($eAid['cAidLink'] as $eAidLink) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						$h .= '<a href="/aid/admin/aid?id='.$eAidLink['target']['id'].'">'.encode($eAidLink['target']['name']).'</a>';
						$h .= '<div class="util-annotation">'.encode($eAidLink['target']['organization']['name']).'</div>';
					$h .= '</div>';
					$h .= '<div>'.AidLinkUi::p('kind')->values[$eAidLink['kind']].'</div>';
					$h .= '<div>'.$this->getRemove('/aid/admin/aidLink:doDelete', $eAidLink['id'], s("Retirer ce lien ?")).'</div>';
				$h .= '</div>';

			}

			$form = new \util\FormUi();
			$eAidLink = new AidLink(['aid' => $eAid]);

			$h .= $this->getAddLink(s("Lier une autre aide"));

			$h .= $form->openAjax('/aid/admin/aidLink:doCreate', ['class' => 'util-table-row util-table-add-entry hide', 'autocomplete' => 'off']);
				$h .= '<div data-wrapper="target">'.$form->dynamicField($eAidLink, 'target').'</div>';
				$h .= '<div data-wrapper="kind">'.$form->dynamicField($eAidLink, 'kind').'</div>';
				$h .= $this->getAddActions($form, $eAid);
			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	protected function getSources(Aid $eAid): string {

		$h = $this->getSectionTitle(s("Sources"), $eAid['cSource']->count());

		$h .= '<div class="util-table aid-table-sources">';

			if($eAid['cSource']->notEmpty()) {

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.SourceUi::p('url')->label.'</div>';
					$h .= '<div class="hide-xs-down">'.SourceUi::p('kind')->label.'</div>';
					$h .= '<div>'.SourceUi::p('status')->label.'</div>';
					$h .= '<div class="hide-sm-down">'.SourceUi::p('checkedAt')->label.'</div>';
					$h .= '<div></div>';
				$h .= '</div>';

			}

			foreach($eAid['cSource'] as $eSource) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						$h .= '<a href="'.encode($eSource['url']).'" target="_blank" class="text-break">'.encode($eSource['url']).'</a>';
						$h .= SourceUi::getWatch($eAid, $eSource);
					$h .= '</div>';
					$h .= '<div class="hide-xs-down">'.SourceUi::p('kind')->values[$eSource['kind']].'</div>';
					$h .= '<div>'.SourceUi::getStatus($eSource).'</div>';
					$h .= '<div class="hide-sm-down">'.$this->getDate($eSource['checkedAt']).'</div>';
					$h .= '<div>';
						$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
						$h .= '<div class="dropdown-list">';
							$h .= '<a href="/aid/admin/source:update?id='.$eSource['id'].'" class="dropdown-item">'.s("Modifier la source").'</a>';
							if($eSource->assertMove()) {
								$h .= '<a data-ajax="/aid/admin/source:doMove" post-id="'.$eSource['id'].'" class="dropdown-item">'.s("Prendre la nouvelle adresse").'</a>';
							}
							if($eSource['deletable']) {
								$h .= '<div class="dropdown-divider"></div>';
								$h .= '<a data-ajax="/aid/admin/source:doDelete" post-id="'.$eSource['id'].'" class="dropdown-item" data-confirm="'.s("Retirer cette source ?").'">'.s("Retirer la source").'</a>';
							}
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

			$form = new \util\FormUi();
			$eSource = new Source(['aid' => $eAid]);

			$h .= $this->getAddLink(s("Ajouter une source"));

			$h .= $form->openAjax('/aid/admin/source:doCreate', ['class' => 'util-table-row util-table-add-entry hide', 'autocomplete' => 'off']);
				$h .= '<div data-wrapper="url">'.$form->dynamicField($eSource, 'url').'</div>';
				$h .= '<div data-wrapper="kind">'.$form->dynamicField($eSource, 'kind', function(\PropertyDescriber $d) {
					$d->field = 'select';
					$d->placeholder = s("Nature");
				}).'</div>';
				// La page et la date de lecture sont celles de la veille, que la saisie n'écrit pas : leurs
				// cellules restent vides pour que les gestes tiennent la dernière colonne. Sous 576 px, où la
				// nature n'a plus de colonne, son champ prend celle de la page.
				$h .= '<div class="hide-xs-down"></div>';
				$h .= '<div class="hide-sm-down"></div>';
				$h .= $this->getAddActions($form, $eAid);
			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	protected function getSectionTitle(string $title, int $count, string $action = ''): string {

		$h = '<div class="util-title mt-2">';
			$h .= '<h2>'.$title.' <span class="util-counter">'.$count.'</span></h2>';
			if($action !== '') {
				$h .= '<div>'.$action.'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	protected function getRemove(string $url, int $id, string $confirm): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<a data-ajax="'.$url.'" post-id="'.$id.'" class="dropdown-item" data-confirm="'.$confirm.'">'.s("Retirer").'</a>';
		$h .= '</div>';

		return $h;

	}

	// La dernière ligne d'une table, qui ouvre à sa place la ligne de saisie qui la suit (admin.js).
	protected function getAddLink(string $label): string {
		return '<a class="util-table-row util-table-add" data-aid-admin-add><div><span class="util-table-add-icon">'.\Asset::icon('plus-lg').'</span><span>'.$label.'</span></div></a>';
	}

	// Les champs cachés vont dans la dernière cellule : en tête du formulaire, l'un d'eux prendrait la
	// place de la première cellule de la ligne.
	protected function getAddActions(\util\FormUi $form, Aid $eAid): string {

		$h = '<div class="util-table-add-actions">';
			$h .= $form->hidden('aid', $eAid);
			$h .= $form->submit(s("Ajouter"));
			$h .= '<a class="btn btn-outline-primary" data-aid-admin-cancel>'.s("Annuler").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function create(Aid $eAid): \Panel {
		return $this->getPanel($eAid, '/aid/admin/aid:doCreate', s("Nouvelle aide"), s("Créer l'aide"));
	}

	public function update(Aid $eAid): \Panel {
		return $this->getPanel($eAid, '/aid/admin/aid:doUpdate', s("Modifier l'aide"), s("Enregistrer"));
	}

	protected function getPanel(Aid $eAid, string $action, string $title, string $submit): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax($action, ['autocomplete' => 'off']);

			if($eAid->exists()) {
				$h .= $form->hidden('id', $eAid);
			}

			// AidLib::getPropertiesCreate() reprend cet ordre, que la revue d'un écart suit.
			$h .= $form->fieldset(s("L'aide"), $form->dynamicGroups($eAid, ['name*', 'summary', 'organization*', 'parent', 'level*', 'type*', 'calendar*']));

			$amount = $form->dynamicField($eAid, 'amountMax');
			$amount .= $form->dynamicField($eAid, 'amountUnit', fn(\PropertyDescriber $d) => $d->field = 'select');

			$h .= $form->fieldset(s("Montant"),
				$form->dynamicGroups($eAid, ['rate', 'rateUnpublished', 'ratePercentMax']).
				$form->group(AidUi::p('amountMax')->label, $form->inputGroup($amount), ['data-wrapper' => 'amountMax amountUnit']).
				$form->dynamicGroups($eAid, ['floor', 'ceiling', 'boundBase', 'boundPeriod'])
			);

			$h .= $form->fieldset(s("Éligibilité"), $form->dynamicGroups($eAid, ['beneficiaries', 'direct', 'productions', 'labels', 'labelsBonus', 'conditions', 'keywords']));

			$h .= $form->fieldset(s("Dépôt"), $form->dynamicGroups($eAid, ['depositUrl', 'commitmentRule']));

			$h .= $form->fieldset(s("Relevé"), $form->dynamicGroups($eAid, ['notes']));

			$h .= $form->group(
				content: $form->submit($submit)
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-aid-admin',
			title: $title,
			body: $h
		);

	}

	public function updateTags(Aid $eAid, \Collection $cTag): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/aid/admin/aid:doUpdateTags');

			$h .= $form->hidden('id', $eAid);

			$h .= $form->group(
				s("Ce que finance l'aide"),
				$form->checkboxes('tags[]', $cTag->makeArray(fn(Tag $eTag) => encode($eTag['name']), preserveKeys: TRUE), $eAid['cTag']->getIds(), [
					'columns' => 3,
					'all' => FALSE,
				]),
				['data-wrapper' => 'tags']
			);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-aid-admin-tags',
			title: encode($eAid['name']),
			body: $h
		);

	}

}
?>
