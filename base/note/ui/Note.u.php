<?php
namespace note;

class NoteUi {

	public function __construct() {
		\Asset::css('note', 'note.css');
		\Asset::js('note', 'note.js');
	}

	public static function url(Note $eNote): string {
		return '/note/'.$eNote['id'];
	}

	public static function urlStructure(\structure\Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/notes';
	}

	/**
	 * L'onglet ouvert, sans le filtre ni la page : c'est là que mène l'effacement du filtre, et là que
	 * chaque suggestion le pose.
	 */
	private static function urlTab(\structure\Structure $eStructure, ?int $notebook): string {

		if($notebook === NULL) {
			return self::urlStructure($eStructure);
		}

		return self::urlStructure($eStructure).'?notebook='.$notebook;

	}

	private static function getTabNotebook(\Search $search): ?int {

		if($search->has('notebook') === FALSE) {
			return NULL;
		}

		return $search->get('notebook')->empty() ? 0 : $search->get('notebook')['id'];

	}

	public static function urlTarget(string $url, Target $eTarget): string {
		return \util\HttpUi::setArgument($url, 'target', $eTarget->getValue());
	}

	/**
	 * La liste ouverte, que les panneaux d'une note gardent à l'adresse : son onglet (`tab`, `0` pour
	 * « Non classé », `NULL` pour toutes les notes), la valeur de son filtre (`target`) et sa page.
	 */
	public static function getListContext(\Search $search, int $page): array {

		return [
			'tab' => self::getTabNotebook($search),
			'target' => $search->has('target') ? $search->get('target')->getValue() : NULL,
			'page' => $page
		];

	}

	/**
	 * L'adresse de la liste d'où s'ouvre un panneau. Le panneau la pose dans la barre d'adresse au lieu
	 * de la sienne : un rechargement rend la liste telle qu'on l'a laissée, et le retour arrière ferme le
	 * panneau.
	 */
	public static function urlList(\structure\Structure $eStructure, array $list): string {

		$url = self::urlTab($eStructure, $list['tab']);

		if($list['target'] !== NULL) {
			$url = \util\HttpUi::setArgument($url, 'target', $list['target']);
		}

		if($list['page'] > 0) {
			$url = \util\HttpUi::setArgument($url, 'page', $list['page']);
		}

		return $url;

	}

	/**
	 * La liste portée d'un lien à l'autre, jusqu'au formulaire qui la poste et au geste qui la renvoie.
	 * L'onglet voyage sous `tab` : le formulaire d'une note poste déjà son carnet sous `notebook`.
	 */
	private static function getListValues(array $list): array {

		$values = ['origin' => 'list'];

		if($list['tab'] !== NULL) {
			$values['tab'] = $list['tab'];
		}

		if($list['target'] !== NULL) {
			$values['target'] = $list['target'];
		}

		if($list['page'] > 0) {
			$values['page'] = $list['page'];
		}

		return $values;

	}

	private static function getListArguments(array $list): string {
		return http_build_query(self::getListValues($list));
	}

	private static function getListFields(\util\FormUi $form, array $list): string {

		if($list === []) {
			return '';
		}

		return $form->hiddens(self::getListValues($list));

	}

	/**
	 * La note ouverte depuis la liste, dans un panneau par-dessus elle. Sans liste, l'adresse ouvre la
	 * page de la note.
	 */
	public static function urlRead(Note $eNote, array $list): string {
		return self::url($eNote).'?'.self::getListArguments($list);
	}

	public static function urlUpdateTargets(Note $eNote, array $list): string {
		return '/note/note:updateTargets?id='.$eNote['id'].($list === [] ? '' : '&'.self::getListArguments($list));
	}

	/**
	 * « Nouvelle note », au départ de la rangée de la recherche. Un onglet vide porte le sien dans son
	 * bloc vide, que la rangée ne double pas.
	 */
	private function getCreate(Note $eNote, array $counts, array $list): string {

		if($eNote->assertCreate() === FALSE or $counts === []) {
			return '';
		}

		return '<a href="'.self::urlCreate($eNote, $list).'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Nouvelle note").'</span></a>';

	}

	/**
	 * Une note créée depuis l'onglet d'un carnet y est rangée d'office.
	 */
	private static function urlCreate(Note $eNote, array $list): string {
		return '/note/note:create?structure='.$eNote['structure']['id'].'&'.self::getListArguments($list);
	}

	/**
	 * Un onglet par carnet entre « Toutes » et « Non classé », chacun avec le nombre de notes que le
	 * membre lit, et au bout de la rangée le menu des carnets pour qui les écrit. Sans carnet, pas
	 * d'onglets.
	 *
	 * Sous les onglets, une rangée porte « Nouvelle note » au départ du contenu et la recherche à
	 * l'opposé. Ni l'un ni l'autre ne va dans les outils de `getPageTabs()` : au bureau, les onglets
	 * montent dans la tête de l'écran mais gardent leur largeur dans la rangée, et des outils s'y
	 * caleraient à leur droite, loin de la liste qu'ils servent. Un onglet vide sans filtre n'a pas cette
	 * rangée : ses gestes sont dans son bloc vide.
	 */
	public function getTabs(\structure\Structure $eStructure, \Collection $cNotebook, array $counts, \Search $search, int $listed, bool $isTabEmpty, int $page, Note $eNoteCreate, Notebook $eNotebookCreate): string {

		if($isTabEmpty) {
			$filter = '';
			$actions = '';
		} else {
			$filter = $this->getFilter($eStructure, $counts, $search, $listed);
			$actions = $this->getCreate($eNoteCreate, $counts, self::getListContext($search, $page));
		}

		if($cNotebook->empty()) {

			// Le premier carnet ne se propose que lorsqu'il y a déjà des notes à y ranger.
			if($isTabEmpty === FALSE and $eNotebookCreate->assertCreate()) {
				$actions .= new NotebookUi()->getCreate($eNotebookCreate);
			}

			return '<div id="note-head">'.$this->getTools($actions, $filter).'</div>';

		}

		$url = self::urlStructure($eStructure);
		$tabCounts = self::getTabCounts($cNotebook, $counts);

		$tabs = $this->getTab($url, s("Toutes"), 'all', $tabCounts['all'], $search->has('notebook') === FALSE);

		foreach($cNotebook as $eNotebook) {

			$selected = (
				$search->has('notebook') and
				$search->get('notebook')->is($eNotebook)
			);

			$tabs .= $this->getTab(NotebookUi::url($eNotebook), encode($eNotebook['name']), $eNotebook['id'], $tabCounts[$eNotebook['id']], $selected);

		}

		$unclassified = (
			$search->has('notebook') and
			$search->get('notebook')->empty()
		);

		$tabs .= $this->getTab($url.'?notebook=0', s("Non classé"), 0, $tabCounts[0], $unclassified);

		$eNotebookSelected = $search->get('notebook', new Notebook());

		$tabs .= new NotebookUi()->getMenu($cNotebook, $eNotebookSelected, $eNotebookCreate);

		// Le PDF du carnet ouvert, pour tout membre qui y lit au moins une note.
		if($eNotebookSelected->notEmpty() and ($counts[$eNotebookSelected['id']] ?? 0) > 0) {
			$actions .= PdfUi::getDownload(PdfUi::urlNotebook($eNotebookSelected));
		}

		return '<div id="note-head">'.\main\DesignUi::getPageTabs($tabs, fold: TRUE).$this->getTools($actions, $filter).'</div>';

	}

	/**
	 * Les gestes au départ de la rangée, la recherche à l'opposé. Sans geste, une boîte vide tient leur
	 * place : la rangée répartit ses enfants aux deux bouts, et la recherche seule partirait à gauche.
	 */
	private function getTools(string $actions, string $filter): string {

		if($actions === '' and $filter === '') {
			return '';
		}

		return '<div class="util-title"><div class="note-list-actions">'.$actions.'</div>'.$filter.'</div>';

	}

	/**
	 * Le filtre par rattachement, en mode navigation : la suggestion change de page, le jeton ramène à
	 * l'onglet sans lui. Les suggestions viennent du serveur, qui ne propose que ce dont parlent les notes
	 * que le membre lit. Une structure sans note n'a rien à filtrer.
	 */
	private function getFilter(\structure\Structure $eStructure, array $counts, \Search $search, int $listed): string {

		if($counts === []) {
			return '';
		}

		$notebook = self::getTabNotebook($search);
		$urlTab = self::urlTab($eStructure, $notebook);

		$tokens = [];

		if($search->has('target')) {
			$tokens[] = self::getTargetToken($search->get('target'), $urlTab);
		}

		return \main\OmniboxUi::get(
			suggestionsUrl: '/note/note:queryFilter',
			suggestionsBody: [
				'id' => $eStructure['id'],
				'notebook' => $notebook
			],
			tokens: $tokens,
			placeholder: s("Espèce, intervention, emplacement..."),
			count: $search->has('target') ? p("{value} note", "{value} notes", $listed) : NULL,
			clear: $urlTab,
			class: 'util-omnibox-lg'
		);

	}

	/**
	 * Ce que propose le filtre de la liste, chaque suggestion menant à l'onglet d'où l'on cherche.
	 */
	public static function getFilterSuggestions(\structure\Structure $eStructure, \Collection $cTarget, ?int $notebook): array {

		$urlTab = self::urlTab($eStructure, $notebook);

		$suggestions = [];

		foreach($cTarget as $eTarget) {
			$suggestions[] = self::getTargetSuggestion($eTarget) + [
				'url' => self::urlTarget($urlTab, $eTarget)
			];
		}

		return $suggestions;

	}

	/**
	 * Ce que propose l'omnibox du formulaire : sans adresse, la suggestion choisie devient un jeton,
	 * que la boîte poste dans `cTarget[]`.
	 */
	public static function getTargetSuggestions(\Collection $cTarget): array {

		$suggestions = [];

		foreach($cTarget as $eTarget) {
			$suggestions[] = self::getTargetSuggestion($eTarget);
		}

		return $suggestions;

	}

	private static function getTargetSuggestion(Target $eTarget): array {

		$ui = NoteSetting::resolve($eTarget['targetType'], 'NoteTargetUi');

		return array_filter([
			'group' => $ui::getGroup(),
			'label' => $ui::getLabel($eTarget['eElement']),
			'value' => $eTarget->getValue(),
			'icon' => $ui::getIcon($eTarget['eElement']),
			'detail' => $ui::getDetail($eTarget['eElement'])
		], fn($value) => $value !== NULL);

	}

	/** Publique : le champ du formulaire l'appelle depuis une closure que le describer lie à sa propre portée. */
	public static function getTargetToken(Target $eTarget, ?string $url = NULL): array {

		$ui = NoteSetting::resolve($eTarget['targetType'], 'NoteTargetUi');

		return array_filter([
			'group' => $ui::getGroup(),
			'label' => $ui::getLabel($eTarget['eElement']),
			'value' => $eTarget->getValue(),
			'url' => $url
		], fn($value) => $value !== NULL);

	}

	/**
	 * Le nombre de notes de chaque onglet, sous `all` pour « Toutes », sous l'id d'un carnet, et sous 0
	 * pour « Non classé ». Le comptage est groupé par carnet : un carnet sans note lisible n'y a pas de
	 * ligne, d'où le zéro.
	 */
	public static function getTabCounts(\Collection $cNotebook, array $counts): array {

		$tabCounts = ['all' => array_sum($counts)];

		foreach($cNotebook as $eNotebook) {
			$tabCounts[$eNotebook['id']] = $counts[$eNotebook['id']] ?? 0;
		}

		$tabCounts[0] = $counts[0] ?? 0;

		return $tabCounts;

	}

	/**
	 * Le compteur se repère par sa clé et non par un id : le repli des onglets copie l'onglet choisi
	 * dans son menu, et un geste fait dans un panneau réécrit les deux.
	 */
	private function getTab(string $url, string $name, string|int $key, int $count, bool $selected): string {

		return '<a href="'.$url.'" class="tab-item'.($selected ? ' selected' : '').'"><span class="tab-item-name">'.$name.'</span> <small class="util-counter" data-note-count="'.$key.'">'.$count.'</small></a>';

	}

	/**
	 * La liste de l'onglet ouvert, que les gestes faits dans le panneau d'une note redessinent en tout ou
	 * en partie (`#note-list`).
	 */
	public function getList(\structure\Structure $eStructure, \Collection $cNote, \Collection $cNotebook, array $counts, \Search $search, bool $isTabEmpty, int $page, float $nPage, Note $eNoteCreate): string {
		return '<div id="note-list">'.$this->getListContent($eStructure, $cNote, $cNotebook, $counts, $search, $isTabEmpty, $page, $nPage, $eNoteCreate).'</div>';
	}

	private function getListContent(\structure\Structure $eStructure, \Collection $cNote, \Collection $cNotebook, array $counts, \Search $search, bool $isTabEmpty, int $page, float $nPage, Note $eNoteCreate): string {

		$list = self::getListContext($search, $page);

		if($counts === []) {
			return $this->getEmpty($eNoteCreate, $list);
		}

		if($cNote->empty()) {

			if($search->has('target')) {
				$empty = s("Aucune note rattachée à cet objet");
			} else if($search->has('notebook') === FALSE) {
				$empty = s("Aucune note sur cette page");
			} else if($search->get('notebook')->empty()) {
				$empty = s("Aucune note hors des carnets");
			} else {
				$empty = s("Aucune note dans ce carnet");
			}

			$h = '<div class="util-block-empty">';
				$h .= '<h3>'.$empty.'</h3>';

				// Un filtre sans résultat, ou une page au-delà de la dernière, laisse « Nouvelle note » à la
				// rangée de la recherche.
				if($isTabEmpty and $eNoteCreate->assertCreate()) {
					$h .= '<div class="util-buttons">';
						$h .= $this->getCreateButton($eNoteCreate, $list);
					$h .= '</div>';
				}

			$h .= '</div>';

			return $h;

		}

		$h = '<div class="util-cards util-cards-fill">';

			foreach($cNote as $eNote) {
				$h .= $this->getListCard($eStructure, $eNote, $cNotebook, $search, $page);
			}

		$h .= '</div>';

		$h .= \util\TextUi::pagination($page, $nPage);

		return $h;

	}

	/**
	 * La carte d'une note dans la liste de l'onglet ouvert, seule quand un geste fait dans son panneau
	 * ne change qu'elle.
	 */
	public function getListCard(\structure\Structure $eStructure, Note $eNote, \Collection $cNotebook, \Search $search, int $page): string {

		$list = self::getListContext($search, $page);

		// Le carnet d'une note ne se nomme que sous « Toutes » : l'onglet d'un carnet, ou « Non classé »,
		// le nomme déjà.
		$withNotebook = ($cNotebook->notEmpty() and $search->has('notebook') === FALSE);

		$urlTab = self::urlTab($eStructure, $list['tab']);

		$info = \util\DateUi::dayMonthShort($eNote['updatedAt']);

		if($withNotebook) {
			$info .= ' · '.($eNote['notebook']->empty() ? s("Non classé") : encode($cNotebook[$eNote['notebook']['id']]['name']));
		}

		// Sur une carte, le badge filtre la liste sur l'objet, pour tout membre qui la lit.
		$foot = '';

		foreach($eNote['cTarget'] as $eTarget) {
			$foot .= $this->getTarget($eTarget, self::urlTarget($urlTab, $eTarget));
		}

		$foot .= $this->getReaders($eNote);

		return $this->getCard($eNote, self::urlRead($eNote, $list), $info, $foot, withId: TRUE);

	}

	/** La structure n'a aucune note que le membre lise. */
	private function getEmpty(Note $eNoteCreate, array $list): string {

		if($eNoteCreate->assertCreate()) {

			$h = '<div class="util-block-cta">';
				$h .= '<h3>'.s("Aucune note").'</h3>';
				$h .= '<div class="util-buttons">';
					$h .= $this->getCreateButton($eNoteCreate, $list);
				$h .= '</div>';
			$h .= '</div>';

		} else {

			$h = '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucune note").'</h3>';
				$h .= '<p>'.s("Les notes que les administrateurs ouvrent à toute l'équipe paraissent ici.").'</p>';
			$h .= '</div>';

		}

		return $h;

	}

	private function getCreateButton(Note $eNoteCreate, array $list): string {
		return '<a href="'.self::urlCreate($eNoteCreate, $list).'" class="btn btn-primary">'.\Asset::icon('plus-circle').' '.s("Nouvelle note").'</a>';
	}

	/**
	 * Le bloc Notes d'un écran de production (L84) : les notes liées à ce que l'écran montre, qu'on fait
	 * défiler. Chaque carte ouvre sa note en page. Ses badges disent par où elle arrive, l'espèce,
	 * l'intervention ou la planche, et se taisent sur un écran qui n'a qu'une origine (`$withTargets`).
	 * Des lecteurs, seule la marque « Administrateurs » reste : hors de la liste, c'est la seule qui
	 * apprend quelque chose. Sans note, rien.
	 */
	public function getLinked(\structure\Structure $eStructure, \Collection $cNote, bool $withTargets): string {

		if($cNote->empty()) {
			return '';
		}

		\Asset::js('main', 'carouselAi.js');

		$urlStructure = self::urlStructure($eStructure);

		$h = '<div class="util-fold '.($eStructure->getMember()['viewNoteLinked'] ? 'util-fold-open ' : '').'note-linked" data-structure="'.$eStructure['id'].'">';

			$h .= '<a class="util-fold-head" '.attr('onclick', 'NoteLinked.toggle(this)').'>';
				$h .= '<h3>'.s("Notes").' <span class="util-counter">'.$cNote->count().'</span></h3>';
				$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
			$h .= '</a>';

			$h .= '<div class="util-fold-content">';
				$h .= '<div class="util-carousel util-carousel-cards" onrender="MainCarousel.start(this)">';

					$h .= '<div class="util-carousel-track">';

						foreach($cNote as $eNote) {
							$h .= $this->getLinkedCard($eNote, $urlStructure, $withTargets);
						}

					$h .= '</div>';

					if($cNote->count() > 1) {

						$h .= '<div class="util-carousel-dots">';
							$h .= str_repeat('<button type="button"></button>', $cNote->count());
						$h .= '</div>';

					}

				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La carte est la diapositive elle-même : elle prend la hauteur de la plus haute, et son pied se pose
	 * au bas comme dans la liste.
	 */
	private function getLinkedCard(Note $eNote, string $urlStructure, bool $withTargets): string {

		$info = \util\DateUi::dayMonthShort($eNote['updatedAt']);

		if($eNote['notebook']->notEmpty()) {
			$info .= ' · '.encode($eNote['notebook']['name']);
		}

		// Le badge ouvre la liste des notes sur l'objet, toutes celles qui en parlent.
		$foot = '';

		if($withTargets) {
			foreach($eNote['cTarget'] as $eTarget) {
				$foot .= $this->getTarget($eTarget, self::urlTarget($urlStructure, $eTarget));
			}
		}

		if($eNote['readers'] === Note::ADMIN) {
			$foot .= $this->getReaders($eNote);
		}

		return $this->getCard($eNote, self::url($eNote), $info, $foot, withId: FALSE, class: 'util-carousel-slide');

	}

	/** L'ancre de la carte, que la liste fait briller à l'arrivée d'une note créée. */
	public static function getCardId(Note $eNote): string {
		return 'note-card-'.$eNote['id'];
	}

	/**
	 * La carte d'une note. Seule celle de la liste porte son ancre : le bloc Notes d'une série et celui
	 * du panneau d'intervention ouvert par-dessus peuvent montrer la même note.
	 */
	private function getCard(Note $eNote, string $url, string $info, string $foot, bool $withId, string $class = ''): string {

		$h = '<div'.($withId ? ' id="'.self::getCardId($eNote).'"' : '').' class="util-card util-card-link note-card'.($class === '' ? '' : ' '.$class).'">';

			if($eNote['cover'] !== NULL) {

				$h .= '<div class="util-card-bleed note-cover">';
					$h .= '<img src="'.encode(new \media\EditorUi()->getUrlByHash($eNote['cover']['xyz'], 'm', $eNote['cover']['xyzVersion'])).'" alt="" loading="lazy"/>';
				$h .= '</div>';

			}

			$h .= '<div class="util-card-title">';
				$h .= '<a href="'.$url.'">';
					if($eNote['pinned']) {
						$h .= \Asset::icon('pin-angle-fill').' ';
					}
					$h .= encode($eNote['title']);
				$h .= '</a>';
			$h .= '</div>';

			$h .= '<div class="util-card-info">'.$info.'</div>';

			if($eNote['extract'] !== '') {
				$h .= '<p class="util-text-clamp mt-1 mb-0">'.encode($eNote['extract']).'</p>';
			}

			if($foot !== '') {
				$h .= '<div class="note-marks util-card-foot">'.$foot.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La note ouverte en page : la même fiche que dans le panneau, sous l'en-tête de la rubrique. Qui
	 * peut la modifier y trouve son formulaire, comme dans le panneau.
	 */
	public function getRead(\structure\Structure $eStructure, Note $eNote): string {

		if($eNote->assertUpdate()) {
			return $this->getUpdate($eStructure, $eNote, [], inPanel: FALSE);
		}

		return $this->getRecord($eStructure, $eNote, []).$this->getContent($eNote);

	}

	/**
	 * La note ouverte depuis la liste. La tête de la fiche, avec les gestes de la note, devient celle du
	 * panneau (util-panel-tinted, component.css), qui garde sa croix au bout et reste en vue au
	 * défilement ; la fiche ne garde dans le corps que ses branches, accrochées à la tête.
	 *
	 * Qui peut la modifier la modifie là : le corps est son formulaire, et le pied, vide tant que rien
	 * ne change, reçoit « Enregistrer » au premier changement (`NoteUpdate`, note.js). Le titre, dans la
	 * tête, est hors du formulaire et s'y rattache par son attribut form. Le formulaire n'enveloppe pas
	 * le panneau entier (dialogOpen) : un enregistrement repeint la tête, le corps et le pied, jamais
	 * l'enveloppe (`replacePanel`, ajax.js), et le formulaire d'avant resterait en place avec
	 * « Enregistrer » affiché.
	 */
	public function getPanel(\structure\Structure $eStructure, Note $eNote, array $list): \Panel {

		if($eNote->assertUpdate()) {
			$h = $this->getUpdate($eStructure, $eNote, $list, inPanel: TRUE);
		} else {
			$h = $this->getRecord($eStructure, $eNote, $list, withHead: FALSE).$this->getContent($eNote);
		}

		return new \Panel(
			id: 'panel-note',
			documentTitle: encode($eNote['title']),
			header: $this->getRecordMain($eNote, $list),
			body: $h,
			url: self::urlList($eStructure, $list),
			attributes: ['class' => 'util-panel-tinted']
		);

	}

	private static function getFormId(Note $eNote): string {
		return 'note-update-'.$eNote['id'];
	}

	/**
	 * La note en fiche (util-record, component.css) : sa tête porte le titre, le carnet et le jour où
	 * elle a été écrite, le PDF, « Rattacher » et la roue ; chaque branche une information liée, avec son
	 * geste.
	 * « Rattacher » garde sa place que la note ait des objets ou non.
	 *
	 * Le carnet se change par sa marque dans la tête et par la roue (`getActions()`), qui écrivent le
	 * champ caché du formulaire. Les rattachements se changent dans leur propre panneau (`updateTargets()`),
	 * que « Rattacher » ouvre depuis la tête, et leur branche ne paraît que si la note en a.
	 *
	 * Sans sa tête, que le panneau porte (`getPanel()`), la fiche n'a plus de bande teintée à poser.
	 */
	private function getRecord(\structure\Structure $eStructure, Note $eNote, array $list, bool $withHead = TRUE): string {

		$writable = $eNote->assertUpdate();
		$formId = self::getFormId($eNote);

		$h = '<article class="util-record util-record-branches'.($withHead ? ' util-record-tinted' : '').' note-record">';

			if($withHead) {
				$h .= '<div class="util-record-main">'.$this->getRecordMain($eNote, $list).'</div>';
			}

			$h .= '<ul id="'.self::getRecordListId($eNote).'" class="util-record-list">';

				$h .= $this->getTargetsItem($eStructure, $eNote);

				$readers = $writable ? $this->getReadersChoice($eNote, $formId) : $this->getReaders($eNote);

				$h .= \main\DesignUi::getRecordItem(self::p('readers')->label, '<div data-wrapper="readers">'.$readers.'</div>');

			$h .= '</ul>';

		$h .= '</article>';

		return $h;

	}

	public static function getRecordListId(Note $eNote): string {
		return 'note-record-list-'.$eNote['id'];
	}

	/**
	 * La branche des rattachements, que la réponse de leur panneau repose seule : la note ouverte dessous
	 * garde ce qu'on y a changé sans l'enregistrer. Sans rattachement, pas de branche.
	 */
	public function getTargetsItem(\structure\Structure $eStructure, Note $eNote): string {

		if($eNote['cTarget']->empty()) {
			return '';
		}

		// Le badge ouvre toutes les notes sur l'objet, pas celles du seul carnet de la note : c'est
		// l'objet qu'on suit d'une note à l'autre.
		$urlTab = self::urlTab($eStructure, NULL);

		$targets = '<div class="note-marks">';

			foreach($eNote['cTarget'] as $eTarget) {
				$targets .= $this->getTarget($eTarget, self::urlTarget($urlTab, $eTarget));
			}

		$targets .= '</div>';

		return \main\DesignUi::getRecordItem(self::p('cTarget')->label, $targets, class: 'note-targets');

	}

	/**
	 * La tête de la fiche et ses gestes, sans enveloppe : la fiche les pose dans util-record-main, le
	 * panneau dans sa bande.
	 */
	private function getRecordMain(Note $eNote, array $list): string {

		$writable = $eNote->assertUpdate();

		$h = '<div class="util-record-head">';
			$h .= '<span class="util-icon util-icon-lg">'.\Asset::icon('journal-text').'</span>';
			$h .= '<div class="util-record-identity">';
				$h .= '<h3 class="util-record-title">'.$this->getTitle($eNote, $writable).'</h3>';
				$h .= '<div class="util-record-context" data-wrapper="notebook">'.$this->getContext($eNote, $writable, self::getFormId($eNote)).'</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-record-actions">';
			$h .= PdfUi::getDownload(PdfUi::urlNote($eNote));
			if($writable) {
				$h .= '<a href="'.self::urlUpdateTargets($eNote, $list).'" class="btn btn-outline-primary">'.\Asset::icon('link-45deg').'<span class="hide-xs-down"> '.s("Rattacher").'</span></a>';
			}
			$h .= $this->getActions($eNote, $list);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le titre de la note, que qui peut la modifier écrit à sa place. Le pointillé est celui
	 * d'util-quick, porté par l'enveloppe : le champ sans cadre n'a pas où le dessiner avec l'espace
	 * qu'util-quick lui donne sous le texte.
	 */
	private function getTitle(Note $eNote, bool $writable): string {

		if($writable === FALSE) {
			return encode($eNote['title']);
		}

		// Le formulaire se nomme : dans le panneau, le titre est dans la tête, hors du formulaire.
		$field = new \util\FormUi()->dynamicField($eNote, 'title', function(\PropertyDescriber $d) use($eNote) {
			$d->attributes = [
				'class' => 'util-inplace-field util-inplace-field-fit note-update-title',
				'form' => self::getFormId($eNote),
				'onrender' => 'NoteUpdate.sizeTitle(this)'
			];
			$d->placeholder = s("Titre de la note");
		});

		return '<span class="util-quick">'.$field.'</span>';

	}

	/**
	 * Le carnet de la note, puis le jour où elle a été écrite. Qui peut la modifier y trouve la marque
	 * qui change le carnet, « Non classé » compris, dans une structure qui a des carnets ; sans carnet
	 * dans la structure, ce ne serait que du bruit. Qui la lit ne connaît que le carnet de la note.
	 */
	private function getContext(Note $eNote, bool $writable, string $formId): string {

		$date = \util\DateUi::textual($eNote['createdAt'], \util\DateUi::DATE);

		if($writable) {

			if($eNote['cNotebook']->empty()) {
				return s("écrite le {date}", ['date' => $date]);
			}

			$notebook = $this->getNotebookChoice($eNote, $formId);

		} else {

			if($eNote['notebook']->empty()) {
				return s("écrite le {date}", ['date' => $date]);
			}

			$notebook = self::getNotebookLabel($eNote['notebook']);

		}

		return s("{notebook} · écrite le {date}", ['notebook' => $notebook, 'date' => $date]);

	}

	/**
	 * La note à modifier, sous la forme de sa lecture : la fiche, dont le carnet et les lecteurs sont
	 * devenus les menus qui les changent, puis le texte dans l'éditeur sans cadre.
	 *
	 * Les menus du carnet et des lecteurs vivent dans `body` une fois ouverts (`Lime.Dropdown`) : leurs
	 * entrées nomment le formulaire (`data-form`) et écrivent le champ caché qu'il garde.
	 *
	 * « Enregistrer » attend dans un gabarit : en panneau, il va dans le pied, collant, que la lecture
	 * laisse vide ; en page, à la fin du formulaire.
	 */
	private function getUpdate(\structure\Structure $eStructure, Note $eNote, array $list, bool $inPanel): string {

		$eNote->expects(['cNotebook', 'cMemberAdmin']);

		$form = new \util\FormUi();
		$formId = self::getFormId($eNote);

		$h = $form->openAjax('/note/note:doUpdate', ['id' => $formId, 'class' => 'note-update']);

			// La fiche avant les champs cachés : en panneau, la bande s'accroche à la fiche qui ouvre le formulaire.
			$h .= $this->getRecord($eStructure, $eNote, $list, withHead: ($inPanel === FALSE));

			$h .= $form->hidden('id', $eNote);
			$h .= self::getListFields($form, $list);

			if($eNote['cNotebook']->notEmpty()) {
				$h .= $form->hidden('notebook', $eNote['notebook']);
			}

			$h .= $form->hidden('readers', $eNote['readers']);

			$h .= '<div data-wrapper="content">';
				$h .= $form->dynamicField($eNote, 'content', function(\PropertyDescriber $d) {
					// Sans brouillon : fermer le panneau sans enregistrer abandonne ce qui a changé, et un
					// brouillon le rendrait à la réouverture comme si c'était la note.
					$d->options = ['intro' => FALSE, 'inline' => TRUE, 'draft' => NULL] + $d->options;
					$d->attributes = ['class' => 'util-inplace-field'];
				});
			$h .= '</div>';

			$h .= '<template data-note-submit>'.$form->submit(s("Enregistrer"), ['form' => $formId]).'</template>';

			if($inPanel === FALSE) {
				$h .= '<div class="note-update-submit mt-2"></div>';
			}

		$h .= $form->close();

		return $h;

	}

	private function getNotebookChoice(Note $eNote, string $formId): string {

		$h = '<a id="'.$formId.'-notebook" data-dropdown="bottom-start" class="btn btn-badge btn-sm badge badge-outline dropdown-toggle">';
			$h .= '<span class="note-choice-label">'.self::getNotebookLabel($eNote['notebook']).'</span>';
		$h .= '</a>';

		$h .= '<div class="dropdown-list">';
			$h .= $this->getNotebookChoices($eNote, $formId);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les carnets qu'on peut donner à la note, dans la marque de sa tête et dans la roue.
	 */
	private function getNotebookChoices(Note $eNote, string $formId): string {

		$h = $this->getChoice($formId, 'notebook', '', self::getNotebookLabel(new Notebook()), $eNote['notebook']->empty());

		foreach($eNote['cNotebook'] as $eNotebook) {
			$h .= $this->getChoice($formId, 'notebook', (string)$eNotebook['id'], self::getNotebookLabel($eNotebook), $eNote['notebook']->is($eNotebook));
		}

		return $h;

	}

	private static function getNotebookLabel(Notebook $eNotebook): string {
		return $eNotebook->empty() ? s("Non classé") : encode($eNotebook['name']);
	}

	/**
	 * L'entrée « Administrateurs » montre à qui la note reste réservée, comme le choix de la création.
	 *
	 * La marque garde la teinte d'un état qu'on ouvre, sans le gris ni le cadenas de la lecture : l'un
	 * dit « inactif » et l'autre « ne bouge plus », le contraire d'un menu.
	 */
	private function getReadersChoice(Note $eNote, string $formId): string {

		$h = '<a id="'.$formId.'-readers" data-dropdown="bottom-start" class="btn btn-badge btn-sm badge badge-outline dropdown-toggle">';
			$h .= '<span class="note-choice-label">'.self::getReadersLabel($eNote['readers'], withLock: FALSE).'</span>';
		$h .= '</a>';

		$h .= '<div class="dropdown-list">';
			$h .= $this->getChoice($formId, 'readers', Note::ADMIN, self::getReadersLabel(Note::ADMIN, withLock: FALSE), $eNote['readers'] === Note::ADMIN, '<div class="mt-1">'.self::getVignettes($eNote['cMemberAdmin']).'</div>');
			$h .= $this->getChoice($formId, 'readers', Note::TEAM, self::getReadersLabel(Note::TEAM, withLock: FALSE), $eNote['readers'] === Note::TEAM);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une entrée d'un menu de la note à modifier. Son libellé (`note-choice-label`) est celui que la
	 * marque prend quand on la choisit.
	 */
	private function getChoice(string $formId, string $property, string $value, string $label, bool $selected, string $more = ''): string {

		$h = '<a class="dropdown-item'.($selected ? ' selected' : '').'" data-note-choice="'.$property.'" data-form="'.$formId.'" data-value="'.encode($value).'">';
			$h .= '<span class="note-choice-label">'.$label.'</span>';
			$h .= $more;
		$h .= '</a>';

		return $h;

	}

	private function getContent(Note $eNote): string {

		if($eNote['content'] === NULL) {
			return '';
		}

		return new \editor\EditorUi()->value($eNote['content']);

	}

	/**
	 * Le carnet de la note et le jour où elle a été écrite, sous son titre. Dans le PDF d'un carnet,
	 * que son titre nomme déjà, le jour seul.
	 */
	public static function getAnnotation(Note $eNote, bool $withNotebook = TRUE): string {

		$date = \util\DateUi::textual($eNote['createdAt'], \util\DateUi::DATE);

		if($withNotebook === FALSE or $eNote['notebook']->empty()) {
			return s("écrite le {date}", ['date' => $date]);
		} else {
			return s("Carnet {notebook} · écrite le {date}", ['notebook' => encode($eNote['notebook']['name']), 'date' => $date]);
		}

	}

	public function getReaders(Note $eNote): string {

		$eNote->expects(['readers']);

		return match($eNote['readers']) {
			Note::ADMIN => '<span class="badge badge-off">'.self::getReadersLabel(Note::ADMIN, withLock: TRUE).'</span>',
			Note::TEAM => '<span class="badge badge-outline">'.self::getReadersLabel(Note::TEAM, withLock: TRUE).'</span>',
		};

	}

	/**
	 * Le cadenas dit, à la lecture, que la note est fermée au reste de l'équipe. Sur la marque qui ouvre
	 * le menu, il dirait qu'elle ne bouge plus.
	 */
	private static function getReadersLabel(string $readers, bool $withLock): string {

		return match($readers) {
			Note::ADMIN => ($withLock ? \Asset::icon('lock-fill').' ' : '').s("Administrateurs"),
			Note::TEAM => s("Toute l'équipe"),
		};

	}

	/**
	 * Les administrateurs de la structure, à la suite du choix qui leur réserve la note : celui qui
	 * écrit voit qui la lira.
	 */
	private static function getAdmins(\Collection $cMemberAdmin): string {
		return self::getVignettes($cMemberAdmin, '<span>'.self::getReadersLabel(Note::ADMIN, withLock: FALSE).'</span>');
	}

	private static function getVignettes(\Collection $cMemberAdmin, string $before = ''): string {

		$h = '<span class="util-vignettes">';

			$h .= $before;

			foreach($cMemberAdmin as $eMember) {
				$h .= '<span class="util-vignette">'.\user\UserUi::getVignette($eMember['user'], '1.5rem').'<span>'.encode($eMember['user']['firstName'] ?? $eMember['user']['lastName']).'</span></span>';
			}

		$h .= '</span>';

		return $h;

	}

	private function getTarget(Target $eTarget, string $url): string {

		$ui = NoteSetting::resolve($eTarget['targetType'], 'NoteTargetUi');

		$content = '';

		$icon = $ui::getIcon($eTarget['eElement']);

		if($icon !== NULL) {
			$content .= '<svg class="asset-icon"><use xlink:href="'.$icon.'"/></svg> ';
		}

		$content .= encode($ui::getLabel($eTarget['eElement']));

		return '<a href="'.$url.'" class="btn btn-badge btn-sm badge badge-outline badge-info">'.$content.'</a>';

	}

	/**
	 * La roue de la note. Elle redit le carnet que la marque de la tête change déjà, sous son nom : qui
	 * cherche où ranger la note ouvre la roue avant de penser à la marque. Ses entrées écrivent le
	 * champ caché du formulaire, comme le menu des lecteurs.
	 *
	 * Ouverte depuis la liste, la note y renvoie ses gestes, qui rafraîchissent la liste sous le panneau.
	 */
	public function getActions(Note $eNote, array $list = []): string {

		if($eNote->assertUpdate() === FALSE) {
			return '';
		}

		$eNote->expects(['cNotebook']);

		$formId = self::getFormId($eNote);
		$posts = ($list === []) ? '' : ' '.attrs(self::getListValues($list), 'post-');

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';

		$h .= '<div class="dropdown-list bg-primary">';

			if($eNote['cNotebook']->notEmpty()) {

				$h .= '<div class="dropdown-subtitle">'.self::p('notebook')->label.'</div>';
				$h .= $this->getNotebookChoices($eNote, $formId);
				$h .= '<div class="dropdown-divider"></div>';

			}

			$h .= $this->getPin($eNote, $list);

			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/note/note:doDelete" post-id="'.$eNote['id'].'"'.$posts.' data-confirm="'.s("Supprimer cette note ?").'" class="dropdown-item">'.s("Supprimer la note").'</a>';

		$h .= '</div>';

		return $h;

	}

	public static function getPinId(Note $eNote): string {
		return 'note-pin-'.$eNote['id'];
	}

	/**
	 * L'entrée de l'épingle, que la réponse remplace seule : le reste de la roue, dont le carnet
	 * peut-être choisi sans être encore enregistré, ne change pas.
	 */
	public function getPin(Note $eNote, array $list = []): string {

		$posts = ($list === []) ? '' : ' '.attrs(self::getListValues($list), 'post-');

		return '<a id="'.self::getPinId($eNote).'" data-ajax="/note/note:doUpdatePinned" post-id="'.$eNote['id'].'" post-pinned="'.($eNote['pinned'] ? '0' : '1').'"'.$posts.' class="dropdown-item">'.($eNote['pinned'] ? s("Ne plus épingler en tête des notes") : s("Épingler en tête des notes")).'</a>';

	}

	/**
	 * Les rattachements de la note, par-dessus son panneau : on y ajoute un objet comme on en retire un, et
	 * l'enregistrement ne touche qu'eux.
	 */
	public function updateTargets(Note $eNote, array $list): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/note/note:doUpdateTargets');

			$h .= $form->hidden('id', $eNote);
			$h .= self::getListFields($form, $list);
			$h .= $form->dynamicGroup($eNote, 'cTarget');
			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-note-targets',
			title: s("Rattacher la note"),
			subTitle: encode($eNote['title']),
			body: $h
		);

	}

	/**
	 * Le panneau garde l'adresse de la liste d'où il s'ouvre (`urlList()`), comme celui de la note.
	 */
	public function create(Note $eNote, array $list): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/note/note:doCreate', ['class' => 'note-form']);

			$h .= $form->hidden('structure', $eNote['structure']);
			$h .= self::getListFields($form, $list);
			$h .= $form->dynamicGroups($eNote, self::getFormProperties($eNote));
			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-note-create',
			title: s("Nouvelle note"),
			body: $h,
			url: ($list === []) ? NULL : self::urlList($eNote['structure'], $list)
		);

	}

	/**
	 * Le carnet ne se choisit que dans une structure qui en a : sans carnet, toutes les notes sont
	 * « Non classé », et le champ n'offrirait que cette valeur.
	 */
	private static function getFormProperties(Note $eNote): array {

		$eNote->expects(['cNotebook']);

		$properties = ['title', 'notebook', 'cTarget', 'content', 'readers'];

		if($eNote['cNotebook']->empty()) {
			$properties = array_diff($properties, ['notebook']);
		}

		return array_values($properties);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Note::model()->describer($property, [
			'title' => s("Titre"),
			'notebook' => s("Carnet"),
			'content' => s("Contenu"),
			'cTarget' => s("Rattacher à"),
			'readers' => s("Qui peut la lire"),
		]);

		switch($property) {

			case 'notebook' :
				$d->values = fn(Note $e) => $e['cNotebook']->getColumn('name', 'id');
				$d->placeholder = s("Non classé");
				break;

			case 'content' :
				$d->options = NoteSetting::getEditorOptions();
				break;

			// L'omnibox en champ : chaque jeton poste sa valeur dans `cTarget[]`, et les suggestions viennent
			// du serveur, qui cherche dans tout ce que les packages apportent.
			case 'cTarget' :
				$d->field = fn(\util\FormUi $form, Note $e) => \main\OmniboxUi::get(
					suggestionsUrl: '/note/note:queryTarget',
					suggestionsBody: [
						'id' => $e['structure']['id']
					],
					tokens: $e['cTarget']->makeArray(fn(Target $eTarget) => NoteUi::getTargetToken($eTarget)),
					placeholder: s("Espèce, intervention, emplacement..."),
					name: 'cTarget'
				);
				// L'omnibox écrit ses champs cachés elle-même, sans passer par le formulaire qui nommerait
				// le groupe : sans ce nom, un refus tomberait dans le bandeau au lieu du champ.
				$d->wrapper = 'cTarget';
				break;

			case 'readers' :
				$d->field = 'radio';
				$d->values = fn(Note $e) => [
					Note::ADMIN => self::getAdmins($e['cMemberAdmin']),
					Note::TEAM => self::getReadersLabel(Note::TEAM, withLock: FALSE),
				];
				$d->required = TRUE;
				break;

		}

		return $d;

	}

}
?>
