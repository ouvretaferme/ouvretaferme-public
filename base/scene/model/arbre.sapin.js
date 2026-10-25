// Le sapin de arbre.js : trois jupes coniques dentelées qui se recouvrent, deux fois plus haut que large.
import { build as buildTree } from './arbre.js';

export function build(THREE, helpers) {
	return buildTree(THREE, helpers, { essence: 'sapin' });
}
