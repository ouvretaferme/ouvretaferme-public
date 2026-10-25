// Le paysan de paysan.js, houe en main : le geste du désherbage, porté par le bras gauche.
import { build as buildFarmer } from './paysan.js';

export function build(THREE, helpers) {
	return buildFarmer(THREE, helpers, { outil: 'houe' });
}
