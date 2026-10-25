// Résolution d'une culture vers une espèce du catalogue (model/species.json et model/alias.json, que la cuisson recopie dans
// l'index servi). Module ES sans import : le catalogue est passé en argument, la page le lit comme elle l'entend.
// Le catalogue cite des modèles, il ne dit pas qu'ils existent : test/CatalogSentinel.t.php tient chaque citation.

export function sceneCreateSpecies({ species, alias }) {

	// Un nom de plante est saisi à la main : « AMMI » et « Ammi » sont la même, la casse et les espaces de bord ne comptent pas
	const nameKey = name => name.trim().toLowerCase();

	const bySlug = new Map(species.map(entry => [entry.slug, entry]));

	// Un alias qui porte ses propres couleurs (color, leaf, accent) devient une espèce dérivée : même module, même emprise, et
	// les pièces du module peintes à la couleur de l'espèce empruntée repeintes à la sienne (crop.js). Son slug garde celui de
	// la cible devant le tilde, ce qui laisse lire l'espèce d'origine sans table de plus
	const derived = [];
	for(const [name, [slug, , tints]] of Object.entries(alias)) {
		const base = tints ? bySlug.get(slug) : undefined;
		if(base) {
			derived.push({
				...base,
				...tints,
				plant: name,
				slug: `${slug}~${name.toLowerCase().normalize('NFD').replace(/[^a-z]+/g, '-')}`,
				module: base.module ?? base.slug,
				base
			});
		}
	}

	const list = [...species, ...derived];

	// Le fqn d'une plante égale le slug de son entrée, sauf pour l'entrée qui déclare le sien. Une espèce dérivée n'en a pas :
	// elle vient d'un nom saisi, et hériterait sinon du fqn de l'espèce qu'elle emprunte
	const byFqn = new Map(species.map(entry => [entry.fqn ?? entry.slug, entry]));

	const byName = new Map();
	for(const entry of list) {
		const key = nameKey(entry.plant);
		// Le premier nom l'emporte : une entrée du catalogue passe avant l'espèce dérivée d'un alias du même nom
		if(!byName.has(key)) {
			byName.set(key, entry);
		}
	}

	const aliasByName = new Map(Object.entries(alias).map(([name, [slug]]) => [nameKey(name), slug]));

	return {

		list,

		// Le fqn d'abord, que personne ne saisit, puis le nom. Une espèce inconnue ou un alias vers rien rendent null, jamais
		// une forme de remplacement : la scène décide de ce qu'elle montre d'une culture sans modèle
		get(fqn, name) {

			const key = nameKey(name);

			return byFqn.get(fqn) ?? byName.get(key) ?? bySlug.get(aliasByName.get(key)) ?? null;

		},

		// Arbres et arbustes pérennes, déjà formés : ils ne lèvent pas et peuvent perdre leurs feuilles. Le fraisier, rangé avec
		// les petits fruits, n'en est pas, ni une espèce dérivée de lui
		woody: entry => entry.family === 'tree' || (entry.family === 'berry' && (entry.module ?? entry.slug.split('~')[0]) !== 'fraise')

	};

}
