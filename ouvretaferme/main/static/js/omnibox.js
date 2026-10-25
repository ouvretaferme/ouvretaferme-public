/*
 * L'omnibox : un champ unique où l'on cherche, et où ce qu'on a choisi reste sous les
 * yeux en jetons.
 *
 * Le composant ne porte que le châssis — le markup, le clavier, l'ouverture et la
 * fermeture de la liste. Ce qui alimente les suggestions et ce qu'un choix déclenche
 * appartient à la page, et c'est ce qui permet aux deux modes de cohabiter sans que
 * l'utilisateur ait à distinguer lequel il a sous la main :
 *
 * - mode navigation : la suggestion porte une `url`, la choisir change de page. L'état
 *   reste dans la requête, donc le lien partagé et le bouton retour restent justes, et
 *   le rendu reste celui du serveur.
 * - mode local : la suggestion n'a pas d'url, la choisir pose un jeton et émet
 *   l'événement `omnibox` sur la boîte. La page écoute et filtre ce qu'elle a déjà.
 *
 * Rien ne se déclenche tant qu'on n'a rien choisi : le texte tapé ne fait que réduire
 * les suggestions. C'est ce qui rend les deux modes identiques à l'usage — un critère
 * actif est toujours un jeton, jamais du texte resté dans le champ. Un champ qui
 * filtrerait aussi en direct ferait cohabiter deux mécanismes dans le même endroit,
 * dont un seul laisse une trace visible.
 *
 * Délégation sur `document` plutôt qu'un accrochage nœud par nœud : la navigation est
 * ajax, une boîte est remplacée sans prévenir. Un `onrender` ferait le même travail,
 * mais une exception dedans avorte toute la boucle de parseRender et emporterait les
 * autocomplétions de la même page.
 */
class UtilOmnibox {

	static getBox(node) {

		return node.closest('.util-omnibox');

	}

	/*
	 * Les espaces insécables, normale et fine, se replient sur une espace ordinaire : la typographie les
	 * pose devant « ? » ou « ! » sans que personne ne les tape, et la recherche compare des sous-chaînes.
	 * TaskSearchLib::normalize() tient la même forme côté serveur.
	 */
	static normalize(value) {

		return value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[\u00a0\u202f]/g, ' ').trim();

	}

	/*
	 * Les suggestions, dans l'ordre de préférence de leur source :
	 *
	 * 1. celles que la page a posées par setSuggestions() ;
	 * 2. le JSON de l'attribut, quand la page les connaît au rendu ;
	 * 3. l'événement `omniboxsuggest`, pour une page qui les tire de ce qu'elle
	 *    affiche déjà — le plan de culture les relève sur ses propres lignes. C'est
	 *    aussi par là que passera un jour une liste demandée en ajax.
	 *
	 * Le calcul n'est fait qu'une fois par boîte : la liste porte parfois une centaine
	 * d'entrées et se recalcule à chaque frappe.
	 */
	static getSuggestions(box) {

		if(box.omniboxSuggestions === undefined) {

			// Source distante : la liste dépend de ce qui est tapé, elle ne peut pas
			// être connue d'avance. On rend ce qu'on a — rien la première fois — et
			// loadSuggestions() rejouera le rendu quand la réponse arrivera.
			if(box.dataset.suggestionsUrl !== undefined) {

				return [];

			} else if(box.dataset.suggestions !== undefined) {

				try {
					UtilOmnibox.setSuggestions(box, JSON.parse(box.dataset.suggestions));
				} catch(e) {
					UtilOmnibox.setSuggestions(box, []);
				}

			} else {

				box.dispatchEvent(new CustomEvent('omniboxsuggest', {bubbles: true}));

				if(box.omniboxSuggestions === undefined) {
					UtilOmnibox.setSuggestions(box, []);
				}

			}

		}

		return box.omniboxSuggestions;

	}

	/*
	 * Un champ distant peut porter une liste à lui (`suggestionsIdle` de `OmniboxUi::get()`), montrée
	 * tant que rien n'est tapé ni posé : il n'y a encore rien à lire, et le focus ne coûte aucune
	 * requête. Relue à chaque rendu, la page peut la changer sans prévenir le composant.
	 */
	static isIdle(box) {

		return (
			box.dataset.suggestionsIdle !== undefined &&
			UtilOmnibox.isEmpty(box)
		);

	}

	static isEmpty(box) {

		return (
			box.qs('.util-omnibox-input').value === '' &&
			box.qs('.util-omnibox-token') === null
		);

	}

	static getSuggestionsIdle(box) {
		return JSON.parse(box.dataset.suggestionsIdle);
	}

	// `terms` élargit la recherche d'une suggestion à des mots qu'elle n'affiche pas : une question
	// de la FAQ se trouve aussi par sa réponse, sans que la liste la recopie.
	static setSuggestions(box, suggestions) {

		box.omniboxSuggestions = suggestions.map(one => ({...one, search: UtilOmnibox.normalize(one.terms === undefined ? one.label : one.label +' '+ one.terms)}));

	}

	// Ce que la page lit pour filtrer. Un jeton porte son groupe et sa valeur : deux
	// « Carotte », l'une espèce et l'autre variété, ne se confondent pas.
	static getTokens(box) {

		return [...box.qsa('.util-omnibox-token')].map(token => ({
			group: token.dataset.group ?? '',
			value: token.dataset.value ?? '',
			label: token.dataset.label ?? '',
			icon: token.dataset.icon,
			badge: token.dataset.badge
		}));

	}

	/*
	 * Les jetons rangés par famille, `{famille: [valeurs]}`.
	 *
	 * C'est la forme que doit lire une page qui filtre, parce que c'est elle qui porte
	 * la règle : dans une famille les critères s'ajoutent (« Ail » et « Artichaut », ce
	 * sont les deux espèces), entre familles ils se croisent (« Ail » et « Germidour »,
	 * c'est l'ail de cette variété-là). Croiser deux critères d'une même famille ne
	 * rendrait jamais rien : aucune ligne n'est deux espèces à la fois.
	 */
	static getTokenGroups(box) {

		const groups = {};

		UtilOmnibox.getTokens(box).forEach(token => (groups[token.group] ??= []).push(token.value));

		return groups;

	}

	/*
	 * Le plafond est par famille, jamais global : rangées famille par famille, quatre-
	 * vingts espèces mangeraient à elles seules une liste plafonnée en tout, et les
	 * variétés comme le matériel ne s'afficheraient jamais à champ vide. Le champ
	 * annonce alors trois choses et n'en montre qu'une.
	 */
	static groupLimit = 8;

	/*
	 * Une famille en puces tient ses choix sur une ou deux lignes au lieu d'une ligne chacun : elle
	 * peut donc tout montrer bien au-delà du plafond d'une liste, les douze mois d'un exercice comme
	 * les moyens de paiement, sans que le déroulant ne s'allonge pour autant.
	 */
	static chipLimit = 24;

	static getChips(box) {
		return (box.dataset.chips === undefined) ? [] : JSON.parse(box.dataset.chips);
	}

	/*
	 * Va chercher les suggestions au serveur, quand la boîte en désigne un.
	 *
	 * Quatre précautions, et aucune n'est facultative :
	 *
	 * - un anti-rebond, sans quoi « carotte » part en sept requêtes ;
	 * - un numéro d'ordre, pris dès la frappe et non au départ de la requête, sans quoi
	 *   « car » répondant après « carotte », ou pendant l'anti-rebond de « carotte »,
	 *   écrase la liste précise par la périmée ;
	 * - une requête sans contexte : `Ajax.Query` verrouille son contexte jusqu'à la
	 *   réponse et lève `LOCK_FAILED` sur la requête suivante, qui ne part alors jamais,
	 *   laissant la liste voilée sur une frappe dépassée ;
	 * - la mémoire de ce qui a été demandé, sans quoi le rendu déclenché par la réponse
	 *   redemanderait aussitôt la même chose.
	 */
	static loadSuggestions(box) {

		const needle = box.qs('.util-omnibox-input').value;

		// Un rendu sans frappe (une flèche, le focus) ne relance pas la lecture déjà en route. Le
		// corps en fait partie : l'appelant qui le change attend une autre réponse au même texte.
		const request = needle +'\n'+ (box.dataset.suggestionsBody ?? '');

		if(box.omniboxReading === true && box.omniboxRequest === request) {
			return;
		}

		clearTimeout(box.omniboxPending);

		const sequence = (box.omniboxSequence = (box.omniboxSequence ?? 0) + 1);

		// Revenu au texte déjà lu (« abc » effacé en « ab »), la lecture de « abc » encore en route
		// ne doit plus rien poser.
		if(box.omniboxLoaded === needle) {
			box.omniboxReading = false;
			box.classList.remove('is-loading');
			return;
		}

		box.omniboxReading = true;
		box.omniboxRequest = request;

		// La toute première demande part sans attendre : à l'ouverture il n'y a rien à
		// regrouper, et un quart de seconde de panneau vide se lit comme une panne. Les
		// suivantes attendent, même tant que la première n'a pas répondu.
		const delay = (box.omniboxLoaded === undefined && box.omniboxReading !== true) ? 0 : 250;

		box.omniboxPending = setTimeout(() => {

			const body = new URLSearchParams();

			Object.entries(JSON.parse(box.dataset.suggestionsBody ?? '{}')).forEach(([key, value]) => body.set(key, value));

			body.set('query', needle);

			box.classList.add('is-loading');

			new Ajax.Query()
				.url(box.dataset.suggestionsUrl)
				.body(body)
				.fetch()
				.then((json) => {

					if(sequence !== box.omniboxSequence) {
						return;
					}

					box.classList.remove('is-loading');
					box.omniboxLoaded = needle;
					box.omniboxReading = false;

					// La réponse renouvelle la liste, elle ne rouvre pas celle d'un champ qu'on a quitté
					// entre-temps : une page peut en porter plusieurs, et seul celui qui a le focus parle.
					// Le rendu a lieu quand même, Entrée attend la liste de ce texte (`afterRead()`).
					const shown = (
						box.qs('.util-omnibox-list.is-open') !== null ||
						box.contains(document.activeElement)
					);

					UtilOmnibox.setSuggestions(box, json.results ?? []);
					UtilOmnibox.render(box);

					if(shown === false) {
						UtilOmnibox.close(box);
					}

					box.dispatchEvent(new CustomEvent('omniboxload', {bubbles: true, detail: {json: json}}));

					UtilOmnibox.settle(box, true);

				})
				.catch(() => {

					if(sequence !== box.omniboxSequence) {
						return;
					}

					// L'échec ne se mémorise pas : sans ça, la frappe suivante croirait la
					// réponse déjà connue et ne redemanderait jamais. Le voile reste sur la
					// liste précédente, qui est encore ce qu'on a de plus juste.
					box.classList.remove('is-loading');
					box.omniboxLoaded = undefined;
					box.omniboxReading = false;

					UtilOmnibox.settle(box, false);

				});

		}, delay);

	}

	/*
	 * Un geste qui porte sur la liste (Entrée) ou sur le texte (l'écriture d'un appelant) attend la
	 * lecture du texte courant : pris sur la liste d'une frappe dépassée, il choisirait ce qui n'est
	 * plus proposé. Une seule attente à la fois, la dernière demandée ; une frappe l'annule.
	 * `callback` reçoit `false` si la lecture a échoué.
	 */
	static afterRead(box, callback) {

		const remote = (box.dataset.suggestionsUrl !== undefined);

		if(remote === false || UtilOmnibox.isIdle(box)) {
			callback(true);
			return;
		}

		box.omniboxAfterRead = callback;

		// Une lecture échouée n'a rien laissé en attente : on la relance pour ce texte.
		if(box.omniboxReading !== true) {

			if(box.omniboxLoaded === box.qs('.util-omnibox-input').value) {
				UtilOmnibox.settle(box, true);
			} else {
				UtilOmnibox.loadSuggestions(box);
			}

		}

	}

	static settle(box, ok) {

		// La réponse a pu relancer une lecture (un jeton retiré par `omniboxload`) : l'attente va
		// à la suivante.
		if(box.omniboxReading === true || box.omniboxAfterRead === undefined) {
			return;
		}

		const callback = box.omniboxAfterRead;

		box.omniboxAfterRead = undefined;

		callback(ok);

	}

	// Sans rien de visé, Entrée prend la première suggestion : le champ ne déclenche rien par
	// lui-même, une frappe suivie d'Entrée resterait sinon sans effet et se lirait comme une panne.
	// Une boîte dont les puces précisent une phrase (`data-chips-explicit`) ne les laisse prendre qu'au
	// clic ou à la flèche : Entrée répétée sur une phrase qui n'écrit pas les poserait l'une après l'autre.
	static enter(box) {

		const first = (box.dataset.chipsExplicit === undefined) ?
			'.util-omnibox-item' :
			'.util-omnibox-family:not(.util-omnibox-family-chips) .util-omnibox-item';

		const item = box.qs('.util-omnibox-item.is-cursor') ?? box.qs(first);

		if(item !== null) {
			UtilOmnibox.pick(box, item);
		}

	}

	static render(box) {

		const list = box.qs('.util-omnibox-list');
		const input = box.qs('.util-omnibox-input');

		if(list === null || input === null) {
			return;
		}

		if(box.classList.contains('util-omnibox-scroll')) {
			UtilOmnibox.sizeInput(input);
		}

		const idle = UtilOmnibox.isIdle(box);
		const remote = (box.dataset.suggestionsUrl !== undefined && idle === false);

		// Vidé, le champ revient à sa liste au repos, qui ne se lit pas : la lecture d'une frappe effacée, en route ou
		// retenue par l'anti-rebond, répondrait sur un champ qui n'attend plus rien, et la page appliquerait la réponse
		// d'une phrase qui n'existe plus.
		if(idle) {
			UtilOmnibox.forgetRead(box);
		} else if(remote) {
			UtilOmnibox.loadSuggestions(box);
		}

		const needle = UtilOmnibox.normalize(input.value);
		const posed = UtilOmnibox.getTokens(box).map(token => token.group +'\n'+ token.value);

		// Un serveur qui cherche sait le faire mieux que ce filtre : synonymes, nom
		// latin, tolérance à la faute. Refiltrer ici jetterait ses bonnes réponses.
		const matches = (idle ? UtilOmnibox.getSuggestionsIdle(box) : UtilOmnibox.getSuggestions(box))
			.filter(one => remote || needle === '' || one.search.includes(needle))
			.filter(one => posed.includes((one.group ?? '') +'\n'+ (one.value ?? one.label)) === false);

		const groups = [];
		const chips = UtilOmnibox.getChips(box);
		const icons = matches.some(one => one.icon !== undefined || one.vignette !== undefined);

		matches.forEach(one => {

			const group = groups.find(group => group.label === one.group) ?? groups[groups.push({label: one.group, items: []}) - 1];

			group.items.push(one);

		});

		list.replaceChildren();

		if(groups.length === 0) {
			UtilOmnibox.close(box);
			return;
		}

		groups.forEach(group => {

			// Chaque famille dans son conteneur : c'est ce qui permet à son en-tête de
			// coller au défilement puis d'être chassé par le suivant. Posés à plat, deux
			// en-têtes collants se superposent au lieu de se relayer.
			const family = document.createElement('div');

			family.className = 'util-omnibox-family';

			const isChips = chips.includes(group.label);

			// Une famille dont les entrées demandent `uncapped` se montre entière : ce sont les lignes d'un
			// récapitulatif, qu'aucune frappe ne filtre et dont la réserve cacherait une partie.
			const uncapped = group.items.some(one => one.uncapped === true);
			const limit = isChips ? UtilOmnibox.chipLimit : (uncapped ? group.items.length : UtilOmnibox.groupLimit);

			family.classList.toggle('util-omnibox-family-chips', isChips);

			const title = document.createElement('div');

			title.className = 'util-omnibox-group';
			title.textContent = group.label;

			family.append(title);

			group.items.slice(0, limit).forEach(one => {

				// Hors de util-omnibox-item, la ligne échappe aux flèches, à Entrée et au clic,
				// qui ne visent que les entrées.
				if(one.notice === true || one.notice === 'muted') {

					const notice = document.createElement('div');

					notice.className = 'util-omnibox-notice';
					notice.classList.toggle('util-omnibox-notice-muted', one.notice === 'muted');
					notice.textContent = one.label;

					family.append(notice);

					return;

				}

				const item = document.createElement('a');

				item.className = 'util-omnibox-item';
				item.classList.toggle('is-cursor', one.cursor === true);

				// La valeur retenue d'une famille en puces, qui reste à sa place parmi les autres.
				item.classList.toggle('is-selected', one.selected === true);
				item.dataset.group = one.group ?? '';
				item.dataset.value = one.value ?? one.label;
				item.dataset.label = one.label;

				// Le libellé du jeton, quand il ne redit pas celui de la suggestion : la puce « +30 min » pose 1 h 30.
				if(one.token !== undefined) {
					item.dataset.token = one.token;
				}

				if(one.icon !== undefined) {
					item.dataset.icon = one.icon;
				}

				if(one.badge !== undefined) {
					item.dataset.badge = one.badge;
				}

				/*
				 * La vignette est facultative, et la suggestion porte son href entier :
				 * le composant n'a pas à connaître le sprite du package qui l'alimente,
				 * ni à deviner qu'un symbole y existe. Ce qu'aucun sprite ne porte, un
				 * avatar ou une photo, arrive en `vignette`, du HTML rendu par le serveur
				 * comme `sub`, à la même place.
				 *
				 * Dès qu'une entrée en porte une, toutes réservent la place, vide s'il
				 * le faut : sans ça les libellés démarrent à deux abscisses différentes
				 * et la liste se lit comme deux colonnes mal rangées. Les puces n'ont
				 * pas de colonne à aligner, et ne la réservent pas.
				 */
				if(one.vignette !== undefined) {

					const vignette = document.createElement('span');

					vignette.className = 'util-omnibox-item-icon';
					vignette.innerHTML = one.vignette;

					item.append(vignette);

				} else if(icons && isChips === false) {

					const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

					svg.setAttribute('class', 'util-omnibox-item-icon');

					if(one.icon !== undefined) {

						const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');

						use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', one.icon);
						use.setAttribute('href', one.icon);

						svg.append(use);

					}

					item.append(svg);

				}

				// La seconde ligne est du HTML rendu par le serveur, le libellé reste du texte.
				if(one.sub !== undefined) {

					const body = document.createElement('span');
					const label = document.createElement('span');
					const sub = document.createElement('span');

					body.className = 'util-omnibox-item-body';
					label.textContent = one.label;
					sub.className = 'util-omnibox-item-sub';
					sub.innerHTML = one.sub;

					body.append(label, sub);
					item.append(body);

				} else {
					item.append(document.createTextNode(one.label));
				}

				// Ce qui lève une ambiguïté sans faire partie du nom : deux variétés
				// « Black Beauty » ne se distinguent que par leur espèce. Poussé à droite
				// et effacé, pour rester lisible sans concurrencer le libellé.
				if(one.detail !== undefined) {

					const detail = document.createElement('span');

					detail.className = 'util-omnibox-item-detail';
					detail.textContent = one.detail;

					item.append(detail);

				}

				// Une suggestion qui mène ailleurs est un vrai lien : elle s'ouvre dans
				// un nouvel onglet, se copie, et fonctionne sans le JS de la liste.
				if(one.url !== undefined) {

					item.href = one.url;

					// Le délégué de navigation ajax (ajax.js, 'a[href]') est enregistré avant
					// celui de la liste et ne peut pas être arrêté depuis lui : sans ce
					// jalon, un clic charge la page deux fois, par lui puis par pick().
					item.dataset.ajaxNavigation = 'never';

				}

				family.append(item);

			});

			// Ce que la famille garde en réserve, en **fin** de liste et non sur son
			// en-tête : là, le nombre se lit comme la suite de ce qu'on vient de parcourir
			// et invite à préciser la recherche. Sur l'en-tête, il touchait la dernière
			// entrée de la famille précédente et semblait la compter.
			if(group.items.length > limit) {

				const rest = document.createElement('div');

				rest.className = 'util-omnibox-rest';

				// La même place réservée que sur une entrée, quand la liste porte des
				// vignettes : le nombre commence alors là où commencent les noms, sans
				// qu'aucun retrait n'ait à être deviné.
				if(icons) {

					const slot = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

					slot.setAttribute('class', 'util-omnibox-item-icon');

					rest.append(slot);

				}

				// Le nom de la famille accompagne le nombre, parce que « +40 » seul ne dit pas
				// de quoi et que l'en-tête qui le dirait est hors de l'écran dès qu'on a défilé.
				rest.append(document.createTextNode(list.dataset.rest
					.replace('{value}', group.items.length - limit)
					.replace('{group}', group.label)));

				family.append(rest);

			}

			list.append(family);

		});

		// Comme les listes de l'autocomplete et des menus déroulants, au-dessus des en-têtes de tableau
		// collants (z-index 99, reboot.css) qui suivent la boîte dans la page.
		if(list.classList.contains('is-open') === false) {
			list.style.zIndex = Lime.getZIndex();
		}

		list.classList.add('is-open');

	}

	/*
	 * Le texte tapé d'une boîte `util-omnibox-scroll`, ou son texte indicatif, garde sa largeur par `field-sizing`,
	 * que Firefox ignore : il y garderait ses 20 caractères par défaut, que la queue compterait. Même repli que le
	 * titre d'une note (`NoteUpdate.sizeTitle()`), un caractère de plus pour le curseur ; `OmniboxUi::get()` pose le
	 * premier au rendu. `render()` le recalcule à chaque frappe ; une page qui change le texte ou le texte indicatif
	 * sans frappe le recalcule elle-même.
	 */
	static sizeInput(input) {
		input.size = Math.max(Math.max(input.value.length, input.placeholder.length) + 1, 2);
	}

	/*
	 * Au doigt, la recherche prend l'écran.
	 *
	 * Même règle de déclenchement que les autocomplétions du framework — `data-touch`
	 * posé une fois sur le body — et non un palier de largeur : ce qui change le geste,
	 * c'est le doigt, pas le nombre de pixels. Une tablette large se pilote au doigt, un
	 * navigateur rétréci à la souris.
	 *
	 * Le mécanisme est celui d'`AutocompleteField.move()` : un jalon prend la place de la
	 * boîte, la boîte part dans une enveloppe fixée en haut de l'écran, et une couche
	 * d'historique rend le bouton retour du téléphone au geste qu'on attend de lui.
	 * Sans elle, « retour » quitterait la page au lieu de refermer la recherche.
	 *
	 * Le champ posé en bas (`OmniboxUi::get(fullscreenBottom: TRUE)`) suit au contraire la largeur : il
	 * n'est en bas que sous la condition qui y met la nav, et au-dessus, au doigt comme à la souris, il
	 * reste à sa place dans la colonne. Sans boîte, la question est celle du doigt.
	 */
	static isFullscreen(box = null) {

		if(box?.matches('[data-fullscreen="bottom"]')) {
			return window.matchMedia('(max-width: 1099px), (max-height: 599px)').matches;
		} else {
			return document.body.matches('[data-touch="yes"]');
		}

	}

	static isFullscreenBottom(box) {
		return box.omniboxPlaceholder !== undefined && box.matches('[data-fullscreen="bottom"]');
	}

	static openFullscreen(box) {

		// Une boîte posée en place (`OmniboxUi::get(fullscreen: FALSE)`) y reste au doigt aussi.
		if(UtilOmnibox.isFullscreen(box) === false || box.matches('[data-inline]') || box.omniboxPlaceholder !== undefined) {
			return;
		}

		const placeholder = document.createElement('div');

		placeholder.className = 'util-omnibox-placeholder';

		box.before(placeholder);
		box.omniboxPlaceholder = placeholder;

		const wrapper = document.createElement('div');

		wrapper.className = 'util-omnibox-wrapper';
		wrapper.style.zIndex = Lime.getZIndex();

		document.body.append(wrapper);
		wrapper.append(box);

		box.classList.add('is-fullscreen');
		document.body.classList.add('util-omnibox-fullscreen-open');

		if(box.matches('[data-fullscreen="bottom"]')) {

			wrapper.classList.add('util-omnibox-wrapper-bottom');
			document.body.classList.add('util-omnibox-fullscreen-bottom-open');

			UtilOmnibox.followViewport(box, wrapper);

		}

		// Déplacer un nœud lui fait perdre le focus : sans ce rappel, le panneau s'ouvre
		// et le clavier ne monte pas, alors que c'est le geste qui vient de le demander.
		// Le framework corrige la même chose au même endroit dans ses autocomplétions.
		// Rappeler focus() ici ne boucle pas : openFullscreen() rend la main tout de
		// suite tant que le jalon est posé.
		box.qs('.util-omnibox-input').focus();

		Lime.History.pushLayer(wrapper, () => UtilOmnibox.unmountFullscreen(box), document.location.href, null);

	}

	/*
	 * Le clavier du téléphone réduit la zone visible sans changer la fenêtre : l'enveloppe se cale sur
	 * `visualViewport` pour que le champ reste juste au-dessus de lui. La zone visible se relit aussi sur
	 * le redimensionnement de la fenêtre, parce que celui de `visualViewport` arrive parfois avant que sa
	 * hauteur ait bougé.
	 */
	static followViewport(box, wrapper) {

		const viewport = window.visualViewport;

		const follow = () => {
			const height = viewport.height;
			const top = viewport.offsetTop;
			wrapper.style.setProperty('--util-omnibox-viewport-height', height +'px');
			wrapper.style.setProperty('--util-omnibox-viewport-top', top +'px');
		};

		follow();

		viewport.addEventListener('resize', follow);
		viewport.addEventListener('scroll', follow);
		window.addEventListener('resize', follow);

		box.omniboxViewport = follow;

	}

	/*
	 * La sortie du plein écran, pour tous les gestes qui ne viennent pas du retour du navigateur : elle dépile la
	 * couche d'historique que l'ouverture a posée, et le démontage suit (`unmountFullscreen()`). Refermer sans
	 * dépiler laisserait une entrée morte, sur laquelle le retour suivant ne ferait rien. La promesse se résout
	 * une fois la boîte rendue à la page et le défilement de la couche restauré : ce qui défile ensuite attend.
	 */
	static closeFullscreen(box) {

		if(box.omniboxPlaceholder === undefined) {
			return Promise.resolve();
		}

		return Lime.History.popLayer(box.closest('.util-omnibox-wrapper')) || Promise.resolve();

	}

	static unmountFullscreen(box) {

		const placeholder = box.omniboxPlaceholder;

		if(placeholder === undefined) {
			return;
		}

		placeholder.before(box);
		placeholder.remove();

		box.omniboxPlaceholder = undefined;

		if(box.omniboxViewport !== undefined) {

			window.visualViewport.removeEventListener('resize', box.omniboxViewport);
			window.visualViewport.removeEventListener('scroll', box.omniboxViewport);
			window.removeEventListener('resize', box.omniboxViewport);

			box.omniboxViewport = undefined;

		}

		document.body.classList.remove('util-omnibox-fullscreen-bottom-open');

		box.classList.remove('is-fullscreen');
		document.body.classList.remove('util-omnibox-fullscreen-open');

		qsa('.util-omnibox-wrapper', wrapper => wrapper.remove());

		// Le champ repart dans la page : lui laisser le focus rouvrirait le clavier sur
		// un écran qu'on vient de demander à revoir.
		box.qs('.util-omnibox-input').blur();

		UtilOmnibox.close(box);
		UtilOmnibox.fold(box);

	}

	static close(box) {

		box?.qs('.util-omnibox-list')?.classList.remove('is-open');

	}

	/*
	 * Échap renonce à la lecture en route avec la liste : sa réponse, arrivée après, rouvrirait sous
	 * le curseur ce qu'on vient de fermer. Le texte reste, la frappe ou une flèche le relisent.
	 */
	static dismiss(box) {

		UtilOmnibox.abandonRead(box);
		UtilOmnibox.close(box);

	}

	// Le numéro d'ordre avance sans requête : la réponse de la lecture en route ne sera plus la dernière demandée.
	static abandonRead(box) {

		clearTimeout(box.omniboxPending);

		box.omniboxSequence = (box.omniboxSequence ?? 0) + 1;
		box.omniboxReading = false;
		box.omniboxAfterRead = undefined;
		box.classList.remove('is-loading');

	}

	// Un champ vidé, au clavier (`render()`), par la croix ou par un appelant qui le remet à neuf : ce qui se tapera
	// ensuite est une autre phrase, lue même si elle redit l'ancienne.
	static forgetRead(box) {

		UtilOmnibox.abandonRead(box);
		box.omniboxLoaded = undefined;

	}

	/*
	 * Une boîte repliée n'est qu'une loupe tant qu'on n'y cherche rien. Le champ ne se montre que
	 * le temps de la saisie, ou au bureau tant qu'un critère est posé, et il se replie dès qu'on en
	 * sort : c'est la feuille de style qui décide, d'après `is-unfolded` et `is-filled`.
	 */
	static unfold(box) {

		box.classList.add('is-unfolded');

		box.qs('.util-omnibox-input').focus();

	}

	static fold(box) {

		if(box.classList.contains('util-omnibox-folded') === false) {
			return;
		}

		// Une lecture encore en route rouvrirait la liste sous la loupe en répondant : repliée, la boîte
		// n'attend plus rien.
		UtilOmnibox.forgetRead(box);

		box.qs('.util-omnibox-input').value = '';
		box.classList.remove('is-unfolded');

		UtilOmnibox.close(box);

	}

	static move(box, delta) {

		const items = [...box.qsa('.util-omnibox-item')];

		if(items.length === 0) {
			return;
		}

		const current = items.findIndex(one => one.classList.contains('is-cursor'));
		const next = (current === -1) ? (delta > 0 ? 0 : items.length - 1) : (current + delta + items.length) % items.length;

		items.forEach(one => one.classList.remove('is-cursor'));

		items[next].classList.add('is-cursor');
		items[next].scrollIntoView({block: 'nearest'});

	}

	// Un choix qui mène ailleurs suit la règle d'un lien du site (ajax.js, délégué de 'a[href]') : une ancre ou
	// une adresse d'une autre origine change la page sans ajax, le reste passe par ajax avec la boîte pour
	// contexte, qui refuse un second envoi tant que le premier n'est pas revenu.
	static navigate(box, link) {

		const href = link.getAttribute('href');

		if(href.startsWith('#') || Ajax.Navigation.isUrlValid(href) === false) {
			window.location = href;
			return;
		}

		// En plein écran, la couche d'historique se dépile avant de naviguer : dépilée après, son retour en arrière
		// tomberait sur l'adresse que la navigation vient de poser.
		if(box.omniboxPlaceholder !== undefined) {

			UtilOmnibox.closeFullscreen(box).then(() => {
				new Ajax.Navigation(box)
					.url(href)
					.fetch();
			});

			return;

		}

		// La navigation ajax ne réécrit que le contenu de la page : une boîte posée hors de lui, comme celle de
		// la barre latérale, y survivrait avec sa liste ouverte et le focus. Elle se referme au retour de la
		// requête et pas avant : un second clic retombe ainsi sur la suggestion, que le verrou refuse, et non
		// sur le lien de la page que la liste couvrait.
		const close = () => {
			UtilOmnibox.close(box);
			box.qs('.util-omnibox-input').blur();
		};

		new Ajax.Navigation(box)
			.url(href)
			.fetch()
			.then(close, close);

	}

	static pick(box, item) {

		if(box.dispatchEvent(new CustomEvent('omniboxpick', {bubbles: true, cancelable: true, detail: {item: item}})) === false) {
			return;
		}

		if(item.href) {
			UtilOmnibox.navigate(box, item);
			return;
		}

		UtilOmnibox.addToken(box, item.dataset.group, item.dataset.value, item.dataset.token ?? item.dataset.label);

		const input = box.qs('.util-omnibox-input');

		input.value = '';

		UtilOmnibox.change(box);

		// Au doigt, choisir applique et referme tout : le panneau couvre l'écran, et y
		// rester devant une liste vidée de ce qu'on vient de prendre ne laisse rien à
		// faire ni rien à voir. Poser un second critère se fait en rouvrant, ce qui est
		// un geste de plus mais jamais une impasse.
		//
		// À la souris la boîte reste en place : seule la liste se referme, parce que ce
		// qu'on vient de choisir a changé ce qu'il y a derrière et que le premier geste
		// est de le regarder. Reprendre la frappe ou une flèche la rouvre.
		if(box.omniboxPlaceholder !== undefined) {

			UtilOmnibox.closeFullscreen(box);

		} else {

			// Rendre le focus au champ déclenche le focusin, qui rouvrirait la liste sur la
			// recherche vide : il se tait pendant ce focus-là.
			box.omniboxRefocus = true;
			input.focus();
			box.omniboxRefocus = undefined;

			UtilOmnibox.close(box);

		}

	}

	// Le jeton est cloné d'un gabarit posé par le serveur : son icône est celle du
	// thème, que le JS ne sait pas fabriquer.
	static addToken(box, group, value, label, icon = undefined, badge = undefined) {

		// Un critère par famille : le nouveau remplace l'ancien au lieu de s'y
		// ajouter, pour les écrans où deux espèces à la fois n'ont pas de sens.
		if(box.dataset.singleByGroup !== undefined) {
			box.qsa('.util-omnibox-token', token => {
				if(token.dataset.group === (group ?? '')) {
					token.remove();
				}
			});
		}

		const token = box.qs('.util-omnibox-token-template').content.firstElementChild.cloneNode(true);

		token.dataset.group = group ?? '';
		token.dataset.value = value ?? label;
		token.dataset.label = label;

		const labelNode = document.createElement('span');
		labelNode.className = 'util-omnibox-token-label';
		labelNode.textContent = label;
		token.prepend(labelNode);

		if(badge !== undefined) {
			token.dataset.badge = badge;
			labelNode.after(document.createRange().createContextualFragment(badge));
		}

		if(icon !== undefined) {

			const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
			const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');

			svg.setAttribute('class', 'util-omnibox-token-icon');
			use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', icon);
			use.setAttribute('href', icon);

			svg.append(use);
			token.prepend(svg);

			token.dataset.icon = icon;

		}

		// Repliée, la boîte pose ses jetons sur leur rangée, devant « Tout effacer ».
		(box.qs('.util-omnibox-clear-all') ?? box.qs('.util-omnibox-tail')).before(token);

		// Des pages appellent addToken() sans change() derrière : le champ suit donc chaque jeton posé.
		UtilOmnibox.synchronizeFields(box);

		return token;

	}

	/*
	 * Une boîte qui porte `data-name` est un champ de formulaire : chaque jeton poste sa valeur dans un
	 * champ caché `<name>[]`. Les champs suivent la boîte et non son contenu, parce qu'openFullscreen()
	 * l'accroche à document.body, hors du formulaire : ils restent alors à la suite du jalon, qui tient sa
	 * place. Ils se refont d'après les jetons, qui restent le seul endroit où le choix se lit.
	 */
	static synchronizeFields(box) {

		if(box.dataset.name === undefined) {
			return;
		}

		const anchor = box.omniboxPlaceholder ?? box;

		while(anchor.nextElementSibling !== null && anchor.nextElementSibling.matches('input[data-omnibox-field]')) {
			anchor.nextElementSibling.remove();
		}

		const fields = UtilOmnibox.getTokens(box).map(token => {

			const field = document.createElement('input');

			field.type = 'hidden';
			field.name = box.dataset.name +'[]';
			field.value = token.value;
			field.dataset.omniboxField = '';

			return field;

		});

		anchor.after(...fields);

	}

	/*
	 * Poser, remplacer ou retirer un critère depuis la page.
	 *
	 * Un écran peut avoir d'autres façons de choisir qu'un déroulant — le plan de
	 * culture pose sa semaine en cliquant sur la graduation. Le jeton reste alors le
	 * seul endroit où le critère se lit et s'efface, et c'est ce qui compte : un filtre
	 * posé ailleurs et visible ailleurs se perd.
	 *
	 * `previous` désigne le jeton à retirer quand la valeur change (une semaine dont on
	 * a modifié les actions n'a plus la même). `value` à NULL ne fait que retirer.
	 */
	static setToken(box, group, value, label, previous = null) {

		box.qsa('.util-omnibox-token', token => {
			if(token.dataset.group === group && token.dataset.value === (previous ?? value)) {
				token.remove();
			}
		});

		value === null || UtilOmnibox.addToken(box, group, value, label);

		UtilOmnibox.change(box);

	}

	static removeToken(token) {

		const box = UtilOmnibox.getBox(token);

		token.remove();

		UtilOmnibox.change(box);

		// Le critère retiré redevient proposable, mais seulement si la liste est déjà
		// ouverte : retirer un jeton n'est pas une demande de suggestions.
		box.qs('.util-omnibox-list.is-open') === null || UtilOmnibox.render(box);

	}

	static clear(box) {

		box.qsa('.util-omnibox-token', token => token.remove());

		const input = box.qs('.util-omnibox-input');

		input.value = '';

		UtilOmnibox.forgetRead(box);

		/*
		 * Reprendre le focus est ce qu'il faut à la souris : on efface pour retaper.
		 *
		 * Au doigt, non : le focus rouvrirait le panneau plein écran et ferait monter le
		 * clavier, alors que la croix demandait exactement le contraire — effacer et
		 * rendre la main sur ce qu'on regarde. Dans le panneau déjà ouvert, en revanche,
		 * on reste dans la recherche : effacer y est une étape, pas une sortie.
		 */
		if(UtilOmnibox.isFullscreen(box) === false || box.omniboxPlaceholder !== undefined) {
			input.focus();
		}

		UtilOmnibox.change(box);
		UtilOmnibox.close(box);

	}

	static change(box) {

		box.classList.toggle('is-filled', box.qs('.util-omnibox-token') !== null);

		UtilOmnibox.synchronizeFields(box);
		UtilOmnibox.countFilters();

		box.dispatchEvent(new CustomEvent('omnibox', {bubbles: true}));

	}

	/*
	 * Dans l'en-tête d'un écran, les jetons tiennent sur un rang et défilent sous la queue du champ :
	 * quand ils débordent, le nombre de filtres posés dit qu'il y en a d'autres sous le fondu. Un jeton
	 * seul se voit coupé, le nombre ne s'affiche qu'à partir de deux.
	 *
	 * Toutes les largeurs se lisent avant la moindre écriture, sinon chaque lecture recalculerait la
	 * mise en page entière.
	 */
	static countFilters() {

		const measures = [];

		// Ailleurs, les jetons passent à la ligne ou défilent pour d'autres raisons (le filtre de sol de
		// l'assolement) : un nombre de filtres n'y désignerait rien de caché.
		qsa('.util-page-header-search .util-omnibox-filters', badge => {

			const field = badge.closest('.util-omnibox-field');

			// Le nombre déjà affiché élargit la queue : sa place ne compte pas dans le débordement, sans
			// quoi il se maintiendrait de lui-même une fois les jetons retirés.
			const badgeWidth = (badge.textContent === '') ? 0 : badge.offsetWidth + parseFloat(getComputedStyle(badge.parentNode).columnGap);

			measures.push({
				badge: badge,
				tokens: field.qsa('.util-omnibox-token').length,
				overflows: (field.scrollWidth - badgeWidth > field.clientWidth)
			});

		});

		measures.forEach(({badge, tokens, overflows}) => {
			badge.textContent = (tokens >= 2 && overflows) ? badge.dataset.label.replace('{value}', tokens) : '';
		});

	}

}

document.addEventListener('navigation.wakeup', () => UtilOmnibox.countFilters());
window.addEventListener('resize', () => UtilOmnibox.countFilters());

document.delegateEventListener('input', '.util-omnibox-input', function() {

	const box = UtilOmnibox.getBox(this);

	box.omniboxAfterRead = undefined;

	UtilOmnibox.render(box);

});

document.delegateEventListener('focusin', '.util-omnibox-input', function() {

	const box = UtilOmnibox.getBox(this);

	if(box.omniboxRefocus === true) {
		return;
	}

	UtilOmnibox.openFullscreen(box);
	UtilOmnibox.render(box);

});

document.delegateEventListener('keydown', '.util-omnibox-input', function(e) {

	const box = UtilOmnibox.getBox(this);

	if(e.key === 'ArrowDown' || e.key === 'ArrowUp') {

		e.preventDefault();

		if(box.qs('.util-omnibox-list.is-open') === null) {
			UtilOmnibox.render(box);
		}

		UtilOmnibox.move(box, e.key === 'ArrowDown' ? 1 : -1);

	} else if(e.key === 'Enter') {

		e.preventDefault();

		UtilOmnibox.afterRead(box, ok => {
			if(ok) {
				UtilOmnibox.enter(box);
			}
		});

	} else if(e.key === 'Escape') {

		// Un plein écran dont le champ est vide n'a rien à garder : fermer la liste d'abord le laisserait réduit
		// au champ seul. Une liste au repos s'ouvre sur un champ vide, d'où cette branche en tête.
		if(box.omniboxPlaceholder !== undefined && UtilOmnibox.isEmpty(box)) {
			UtilOmnibox.dismiss(box);
			UtilOmnibox.closeFullscreen(box);
		} else if(box.qs('.util-omnibox-list.is-open') !== null || box.omniboxReading === true) {
			UtilOmnibox.dismiss(box);
		} else if(box.omniboxPlaceholder !== undefined) {
			UtilOmnibox.closeFullscreen(box);
		} else if(box.classList.contains('is-unfolded')) {
			this.blur();
			UtilOmnibox.fold(box);
		} else {
			this.value = '';
			UtilOmnibox.render(box);
		}

	} else if(e.key === 'Backspace' && this.value === '') {

		// Le geste attendu de tout champ à jetons : effacer le dernier posé.
		const tokens = [...box.qsa('.util-omnibox-token')];

		if(tokens.length > 0) {

			e.preventDefault();

			if(tokens[tokens.length - 1].href) {
				UtilOmnibox.navigate(box, tokens[tokens.length - 1]);
			} else {
				UtilOmnibox.removeToken(tokens[tokens.length - 1]);
			}

		}

	}

});

// Une liste montrée à plat, hors de toute omnibox, n'a pas de boîte où poser un choix : la page
// design system en présente ainsi, et son clic reste celui du navigateur.
document.delegateEventListener('click', '.util-omnibox .util-omnibox-item', function(e) {

	// Un lien laisse le navigateur faire, sauf clic modifié : un nouvel onglet doit
	// rester possible sur une suggestion qui mène ailleurs.
	if(this.href && (e.ctrlKey || e.metaKey || e.shiftKey)) {
		return;
	}

	e.preventDefault();

	UtilOmnibox.pick(UtilOmnibox.getBox(this), this);

});

document.delegateEventListener('click', '.util-omnibox-token', function(e) {

	if(this.href) {
		return;
	}

	e.preventDefault();

	UtilOmnibox.removeToken(this);

});

document.delegateEventListener('click', '.util-omnibox-clear, .util-omnibox-clear-all', function(e) {

	if(this.href) {
		return;
	}

	e.preventDefault();

	UtilOmnibox.clear(UtilOmnibox.getBox(this));

});

document.delegateEventListener('click', '.util-omnibox-unfold', function(e) {

	e.preventDefault();

	UtilOmnibox.unfold(UtilOmnibox.getBox(this));

});

// Partir au clavier referme la liste, et replie la boîte qui se replie, de quelque élément qu'on en sorte :
// le champ, une suggestion, un jeton de la rangée ou « Tout effacer ». Une page porte parfois plusieurs
// boîtes, et le focus qui passe à l'une ne doit pas laisser la liste de l'autre couvrir la sienne. Un
// clic ailleurs referme aussi, mais par le délégué du document et non ici : le clic sur une suggestion
// fait quitter le champ avant d'arriver à la suggestion, et la liste se fermerait sous le pointeur.
document.delegateEventListener('focusout', '.util-omnibox', function(e) {

	if(e.relatedTarget === null || this.contains(e.relatedTarget)) {
		return;
	}

	UtilOmnibox.close(this);

	if(this.classList.contains('util-omnibox-folded')) {
		UtilOmnibox.fold(this);
	}

});

// Cliquer dans le cadre, mais à côté du champ, met le curseur dedans : le cadre a
// l'apparence d'un champ de saisie, il doit se comporter comme tel.
document.delegateEventListener('click', '.util-omnibox-field', function(e) {

	if(e.target.closest('a, input') === null) {
		this.qs('.util-omnibox-input').focus();
	}

});

// Dans l'enveloppe plein écran, l'espace laissé libre sous la liste est la sortie :
// c'est le geste attendu d'un panneau qui couvre tout, à côté du bouton retour.
document.delegateEventListener('click', '.util-omnibox-wrapper', function(e) {

	if(e.target === this) {
		UtilOmnibox.closeFullscreen(this.qs('.util-omnibox'));
	}

});

document.delegateEventListener('click', '.util-omnibox-back', function() {
	UtilOmnibox.closeFullscreen(UtilOmnibox.getBox(this));
});

// Un clic hors de la boîte referme les suggestions : la liste couvre ce qu'on lit. Une liste ouverte hors
// d'une boîte, comme celles que la page du design system montre à plat, n'a rien à refermer.
// Le chemin du clic se lit tel qu'il était au clic (`composedPath()`) : un élément de la boîte retiré par
// son propre clic, suggestion refaite à son choix ou jeton ôté, a quitté le document quand le clic arrive
// ici, et `contains()` le dirait hors de la boîte, dont la liste se refermerait.
// Une boîte repliée garde `contains()` : ôter un jeton la replie, le champ ayant perdu le focus au clic.
document.addEventListener('click', function(e) {

	const path = e.composedPath();

	qsa('.util-omnibox .util-omnibox-list.is-open', list => {

		if(path.includes(list.closest('.util-omnibox')) === false) {
			list.classList.remove('is-open');
		}

	});

	qsa('.util-omnibox-folded.is-unfolded', box => {

		if(box.contains(e.target) === false && box.omniboxPlaceholder === undefined) {
			UtilOmnibox.fold(box);
		}

	});

});
