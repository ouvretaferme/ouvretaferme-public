/**
 * Les emplacements du groupe Bureau, hors des écrans qui les lisent : « Du nouveau », puis la météo
 * (L52). Chacun se demande une fois, au premier passage sur l'univers, et la réponse le
 * remplace par ce qu'il porte ou le retire. La navigation ajax ne réécrit pas le groupe : la réponse
 * vaut jusqu'au rechargement.
 *
 * Le passage se lit sur le `<body>`, où `Structure.setSection()` pose l'univers ouvert, et non sur un
 * geste : survol, clic, flèches du clavier et feuille du téléphone y passent tous.
 */
class DashboardPilotage {

	static observer = null;
	static tablet = null;

	static watch() {

		if(this.observer === null) {
			this.observer = new MutationObserver(() => this.load());
			this.observer.observe(document.body, {attributes: true, attributeFilter: ['data-section', 'data-template']});
		}

		// Le rendu arrive sur un univers déjà posé, la feuille du téléphone ouverte sur Bureau par
		// exemple : aucun changement ne viendrait demander les nouvelles.
		this.load();

	}

	static load() {

		// Un écran du groupe Bureau autre que l'accueil, celui des notes, ouvre le groupe par le jeton de son
		// gabarit, sans univers posé sur le `<body>` : le groupe y est visible d'emblée.
		const section = document.body.dataset.section;
		const home = (section === undefined && document.body.dataset.template.split(' ').includes('structure-home'));

		if(section !== 'home' && home === false) {
			return;
		}

		// Chaque emplacement appelle `watch()` à son rendu, et l'univers change plusieurs fois avant la
		// réponse : la marque garde un emplacement demandé jusqu'à ce que la réponse le remplace, et le
		// rend à la demande suivante si elle échoue. Ce qui a remplacé un emplacement ne porte plus
		// d'adresse, et n'a donc rien à redemander.
		qsa('#structure-pilotage > [data-url]', node => {

			if(node.dataset.loaded !== undefined) {
				return;
			}

			node.dataset.loaded = '0';

			// Le verrou d'`Ajax.Query` se nomme d'après l'instant où il se pose (`getLockId()`) : les deux
			// emplacements, demandés dans la même milliseconde, se le disputeraient sans leur nom.
			new Ajax.Query(node)
				.lockAs('pilotage-'+ node.id)
				.method('GET')
				.url(node.dataset.url)
				.fetch()
				.then(() => node.dataset.loaded = '1', () => delete node.dataset.loaded);

		});

	}

	/*
	 * À la tablette, le groupe Bureau n'a pas de panneau : la météo passe sous la date de l'accueil,
	 * dans la place que la tête lui garde (`DashboardUi::getHead()`). Le nœud se déplace et ne se
	 * recopie pas : les identifiants que visent les routes restent uniques.
	 *
	 * La requête est celle du palier tablette du gabarit (`Structure.TABLET`), que le CSS ne peut
	 * pas dire à la place du script : il montre un nœud, il ne le change pas de parent.
	 */
	static place() {

		if(this.tablet === null) {
			this.tablet = matchMedia(Structure.TABLET);
			this.tablet.addEventListener('change', () => this.place());
		}

		const place = qs('#dashboard-home');

		if(place === null) {
			return;
		}

		// La place et le nœud du groupe peuvent s'écrire dans la même réponse, l'écran avant l'en-tête :
		// chacun appelle donc ce placement à son rendu. Le menu d'une autre structure, que l'en-tête va
		// remplacer, n'a rien à céder à la place.
		const current = (qs('#structure-nav-sections[data-structure="'+ place.dataset.structure +'"]') !== null);
		const home = (current ? qs('#structure-pilotage > #structure-pilotage-home') : null) ?? qs('#dashboard-home > #structure-pilotage-home');

		if(home === null) {
			return;
		}

		const parent = this.tablet.matches ? place : qs('#structure-pilotage');

		if(home.parentElement === parent) {
			return;
		}

		parent.append(home);

	}

	/*
	 * La place disparaît avec l'accueil, et le nœud avec elle s'il y était : il retourne au groupe avant
	 * que l'écran suivant ne remplace la page.
	 */
	static restore() {
		qs('#dashboard-home > #structure-pilotage-home', home => qs('#structure-pilotage').append(home));
	}

}

document.addEventListener('navigation.sleep', () => DashboardPilotage.restore());
