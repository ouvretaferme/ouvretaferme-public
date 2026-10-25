/*
 * Les cartes d'un `.util-piles` en piles de même largeur, remplies dans l'ordre des cartes en posant
 * chacune sur la pile la moins haute : autant de piles que la largeur en contient à `data-pile-width`
 * rem au moins (20 par défaut).
 *
 * Toutes les hauteurs se lisent avant la moindre écriture, sinon chaque lecture recalculerait la mise en
 * page entière. Il y a deux passes, la seconde sur les hauteurs que les cartes prennent dans leur pile
 * (`stack()`), et chacune lit tout avant d'écrire.
 *
 * L'empilement déplace les cartes dans les piles, donc l'ordre du document ne dit plus l'ordre voulu dès
 * le second passage (redimensionnement) : le premier note le rang de chaque carte, les suivants le
 * relisent. Un écran qui remplace une carte seule par une réponse ajax doit donc écrire ce rang lui-même
 * (`data-util-piles-rank`) ; un écran qui remplace tout le `.util-piles` n'a rien à faire.
 *
 * Une carte qui porte `data-util-piles-uncounted` se range à son rang comme les autres, mais sa hauteur ne
 * compte pas dans celle de sa pile : posée en tête, elle ne repousse pas vers la droite les cartes qui la
 * suivent (le pense-bête de la semaine du planning).
 *
 * `--util-piles-max`, posée en CSS sur le `.util-piles`, plafonne le nombre de piles : au-delà, les piles
 * s'élargissent au lieu de se multiplier. C'est une propriété CSS et non un attribut pour qu'une media query
 * la règle, alors que `--util-piles` est la sortie que le script écrit.
 */
class MainPiles {

	static PILE_WIDTH = 20;

	/*
	 * `force` recompose des piles dont le nombre n'a pas changé : un écran qui masque des cartes sans les
	 * retirer (une recherche) déséquilibre les piles sans changer leur nombre.
	 */
	static stack(board, force = false) {

		if(board.isConnected === false) {
			return;
		}

		const blocks = Array.from(board.qsa(':scope > :not(.util-piles-pile), :scope > .util-piles-pile > *'));

		blocks.forEach((block, index) => {
			block.dataset.utilPilesRank ??= index;
		});

		blocks.sort((block1, block2) => parseInt(block1.dataset.utilPilesRank) - parseInt(block2.dataset.utilPilesRank));

		const style = getComputedStyle(board);
		const rem = parseFloat(getComputedStyle(document.documentElement).fontSize);
		const gap = parseFloat(style.rowGap) || 0;
		const pilesMax = parseInt(style.getPropertyValue('--util-piles-max'), 10);
		const width = board.getBoundingClientRect().width;
		const heights = blocks.map(block => block.getBoundingClientRect().height);
		const pileWidth = parseFloat(board.dataset.pileWidth ?? this.PILE_WIDTH);

		const pilesFit = Math.max(1, Math.floor((width + gap) / (pileWidth * rem + gap)));
		const piles = (pilesMax > 0) ? Math.min(pilesFit, pilesMax) : pilesFit;

		if(force === false && parseInt(board.dataset.piles ?? '0') === piles) {
			return;
		}

		const assignment = this.distribute(blocks, heights, piles, gap);

		this.place(board, blocks, assignment, piles);

		board.style.setProperty('--util-piles', piles);
		board.dataset.piles = piles;

		// Les hauteurs lues plus haut sont celles des cartes à leur largeur d'avant, celle de la grille ou
		// d'un autre nombre de piles, et une liste qui revient à la ligne dans sa pile s'y allonge. Une
		// seconde répartition se fait donc sur les hauteurs lues dans les piles, une seule fois, et
		// seulement si elle change quelque chose : à largeur de pile égale, la hauteur d'une carte ne
		// dépend plus de sa pile. Une pile seule n'a rien à répartir.
		if(piles > 1) {

			const heightsPiled = blocks.map(block => block.getBoundingClientRect().height);
			const assignmentPiled = this.distribute(blocks, heightsPiled, piles, gap);

			if(assignmentPiled.some((pile, index) => pile !== assignment[index])) {
				this.place(board, blocks, assignmentPiled, piles);
			}

		}

	}

	/*
	 * La pile de chaque carte, dans l'ordre des cartes : chacune va sur la pile la moins haute.
	 */
	static distribute(blocks, heights, piles, gap) {

		const sums = new Array(piles).fill(0);

		return blocks.map((block, index) => {

			const pile = sums.indexOf(Math.min(...sums));

			// Une carte masquée ne mesure rien et ne doit pas compter l'écart qui la suivrait.
			if(heights[index] > 0 && block.dataset.utilPilesUncounted === undefined) {
				sums[pile] += heights[index] + gap;
			}

			return pile;

		});

	}

	/*
	 * Les cartes dans des piles neuves, selon `assignment`. Les anciennes piles se retirent vides, une fois
	 * leurs cartes passées dans les nouvelles.
	 */
	static place(board, blocks, assignment, piles) {

		const content = Array.from({length: piles}, () => []);

		blocks.forEach((block, index) => content[assignment[index]].push(block));

		const pilesOld = Array.from(board.qsa(':scope > .util-piles-pile'));

		content.forEach(pileBlocks => {

			const pile = document.createElement('div');
			pile.classList.add('util-piles-pile');
			pile.append(...pileBlocks);

			board.append(pile);

		});

		pilesOld.forEach(pile => pile.remove());

	}

	static restack() {
		qsa('.util-piles', board => this.stack(board));
	}

}

window.addEventListener('resize', () => MainPiles.restack());
