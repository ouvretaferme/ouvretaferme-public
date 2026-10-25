// Le paysan de paysan.js, mains libres, en casquette et chemise jaune : il porte la caisse ou pousse la brouette sous une
// autre silhouette que le chapeau de paille.
import { build as buildFarmer } from './paysan.js';

export function build(THREE, helpers) {
	return buildFarmer(THREE, helpers, { coiffe: 'casquette', tenue: 'or', salopette: 'ardoise', peau: 'peauSombre', couleurCoiffe: 'rouge' });
}
