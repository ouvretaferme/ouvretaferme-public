// Crochets de résolution de node pour la cuisson. Les modules de model/ sont écrits pour le navigateur : ils importent three par un
// spécificateur nu, que la page résout par une importmap, et portent l'extension .js sans package.json qui les déclare en modules.
// Node n'a ni l'un ni l'autre, d'où ces deux crochets plutôt qu'une retouche des modules.
const LIB = new URL('../static/lib/', import.meta.url);
const ROOT = new URL('../', import.meta.url).href;
// Le dossier des modules que la cuisson lit, quand c'est celui d'un autre package (bake.mjs)
let model = ROOT;

// Les outils de node qui empruntent ces crochets pour lire three les enregistrent sans rien passer : ils gardent ce package
export function initialize(data) {
	if(data?.model !== undefined) {
		model = data.model;
	}
}

export async function resolve(specifier, context, nextResolve) {
	if(specifier === 'three') {
		return { url: new URL('three-0.170.0.module.min.js', LIB).href, shortCircuit: true };
	}
	if(specifier === 'three/addons/utils/BufferGeometryUtils.js') {
		return { url: new URL('three-0.170.0.BufferGeometryUtils.js', LIB).href, shortCircuit: true };
	}
	return nextResolve(specifier, context);
}

export async function load(url, context, nextLoad) {
	if((url.startsWith(ROOT) || url.startsWith(model)) && url.endsWith('.js')) {
		return nextLoad(url, { ...context, format: 'module' });
	}
	return nextLoad(url, context);
}
