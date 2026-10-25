// Le peuplier de arbre.js : un fuseau haut et étroit sur un tronc qui dépasse à peine.
import { build as buildTree } from './arbre.js';

export function build(THREE, helpers) {
	return buildTree(THREE, helpers, { essence: 'peuplier' });
}
