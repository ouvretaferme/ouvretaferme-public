/**
 * Le bandeau des fermes cochées sur l'onglet Fermes d'un groupe.
 *
 * `batch.js` cache ou montre une action selon le **contenu** de la sélection (`data-batch-test`),
 * et c'est lui qui refuse « Cultures » à une ferme qui ne partage pas son plan de culture. Trois
 * choses lui échappent, et se font ici, dans le callback de `Batch.changeSelection()` comme sur les
 * écrans voisins (`catalog/static/js/offer.js`, `bank/static/js/cashflow.js`) :
 * - ce qui dépend du **nombre** de cases : « Face au réseau » à une ferme, « Ferme à ferme » à
 *   deux, et au-delà les deux actions grisées ;
 * - le grisé lui-même, qui est `disabled` et non `batch-mute`, plus pâle ;
 * - l'adresse de chaque entrée, qui porte les fermes cochées.
 *
 * ⚠️ Le callback tourne **après** les tests de `batch.js`, qui retirent `hide` de toute entrée dont
 * le test ne demande pas à la cacher : l'entrée d'une cible qui ne vaut pas pour ce nombre de cases
 * se recache donc ici à chaque fois, sinon les deux cibles s'afficheraient ensemble. À l'inverse, un
 * sous-titre ne porte aucun test : `batch.js` ne le réaffiche jamais, et c'est ici qu'il revient.
 *
 * ⚠️ L'adresse se **reconstruit** depuis `data-url`, jamais en complétant `href` : le callback tourne
 * à chaque case, et un `href` complété en place accumulerait les fermes d'un clic à l'autre.
 *
 * ⚠️ La liste du menu n'est pas cherchée sous le bandeau : `Lime.Dropdown` la déplace dans `body`
 * tant qu'elle est ouverte. Ses entrées se trouvent par leurs attributs, dans tout le document.
 */
class StructureCommunityFarmsAi {

	static changeSelection() {

		qsa('[data-batch="#batch-farm"] .util-deck-aside [name="batch[]"]', field => field.firstParent('.util-deck').classList.toggle('util-deck-selected', field.checked));

		return Batch.changeSelection('#batch-farm', null, (selection) => {

			const count = selection.length;

			const compare = qs('#batch-farm [data-compare]');

			// Un menu resté ouvert flotterait au-dessus d'une action qui ne vaut peut-être plus.
			if(Lime.Dropdown.isOpen(compare)) {
				Lime.Dropdown.close(compare);
			}

			compare.classList.toggle('disabled', count > 2);

			qsa('[data-compare-target]', node => {
				if(parseInt(node.dataset.compareTarget) !== count) {
					node.classList.add('hide');
				} else if(node.dataset.batchTest === undefined) {
					node.classList.remove('hide');
				}
			});

			const farms = Array.from(selection).map(field => field.value);

			qsa('[data-compare-target][data-url]', node => {

				const query = '?farm='+ farms[0] + (count === 2 ? '&against='+ farms[1] : '');

				node.setAttribute('href', node.dataset.url + query);

			});

			qsa('[data-compare-names]', node => {
				node.textContent = Array.from(selection).map(field => field.dataset.name).join(' '+ node.dataset.and +' ');
			});

			const duplicate = qs('#batch-farm [data-duplicate]');

			if(duplicate !== null) {

				const accepted = (count === 1 && selection[0].dataset.batch.split(' ').includes('accept-duplicate'));

				duplicate.classList.toggle('disabled', accepted === false);

				if(accepted) {
					duplicate.setAttribute('href', duplicate.dataset.url + farms[0]);
				} else {
					duplicate.removeAttribute('href');
				}

			}

		});

	}

	static hideSelection() {

		Batch.hideSelection('#batch-farm');

		this.changeSelection();

	}

}
