/**
 * Ranger à la souris les listes des écrans d'auteur : thématiques, niveaux, modules d'une
 * formation et leçons d'un module.
 *
 * Une liste se déclare par son balisage : `data-sort-url` (l'action qui enregistre l'ordre),
 * `data-sort-handle` (la poignée, `.util-drag-handle` à défaut), les attributs `post-*` (ce que
 * l'action reçoit en plus de l'ordre), et un `data-sort-id` sur chaque élément rangé.
 */
class LearningSort {

	/**
	 * Le rendu peut repasser sur une liste déjà armée (onrender après une navigation ajax) : le
	 * drapeau évite d'armer deux Sortable sur la même liste, qui enregistreraient deux fois.
	 */
	static init(node) {

		if(node.dataset.sortReady === '1') {
			return;
		}

		node.dataset.sortReady = '1';

		new Sortable(node, {
			handle: node.dataset.sortHandle || '.util-drag-handle',
			draggable: '[data-sort-id]',
			ghostClass: 'util-drag-ghost',
			chosenClass: 'util-drag-chosen',
			forceFallback: true,
			fallbackOnBody: true,
			onEnd: e => {

				if(e.oldIndex === e.newIndex) {
					return;
				}

				LearningSort.save(node);

			}
		});

	}

	static save(node) {

		const ids = Array.from(node.children)
			.filter(item => item.dataset.sortId !== undefined)
			.map(item => parseInt(item.dataset.sortId));

		const body = {
			positions: JSON.stringify(ids)
		};

		Array.from(node.attributes)
			.filter(attribute => attribute.name.startsWith('post-'))
			.forEach(attribute => body[attribute.name.substring(5)] = attribute.value);

		LearningSort.renumber(node.closest('[data-sort-numbering]') ?? node);

		new Ajax.Query()
			.url(node.dataset.sortUrl)
			.body(body)
			.fetch();

	}

	/**
	 * Les leçons sont numérotées sur toute la formation, les modules dans la formation, les
	 * questions dans leur quiz : un déplacement change les numéros de tout ce qui suit, qu'on
	 * réécrit sans recharger l'écran.
	 */
	static renumber(root) {

		['chapter', 'lesson', 'question'].forEach(kind => {

			let number = 0;

			root.qsa('[data-number="'+ kind +'"]', item => item.textContent = ++number);

		});

	}

}

/**
 * Le dépôt d'un fichier sur une leçon, dès qu'il est choisi.
 *
 * Le champ est vidé dès que son fichier est repris dans le corps de la requête : sans cela, choisir
 * de nouveau le même fichier ne déclencherait aucun `change`. La réponse remplace la liste des
 * fichiers, champ compris.
 */
class LearningAttachment {

	static upload(input) {

		if(input.files.length === 0) {
			return;
		}

		const file = input.files[0];

		input.value = '';

		// Au-delà de la limite de nginx, la requête s'arrête avant PHP et ne revient qu'en 413, sans
		// message : le poids se juge donc aussi ici, le serveur gardant le dernier mot.
		if(file.size > parseInt(input.dataset.sizeMax)) {
			Lime.Alert.showStaticError(input.dataset.sizeError);
			return;
		}

		const body = new FormData();
		body.append('lesson', input.dataset.lesson);
		body.append('file', file);

		// Le contexte verrouille la liste pendant l'envoi et refuserait sans rien dire un second
		// fichier choisi entre-temps : le champ ne s'ouvre plus tant que le premier n'est pas parti.
		input.disabled = true;

		new Ajax.Query(input.closest('#lesson-attachments'))
			.url(input.dataset.url)
			.body(body)
			.fetch()
			.finally(() => input.disabled = false);

	}

}

/**
 * Les promotions du panneau de partage : une case décochée n'envoie pas ses dates, qui restent
 * désactivées. La cocher les rouvre, et remet l'ouverture du jour dans un champ vide. La case et
 * ses deux dates sont dans le groupe de leur promotion.
 */
class LearningShare {

	static toggle(checkbox) {

		const row = checkbox.closest('.form-group');

		row.querySelectorAll('input[type="date"]').forEach(input => input.disabled = (checkbox.checked === false));

		const opensAt = row.querySelector('input[data-default]');

		if(checkbox.checked && opensAt.value === '') {
			opensAt.value = opensAt.dataset.default;
		}

	}

}

/**
 * La question de la prise en charge, dans le panneau d'inscription (`EnrollmentUi::create()`) : la
 * demander fait attendre la confirmation du réseau, et le bouton d'envoi le dit en devenant
 * « Envoyer la demande », le titre du panneau et celui de l'onglet avec lui (le calque rend le titre
 * d'avant à sa fermeture, `Lime.History.removeLayer()`). Ses deux mots sont posés par le serveur
 * (`data-label`, `data-label-requested`).
 * `onchange` est porté par le groupe des choix, où remonte le changement de l'un d'eux.
 * La classe ouvre aussi ce panneau à l'arrivée sur la fiche (`openOnArrival()`).
 */
class LearningEnrollment {

	static changeFunding(group) {

		const submit = group.closest('form').qs('[data-label-requested]');
		const requested = (group.qs('input:checked').value === '1');
		const label = requested ? submit.dataset.labelRequested : submit.dataset.label;

		submit.textContent = label;
		group.closest('.panel').qs('.panel-title', title => title.textContent = label);
		document.title = label;

	}

	/**
	 * Le panneau ouvert à l'arrivée sur la fiche (`EnrollmentUi::getPanelOnArrival()`). `enroll` est
	 * retiré de l'adresse avant l'ouverture : le calque y revient à la croix, et un rechargement de
	 * la fiche ne doit pas rouvrir le panneau.
	 */
	static openOnArrival(url) {

		const location = new URL(document.location.href);
		location.searchParams.delete('enroll');

		Lime.History.replaceState(location.href);

		new Ajax.Navigation().url(url).fetch();

	}

}

/**
 * La thématique d'une formation, dans ses réglages et dans l'étape « Présentez la formation » : la
 * changer recharge le groupe de la sous-thématique, qui ne propose que celles de la thématique
 * choisie. La liste sert de contexte à la requête, et non le formulaire : le verrou d'`Ajax.Query`
 * désactiverait le bouton d'envoi le temps de la requête, et un envoi tenté pendant ce temps
 * lèverait `LOCK_FAILED` sans rien dire, la formation n'étant pas créée (« Créer la formation »
 * muet, retour du 05/10). L'erreur précédente du formulaire s'efface donc à part. Les deux
 * formulaires ne rangent pas leurs groupes de la même façon : la route reçoit le nombre de colonnes
 * pour rendre le groupe comme ses voisins.
 */
class LearningCourse {

	static changeTheme(select) {

		const form = select.closest('form');
		const id = form.querySelector('[name="id"]');

		const body = {
			theme: select.value,
			columns: form.classList.contains('form-columns') ? 2 : 1
		};

		if(id !== null) {
			body.id = id.value;
		}

		Lime.Alert.hideErrors(form);

		new Ajax.Query(select)
			.url('/learning/course:getSubthemeField')
			.body(body)
			.fetch();

	}

}

/**
 * L'ajout d'un encadrant que le réseau connait déjà : le rôle habituel de la personne choisie
 * remplit « Rôle dans la formation », tant que le champ est vide ou porte encore le rôle proposé
 * pour la personne choisie avant elle. Un rôle saisi à la main n'est jamais remplacé.
 */
class LearningStaff {

	static proposeRole(select) {

		const input = select.closest('fieldset').querySelector('[name="role"]');
		const role = select.selectedOptions[0].dataset.role ?? '';

		if(input.value === '' || input.value === input.dataset.proposed) {
			input.value = role;
			input.dataset.proposed = role;
		}

	}

}

/**
 * La case « Constatée » de la vue d'une leçon, dans le suivi des stagiaires : cocher et décocher
 * écrivent aussitôt, et la réponse redessine la ligne du stagiaire, dont l'état a pu changer depuis
 * le chargement. La ligne sert de contexte à la requête, ce qui la verrouille le temps de
 * l'écriture et efface l'erreur précédente. La grille ne porte aucune case : c'est le seul appelant.
 */
class LearningTrack {

	static check(checkbox) {

		new Ajax.Query(checkbox.closest('.util-table-row'))
			.url(checkbox.checked ? '/learning/progress:doCheck' : '/learning/progress:doUncheck')
			.body({
				id: checkbox.dataset.course,
				lesson: checkbox.dataset.lesson,
				user: checkbox.dataset.user
			})
			.fetch();

	}

}

/**
 * La case « Présente » de la liste des inscrits d'une date finie (lot L8e) : cocher et décocher
 * écrivent aussitôt, et la réponse redessine la ligne et le compte des présentes sous le titre. La
 * ligne sert de contexte à la requête, comme pour `LearningTrack.check()`.
 */
class LearningDate {

	static attend(checkbox) {

		new Ajax.Query(checkbox.closest('.util-table-row'))
			.url(checkbox.checked ? '/learning/dateEnrollment:doAttend' : '/learning/dateEnrollment:doUnattend')
			.body({
				id: checkbox.dataset.id
			})
			.fetch();

	}

	/**
	 * Les horaires des trois boutons qui ajoutent une séance (`DateSequenceUi::getField()`).
	 */
	static presets = {
		day: ['09:30', '17:00'],
		morning: ['09:30', '12:30'],
		afternoon: ['14:00', '17:00']
	};

	/**
	 * Ajoute une séance préréglée sous les autres, dans le mode et au lieu de la dernière. Journée et
	 * Matin se posent le lendemain de la dernière séance ; Après-midi le même jour quand aucune séance
	 * n'en occupe déjà l'après-midi, sinon le lendemain. Sans séance avant elle, la ligne garde le
	 * mode du gabarit et attend son jour. La ligne prend l'index qui suit le dernier donné
	 * (`data-index`), jamais le nombre de lignes : après un retrait, deux lignes porteraient le même
	 * index, et le refus de l'une s'afficherait sous l'autre.
	 */
	static add(button, preset) {

		// Les boutons suivent la liste, le gabarit les suit (`DateSequenceUi::getField()`).
		const buttons = button.parentElement;
		const list = buttons.previousElementSibling;
		const index = parseInt(list.dataset.index);

		const rows = Array.from(list.children);
		const last = rows.length > 0 ? rows[rows.length - 1] : null;

		list.dataset.index = index + 1;
		list.insertAdjacentHTML('beforeend', buttons.nextElementSibling.innerHTML.replaceAll('__i__', index));

		const row = list.lastElementChild;
		const [start, end] = LearningDate.presets[preset];

		row.qs('[name="startAt[' + index + '][time]"]').value = start;
		row.qs('[name="endAt[' + index + '][time]"]').value = end;

		if(last !== null) {

			const lastDay = LearningDate.getRowValue(last, 'startAt', '[date]');

			if(lastDay !== '') {

				const afternoonTaken = rows.some(other =>
					LearningDate.getRowValue(other, 'startAt', '[date]') === lastDay && (
						LearningDate.getRowValue(other, 'endAt', '[time]') > '13:00' ||
						LearningDate.getRowValue(other, 'startAt', '[time]') >= '12:00'
					)
				);

				row.qs('[name="startAt[' + index + '][date]"]').value = (preset === 'afternoon' && afternoonTaken === false) ?
					lastDay :
					LearningDate.getNextDay(lastDay);

			}

			row.qs('[name="mode[' + index + ']"]').value = LearningDate.getRowValue(last, 'mode', '');
			row.qs('[name="place[' + index + ']"]').value = LearningDate.getRowValue(last, 'place', '');

		}

		LearningDate.changeMode(row.qs('[name="mode[' + index + ']"]'));
		LearningDate.updateSchedule(row);

		row.qs('input[type="date"]').focus();

	}

	static remove(link) {

		const form = link.closest('form');

		link.closest('.onboarding-row').remove();

		LearningDate.updateLink(form);
		LearningDate.updateSchedule(form);

	}

	/**
	 * La valeur d'un champ d'une ligne, nommé `<propriété>[<index>]<suite>` : l'index se lit sur le
	 * champ caché `sequences[]` de la ligne.
	 */
	static getRowValue(row, property, suffix) {

		const index = row.qs('[name="sequences[]"]').value;

		return row.qs('[name="' + property + '[' + index + ']' + suffix + '"]').value;

	}

	/**
	 * Le lendemain d'un jour `AAAA-MM-JJ`, compté en UTC, que le changement d'heure ne décale pas.
	 */
	static getNextDay(day) {

		const date = new Date(Date.UTC(parseInt(day.substring(0, 4)), parseInt(day.substring(5, 7)) - 1, parseInt(day.substring(8, 10)) + 1));

		return date.toISOString().substring(0, 10);

	}

	/**
	 * Le lieu ne se demande qu'en présentiel, le lien de la visio que lorsqu'une séance au moins
	 * l'est. Le lieu masqué garde sa saisie, que la ligne retrouve si elle revient en présentiel.
	 */
	static changeMode(select) {

		const row = select.closest('.onboarding-row');

		row.qs('[data-wrapper^="place["]').classList.toggle('hide', select.value !== 'onsite');

		LearningDate.updateLink(select.closest('form'));

	}

	static updateLink(form) {

		const link = form.qs('[data-wrapper="link"]');

		if(link === null) {
			return;
		}

		const remote = Array.from(form.querySelectorAll('[name^="mode["]')).some(select => select.value === 'remote');

		link.classList.toggle('hide', remote === false);

	}

	/**
	 * La durée que donnent les séances, montrée en indication dans le champ « Durée de formation »
	 * tant qu'il est vide (`data-schedule`, posé par `DateUi::p('minutes')`) : elle suit les jours et
	 * les horaires à chaque saisie. Le champ en édition rapide n'a pas de séances à côté de lui, et
	 * garde l'indication du serveur.
	 */
	static updateSchedule(element) {

		const form = element.tagName === 'FORM' ? element : element.closest('form');
		const minutes = form.qs('[data-schedule]');

		if(minutes === null) {
			return;
		}

		const rows = Array.from(form.querySelectorAll('.learning-date-sequences > .onboarding-row'));

		const sequences = rows.map(row => [
			LearningDate.getRowValue(row, 'startAt', '[date]'),
			LearningDate.getRowValue(row, 'startAt', '[time]'),
			LearningDate.getRowValue(row, 'endAt', '[time]')
		]);

		const day = rows.length > 0 ? rows[0].qs('input[type="date"]') : null;

		minutes.placeholder = day === null ? '' : LearningDate.getScheduleHours(sequences, day.min, day.max);

	}

	/**
	 * Le même calcul que `Date::getScheduleMinutesProposed()` et le même arrondi que
	 * `DateUi::getHoursPlaceholder()` : la somme des heures des séances, chacune de son début à sa
	 * fin, une séance qui finit avant de commencer ne comptant rien. Rien tant qu'une saisie manque,
	 * ni tant qu'un jour sort des bornes que le champ porte (`min` et `max`, posés par
	 * `FormUi::date()`) : une année en cours de frappe vaut 0002, puis 0020, puis 0202. Rien non plus
	 * quand les séances ne comptent aucune heure. `test/DateForm.t.php` joue les deux calculs sur les
	 * mêmes horaires.
	 */
	static getScheduleHours(sequences, min, max) {

		if(sequences.length === 0) {
			return '';
		}

		const getMinutes = time => parseInt(time.substring(0, 2)) * 60 + parseInt(time.substring(3, 5));

		let minutes = 0;

		for(const [day, start, end] of sequences) {

			if(day === '' || start === '' || end === '') {
				return '';
			}

			if(day < min || day > max) {
				return '';
			}

			minutes += Math.max(0, getMinutes(end) - getMinutes(start));

		}

		if(minutes === 0) {
			return '';
		}

		// Un texte et non une valeur : la virgule et l'espace des milliers de `TextUi::number()`.
		return (Math.round(minutes / 60 * 100) / 100).toLocaleString('fr-FR');

	}

}

/**
 * Le dépôt de la feuille signée d'une date, dès que les fichiers sont choisis. Le serveur reçoit un
 * fichier par requête : plusieurs fichiers choisis d'un coup partent l'un après l'autre, chacun
 * attendant la réponse du précédent, qui a remplacé la section et son contexte. Un fichier refusé
 * (trop lourd, d'un autre type, au-delà du dixième) n'arrête pas les suivants, que le serveur juge
 * chacun. Le champ est vidé aussitôt, pour que le même choix redéclenche `change`.
 */
class LearningDateSheet {

	static async upload(input) {

		if(input.files.length === 0) {
			return;
		}

		const files = Array.from(input.files);
		const url = input.dataset.url;
		const date = input.dataset.date;
		const sizeMax = parseInt(input.dataset.sizeMax);
		const sizeError = input.dataset.sizeError;

		input.value = '';
		input.disabled = true;

		for(const file of files) {

			// Au-delà de la limite de nginx, la requête s'arrête avant PHP et ne revient qu'en 413, sans
			// message : le poids se juge donc aussi ici, le serveur gardant le dernier mot.
			if(file.size > sizeMax) {
				Lime.Alert.showStaticError(sizeError);
				continue;
			}

			const body = new FormData();
			body.append('date', date);
			body.append('file', file);

			// La section est relue à chaque envoi : la réponse du précédent l'a remplacée.
			const section = document.getElementById('learning-date-sheets');

			if(section === null) {
				return;
			}

			try {
				await new Ajax.Query(section)
					.url(url)
					.body(body)
					.fetch();
			} catch(e) {
				// L'échec a déjà été affiché par la chaîne d'erreur ; le fichier suivant part quand même.
			}

		}

		// Le champ d'origine a pu être remplacé par une réponse, qui en porte un neuf : on ne rouvre
		// que s'il est encore dans le document.
		if(input.isConnected) {
			input.disabled = false;
		}

	}

}

/**
 * L'écran d'édition d'une leçon (`LessonUi::getEdition()`). Le panneau « Modifier la leçon » ramène
 * sur l'onglet d'où sa roue est cliquée : le lien de la roue porte cet onglet (`origin`), que le
 * panneau reposte.
 */
class LearningLesson {

	// Le délégué de navigation lit un lien en capture, avant tout `onclick` posé sur lui : l'origine
	// s'écrit quand l'onglet change, et non quand la roue est cliquée.
	static updateOrigin() {

		const origin = qs('[id^="learning-lesson-tab-"].selected').dataset.tab;

		qs('a[href^="/learning/lesson:updateSettings"]', gear => gear.setAttribute('href', gear.getAttribute('href').setArgument('origin', origin)));

	}

	// `innerHtml` sur un `.tab-item` ne relance pas le repli de sa rangée : `renderInner()` ne rend que
	// les éléments insérés, et un libellé sans compteur n'en porte aucun. Le repli se relance donc depuis
	// la tête d'onglet, dont la rangée peut être dans la bande d'un groupe et non sous le `.tabs` des
	// panneaux.
	static refreshTabs() {

		qs('#learning-lesson-tab-content', tab => Lime.TabFold.refresh([tab.firstParent('.tabs-h-fold')]));

	}

}

/**
 * La carte de la vidéo d'une leçon (`LessonUi::getVideoCard()`), dont le titre mène aux étapes. La
 * page porte une `<base>`, contre laquelle une ancre seule se résout en l'accueil du site : le
 * défilement se fait ici, sous la barre collée (`scroll-margin-top`, `learning.css`).
 */
class LearningVideoCard {

	static toSteps(event) {

		event.preventDefault();

		qs('#learning-lesson-steps', steps => steps.scrollIntoView());

	}

}

/**
 * Le quiz d'une leçon, du côté de l'auteur : le panneau d'une question et ses choix, puis le seuil
 * de réussite dans le panneau de la leçon.
 *
 * « Juste » est un bouton radio ou une case sans nom, selon le type de la question : il pilote le
 * champ caché de sa ligne, `choices[correct][<rang>]`, que le formulaire poste toujours. Les boutons
 * radio n'ont pas de nom commun, pour que rien d'autre que le champ caché ne parte : leur exclusion
 * mutuelle se fait donc ici.
 */
class LearningQuiz {

	static init(form) {

		if(form.dataset.quizReady === '1') {
			return;
		}

		form.dataset.quizReady = '1';

		const table = form.qs('.learning-table-choice');

		// L'ordre des lignes est celui qui part avec le formulaire : rien à enregistrer au dépôt.
		new Sortable(table, {
			handle: '.util-drag-handle',
			draggable: '[data-choice]',
			ghostClass: 'util-drag-ghost',
			chosenClass: 'util-drag-chosen',
			forceFallback: true,
			fallbackOnBody: true
		});

	}

	static check(input) {

		const table = input.firstParent('.learning-table-choice');

		if(input.type === 'radio') {

			table.qsa('[data-choice]', row => {

				const other = row.qs('.util-checkbox input:not([type="hidden"])');

				if(other !== input) {
					other.checked = false;
				}

			});

		}

		LearningQuiz.synchronize(table);

	}

	/**
	 * Le type change la marque de chaque ligne. Une question à une seule réponse n'en garde qu'une
	 * juste, la première cochée : les autres se décochent sous les yeux de l'auteur, plutôt qu'un
	 * refus à l'enregistrement.
	 */
	static changeType(group) {

		const checked = group.qs('input:checked');

		if(checked === null) {
			return;
		}

		const table = group.firstParent('form').qs('.learning-table-choice');
		const type = (checked.value === 'multiple') ? 'checkbox' : 'radio';

		let found = false;

		table.qsa('[data-choice] .util-checkbox input:not([type="hidden"])', input => {

			input.type = type;

			if(type === 'radio' && input.checked) {
				input.checked = (found === false);
				found = true;
			}

		});

		LearningQuiz.synchronize(table);

	}

	static synchronize(table) {

		table.qsa('[data-choice]', row => {
			row.qs('[data-choice-correct]').value = row.qs('.util-checkbox input:not([type="hidden"])').checked ? '1' : '0';
		});

	}

	/**
	 * La ligne ajoutée prend le prochain rang libre, porté par le bouton, et la marque du type
	 * choisi. Le bouton disparaît au nombre de choix que le quiz accepte au plus, le serveur gardant
	 * le dernier mot.
	 */
	static addChoice(button) {

		const form = button.firstParent('form');
		const table = form.qs('.learning-table-choice');
		const template = qs('#learning-choice-template');

		const index = parseInt(button.dataset.index);

		table.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('{index}', index));

		button.dataset.index = index + 1;

		const row = table.lastElementChild;
		const type = form.qs('[data-field="type"] input:checked');

		row.qs('.util-checkbox input:not([type="hidden"])').type = (type !== null && type.value === 'multiple') ? 'checkbox' : 'radio';

		LearningQuiz.refresh(form);

		row.qs('input[type="text"]').focus();

	}

	static removeChoice(button) {

		const form = button.firstParent('form');

		button.firstParent('[data-choice]').remove();

		LearningQuiz.refresh(form);

	}

	static refresh(form) {

		const button = form.qs('[data-max]');
		const choices = form.qsa('.learning-table-choice [data-choice]').length;

		button.classList.toggle('hide', choices >= parseInt(button.dataset.max));

	}

	/**
	 * Le seuil ne se montre qu'avec le quiz, mais il part toujours : un champ désactivé ne serait pas
	 * posté, `build()` le lirait vide et l'effacerait, alors qu'une leçon qui se déclare suivie garde
	 * celui qu'elle avait. Caché, il reprend donc la valeur enregistrée (`defaultValue`, vide pour un
	 * seuil jamais réglé), ce qui écarte aussi une saisie hors bornes que `min` et `max` bloqueraient
	 * sur un champ invisible. Montré vide, il reçoit le seuil par défaut, que le serveur ne pose pas.
	 */
	static initSettings(form) {

		const threshold = form.qs('[name="quizThreshold"]');

		if(threshold === null) {
			return;
		}

		form.qsa('[name="validation"]', radio => radio.addEventListener('change', () => LearningQuiz.toggleThreshold(form)));

		LearningQuiz.toggleThreshold(form);

	}

	static toggleThreshold(form) {

		const validation = form.qs('[name="validation"]:checked');
		const threshold = form.qs('[name="quizThreshold"]');
		const quiz = (validation !== null && validation.value === 'quiz');

		if(quiz === false) {
			threshold.value = threshold.defaultValue;
		} else if(threshold.value === '') {
			threshold.value = threshold.dataset.default;
		}

		LearningQuiz.updateNeeded(threshold);

		threshold.firstParent('[data-wrapper="quizThreshold"]').classList.toggle('hide', quiz === false);

		form.qs('#lesson-quiz-kept', reason => reason.classList.toggle('hide', quiz));

	}

	/**
	 * Même arrondi que `ResponseLib::getNeeded()`, au supérieur : 80 % de 5 questions, ce sont 4
	 * bonnes réponses. Les phrases viennent du serveur, une par nombre possible.
	 */
	static updateNeeded(input) {

		const info = qs('#lesson-quiz-needed');

		if(info === null) {
			return;
		}

		const threshold = parseInt(input.value);

		if(isNaN(threshold) || threshold < 0 || threshold > 100) {
			info.textContent = '';
			return;
		}

		const texts = JSON.parse(input.dataset.texts);
		const questions = parseInt(input.dataset.questions);

		info.textContent = texts[Math.ceil(questions * threshold / 100)];

	}

}

/**
 * Le lecteur d'une leçon (`LessonUi::getReader()`) : choisir une étape montre sa capture à côté des
 * étapes, et les flèches passent d'une étape à l'autre, fermées aux deux bouts. La capture est
 * recopiée de l'étape, qui garde la sienne pour la lecture sur une colonne, où elle se lit sous son
 * texte (`learning.css`). À l'ouverture, la première étape qui a une capture est choisie. Le clic sur
 * l'instant d'une étape la choisit aussi, en plus de lancer la vidéo de la carte (`BlockVideo.jump()`).
 *
 * Le clic s'écoute sur le lecteur, posé avec lui par `onrender` : il part avec la leçon quand une
 * navigation ajax la remplace, et le fichier ne pose rien à son chargement.
 */
class LearningReader {

	static start(reader) {

		reader.classList.add('learning-reader-ready');

		reader.addEventListener('click', e => {

			const move = e.target.closest('[data-reader-move]');

			if(move !== null) {
				LearningReader.move(reader, parseInt(move.dataset.readerMove, 10));
				return;
			}

			const step = e.target.closest('.learning-reader-text .util-procedure-step');

			if(LearningReader.getSteps(reader).includes(step)) {
				LearningReader.select(reader, step);
			}

		});

		const step = LearningReader.getSteps(reader).find(node => node.qs(':scope > .block-step-media') !== null);

		if(step !== undefined) {
			LearningReader.select(reader, step);
		}

	}

	/**
	 * Les étapes numérotées du lecteur, toutes listes confondues : un critère d'une liste cochée
	 * (`util-procedure-check`) n'est pas une étape, ni le clic ni les flèches ne s'y arrêtent, comme
	 * sa capture n'ouvre pas le lecteur (`LessonLib::fillReader()`).
	 */
	static getSteps(reader) {
		return Array.from(reader.qsa('.learning-reader-text .util-procedure:not(.util-procedure-check) > .util-procedure-step'));
	}

	static select(reader, step) {

		const steps = LearningReader.getSteps(reader);
		const position = steps.indexOf(step);

		steps.forEach(node => node.classList.toggle('selected', node === step));

		const media = step.qs(':scope > .block-step-media');

		reader.qs('.learning-reader-capture').replaceChildren(...(media === null ? [] : [media.cloneNode(true)]));

		reader.qs('[data-reader-move="-1"]').disabled = (position === 0);
		reader.qs('[data-reader-move="1"]').disabled = (position === steps.length - 1);

	}

	/**
	 * Les flèches passent à l'étape voisine, dans toutes les listes du lecteur, et la remettent en vue
	 * sans déplacer la page quand elle y est déjà.
	 */
	static move(reader, direction) {

		const steps = LearningReader.getSteps(reader);
		const step = steps[steps.findIndex(node => node.classList.contains('selected')) + direction];

		if(step === undefined) {
			return;
		}

		LearningReader.select(reader, step);
		step.scrollIntoView({block: 'nearest'});

	}

}

/**
 * Le signal de lecture d'une leçon (lot L8c), posé par `LessonUi::getReading()` : toutes les
 * `data-period` secondes, la leçon prolonge sa séance du journal (`ReadingLib::see()`), à deux
 * conditions (porte du plan L8, question 1 a). L'onglet est visible, et le stagiaire a agi sur la
 * page depuis moins de `data-idle` secondes : défilement, clic, touche, ou une vidéo en lecture.
 *
 * Une vidéo est une iframe : ce qui s'y passe n'atteint pas le document, qui ne peut savoir ni si elle
 * joue ni si elle est finie. Dans un texte (`editor-video`), un clic dedans lui donne le focus, et une
 * iframe active compte comme une vidéo regardée. Dans un bloc Vidéo, le clic sur « Lire la vidéo »
 * pose le lecteur (`BlockVideo`) et le focus retombe sur le document : le lecteur s'annonce par
 * `blockVideoPlay`, et la lecture compte tant qu'un cadre lancé est à l'écran, onglet visible.
 *
 * Une vidéo lancée ne tient la lecture que pendant `data-video-limit` secondes, la durée estimée de
 * la leçon : le temps mesuré est remis à un tiers dans le relevé de suivi, et il ne peut pas croitre
 * derrière une vidéo finie ou en pause, que rien ici ne distingue d'une vidéo qui joue. Ce délai
 * court une fois par leçon ouverte, depuis la première vidéo lancée. Ni un geste ni une autre vidéo
 * ne le relancent, sans quoi trois vidéos tripleraient le plafond. Passé le délai, un bloc Vidéo ne
 * tient plus la lecture, ni par son cadre à l'écran ni par le focus de son lecteur, qu'un clic sur
 * pause suffit à lui donner. Ce que le plafond ne vise pas compte comme avant lui : un geste, la
 * vidéo d'un texte qui a le focus, un `<video>` en lecture, dont le document sait s'il joue.
 *
 * Qu'un cadre soit à l'écran se lit dans un état que l'observateur tient à jour, jamais par une
 * mesure dans le battement, qui forcerait une mise en page toutes les `data-period` secondes. Cet
 * état ne survit pas à l'écran qui l'a produit : une navigation ajax garde le document, et un cadre
 * ou un délai resté là ferait compter la leçon suivante sur la vidéo de la précédente. Les cadres
 * ne survivent pas non plus à un nouveau rendu de la même leçon, qui remplace son contenu et son
 * nœud sans navigation : le délai, lui, reste, la leçon n'ayant pas changé.
 *
 * L'intervalle s'arrête dès que son nœud a quitté le document, et non sur un sélecteur : la leçon
 * suivante, atteinte en navigation ajax, pose un nœud de même forme, que l'ancien intervalle
 * prendrait pour le sien et ferait signaler pour la mauvaise leçon. Les écouteurs d'activité, eux,
 * ne se posent qu'une fois pour tout le document.
 *
 * Le signal part sans contexte, comme `FinancialYearDocument.checkGeneration()` : un signal de fond
 * ne verrouille rien et n'a pas d'erreur à afficher.
 */
class LearningReading {

	static activeAt = null;

	static observer = null;
	static framesVisible = new Set();

	// En millisecondes : le plafond de la leçon ouverte, et l'instant où il tombe, sur l'horloge de
	// `performance.now()`. Sans leçon ouverte, pas de plafond ; sans vidéo lancée, pas d'instant.
	static videoLimit = null;
	static videoUntil = null;

	static start(node) {

		LearningReading.listen();
		LearningReading.closeFrames();
		LearningReading.activeAt = performance.now();
		LearningReading.videoLimit = parseInt(node.dataset.videoLimit) * 1000;

		const period = parseInt(node.dataset.period) * 1000;
		const idle = parseInt(node.dataset.idle) * 1000;

		const intervalId = setInterval(() => {

			if(node.isConnected === false) {
				clearInterval(intervalId);
				return;
			}

			if(LearningReading.isVideoOver()) {
				LearningReading.closeFrames();
			}

			if(
				document.visibilityState !== 'visible' ||
				LearningReading.isActive(idle) === false
			) {
				return;
			}

			new Ajax.Query()
				.method('post')
				.url('/learning/reading:doSee')
				.body({
					lesson: node.dataset.lesson
				})
				.fetch();

		}, period);

	}

	static listen() {

		if(LearningReading.activeAt !== null) {
			return;
		}

		const touch = () => LearningReading.activeAt = performance.now();

		// En capture : le défilement d'un bloc intérieur ne remonte pas jusqu'au document.
		document.addEventListener('scroll', touch, {capture: true, passive: true});
		document.addEventListener('pointerdown', touch, {capture: true, passive: true});
		document.addEventListener('keydown', touch, {capture: true, passive: true});

		LearningReading.observer = new IntersectionObserver(entries => entries.forEach(entry => {

			if(entry.isIntersecting) {
				LearningReading.framesVisible.add(entry.target);
			} else {
				LearningReading.framesVisible.delete(entry.target);
			}

		}));

		document.addEventListener('blockVideoPlay', e => LearningReading.watchFrame(e.target));
		document.addEventListener('navigation.sleep', () => LearningReading.forgetFrames());

	}

	static watchFrame(frame) {

		if(LearningReading.videoLimit === null) {
			return;
		}

		const now = performance.now();

		if(LearningReading.videoUntil === null) {
			LearningReading.videoUntil = now + LearningReading.videoLimit;
		}

		if(now < LearningReading.videoUntil) {
			LearningReading.observer.observe(frame);
		}

	}

	static closeFrames() {

		LearningReading.observer.disconnect();
		// Une entrée déjà en file serait livrée après la déconnexion et remettrait un cadre dans la
		// liste qu'on vide : la file se vide avec elle.
		LearningReading.observer.takeRecords();
		LearningReading.framesVisible.clear();

	}

	static forgetFrames() {

		LearningReading.closeFrames();

		LearningReading.videoLimit = null;
		LearningReading.videoUntil = null;

	}

	static isVideoOver() {
		return (LearningReading.videoUntil !== null && performance.now() >= LearningReading.videoUntil);
	}

	static isActive(idle) {

		if(performance.now() - LearningReading.activeAt < idle) {
			return true;
		}

		if(LearningReading.framesVisible.size > 0) {
			return true;
		}

		const focused = document.activeElement;

		// Le lecteur d'un bloc Vidéo se reconnait à la parenté que `BlockVideo` écrit, et son focus
		// tombe avec le plafond de ce bloc. Toute autre iframe est la vidéo d'un texte, hors plafond.
		if(
			focused !== null &&
			focused.tagName === 'IFRAME' &&
			(
				focused.matches('.block-video-frame > iframe') === false ||
				LearningReading.isVideoOver() === false
			)
		) {
			return true;
		}

		return Array.from(document.querySelectorAll('video'))
			.some(video => video.paused === false && video.ended === false);

	}

}
