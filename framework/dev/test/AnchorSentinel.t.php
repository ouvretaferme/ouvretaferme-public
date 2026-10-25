<?php
namespace dev;

/**
 * Une ancre `chemin:ligne` d'un fichier de règles cite entre guillemets ce qu'elle pointe, une clé ou
 * un nom de méthode, et la ligne citée le porte encore.
 *
 * Une ancre ne meurt pas en dépassant la fin de son fichier, elle meurt par son contenu : le fichier
 * cité bouge sous elle, et le numéro désigne alors une autre ligne sans que rien le signale.
 * `framework/core/Api.php` a été cité à 446, 572 et 640 quand `acceptCreate` vivait vingt lignes plus
 * bas. Un numéro seul ne se retrouve pas ; un mot cité, si (conseil des gardiens,
 * `2026-09-29-artefact-derive-se-perime`, piste 3).
 *
 * La population est déclarée, pour qu'un compte d'ancres se compare à un autre : les fichiers de
 * règles de `POPULATION` que git suit, lus sur le disque. Le motif est un chemin à extension suivi de
 * `:<ligne>` ou `:<ligne>-<ligne>`, tout entier entre accents graves. Les mots cités sont ceux de la
 * phrase de l'ancre, entre accents graves ou entre guillemets français, hors chemins.
 *
 * Deux règles :
 * - `quoted` : l'ancre cite au moins un mot dans sa phrase, en cliquet par fichier de règles ;
 * - `alive` : une ancre qui cite se résout, et la ligne citée porte un des mots, déclaré par ancre,
 *   `<fichier de règles>:<chemin cité>:<ligne>`, puisque chaque écart a sa cause : un compte
 *   laisserait passer une ancre réparée échangée contre une ancre morte. Chaque écart dit sa
 *   cause, et à qui il s'adresse :
 *   - « décalage de N, la nouvelle ligne est L », « ligne morte », « chemin renommé par <sha> »,
 *     « chemin supprimé par <sha> », « chemin faux », « ambiguë » : à l'auteur de la règle, qui
 *     réécrit l'ancre ;
 *   - « la référence vit hors de git » : à l'auteur de la règle, qui recopie l'appui dans le fichier
 *     de règles, une ancre vers `.claude/state` n'étant juste que sur les postes qui portent le
 *     chantier ;
 *   - « chemin absent de l'amont » : à qui n'a pas poussé le fichier cité.
 *
 * Un chemin partiel (`selling/lib/Item.l.php`) se résout par le disque, parmi les fichiers que le
 * `.gitignore` du dépôt n'écarte pas ; son appartenance à l'amont se lit dans `origin/main`, jamais
 * l'une à la place de l'autre. Les lignes citées se lisent sur le disque, comme les règles qui les
 * citent : les deux côtés d'une ancre se jugent au même endroit. Un chemin que le disque ne porte pas se cherche d'abord dans
 * l'historique de `origin/main`, puis par son contenu : l'ordre compte, une recherche par le contenu
 * menée d'abord trouvant le fichier d'un renommage en attribuant la mauvaise cause.
 *
 * Tout verdict d'amont suppose que git a répondu. Sans `origin/main`, `git cat-file -e` rend la même
 * erreur sur un chemin jamais poussé, et chaque ancre se lirait « absent de l'amont » : les méthodes
 * qui lisent l'amont, placées en dernier, s'arrêtent alors en non applicable.
 *
 * Ce qu'elle ne voit pas : une ancre écrite hors accents graves, un numéro de ligne sans chemin, une
 * ancre dont un mot cité est assez courant pour se trouver sur n'importe quelle ligne. La recherche par
 * le contenu ne parcourt que les fichiers de même nom de base que le chemin cité.
 *
 * Lancement : dev/test package=dev file=AnchorSentinel
 */
class AnchorSentinelTest extends SentinelTest {

	const RULE_QUOTED = 'quoted';
	const RULE_ALIVE = 'alive';

	/**
	 * Les fichiers de règles lus, en motifs de `git ls-files`, où `*` traverse les répertoires.
	 */
	const POPULATION = ['CLAUDE.md', '*/CLAUDE.md', '.claude/protocole.md', '.claude/cas-vus/*.md', '.claude/skills/*/SKILL.md'];

	const ANCHOR = '/`((?:[\w.@-]+\/)*[\w.@-]+\.[A-Za-z0-9]+):(\d+)(?:-(\d+))?`/u';

	/**
	 * Les mots de code trop courants pour désigner une ligne.
	 */
	const COMMON = ['this', 'self', 'static', 'function', 'return', 'public', 'private', 'protected', 'array', 'string', 'TRUE', 'FALSE', 'NULL', 'true', 'false', 'null'];

	/**
	 * Les sortes d'artefacts dérivés du source, que la recherche par le contenu écarte : une copie
	 * y serait préférée à l'original sans le signaler. Le `.gitignore` du dépôt écarte déjà
	 * `.claude/state/`, cette liste attrape ce qui est rangé ailleurs.
	 */
	const DERIVED = ['*.patch', '*.diff', '*/diff/*', '*.txt', '*.log', '*/etat.md', 'etat.md'];

	/**
	 * Ancres sans mot cité, par fichier de règles, au 2026-10-16 : l'existant d'avant la règle, qui ne
	 * fait que descendre, chaque ancre réécrite avec son mot.
	 */
	const QUOTED = [
		'.claude/cas-vus/citer-une-ligne-sans-l-avoir-lue.md' => 1,
		'.claude/cas-vus/conventions-de-l-interface.md' => 2,
		'.claude/cas-vus/http-referer.md' => 4,
		'.claude/cas-vus/l-objet-recu-est-il-complet.md' => 1,
		'.claude/cas-vus/la-fusion-au-jardin-n-est-pas-un-dessin.md' => 1,
		'.claude/cas-vus/le-droit-l-etat-et-les-deux-questions.md' => 1,
		'.claude/cas-vus/le-proprietaire-d-un-fichier-d-inventaire-n-est-pas-celui-de-ses-hunks.md' => 1,
		'.claude/cas-vus/on-ajoute-a-une-liste-par-un-bouton-a-droite-de-son-titre.md' => 1,
		'.claude/cas-vus/trois-facons-pour-un-temoin-de-se-taire-toutes-vues-le-meme-jour.md' => 1,
		'.claude/cas-vus/un-cadre-de-planche-sous-la-bascule-du-rail.md' => 1,
		'.claude/cas-vus/un-chemin-faux-rend-le-vide-sans-le-dire.md' => 1,
		'.claude/cas-vus/un-cliquet-ne-se-verifie-jamais-depuis-le-seul-cote-qu-on-corrige.md' => 1,
		'.claude/cas-vus/un-clone-qui-emporte-un-bouton-desactive.md' => 1,
		'.claude/cas-vus/un-comptage-de-la-base-de-dev-pris-pour-une-mesure-du-produit.md' => 1,
		'.claude/cas-vus/un-controle-qui-s-accusait-de-ce-qu-il-donnait-a-lire.md' => 1,
		'.claude/cas-vus/un-formulaire-affiche-qui-refusait-tout-montant-entier.md' => 1,
		'.claude/cas-vus/un-motif-precis-qui-enjambe-la-frontiere-du-balisage-et-du-code.md' => 1,
		'.claude/cas-vus/un-numero-de-ligne-sans-son-sha.md' => 2,
		'.claude/cas-vus/un-rollback-qui-emporte-la-transaction-de-son-appelant.md' => 11,
		'.claude/cas-vus/une-cle-de-localstorage-n-est-pas-propre-a-une-page.md' => 1,
		'.claude/cas-vus/une-consigne-lue-sans-le-lieu-ou-elle-s-applique.md' => 1,
		'.claude/cas-vus/une-interface-qui-a-besoin-d-etre-expliquee-n-est-pas-finie.md' => 1,
		'.claude/cas-vus/une-mesure-juste-dont-la-regle-derivee-est-fausse.md' => 2,
		'.claude/cas-vus/une-page-de-recette-debordait-a-360-px-sans-que-la-page-deborde.md' => 1,
		'.claude/cas-vus/une-transition-figee-dans-un-onglet-masque.md' => 1,
		'.claude/protocole.md' => 4,
		'.claude/skills/recette/SKILL.md' => 1,
		'.claude/skills/ui/SKILL.md' => 7,
		'CLAUDE.md' => 5,
		'base/structure/CLAUDE.md' => 1,
		'framework/dev/test/CLAUDE.md' => 2,
		'production/toolbox/CLAUDE.md' => 1,
	];

	/**
	 * Ancres qui ne se résolvent plus, au 2026-10-16, avec leur cause : l'existant d'avant la règle,
	 * qui se retire ancre par ancre à mesure qu'elles se réécrivent.
	 */
	const ALIVE = [
		'.claude/cas-vus/chaine-d-erreur.md:ajax.js:26' => 'décalage',
		'.claude/cas-vus/chaine-d-erreur.md:framework/core/Action.php:456' => 'décalage',
		'.claude/cas-vus/chaine-d-erreur.md:framework/core/Module.php:5275' => 'décalage',
		'.claude/cas-vus/chaine-d-erreur.md:lime.js:1013' => 'décalage',
		'.claude/cas-vus/chaine-d-erreur.md:lime.js:1100' => 'décalage',
		'.claude/cas-vus/classe-js-de-premier-niveau-dupliquee.md:ajax.js:1344' => 'décalage',
		'.claude/cas-vus/collection-filter-destructif.md:production/series/ui/Task.u.php:1254' => 'décalage',
		'.claude/cas-vus/compter-comment-le-depot-fait-deja.md:product/lib/Offer.l.php:974' => 'décalage',
		'.claude/cas-vus/compter-comment-le-depot-fait-deja.md:selling/lib/Customer.l.php:572' => 'décalage',
		'.claude/cas-vus/compter-comment-le-depot-fait-deja.md:selling/module/Item.m.php:517' => 'décalage',
		'.claude/cas-vus/convertproperties-exige-le-corps-complet.md:framework/core/Api.php:426' => 'décalage',
		'.claude/cas-vus/corriger-la-cause-avant-le-symptome.md:accounting/cash/lib/Suggestion.l.php:982' => 'décalage',
		'.claude/cas-vus/des-boutons-au-pied-d-un-panneau-court.md:ouvretaferme/design/ui/Design.u.php:2608' => 'décalage',
		'.claude/cas-vus/des-boutons-au-pied-d-un-panneau-court.md:selling/ui/Item.u.php:973' => 'décalage',
		'.claude/cas-vus/donnees-de-production.md:internal/aggregate/lib/CultivationProjectionAi.l.php:49' => 'décalage',
		'.claude/cas-vus/figer-le-lot-suivant.md:.claude/push/coupe.py:204' => 'décalage',
		'.claude/cas-vus/flags-e-pour-rattraper-un-modele.md:framework/dev/page/module.p.php:57' => 'décalage',
		'.claude/cas-vus/formulaire-ajax-et-ajax-query.md:framework/util/static/js/form.js:26' => 'décalage',
		'.claude/cas-vus/id-de-tiers-partage-entre-structures.md:accounting/thirdParty/lib/ThirdParty.l.php:669' => 'décalage',
		'.claude/cas-vus/id-de-tiers-partage-entre-structures.md:framework/core/Module.php:2232' => 'décalage',
		'.claude/cas-vus/id-en-sous-selection.md:framework/core/Module.php:2605' => 'décalage',
		'.claude/cas-vus/l-ecran-entier-pas-le-perimetre-du-diff.md:base/structure/ui/StructureCommunity.u.php:347' => 'décalage',
		'.claude/cas-vus/la-chaine-de-build.md:Api.php:404' => 'décalage',
		'.claude/cas-vus/la-chaine-de-build.md:commercialisation/product/module/Product.e.php:290' => 'ligne morte',
		'.claude/cas-vus/la-chaine-de-build.md:commercialisation/shop/module/Shop.e.php:388' => 'décalage',
		'.claude/cas-vus/la-chaine-de-build.md:framework/core/Fail.php:407' => 'décalage',
		'.claude/cas-vus/la-chaine-de-build.md:production/series/module/Task.e.php:447' => 'décalage',
		'.claude/cas-vus/la-structure-neuve-et-le-volume-deux-scenes-sautees.md:base/structure/page/community.p.php:235' => 'décalage',
		'.claude/cas-vus/le-css-d-un-package-s-accumule-en-navigation-ajax.md:journal/static/css/operation.css:170' => 'décalage',
		'.claude/cas-vus/le-css-d-un-package-s-accumule-en-navigation-ajax.md:ouvretaferme/analyze/ui/France.u.php:108' => 'chemin renommé',
		'.claude/cas-vus/le-design-system-se-cherche-par-besoin-pas-par-nom.md:reboot.css:1297' => 'décalage',
		'.claude/cas-vus/le-droit-l-etat-et-les-deux-questions.md:base/structure/module/Structure.e.php:338' => 'décalage',
		'.claude/cas-vus/le-droit-l-etat-et-les-deux-questions.md:commercialisation/selling/module/Sale.e.php:279' => 'décalage',
		'.claude/cas-vus/le-droit-l-etat-et-les-deux-questions.md:framework/core/Data.php:2011' => 'décalage',
		'.claude/cas-vus/le-droit-l-etat-et-les-deux-questions.md:framework/core/Data.php:2025' => 'décalage',
		'.claude/cas-vus/le-proprietaire-d-un-fichier-d-inventaire-n-est-pas-celui-de-ses-hunks.md:diff-1.patch:3050' => 'artefact dérivé',
		'.claude/cas-vus/le-test-prouve-que-sa-donnee-est-la.md:ProfileAi.t.php:175' => 'ligne morte',
		'.claude/cas-vus/les-deux-assertions-d-une-sentinelle-bidirectionnelle-se-reparent-l-une-contre-l-autre.md:.claude/state/planning/L5a/reprise-2/recoupe.md:29' => 'hors de git',
		'.claude/cas-vus/les-deux-assertions-d-une-sentinelle-bidirectionnelle-se-reparent-l-une-contre-l-autre.md:production/toolbox/toolbox.yml:252' => 'décalage',
		'.claude/cas-vus/les-structures-de-test-que-le-produit-ne-sait-pas-supprimer.md:base/structure/lib/Structure.l.php:812' => 'décalage',
		'.claude/cas-vus/menu-deroulant-et-stopimmediatepropagation.md:lime.js:1567' => 'décalage',
		'.claude/cas-vus/menu-deroulant-et-stopimmediatepropagation.md:lime.js:2874' => 'décalage',
		'.claude/cas-vus/page-configure-en-get.md:framework/core/Page.php:435' => 'décalage',
		'.claude/cas-vus/pieges-des-tests.md:Data.php:743' => 'décalage',
		'.claude/cas-vus/pieges-des-tests.md:base/structure/module/Structure.e.php:164' => 'décalage',
		'.claude/cas-vus/pieges-des-tests.md:framework/core/Module.php:1900' => 'décalage',
		'.claude/cas-vus/pieges-des-tests.md:framework/core/Test.php:20' => 'décalage',
		'.claude/cas-vus/pieges-des-tests.md:framework/dev/lib/Test.l.php:68' => 'décalage',
		'.claude/cas-vus/replacecheck-ou-addaftercheck.md:commercialisation/quotation/module/Pause.e.php:34' => 'décalage',
		'.claude/cas-vus/replacecheck-ou-addaftercheck.md:framework/core/Data.php:2159' => 'décalage',
		'.claude/cas-vus/replacecheck-ou-addaftercheck.md:framework/core/Module.php:3143' => 'décalage',
		'.claude/cas-vus/setcallback-xxx-check-sur-une-propriete-non-construite.md:framework/core/Module.php:5755' => 'décalage',
		'.claude/cas-vus/trames-d-ecritures-ce-que-le-code-ne-dit-pas.md:lime.js:1129' => 'décalage',
		'.claude/cas-vus/trois-facons-pour-un-temoin-de-se-taire-toutes-vues-le-meme-jour.md:controle.py:539' => 'ambiguë',
		'.claude/cas-vus/trois-pieges-du-plan-d-assolement-sauves-du-retrait-des-commentaires.md:production/series/static/js/place.js:4752' => 'décalage',
		'.claude/cas-vus/un-cadre-de-planche-sous-la-bascule-du-rail.md:reboot.css:1387' => 'décalage',
		'.claude/cas-vus/un-cadre-de-planche-sous-la-bascule-du-rail.md:template-structure.css:446' => 'décalage',
		'.claude/cas-vus/un-comptage-de-la-base-de-dev-pris-pour-une-mesure-du-produit.md:lib/ContractProduct.l.php:504' => 'décalage',
		'.claude/cas-vus/un-compte-pris-pour-son-propre-temoin.md:CLAUDE.md:212' => 'ligne morte',
		'.claude/cas-vus/un-compte-pris-pour-son-propre-temoin.md:protocole.md:33' => 'décalage',
		'.claude/cas-vus/un-decor-seme-sous-un-cache.md:base/dashboard/lib/Dashboard.l.php:336' => 'décalage',
		'.claude/cas-vus/un-defaut-que-l-environnement-de-developpement-affiche-et-que-la-production-tait.md:framework/core/Action.php:230' => 'décalage',
		'.claude/cas-vus/un-defaut-que-l-environnement-de-developpement-affiche-et-que-la-production-tait.md:framework/core/Route.php:243' => 'décalage',
		'.claude/cas-vus/un-defaut-que-l-environnement-de-developpement-affiche-et-que-la-production-tait.md:framework/core/Template.php:11' => 'décalage',
		'.claude/cas-vus/un-fail-sans-libelle-surligne-sans-parler.md:lime.js:1128' => 'ligne morte',
		'.claude/cas-vus/un-fail-sans-libelle-surligne-sans-parler.md:lime.js:1130' => 'décalage',
		'.claude/cas-vus/un-fichier-neuf-qu-aucune-sentinelle-n-a-jamais-lu.md:framework/dev/lib/Sentinel.l.php:42' => 'décalage',
		'.claude/cas-vus/un-fichier-neuf-qu-aucune-sentinelle-n-a-jamais-lu.md:framework/dev/lib/Sentinel.l.php:97' => 'décalage',
		'.claude/cas-vus/un-lecteur-a-froid-ne-juge-pas-un-geste.md:ouvretaferme/main/static/css/component.css:4055' => 'décalage',
		'.claude/cas-vus/un-motif-precis-qui-enjambe-la-frontiere-du-balisage-et-du-code.md:framework/core/Instruction.php:274' => 'décalage',
		'.claude/cas-vus/un-motif-precis-qui-enjambe-la-frontiere-du-balisage-et-du-code.md:framework/util/static/js/ajax.js:246' => 'décalage',
		'.claude/cas-vus/un-motif-precis-qui-enjambe-la-frontiere-du-balisage-et-du-code.md:ouvretaferme/main/view/Structure.php:199' => 'décalage',
		'.claude/cas-vus/un-numero-de-ligne-sans-son-sha.md:journal/static/css/operation.css:170' => 'décalage',
		'.claude/cas-vus/un-numero-de-ligne-sans-son-sha.md:product/lib/Offer.l.php:974' => 'décalage',
		'.claude/cas-vus/un-numero-de-ligne-sans-son-sha.md:selling/module/Item.m.php:517' => 'ligne morte',
		'.claude/cas-vus/un-perimetre-derive-des-repertoires-du-lot.md:commercialisation/selling/page/configure/lot2RecetteAi.p.php:2241' => 'décalage',
		'.claude/cas-vus/un-perimetre-derive-des-repertoires-du-lot.md:commercialisation/selling/ui/Dashboard.u.php:228' => 'décalage',
		'.claude/cas-vus/un-plancher-de-taille-restreint-puis-rendu-general.md:component.css:130' => 'décalage',
		'.claude/cas-vus/un-rollback-qui-emporte-la-transaction-de-son-appelant.md:framework/core/Fail.php:298' => 'décalage',
		'.claude/cas-vus/un-rollback-qui-emporte-la-transaction-de-son-appelant.md:framework/core/Module.php:1893' => 'décalage',
		'.claude/cas-vus/un-rollback-qui-emporte-la-transaction-de-son-appelant.md:framework/core/Module.php:1914' => 'décalage',
		'.claude/cas-vus/un-rouge-relaye-par-sa-paraphrase.md:internal/data/test/MigrationFormatSentinelAi.t.php:128' => 'décalage',
		'.claude/cas-vus/une-cle-de-localstorage-n-est-pas-propre-a-une-page.md:amap/2-contrat-souscription/genere.py:852' => 'ambiguë',
		'.claude/cas-vus/une-cle-de-localstorage-n-est-pas-propre-a-une-page.md:projet.md:135' => 'ambiguë',
		'.claude/cas-vus/une-closure-passee-a-create-ou-update-eteint-la-vue.md:framework/core/Module.php:5480' => 'décalage',
		'.claude/cas-vus/une-collection-n-a-qu-un-curseur.md:framework/core/Data.php:9' => 'décalage',
		'.claude/cas-vus/une-contrainte-de-fenetre-sans-origine-citable.md:.claude/skills/push/SKILL.md:301' => 'décalage',
		'.claude/cas-vus/une-garde-d-element-ne-protege-pas-une-colonne.md:framework/core/Route.php:236' => 'décalage',
		'.claude/cas-vus/une-mesure-juste-citee-apres-coup.md:reboot.css:1549' => 'ligne morte',
		'.claude/cas-vus/une-mesure-juste-dont-la-regle-derivee-est-fausse.md:base/structure/static/js/communityFarmsAi.js:28' => 'ligne morte',
		'.claude/cas-vus/une-mesure-juste-dont-la-regle-derivee-est-fausse.md:commercialisation/amap/static/js/subscription.js:36' => 'décalage',
		'.claude/cas-vus/une-mesure-juste-dont-la-regle-derivee-est-fausse.md:framework/util/static/js/lime.js:1586' => 'décalage',
		'.claude/cas-vus/une-mesure-qui-change-apres-un-geste.md:framework/core/Data.php:1481' => 'décalage',
		'.claude/cas-vus/une-mesure-qui-change-apres-un-geste.md:framework/core/Module.php:5657' => 'décalage',
		'.claude/cas-vus/une-mesure-vraie-qui-repond-a-une-autre-question.md:commercialisation/selling/lib/CompositionProjectionAi.l.php:235' => 'décalage',
		'.claude/cas-vus/une-planche-qui-porte-31-cadratins-dont-le-relecteur-en-voit-trois.md:commun.py:344' => 'décalage',
		'.claude/cas-vus/une-production-cassee-par-un-commit-qui-appelle-ce-qu-il-n-emporte-pas.md:base/structure/lib/Structure.l.php:440' => 'décalage',
		'.claude/cas-vus/une-production-cassee-par-un-commit-qui-appelle-ce-qu-il-n-emporte-pas.md:ouvretaferme/main/page/index.p.php:57' => 'décalage',
		'.claude/cas-vus/une-question-metier-portee-a-vincent-au-lieu-du-chantier.md:production/toolbox/lib/Rain.l.php:59' => 'décalage',
		'.claude/cas-vus/une-sentinelle-qui-refuse-la-forme-sans-shell.md:ouvretaferme/learning/test/DateEnrollment.t.php:1548' => 'décalage',
		'.claude/cas-vus/une-sonde-qui-ne-lit-pas-la-valeur-la-ou-le-produit-la-lit.md:ouvretaferme/design/test/DesignSystemSentinelAi.t.php:266' => 'décalage',
		'.claude/cas-vus/une-table-que-le-schema-ne-declare-plus-fait-refuser-un-effacement-en-cascade.md:base/structure/lib/FixtureAi.l.php:938' => 'décalage',
		'.claude/cas-vus/une-tournure-sujet-deux-points-ce-qui-lui-arrive.md:ouvretaferme/main/ui/Design.u.php:403' => 'ligne morte',
		'.claude/cas-vus/une-transition-figee-dans-un-onglet-masque.md:Plan.u.php:118' => 'ambiguë',
		'.claude/cas-vus/une-transition-figee-dans-un-onglet-masque.md:production/map/ui/Plan.u.php:116' => 'décalage',
		'.claude/protocole.md:Deposit.u.php:365' => 'décalage',
		'.claude/protocole.md:X.l.php:671' => 'absent de l\'amont',
		'.claude/protocole.md:framework/dev/lib/Sentinel.l.php:97' => 'décalage',
		'.claude/protocole.md:framework/util/static/js/lime.js:752' => 'décalage',
		'.claude/protocole.md:internal/data/test/MigrationFormatSentinelAi.t.php:128' => 'décalage',
		'.claude/protocole.md:ouvretaferme/main/static/css/component.css:2659' => 'décalage',
		'.claude/protocole.md:ouvretaferme/main/static/css/reboot.css:1187' => 'décalage',
		'.claude/skills/codeur/SKILL.md:framework/core/Module.php:1925' => 'décalage',
		'.claude/skills/fec-importeur/SKILL.md:FinancialYear.e.php:616' => 'décalage',
		'.claude/skills/fec-importeur/SKILL.md:accounting/asset/lib/Asset.l.php:1717' => 'décalage',
		'.claude/skills/fec-importeur/SKILL.md:vat/lib/Adar.l.php:129' => 'décalage',
		'.claude/skills/gardien/SKILL.md:projet.md:135' => 'ambiguë',
		'.claude/skills/pull/SKILL.md:.claude/push/main.py:389' => 'décalage',
		'.claude/skills/pull/SKILL.md:framework/dev/lib/Module.l.php:191' => 'décalage',
		'.claude/skills/pull/SKILL.md:framework/dev/page/module.p.php:57' => 'décalage',
		'.claude/skills/push/SKILL.md:.claude/push/main.py:313' => 'décalage',
		'.claude/skills/push/SKILL.md:framework/dev/lib/Sentinel.l.php:42' => 'décalage',
		'.claude/skills/push/SKILL.md:framework/dev/lib/Sentinel.l.php:97' => 'décalage',
		'.claude/skills/ui/SKILL.md:framework/util/ui/Form.u.php:736' => 'décalage',
		'.claude/skills/ui/SKILL.md:learning/ui/Theme.u.php:104' => 'décalage',
		'.claude/skills/ui/SKILL.md:ouvretaferme/main/view/Root.php:60' => 'décalage',
		'.claude/skills/ui/SKILL.md:product/ui/Product.u.php:746' => 'décalage',
		'.claude/skills/ui/SKILL.md:reboot.css:1294' => 'décalage',
		'.claude/skills/ui/SKILL.md:reboot.css:1322' => 'décalage',
		'.claude/skills/ui/SKILL.md:reboot.css:1325' => 'décalage',
		'.claude/skills/ui/SKILL.md:selling/ui/Invoice.u.php:1376' => 'décalage',
		'CLAUDE.md:base/structure/Structure.s.php:118' => 'décalage',
		'CLAUDE.md:base/structure/lib/Structure.l.php:581' => 'décalage',
		'CLAUDE.md:framework/core/Api.php:567' => 'décalage',
		'CLAUDE.md:framework/core/Asset.php:104' => 'décalage',
		'CLAUDE.md:framework/core/Fail.php:399' => 'décalage',
		'CLAUDE.md:framework/dev/lib/Module.l.php:906' => 'décalage',
		'CLAUDE.md:framework/dev/test/ErrorSecret.t.php:73' => 'ligne morte, à tort : la ligne porte testTheBuiltErrorCarriesNoSecretInItsUrls, et le mot data-ajax-form de la phrase est celui d\'une autre parenthèse ; l\'ancre se réécrit avec le nom du test',
		'CLAUDE.md:framework/util/static/js/ajax.js:27' => 'décalage',
		'CLAUDE.md:journal/static/css/operation.css:182' => 'décalage',
		'CLAUDE.md:lime.js:2871' => 'décalage',
		'CLAUDE.md:product/lib/Product.l.php:255' => 'décalage',
		'CLAUDE.md:production/series/static/js/task.js:264' => 'décalage',
		'CLAUDE.md:selling/lib/Customer.l.php:924' => 'décalage',
		'base/notification/CLAUDE.md:accounting/invoicing/lib/Notification.l.php:64' => 'décalage',
		'base/structure/CLAUDE.md:framework/core/Module.php:6076' => 'décalage',
		'base/structure/CLAUDE.md:module/Member.e.php:111' => 'décalage',
		'base/structure/CLAUDE.md:module/Structure.e.php:1169' => 'décalage',
		'base/structure/CLAUDE.md:module/Structure.e.php:594' => 'décalage',
		'base/structure/CLAUDE.md:structure.yml:306' => 'décalage',
		'base/structure/CLAUDE.md:ui/Structure.u.php:501' => 'décalage',
		'commercialisation/shop/CLAUDE.md:lib/Sale.l.php:465' => 'décalage',
		'commercialisation/shop/CLAUDE.md:page/public.p.php:381' => 'ligne morte',
		'commercialisation/shop/CLAUDE.md:static/js/auditAi.js:396' => 'décalage',
		'framework/dev/test/CLAUDE.md:base/structure/test/CommunityFeatureSentinelAi.t.php:69' => 'ligne morte',
		'production/toolbox/CLAUDE.md:base/structure/module/Structure.e.php:1111' => 'décalage',
		'production/toolbox/CLAUDE.md:page/catalog.p.php:23' => 'décalage',
		'production/toolbox/CLAUDE.md:ui/Treatment.u.php:413' => 'décalage',
	];

	/**
	 * Les ancres relevées.
	 * @var array<int, array{file: string, line: int, path: string, target: int, end: int, words: string[]}>
	 */
	private array $anchors = [];

	/** @var array<string, array<string, TRUE>> les listes de git, lues une fois chacune */
	private array $lists = [];

	/** @var array<string, string>|null les chemins retirés de `origin/main`, avec le dernier commit qui l'a fait */
	private ?array $deleted = NULL;

	/** @var array<string, string[]> */
	private array $lines = [];

	public function init(): void {

		$files = SentinelLib::getGitPaths('ls-files', '--', ...self::POPULATION);

		if($files === NULL) {
			$this->notApplicable('git n\'a pas listé les fichiers de règles');
		}

		foreach($files as $file) {

			$path = SentinelLib::getRoot().'/'.$file;

			if(is_file($path) === FALSE) {
				continue;
			}

			foreach(self::findAnchors(file_get_contents($path)) as $anchor) {
				$this->anchors[] = ['file' => $file] + $anchor;
			}

		}

	}

	public function testFindAnchors(): void {

		$content = <<<'MD'
Le nom du fail pilote l'affichage (`lime.js:1100`) : `propriete.check` est rattaché au champ ; il se crée tout seul (`lime.js:1013`).

Une ancre seule `framework/core/Module.php:5275`.
```
`ignored/In.php:3` dans un bloc de code.
```
Une plage, `product/lib/Offer.l.php:974-985` écrit `$propertiesExclude` puis « Gardez la ligne » ; ailleurs `Autre.php:1` cite `Voisin::method()`.
- Un chemin seul `Customer.u.php` ne compte pas comme mot de `selling/ui/Customer.u.php:368`.
- Une phrase coupée par le retour à la ligne, `buildIndex()` d'abord,
puis son ancre (`framework/core/Data.php:120`).
- « à 22h10 j'ai lu ceci dans `X.l.php:671` », clé `paniers-L0c-saisie-v8`.
- Il émet un événement (`base/A.l.php:4`) auquel répond `observe()` (`base/B.o.php:6`), puis `:12`.
- `base/C.l.php:9` écrit `firstWord`, et la déclaration (`base/D.l.php:3`) rend `secondWord`.
MD;

		$this->assertEquals(
			[
				[1, 'lime.js', 1100, 1100, ['propriete.check']],
				[1, 'lime.js', 1013, 1013, []],
				[3, 'framework/core/Module.php', 5275, 5275, []],
				[7, 'product/lib/Offer.l.php', 974, 985, ['Gardez la ligne', '$propertiesExclude', 'propertiesExclude']],
				[7, 'Autre.php', 1, 1, ['Voisin::method()', 'function method(', 'Voisin', 'method']],
				[8, 'selling/ui/Customer.u.php', 368, 368, []],
				[10, 'framework/core/Data.php', 120, 120, ['buildIndex()', 'buildIndex']],
				[11, 'X.l.php', 671, 671, ['paniers-L0c-saisie-v8']],
				[12, 'base/A.l.php', 4, 4, []],
				[12, 'base/B.o.php', 6, 6, ['observe()', 'observe']],
				[13, 'base/C.l.php', 9, 9, ['firstWord']],
				[13, 'base/D.l.php', 3, 3, ['secondWord']],
			],
			array_map(fn(array $anchor) => [$anchor['line'], $anchor['path'], $anchor['target'], $anchor['end'], $anchor['words']], self::findAnchors($content)),
			'une ancre par chemin à extension suivi d\'une ligne, mots lus dans sa seule phrase, retours à la ligne compris, hors chemins, numéros seuls et blocs de code, chaque mot à l\'ancre la plus proche, clés entières'
		);

	}

	public function testFindLine(): void {

		$lines = ['<?php', 'class A {', '', 'function acceptCreate() {', '}', 'function other() {', '}'];

		$this->assertEquals(4, self::findLine($lines, 4, 4, ['acceptCreate']), 'la ligne citée porte le mot');
		$this->assertEquals(4, self::findLine($lines, 2, 2, ['acceptCreate']), 'décalé de 2 vers le bas');
		$this->assertEquals(4, self::findLine($lines, 6, 6, ['acceptCreate']), 'décalé de 2 vers le haut');
		$this->assertEquals(3, self::findLine($lines, 3, 5, ['acceptCreate']), 'un mot dans l\'intervalle rend la ligne citée');
		$this->assertEquals(NULL, self::findLine($lines, 4, 4, ['acceptUpdate']), 'ligne morte : le mot n\'est plus dans le fichier');
		$this->assertEquals(4, self::findLine($lines, 7, 7, ['function', 'acceptCreate']), 'le plus long des mots désigne, pas le plus proche');
		$this->assertEquals(4, self::findLine($lines, 40, 40, ['acceptCreate']), 'une ligne citée au-delà de la fin d\'un fichier qui a maigri se retrouve aussi');

	}

	public function testUniformShift(): void {

		$diagnoses = self::markUniformShifts([
			['file' => 'CLAUDE.md', 'diagnosis' => ['cause' => 'décalage de +20, la nouvelle ligne est 466', 'path' => 'framework/core/Api.php', 'shift' => 20]],
			['file' => 'CLAUDE.md', 'diagnosis' => ['cause' => 'décalage de +20, la nouvelle ligne est 592', 'path' => 'framework/core/Api.php', 'shift' => 20]],
			['file' => 'CLAUDE.md', 'diagnosis' => ['cause' => 'décalage de +3, la nouvelle ligne est 12', 'path' => 'framework/core/Api.php', 'shift' => 3]],
			['file' => 'CLAUDE.md', 'diagnosis' => ['cause' => 'ligne morte']],
		]);

		$this->assertEquals(
			['décalage uniforme sur 2 ancres de +20, la nouvelle ligne est 466', 'décalage uniforme sur 2 ancres de +20, la nouvelle ligne est 592', 'décalage de +3, la nouvelle ligne est 12', 'ligne morte'],
			array_map(fn(array $diagnosis) => $diagnosis['diagnosis']['cause'], $diagnoses),
			'deux ancres décalées du même nombre vers le même fichier se disent uniformes, la troisième non'
		);

	}

	public function testFindRename(): void {

		$output = ['M	accounting/taxReturn/lib/Control.l.php', 'R058	accounting/overview/ui/TaxReturnEntry.u.php	accounting/taxReturn/ui/Entry.u.php', 'D	accounting/overview/ui/Other.u.php'];

		$this->assertEquals('accounting/taxReturn/ui/Entry.u.php', self::findRename($output, 'accounting/overview/ui/TaxReturnEntry.u.php'), 'chemin complet renommé');
		$this->assertEquals('accounting/taxReturn/ui/Entry.u.php', self::findRename($output, 'overview/ui/TaxReturnEntry.u.php'), 'chemin partiel renommé');
		$this->assertEquals(NULL, self::findRename($output, 'accounting/overview/ui/Other.u.php'), 'supprimé sans renommage');

	}

	public function testAnchorsQuote(): void {

		foreach($this->anchors as $anchor) {

			if($anchor['words'] === []) {
				$this->addFound(self::RULE_QUOTED, $anchor['file'], [$anchor['line']]);
			}

		}

		$this->assertRatchet(self::RULE_QUOTED, self::QUOTED, 'ancre `chemin:ligne` sans mot cité à côté d\'elle : écrire dans sa phrase, plus près d\'elle que de toute autre ancre, la clé, la méthode ou la ligne qu\'elle pointe, entre accents graves, qui la retrouve quand le fichier bouge');

	}

	/**
	 * Les méthodes qui suivent lisent l'amont : sans lui, la première s'arrête en non applicable, et
	 * les suivantes avec elle.
	 */
	public function testAnchorsResolve(): void {

		if(SentinelLib::getGitPaths('rev-parse', '--verify', '--quiet', SentinelLib::getUpstream()) === NULL) {
			$this->notApplicable('git n\'a pas répondu sur origin/main, aucun verdict d\'amont');
		}

		$diagnoses = [];

		foreach($this->anchors as $anchor) {

			if($anchor['words'] === []) {
				continue;
			}

			$diagnosis = $this->diagnose($anchor);

			if($diagnosis !== NULL) {
				$diagnoses[] = $anchor + ['diagnosis' => $diagnosis];
			}

		}

		$diagnoses = self::markUniformShifts($diagnoses);

		foreach($diagnoses as $diagnosis) {
			$this->addFoundKey(self::RULE_ALIVE, $diagnosis['file'], $diagnosis['path'].':'.$diagnosis['target'], $diagnosis['line']);
		}

		$this->assertDeclared(
			self::RULE_ALIVE,
			self::ALIVE,
			'ancre `chemin:ligne` dont la ligne citée ne porte plus aucun mot cité, chaque cause disant qui la corrige'.self::explainDiagnoses($diagnoses, self::ALIVE)
		);

	}

	/**
	 * Une ancre qui cite `Classe::methode()` se reloge sur la définition de la méthode, pas sur la
	 * ligne la plus proche qui nomme la classe.
	 */
	public function testMethodCallFindsItsDefinition(): void {

		$text = 'Une structure insérée sans `StructureLib::create()` (`lib/Structure.l.php:10`) n\'a aucune des lignes filles.';
		preg_match(self::ANCHOR, $text, $match, PREG_OFFSET_CAPTURE);

		$lines = array_fill(0, 40, '');
		$lines[4] = '\\structure\\StructureLib::connectDatabase($eStructure);';
		$lines[27] = '	public static function create(Structure $e, bool $withDefaults = TRUE): void {';

		$this->assertEquals(28, self::findLine($lines, 10, 10, self::getWords($text, $match[0][1])), 'la définition de create() est à la ligne 28, la ligne 5 ne porte que le nom de la classe');

	}

	/**
	 * Le renommage du 2026-09-28 reste dans l'historique de `origin/main` : un témoin que la lecture de
	 * l'historique sait trouver, le même sur chaque poste.
	 */
	public function testRenameWitness(): void {

		$diagnosis = $this->diagnose(['file' => '.claude/cas-vus/un-formulaire-affiche-qui-refusait-tout-montant-entier.md', 'path' => 'accounting/overview/ui/TaxReturnEntry.u.php', 'target' => 17, 'end' => 17, 'words' => ['append']]);

		$this->assertTrue(
			$diagnosis !== NULL and str_starts_with($diagnosis['cause'], 'chemin renommé par 961decde3, la référence vit à accounting/taxReturn/ui/Entry.u.php'),
			'l\'historique doit nommer le renommage de TaxReturnEntry.u.php par 961decde3 : '.($diagnosis['cause'] ?? 'aucun écart rendu')
		);

	}

	/**
	 * Le témoin de la recherche par le contenu est local : `.claude/state/paniers/projet.md` n'existe
	 * que sur les postes qui portent le chantier paniers, et seul il y portait `paniers-L0c-saisie-v8`
	 * le 2026-09-30. Un fichier d'état se réécrit : ailleurs, ou une fois la clé partie, la branche n'a
	 * pas de témoin, ce que la méthode dit au lieu de passer.
	 */
	public function testContentWitness(): void {

		$witness = SentinelLib::getRoot().'/.claude/state/paniers/projet.md';

		if(is_file($witness) === FALSE or str_contains(file_get_contents($witness), 'paniers-L0c-saisie-v8') === FALSE) {
			$this->notApplicable('branche sans témoin ici, .claude/state/paniers/projet.md absent de cet arbre ou sans la clé paniers-L0c-saisie-v8');
		}

		$diagnosis = $this->diagnose(['file' => '.claude/cas-vus/une-cle-de-localstorage-n-est-pas-propre-a-une-page.md', 'path' => 'projet.md', 'target' => 135, 'end' => 135, 'words' => ['paniers-L0c-saisie-v8']]);

		$this->assertTrue(
			$diagnosis !== NULL and str_starts_with($diagnosis['cause'], 'la référence vit hors de git (.claude/state/paniers/projet.md:135,'),
			'le contenu doit résoudre projet.md:135 vers .claude/state/paniers/projet.md, hors de git : '.($diagnosis['cause'] ?? 'aucun écart rendu')
		);

	}

	/**
	 * Les ancres d'un fichier de règles, hors blocs de code. La phrase d'une ancre peut commencer sur
	 * une ligne précédente du même paragraphe : un paragraphe se lit d'un seul tenant, et chaque ancre
	 * garde la ligne qui la porte.
	 *
	 * @return array<int, array{line: int, path: string, target: int, end: int, words: string[]}>
	 */
	private static function findAnchors(string $content): array {

		$anchors = [];

		foreach(self::getParagraphs($content) as [$first, $text, $starts]) {

			if(preg_match_all(self::ANCHOR, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 0) {
				continue;
			}

			foreach($matches as $match) {

				$line = $first;

				foreach($starts as $index => $start) {

					if($start <= $match[0][1]) {
						$line = $first + $index;
					}

				}

				$target = (int)$match[2][0];

				$anchors[] = [
					'line' => $line,
					'path' => $match[1][0],
					'target' => $target,
					'end' => ($match[3][0] === NULL) ? $target : max($target, (int)$match[3][0]),
					'words' => self::getWords($text, $match[0][1]),
				];

			}

		}

		return $anchors;

	}

	/**
	 * Les paragraphes d'un fichier Markdown, chacun sous la forme `[première ligne, texte, débuts]`,
	 * où `débuts` donne la position de chaque ligne dans le texte. Une ligne vide, un titre, un élément
	 * de liste ou de tableau ouvrent un paragraphe ; un bloc de code n'en est pas un.
	 *
	 * @return array<int, array{0: int, 1: string, 2: int[]}>
	 */
	private static function getParagraphs(string $content): array {

		$paragraphs = [];
		$current = NULL;
		$fenced = FALSE;

		foreach(explode("\n", $content) as $index => $text) {

			$trimmed = ltrim($text);

			if(str_starts_with($trimmed, '```')) {
				$fenced = ($fenced === FALSE);
				$current = NULL;
				continue;
			}

			if($fenced or $trimmed === '') {
				$current = NULL;
				continue;
			}

			if($current === NULL or preg_match('/^(#|[-*|>] |\d+\. )/', $trimmed) === 1) {
				$paragraphs[] = [$index + 1, '', []];
				$current = array_key_last($paragraphs);
			}

			$joined = ($paragraphs[$current][1] === '') ? '' : $paragraphs[$current][1].' ';

			$paragraphs[$current][2][] = strlen($joined);
			$paragraphs[$current][1] = $joined.$text;

		}

		return $paragraphs;

	}

	/**
	 * Les mots cités dans la phrase qui porte la position `$offset` : le code entre accents graves,
	 * entier quand il ne porte pas d'espace et par identifiants de quatre caractères au moins, et le
	 * texte entre guillemets français qui ne contient pas l'ancre. Les chemins ne comptent pas, ils
	 * désignent le fichier et non la ligne. Un identifiant ne se coupe pas sur un tiret, ni sur un
	 * point hors d'un appel : `util-block-sticked` découpé en `util` se trouverait sur n'importe quelle
	 * ligne de `component.css`, et ferait passer une ancre morte pour vivante, quand
	 * `BasketManage.loadSummary()` se retrouve par `loadSummary`, que sa définition porte.
	 *
	 * Une phrase finit sur `.`, `;`, `!` ou `?` suivis d'une espace, hors du code, où un point
	 * sépare un objet de sa propriété. Quand elle porte plusieurs ancres, un mot revient à la plus
	 * proche, avant comme après lui, et à égalité à celle qui le suit : les règles écrivent le mot
	 * puis son appui entre parenthèses, ou l'appui puis ce qu'il « écrit ».
	 *
	 * @return string[]
	 */
	private static function getWords(string $text, int $offset): array {

		preg_match_all('/`[^`]+`/u', $text, $codes, PREG_OFFSET_CAPTURE);
		preg_match_all('/«[^»]+»/u', $text, $quotes, PREG_OFFSET_CAPTURE);

		$masked = $text;

		foreach([...$codes[0], ...$quotes[0]] as [$span, $position]) {
			$masked = substr_replace($masked, str_repeat('x', strlen($span)), $position, strlen($span));
		}

		$start = 0;
		$end = strlen($text);

		preg_match_all('/[.;!?](?=\s)/', $masked, $boundaries, PREG_OFFSET_CAPTURE);

		foreach($boundaries[0] as [, $position]) {

			if($position < $offset) {
				$start = $position + 1;
			} else {
				$end = $position;
				break;
			}

		}

		preg_match_all(self::ANCHOR, $text, $anchors, PREG_OFFSET_CAPTURE);

		$owners = array_filter($anchors[0], fn(array $anchor) => ($anchor[1] >= $start and $anchor[1] < $end));

		$inSentence = function(int $position, int $length) use($start, $end, $owners, $offset): bool {

			if($position < $start or $position >= $end) {
				return FALSE;
			}

			$nearest = NULL;
			$distance = PHP_INT_MAX;

			foreach($owners as [$span, $owner]) {

				$gap = ($owner > $position) ? $owner - ($position + $length) : $position - ($owner + strlen($span));

				if($gap < $distance or ($gap === $distance and $owner > $position)) {
					$nearest = $owner;
					$distance = $gap;
				}

			}

			return ($nearest === $offset);

		};

		$words = [];

		foreach($quotes[0] as [$span, $position]) {

			$quoted = trim(substr($span, strlen('«'), -strlen('»')));

			if($inSentence($position, strlen($span)) and ($offset < $position or $offset >= $position + strlen($span)) and mb_strlen($quoted) >= 4) {
				$words[] = $quoted;
			}

		}

		foreach($codes[0] as [$span, $position]) {

			$code = substr($span, 1, -1);

			if($inSentence($position, strlen($span)) === FALSE or preg_match('/^([\w.\/@<>*-]+\.[A-Za-z0-9]{1,4})?(:[\d, -]+)?$/', $code) === 1) {
				continue;
			}

			if(strlen($code) >= 4 and preg_match('/\s/', $code) === 0) {
				$words[] = $code;
			}

			// Un appel `Classe::methode()` se retrouve à sa définition, que le nom de la classe ne désigne
			// pas : il est sur toutes les lignes de son propre Lib. Rendu plus long que lui, il passe devant.
			// Mesuré le 2026-10-20 sur `StructureLib::create()`, relogé à 748 au lieu de 771 (relevé par E-RH).
			if(preg_match('/::(\w+)\(/', $code, $method) === 1) {
				$words[] = 'function '.$method[1].'(';
			}

			preg_match_all(str_contains($code, '(') ? '/[A-Za-z_][\w-]{2,}\w/' : '/[A-Za-z_][\w.-]{2,}\w/', $code, $identifiers);

			foreach($identifiers[0] as $identifier) {

				if(in_array($identifier, self::COMMON, TRUE) === FALSE) {
					$words[] = $identifier;
				}

			}

		}

		return array_values(array_unique($words));

	}

	/**
	 * La ligne citée quand l'intervalle cité porte un des mots, sinon la ligne la plus proche qui porte
	 * le plus long des mots que le fichier contient encore, ou NULL : un mot court se retrouve partout,
	 * le plus long désigne. Au-delà de l'intervalle, la ligne rendue en est le nouveau début.
	 *
	 * @param string[] $lines
	 * @param string[] $words
	 */
	private static function findLine(array $lines, int $target, int $end, array $words): ?int {

		$count = count($lines);

		for($number = max(1, $target); $number <= min($end, $count); $number++) {

			foreach($words as $word) {

				if(str_contains($lines[$number - 1], $word)) {
					return $target;
				}

			}

		}

		usort($words, fn(string $a, string $b) => strlen($b) <=> strlen($a));

		foreach($words as $word) {

			for($distance = 1; $distance <= max($target, $count); $distance++) {

				if($target - $distance >= 1 and $target - $distance <= $count and str_contains($lines[$target - $distance - 1], $word)) {
					return $target - $distance;
				}

				if($end + $distance <= $count and str_contains($lines[$end + $distance - 1], $word)) {
					return $target + $distance;
				}

			}

		}

		return NULL;

	}

	/**
	 * Un décalage partagé par plusieurs ancres d'un même fichier de règles vers un même fichier se dit
	 * uniforme : une seule édition, du même nombre, les répare toutes.
	 */
	private static function markUniformShifts(array $diagnoses): array {

		$key = fn(array $diagnosis) => $diagnosis['file'].' '.$diagnosis['diagnosis']['path'].' '.$diagnosis['diagnosis']['shift'];

		$shifted = array_filter($diagnoses, fn(array $diagnosis) => array_key_exists('shift', $diagnosis['diagnosis']));
		$groups = array_count_values(array_map($key, $shifted));

		foreach($shifted as $index => $diagnosis) {

			$count = $groups[$key($diagnosis)];

			if($count > 1) {
				$diagnoses[$index]['diagnosis']['cause'] = str_replace('décalage de ', 'décalage uniforme sur '.$count.' ancres de ', $diagnosis['diagnosis']['cause']);
			}

		}

		return $diagnoses;

	}

	/**
	 * Les causes des ancres que la déclaration ne porte pas, pour que le message dise quoi corriger
	 * sans relancer la recherche.
	 */
	private static function explainDiagnoses(array $diagnoses, array $declared): string {

		$lines = [];

		foreach($diagnoses as $diagnosis) {

			if(array_key_exists($diagnosis['file'].':'.$diagnosis['path'].':'.$diagnosis['target'], $declared) === FALSE) {
				$lines[] = $diagnosis['file'].':'.$diagnosis['line'].' `'.$diagnosis['path'].':'.$diagnosis['target'].'` '.$diagnosis['diagnosis']['cause'];
			}

		}

		return ($lines === []) ? '' : ' : '.implode(' · ', $lines);

	}

	/**
	 * La cause qui empêche l'ancre de se résoudre, ou NULL quand sa ligne porte un mot cité.
	 *
	 * @param array{path: string, target: int, end: int, words: string[]} $anchor
	 * @return array{cause: string, path?: string, shift?: int}|null
	 */
	private function diagnose(array $anchor): ?array {

		$path = $anchor['path'];
		$candidates = $this->findPresent($path, $anchor['file']);

		if(count($candidates) > 1) {

			$carriers = array_values(array_filter($candidates, fn(string $candidate) => self::findLine($this->getLines($candidate), $anchor['target'], $anchor['end'], $anchor['words']) === $anchor['target']));

			if(count($carriers) !== 1) {
				return ['cause' => 'ambiguë, '.count($candidates).' candidats'.($carriers === [] ? ', aucun ne porte le mot cité à cette ligne' : '').' : '.implode(', ', $candidates)];
			}

			$candidates = $carriers;

		}

		if($candidates !== []) {
			return $this->diagnoseFile($candidates[0], $anchor);
		}

		if(self::matches($path, self::DERIVED)) {
			return ['cause' => 'ancre vers un artefact dérivé, qui n\'existe que dans l\'arbre du chantier qui l\'a produit : recopier l\'appui dans le fichier de règles'];
		}

		$ignore = SentinelLib::getGitPaths('check-ignore', '--no-index', '--verbose', '--non-matching', '--', $path);

		if($ignore !== NULL and str_starts_with($ignore[0], '::') === FALSE) {
			return ['cause' => 'la référence vit hors de git ('.$path.', sous ce que le .gitignore du dépôt écarte, absent de cet arbre) : recopier l\'appui dans le fichier de règles'];
		}

		$sha = $this->findDeletion($path);

		if($sha !== NULL) {

			$renamed = self::findRename(SentinelLib::getGitPaths('show', '-M', '--name-status', '--format=', $sha) ?? [], $path);

			if($renamed === NULL) {
				return ['cause' => 'chemin supprimé par '.$sha.', sans renommage'];
			}

			$line = is_file(SentinelLib::getRoot().'/'.$renamed) ? self::findLine($this->getLines($renamed), $anchor['target'], $anchor['end'], $anchor['words']) : NULL;

			return ['cause' => 'chemin renommé par '.$sha.', la référence vit à '.$renamed.($line === NULL ? ', où aucun mot cité ne se retrouve' : ':'.$line)];

		}

		return $this->diagnoseByContent($anchor);

	}

	/**
	 * Le dernier commit de `origin/main` qui a retiré le chemin cité, ou NULL. Un chemin partiel qui
	 * désigne plusieurs chemins retirés n'en désigne aucun, et un nom de fichier seul n'est pas
	 * cherché : l'historique en a retiré trop sous ce nom pour qu'un seul soit le bon.
	 *
	 * Le relevé est pris une fois, sans détection de renommage : un renommage y paraît comme le
	 * retrait de l'ancien chemin, que `findRename()` reconnaît ensuite dans son commit. Un `git log`
	 * borné par ancre coûte 1,8 s, le relevé entier 2,2 s (mesuré le 2026-10-16).
	 */
	private function findDeletion(string $path): ?string {

		if(str_contains($path, '/') === FALSE) {
			return NULL;
		}

		if($this->deleted === NULL) {

			$this->deleted = [];
			$sha = NULL;

			foreach(SentinelLib::getGitPaths('log', '--no-renames', '--diff-filter=D', '--name-only', '--format=%x01%h', SentinelLib::getUpstream()) ?? [] as $line) {

				if(str_starts_with($line, "\x01")) {
					$sha = substr($line, 1);
				} else {
					$this->deleted[$line] ??= $sha;
				}

			}

		}

		if(array_key_exists($path, $this->deleted)) {
			return $this->deleted[$path];
		}

		$matches = array_filter($this->deleted, fn(string $deleted) => str_ends_with($deleted, '/'.$path), ARRAY_FILTER_USE_KEY);

		return (count($matches) === 1) ? reset($matches) : NULL;

	}

	/**
	 * Le fichier résolu : hors de git, absent de l'amont, ou présent, et alors la ligne se juge.
	 */
	private function diagnoseFile(string $path, array $anchor): ?array {

		if(isset($this->getList('ignored')[$path])) {
			return ['cause' => 'la référence vit hors de git ('.$path.', que le .gitignore du dépôt écarte) : recopier l\'appui dans le fichier de règles'];
		}

		if(isset($this->getList('upstream')[$path]) === FALSE) {
			return ['cause' => 'chemin absent de l\'amont, '.(isset($this->getList('tracked')[$path]) ? 'ajouté à l\'index de cet arbre sans être poussé' : 'non suivi dans cet arbre').' : à pousser par son auteur'];
		}

		$line = self::findLine($this->getLines($path), $anchor['target'], $anchor['end'], $anchor['words']);

		if($line === $anchor['target']) {
			return NULL;
		}

		if($line === NULL) {
			return ['cause' => 'ligne morte, aucun des mots de sa phrase ('.implode(', ', $anchor['words']).') n\'est dans '.$path];
		}

		$shift = $line - $anchor['target'];

		return [
			'cause' => 'décalage de '.($shift > 0 ? '+' : '').$shift.', la nouvelle ligne est '.$line,
			'path' => $path,
			'shift' => $shift,
		];

	}

	/**
	 * Second recours, quand ni le disque ni l'historique ne connaissent le chemin : les fichiers de même
	 * nom qui portent un mot cité, hors fichiers de règles et hors dérivés du source. Les fichiers que
	 * le `.gitignore` écarte en sont : `.claude/state/` porte des appuis réels. Les mots se prennent du
	 * plus long au plus court, et le premier qu'un candidat porte désigne les porteurs : un `projet.md`
	 * porte `saisie` dans chaque chantier, `paniers-L0c-saisie-v8` dans un seul.
	 */
	private function diagnoseByContent(array $anchor): array {

		$basename = basename($anchor['path']);
		$candidates = [];

		foreach(array_keys($this->getList('present') + $this->getList('ignored')) as $file) {

			if(basename($file) === $basename and self::matches($file, self::POPULATION) === FALSE and self::matches($file, self::DERIVED) === FALSE and is_file(SentinelLib::getRoot().'/'.$file)) {
				$candidates[] = $file;
			}

		}

		sort($candidates);

		$words = $anchor['words'];
		usort($words, fn(string $first, string $second) => mb_strlen($second) <=> mb_strlen($first));

		$carriers = [];

		foreach($words as $word) {

			foreach($candidates as $candidate) {

				$line = self::findLine($this->getLines($candidate), $anchor['target'], $anchor['end'], [$word]);

				if($line !== NULL) {
					$carriers[$candidate] = $line;
				}

			}

			if($carriers !== []) {
				break;
			}

		}

		$tracked = array_intersect_key($carriers, $this->getList('tracked'));

		if(count($tracked) === 1) {
			return ['cause' => 'chemin faux, la référence vit à '.array_key_first($tracked).':'.reset($tracked)];
		}

		if($tracked === [] and count($carriers) === 1) {

			$file = array_key_first($carriers);

			return ['cause' => 'la référence vit hors de git ('.$file.':'.$carriers[$file].', '.(array_key_exists($file, $this->getList('ignored')) ? 'que le .gitignore du dépôt écarte' : 'non suivi').') : recopier l\'appui dans le fichier de règles'];

		}

		if($carriers !== []) {
			return ['cause' => 'ambiguë, '.count($carriers).' candidats portent un mot cité : '.implode(', ', array_map(fn(string $file, int $line) => $file.':'.$line, array_keys($carriers), $carriers))];
		}

		if($candidates !== []) {
			return ['cause' => 'ambiguë, '.count($candidates).' candidats, aucun ne porte le mot cité : '.implode(', ', $candidates)];
		}

		return ['cause' => 'chemin absent de l\'amont, et absent de cet arbre : à pousser par son auteur'];

	}

	/**
	 * Le chemin du fichier qui a pris la place de `$path` dans un `git show -M --name-status`, ou NULL.
	 *
	 * @param string[] $output
	 */
	private static function findRename(array $output, string $path): ?string {

		foreach($output as $line) {

			$columns = explode("\t", $line);

			if(count($columns) === 3 and str_starts_with($columns[0], 'R') and ($columns[1] === $path or str_ends_with($columns[1], '/'.$path))) {
				return $columns[2];
			}

		}

		return NULL;

	}

	/**
	 * Les fichiers présents que le `.gitignore` du dépôt n'écarte pas, dont le chemin finit par `$path`.
	 * Un `CLAUDE.md` de package cite depuis son répertoire (`ui/Treatment.u.php` dans
	 * `production/toolbox/CLAUDE.md`), et ce répertoire se cherche d'abord.
	 *
	 * @return string[]
	 */
	private function findPresent(string $path, string $rule): array {

		$present = $this->getList('present');

		if(basename($rule) === 'CLAUDE.md' and dirname($rule) !== '.' and isset($present[dirname($rule).'/'.$path])) {
			return [dirname($rule).'/'.$path];
		}

		if(isset($present[$path])) {
			return [$path];
		}

		$candidates = array_values(array_filter(array_keys($present), fn(string $file) => str_ends_with($file, '/'.$path)));

		sort($candidates);

		return $candidates;

	}

	private static function matches(string $file, array $patterns): bool {

		foreach($patterns as $pattern) {

			if(fnmatch($pattern, $file)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Les lignes d'un fichier, lues sur le disque comme les fichiers de règles qui le citent : un lot
	 * qui déplace du code et réécrit l'ancre qui le cite reste vert, et celui qui déplace sans réécrire
	 * rougit avant son push, chez lui. Lire l'un dans l'arbre et l'autre dans `origin/main` inversait
	 * les deux. Le verdict d'amont d'une entrée neuve est celui de `SentinelLib::explainNew()`.
	 *
	 * @return string[]
	 */
	private function getLines(string $path): array {

		if(array_key_exists($path, $this->lines) === FALSE) {

			$content = file_get_contents(SentinelLib::getRoot().'/'.$path);

			if($content === FALSE) {
				throw new \Exception('le contenu de '.$path.' n\'a pas pu être lu sur le disque');
			}

			$this->lines[$path] = explode("\n", $content);

		}

		return $this->lines[$path];

	}

	/**
	 * Une liste de chemins lue de git, une fois par lancement :
	 * - `present`, les fichiers du disque, suivis ou non, que le `.gitignore` du dépôt n'écarte pas ;
	 *   un fichier suivi supprimé de l'arbre n'en est pas, le disque fait foi ;
	 * - `ignored`, ceux qu'il écarte, `.claude/state/` en tête ;
	 * - `tracked`, ceux que git suit ;
	 * - `upstream`, ceux que porte `origin/main`.
	 *
	 * `testAnchorsResolve()` a vérifié que git répond avant la première lecture : un silence ici
	 * serait une panne survenue entre deux appels, et la liste vide le fait voir par des écarts.
	 *
	 * @return array<string, TRUE>
	 */
	private function getList(string $name): array {

		if(array_key_exists($name, $this->lists) === FALSE) {

			$paths = match($name) {
				'present' => array_filter(SentinelLib::getGitPaths('ls-files', '--cached', '--others', '--exclude-standard') ?? [], fn(string $file) => is_file(SentinelLib::getRoot().'/'.$file)),
				'ignored' => SentinelLib::getGitPaths('ls-files', '--others', '--ignored', '--exclude-standard') ?? [],
				'tracked' => SentinelLib::getGitPaths('ls-files') ?? [],
				'upstream' => SentinelLib::getGitPaths('ls-tree', '-r', '--name-only', SentinelLib::getUpstream()) ?? [],
			};

			$this->lists[$name] = array_fill_keys($paths, TRUE);

		}

		return $this->lists[$name];

	}

}
?>
