/**
 * L'habillage de la cour : ce que la page fait des événements de `GameBoard` (board.js) et des gestes sur ce qu'elle pose
 * par-dessus. Il ouvre la feuille d'une case touchée, remplie par `tile:get`, les feuilles du bandeau, déjà dans la
 * page, et la porte du jour ; il fait voler une récolte jusqu'au cellier, et dit quand la cour ne s'affiche pas.
 */
class GameDesk {

	static start(node) {

		const court = node.closest('#game-court');

		node.addEventListener('gameboard:tile', event => GameDesk.openTile(event.detail.id));
		node.addEventListener('gameboard:cellar', () => GameDesk.openSheet('cellar'));
		node.addEventListener('gameboard:avatar', () => GameDesk.openSheet('player'));
		node.addEventListener('gameboard:error', () => Lime.Alert.showStaticError(node.dataset.error));

		// Une feuille que la page ouvre d'elle-même (`?start`, `?show=`) tient la page comme celle qu'ouvre un geste
		GameDesk.lock();

		GameDesk.door(court);

		GameBoard.start(node, court.querySelector('.game-court-top'));

	}

	static openTile(id) {

		qs('#game-tile-sheet', sheet => {

			// La feuille d'une case touchée avant ne doit pas paraître le temps de la réponse
			sheet.qs('.util-sheet-head h4').replaceChildren();
			sheet.qs('.util-sheet-body').replaceChildren();
			sheet.hidden = false;

			// La feuille défile seule : la page derrière elle ne suit pas le doigt
			document.body.classList.add('game-tile-sheet-open');

			new Ajax.Query(sheet)
				.url('/game/tile:get?id='+ encodeURIComponent(id))
				.method('get')
				.fetch();

		});

	}

	static closeTile() {

		qs('#game-tile-sheet', sheet => sheet.hidden = true);
		document.body.classList.remove('game-tile-sheet-open');
		GameBoard.clear();

	}

	// Une feuille du bandeau ou du cellier ; la vue d'un ami n'en a aucune. Elle prend la place de la case touchée, dont
	// la feuille ne s'ouvrira plus à l'arrivée du joueur
	static openSheet(name) {

		qs('#game-sheet-'+ name, sheet => {

			sheet.hidden = false;
			GameDesk.lock();
			GameBoard.clear();

		});

	}

	static closeSheet(name) {

		qs('#game-sheet-'+ name, sheet => {

			sheet.hidden = true;
			GameDesk.lock();

		});

	}

	// La page derrière une feuille ouverte ne défile pas
	static lock() {
		document.body.classList.toggle('game-sheet-open', document.querySelector('.game-sheet:not([hidden])') !== null);
	}

	/**
	 * La porte du jour, à la première visite de la journée dans ce navigateur. Seule la date y est gardée ; sans stockage
	 * possible, la porte ne s'ouvre pas, plutôt que de revenir à chaque page.
	 */
	static door(court) {

		const door = court.qs('[data-game-door]');

		if(door === null) {
			return;
		}

		try {

			if(localStorage.getItem('game-door') === door.dataset.gameDoor) {
				return;
			}

			localStorage.setItem('game-door', door.dataset.gameDoor);

		} catch {
			return;
		}

		door.hidden = false;

	}

	static closeDoor(door) {

		// Le battant tourne aussi : seule la fin du fondu de la porte la retire
		door.addEventListener('animationend', event => {
			if(event.target === door) {
				door.hidden = true;
			}
		});

		door.classList.add('game-door-leaving');

	}

	/**
	 * La récolte vole de la case jusqu'à son légume dans le cellier, et les rennes qu'elle attire montent du bandeau. Le
	 * mouvement est celui de la feuille de style (`game-fly`, `game-float`), qui le réduit à rien sous `prefers-reduced-motion`.
	 */
	static harvested(id, growing, gain) {

		// La marque du cellier vient d'être reposée par `GameBoard.apply()`, et la scène ne la place qu'à sa prochaine
		// image, déjà demandée : on part après elle
		requestAnimationFrame(() => GameDesk.fly(id, growing, gain));

	}

	static fly(id, growing, gain) {

		const court = document.querySelector('#game-court');
		const from = GameBoard.locate(id);
		const target = court.querySelector('.game-cellar [data-game-stock="'+ growing +'"]');

		// Toutes les mesures avant le premier nœud posé, qui forcerait sinon une mise en page de plus
		const to = (target === null) ? null : target.getBoundingClientRect();
		const box = court.querySelector('#game-points').getBoundingClientRect();

		if(from !== null && to !== null) {

			const vignette = target.firstElementChild;

			for(let index = 0; index < 6; index++) {

				const node = vignette.cloneNode(true);
				node.classList.add('game-fly');
				node.style.left = from.x +'px';
				node.style.top = from.y +'px';
				node.style.setProperty('--game-fly-x', (to.left + to.width / 2 - from.x) +'px');
				node.style.setProperty('--game-fly-y', (to.top + to.height / 2 - from.y) +'px');
				node.style.setProperty('--game-fly-spread', ((index - 2.5) * 14) +'px');
				node.addEventListener('animationend', () => node.remove(), { once: true });

				court.append(node);

			}

		}

		if(gain > 0) {

			const node = document.createElement('span');
			node.className = 'game-float';
			node.textContent = '+'+ gain +' 🦌';
			node.style.left = (box.left + box.width / 2) +'px';
			node.style.top = box.bottom +'px';
			node.addEventListener('animationend', () => node.remove(), { once: true });

			court.append(node);

		}

	}

}

// Les feuilles et la porte sont posées par la page : leurs gestes s'écoutent une fois, sur le document
document.addEventListener('click', event => {

	const open = event.target.closest('[data-game-open]');

	if(open !== null) {
		GameDesk.openSheet(open.dataset.gameOpen);
	}

	const close = event.target.closest('[data-game-close]');

	if(close !== null) {
		close.closest('.game-sheet').hidden = true;
		GameDesk.lock();
	}

	const door = event.target.closest('[data-game-door-close]');

	if(door !== null) {
		GameDesk.closeDoor(door.closest('[data-game-door]'));
	}

	const gesture = event.target.closest('[data-game-gesture]');

	if(gesture !== null) {
		GameBoard.react([gesture.dataset.gameGesture]);
	}

});

// Une navigation qui quitte la page feuille ouverte ne doit pas laisser la suivante sans défilement
document.addEventListener('navigation.wakeup', () => {
	document.body.classList.remove('game-tile-sheet-open');
	GameDesk.lock();
});
