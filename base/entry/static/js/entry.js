/*
 * Le champ de saisie (`EntryUi::getMenu()`, `EntryUi::getHarvestCell()`), posé sur l'omnibox
 * (`ouvretaferme/main/static/js/omnibox.js`). Une page en porte plusieurs, celui du menu et ceux des cases
 * d'un jour de récolte : chacun se tient par sa classe `entry-field`, depuis le nœud qui reçoit le geste.
 *
 * L'omnibox porte le châssis, ce fichier ne fait que deux choses : il traduit le choix d'une
 * suggestion selon sa nature (`intention:`, `plant:`, `object:`, `cultivation:`, `task:`, `category:`, `duration:`, `all`,
 * `write`, pour le récapitulatif d'une vente `recap`, `line:`, `recapAdd`, `date:`, `dateOther`, `status:`, et pour celui
 * d'une intervention les mêmes puces de jour et d'état, avec `week:` et `weekOther`), et il tient à jour ce que la
 * lecture suivante enverra au serveur
 * (`data-suggestions-body`).
 *
 * Le serveur relit toute la phrase à chaque demande : les jetons posés font le préfixe, la frappe la
 * suite, la réponse à la question voyage à part. Rien de la lecture n'est tenu ici que ce qu'elle
 * rend pour l'écran (`EntryUi::getEntry()`).
 */
class EntryField {

	// La case ouverte qu'un `pointerdown` a quittée, dont le clic qui suit ne fait que la fermer (HN-B19).
	static cellOutside = null;

	static getBox(node) {
		return node.closest('.entry-field');
	}

	/*
	 * Vide, le champ propose les gestes du « + » de l'univers de l'écran, ou ceux de tous les univers
	 * quand le sien n'en a pas (Pilotage, Gestion). Le serveur les rend tous, une fois : le champ vit
	 * dans l'en-tête, qu'une navigation ajax ne réécrit pas, et l'univers de l'écran change sous lui.
	 * Un membre sans aucun geste n'a pas de liste : le focus lit alors la phrase vide, et propose les
	 * verbes.
	 *
	 * Le message d'une écriture perdue et les jetons des verbes se relèvent ici aussi, sur l'enveloppe : le plein
	 * écran du bas emporte la boîte hors d'elle (`UtilOmnibox.openFullscreen()`) dès le focus, et `setIdle()` passe
	 * avant lui.
	 */
	static setIdle(box) {

		box.entryPlaceholder ??= box.qs('.util-omnibox-input').placeholder;
		box.entryLostWrite ??= box.closest('[data-lost-write]').dataset.lostWrite;
		box.entryIntentionTokens ??= JSON.parse(box.closest('[data-intention-tokens]').dataset.intentionTokens);
		box.entryGestures ??= JSON.parse(box.dataset.suggestionsIdle ?? '[]');

		if(box.entryGestures.length === 0) {
			return;
		}

		const section = Structure.getScreenSection();
		const gestures = box.entryGestures.filter(gesture => gesture.section === section);

		box.dataset.suggestionsIdle = JSON.stringify(gestures.length > 0 ? gestures : box.entryGestures);

	}

	/*
	 * Le libellé du jeton d'un verbe, qui se relit comme lui (`IntentionUi::getToken()`), que le choisisse un geste
	 * du champ vide ou la liste des verbes : « Temps », que « Temps de travail » dit dans la liste.
	 */
	static getIntentionToken(box, intention) {
		return box.entryIntentionTokens[intention] ?? null;
	}

	/*
	 * La croix ramène au départ, un champ vide. Après une écriture, le jour qu'elle a dit reste en jeton
	 * (`date`, rendu par `doWrite`), pour la saisie suivante.
	 */
	static reset(box, date = null) {

		box.entryPlant = undefined;

		box.qsa('.util-omnibox-token', token => token.remove());
		box.qs('.util-omnibox-input').value = '';
		UtilOmnibox.forgetRead(box);

		// Les réponses voyagent par leurs jetons, que la ligne vient de retirer, sauf « Toutes les séries », le
		// récapitulatif et l'état qu'il a choisi, qui n'en ont pas : sans cela, la phrase suivante cocherait toutes
		// les séries ou s'ouvrirait sur le récapitulatif sans le demander.
		const body = EntryField.getBody(box);
		delete body.all;
		box.dataset.suggestionsBody = JSON.stringify(body);

		EntryField.closeRecap(box);

		EntryField.setCompletion(box, null);
		EntryField.restorePlaceholder(box);

		if(date !== null) {
			UtilOmnibox.addToken(box, 'date', date.value, date.label);
		}

		UtilOmnibox.change(box);

		// Comme `UtilOmnibox.clear()` : remis au départ, le champ ne propose rien avant la frappe suivante. Le plein
		// écran du bas reste ouvert pour la saisie suivante, et sa liste est tout ce qu'il montre au-dessus du champ :
		// elle revient à ce que le focus propose au champ remis au départ.
		if(UtilOmnibox.isFullscreenBottom(box)) {
			UtilOmnibox.render(box);
		} else {
			UtilOmnibox.close(box);
		}

	}

	/*
	 * La fin du mot en frappe que la lecture propose (`EntryLib::getCompletion()`), pour le texte du champ
	 * qu'elle a lu. La frappe qui suit la reprend tant qu'elle la suit, jusqu'à la lecture suivante.
	 */
	static setCompletion(box, suffix) {

		box.entryCompletion = (suffix === null) ? null : {value: box.qs('.util-omnibox-input').value, suffix: suffix};

		EntryField.showCompletion(box);

	}

	static getCompletion(box) {

		const completion = box.entryCompletion ?? null;
		const value = box.qs('.util-omnibox-input').value;

		if(completion === null || value.startsWith(completion.value) === false) {
			return null;
		}

		const typed = UtilOmnibox.normalize(value.slice(completion.value.length));

		if(UtilOmnibox.normalize(completion.suffix).startsWith(typed) === false) {
			return null;
		}

		const rest = completion.suffix.slice(typed.length);

		return (rest === '') ? null : rest;

	}

	/*
	 * Le grisé ne se pose qu'à la fin d'un texte qui tient dans le champ : ailleurs, il ne prolongerait pas
	 * ce qui est sous les yeux. Le clavier le prend aux mêmes conditions, qu'il relit lui-même : la touche
	 * peut précéder le `selectionchange` qui aurait posé le grisé.
	 */
	static getVisibleCompletion(box) {

		const input = box.qs('.util-omnibox-input');

		const visible = (
			document.activeElement === input &&
			input.readOnly === false &&
			input.selectionStart === input.value.length &&
			input.scrollWidth <= input.clientWidth
		);

		return visible ? EntryField.getCompletion(box) : null;

	}

	static showCompletion(box) {

		const input = box.qs('.util-omnibox-input');
		const rest = EntryField.getVisibleCompletion(box);

		const left = input.offsetLeft;

		let ghost = box.qs('.entry-completion');

		if(rest === null) {
			ghost?.remove();
			return;
		}

		if(ghost === null) {
			ghost = document.createElement('span');
			ghost.className = 'entry-completion';
			input.after(ghost);
		}

		const typed = document.createElement('span');

		typed.className = 'entry-completion-typed';
		typed.textContent = input.value;

		ghost.replaceChildren(typed, rest);
		ghost.style.left = left +'px';

	}

	/*
	 * Tab, la flèche à droite ou la ligne de tête prennent la fin du mot, avec l'espace qui le finit : la
	 * lecture passe au mot suivant comme après une frappe.
	 */
	static complete(box) {

		const rest = EntryField.getCompletion(box);

		if(rest === null) {
			return false;
		}

		const input = box.qs('.util-omnibox-input');

		input.value += rest +' ';
		input.focus();

		EntryField.setCompletion(box, null);

		input.dispatchEvent(new Event('input', {bubbles: true}));

		return true;

	}

	/*
	 * Le texte indicatif suit la lecture (`EntryUi::getPlaceholder()`), et le champ vidé n'en lit
	 * aucune : il reprend celui de la page, que la liste des gestes accompagne. Il est relevé au
	 * premier focus (`setIdle()`), avant lequel aucune lecture n'a pu le changer.
	 */
	static restorePlaceholder(box) {

		if(box.entryPlaceholder !== undefined) {

			const input = box.qs('.util-omnibox-input');
			input.placeholder = box.entryPlaceholder;

			// Le texte indicatif change sans frappe : sous Firefox, la largeur d'une case le suit par `size`.
			if(box.classList.contains('util-omnibox-scroll')) {
				UtilOmnibox.sizeInput(input);
			}

		}

	}

	static getBody(box) {
		return JSON.parse(box.dataset.suggestionsBody);
	}

	/*
	 * Le corps de la prochaine lecture. Le préfixe est fait des libellés des jetons, dans l'ordre où
	 * ils sont posés : le serveur les lit comme s'ils avaient été tapés.
	 */
	static refresh(box, changes = {}) {

		const body = {...EntryField.getBody(box), ...changes};

		// Les objets des lignes voyagent aussi par leurs jetons, dans l'ordre des lignes : leur libellé ne les
		// départage pas toujours (`lines`, `EntryLib::read()`).
		body.lines = UtilOmnibox.getTokens(box)
			.filter(token => token.group === 'line')
			.map(token => token.value)
			.join(',');

		// Les jours qu'un temps ajoute au sien aussi, que la phrase ne dit pas (`TimeIntentionLib::getDays()`).
		body.days = UtilOmnibox.getTokens(box)
			.filter(token => token.group === 'day')
			.map(token => token.value)
			.join(',');

		// Les réponses à la question voyagent à part, chacune portée par son jeton.
		['cultivation', 'task', 'addCultivation', 'addNoSeries', 'category'].forEach(group => {

			const answer = UtilOmnibox.getTokens(box).find(token => token.group === group);

			if(answer === undefined) {
				delete body[group];
			} else {
				body[group] = answer.value;
			}

		});

		// Une espèce retirée emporte la réponse qui la concernait. Une espèce tapée sans être choisie
		// n'a pas de jeton : la réponse posée à sa suite reste.
		const plantRemoved = (
			(box.entryTokens ?? '').includes('plant:') &&
			UtilOmnibox.getTokens(box).some(token => token.group === 'plant') === false &&
			box.entryPlant === undefined
		);

		// La variété et le calibre aussi, qui ne valent que pour elle.
		if(plantRemoved) {
			delete body.cultivation;
			delete body.all;
			box.qsa('.util-omnibox-token[data-group="cultivation"], .util-omnibox-token[data-group="variety"], .util-omnibox-token[data-group="harvestSize"]', token => token.remove());
		}

		// De même pour l'intervention d'un temps seul et la réponse qui la précisait, la série ou la catégorie où
		// l'ajouter, les jours où l'écrire, et pour « Toutes les séries » de la coche, qui n'a pas de jeton.
		const objectRemoved = (
			(box.entryTokens ?? '').includes('object:') &&
			UtilOmnibox.getTokens(box).some(token => token.group === 'object') === false
		);

		if(objectRemoved) {
			delete body.all;
			delete body.days;
			['task', 'addCultivation', 'addNoSeries', 'category', 'day'].forEach(group => {
				delete body[group];
				box.qsa('.util-omnibox-token[data-group="'+ group +'"]', token => token.remove());
			});
		}

		// Retiré, ou emporté avec l'action, le jeton de série rend l'espèce qu'il avait remplacée, et la question se
		// repose. Après les retraits : la série partie avec l'action laisserait sinon l'espèce cachée dans le préfixe.
		if(
			box.entryPlant !== undefined &&
			UtilOmnibox.getTokens(box).some(token => EntryField.getSeriesGroups().includes(token.group)) === false
		) {

			const {value, label, icon} = box.entryPlant;

			box.entryPlant = undefined;

			UtilOmnibox.addToken(box, 'plant', value, label, icon);

		}

		// Le préfixe se fait des jetons restés après ces retraits. L'espèce que le jeton de série a remplacée se
		// lit toujours : c'est d'elle que le serveur tire la question et la confirmation. Une durée part en minutes
		// et non par son libellé : « 1 h » suivi de la quantité tapée se lirait « 1 h 5 », une heure et cinq
		// minutes. La variété et le calibre partent par le mot que leur jeton porte, leur nom ou le mot-clé de la
		// part sans couple, que le libellé « Non spécifiée » ne dit pas, et l'unité par le mot que le serveur lit.
		body.prefix = UtilOmnibox.getTokens(box)
			.filter(token => ['intention', 'date', 'week', 'plant', 'object', 'line', 'duration', 'variety', 'harvestSize', 'unit'].includes(token.group))
			.map(token => EntryField.getPrefixWord(token))
			.concat(box.entryPlant === undefined ? [] : [box.entryPlant.label])
			.join(' ');

		box.entryTokens = EntryField.getSignature(box);

		box.dataset.suggestionsBody = JSON.stringify(body);

		// La mémoire de l'omnibox ne connaît que la frappe : à frappe égale, une réponse changée
		// ne redemanderait rien.
		box.omniboxLoaded = undefined;

		// Le dernier jeton retiré vide le champ sans frappe, et sans lecture : la question posée par celle d'avant
		// resterait sous la liste des gestes.
		if(UtilOmnibox.isIdle(box)) {
			EntryField.restorePlaceholder(box);
		}

		UtilOmnibox.render(box);
		EntryField.sizeList(box);

	}

	/*
	 * Une réponse choisie, ce que la phrase a déjà dit passe en jetons et le champ se vide des mots qui l'ont dit
	 * (`EntryUi::getEntry()`) : le verbe tapé, en tête, puis l'objet, puis l'espèce qu'un temps lit après son
	 * action. Une série dit déjà l'espèce : son jeton prend la place de celui de l'espèce, que la boîte garde pour
	 * le préfixe et pour le rendre si on retire la série (`refresh()`). L'objet d'une récolte est cette espèce. Le
	 * reste de la frappe, une quantité ou un jour, reste la phrase à relire.
	 */
	static setSaid(box, kind) {

		const input = box.qs('.util-omnibox-input');
		const typingPrefix = box.entry?.typingPrefix ?? false;

		const verb = box.entry?.intention ?? null;

		if(verb !== null && box.qs('.util-omnibox-token[data-group="intention"]') === null) {
			const first = box.qs('.util-omnibox-token');
			const token = UtilOmnibox.addToken(box, 'intention', verb.value, verb.label);
			first?.before(token);
			input.value = EntryField.withoutPending(input.value, verb.named, typingPrefix);
		}

		const object = box.entry?.object ?? null;

		if(kind !== 'cultivation' && object !== null && box.qs('.util-omnibox-token[data-group="object"]') === null) {
			UtilOmnibox.addToken(box, 'object', String(object.value), object.label, object.icon ?? undefined);
			input.value = EntryField.withoutPending(input.value, object.named, typingPrefix);
		}

		const species = (kind === 'cultivation') ? object : (box.entry?.plant ?? null);
		const replaced = EntryField.getSeriesGroups().includes(kind);
		const plant = box.qs('.util-omnibox-token[data-group="plant"]');

		if(plant !== null) {

			if(replaced) {
				box.entryPlant = {value: plant.dataset.value, label: plant.dataset.label, icon: plant.dataset.icon};
				plant.remove();
			}

		} else if(species !== null && box.entryPlant === undefined) {

			if(replaced) {
				box.entryPlant = {value: String(species.value), label: species.label, icon: species.icon ?? undefined};
			} else {
				UtilOmnibox.addToken(box, 'plant', String(species.value), species.label, species.icon ?? undefined);
			}

			input.value = EntryField.withoutPending(input.value, species.named, typingPrefix);

		}

	}

	// Les réponses qui nomment une série, et disent donc l'espèce.
	static getSeriesGroups() {
		return ['cultivation', 'addCultivation'];
	}

	static getPrefixWord(token) {

		switch(token.group) {

			case 'duration' :
				return token.value +' min';

			case 'variety' :
			case 'harvestSize' :
			case 'unit' :
				return token.value;

			default :
				return token.label;

		}

	}

	static getSignature(box) {

		const signature = UtilOmnibox.getTokens(box).map(token => token.group +':'+ token.value);

		if(box.entryPlant !== undefined) {
			signature.push('plant:'+ box.entryPlant.value);
		}

		return signature.join(' ');

	}

	/*
	 * Le texte tapé moins les mots en attente, que le serveur rend découpés et normalisés comme
	 * `EntryLib::split()` (minuscules, sans accents ni emoji, « d' » détaché, « 12kg » coupé) : chaque mot tapé
	 * est découpé de la même façon et les morceaux sont retrouvés en partant de la fin. Un mot tapé dont
	 * un morceau est retrouvé ne garde que ses autres morceaux, élision exceptée : « d'aub » part entier,
	 * « 12courg » laisse « 12 ».
	 * Sans mot en attente, rien : la lecture a compris chaque mot tapé, « vente » compris quand on choisit le
	 * client qu'il demande.
	 */
	static withoutPending(value, pending, typingPrefix) {

		const normalize = word => word.toLowerCase().normalize('NFD').replace(/\p{Mn}/gu, '').replace(/[\p{So}\p{Sk}\p{Cf}]/gu, ' ');

		const words = value.trim().split(/\s+/).filter(word => word !== '');
		const pieces = words.flatMap((word, index) => normalize(word)
			.replace(/[’‘]/g, "'")
			.replace(/^d'/, "d' ")
			.replace(/([0-9])([^0-9,.])/g, '$1 $2')
			.split(' ')
			.filter(piece => piece !== '')
			.map(piece => ({piece, index, matched: false})));

		const remaining = (pending === null) ? [] : pending.split(' ');

		for(let position = pieces.length - 1; position >= 0 && remaining.length > 0; position--) {

			if(pieces[position].piece === remaining[remaining.length - 1]) {
				pieces[position].matched = true;
				remaining.pop();
			}

		}

		const kept = words
			.map((word, index) => {
				const own = pieces.filter(piece => piece.index === index);
				return own.some(piece => piece.matched) ?
					own.filter(piece => piece.matched === false && piece.piece !== "d'").map(piece => piece.piece).join('') :
					word;
			})
			.filter(word => word !== '');

		// Un choix pose l'espace qui suit, pour que le mot d'après ne se colle pas au dernier. Pas derrière un
		// mot que la lecture n'a pris que pour un début (`typingPrefix`, `EntryLib::isTypingPrefix()`) et que
		// le choix laisse : l'espace en ferait un mot fini, et « 4 p » ne se lirait plus en pièces.
		const prefix = (
			typingPrefix &&
			/\s$/.test(value) === false &&
			pieces.some(piece => piece.index === words.length - 1 && piece.matched) === false
		);

		return kept.length === 0 ? '' : kept.join(' ') + (prefix ? '' : ' ');

	}

	static pick(box, item) {

		// La famille s'arrête au premier deux-points : la valeur d'une puce du couple est un nom, qui peut en porter.
		const [kind, ...rest] = item.dataset.value.split(':');
		const value = (rest.length === 0) ? undefined : rest.join(':');

		const label = item.dataset.label;
		const input = box.qs('.util-omnibox-input');

		switch(kind) {

			// Le jeton remplace les mots qui l'ont fait proposer, « patate d » pour Patate douce : ce
			// qui était tapé à côté, une quantité par exemple, reste.
			// Un geste du champ vide (`EntryUi::getMenu()`) dit « Une récolte » dans la liste, et son
			// jeton dit le verbe, comme celui que la lecture propose.
			case 'intention' :
			case 'plant' :
			case 'object' :

				// Une intention qui lit des lignes redit la phrase en jetons (`setTokens()`), pour que l'objet
				// choisi vienne après elles dans la phrase relue. Il ouvre une ligne : son jeton en est une, et
				// porte son id jusqu'au serveur, que son nom ne départage pas toujours d'un autre.
				if(kind === 'object' && (box.entry?.tokens ?? null) !== null) {
					EntryField.setTokens(box);
					UtilOmnibox.addToken(box, 'line', value, label, item.dataset.icon);
					input.value = box.entry.rest;
					EntryField.refresh(box);
					break;
				}

				// Un objet ou une espèce répond à la phrase : ce qu'elle a déjà dit passe d'abord en jetons, l'action
				// tapée en entier devant l'espèce qu'elle demande (`setSaid()`).
				if(kind !== 'intention') {
					EntryField.setSaid(box, kind);
				}

				UtilOmnibox.addToken(box, kind, value, ((kind === 'intention') ? EntryField.getIntentionToken(box, value) : null) ?? label, item.dataset.icon);
				input.value = EntryField.withoutPending(input.value, box.entry?.pending ?? null, box.entry?.typingPrefix ?? false);
				EntryField.refresh(box);
				break;

			// La réponse à la question voyage à part, portée par son jeton, après ce que la phrase a déjà dit
			// (`setSaid()`).
			case 'cultivation' :
			case 'task' :
			case 'addCultivation' :
			case 'addNoSeries' :
			case 'category' :
				EntryField.setSaid(box, kind);
				UtilOmnibox.addToken(box, kind, value, label, item.dataset.icon, item.dataset.badge);
				EntryField.refresh(box);
				break;

			// Une puce de durée ajoute au temps déjà dit (`DurationComplementUi::getAdditionResults()`) : sa valeur est
			// le temps qu'elle donne, qui remplace le jeton ou la durée tapée, et son jeton le dit (`token`). Sous la
			// coche (`DoneIntentionUi::getResults()`), la phrase devient un temps.
			case 'duration' : {
				// Sa valeur est un total, calculé sur le temps de la phrase que la liste a lue. Une puce choisie depuis
				// change ce temps, et tant qu'aucune lecture n'a répondu à la phrase du champ, en cours ou échouée, la
				// liste ajouterait au temps d'avant : la puce ne fait rien. `refresh()` oublie la frappe lue, qu'une
				// réponse seule repose.
				if(box.omniboxLoaded !== input.value) {
					break;
				}
				const named = box.entry?.complementsNamed?.duration ?? null;
				box.qs('.util-omnibox-token[data-group="duration"]')?.remove();
				if(named !== null) {
					input.value = EntryField.withoutPending(input.value, named, box.entry?.typingPrefix ?? false);
				}
				UtilOmnibox.addToken(box, 'duration', value, item.dataset.token);
				EntryField.refresh(box);
				break;
			}

			// Une puce qui tape son mot à la suite de la phrase, qui se relit : le colis ou l'unité d'une vente après
			// un nombre qui ne dit ni l'un ni l'autre, « 2 » devenant « 2 colis » ou « 2 kg ».
			case 'type' :
				EntryField.type(box, value +' ');
				EntryField.refresh(box);
				break;

			case 'all' :
				EntryField.refresh(box, {all: 1});
				break;

			// La coche de la même phrase, sous le temps (`DoneIntentionUi::getTimedResult()`).
			case 'timeDone' :
				EntryField.markDone(box, item.dataset.value.split(':').slice(1).join(':'));
				return;

			case 'completion' :
				EntryField.complete(box);
				break;

			case 'write' :
				EntryField.write(box);
				return;

			case 'recap' :
				EntryField.recap(box);
				break;

			case 'line' :
				EntryField.retype(box, Number(value));
				break;

			// Le récapitulatif se replie sur la liste du produit suivant, que la frappe complète en ligne. Le
			// jour et l'état choisis restent : ils valent pour la vente, pas pour le récapitulatif.
			case 'recapAdd' :
				EntryField.foldRecap(box);
				EntryField.refresh(box);
				break;

			case 'date' :
				EntryField.date(box, value);
				break;

			// Un autre jour se dit avec le vocabulaire du champ (`DateComplementLib`) : « le 12 », « lundi ». Le jeton
			// du jour retenu reste en tête de la phrase, et le récapitulatif sur lui, jusqu'à ce que la frappe dise
			// un autre jour (`load()`).
			// Sous une intervention (`InterventionIntentionUi`), sans jetons à redire, le jour que la phrase disait part,
			// et l'autre se tape à sa place.
			case 'dateOther' :
				if((box.entry?.tokens ?? null) === null) {
					EntryField.unsay(box, ['date']);
					EntryField.type(box, 'le ');
				} else {
					EntryField.setTokens(box);
					input.value = 'le ';
				}
				EntryField.refresh(box);
				break;

			// La semaine d'une intervention à faire se pose en jeton, comme le jour d'une puce, et remplace le jour ou
			// la semaine que la phrase disait. Une autre semaine se tape par son numéro (« semaine 46 »,
			// `WeekComplementLib`).
			case 'week' :
				EntryField.unsay(box, ['date', 'week']);
				UtilOmnibox.addToken(box, 'week', value, label);
				EntryField.refresh(box);
				break;

			case 'weekOther' :
				EntryField.unsay(box, ['date', 'week']);
				EntryField.type(box, 'semaine ');
				EntryField.refresh(box);
				break;

			// L'état d'une intervention change ce qui la date, le jour d'une faite, la semaine d'une à faire : ce que la
			// phrase en disait part avec lui. Celui d'une vente laisse le jour, qui vaut pour les deux.
			case 'status' :
				if((box.entry?.tokens ?? null) === null) {
					EntryField.unsay(box, ['date', 'week']);
				}
				EntryField.refresh(box, {status: value});
				break;

			// Une puce du couple ou d'unité devient un jeton, un par famille, et remplace ce qui disait déjà la famille,
			// son jeton ou ses mots tapés (« cœur de bœuf », « bottes »), comme le début de nom tapé qui l'a fait
			// proposer. La puce retenue, choisie de nouveau, se décoche : ce qui la disait part et la famille redevient
			// libre, l'unité revenant à celle de la semaine. Comme une réponse, la puce fait d'abord passer en jetons ce
			// que la phrase a déjà dit (`setSaid()`).
			case 'variety' :
			case 'harvestSize' :
			case 'unit' : {
				EntryField.setSaid(box, kind);
				const named = (kind === 'unit') ?
					(box.entry?.complementsNamed?.unit ?? null) :
					(box.entry?.couple?.named[kind] ?? null);
				box.qs('.util-omnibox-token[data-group="'+ kind +'"]')?.remove();
				if(named !== null) {
					input.value = EntryField.withoutPending(input.value, named, box.entry?.typingPrefix ?? false);
				}
				if(item.classList.contains('is-selected') === false) {
					UtilOmnibox.addToken(box, kind, value, label);
				}
				input.value = EntryField.withoutPending(input.value, box.entry?.pending ?? null, box.entry?.typingPrefix ?? false);
				EntryField.refresh(box);
				break;
			}

			// Une puce de jour s'ajoute aux autres, un jeton par jour, et se décoche en le retirant. Celle du jour que
			// la phrase tape n'a pas de jeton : elle reste cochée tant que la phrase le dit. Aujourd'hui, coché sans
			// jeton quand la phrase ne tape aucun jour, n'est qu'un défaut que la première puce choisie remplace :
			// choisi, il prend un jeton comme les autres, et le résultat ne dépend pas de l'ordre des choix.
			case 'day' : {
				EntryField.setSaid(box, kind);
				const chosen = UtilOmnibox.getTokens(box).some(token => token.group === 'day' && token.value === value);
				const typed = (box.entry?.complementsNamed?.date ?? null) !== null;
				if(chosen) {
					box.qs('.util-omnibox-token[data-group="day"][data-value="'+ value +'"]')?.remove();
				} else if(item.classList.contains('is-selected') === false || typed === false) {
					UtilOmnibox.addToken(box, 'day', value, label);
				}
				input.value = EntryField.withoutPending(input.value, box.entry?.pending ?? null, box.entry?.typingPrefix ?? false);
				EntryField.refresh(box);
				break;
			}

		}

		input.focus();

	}

	/*
	 * Le récapitulatif qu'une intention montre avant d'écrire (ses puces déclarées par `IntentionUi::getChips()`),
	 * que la seconde Entrée écrit : la phrase passe en jetons, et la lecture le
	 * demande par `recap`. Il reste ouvert pendant qu'on corrige, et tombe avec la croix, l'écriture ou l'objet de
	 * la phrase (`load()`).
	 */
	static recap(box) {

		UtilOmnibox.afterRead(box, ok => {

			if(ok !== true || box.entry.outcome !== 'write' || box.entry.refusal !== null) {
				return;
			}

			EntryField.setTokens(box);

			box.qs('.util-omnibox-input').value = '';

			EntryField.refresh(box, {recap: 1});

			// Les jetons posés par `setTokens()` ne passent pas par la saisie de l'omnibox : `change()` lui fait
			// poser la croix, qui ramène au départ. La signature est déjà à jour, rien ne se relit.
			UtilOmnibox.change(box);

		});

	}

	// Le récapitulatif se replie, la phrase garde l'état qu'il lui a donné.
	static foldRecap(box) {

		const body = EntryField.getBody(box);

		delete body.recap;

		box.dataset.suggestionsBody = JSON.stringify(body);

		box.style.removeProperty('--util-omnibox-list-height');
		EntryField.sizeList(box);

	}

	// Le récapitulatif se ferme avec ce qu'il a réglé : la phrase suivante repart de l'état par défaut.
	static closeRecap(box) {

		EntryField.foldRecap(box);

		const body = EntryField.getBody(box);

		delete body.status;

		box.dataset.suggestionsBody = JSON.stringify(body);

	}

	/*
	 * Un article choisi dans le récapitulatif revient dans le champ, sous le libellé de son jeton, pour qu'on en
	 * retape la quantité. Il est relu après les autres et passe donc en dernier. Un article encore tapé n'a pas
	 * de jeton : il est déjà dans le champ.
	 */
	static retype(box, position) {

		const token = [...box.querySelectorAll('.util-omnibox-token[data-group="line"]')][position];

		if(token === undefined) {
			return;
		}

		const input = box.qs('.util-omnibox-input');

		input.value = (input.value.trim() === '') ? token.dataset.label : input.value.trim() +' '+ token.dataset.label;

		token.remove();

		EntryField.refresh(box);

	}

	/*
	 * Le jour d'une puce du récapitulatif : son jeton en tête, comme celui que `reset()` repose, ou aucun pour
	 * aujourd'hui. Un jour encore tapé (« le 12 ») passe d'abord en jeton, pour être remplacé lui aussi. Une
	 * intention sans lignes n'a pas de jetons à redire : ce que la phrase a déjà dit y passe en jetons
	 * (`setSaid()`), et le jour tapé s'efface du champ.
	 */
	static date(box, label) {

		const input = box.qs('.util-omnibox-input');

		if(box.entry.tokens === null) {
			EntryField.unsay(box, ['date']);
		} else {
			EntryField.setTokens(box);
			input.value = box.entry.rest;
			box.qsa('.util-omnibox-token[data-group="date"]', token => token.remove());
		}

		if(label !== '') {

			const first = box.qs('.util-omnibox-token');
			const token = UtilOmnibox.addToken(box, 'date', label, label);

			first?.before(token);

		}

		EntryField.refresh(box);

	}

	/*
	 * Ce que la phrase dit de ces compléments part, jetons comme mots tapés, pour qu'une puce le dise à sa place.
	 * Le reste de ce qu'elle a déjà dit passe d'abord en jetons (`setSaid()`). Le groupe d'un jeton est le nom du
	 * complément qu'il dit.
	 */
	static unsay(box, complements) {

		const input = box.qs('.util-omnibox-input');

		EntryField.setSaid(box, complements[0]);

		complements.forEach(complement => {

			const named = box.entry?.complementsNamed?.[complement] ?? null;

			if(named !== null) {
				input.value = EntryField.withoutPending(input.value, named, box.entry?.typingPrefix ?? false);
			}

			box.qsa('.util-omnibox-token[data-group="'+ complement +'"]', token => token.remove());

		});

	}

	// Le début d'un complément à finir de taper, après ce qui reste de la frappe.
	static type(box, words) {

		const input = box.qs('.util-omnibox-input');

		input.value = (input.value.trim() === '') ? words : input.value.trim() +' '+ words;

	}

	/*
	 * Le temps et la coche d'un même geste (lot S4c) : le verbe de la coche se pose en jeton, en tête comme le
	 * dit la phrase, et la phrase relue par le serveur est celle de la coche. `step` vaut `write:<verbe>`, qui
	 * écrit aussitôt, ou `ask:<verbe>`, qui relit la phrase pour que la coche pose sa question.
	 */
	static markDone(box, step) {

		const [action, verb] = step.split(':');

		const first = box.qs('.util-omnibox-token');
		const token = UtilOmnibox.addToken(box, 'intention', 'done', verb);

		first?.before(token);

		// Le préfixe de l'écriture est celui que pose la lecture (`refresh()`), avec le verbe avant. L'écriture
		// part sans attendre la réponse de cette lecture, que le serveur refait de toute façon : elle n'a rien
		// à montrer avant, et l'attendre la ferait dépendre de l'ordre des réponses.
		EntryField.refresh(box);

		if(action === 'write') {
			EntryField.send(box);
		} else {
			box.qs('.util-omnibox-input').focus();
		}

	}

	/*
	 * L'écriture ne recharge pas la page : la réponse pose la pastille de la récolte, avec son lien
	 * « Annuler », puis réécrit les lignes de l'ancien planning qu'elle touche (`entry.v.php`). Le champ
	 * reste ouvert et repart vide pour la saisie suivante. Refusée, la saisie
	 * reste sous les yeux : `Ajax.Query` résout aussi une réponse en erreur, seule l'écriture porte
	 * `written`. `entryWrite` dit l'écriture aux écrans que la réponse ne réécrit pas : émis depuis le
	 * champ qui a écrit, il remonte jusqu'au document, et la case d'un jour de récolte s'y retrouve.
	 *
	 * La pastille part du champ qui écrit (`EntryUi::TOAST_FROM`), que la page ne départage pas sans
	 * `data-entry-sending` quand elle en porte plusieurs.
	 *
	 * Une lecture en cours passe devant : sa réponse, arrivée après l'écriture, reposerait sous un
	 * champ remis à zéro la liste et la consigne de la saisie qu'on vient d'enregistrer.
	 */
	static write(box) {
		UtilOmnibox.afterRead(box, () => EntryField.send(box));
	}

	static send(box) {

		const body = new URLSearchParams(EntryField.getBody(box));

		body.set('query', box.qs('.util-omnibox-input').value);

		EntryField.setContext(body);

		const anchor = EntryField.getScrollAnchor();

		box.dataset.entrySending = '';

		new Ajax.Query(box)
			.url('/entry/entry:doWrite')
			.body(body)
			.fetch()
			.then(json => {

				EntryField.restoreScroll(anchor);

				if(json.written === true) {
					EntryField.reset(box, json.date);
					box.dispatchEvent(new CustomEvent('entryWrite', {detail: json.event, bubbles: true}));
				}

			}, () => EntryField.lost(EntryField.getLostWrite(box), box))
			.finally(() => delete box.dataset.entrySending);

	}

	/*
	 * Le message d'une écriture sans réponse lisible, relevé sur l'enveloppe du champ (`setIdle()`).
	 */
	static getLostWrite(box) {
		return box.entryLostWrite;
	}

	/*
	 * « Annuler » sur la pastille d'une écriture (`EntryUi::getUndo()`). Il passe par ici plutôt que par
	 * `data-ajax` pour que son échec soit traité comme celui de l'écriture (`lost()`). La pastille,
	 * figée par le clic, part à la réponse, quelle qu'elle soit : la requête porte sur son lien, qui doit
	 * rester dans le document pour qu'un refus s'y affiche.
	 *
	 * La pastille est un enfant de `body` et survit à une navigation ajax, qui ne remplace que `main` :
	 * la semaine et la parcelle se lisent donc sur la page affichée au clic, comme à l'écriture, et non
	 * sur le lien (`setContext()`). L'adresse est celle de l'intention qui annule, que le lien porte
	 * (`IntentionUi::getCancelUrl()`).
	 */
	static cancel(link) {

		const body = new URLSearchParams();
		const toast = link.closest('.util-toast');

		link.post(body);

		EntryField.setContext(body);

		new Ajax.Query(link)
			.url(link.dataset.entryCancel)
			.body(body)
			.fetch()
			.then(
				json => {
					Lime.Alert.hideToast(toast, false);
					if(json.cancelled !== undefined) {
						document.dispatchEvent(new CustomEvent('entryCancel', {detail: json.cancelled}));
					}
				},
				() => {
					Lime.Alert.hideToast(toast, false);
					EntryField.lost(link.dataset.lost);
				}
			);

	}

	/*
	 * Une réponse illisible : une erreur levée par le serveur, qu'`Ajax.Query` rejette parce qu'elle
	 * n'est pas du JSON, ou le réseau coupé. Le cadriciel rend la vue après le COMMIT : la base a donc
	 * pu valider ce que l'écran n'a pas montré (cas vu le 2026-09-28, 90 kg écrits et la réponse en
	 * erreur). Revalider la même phrase l'écrirait deux fois.
	 *
	 * Le champ est vidé tout de suite, pour qu'Entrée ne puisse rien renvoyer même si le rechargement
	 * échoue à son tour. La page est ensuite rechargée, sans entrée d'historique, pour montrer ce
	 * que la base porte, et le bandeau d'erreur dit que la demande a pu passer. Une annulation n'a pas de
	 * champ à vider.
	 *
	 * Le plein écran du bas, ouvert par l'écriture ou derrière la pastille d'une annulation, couvrirait la page
	 * à vérifier : il se referme d'abord. Sa fermeture restaure le défilement de la page une fois l'historique
	 * dépilé (`UtilOmnibox.closeFullscreen()`), et le rechargement attend ce défilement pour ne pas être déplacé.
	 */
	static lost(message, box = null) {

		if(box !== null) {
			EntryField.reset(box);
		}

		const menu = qs('#entry-omnibox');
		const closed = (menu !== null) ? UtilOmnibox.closeFullscreen(menu) : Promise.resolve();

		closed.then(() => {
			new Ajax.Navigation(document.body)
				.url(location.href)
				.skipHistory()
				.fetch()
				.then(() => Lime.Alert.showStaticError(message.encode()));
		});

	}

	/*
	 * L'ancien planning en vue semaine dit sa semaine, sa parcelle (`EntryUi::getContext()`) et le membre
	 * de son onglet Temps de travail, l'accueil qu'il porte le champ et les blocs qu'il montre
	 * (`EntryUi::getHomeContext()`) : le serveur en rend ce qui change. Ailleurs, il ne rend que la
	 * pastille.
	 */
	static setContext(body) {

		const context = qs('#entry-context');

		if(context === null) {
			return;
		}

		['week', 'plot'].forEach(key => {
			if(context.dataset[key] !== undefined) {
				body.set(key, context.dataset[key]);
			}
		});

		// Les blocs se lisent sur la page au moment de poster : une réponse précédente a pu en retirer ou en
		// ajouter. Le préfixe de leur id vient du serveur, qui le pose.
		if(context.dataset.origin !== undefined) {

			const prefix = context.dataset.block;

			body.set('origin', context.dataset.origin);
			body.set('blocks', Array.from(document.querySelectorAll('[id^="'+ prefix +'"]'), node => node.id.substring(prefix.length)).join(','));

		}

		// Le membre dont l'onglet Temps de travail montre la semaine, quand l'onglet en propose plusieurs
		// (vide pour toute l'équipe) : le serveur ne le rend de nouveau que s'il s'agit de qui tape, seul
		// temps que la saisie touche. Sans choix de membre, l'onglet montre qui tape.
		if(qs('#planning-weekly-time .tab-item') !== null) {
			body.set('timeUser', qs('#planning-weekly-time .tab-item.selected')?.getAttribute('post-user') ?? '');
		}

	}

	/*
	 * Une ligne insérée au-dessus de ce qu'on regarde ne doit rien faire bouger. `overflow-anchor` s'en
	 * charge partout sauf dans Safari, qui ne le connaît pas : on y retient la première ligne visible
	 * avant la réponse, puis on compense son déplacement. Toutes les mesures sont prises avant
	 * `scrollBy()`, la seule écriture.
	 */
	static getScrollAnchor() {

		if(CSS.supports('overflow-anchor', 'auto')) {
			return null;
		}

		const node = [...qsa('#planning-weekly-container .tasks-planning-item')].find(node => node.getBoundingClientRect().top >= 0);

		if(node === undefined) {
			return null;
		}

		return {node, top: node.getBoundingClientRect().top, scrollY: window.scrollY};

	}

	static restoreScroll(anchor) {

		if(
			anchor === null ||
			anchor.node.isConnected === false ||
			window.scrollY !== anchor.scrollY
		) {
			return;
		}

		const shift = anchor.node.getBoundingClientRect().top - anchor.top;

		if(shift !== 0) {
			window.scrollBy(0, shift);
		}

	}

	/*
	 * Ce que la lecture rend pour l'écran.
	 */
	static load(box, entry) {

		box.entry = entry;

		// Une réponse que la lecture n'a pas retenue (l'espèce ou l'action tapée a changé) ne reste pas
		// affichée au-dessus d'une confirmation qui porte sur une autre culture ou une autre intervention.
		const dropped = UtilOmnibox.getTokens(box).filter(token => (
			(token.group === 'cultivation' && String(entry.cultivation) !== token.value) ||
			(token.group === 'task' && String(entry.task) !== token.value) ||
			(token.group === 'addCultivation' && String(entry.addCultivation) !== token.value) ||
			(token.group === 'addNoSeries' && String(entry.addNoSeries) !== token.value)
		));

		if(dropped.length > 0) {
			dropped.forEach(token => box.qs('.util-omnibox-token[data-group="'+ token.group +'"]').remove());
			EntryField.refresh(box);
			return;
		}

		// De même pour un jour ajouté par sa puce que l'écriture écarte, quand la phrase dit un jour d'une autre
		// semaine : il ne reviendrait pas en douce quand elle cesse de le dire (`IntentionUi::getDays()`).
		const daysDropped = (entry.days === null) ? [] : [...box.qsa('.util-omnibox-token[data-group="day"]')].filter(token => entry.days.includes(token.dataset.value) === false);

		if(daysDropped.length > 0) {
			daysDropped.forEach(token => token.remove());
			EntryField.refresh(box);
			return;
		}

		// De même pour le jeton du jour que le récapitulatif retenait, dès que la frappe dit un autre jour : le champ
		// ne montre que celui qui s'écrira, et la phrase relue sans lui ne le garde pas en douce. Le jeton d'une puce
		// porte le libellé pour valeur, celui de la lecture la date : les deux se comparent par le libellé.
		// Seul le récapitulatif lit les jetons : une lecture sans lignes n'en rend pas (`EntryUi::getEntry()`).
		if(entry.step === 'write' && EntryField.getBody(box).recap !== undefined) {

			const date = entry.tokens.find(token => token.group === 'date');
			const dateDropped = [...box.qsa('.util-omnibox-token[data-group="date"]')].filter(token => token.dataset.label !== date?.label);

			if(dateDropped.length > 0) {
				dateDropped.forEach(token => token.remove());
				EntryField.refresh(box);
				return;
			}

		}

		// Sans objet, la phrase n'est plus la vente que le récapitulatif montrait.
		if(['intention', 'object'].includes(entry.step)) {
			EntryField.closeRecap(box);
		}

		EntryField.setCompletion(box, entry.completion);

		box.qs('.util-omnibox-input').placeholder = entry.placeholder;

		// Le récapitulatif se lit en entier avant la seconde Entrée : sa liste prend toute la hauteur que le
		// champ lui laisse, au lieu du plafond d'une liste de suggestions. Les jetons d'une longue vente repoussent
		// son départ, aucune hauteur fixe ne la tient dans l'écran. « Créer la vente » vient après les articles et
		// peut rester sous le bas de la liste. Le plein écran du bas n'a rien à mesurer : sa liste prend déjà tout
		// ce que l'enveloppe laisse au-dessus du champ.
		if(entry.step === 'write' && EntryField.getBody(box).recap !== undefined) {

			if(UtilOmnibox.isFullscreenBottom(box) === false) {
				box.style.setProperty('--util-omnibox-list-height', 'calc('+ EntryField.getListRoom(box) +'px - var(--space-md))');
			}

			box.qs('.util-omnibox-item.is-cursor')?.scrollIntoView({block: 'nearest'});

		} else if(EntryField.getBody(box).recap !== undefined) {
			// Hors du récapitulatif, la hauteur de la liste du menu revient à `sizeList()`, qui la mesure à l'ouverture
			// et à chaque jeton : une lecture ne la retire donc qu'à un récapitulatif qui n'est plus à l'étape d'écriture.
			box.style.removeProperty('--util-omnibox-list-height');
		} else if(box.entryListTokens !== EntryField.getSignature(box)) {
			// Un jeton posé ou retiré liste fermée n'a pas pu se mesurer : la liste que la réponse rouvre se mesure
			// ici, une fois, et les lectures suivantes à jetons égaux ne mesurent plus rien.
			EntryField.sizeList(box);
		}

		// Une case tient sur une ligne, et la piste de ses jetons défile quand ils ne laissent plus de place au texte
		// (`util-omnibox-scroll`) : celui que la lecture vient de poser le repousse alors au-delà du bord, et c'est là
		// que la frappe reprend. Le champ du menu passe à la ligne et ne défile pas. Le texte indicatif de la lecture
		// change sans frappe : sous Firefox, la largeur de la case le suit par `size`.
		if(box.classList.contains('util-omnibox-scroll')) {

			UtilOmnibox.sizeInput(box.qs('.util-omnibox-input'));

			const track = box.qs('.util-omnibox-track');
			track.scrollLeft = track.scrollWidth;

		}

	}

	/*
	 * Dans la colonne du bureau, la liste du menu prend la hauteur que l'écran lui laisse sous le champ, au lieu du
	 * plafond de 20rem : une réponse qui nomme variété et calibre se lit alors sans défiler. Ce qui précède le champ
	 * dans la colonne le place plus ou moins haut, d'où la mesure. Pas de plancher : la colonne est fixe, et une
	 * liste plus haute que la place qu'elle a sortirait de l'écran sans qu'on puisse l'y ramener. Là où le champ est
	 * en bas de l'écran, il passe en plein écran dès qu'on y entre (`EntryUi::getField()`, `fullscreenBottom`) :
	 * la boîte quitte alors `#entry-menu` pour l'enveloppe, qui borne sa liste. Le récapitulatif d'une vente règle
	 * sa propre hauteur (`load()`). Seuls l'ouverture, un jeton posé ou retiré et le clavier d'une tablette changent
	 * la place de la liste : la mesure les suit, jamais la frappe.
	 */
	static sizeList(box) {

		if(
			box.closest('#entry-menu') === null ||
			EntryField.getBody(box).recap !== undefined
		) {
			return;
		}

		if(box.qs('.util-omnibox-list.is-open') === null) {
			return;
		}

		const height = EntryField.getListRoom(box);

		box.style.setProperty('--util-omnibox-list-height', 'calc('+ height +'px - var(--space-md))');
		box.entryListTokens = EntryField.getSignature(box);

	}

	// La place que l'écran laisse à la liste ouverte sous le champ. Le bas est celui de ce qu'on voit : le clavier
	// d'une tablette le remonte sans toujours changer `innerHeight`.
	static getListRoom(box) {

		const opened = box.qs('.util-omnibox-list').getBoundingClientRect();

		return window.visualViewport.offsetTop + window.visualViewport.height - opened.top;

	}

	/*
	 * Les jetons d'une intention qui lit des lignes (`EntryUi::getEntry()`) remplacent ceux du champ de leurs
	 * groupes, et la frappe des lignes complètes avec eux. Le jour se remplace toujours : la réponse ne le
	 * porte que s'il n'est pas aujourd'hui, si bien qu'un jour absent de la réponse est un « aujourd'hui »
	 * tapé contre le jour que `reset()` a reposé.
	 */
	static setTokens(box) {

		const groups = [...box.entry.tokens.map(token => token.group), 'date'];

		box.qsa('.util-omnibox-token', token => {
			if(groups.includes(token.dataset.group)) {
				token.remove();
			}
		});

		box.entry.tokens.forEach(token => UtilOmnibox.addToken(box, token.group, String(token.value), token.label, token.icon ?? undefined));

	}

}

// En capture, avant le délégué de `omnibox.js` qui retirerait aussi le jeton de l'intention.
document.addEventListener('click', function(e) {

	const clear = e.target.closest('.entry-field .util-omnibox-clear');

	if(clear === null) {
		return;
	}

	e.preventDefault();
	e.stopImmediatePropagation();

	const box = EntryField.getBox(clear);

	// Comme `UtilOmnibox.clear()` : effacer est une étape de la saisie, on reste dans le champ. Le focus passe donc
	// avant `reset()`, qui règle la liste que son `focusin` vient de rouvrir : fermée dans la colonne, remise au
	// départ dans le plein écran du bas.
	box.qs('.util-omnibox-input').focus();

	EntryField.reset(box);

}, true);

document.delegateEventListener('omniboxpick', '.entry-field', function(e) {

	// Un geste du « + » est un lien : l'omnibox le suit elle-même (`UtilOmnibox.pick()`).
	if(e.detail.item.href) {
		return;
	}

	e.preventDefault();

	EntryField.pick(this, e.detail.item);

});

// Retirer un jeton est une étape de la saisie, comme choisir une puce : on reste dans le champ, curseur au bout
// du texte, pour retaper aussitôt. L'appui sur un jeton ne retire donc pas le focus au champ, et le clic le rend
// au champ quand il était ailleurs. En capture, avant le délégué de `omnibox.js` qui retire le jeton : retiré, le
// jeton n'a plus de champ où rendre le focus.
document.delegateEventListener('mousedown', '.entry-field .util-omnibox-token', function(e) {
	e.preventDefault();
});

document.addEventListener('click', function(e) {

	const token = e.target.closest('.entry-field .util-omnibox-token');

	if(token === null) {
		return;
	}

	const input = EntryField.getBox(token).qs('.util-omnibox-input');

	input.focus();
	input.setSelectionRange(input.value.length, input.value.length);

}, true);

// Un jeton retiré (la croix, le retour arrière) change la phrase autant qu'un jeton posé. Les jetons
// posés ici passent par `refresh()`, qui tient la signature à jour : ils ne relancent rien.
document.delegateEventListener('omnibox', '.entry-field', function() {

	if(EntryField.getSignature(this) !== this.entryTokens) {
		EntryField.refresh(this);
	}

});

// Sur la fenêtre, en capture : avant tout délégué du document, dont celui de `omnibox.js` qui rend la liste au
// focus et emporte la boîte en plein écran, quel que soit l'ordre dans lequel les scripts se chargent.
window.addEventListener('focusin', function(e) {

	if(e.target.matches('.entry-field .util-omnibox-input')) {
		EntryField.setIdle(EntryField.getBox(e.target));
	}

}, true);

// Un clic dans un champ qui a déjà le focus ne déclenche pas de `focusin`, qui ouvre la liste : la liste fermée par
// la croix ou par Échap se rouvre au clic, comme au focus.
document.delegateEventListener('click', '.entry-field .util-omnibox-input', function() {

	const box = EntryField.getBox(this);

	if(box.qs('.util-omnibox-list.is-open') === null) {
		UtilOmnibox.render(box);
	}

});

// La liste se mesure ouverte, et `omnibox.js` l'ouvre au focus par un délégué du document : la fenêtre reçoit
// l'événement après tous ceux du document, quel que soit l'ordre dans lequel les scripts se chargent.
window.addEventListener('focusin', e => {

	if(e.target instanceof Element && e.target.matches('#entry-menu .util-omnibox-input')) {
		EntryField.sizeList(EntryField.getBox(e.target));
	}

});

// Le clavier d'une tablette réduit la place que l'écran laisse à une liste déjà ouverte.
window.visualViewport.addEventListener('resize', () => {

	const box = qs('#entry-menu .util-omnibox');

	if(box !== null) {
		EntryField.sizeList(box);
	}

});

document.delegateEventListener('input', '.entry-field .util-omnibox-input', function() {

	const box = EntryField.getBox(this);

	if(UtilOmnibox.isIdle(box)) {
		EntryField.restorePlaceholder(box);
	}

	EntryField.showCompletion(box);

});

document.delegateEventListener('keydown', '.entry-field .util-omnibox-input', function(e) {

	const box = EntryField.getBox(this);

	// Sans grisé, Tab et la flèche gardent leur geste.
	const accept = (
		((e.key === 'Tab' && e.shiftKey === false) || e.key === 'ArrowRight') &&
		EntryField.getVisibleCompletion(box) !== null
	);

	if(accept && EntryField.complete(box)) {
		e.preventDefault();
	}

});

// Échap rend une case du planning telle qu'elle s'est ouverte, vide : elle n'est qu'une cellule de la grille, où
// ce qui est tapé sans être écrit s'annule. Le champ du menu garde le geste de l'omnibox, qui ferme la liste.
document.delegateEventListener('keydown', '.entry-cell .util-omnibox-input', function(e) {

	if(e.key === 'Escape') {
		EntryField.reset(EntryField.getBox(this));
	}

});

// Le premier clic à côté d'une case ouverte avec sa liste ne fait que la fermer, comme Échap : la case couvre ses
// voisines, et ce clic visait à en sortir, non le total ou la case d'à côté (HN-B19). Tout s'écoute en capture sur
// `window`, avant les délégués du document, qui sont eux aussi en capture (`delegateEventListener()`, lime.js).
// La case se lit au `pointerdown` : le focus qui part au lien referme la liste avant le clic (délégué `focusout`
// de l'omnibox). Le focus ne part pas (`mousedown`), sans quoi une case voisine s'ouvrirait, et le clic qui suit
// s'arrête là, le lien n'étant pas suivi. Un toucher qui fait défiler s'annule (`pointercancel`) sans clic, et
// ne doit pas garder la case pour le clic d'après.
// Seul le bouton principal compte, et le clic du clavier (`detail` à 0) passe : un clic droit ne suit pas de
// `click`, et laisserait la case retenue pour la prochaine Entrée. Ctrl ou Cmd sur un lien ouvre un onglet sans
// quitter la case, et passe aussi, comme les clics ailleurs du planning (`PlanningHarvest.outside()`).
window.addEventListener('pointerdown', e => {

	EntryField.cellOutside = null;

	if(e.button !== 0 || ((e.ctrlKey || e.metaKey) && e.target.closest('a[href]') !== null)) {
		return;
	}

	const path = e.composedPath();

	qsa('.entry-cell .util-omnibox-list.is-open', list => {

		const box = list.closest('.util-omnibox');

		if(path.includes(box) === false) {
			EntryField.cellOutside = box;
		}

	});

}, true);

window.addEventListener('pointercancel', () => {
	EntryField.cellOutside = null;
}, true);

window.addEventListener('mousedown', e => {

	if(EntryField.cellOutside !== null) {
		e.preventDefault();
	}

}, true);

window.addEventListener('click', e => {

	const box = EntryField.cellOutside;

	EntryField.cellOutside = null;

	if(box === null || e.detail === 0 || e.composedPath().includes(box)) {
		return;
	}

	e.preventDefault();
	e.stopImmediatePropagation();

	// `reset()` ferme aussi la liste de la case.
	EntryField.reset(box);
	box.qs('.util-omnibox-input').blur();

}, true);

// Le grisé suit le curseur et le focus : il ne reste pas sous un champ qu'on a quitté. Le focus dit le champ
// qu'il quitte ou gagne, la sélection celui qui a le focus.
['focusin', 'focusout'].forEach(type => document.addEventListener(type, e => {

	const box = (e.target instanceof Element) ? EntryField.getBox(e.target) : null;

	if(box !== null) {
		EntryField.showCompletion(box);
	}

}));

// Le texte indicatif d'une lecture parle à qui est dans le champ. Quitté vide, après une écriture ou sans rien
// y avoir tapé, le champ reprend celui de la page : une case du planning se lit comme ses voisines.
document.delegateEventListener('focusout', '.entry-field', function(e) {

	if(
		this.contains(e.relatedTarget) === false &&
		this.qs('.util-omnibox-input').value === '' &&
		this.qs('.util-omnibox-token') === null
	) {
		EntryField.restorePlaceholder(this);
	}

});

document.addEventListener('selectionchange', () => {

	const box = (document.activeElement instanceof Element) ? EntryField.getBox(document.activeElement) : null;

	if(box !== null) {
		EntryField.showCompletion(box);
	}

});

document.delegateEventListener('omniboxload', '.entry-field', function(e) {
	EntryField.load(this, e.detail.json.entry);
});

document.delegateEventListener('click', '[data-entry-cancel]', function(e) {

	e.preventDefault();

	EntryField.cancel(this);

});
