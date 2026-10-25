// Le fruitier de fruitier.js, chargé de fruits rouges : pommier ou cerisier vu de loin.
import { build as buildTree } from './fruitier.js';

export function build(THREE, helpers) {
	return buildTree(THREE, helpers, { fruit: 'rouge' });
}
