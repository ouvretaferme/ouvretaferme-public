<?php
namespace dev;

/**
 * Règles de la section « CSS et maquettes » du CLAUDE.md, et aria du « Style PHP ».
 *
 * Le mouvement se lit par `MotionLib::getDeclarations()`, l'extraction même de `dev/motion` : le
 * cliquet compte ce que compte l'inventaire, un segment par ligne. Les durées écrites en JS ou en
 * PHP (`setTimeout` qui attend la fin d'une animation, `style.transition`) ne sont pas tenues ici,
 * `MotionLib::parseScript()` les repère par motif sans pouvoir les lire toutes : elles se listent par
 * `dev/motion scope=script`.
 *
 * Le châssis du design system se lit sur ses deux feuilles, component.css et reboot.css : une feuille
 * de package ne reprend pas ce qu'elles posent sur un élément (`findChassis()`), ni un fond de
 * `util-table-row`, ni la marge ou l'espacement d'une classe util-*. Les tailles de texte sous
 * 0,9 rem et les valeurs nues de `z-index`, `max-width` et `grid-template-columns` se comptent dans
 * toutes les feuilles, celles du design system comprises. Ce qui ne se voit pas ici : une valeur
 * passée par une propriété personnalisée locale (`--largeur: 40rem` puis `var(--largeur)`), un
 * style posé dans un attribut `style` (`InlineStyleSentinelAi`), une surcharge écrite en JS, et les
 * raccourcis `grid` et `grid-template`, qui portent aussi des colonnes.
 *
 * Lancement : dev/test package=dev file=CssSentinel
 */
class CssSentinelTest extends SentinelTest {

	const BREAKPOINT = [
		'accounting/articles/static/css/articles.css' => 4,
		'accounting/financialYear/static/css/financialYear.css' => 2,
		'accounting/overview/static/css/analyze.css' => 1,
		'base/structure/static/css/structure.css' => 1,
		'commercialisation/catalog/static/css/offer.css' => 6,
		'commercialisation/selling/static/css/item.css' => 2,
		'commercialisation/selling/static/css/market.css' => 10,
		'commercialisation/selling/static/css/merchant.css' => 3,
		'commercialisation/shop/static/css/design-bucolique.css' => 2,
		'commercialisation/shop/static/css/menu.css' => 3,
		'commercialisation/shop/static/css/shop.css' => 3,
		'framework/editor/static/css/editor.css' => 2,
		'framework/util/static/css/batch.css' => 1,
		'framework/util/static/css/form.css' => 1,
		'ouvretaferme/game/static/css/game.css' => 2,
		'ouvretaferme/main/static/css/chart.css' => 1,
		// Dont le titre de la tête à 2rem dès 1400 px, largeur demandée par Vincent
		'ouvretaferme/main/static/css/component.css' => 5,
		// Dont la taille de la racine, qui suit ses propres paliers (`html { font-size }`)
		'ouvretaferme/main/static/css/reboot.css' => 15,
		'ouvretaferme/main/static/css/root.css' => 5,
		'ouvretaferme/main/static/css/template-doc.css' => 1,
		// Dont le plafond du gabarit à 2400 px, palier de la taille de la racine (`reboot.css`), et le
		// rail à 1100 px, palier du gabarit que le menu sombre reprend tel quel
		'ouvretaferme/main/static/css/template-structure.css' => 16,
		'ouvretaferme/main/static/css/template-website.css' => 2,
		'ouvretaferme/website/static/css/design-classique.css' => 1,
		'ouvretaferme/website/static/css/design-simple.css' => 1,
		'production/map/static/css/bed.css' => 1,
		'production/map/static/css/zone.css' => 3,
		'production/plant/static/css/forecast.css' => 1,
		'production/series/static/css/place.css' => 3,
		'production/series/static/css/planning.css' => 5,
		'production/series/static/css/series.css' => 1,
		'production/series/static/css/task.css' => 3,
		'production/series/static/css/timesheet.css' => 1,
	];

	/**
	 * Les `:has()` posés sur la racine du document dont l'argument descend plus bas que ses enfants.
	 * Sous Firefox, un tel sélecteur se réévalue sur tout le document à chaque changement de ce que
	 * l'argument lit, où que ce soit dans la page : `body:has(#series-selector:not(.hide))` coûtait
	 * 6 ms à chaque bascule de `hide`, et 3,1 s pour les 502 planches d'un assolement (mesuré le
	 * 2026-09-04). Le même argument en enfant direct, `body:has(> #series-selector:not(.hide))`, ne
	 * coûte rien, d'où son exemption (banc Firefox 151 du 2026-10-09, 500 bascules sur 30 000 nœuds :
	 * 299 à 587 ms en descendant, 0 à 3 ms en enfant direct). Un état global se porte par une classe
	 * que le code pose sur `body`, comme `body.series-selector-shown`.
	 */
	const HAS_ON_ROOT = [
		'accounting/articles/static/css/articles.css' => 1,
		'commercialisation/shop/static/css/customizeAi.css' => 1,
		'commercialisation/shop/static/css/design-bucolique.css' => 6,
		'ouvretaferme/game/static/css/game.css' => 1,
		'ouvretaferme/main/static/css/customize.css' => 4,
		'ouvretaferme/main/static/css/template-doc.css' => 1,
		'ouvretaferme/main/static/css/template-structure.css' => 4,
		'ouvretaferme/website/static/css/customizeAi.css' => 1,
	];

	const TOKENS = 'ouvretaferme/main/static/css/juicinessAi.css';

	const MOTION = [
		'accounting/articles/static/css/articles.css' => 2,
		'accounting/bank/static/css/import.css' => 2,
		'accounting/journal/static/css/operation.css' => 1,
		'accounting/preaccounting/static/css/preaccounting.css' => 1,
		'accounting/vat/static/css/timeline.css' => 2,
		'base/media/static/css/upload.css' => 2,
		'base/structure/static/css/apiKey.css' => 2,
		'base/structure/static/css/production.css' => 2,
		'commercialisation/payment/static/css/stripe.css' => 1,
		'commercialisation/payment/static/css/wallet.css' => 1,
		'base/product/static/css/category.css' => 1,
		'commercialisation/catalog/static/css/offer.css' => 1,
		'base/product/static/css/stock.css' => 1,
		'commercialisation/quotation/static/css/panelAi.css' => 1,
		'commercialisation/quotation/static/css/quotation.css' => 1,
		'commercialisation/selling/static/css/market.css' => 4,
		'commercialisation/selling/static/css/merchant.css' => 1,
		'commercialisation/selling/static/css/order.css' => 1,
		'commercialisation/selling/static/css/sale.css' => 1,
		'commercialisation/shop/static/css/basket.css' => 1,
		'commercialisation/shop/static/css/manage.css' => 1,
		'commercialisation/shop/static/css/menu.css' => 7,
		'framework/editor/static/css/editor.css' => 12,
		'framework/util/static/css/form.css' => 10,
		'framework/util/static/css/lime.css' => 1,
		'ouvretaferme/association/static/css/association.css' => 1,
		// Le bouton « Simuler le mouvement réduit » pose hors du bloc prefers-reduced-motion la durée qu'il simule
		'ouvretaferme/design/static/css/designAi.css' => 1,
		// Dont le 0.9s de util-celebrate, seule durée hors de l'échelle que le design system déclare
		'ouvretaferme/main/static/css/component.css' => 29,
		'ouvretaferme/main/static/css/onboarding.css' => 4,
		'ouvretaferme/main/static/css/reboot.css' => 4,
		'ouvretaferme/main/static/css/root.css' => 1,
		'ouvretaferme/main/static/css/template-base.css' => 2,
		'ouvretaferme/main/static/css/template-structure.css' => 4,
		'ouvretaferme/presentation/static/css/feature.css' => 1,
		'production/gallery/static/css/photo.css' => 1,
		'production/map/static/css/bed.css' => 1,
		'production/map/static/css/plan.css' => 1,
		'production/planning/static/css/planning.css' => 2,
		'production/plant/static/css/plant.css' => 1,
		'production/plant/static/css/varietyRating.css' => 4,
		'production/sequence/static/css/flow.css' => 3,
		'production/series/static/css/cultivation.css' => 2,
		'production/series/static/css/place.css' => 18,
		'production/series/static/css/planning.css' => 3,
		'production/series/static/css/series.css' => 20,
		'production/series/static/css/task.css' => 5,
		'production/series/static/css/timesheet.css' => 3,
	];

	/**
	 * Les thèmes de la boutique et du site public, que la structure choisit et qui gardent leur
	 * propre mouvement : aucune convergence ne les baissera.
	 */
	const MOTION_PUBLIC = [
		'commercialisation/shop/static/css/design-ami.css' => 2,
		'commercialisation/shop/static/css/design-bucolique.css' => 4,
		'commercialisation/shop/static/css/design-bulles.css' => 12,
		'commercialisation/shop/static/css/design-carnet.css' => 3,
		'commercialisation/shop/static/css/design-collage.css' => 4,
		'commercialisation/shop/static/css/design-contour.css' => 2,
		'commercialisation/shop/static/css/design-futur.css' => 6,
		'commercialisation/shop/static/css/design-guinguette.css' => 7,
		'commercialisation/shop/static/css/design-halo.css' => 4,
		'commercialisation/shop/static/css/design-joyeux.css' => 4,
		'commercialisation/shop/static/css/design-kraft.css' => 2,
		'commercialisation/shop/static/css/design-medaillon.css' => 5,
		'commercialisation/shop/static/css/design-profil.css' => 3,
		'commercialisation/shop/static/css/design-reportage.css' => 3,
		'commercialisation/shop/static/css/design-splash.css' => 11,
		'commercialisation/shop/static/css/design-vintage.css' => 6,
		'ouvretaferme/website/static/css/design-etal.css' => 6,
		'ouvretaferme/website/static/css/design-geek.css' => 1,
		'ouvretaferme/website/static/css/design-kermesse.css' => 1,
		'ouvretaferme/website/static/css/design-submenu.css' => 1,
	];

	const REDUCED_MOTION = [
		'commercialisation/shop/static/css/design-bulles.css' => 1,
		'ouvretaferme/main/static/css/component.css' => 1,
		'ouvretaferme/website/static/css/design-geek.css' => 1,
		'production/planning/static/css/planning.css' => 1,
		'production/plant/static/css/varietyRating.css' => 1,
		'production/series/static/css/place.css' => 2,
		'production/series/static/css/series.css' => 3,
	];

	const MOTION_VARIANT = [
		'base/media/static/css/upload.css' => 2,
		'base/structure/static/css/apiKey.css' => 2,
		'base/structure/static/css/production.css' => 1,
		'commercialisation/payment/static/css/wallet.css' => 1,
		'base/product/static/css/category.css' => 1,
		'commercialisation/catalog/static/css/offer.css' => 1,
		'base/product/static/css/stock.css' => 1,
		'commercialisation/quotation/static/css/panelAi.css' => 1,
		'commercialisation/quotation/static/css/quotation.css' => 1,
		'commercialisation/selling/static/css/market.css' => 3,
		'commercialisation/selling/static/css/sale.css' => 1,
		'commercialisation/shop/static/css/design-bucolique.css' => 1,
		'commercialisation/shop/static/css/design-bulles.css' => 5,
		'commercialisation/shop/static/css/design-collage.css' => 2,
		'commercialisation/shop/static/css/design-guinguette.css' => 3,
		'commercialisation/shop/static/css/design-joyeux.css' => 1,
		'commercialisation/shop/static/css/design-splash.css' => 2,
		'commercialisation/shop/static/css/design-vintage.css' => 1,
		'commercialisation/shop/static/css/menu.css' => 1,
		'framework/editor/static/css/editor.css' => 3,
		'framework/util/static/css/form.css' => 3,
		'ouvretaferme/association/static/css/association.css' => 1,
		// La variante est écrite sur `.design-motion-after :is(…)`, un sélecteur que la sentinelle ne rapproche pas de ceux de ses règles
		'ouvretaferme/design/static/css/designAi.css' => 4,
		'ouvretaferme/main/static/css/component.css' => 6,
		'ouvretaferme/main/static/css/template-base.css' => 1,
		'ouvretaferme/main/static/css/template-structure.css' => 1,
		'ouvretaferme/website/static/css/design-etal.css' => 3,
		'ouvretaferme/website/static/css/design-kermesse.css' => 1,
		'ouvretaferme/website/static/css/design-submenu.css' => 1,
		'production/sequence/static/css/flow.css' => 1,
		'production/series/static/css/place.css' => 2,
		'production/series/static/css/planning.css' => 1,
		'production/series/static/css/series.css' => 3,
		'production/series/static/css/task.css' => 4,
		'production/series/static/css/timesheet.css' => 1,
	];

	const LAYOUT_TRANSITION = [
		'base/media/static/css/upload.css' => 2,
		'base/structure/static/css/apiKey.css' => 1,
		'commercialisation/payment/static/css/wallet.css' => 1,
		'base/product/static/css/category.css' => 1,
		'commercialisation/catalog/static/css/offer.css' => 1,
		'base/product/static/css/stock.css' => 1,
		'commercialisation/selling/static/css/market.css' => 2,
		'commercialisation/selling/static/css/sale.css' => 1,
		'commercialisation/shop/static/css/menu.css' => 1,
		'framework/editor/static/css/editor.css' => 3,
		'framework/util/static/css/form.css' => 3,
		'ouvretaferme/association/static/css/association.css' => 1,
		'ouvretaferme/main/static/css/component.css' => 6,
		// Le panneau en tactile, `left 0.33s`, repris de lime.css tel quel pour ne pas changer ce que l'on sent sous le doigt
		'ouvretaferme/main/static/css/juicinessAi.css' => 1,
		'production/sequence/static/css/flow.css' => 1,
		'production/series/static/css/place.css' => 3,
		'production/series/static/css/planning.css' => 1,
		'production/series/static/css/task.css' => 4,
		'production/series/static/css/timesheet.css' => 1,
	];

	/**
	 * Les deux feuilles du design system. Toute autre feuille suivie est une feuille de package au
	 * sens des contrôles du châssis, ceux du socle de `ouvretaferme/main` et de `framework/util`
	 * compris : c'est la définition du skill `ui` (« une feuille qui n'est ni reboot.css ni
	 * component.css »).
	 */
	const COMPONENT = 'ouvretaferme/main/static/css/component.css';
	const REBOOT = 'ouvretaferme/main/static/css/reboot.css';

	/**
	 * Le plancher d'un texte, en rem (skill `ui`, « Aucun texte ne descend sous 0,9 rem », décidé par
	 * Vincent le 2026-08-15, portée confirmée le 2026-10-02).
	 */
	const TEXT_FLOOR = 0.9;

	/**
	 * Les ancêtres que le HTML impose à un élément du châssis : une feuille qui écrit `.x th` vise le
	 * `th` de `table:not(.table-unstyled) th` sans avoir à nommer la table.
	 */
	const IMPLIED_ANCESTORS = [
		'th' => ['table', 'thead', 'tbody', 'tfoot', 'tr'],
	];

	/**
	 * Une marge ou un espacement, comme `SpaceScaleSentinelAi` les lit, `padding` en moins : la règle
	 * porte sur la place d'un composant parmi ses voisins, pas sur son intérieur.
	 */
	const SPACING = '/^(?:margin(?:-(?:top|right|bottom|left|inline|block)(?:-(?:start|end))?)?|(?:grid-)?(?:row-|column-)?gap)$/';

	/**
	 * Les raccourcis, avec ceux de leurs préfixés qu'ils ne fixent pas : `border` ne touche pas au
	 * rayon, `flex` ne touche pas à la direction, `outline` ne touche pas à son décalage. Un raccourci
	 * et un de ses longs se recouvrent, deux longs voisins non.
	 */
	const SHORTHANDS = [
		'animation' => [],
		'background' => [],
		'border' => ['border-collapse', 'border-spacing', 'border-radius', 'border-top-left-radius', 'border-top-right-radius', 'border-bottom-left-radius', 'border-bottom-right-radius', 'border-start-start-radius', 'border-start-end-radius', 'border-end-start-radius', 'border-end-end-radius'],
		'border-top' => [],
		'border-right' => [],
		'border-bottom' => [],
		'border-left' => [],
		'border-color' => [],
		'border-style' => [],
		'border-width' => [],
		'border-radius' => [],
		'columns' => [],
		'flex' => ['flex-direction', 'flex-wrap', 'flex-flow'],
		'flex-flow' => [],
		'font' => ['font-feature-settings', 'font-variation-settings'],
		'gap' => [],
		'grid' => ['grid-row', 'grid-row-start', 'grid-row-end', 'grid-column', 'grid-column-start', 'grid-column-end', 'grid-area'],
		'grid-template' => [],
		'inset' => [],
		'list-style' => [],
		'margin' => [],
		'outline' => ['outline-offset'],
		'overflow' => ['overflow-wrap', 'overflow-anchor'],
		'padding' => [],
		'place-content' => [],
		'place-items' => [],
		'place-self' => [],
		'text-decoration' => [],
		'transition' => [],
	];

	/**
	 * Les déclarations d'une feuille de package qui reprennent ce que le châssis pose déjà sur le
	 * même élément (`findChassis()`). Relevé sur `origin/main` à `b41869f01` le 2026-10-16 : 514
	 * déclarations dans 87 feuilles, dont 236 sur une classe util-* de component.css et 278 sur une
	 * règle de reboot.css.
	 */
	const CHASSIS = [
		'accounting/articles/static/css/articles.css' => 3,
		// L'en-tête des opérations bancaires masqué au téléphone, comme journal.css, en `@media screen` pour que la page imprimée le garde
		'accounting/bank/static/css/cashflow.css' => 1,
		'accounting/bank/static/css/import.css' => 1,
		// L'en-tête des opérations d'un cahier et celui des suggestions d'import masqués au téléphone, comme journal.css, en `@media screen` pour que la page imprimée les garde
		'accounting/cash/static/css/cash.css' => 2,
		'accounting/company/static/css/company.css' => 1,
		'accounting/financialYear/static/css/financialYear.css' => 2,
		'accounting/financialYear/static/css/pdf.css' => 7,
		// L'en-tête des factures électroniques masqué jusqu'à 991 px, là où leur tableau défilait en largeur ; la page imprimée y tombe et s'empile aussi
		'accounting/invoicing/static/css/invoicing.css' => 2,
		'accounting/invoicing/static/css/pdf.css' => 1,
		// L'en-tête du livre journal et celui du grand livre masqués au téléphone, comme proof.css, en `@media screen` pour que la page imprimée les garde : hide-sm-down les masquerait aussi à l'impression
		'accounting/journal/static/css/journal.css' => 2,
		'accounting/journal/static/css/operation.css' => 2,
		'accounting/journal/static/css/pattern.css' => 1,
		'accounting/overview/static/css/balance.css' => 3,
		'accounting/overview/static/css/incomeStatement.css' => 3,
		// L'en-tête d'un import des ventes ou des achats masqué au téléphone, comme journal.css, en `@media screen` pour que la page imprimée le garde
		'accounting/preaccounting/static/css/invoicing.css' => 1,
		'accounting/preaccounting/static/css/preaccounting.css' => 7,
		'accounting/proof/static/css/proof.css' => 3,
		'accounting/taxReturn/static/css/pdf.css' => 13,
		'accounting/taxReturn/static/css/taxReturn.css' => 2,
		'accounting/town/static/css/town.css' => 3,
		'accounting/vat/static/css/timeline.css' => 1,
		'accounting/vat/static/css/vat.css' => 8,
		'base/block/static/css/block.css' => 2,
		'base/block/static/css/read.css' => 2,
		'base/dashboard/static/css/dashboard.css' => 9,
		'base/entry/static/css/entry.css' => 1,
		'base/mail/static/css/mail.css' => 2,
		'base/notification/static/css/notification.css' => 7,
		'base/scene/static/css/scene.css' => 3,
		'base/structure/static/css/weatherAi.css' => 2,
		'commercialisation/amap/static/css/contract.css' => 7,
		'commercialisation/amap/static/css/customer.css' => 3,
		'commercialisation/amap/static/css/distribution.css' => 1,
		'commercialisation/amap/static/css/membership.css' => 4,
		'commercialisation/amap/static/css/referent.css' => 2,
		'commercialisation/amap/static/css/sheet.css' => 3,
		'commercialisation/amap/static/css/subscription.css' => 26,
		'commercialisation/amap/static/css/volunteer.css' => 2,
		'commercialisation/external/static/css/document.css' => 1,
		'commercialisation/payment/static/css/payment.css' => 1,
		'base/product/static/css/product.css' => 11,
		'commercialisation/catalog/static/css/shopVariantAi.css' => 1,
		'commercialisation/quotation/static/css/panelAi.css' => 2,
		'commercialisation/quotation/static/css/quotation.css' => 23,
		'commercialisation/selling/static/css/analyzeVariantAi.css' => 2,
		'commercialisation/selling/static/css/compositionAi.css' => 8,
		'commercialisation/selling/static/css/market.css' => 2,
		'commercialisation/selling/static/css/pdf.css' => 1,
		'commercialisation/selling/static/css/sale.css' => 1,
		'commercialisation/shop/static/css/auditAi.css' => 3,
		'commercialisation/shop/static/css/date.css' => 2,
		'commercialisation/shop/static/css/design-futur.css' => 4,
		'commercialisation/shop/static/css/manage.css' => 3,
		'commercialisation/shop/static/css/menu.css' => 7,
		'commercialisation/shop/static/css/shop.css' => 13,
		'framework/util/static/css/batch.css' => 2,
		'framework/util/static/css/form.css' => 2,
		'internal/communication/static/css/noteAi.css' => 1,
		'ouvretaferme/admin/static/css/statisticAi.css' => 6,
		'ouvretaferme/analyze/static/css/report.css' => 3,
		'ouvretaferme/design/static/css/designAi.css' => 12,
		'ouvretaferme/game/static/css/game.css' => 3,
		'ouvretaferme/learning/static/css/learning.css' => 11,
		'ouvretaferme/main/static/css/customize.css' => 1,
		'ouvretaferme/main/static/css/pdf.css' => 17,
		'ouvretaferme/main/static/css/template-base.css' => 6,
		'ouvretaferme/main/static/css/template-community.css' => 1,
		'ouvretaferme/main/static/css/template-structure.css' => 12,
		'ouvretaferme/subscription/static/css/subscriptionAi.css' => 1,
		'production/map/static/css/plan.css' => 10,
		'production/map/static/css/scene.css' => 39,
		'production/map/static/css/zone.css' => 2,
		'production/planning/static/css/planning.css' => 56,
		'production/plant/static/css/analyze.css' => 3,
		'production/plant/static/css/communityAi.css' => 1,
		'production/sequence/static/css/flow.css' => 1,
		'production/series/static/css/gardenChairsAi.css' => 2,
		'production/series/static/css/place.css' => 8,
		'production/series/static/css/planning.css' => 4,
		'production/series/static/css/series.css' => 11,
		'production/toolbox/static/css/calculator.css' => 3,
		'production/toolbox/static/css/composition.css' => 17,
		'production/toolbox/static/css/fertilization.css' => 1,
		'production/toolbox/static/css/maintenance.css' => 4,
		'production/toolbox/static/css/pdfTable.css' => 7,
		'production/toolbox/static/css/toolbox.css' => 4,
		'production/toolbox/static/css/treatment.css' => 3,
		'production/work/static/css/routine.css' => 1,
	];

	/**
	 * Les tailles de texte sous le plancher (`findSmallText()`). Relevé sur `origin/main` à
	 * `b41869f01` le 2026-10-16 : 551 dans 119 feuilles, dont les 38 de component.css que le skill
	 * `ui` compte déjà comme une dette du socle.
	 */
	const SMALL_TEXT = [
		'accounting/articles/static/css/articles.css' => 3,
		'accounting/company/static/css/onboardingAi.css' => 2,
		'accounting/company/static/css/pdf.css' => 1,
		'accounting/financialYear/static/css/financialYear.css' => 6,
		'accounting/financialYear/static/css/pdf.css' => 1,
		'accounting/invoicing/static/css/invoicing.css' => 2,
		'accounting/invoicing/static/css/pdf.css' => 17,
		'accounting/journal/static/css/journal.css' => 1,
		'accounting/journal/static/css/pattern.css' => 1,
		'accounting/overview/static/css/analyze.css' => 1,
		'accounting/overview/static/css/sig.css' => 1,
		'accounting/preaccounting/static/css/invoicing.css' => 2,
		'accounting/preaccounting/static/css/preaccounting.css' => 6,
		'accounting/proof/static/css/proof.css' => 1,
		'accounting/taxReturn/static/css/pdf.css' => 1,
		'accounting/town/static/css/town.css' => 1,
		'base/block/static/css/block.css' => 1,
		'base/mail/static/css/contact.css' => 1,
		'base/mail/static/css/mail.css' => 1,
		'base/structure/static/css/apiKey.css' => 4,
		'base/structure/static/css/profileAi.css' => 5,
		'base/structure/static/css/structure.css' => 1,
		'base/structure/static/css/system.css' => 2,
		'base/user/static/css/avatar.css' => 1,
		'commercialisation/payment/static/css/payment.css' => 1,
		'commercialisation/catalog/static/css/offer.css' => 5,
		'base/product/static/css/product.css' => 4,
		'commercialisation/catalog/static/css/shopVariantAi.css' => 3,
		'base/product/static/css/stock.css' => 1,
		'commercialisation/quotation/static/css/quotation.css' => 34,
		'commercialisation/quotation/static/css/synthesisAi.css' => 2,
		'commercialisation/selling/static/css/campaign.css' => 1,
		'commercialisation/selling/static/css/compositionAi.css' => 2,
		'commercialisation/selling/static/css/customer.css' => 2,
		'commercialisation/selling/static/css/item.css' => 2,
		'commercialisation/selling/static/css/merchant.css' => 1,
		'commercialisation/selling/static/css/pdf.css' => 19,
		'commercialisation/selling/static/css/sale.css' => 3,
		'commercialisation/shop/static/css/design-ami.css' => 2,
		'commercialisation/shop/static/css/design-bucolique.css' => 2,
		'commercialisation/shop/static/css/design-bulles.css' => 1,
		'commercialisation/shop/static/css/design-carnet.css' => 1,
		'commercialisation/shop/static/css/design-contour.css' => 1,
		'commercialisation/shop/static/css/design-epure.css' => 1,
		'commercialisation/shop/static/css/design-futur.css' => 2,
		'commercialisation/shop/static/css/design-halo.css' => 1,
		'commercialisation/shop/static/css/design-joyeux.css' => 1,
		'commercialisation/shop/static/css/design-kraft.css' => 2,
		'commercialisation/shop/static/css/design-medaillon.css' => 1,
		'commercialisation/shop/static/css/design-origami.css' => 1,
		'commercialisation/shop/static/css/design-profil.css' => 1,
		'commercialisation/shop/static/css/design-psychedelique.css' => 2,
		'commercialisation/shop/static/css/design-reportage.css' => 4,
		'commercialisation/shop/static/css/design-splash.css' => 1,
		'commercialisation/shop/static/css/design-vintage.css' => 2,
		'commercialisation/shop/static/css/manage.css' => 1,
		'commercialisation/shop/static/css/menu.css' => 1,
		'commercialisation/shop/static/css/point.css' => 2,
		'commercialisation/shop/static/css/shop.css' => 1,
		'framework/dev/static/css/doc.css' => 1,
		'framework/editor/static/css/editor.css' => 8,
		'framework/util/static/css/batch.css' => 8,
		// La sonde de `.columns:before`, masquée, dont 1px ne dimensionne aucun texte lu
		'framework/util/static/css/column.css' => 1,
		'framework/util/static/css/form.css' => 5,
		'framework/util/static/css/lime.css' => 1,
		'framework/util/static/css/steps.css' => 1,
		'ouvretaferme/admin/static/css/statisticAi.css' => 23,
		'ouvretaferme/analyze/static/css/report.css' => 2,
		'ouvretaferme/design/static/css/designAi.css' => 6,
		'ouvretaferme/learning/static/css/learning.css' => 2,
		'ouvretaferme/main/static/css/chart.css' => 1,
		'ouvretaferme/main/static/css/component.css' => 38,
		'ouvretaferme/main/static/css/customize.css' => 3,
		'ouvretaferme/main/static/css/pdf.css' => 8,
		'ouvretaferme/main/static/css/reboot.css' => 2,
		'ouvretaferme/main/static/css/root.css' => 2,
		'ouvretaferme/main/static/css/template-base.css' => 3,
		'ouvretaferme/main/static/css/template-structure.css' => 3,
		'ouvretaferme/website/static/css/customizeAi.css' => 1,
		'ouvretaferme/website/static/css/design-arche.css' => 2,
		'ouvretaferme/website/static/css/design-atelier.css' => 8,
		'ouvretaferme/website/static/css/design-classique.css' => 1,
		'ouvretaferme/website/static/css/design-content.css' => 1,
		'ouvretaferme/website/static/css/design-etal.css' => 8,
		'ouvretaferme/website/static/css/design-gazette.css' => 11,
		'ouvretaferme/website/static/css/design-geek.css' => 9,
		'ouvretaferme/website/static/css/design-horizon.css' => 5,
		'ouvretaferme/website/static/css/design-kermesse.css' => 7,
		'ouvretaferme/website/static/css/design-medaillon.css' => 4,
		'ouvretaferme/website/static/css/design-pele-mele.css' => 5,
		'ouvretaferme/website/static/css/design-simple.css' => 1,
		'ouvretaferme/website/static/css/design-sommaire.css' => 10,
		'ouvretaferme/website/static/css/manage.css' => 2,
		'production/map/static/css/bed.css' => 5,
		'production/map/static/css/onboarding.css' => 2,
		'production/map/static/css/plan.css' => 13,
		'production/map/static/css/plot.css' => 1,
		'production/map/static/css/scene.css' => 2,
		'production/map/static/css/zone.css' => 2,
		'production/planning/static/css/planning.css' => 3,
		'production/plant/static/css/communityAi.css' => 22,
		'production/plant/static/css/family.css' => 2,
		'production/plant/static/css/plant.css' => 1,
		'production/plant/static/css/varietyRating.css' => 4,
		'production/sequence/static/css/densityPlanAi.css' => 1,
		'production/sequence/static/css/flow.css' => 3,
		'production/series/static/css/compare.css' => 1,
		'production/series/static/css/cultivation.css' => 3,
		'production/series/static/css/gardenChairsAi.css' => 2,
		'production/series/static/css/place.css' => 33,
		'production/series/static/css/placeGardenAi.css' => 12,
		'production/series/static/css/series.css' => 42,
		'production/series/static/css/task.css' => 8,
		'production/toolbox/static/css/composition.css' => 5,
		'production/toolbox/static/css/pdf.css' => 3,
		'production/toolbox/static/css/pdfTable.css' => 3,
	];

	/**
	 * Les valeurs nues de `z-index`, `max-width` et `grid-template-columns` (`findBareValue()`).
	 * Relevé sur `origin/main` à `b41869f01` le 2026-10-16 : 804 dans 154 feuilles, 346 `z-index`,
	 * 241 `max-width` et 217 `grid-template-columns`. Une valeur en dur ne se lit pas dans une
	 * échelle : sa baisse passe par des tokens de reboot.css, qui sont une décision du design system.
	 */
	const BARE_VALUE = [
		'accounting/articles/static/css/articles.css' => 5,
		'accounting/asset/static/css/asset.css' => 1,
		'accounting/bank/static/css/import.css' => 1,
		'accounting/bank/static/css/reconciliation.css' => 1,
		'accounting/bank/static/css/statementAi.css' => 1,
		'accounting/cash/static/css/cash.css' => 1,
		'accounting/company/static/css/company.css' => 3,
		'accounting/company/static/css/onboardingAi.css' => 1,
		'accounting/financialYear/static/css/financialYear.css' => 2,
		'accounting/invoicing/static/css/invoicing.css' => 2,
		'accounting/invoicing/static/css/pdf.css' => 7,
		'accounting/journal/static/css/operation.css' => 10,
		'accounting/preaccounting/static/css/preaccounting.css' => 2,
		'accounting/preaccounting/static/css/step.css' => 4,
		'accounting/proof/static/css/proof.css' => 1,
		'accounting/taxReturn/static/css/pdf.css' => 1,
		'accounting/town/static/css/town.css' => 4,
		'accounting/training/static/css/training.css' => 4, // les pistes des trois grilles d'édition des écritures, chacune à la largeur de son champ (date, taux, montant), et 100cqw, la largeur du cadre qui défile, de même nature que 100 %
		'accounting/vat/static/css/timeline.css' => 1,
		'base/block/static/css/block.css' => 4,
		'base/block/static/css/read.css' => 1,
		'base/dashboard/static/css/dashboard.css' => 1,
		'base/hr/static/css/absence.css' => 1, // le calque des traits de la frise passe sous le contenu de sa cellule, que isolation: isolate borne : un empilement local, pas une couche de l'interface
		'base/mail/static/css/contact.css' => 2,
		'base/media/static/css/media.css' => 1,
		'base/media/static/css/upload.css' => 5,
		'base/notification/static/css/notification.css' => 5,
		'base/scene/static/css/scene.css' => 1,
		'base/structure/static/css/profileAi.css' => 4,
		'base/structure/static/css/shareAi.css' => 3,
		'base/structure/static/css/survey.css' => 1,
		'base/user/static/css/admin.css' => 1,
		'base/user/static/css/avatar.css' => 1,
		'base/user/static/css/tosAi.css' => 1,
		'base/user/static/css/user.css' => 2,
		'commercialisation/amap/static/css/contract.css' => 5,
		'commercialisation/amap/static/css/customer.css' => 2,
		'commercialisation/amap/static/css/distribution.css' => 4,
		'commercialisation/amap/static/css/membershipRequest.css' => 2,
		'commercialisation/amap/static/css/producer.css' => 1,
		'commercialisation/amap/static/css/subscription.css' => 4,
		'commercialisation/external/static/css/document.css' => 4,
		'commercialisation/payment/static/css/wallet.css' => 3,
		'commercialisation/catalog/static/css/offer.css' => 7,
		'base/product/static/css/product.css' => 1,
		'commercialisation/catalog/static/css/shopVariantAi.css' => 2,
		'base/product/static/css/stock.css' => 1,
		'commercialisation/quotation/static/css/panelAi.css' => 1,
		'commercialisation/quotation/static/css/quotation.css' => 19,
		'commercialisation/quotation/static/css/synthesisAi.css' => 4,
		'commercialisation/selling/static/css/compositionAi.css' => 2,
		'commercialisation/selling/static/css/configuration.css' => 2,
		'commercialisation/selling/static/css/customer.css' => 2,
		'commercialisation/selling/static/css/invoice.css' => 1,
		'commercialisation/selling/static/css/market.css' => 10,
		'commercialisation/selling/static/css/merchant.css' => 6,
		'commercialisation/selling/static/css/pdf.css' => 7,
		'commercialisation/selling/static/css/sale.css' => 2,
		'commercialisation/shop/static/css/auditAi.css' => 1,
		'commercialisation/shop/static/css/date.css' => 1,
		'commercialisation/shop/static/css/design-ami.css' => 1,
		'commercialisation/shop/static/css/design-bucolique.css' => 2,
		'commercialisation/shop/static/css/design-bulles.css' => 2,
		'commercialisation/shop/static/css/design-carnet.css' => 1,
		'commercialisation/shop/static/css/design-collage.css' => 3,
		'commercialisation/shop/static/css/design-contour.css' => 1,
		'commercialisation/shop/static/css/design-epure.css' => 1,
		'commercialisation/shop/static/css/design-futur.css' => 2,
		'commercialisation/shop/static/css/design-halo.css' => 3,
		'commercialisation/shop/static/css/design-kraft.css' => 2,
		'commercialisation/shop/static/css/design-medaillon.css' => 2,
		'commercialisation/shop/static/css/design-profil.css' => 2,
		'commercialisation/shop/static/css/design-psychedelique.css' => 2,
		'commercialisation/shop/static/css/design-reportage.css' => 2,
		'commercialisation/shop/static/css/design-splash.css' => 1,
		'commercialisation/shop/static/css/design-vintage.css' => 2,
		'commercialisation/shop/static/css/manage.css' => 5,
		'commercialisation/shop/static/css/menu.css' => 1,
		'commercialisation/shop/static/css/shop.css' => 3,
		'framework/api/static/css/authorizeAi.css' => 1,
		'framework/dev/static/css/admin.css' => 1,
		'framework/dev/static/css/dev.css' => 2,
		'framework/dev/static/css/monitoring.css' => 1,
		'framework/editor/static/css/editor.css' => 19,
		'framework/util/static/css/batch.css' => 3,
		'framework/util/static/css/form.css' => 7,
		'framework/util/static/css/lime.css' => 8,
		'framework/util/static/css/steps.css' => 2,
		'ouvretaferme/admin/static/css/statisticAi.css' => 6,
		'ouvretaferme/analyze/static/css/report.css' => 3,
		'ouvretaferme/association/static/css/pdf.css' => 1,
		'ouvretaferme/design/static/css/designAi.css' => 10,
		'ouvretaferme/game/static/css/game.css' => 2,
		'ouvretaferme/learning/static/css/learning.css' => 23,
		'ouvretaferme/main/static/css/component.css' => 66,
		'ouvretaferme/main/static/css/customize.css' => 5,
		'ouvretaferme/main/static/css/home.css' => 2,
		'ouvretaferme/main/static/css/onboarding.css' => 2,
		'ouvretaferme/main/static/css/pdf.css' => 4,
		'ouvretaferme/main/static/css/reboot.css' => 6,
		'ouvretaferme/main/static/css/root.css' => 5,
		'ouvretaferme/main/static/css/template-base.css' => 7,
		'ouvretaferme/main/static/css/template-doc.css' => 5,
		'ouvretaferme/main/static/css/template-structure.css' => 22,
		'ouvretaferme/presentation/static/css/feature.css' => 4,
		'ouvretaferme/presentation/static/css/landing.css' => 7,
		'ouvretaferme/presentation/static/css/pricing.css' => 1,
		'ouvretaferme/website/static/css/design-arche.css' => 5,
		'ouvretaferme/website/static/css/design-atelier.css' => 5,
		'ouvretaferme/website/static/css/design-classique.css' => 5,
		'ouvretaferme/website/static/css/design-etal.css' => 4,
		'ouvretaferme/website/static/css/design-gazette.css' => 5,
		'ouvretaferme/website/static/css/design-geek.css' => 4,
		'ouvretaferme/website/static/css/design-horizon.css' => 4,
		'ouvretaferme/website/static/css/design-kermesse.css' => 4,
		'ouvretaferme/website/static/css/design-medaillon.css' => 4,
		'ouvretaferme/website/static/css/design-pele-mele.css' => 5,
		'ouvretaferme/website/static/css/design-simple.css' => 5,
		'ouvretaferme/website/static/css/design-sommaire.css' => 11,
		'ouvretaferme/website/static/css/design-submenu.css' => 2,
		'ouvretaferme/website/static/css/widget.css' => 4,
		'production/map/static/css/bed.css' => 12,
		'production/map/static/css/plan.css' => 7,
		'production/map/static/css/plot.css' => 1,
		'production/map/static/css/scene.css' => 3,
		'production/map/static/css/zone.css' => 11,
		'production/planning/static/css/planning.css' => 36,
		'production/plant/static/css/analyze.css' => 1,
		'production/plant/static/css/communityAi.css' => 12,
		'production/plant/static/css/criterion.css' => 3,
		'production/plant/static/css/plant.css' => 1,
		'production/plant/static/css/varietyRating.css' => 2,
		'production/sequence/static/css/crop.css' => 1,
		'production/sequence/static/css/densityPlanAi.css' => 1,
		'production/sequence/static/css/densityViewAi.css' => 1,
		'production/sequence/static/css/flow.css' => 3,
		'production/sequence/static/css/sequence.css' => 5,
		'production/series/static/css/compare.css' => 4,
		'production/series/static/css/cultivation.css' => 4,
		'production/series/static/css/gardenChairsAi.css' => 4,
		'production/series/static/css/place.css' => 38,
		'production/series/static/css/placeGardenAi.css' => 9,
		'production/series/static/css/planning.css' => 3,
		'production/series/static/css/series.css' => 62,
		'production/series/static/css/task.css' => 9,
		'production/series/static/css/timesheet.css' => 2,
		'production/series/static/css/vegetableDashboardAi.css' => 1,
		'production/toolbox/static/css/catalog.css' => 1,
		'production/toolbox/static/css/composition.css' => 15,
		'production/toolbox/static/css/fertilization.css' => 5,
		'production/toolbox/static/css/treatment.css' => 6,
	];

	/**
	 * Les fonds posés par une feuille de package sur `util-table-row` (`findChassis()`). Relevé sur
	 * `origin/main` à `b41869f01` le 2026-10-16.
	 */
	const ROW_BACKGROUND = [
		// Le total des opérations à justifier, qui l'emporte sur l'alternance comme l'ancien `tr.row-highlight`
		'accounting/bank/static/css/cashflow.css' => 1,
		// La date d'un cahier, qui porte les totaux du jour, et l'opération ciblée, qui l'emportent sur l'alternance comme les anciens `tr.tr-title` et `tr.row-highlight`
		'accounting/cash/static/css/cash.css' => 2,
		// Les totaux du livre journal, qui l'emportent sur l'alternance comme les anciens `tr.row-highlight`
		'accounting/journal/static/css/journal.css' => 1,
		// L'opération qu'un import écarte, qui l'emporte sur l'alternance comme l'ancien `tbody.preaccounting-import-ignored`
		'accounting/preaccounting/static/css/invoicing.css' => 1,
		// La teinte d'une somme rendue, qui l'emporte sur l'alternance
		'commercialisation/amap/static/css/subscription.css' => 1,
		// Le fond de surface des rangs dans le gabarit des structures, feuille du socle hors design system
		'ouvretaferme/main/static/css/template-structure.css' => 1,
		// Le jour choisi, qui laisse voir le fond de la page, et les tableaux du détail d'une intervention, sans fond
		'production/planning/static/css/planning.css' => 2,
	];

	/**
	 * Les marges et espacements qu'une feuille de package pose sur une classe util-* du design
	 * system (`findChassis()`). Relevé sur `origin/main` à `b41869f01` le 2026-10-16 : 100 dans 38
	 * feuilles, dont 44 sur une propriété que le design system pose lui-même sur la classe et 56 sur
	 * une propriété qu'il laisse à sa valeur initiale.
	 */
	const UTIL_SPACING = [
		'accounting/articles/static/css/articles.css' => 1,
		'accounting/cash/static/css/cash.css' => 1,
		'accounting/financialYear/static/css/financialYear.css' => 2,
		'accounting/journal/static/css/pattern.css' => 2,
		'accounting/preaccounting/static/css/preaccounting.css' => 1,
		'accounting/proof/static/css/proof.css' => 1,
		'base/block/static/css/read.css' => 4,
		'base/dashboard/static/css/dashboard.css' => 9,
		'base/notification/static/css/notification.css' => 5,
		'base/structure/static/css/shareAi.css' => 1,
		'commercialisation/amap/static/css/distribution.css' => 1,
		'commercialisation/external/static/css/document.css' => 1,
		'commercialisation/catalog/static/css/offer.css' => 1,
		'base/product/static/css/product.css' => 1,
		'base/product/static/css/stock.css' => 2,
		'commercialisation/quotation/static/css/quotation.css' => 1,
		'commercialisation/selling/static/css/compositionAi.css' => 4,
		'commercialisation/selling/static/css/market.css' => 1,
		'internal/communication/static/css/noteAi.css' => 1,
		'ouvretaferme/admin/static/css/statisticAi.css' => 2,
		'ouvretaferme/design/static/css/designAi.css' => 2,
		'ouvretaferme/learning/static/css/learning.css' => 4,
		'ouvretaferme/main/static/css/template-community.css' => 1,
		'ouvretaferme/main/static/css/template-structure.css' => 1,
		'production/map/static/css/plan.css' => 1,
		'production/map/static/css/scene.css' => 3,
		'production/planning/static/css/planning.css' => 20,
		'production/plant/static/css/communityAi.css' => 1,
		'production/series/static/css/series.css' => 1,
		'production/toolbox/static/css/calculator.css' => 2,
		'production/toolbox/static/css/composition.css' => 7,
		'production/toolbox/static/css/fertilization.css' => 1,
		'production/toolbox/static/css/toolbox.css' => 3,
		'production/toolbox/static/css/treatment.css' => 2,
	];

	/**
	 * Le châssis lu dans les deux feuilles du design system (`getChassis()`).
	 * @var array{entries: array<int, array{compounds: array<int, string[]>, properties: string[]}>, index: array<string, int[]>, utils: array<string, true>}
	 */
	private array $chassis = ['entries' => [], 'index' => [], 'utils' => []];

	/**
	 * La taille de la racine en pixels, hors `@media` (`getRootSize()`).
	 */
	private float $root = 0.0;

	public function init() {

		$reboot = file_get_contents(SentinelLib::getRoot().'/'.self::REBOOT);

		$this->chassis = $this->getChassis([
			self::COMPONENT => file_get_contents(SentinelLib::getRoot().'/'.self::COMPONENT),
			self::REBOOT => $reboot,
		]);
		$this->root = $this->getRootSize($reboot);

		foreach(SentinelLib::getFiles('.css') as $file => $path) {

			$content = file_get_contents($path);
			$lines = explode("\n", $content);
			$rules = $this->parseRules($content);

			$this->addFound('breakpoint', $file, $this->findBreakpoint($lines));
			$this->addFound('aria', $file, $this->findAria($lines));
			$this->addFound('hasOnRoot', $file, $this->findHasOnRoot($content));

			// Sans racine en pixels, une taille absolue ne se rapporte à rien : testSmallText() le dit
			if($this->root > 0.0) {
				$this->addFound('smallText', $file, $this->findSmallText($rules, $this->root));
			}

			$this->addFound('bareValue', $file, $this->findBareValue($rules));

			if(in_array($file, [self::COMPONENT, self::REBOOT], TRUE) === FALSE) {

				foreach($this->findChassis($rules, $this->chassis) as $rule => $found) {
					$this->addFound($rule, $file, $found);
				}

			}

		}

		$rows = MotionLib::getDeclarations();

		foreach($this->findMotion($rows) as $file => $lines) {
			$this->addFound(array_key_exists($file, self::MOTION_PUBLIC) ? 'motionPublic' : 'motion', $file, $lines);
		}

		foreach($this->findReducedMotion($rows) as $file => $lines) {
			$this->addFound('reducedMotion', $file, $lines);
		}

		foreach($this->findMotionVariant($rows) as $file => $lines) {
			$this->addFound('motionVariant', $file, $lines);
		}

		foreach($this->findLayoutTransition($rows) as $file => $lines) {
			$this->addFound('layoutTransition', $file, $lines);
		}

	}

	/**
	 * Une durée ou une courbe littérale hors de la feuille des tokens. Le `0.01ms` d'un bloc de
	 * mouvement réduit est la valeur que la règle impose, pas une durée choisie.
	 *
	 * @param array<int, array<string, string|int>> $rows
	 * @return array<string, int[]>
	 */
	private function findMotion(array $rows): array {

		$found = [];

		foreach($rows as $row) {

			if(
				in_array($row['kind'], [MotionLib::TRANSITION, MotionLib::ANIMATION], TRUE) === FALSE or
				$row['literal'] === 0 or
				$row['file'] === self::TOKENS or
				$this->isReducedDuration($row)
			) {
				continue;
			}

			$found[$row['file']][] = $row['line'];

		}

		return $found;

	}

	/**
	 * Un mouvement coupé sous `prefers-reduced-motion` : sans animation, `animationend` ne part
	 * jamais, et ce qui l'attend pour retirer une classe ou un nœud reste posé.
	 *
	 * @param array<int, array<string, string|int>> $rows
	 * @return array<string, int[]>
	 */
	private function findReducedMotion(array $rows): array {

		$found = [];

		foreach($rows as $row) {

			if($this->isReduced($row) === FALSE) {
				continue;
			}

			if($this->isCut($row)) {
				$found[$row['file']][] = $row['line'];
			}

		}

		return $found;

	}

	/**
	 * Un mouvement de taille, de forme ou de position sans variante sous `prefers-reduced-motion`
	 * dans sa feuille. La variante se reconnait à un sélecteur de la même règle, repris dans le bloc
	 * de mouvement réduit par une déclaration du même genre (transition ou animation) qui coupe le
	 * mouvement ou le remplace par un fondu. Une animation dont aucune feuille suivie ne porte les
	 * images clés compte comme mouvante, et un mouvement écrit sous `no-preference` a déjà sa variante.
	 *
	 * Un fondu d'opacité ou de couleur n'en demande pas, il reste sous mouvement réduit. Une boucle non
	 * plus : l'attente continue de tourner, et la règle « Une boucle décorative » du design system, qui
	 * proscrit les autres, n'est tenue par aucune sentinelle.
	 *
	 * @param array<int, array<string, string|int>> $rows
	 * @return array<string, int[]>
	 */
	private function findMotionVariant(array $rows): array {

		$moving = array_merge(['transform', 'translate', 'rotate', 'scale'], MotionLib::LAYOUT);
		$keyframes = array_column(array_filter($rows, fn($row) => $row['kind'] === MotionLib::KEYFRAMES), 'name');

		$isMoving = fn(array $row) => (
			array_intersect(explode(',', $row['property']), $moving) !== [] or
			($row['kind'] === MotionLib::ANIMATION and in_array($row['name'], ['', 'none'], TRUE) === FALSE and in_array($row['name'], $keyframes, TRUE) === FALSE)
		);

		$variants = [];

		foreach($rows as $row) {

			if(
				$this->isReduced($row) === FALSE or
				($this->isReducedDuration($row) === FALSE and $this->isCut($row) === FALSE and ($row['property'] === '' or $isMoving($row)))
			) {
				continue;
			}

			foreach($this->getSelectors($row['selector']) as $selector) {
				$variants[$row['file']][$row['kind']][$selector] = TRUE;
			}

		}

		$found = [];

		foreach($rows as $row) {

			if(
				in_array($row['kind'], [MotionLib::TRANSITION, MotionLib::ANIMATION], TRUE) === FALSE or
				$this->isReduced($row) or
				preg_match('/prefers-reduced-motion\s*:\s*no-preference/', $row['media']) === 1 or
				$row['iteration'] === 'infinite' or
				$isMoving($row) === FALSE
			) {
				continue;
			}

			$covered = array_filter($this->getSelectors($row['selector']), fn($selector) => isset($variants[$row['file']][$row['kind']][$selector]));

			if($covered === []) {
				$found[$row['file']][] = $row['line'];
			}

		}

		return $found;

	}

	/**
	 * Une transition sur ce qui recalcule la mise en page, `all` compris.
	 *
	 * @param array<int, array<string, string|int>> $rows
	 * @return array<string, int[]>
	 */
	private function findLayoutTransition(array $rows): array {

		$found = [];

		foreach($rows as $row) {

			if(
				$row['kind'] === MotionLib::TRANSITION and
				$this->isReduced($row) === FALSE and
				in_array($row['property'], MotionLib::LAYOUT, TRUE)
			) {
				$found[$row['file']][] = $row['line'];
			}

		}

		return $found;

	}

	/**
	 * @return string[]
	 */
	private function getSelectors(string $selector): array {
		return array_map('trim', explode(',', $selector));
	}

	private function isReduced(array $row): bool {
		return preg_match('/prefers-reduced-motion\s*(:\s*reduce\s*)?\)/', $row['media']) === 1;
	}

	private function isCut(array $row): bool {

		$property = preg_replace('/^-[a-z]+-/', '', $row['declaration']);

		return (
			(in_array($property, ['animation', 'animation-name', 'transition', 'transition-property'], TRUE) and $row['value'] === 'none') or
			($property === 'animation-play-state' and $row['value'] === 'paused') or
			$row['duration'] === '0'
		);

	}

	private function isReducedDuration(array $row): bool {

		return (
			$this->isReduced($row) and
			$row['duration'] === '0.00001' and
			(is_numeric($row['delay']) === FALSE or (float)$row['delay'] === 0.0) and
			($row['curve'] === '' or str_starts_with($row['curve'], 'var('))
		);

	}

	/**
	 * Une largeur de `@media` hors des paliers du dépôt : 767, 575, 991, 1199, 449, 399 et 1599 en
	 * `max-width`, le pixel suivant en `min-width`.
	 *
	 * ⚠️ 1600 est un palier du socle, pas une largeur inventée : `reboot.css` y bascule
	 * `.hide-xxl-up` (l. 1281) et le retrait de la première colonne de `util-table`, et 13 `@media`
	 * du dépôt l'emploient, dont `framework/util/static/css/batch.css`. Une feuille de package qui
	 * s'aligne sur une de ces règles doit pouvoir citer le même seuil (mesuré le 2026-09-17, sur
	 * `commercialisation/shop/static/css/manage.css:207`, qui prolonge `util-table`).
	 *
	 * @param string[] $lines
	 * @return int[]
	 */
	private function findBreakpoint(array $lines): array {

		$found = [];
		$allowed = [
			'max' => ['767', '575', '991', '1199', '449', '399', '1599'],
			'min' => ['768', '576', '992', '1200', '450', '400', '1600'],
		];

		foreach($lines as $position => $line) {

			if(
				str_contains($line, '@media') === FALSE or
				preg_match_all('/(max|min)-width\s*:\s*([0-9.]+)px/', $line, $matches, PREG_SET_ORDER) === 0
			) {
				continue;
			}

			foreach($matches as [, $bound, $width]) {

				if(in_array($width, $allowed[$bound], TRUE) === FALSE) {
					$found[] = $position + 1;
				}

			}

		}

		return $found;

	}

	/**
	 * @param string[] $lines
	 * @return int[]
	 */
	private function findAria(array $lines): array {

		$found = [];

		foreach($lines as $position => $line) {

			if(preg_match('/\baria-[a-z]/', $line) === 1) {
				$found[] = $position + 1;
			}

		}

		return $found;

	}

	/**
	 * Le sujet est `html`, `:root` ou `body`, avec ses classes, son identifiant, ses attributs et ses
	 * `:not()` (`body[data-template~="doc"]`, `html.js`), en `:has(` comme en `:not(:has(`. Seule une
	 * chaîne d'enfants directs est exemptée (`:has(> body > #sheet)`, `:has(> #sel:not(.hide))`),
	 * parce que c'est la seule forme mesurée : une liste ou un descendant après le premier `>` compte.
	 * Les commentaires sont écartés : une feuille qui raconte le sélecteur qu'elle a remplacé ne le
	 * pose pas.
	 *
	 * @return int[]
	 */
	private function findHasOnRoot(string $content): array {

		$content = preg_replace_callback('#/\*.*?\*/#s', fn(array $comment) => preg_replace('/[^\n]/', ' ', $comment[0]), $content);

		$found = [];

		foreach(explode("\n", $content) as $position => $line) {

			preg_match_all('/(?<![\w.#-])(?:html|body|:root)(?:[.#][\w-]+|\[[^\]]*\]|:not\([^()]*\))*:(?:not\(:)?has\(/', $line, $matches, PREG_OFFSET_CAPTURE);

			foreach($matches[0] as [$subject, $offset]) {

				$argument = substr($line, $offset + strlen($subject));

				if(preg_match('/^\s*>\s*(?:[^\s,()]|\([^()]*\))+(?:\s*>\s*(?:[^\s,()]|\([^()]*\))+)*\s*\)/', $argument) !== 1) {
					$found[] = $position + 1;
					break;
				}

			}

		}

		return $found;

	}

	/**
	 * Les règles de style d'une feuille, une entrée par bloc de déclarations : ses sélecteurs, ce
	 * qui l'enveloppe (`@media`, `@supports`, `@container`, `@layer`) et ses déclarations, chacune
	 * avec sa ligne. Une règle imbriquée reçoit les sélecteurs résolus de son parent, `&` compris.
	 * Une déclaration posée directement dans un `@media` imbriqué (`.x { @media (…) { font-size: … } }`)
	 * revient à son parent, enveloppé de ce `@media`. Le contenu des autres at-rules (`@keyframes`,
	 * `@font-face`, `@page`, `@property`, `@starting-style`, `@scope`…) n'est pas lu. Les commentaires sont blanchis sans toucher aux retours à la ligne, pour que
	 * les numéros restent ceux du fichier.
	 *
	 * @return array<int, array{selectors: string[], media: string, declarations: array<int, array{0: string, 1: string, 2: int}>}>
	 */
	private function parseRules(string $css): array {

		$css = preg_replace_callback('#/\*.*?\*/#s', fn(array $comment) => preg_replace('/[^\n]/', ' ', $comment[0]), $css);

		$rules = [];
		// Une règle de style par sa position dans $rules, un enrobage par son prélude et, dans une règle,
		// par la position de la règle qui reçoit ses déclarations, un bloc ignoré par FALSE
		$stack = [];
		$buffer = '';
		$bufferLine = 1;
		$line = 1;
		$depth = 0;
		$quote = NULL;

		$length = strlen($css);

		for($i = 0; $i < $length; $i++) {

			$character = $css[$i];

			if($character === "\n") {
				$line++;
			}

			if($quote !== NULL) {

				if($character === '\\') {
					$buffer .= $character.($css[$i + 1] ?? '');
					$i++;
					continue;
				}

				if($character === $quote) {
					$quote = NULL;
				}

				$buffer .= $character;
				continue;

			}

			if($character === '"' or $character === "'") {
				$quote = $character;
			} else if($character === '(' or $character === '[') {
				$depth++;
			} else if($character === ')' or $character === ']') {
				$depth = max(0, $depth - 1);
			} else if($depth === 0 and $character === '{') {

				$prelude = trim(preg_replace('/\s+/', ' ', $buffer));
				$buffer = '';

				if(in_array(FALSE, $stack, TRUE)) {
					$stack[] = FALSE;
				} else if(str_starts_with($prelude, '@')) {

					if(preg_match('/^@(media|supports|container|layer)\b/i', $prelude) === 0) {
						$stack[] = FALSE;
						continue;
					}

					$parents = array_filter($stack, 'is_int');

					if($parents === []) {
						$stack[] = [$prelude];
						continue;
					}

					$rules[] = [
						'selectors' => $rules[end($parents)]['selectors'],
						'media' => implode(' ', [...array_column(array_filter($stack, 'is_array'), 0), $prelude]),
						'declarations' => [],
					];
					$stack[] = [$prelude, array_key_last($rules)];

				} else {

					$selectors = $this->splitList($prelude);
					$parents = array_filter($stack, 'is_int');

					if($parents !== []) {

						$resolved = [];

						foreach($rules[end($parents)]['selectors'] as $parent) {
							foreach($selectors as $selector) {
								$resolved[] = str_contains($selector, '&') ? str_replace('&', $parent, $selector) : $parent.' '.$selector;
							}
						}

						$selectors = $resolved;

					}

					$rules[] = [
						'selectors' => $selectors,
						'media' => implode(' ', array_column(array_filter($stack, 'is_array'), 0)),
						'declarations' => [],
					];
					$stack[] = array_key_last($rules);

				}

				continue;

			} else if($depth === 0 and ($character === ';' or $character === '}')) {

				$current = end($stack);

				if(is_array($current)) {
					$current = $current[1] ?? NULL;
				}

				if(is_int($current) and preg_match('/^\s*(-?-?[a-zA-Z][\w-]*)\s*:(.*)$/s', $buffer, $match) === 1) {
					$value = trim(preg_replace('/\s+/', ' ', $match[2]));
					$rules[$current]['declarations'][] = [strtolower($match[1]), trim(preg_replace('/\s*!\s*important$/i', '', $value)), $bufferLine];
				}

				$buffer = '';

				if($character === '}') {
					array_pop($stack);
				}

				continue;

			}

			if($buffer === '' and ctype_space($character)) {
				continue;
			}

			if($buffer === '') {
				$bufferLine = $line;
			}

			$buffer .= $character;

		}

		return $rules;

	}

	/**
	 * Les éléments d'une liste séparée par des virgules, hors parenthèses et crochets.
	 *
	 * @return string[]
	 */
	private function splitList(string $list): array {

		$parts = [];
		$current = '';
		$depth = 0;

		foreach(str_split($list) as $character) {

			if($character === '(' or $character === '[') {
				$depth++;
			} else if($character === ')' or $character === ']') {
				$depth--;
			} else if($character === ',' and $depth === 0) {
				$parts[] = trim($current);
				$current = '';
				continue;
			}

			$current .= $character;

		}

		$parts[] = trim($current);

		return array_values(array_filter($parts, fn(string $part) => $part !== ''));

	}

	/**
	 * Les composés d'un sélecteur, de gauche à droite, chacun en liste d'atomes : type, classe,
	 * identifiant, attribut sans ses guillemets, pseudo-classe et pseudo-élément. Les combinateurs
	 * sont oubliés, `*` aussi.
	 *
	 * @return array<int, string[]>
	 */
	private function getCompounds(string $selector): array {

		$compounds = [];
		$current = '';
		$depth = 0;

		foreach(str_split($selector.' ') as $character) {

			if($character === '(' or $character === '[') {
				$depth++;
			} else if($character === ')' or $character === ']') {
				$depth--;
			} else if($depth === 0 and in_array($character, [' ', '>', '+', '~'], TRUE)) {

				if($current !== '') {

					preg_match_all('/::?[\w-]+(\((?:[^()]++|(?1))*\))?|\.[\w-]+|#[\w-]+|\[[^\]]*\]|^[a-z][\w-]*/i', $current, $matches);

					$compounds[] = array_map(fn(string $part) => $this->normalizeAtom($part), $matches[0]);

					$current = '';

				}

				continue;

			}

			$current .= $character;

		}

		return $compounds;

	}

	/**
	 * Un attribut s'écrit avec ou sans guillemets, un type en majuscules ou non, un pseudo-élément
	 * ancien avec un deux-points ou deux : deux écritures du même atome doivent se reconnaitre.
	 */
	private function normalizeAtom(string $part): string {

		if(str_starts_with($part, '[')) {
			return str_replace(['"', '\'', ' '], '', $part);
		} else if(preg_match('/^:(before|after|first-line|first-letter)$/i', $part) === 1) {
			return ':'.strtolower($part);
		} else if(preg_match('/^[a-z]/i', $part) === 1) {
			return strtolower($part);
		} else {
			return $part;
		}

	}

	/**
	 * @param string[] $parts
	 * @return string[]
	 */
	private function getPseudoElements(array $parts): array {
		return array_values(preg_grep('/^::/', $parts));
	}

	/**
	 * Le sélecteur du package vise ce que vise celui du châssis : son sujet porte tous les atomes du
	 * sujet du châssis et le même pseudo-élément, et chaque composé d'ascendance du châssis se
	 * retrouve, dans le même ordre, dans un composé d'ascendance du package. Un `:not()` du châssis
	 * restreint sans rien nommer : il est ignoré, sans quoi `table:not(.table-unstyled) th` ne
	 * reconnaitrait jamais `.share table th`. Un ancêtre que le HTML impose au sujet (`table` pour un
	 * `th`) n'a pas à être écrit par le package : `.share th` vise le même `th`.
	 *
	 * Les combinateurs sont oubliés : `.util-list .x .util-item` est lu comme un enfant direct de
	 * `.util-list > .util-item`.
	 *
	 * @param array<int, string[]> $package
	 * @param array<int, string[]> $chassis
	 */
	private function isTargeting(array $package, array $chassis): bool {

		$names = fn(array $parts) => array_filter($parts, fn(string $part) => str_starts_with($part, ':not(') === FALSE);

		$subject = array_pop($package);
		$chassisSubject = array_pop($chassis);
		$implied = self::IMPLIED_ANCESTORS[$chassisSubject[0]] ?? [];

		if(
			array_diff($names($chassisSubject), $subject) !== [] or
			$this->getPseudoElements($chassisSubject) !== $this->getPseudoElements($subject)
		) {
			return FALSE;
		}

		while($chassis !== []) {

			$wanted = $names(array_pop($chassis));

			if(array_diff($wanted, $implied) === []) {
				continue;
			}

			do {

				if($package === []) {
					return FALSE;
				}

			} while(array_diff($wanted, array_pop($package)) !== []);

		}

		return TRUE;

	}

	private function isOverlapping(string $first, string $second): bool {
		return ($first === $second or $this->isLonghandOf($first, $second) or $this->isLonghandOf($second, $first));
	}

	private function isLonghandOf(string $longhand, string $shorthand): bool {

		return (
			array_key_exists($shorthand, self::SHORTHANDS) and
			str_starts_with($longhand, $shorthand.'-') and
			in_array($longhand, self::SHORTHANDS[$shorthand], TRUE) === FALSE
		);

	}

	/**
	 * Le châssis : les sujets des règles de component.css qui portent une classe util-*, et des
	 * règles de reboot.css qui portent une classe ou qui dessinent un `th`, avec les propriétés
	 * qu'elles déclarent, indexés par un atome nommant de leur sujet. Une propriété personnalisée
	 * n'en fait pas partie : la poser est la façon prévue de régler un composant (`--record-reach`).
	 *
	 * Les règles de reboot.css sur un élément nu (`a`, `p`, `h1` à `h6`, `ul`, `td`, `label`) sont
	 * la remise à zéro du navigateur, que chaque feuille prolonge sur ses propres balises : seul `th`
	 * en est retenu, parce que reboot.css le dessine en étiquette de colonne et qu'un package qui le
	 * contre-braque réécrit le châssis (skill `ui`, « Jamais de `<th>` sur la première colonne »).
	 * Les classes util-* nommées dans un sélecteur de l'une ou l'autre feuille sont relevées à part :
	 * ce sont celles du design system.
	 *
	 * @param array<string, string> $sheets
	 * @return array{entries: array<int, array{compounds: array<int, string[]>, properties: string[]}>, index: array<string, int[]>, utils: array<string, true>}
	 */
	private function getChassis(array $sheets): array {

		$chassis = ['entries' => [], 'index' => [], 'utils' => []];

		foreach($sheets as $sheet => $css) {

			foreach($this->parseRules($css) as $rule) {

				$properties = array_values(array_unique(array_filter(array_column($rule['declarations'], 0), fn(string $property) => str_starts_with($property, '--') === FALSE)));

				foreach($rule['selectors'] as $selector) {

					preg_match_all('/\.(util-[\w-]+)/', $selector, $classes);

					foreach($classes[1] as $class) {
						$chassis['utils'][$class] = TRUE;
					}

					$compounds = $this->getCompounds($selector);
					$subject = ($compounds === []) ? [] : $compounds[array_key_last($compounds)];
					$names = preg_grep('/^[.#\[a-z]/i', $subject);

					$isChassis = match($sheet) {
						self::COMPONENT => preg_grep('/^\.util-/', $subject) !== [],
						self::REBOOT => (preg_grep('/^\./', $subject) !== [] or in_array('th', $subject, TRUE)),
					};

					if($properties === [] or $names === [] or $isChassis === FALSE) {
						continue;
					}

					$chassis['entries'][] = ['compounds' => $compounds, 'properties' => $properties];
					$chassis['index'][reset($names)][] = array_key_last($chassis['entries']);

				}

			}

		}

		return $chassis;

	}

	/**
	 * Les déclarations d'une feuille de package qui touchent au châssis, par règle :
	 * - `rowBackground`, un fond (`background` ou `background-color`) sur `util-table-row` ;
	 * - `utilSpacing`, une marge ou un espacement sur une classe util-* du design system, que le
	 *   design system en pose un ou non sur elle : sa place parmi ses voisins est la sienne ;
	 * - `chassis`, toute autre propriété qu'une règle du châssis pose déjà sur l'élément visé, même
	 *   valeur comprise, la recopie n'étant pas plus légitime que la surcharge.
	 * Une déclaration compte une fois, dans la première règle qu'elle enfreint. Un pseudo-élément
	 * ajouté par le package n'est pas le composant : il ne compte que s'il reprend le pseudo-élément
	 * même que le châssis dessine. Une propriété personnalisée ne compte jamais.
	 *
	 * @param array<int, array{selectors: string[], media: string, declarations: array<int, array{0: string, 1: string, 2: int}>}> $rules
	 * @return array<string, int[]>
	 */
	private function findChassis(array $rules, array $chassis): array {

		$found = ['chassis' => [], 'rowBackground' => [], 'utilSpacing' => []];

		foreach($rules as $rule) {

			$selectors = array_map(fn(string $selector) => $this->getCompounds($selector), $rule['selectors']);

			foreach($rule['declarations'] as [$property, , $line]) {

				if(str_starts_with($property, '--')) {
					continue;
				}

				$kind = $this->getChassisKind($selectors, $property, $chassis);

				if($kind !== NULL) {
					$found[$kind][] = $line;
				}

			}

		}

		return $found;

	}

	/**
	 * @param array<int, array<int, string[]>> $selectors
	 */
	private function getChassisKind(array $selectors, string $property, array $chassis): ?string {

		foreach($selectors as $compounds) {

			$subject = ($compounds === []) ? [] : $compounds[array_key_last($compounds)];

			if($this->getPseudoElements($subject) !== []) {
				continue;
			}

			if(in_array('.util-table-row', $subject, TRUE) and in_array($property, ['background', 'background-color'], TRUE)) {
				return 'rowBackground';
			}

			$utils = array_filter($subject, fn(string $part) => str_starts_with($part, '.') and isset($chassis['utils'][substr($part, 1)]));

			if($utils !== [] and preg_match(self::SPACING, $property) === 1) {
				return 'utilSpacing';
			}

		}

		foreach($selectors as $compounds) {

			if($compounds === []) {
				continue;
			}

			foreach(array_unique($compounds[array_key_last($compounds)]) as $part) {

				foreach($chassis['index'][$part] ?? [] as $position) {

					$entry = $chassis['entries'][$position];

					if(
						$this->isTargeting($compounds, $entry['compounds']) and
						array_filter($entry['properties'], fn(string $declared) => $this->isOverlapping($property, $declared)) !== []
					) {
						return 'chassis';
					}

				}

			}

		}

		return NULL;

	}

	/**
	 * La taille de la racine hors de tout `@media`, celle de l'écran le plus courant : c'est sur elle
	 * qu'une taille en pixels se rapporte au plancher, 0 quand reboot.css ne la pose pas en pixels.
	 */
	private function getRootSize(string $css): float {

		foreach($this->parseRules($css) as $rule) {

			foreach($rule['declarations'] as [$property, $value]) {

				if(
					$rule['media'] === '' and
					$rule['selectors'] === ['html'] and
					$property === 'font-size' and
					preg_match('/^(\d*\.?\d+)px$/', $value, $match) === 1
				) {
					return (float)$match[1];
				}

			}

		}

		return 0.0;

	}

	/**
	 * Un `font-size`, ou la taille d'un raccourci `font`, sous le plancher. Une taille en rem se
	 * compare au plancher, une taille absolue (px, pt, cm…) et `x-small`, `xx-small` s'y rapportent
	 * par la racine de reboot.css hors `@media` : 11px compte, 12px non, puisqu'il tient le plancher
	 * sur l'écran le plus courant. `clamp()` compte par son minimum, `min()` dès qu'un argument lisible
	 * est sous le plancher, `max()` quand tous ses arguments le sont.
	 *
	 * Ne comptent pas, faute de pouvoir en dire la taille : une taille relative au parent (em, %,
	 * `smaller`), une taille passée par `var()` ou `calc()`, et zéro, qui ne dimensionne pas un texte
	 * mais l'efface.
	 *
	 * @param array<int, array{selectors: string[], media: string, declarations: array<int, array{0: string, 1: string, 2: int}>}> $rules
	 * @return int[]
	 */
	private function findSmallText(array $rules, float $root): array {

		$found = [];

		foreach($rules as $rule) {

			foreach($rule['declarations'] as [$property, $value, $line]) {

				if($property === 'font-size') {
					$size = strtolower($value);
				} else if($property === 'font' and preg_match('/(?:^|\s)(\d*\.?\d+(?:rem|px|pt|pc|cm|mm|in)|x{1,2}-small)(?=\s|\/)/i', $value, $match) === 1) {
					$size = strtolower($match[1]);
				} else {
					continue;
				}

				if($this->isUnderFloor($size, $root)) {
					$found[] = $line;
				}

			}

		}

		return $found;

	}

	private function isUnderFloor(string $size, float $root): bool {

		if(preg_match('/^(clamp|max|min)\((.*)\)$/', $size, $match) === 1) {

			$sizes = array_map(fn(string $argument) => $this->toRem($argument, $root), $this->splitList($match[2]));

			return match($match[1]) {
				'clamp' => ($sizes[0] !== NULL and $sizes[0] < self::TEXT_FLOOR),
				'min' => array_filter($sizes, fn(?float $rem) => $rem !== NULL and $rem < self::TEXT_FLOOR) !== [],
				'max' => (in_array(NULL, $sizes, TRUE) === FALSE and max($sizes) < self::TEXT_FLOOR),
			};

		}

		$rem = $this->toRem($size, $root);

		return ($rem !== NULL and $rem < self::TEXT_FLOOR);

	}

	private function toRem(string $size, float $root): ?float {

		if(preg_match('/^(\d*\.?\d+)(rem|px|pt|pc|cm|mm|in)$/', $size, $match) === 1) {

			$number = (float)$match[1];

			if($number === 0.0) {
				return NULL;
			}

			return match($match[2]) {
				'rem' => $number,
				'px' => $number / $root,
				'pt' => $number * 4 / 3 / $root,
				'pc' => $number * 16 / $root,
				'cm' => $number * 96 / 2.54 / $root,
				'mm' => $number * 96 / 25.4 / $root,
				'in' => $number * 96 / $root,
			};

		}

		return match($size) {
			'xx-small' => 9 / $root,
			'x-small' => 10 / $root,
			default => NULL,
		};

	}

	/**
	 * Une valeur nue, c'est-à-dire écrite hors de `var()` : pour `z-index`, tout entier, zéro compris,
	 * puisqu'un empilement se choisit autant à 0 qu'à 10 ; pour
	 * `max-width` et `grid-template-columns`, toute dimension, y compris dans `calc()`, `min()` ou
	 * `minmax()`. Une déclaration compte une fois, quel que soit le nombre de ses valeurs.
	 *
	 * Ne comptent pas : un mot-clé (`auto`, `none`, `subgrid`, `min-content`), une fraction (`1fr`),
	 * le nombre de pistes d'un `repeat()`, une largeur nulle, et 100 % ou 100vw, qui ne choisissent pas une
	 * largeur mais renvoient à celle du parent ou de l'écran. La valeur de repli d'un `var()` part
	 * avec lui : elle n'est lue que si le token manque.
	 *
	 * @param array<int, array{selectors: string[], media: string, declarations: array<int, array{0: string, 1: string, 2: int}>}> $rules
	 * @return int[]
	 */
	private function findBareValue(array $rules): array {

		$found = [];

		foreach($rules as $rule) {

			foreach($rule['declarations'] as [$property, $value, $line]) {

				if(in_array($property, ['z-index', 'max-width', 'grid-template-columns'], TRUE) === FALSE) {
					continue;
				}

				$value = preg_replace('/var(\((?:[^()]++|(?1))*\))/', '', $value);

				if($this->isBare($property, $value)) {
					$found[] = $line;
				}

			}

		}

		return $found;

	}

	private function isBare(string $property, string $value): bool {

		if($property === 'z-index') {
			return preg_match('/(?<![\w-])-?\d+/', $value) === 1;
		}

		preg_match_all('/(?<![\w.-])(\d*\.?\d+)(px|rem|em|ch|ex|lh|vw|vh|vmin|vmax|[sdl]v[wh]|cq[wih]|pt|pc|cm|mm|in|%)(?![\w-])/', $value, $matches, PREG_SET_ORDER);

		foreach($matches as [, $number, $unit]) {

			if(
				(float)$number !== 0.0 and
				((float)$number === 100.0 and in_array($unit, ['%', 'vw', 'svw', 'dvw', 'lvw'], TRUE)) === FALSE
			) {
				return TRUE;
			}

		}

		return FALSE;

	}

	public function testDetectors(): void {

		$lines = explode("\n", <<<'CSS'
			@media (max-width: 767px) {}
			@media (min-width: 768px) and (max-width: 800px) {}
			@media (max-width: 768px) {}
			.x[aria-expanded="true"] {}
			CSS);

		$this->assertEquals([2, 3], $this->findBreakpoint($lines), 'breakpoint');
		$this->assertEquals([4], $this->findAria($lines), 'aria');

		$css = <<<'CSS'
			body:has(.customize-bar) {}
			:root:has(body[data-template~="structure-production"]) {}
				html:has(body[data-template~="game"]) {}
			.a, body[data-template~="doc"]:has(#doc-menu-list.selected) {}
			html:has(> body[data-template~="shop"]) {}
			body:has( > #planning-harvest-sheet) {}
			.import-table tbody:has(input:checked) {}
			.panel-body:has(.autocomplete-open) {}
			div.tabs-item:has(~ div.tab-panel.selected) {}
			/* remplace body:has(#series-selector:not(.hide))
			   et html:has(.x) */
			body.dark:has(#x) {}
			:root:not(.x):has(.y) {}
			body#main:not(:has(.x)) {}
			body:has(> .a, .b) {}
			body:has(> main #sel:not(.hide)) {}
			body:has(> #sel:not(.hide)) {}
			CSS;

		$this->assertEquals([1, 2, 3, 4, 12, 13, 14, 15, 16], $this->findHasOnRoot($css), 'hasOnRoot : html, :root et body, attribut, classe, identifiant, :not(), :not(:has( ou retrait compris, et une liste ou un descendant après >, pas une chaîne d\'enfants directs, ni tbody, .panel-body, un autre sujet ou un commentaire');

		$css = <<<'CSS'
			.a { transition: opacity 0.2s; }
			.b { transition: opacity var(--duration-sm) var(--ease-out); }
			.c { animation: spin var(--duration-md) ease-in-out; }
			@media (prefers-reduced-motion: reduce) {
				.d { transition-duration: 0.01ms !important; }
				.e { animation: none; }
				.f { transition: none; }
				.g { transition-duration: 0s; }
				.h { animation-name: none; }
				.i { transition: opacity 0.01ms ease-in; }
				.k { animation-play-state: paused; }
			}
			@media (prefers-reduced-motion) {
				.l { animation: none; }
			}
			@media (prefers-reduced-motion: no-preference) {
				.m { animation: none; }
			}
			.j { animation: none; transition: none; }
			CSS;

		$rows = MotionLib::parse($css, 'test.css');

		$this->assertEquals(['test.css' => [1, 3, 10]], $this->findMotion($rows), 'motion : un littéral, une courbe en dur, un 0.01ms qui garde sa courbe ; pas un token ni le 0.01ms de la règle');
		$this->assertEquals(['test.css' => [6, 7, 8, 9, 11, 14]], $this->findReducedMotion($rows), 'reducedMotion : none, pause et durée nulle sous mouvement réduit, forme booléenne comprise, pas hors de lui');
		$this->assertEquals([], $this->findMotion(MotionLib::parse('.a { transition: opacity 0.2s; }', self::TOKENS)), 'la feuille des tokens est exemptée');
		$this->assertEquals(['test.css' => [1]], $this->findMotion(MotionLib::parse(".a { animation: spin var(--duration-loop) linear infinite; }\n.b { animation: spin var(--duration-loop) var(--ease-linear) infinite; }", 'test.css')), 'motion : une courbe linéaire en dur, pas son token');

		$css = <<<'CSS'
			.a { transition: transform var(--duration-md); }
			.b, .c { transition: transform var(--duration-md); }
			.d { transition: opacity var(--duration-md), color var(--duration-xs); }
			.e { transition: width var(--duration-md); }
			.f { transition: var(--duration-md); }
			.g { animation: slide var(--duration-md); }
			.h { animation: slide var(--duration-loop) infinite; }
			.i { animation: slide var(--duration-md); }
			.j { transition: none; }
			@keyframes slide { to { transform: translateX(1rem); } }
			@media (prefers-reduced-motion: reduce) {
				.c { transition-duration: 0.01ms; }
				.i { animation-duration: 0.01ms; }
				.g { transition-duration: 0.01ms; }
				.k { transition: left 0.01ms; }
			}
			CSS;

		$rows = MotionLib::parse($css, 'test.css');

		$this->assertEquals(['test.css' => [1, 4, 5, 6]], $this->findMotionVariant($rows), 'motionVariant : transform, taille, all et animation de transform sans variante du même genre ; pas un sélecteur repris, un fondu, une boucle ni none');

		$css = <<<'CSS'
			.a { transition: transform var(--duration-md); }
			.b { animation: slide var(--duration-md); }
			.c { animation: ghost var(--duration-md); }
			.e { animation: slide var(--duration-md); }
			@keyframes slide { to { transform: translateX(1rem); } }
			@keyframes fade { to { opacity: 0; } }
			@media (prefers-reduced-motion: reduce) {
				.a { transition-duration: 0.3s; }
				.b { animation: fade var(--duration-md); }
				.e { animation-play-state: paused; }
			}
			@media (prefers-reduced-motion: no-preference) {
				.d { transition: transform var(--duration-md); }
			}
			@media (prefers-contrast: no-preference) {
				.f { transition: transform var(--duration-md); }
			}
			CSS;

		$this->assertEquals(['test.css' => [1, 3, 16]], $this->findMotionVariant(MotionLib::parse($css, 'test.css')), 'motionVariant : une variante qui bouge encore ne couvre pas, un fondu substitué et une coupure couvrent, des images clés introuvables comptent comme mouvantes, et rien n\'est demandé sous le no-preference du mouvement réduit, mais un autre no-preference ne dispense pas');
		$this->assertEquals(['test.css' => [4, 5]], $this->findLayoutTransition($rows), 'layoutTransition : width et all, pas transform, opacity ni la variante sous mouvement réduit');

		$chassis = $this->getChassis([
			self::COMPONENT => <<<'CSS'
				.util-card { padding: 1rem; color: red; flex: 1; --card-fill: white; }
				.util-list > .util-item { color: blue; }
				.btn-x { color: red; }
				CSS,
			self::REBOOT => <<<'CSS'
				a { color: blue; }
				table:not(.table-unstyled) th { text-transform: uppercase; }
				.util-table-row { background-color: white; }
				CSS,
		]);

		$css = <<<'CSS'
			.x .util-card { color: green; }
			.x .util-card { --card-fill: red; }
			.x .util-card::before { color: green; }
			.x .util-card { text-align: left; flex-direction: column; }
			.x .util-card:hover { padding-left: 0; }
			.x .util-item { color: red; }
			.util-list .x > .util-item.on { color: red; }
			.x a { color: red; }
			.share table th { text-transform: none; }
			.x .btn-x { color: blue; }
			.x .util-table-row { background: none; }
			.x .util-table-row::after { background-color: red; }
			.x .util-card { margin-top: 0; gap: 0; }
			.x .util-invented { margin: 0; }
			.x { .util-card { color: red; } }
			@media (max-width: 767px) { .x .util-card { color: red; } }
			@keyframes k { from { color: red; } }
			.y th { text-transform: none; }
			.x .util-card:before { color: green; }
			.x .util-card { @media (max-width: 767px) { padding: 0; } }
			CSS;

		$found = $this->findChassis($this->parseRules($css), $chassis);

		$this->assertEquals([1, 5, 7, 9, 15, 16, 18, 20], $found['chassis'], 'chassis : couleur, long d\'un raccourci, ascendance retrouvée, th sous un :not() avec ou sans sa table, règle imbriquée, sous @media et déclaration d\'un @media imbriqué ; pas une propriété personnalisée, un pseudo-élément ajouté, une propriété que le châssis ne pose pas, un long que le raccourci ne fixe pas, une ascendance manquante, un élément nu de reboot.css, une classe de component.css sans util- ni des images clés, ni un pseudo-élément écrit à l\'ancienne');
		$this->assertEquals([11], $found['rowBackground'], 'rowBackground : background sur util-table-row, pas sur son pseudo-élément');
		$this->assertEquals([13, 13], $found['utilSpacing'], 'utilSpacing : une marge et un gap sur une classe util-* du design system, pas sur une classe util-* qu\'il ne connait pas');

		$this->assertEquals(13.0, $this->getRootSize("html { font-size: 13px; }\n@media (max-width: 359px) { html { font-size: 11px; } }"), 'racine : la taille hors @media');

		$css = <<<'CSS'
			.a { font-size: 0.85rem; }
			.b { font-size: 0.9rem; }
			.c { font-size: .8em; }
			.d { font-size: 11px; }
			.e { font-size: 12px; }
			.f { font: 700 10px/1.4 var(--font); }
			.g { font-size: max(0.75rem, 10px); }
			.h { font-size: max(0.75rem, 1em); }
			.i { font-size: clamp(0.8rem, 2vw, 1.2rem); }
			.j { font-size: min(1rem, 0.7rem); }
			.k { font-size: 0; }
			.l { font-size: var(--size); }
			.m { font-size: x-small; }
			.n { font-size: 8pt !important; }
			.o { --font-size: 0.5rem; }
			.p { @media (max-width: 767px) { font-size: 0.7rem; } }
			.q { font: 10PX sans-serif; }
			CSS;

		$this->assertEquals([1, 4, 6, 7, 9, 10, 13, 14, 16, 17], $this->findSmallText($this->parseRules($css), 13.0), 'smallText : rem, px, raccourci font en majuscules compris, max() et min() lisibles, minimum de clamp(), x-small, pt et déclaration posée dans un @media imbriqué ; pas le plancher, em, 12px qui le tient sur une racine de 13px, max() illisible, zéro, var() ni une propriété personnalisée');

		$css = <<<'CSS'
			.a { z-index: 2; }
			.b { z-index: var(--z-modal); }
			.c { z-index: auto; }
			.d { max-width: 40rem; }
			.e { max-width: 100%; }
			.f { max-width: none; }
			.g { max-width: calc(100% - 2rem); }
			.h { max-width: var(--width, 30rem); }
			.i { grid-template-columns: repeat(3, 1fr); }
			.j { grid-template-columns: 12rem 1fr; }
			.k { grid-template-columns: repeat(auto-fill, minmax(min(15rem, 100%), 1fr)); }
			.l { grid-template-columns: subgrid; }
			.m { min-width: 40rem; }
			@media (max-width: 767px) { .n { z-index: -1; } }
			.o { @media (min-width: 768px) { z-index: 3; } }
			CSS;

		$this->assertEquals([1, 4, 7, 10, 11, 14, 15], $this->findBareValue($this->parseRules($css)), 'bareValue : entier de z-index, dimension de max-width et de grid-template-columns, dans calc() et minmax() compris, sous un @media englobant ou imbriqué ; pas var() et son repli, un mot-clé, 100 %, une fraction, un nombre de pistes ni une autre propriété');

	}

	public function testBreakpoint(): void {
		$this->assertRatchet('breakpoint', self::BREAKPOINT, 'largeur de @media hors des paliers du dépôt');
	}

	public function testAria(): void {
		$this->assertRatchet('aria', [], 'attribut aria');
	}

	public function testMotion(): void {
		$this->assertRatchet('motion', self::MOTION, 'durée, délai ou courbe écrite en dur hors de juicinessAi.css, à remplacer par un token --duration-* ou --ease-*, ou ajouter le token manquant à juicinessAi.css (une feuille de thème de la boutique ou du site public se déclare dans MOTION_PUBLIC)');
	}

	public function testMotionPublic(): void {
		$this->assertRatchet('motionPublic', self::MOTION_PUBLIC, 'durée, délai ou courbe écrite en dur dans un thème de la boutique ou du site public, à remplacer par un token --duration-* ou --ease-*, ou ajouter le token manquant à juicinessAi.css');
	}

	public function testMotionVariant(): void {
		$this->assertRatchet('motionVariant', self::MOTION_VARIANT, 'mouvement de taille, de forme ou de position sans variante sous prefers-reduced-motion, à déclarer dans le bloc de mouvement réduit de la même feuille, sous le même sélecteur et en 0.01ms');
	}

	public function testLayoutTransition(): void {
		$this->assertRatchet('layoutTransition', self::LAYOUT_TRANSITION, 'transition sur ce qui recalcule la mise en page (MotionLib::LAYOUT : tailles, positions, marges, espacements, épaisseur de bordure, police, interligne, flex, grille, et all), à faire passer par transform ou opacity');
	}

	public function testHasOnRoot(): void {
		$this->assertRatchet('hasOnRoot', self::HAS_ON_ROOT, ':has() sur html, :root ou body dont l\'argument descend plus bas que ses enfants, que Firefox réévalue sur tout le document à chaque mutation de ce qu\'il lit, à remplacer par une classe posée par le code sur body, ou à écrire en enfant direct :has(> …)');
	}

	public function testReducedMotion(): void {
		$this->assertRatchet('reducedMotion', self::REDUCED_MOTION, 'mouvement coupé sous prefers-reduced-motion, à écrire en durée de 0.01ms, sans quoi animationend ne part plus');
	}

	public function testChassis(): void {
		$this->assertTrue($this->chassis['entries'] !== [] and $this->chassis['utils'] !== [], 'aucune règle du châssis lue dans '.self::COMPONENT.' et '.self::REBOOT.', un vert de chassis, rowBackground et utilSpacing ne prouverait rien : reprendre getChassis()');
		$this->assertRatchet('chassis', self::CHASSIS, 'propriété qu\'une feuille de package reprend sur un élément que le châssis dessine déjà (classe util-* de component.css, règle à classe ou th de reboot.css), à retirer, ou à porter par une variante du composant dans component.css présentée sur Design.u.php, ou par une propriété personnalisée que le composant lit');
	}

	public function testRowBackground(): void {
		$this->assertRatchet('rowBackground', self::ROW_BACKGROUND, 'fond posé par une feuille de package sur util-table-row, à retirer, ou à porter par une variante de util-table dans reboot.css présentée sur Design.u.php');
	}

	public function testUtilSpacing(): void {
		$this->assertRatchet('utilSpacing', self::UTIL_SPACING, 'marge ou espacement posé par une feuille de package sur une classe util-* du design system, à retirer, ou à porter par une variante du composant dans component.css présentée sur Design.u.php');
	}

	public function testSmallText(): void {
		if($this->assertDecorPresent($this->root > 0.0, 'une taille de racine en px sur html, hors @media, dans '.self::REBOOT.' : sans elle, aucune taille en pixels ne se rapporte au plancher, reprendre getRootSize()') === FALSE) {
			return;
		}
		$this->assertEquals(13.0, $this->root, 'la racine lue dans '.self::REBOOT.' hors @media n\'est plus de 13px (reboot.css fait foi : si le changement est voulu, corriger ce contrôle et reprendre SMALL_TEXT dans le même lot, une taille en pixels se rapportant au plancher par elle)');
		$this->assertRatchet('smallText', self::SMALL_TEXT, 'texte sous 0,9 rem (skill ui, « Aucun texte ne descend sous 0,9 rem »), à remonter à 0.9rem au moins, ou à 12px pour une taille en pixels');
	}

	public function testBareValue(): void {
		$this->assertRatchet('bareValue', self::BARE_VALUE, 'z-index, max-width ou grid-template-columns écrit en dur, à passer par un token var(--…) de reboot.css, à y déclarer d\'abord s\'il manque');
	}

}
?>
