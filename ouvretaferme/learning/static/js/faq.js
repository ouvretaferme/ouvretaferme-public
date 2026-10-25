/**
 * La recherche et le filtre de la FAQ publiée, par l'omnibox en mode local, sur les deux pages qui
 * la rendent (`FaqUi`) : `/faq`, où le rail filtre par écran, et `/reseau/{fqn}/faq`, où il filtre
 * par module. Un seul chemin pour les deux : la racine de la liste nomme la dimension du rail
 * (`data-filter`), que portent chaque question et chaque option du rail, « Tous » excepté.
 *
 * Les suggestions se relèvent sur la page, qui porte déjà toutes les questions et leurs réponses :
 * une question se trouve par son texte, et aussi par sa réponse courte, ses symptômes et ses
 * mots-clés, que l'omnibox lit sans les afficher (`terms`). La choisir l'ouvre.
 *
 * Tout ce qui vient de la FAQ se lit par `textContent` et part dans des champs que l'omnibox écrit
 * en texte (`label`, `detail`, `terms`), jamais dans `sub` ni `vignette`, qu'elle écrit en HTML.
 *
 * Le sommaire des tutoriels mène aux questions d'un module par l'ancre `#module-{id}`. Sur `/faq`,
 * le module se pose en jeton et masque les autres questions ; sur un réseau, où le rail est celui
 * des modules, l'ancre y choisit le sien. L'ancre ne donne que l'identifiant, le nom du jeton se lit
 * sur une question du module : une adresse forgée ne fait rien écrire.
 */
document.addEventListener('navigation.wakeup', () => LearningFaq.restoreChapter());

document.delegateEventListener('omniboxsuggest', '#learning-faq-search', function() {
	UtilOmnibox.setSuggestions(this, LearningFaq.suggest());
});

document.delegateEventListener('omnibox', '#learning-faq-search', function() {
	LearningFaq.apply(this);
});

document.delegateEventListener('omniboxpick', '#learning-faq-search', function(e) {

	const item = e.detail.item;

	if(item.dataset.group !== LearningFaq.getGroupQuestions()) {
		return;
	}

	e.preventDefault();

	LearningFaq.open(this, item.dataset.value);

});

document.delegateEventListener('click', '#learning-faq-list .util-filter-option', function() {
	LearningFaq.select(this);
});

class LearningFaq {

	static getList() {
		return qs('#learning-faq-list');
	}

	static getGroupQuestions() {
		return LearningFaq.getList().dataset.groupQuestions;
	}

	static getGroupChapters() {
		return LearningFaq.getList().dataset.groupChapters;
	}

	static getBox() {
		return qs('#learning-faq-search');
	}

	static restoreChapter() {

		const list = LearningFaq.getList();
		const match = location.hash.match(/^#module-(\d+)$/);

		if(list === null || match === null) {
			return;
		}

		if(list.dataset.filter === 'chapter') {

			const option = list.qs('.util-filter-option[data-chapter="'+ match[1] +'"]');

			if(option !== null) {
				LearningFaq.select(option);
			}

			return;

		}

		const item = list.qs('.presentation-faq-item[data-chapter="'+ match[1] +'"]');
		const box = LearningFaq.getBox();

		if(item === null || box === null) {
			return;
		}

		UtilOmnibox.addToken(box, LearningFaq.getGroupChapters(), item.dataset.chapter, item.dataset.chapterName);
		UtilOmnibox.change(box);

	}

	static suggest() {

		const list = LearningFaq.getList();

		if(list === null) {
			return [];
		}

		const filter = list.dataset.filter;
		const questions = [];

		list.qsa('.presentation-faq-item', item => {

			// Le texte rendu par l'éditeur, sans le script qu'`EditorUi::value()` pose à côté : son
			// code ferait trouver toutes les questions à « reador » ou à « instance ».
			const terms = [...item.qsa('.presentation-faq-lead > .reador, .presentation-faq-symptoms > .reador')].map(text => text.textContent);

			if(item.dataset.keywords !== undefined) {
				terms.push(item.dataset.keywords);
			}

			questions.push({
				group: LearningFaq.getGroupQuestions(),
				value: item.id,
				label: item.qs('.presentation-faq-question').textContent,
				detail: list.qs('.util-filter-option[data-'+ filter +'="'+ item.dataset[filter] +'"] [data-faq-label]').textContent,
				terms: terms.join(' ')
			});

		});

		return questions;

	}

	/*
	 * Une option du rail choisit l'écran ou le module, « Tous » n'en porte aucun.
	 */
	static select(option) {

		LearningFaq.mark(option);
		LearningFaq.apply(LearningFaq.getBox());

	}

	/*
	 * Sur un écran étroit, le rail devient une rangée défilante, et l'option choisie y revient en vue.
	 * Elle se choisit ici après le rendu, par l'ancre, le clic ou l'omnibox, hors de portée de `parseRender()`.
	 */
	static mark(option) {

		LearningFaq.getList().qsa('.util-filter-option', other => other.classList.toggle('is-selected', other === option));

		Lime.Tab.reveal([option]);

	}

	/*
	 * La valeur se lit sur l'option choisie du rail, dans sa dimension. Sur `/faq`, le jeton d'un
	 * module masque de plus les questions d'un autre module. Les décomptes suivent ce qui reste en
	 * vue : celui de chaque option, celui de « Tous », et le total, dans l'annotation de la liste.
	 *
	 * Sous ce jeton, une option sans question quitte le rail : elle ne mènerait qu'à une liste vide.
	 * Si c'était l'option choisie, le rail revient sur « Tous », qui dit alors ne compter que le
	 * module.
	 */
	static apply(box) {

		const list = LearningFaq.getList();

		if(list === null) {
			return;
		}

		const filter = list.dataset.filter;
		const tokenChapter = UtilOmnibox.getTokens(box).find(token => token.group === LearningFaq.getGroupChapters());

		const items = list.qsa('.presentation-faq-item');
		const inChapter = item => (tokenChapter === undefined || tokenChapter.value === item.dataset.chapter);

		const counts = {};

		items.forEach(item => {
			if(inChapter(item)) {
				counts[item.dataset[filter]] = (counts[item.dataset[filter]] ?? 0) + 1;
			}
		});

		list.qsa('.util-filter-option[data-'+ filter +']', option => {

			const found = counts[option.dataset[filter]] ?? 0;

			option.qs('.util-counter').textContent = found;
			option.classList.toggle('hide', found === 0);

		});

		const all = list.qs('.util-filter-option:not([data-'+ filter +'])');

		if(list.qs('.util-filter-option.is-selected.hide') !== null) {
			LearningFaq.mark(all);
		}

		all.qs('[data-faq-all]').textContent = (tokenChapter === undefined) ? all.dataset.labelAll : all.dataset.labelChapter;
		all.qs('.util-counter').textContent = Object.values(counts).reduce((sum, value) => sum + value, 0);

		const value = list.qs('.util-filter-option.is-selected').dataset[filter];

		let count = 0;

		// Une question se masque par `hide` et non par `hidden` : le `display` de `util-deck`
		// l'emporte sur celui que le navigateur donne à `[hidden]`.
		items.forEach(item => {

			const hide = (inChapter(item) === false || (value !== undefined && value !== item.dataset[filter]));

			item.classList.toggle('hide', hide);

			if(hide === false) {
				count++;
			}

		});

		list.qs('[data-faq-count]').textContent = (count === 1 ? list.dataset.countOne : list.dataset.countMany).replace('{value}', count);

	}

	/*
	 * Une question choisie s'ouvre et vient en vue. Elle revient si un filtre la masquait : jetons
	 * retirés et rail remis sur « Tous ». La question choisie n'a pas à disparaître
	 * derrière le filtre posé avant.
	 */
	static open(box, id) {

		const item = qs('#'+ id);

		if(item === null) {
			return;
		}

		if(item.classList.contains('hide')) {

			box.qsa('.util-omnibox-token', token => token.remove());

			const list = LearningFaq.getList();

			LearningFaq.mark(list.qs('.util-filter-option:not([data-'+ list.dataset.filter +'])'));

			UtilOmnibox.change(box);

		}

		item.classList.add('util-deck-open');

		box.qs('.util-omnibox-input').value = '';

		// Comme un choix de l'omnibox (`UtilOmnibox.pick()`) : au doigt, le panneau plein écran se
		// referme sur la question ouverte. Le défilement attend la fermeture, qui rend à la page celui
		// qu'elle avait à l'ouverture.
		if(box.omniboxPlaceholder !== undefined) {
			UtilOmnibox.closeFullscreen(box).then(() => item.scrollIntoView({block: 'start', behavior: 'smooth'}));
		} else {
			UtilOmnibox.close(box);
			item.scrollIntoView({block: 'start', behavior: 'smooth'});
		}

	}

}
