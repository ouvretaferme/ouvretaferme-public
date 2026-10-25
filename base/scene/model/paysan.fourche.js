// Le paysan de paysan.js, fourche en main, en casquette et chemise rouge : une seconde silhouette, pour que deux paysans
// côte à côte ne se lisent pas comme le même dupliqué.
import { build as buildFarmer } from './paysan.js';

export function build(THREE, helpers) {
	return buildFarmer(THREE, helpers, { outil: 'fourche', coiffe: 'casquette', tenue: 'rouge', salopette: 'boisSombre', peau: 'peauSombre', couleurCoiffe: 'bleu' });
}
