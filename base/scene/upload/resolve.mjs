// Crochet de résolution de node pour les scripts du skill qui montent un modèle avec three et ses compléments. Les
// compléments de three importent three sous le nom de son fichier dans le site (`./three-0.170.0.module.min.js`), que
// l'archive range sous lib/three.mjs : le crochet y renvoie, pour que le complément et le script partagent le même three.
// Un script l'enregistre avant tout import de three, puis importe ce dont il a besoin :
//   register('./resolve.mjs', import.meta.url);
//   const THREE = await import('./lib/three.mjs');
const THREE = new URL('lib/three.mjs', import.meta.url).href;

export async function resolve(specifier, context, nextResolve) {
	if(specifier === 'three' || specifier.endsWith('/three-0.170.0.module.min.js')) {
		return { url: THREE, shortCircuit: true };
	}
	return nextResolve(specifier, context);
}
