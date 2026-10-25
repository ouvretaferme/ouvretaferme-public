// Le fruitier de fruitier.js, chargé de fruits dorés : poirier, prunier ou abricotier vu de loin.
import { build as buildTree } from './fruitier.js';

export function build(THREE, helpers) {
	return buildTree(THREE, helpers, { fruit: 'or' });
}
