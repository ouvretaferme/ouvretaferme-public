<?php
Route::register([
	'DELETE' => [
	],
	'GET' => [
		'/api/oauth/authorize' => [
			'request' => 'api/authorize',
			'priority' => 5,
			'route' => ['api', 'oauth', 'authorize'],
		],
		'/api/oauth/demo' => [
			'request' => 'api/demo',
			'priority' => 5,
			'route' => ['api', 'oauth', 'demo'],
		],
		'/api/oauth/demo/callback' => [
			'request' => 'api/demo',
			'priority' => 5,
			'route' => ['api', 'oauth', 'demo', 'callback'],
		],
		'/demarrer/amap' => [
			'request' => 'structure/structure',
			'priority' => 5,
			'route' => ['demarrer', 'amap'],
		],
		'/demarrer/communaute' => [
			'request' => 'structure/structure',
			'priority' => 5,
			'route' => ['demarrer', 'communaute'],
		],
		'/demarrer/education' => [
			'request' => 'structure/structure',
			'priority' => 5,
			'route' => ['demarrer', 'education'],
		],
		'/demarrer/ferme' => [
			'request' => 'structure/structure',
			'priority' => 5,
			'route' => ['demarrer', 'ferme'],
		],
		'/demarrer/groupement' => [
			'request' => 'structure/structure',
			'priority' => 5,
			'route' => ['demarrer', 'groupement'],
		],
		'/demarrer/reseau' => [
			'request' => 'structure/structure',
			'priority' => 5,
			'route' => ['demarrer', 'reseau'],
		],
		'/design/' => [
			'request' => 'design/index',
			'priority' => 5,
			'route' => ['design'],
		],
		'/developer/postman-collection.json' => [
			'request' => 'main/developer',
			'priority' => 5,
			'route' => ['developer', 'postman-collection.json'],
		],
		'/discord' => [
			'request' => 'communication/discord',
			'priority' => 5,
			'route' => ['discord'],
		],
		'/doc/' => [
			'request' => 'main/doc/main',
			'priority' => 5,
			'route' => ['doc'],
		],
		'/doc/communication:website' => [
			'request' => 'website/doc',
			'priority' => 5,
			'route' => ['doc', 'communication:website'],
		],
		'/doc/hr' => [
			'request' => 'hr/docAi',
			'priority' => 5,
			'route' => ['doc', 'hr'],
		],
		'/doc/import:products' => [
			'request' => 'product/doc',
			'priority' => 5,
			'route' => ['doc', 'import:products'],
		],
		'/doc/import:series' => [
			'request' => 'series/doc',
			'priority' => 5,
			'route' => ['doc', 'import:series'],
		],
		'/espace-client' => [
			'request' => 'main/index',
			'priority' => 5,
			'route' => ['espace-client'],
		],
		'/espece/{id@int}' => [
			'request' => 'plant/plant',
			'priority' => 5,
			'route' => ['espece', '{id@int}'],
		],
		'/faq' => [
			'request' => 'learning/faq',
			'priority' => 5,
			'route' => ['faq'],
		],
		'/ferme/{id}' => [
			'request' => 'structure/profileAi',
			'priority' => 5,
			'route' => ['ferme', '{id}'],
		],
		'/ferme/{id}/accueil' => [
			'request' => 'dashboard/dashboard',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'accueil'],
		],
		'/ferme/{id}/accueil/bloc/{name}' => [
			'request' => 'dashboard/dashboard',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'accueil', 'bloc', '{name}'],
		],
		'/ferme/{id}/accueil/choisir/{name}' => [
			'request' => 'dashboard/dashboard',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'accueil', 'choisir', '{name}'],
		],
		'/ferme/{id}/accueil/menu' => [
			'request' => 'dashboard/dashboard',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'accueil', 'menu'],
		],
		'/ferme/{id}/accueil/nouveau' => [
			'request' => 'dashboard/dashboard',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'accueil', 'nouveau'],
		],
		'/ferme/{id}/accueil/personnaliser' => [
			'request' => 'dashboard/preference',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'accueil', 'personnaliser'],
		],
		'/ferme/{id}/accueil/pilotage' => [
			'request' => 'dashboard/dashboard',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'accueil', 'pilotage'],
		],
		'/ferme/{id}/analyses/cultures' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'analyses', 'cultures'],
		],
		'/ferme/{id}/analyses/cultures/{season}/{category}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'analyses', 'cultures', '{season}', '{category}'],
		],
		'/ferme/{id}/analyses/planning' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'analyses', 'planning'],
		],
		'/ferme/{id}/analyses/planning/{year}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'analyses', 'planning', '{year}'],
		],
		'/ferme/{id}/analyses/planning/{year}/{category}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'analyses', 'planning', '{year}', '{category}'],
		],
		'/ferme/{id}/assolement' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'assolement'],
		],
		'/ferme/{id}/assolement/{season}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'assolement', '{season}'],
		],
		'/ferme/{id}/bibliotheque' => [
			'request' => 'plant/communityAi',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'bibliotheque'],
		],
		'/ferme/{id}/cartographie' => [
			'request' => 'map/map',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'cartographie'],
		],
		'/ferme/{id}/cartographie/{season}' => [
			'request' => 'map/map',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'cartographie', '{season}'],
		],
		'/ferme/{id}/configuration/adherents' => [
			'request' => 'structure/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'configuration', 'adherents'],
		],
		'/ferme/{id}/configuration/commercialisation' => [
			'request' => 'structure/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'configuration', 'commercialisation'],
		],
		'/ferme/{id}/configuration/distributions' => [
			'request' => 'structure/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'configuration', 'distributions'],
		],
		'/ferme/{id}/configuration/production' => [
			'request' => 'structure/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'configuration', 'production'],
		],
		'/ferme/{id}/equipe' => [
			'request' => 'structure/member',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'equipe'],
		],
		'/ferme/{id}/equipe/absences' => [
			'request' => 'hr/absence',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'equipe', 'absences'],
		],
		'/ferme/{id}/equipe/recapitulatif' => [
			'request' => 'hr/recap',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'equipe', 'recapitulatif'],
		],
		'/ferme/{id}/especes' => [
			'request' => 'plant/plant',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'especes'],
		],
		'/ferme/{id}/especes/{status}' => [
			'request' => 'plant/plant',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'especes', '{status}'],
		],
		'/ferme/{id}/interstitiel/{name}' => [
			'request' => 'structure/interstitial',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'interstitiel', '{name}'],
		],
		'/ferme/{id}/intervention/{week}/{action}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'intervention', '{week}', '{action}'],
		],
		'/ferme/{id}/itineraires' => [
			'request' => 'sequence/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'itineraires'],
		],
		'/ferme/{id}/itineraires/{status}' => [
			'request' => 'sequence/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'itineraires', '{status}'],
		],
		'/ferme/{id}/notes' => [
			'request' => 'note/note',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'notes'],
		],
		'/ferme/{id}/notifications' => [
			'request' => 'notification/notification',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'notifications'],
		],
		'/ferme/{id}/notifications/reglages' => [
			'request' => 'notification/notification',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'notifications', 'reglages'],
		],
		'/ferme/{id}/outils' => [
			'request' => 'toolbox/tool',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils'],
		],
		'/ferme/{id}/outils/aides' => [
			'request' => 'aid/aid',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'aides'],
		],
		'/ferme/{id}/outils/aides/{aid@int}' => [
			'request' => 'aid/aid',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'aides', '{aid@int}'],
		],
		'/ferme/{id}/outils/densites' => [
			'request' => 'toolbox/density',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'densites'],
		],
		'/ferme/{id}/outils/eau' => [
			'request' => 'toolbox/waterReading',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'eau'],
		],
		'/ferme/{id}/outils/fertilisation' => [
			'request' => 'toolbox/fertilization',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'fertilisation'],
		],
		'/ferme/{id}/outils/materiel' => [
			'request' => 'toolbox/equipment',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'materiel'],
		],
		'/ferme/{id}/outils/paniers' => [
			'request' => 'toolbox/composition',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'paniers'],
		],
		'/ferme/{id}/outils/paniers/depart' => [
			'request' => 'toolbox/composition',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'paniers', 'depart'],
		],
		'/ferme/{id}/outils/paniers/especes' => [
			'request' => 'toolbox/composition',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'paniers', 'especes'],
		],
		'/ferme/{id}/outils/paniers/synthese' => [
			'request' => 'toolbox/composition',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'paniers', 'synthese'],
		],
		'/ferme/{id}/outils/paniers/vente' => [
			'request' => 'toolbox/compositionSale',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'paniers', 'vente'],
		],
		'/ferme/{id}/outils/pluviometrie' => [
			'request' => 'toolbox/rain',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'pluviometrie'],
		],
		'/ferme/{id}/outils/produits' => [
			'request' => 'toolbox/catalog',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'produits'],
		],
		'/ferme/{id}/outils/traitement' => [
			'request' => 'toolbox/treatment',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'outils', 'traitement'],
		],
		'/ferme/{id}/paniers' => [
			'request' => 'toolbox/composition',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'paniers'],
		],
		'/ferme/{id}/paniers/depart' => [
			'request' => 'toolbox/composition',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'paniers', 'depart'],
		],
		'/ferme/{id}/paniers/synthese' => [
			'request' => 'toolbox/composition',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'paniers', 'synthese'],
		],
		'/ferme/{id}/paniers/{season@int}' => [
			'request' => 'toolbox/composition',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'paniers', '{season@int}'],
		],
		'/ferme/{id}/paniers/{season@int}/synthese' => [
			'request' => 'toolbox/composition',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'paniers', '{season@int}', 'synthese'],
		],
		'/ferme/{id}/partage-des-donnees' => [
			'request' => 'structure/structure',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'partage-des-donnees'],
		],
		'/ferme/{id}/plan' => [
			'request' => 'map/map',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'plan'],
		],
		'/ferme/{id}/plan-3d' => [
			'request' => 'map/scene',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'plan-3d'],
		],
		'/ferme/{id}/plan-3d/{season}' => [
			'request' => 'map/scene',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'plan-3d', '{season}'],
		],
		'/ferme/{id}/plan/demarrer' => [
			'request' => 'map/onboarding',
			'priority' => 1,
			'route' => ['ferme', '{id}', 'plan', 'demarrer'],
		],
		'/ferme/{id}/plan/{season}' => [
			'request' => 'map/map',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'plan', '{season}'],
		],
		'/ferme/{id}/planches' => [
			'request' => 'map/map',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planches'],
		],
		'/ferme/{id}/planches/{season}' => [
			'request' => 'map/map',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planches', '{season}'],
		],
		'/ferme/{id}/planning-v2/absences' => [
			'request' => 'planning/absence',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'absences'],
		],
		'/ferme/{id}/planning-v2/absences/{week@week}' => [
			'request' => 'planning/absence',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'absences', '{week@week}'],
		],
		'/ferme/{id}/planning-v2/annee' => [
			'request' => 'planning/taskYear',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'annee'],
		],
		'/ferme/{id}/planning-v2/annee/{week@week}' => [
			'request' => 'planning/taskYear',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'annee', '{week@week}'],
		],
		'/ferme/{id}/planning-v2/intervention/{task}' => [
			'request' => 'planning/taskDetail',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'intervention', '{task}'],
		],
		'/ferme/{id}/planning-v2/jour' => [
			'request' => 'planning/taskDay',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'jour'],
		],
		'/ferme/{id}/planning-v2/jour/{week@week}' => [
			'request' => 'planning/taskDay',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'jour', '{week@week}'],
		],
		'/ferme/{id}/planning-v2/personnaliser' => [
			'request' => 'planning/taskWeek',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'personnaliser'],
		],
		'/ferme/{id}/planning-v2/personnaliser/{week@week}' => [
			'request' => 'planning/taskWeek',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'personnaliser', '{week@week}'],
		],
		'/ferme/{id}/planning-v2/recoltes' => [
			'request' => 'planning/harvestWeek',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'recoltes'],
		],
		'/ferme/{id}/planning-v2/recoltes/jour' => [
			'request' => 'planning/harvestDay',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'recoltes', 'jour'],
		],
		'/ferme/{id}/planning-v2/recoltes/jour/{date@date}' => [
			'request' => 'planning/harvestDay',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'recoltes', 'jour', '{date@date}'],
		],
		'/ferme/{id}/planning-v2/recoltes/{week@week}' => [
			'request' => 'planning/harvestWeek',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'recoltes', '{week@week}'],
		],
		'/ferme/{id}/planning-v2/repartir' => [
			'request' => 'planning/taskWeek',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'repartir'],
		],
		'/ferme/{id}/planning-v2/repartir/{week@week}' => [
			'request' => 'planning/taskWeek',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'repartir', '{week@week}'],
		],
		'/ferme/{id}/planning-v2/semaine' => [
			'request' => 'planning/taskWeek',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'semaine'],
		],
		'/ferme/{id}/planning-v2/semaine/{week@week}' => [
			'request' => 'planning/taskWeek',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'semaine', '{week@week}'],
		],
		'/ferme/{id}/planning-v2/temps' => [
			'request' => 'planning/timeDay',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'temps'],
		],
		'/ferme/{id}/planning-v2/temps/{week@week}' => [
			'request' => 'planning/timeDay',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning-v2', 'temps', '{week@week}'],
		],
		'/ferme/{id}/planning/{view}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning', '{view}'],
		],
		'/ferme/{id}/planning/{view}/{period}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning', '{view}', '{period}'],
		],
		'/ferme/{id}/planning/{view}/{period}/{subPeriod}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'planning', '{view}', '{period}', '{subPeriod}'],
		],
		'/ferme/{id}/previsionnel' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'previsionnel'],
		],
		'/ferme/{id}/previsionnel/{season}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'previsionnel', '{season}'],
		],
		'/ferme/{id}/produits' => [
			'request' => 'product/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'produits'],
		],
		'/ferme/{id}/roles' => [
			'request' => 'structure/role',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'roles'],
		],
		'/ferme/{id}/rotation' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'rotation'],
		],
		'/ferme/{id}/rotation/{season}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'rotation', '{season}'],
		],
		'/ferme/{id}/semences-plants' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'semences-plants'],
		],
		'/ferme/{id}/semences-plants/{season}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'semences-plants', '{season}'],
		],
		'/ferme/{id}/series' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'series'],
		],
		'/ferme/{id}/series/{season}' => [
			'request' => 'series/index',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'series', '{season}'],
		],
		'/ferme/{id}/varietes' => [
			'request' => 'plant/varietyRating',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'varietes'],
		],
		'/ferme/{id}/varietes/criteres' => [
			'request' => 'plant/criterion',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'varietes', 'criteres'],
		],
		'/ferme/{id}/varietes/voter' => [
			'request' => 'plant/varietyRating',
			'priority' => 5,
			'route' => ['ferme', '{id}', 'varietes', 'voter'],
		],
		'/formation/date/feuille/{id}' => [
			'request' => 'learning/dateSheet',
			'priority' => 5,
			'route' => ['formation', 'date', 'feuille', '{id}'],
		],
		'/formation/date/inscription/{id}/attestation' => [
			'request' => 'learning/dateEnrollment',
			'priority' => 5,
			'route' => ['formation', 'date', 'inscription', '{id}', 'attestation'],
		],
		'/formation/date/{id}/emargement' => [
			'request' => 'learning/dateEnrollment',
			'priority' => 5,
			'route' => ['formation', 'date', '{id}', 'emargement'],
		],
		'/formation/date/{id}/inscrits' => [
			'request' => 'learning/dateEnrollment',
			'priority' => 5,
			'route' => ['formation', 'date', '{id}', 'inscrits'],
		],
		'/formation/fichier/{id}' => [
			'request' => 'learning/attachment',
			'priority' => 5,
			'route' => ['formation', 'fichier', '{id}'],
		],
		'/formation/lecon/{id}' => [
			'request' => 'learning/lesson',
			'priority' => 5,
			'route' => ['formation', 'lecon', '{id}'],
		],
		'/formation/lecon/{id}/edition' => [
			'request' => 'learning/lesson',
			'priority' => 5,
			'route' => ['formation', 'lecon', '{id}', 'edition'],
		],
		'/formation/{id}' => [
			'request' => 'learning/course',
			'priority' => 5,
			'route' => ['formation', '{id}'],
		],
		'/formation/{id}/attestation' => [
			'request' => 'learning/course',
			'priority' => 5,
			'route' => ['formation', '{id}', 'attestation'],
		],
		'/formation/{id}/bilan' => [
			'request' => 'learning/course',
			'priority' => 5,
			'route' => ['formation', '{id}', 'bilan'],
		],
		'/formation/{id}/catalogue' => [
			'request' => 'learning/course',
			'priority' => 5,
			'route' => ['formation', '{id}', 'catalogue'],
		],
		'/formation/{id}/demarrer' => [
			'request' => 'learning/course',
			'priority' => 5,
			'route' => ['formation', '{id}', 'demarrer'],
		],
		'/formation/{id}/edition' => [
			'request' => 'learning/course',
			'priority' => 5,
			'route' => ['formation', '{id}', 'edition'],
		],
		'/formation/{id}/suivi' => [
			'request' => 'learning/progress',
			'priority' => 5,
			'route' => ['formation', '{id}', 'suivi'],
		],
		'/formation/{id}/suivi/ferme/{structure@int}' => [
			'request' => 'learning/progress',
			'priority' => 5,
			'route' => ['formation', '{id}', 'suivi', 'ferme', '{structure@int}'],
		],
		'/formation/{id}/suivi/lecon/{lesson@int}' => [
			'request' => 'learning/progress',
			'priority' => 5,
			'route' => ['formation', '{id}', 'suivi', 'lecon', '{lesson@int}'],
		],
		'/formation/{id}/suivi/lecon/{lesson@int}/ferme/{structure@int}' => [
			'request' => 'learning/progress',
			'priority' => 5,
			'route' => ['formation', '{id}', 'suivi', 'lecon', '{lesson@int}', 'ferme', '{structure@int}'],
		],
		'/formation/{id}/suivi/lecon/{lesson@int}/{section@int}' => [
			'request' => 'learning/progress',
			'priority' => 5,
			'route' => ['formation', '{id}', 'suivi', 'lecon', '{lesson@int}', '{section@int}'],
		],
		'/formation/{id}/suivi/{section@int}' => [
			'request' => 'learning/progress',
			'priority' => 5,
			'route' => ['formation', '{id}', 'suivi', '{section@int}'],
		],
		'/formations' => [
			'request' => 'learning/catalog',
			'priority' => 5,
			'route' => ['formations'],
		],
		'/formations/avis' => [
			'request' => 'learning/catalog',
			'priority' => 5,
			'route' => ['formations', 'avis'],
		],
		'/formations/compte' => [
			'request' => 'learning/account',
			'priority' => 5,
			'route' => ['formations', 'compte'],
		],
		'/groupe/{id}' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}'],
		],
		'/groupe/{id}/comparer' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'comparer'],
		],
		'/groupe/{id}/comparer/{section@int}' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'comparer', '{section@int}'],
		],
		'/groupe/{id}/fermes' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'fermes'],
		],
		'/groupe/{id}/fermes/ecole' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'fermes', 'ecole'],
		],
		'/groupe/{id}/fermes/gerer' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'fermes', 'gerer'],
		],
		'/groupe/{id}/fermes/{section@int}' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'fermes', '{section@int}'],
		],
		'/groupe/{id}/formations' => [
			'request' => 'learning/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'formations'],
		],
		'/groupe/{id}/formations/avis' => [
			'request' => 'learning/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'formations', 'avis'],
		],
		'/groupe/{id}/formations/commentaires' => [
			'request' => 'learning/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'formations', 'commentaires'],
		],
		'/groupe/{id}/formations/encadrants' => [
			'request' => 'learning/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'formations', 'encadrants'],
		],
		'/groupe/{id}/formations/integrer' => [
			'request' => 'learning/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'formations', 'integrer'],
		],
		'/groupe/{id}/maraichage' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'maraichage'],
		],
		'/groupe/{id}/maraichage/{section@int}' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'maraichage', '{section@int}'],
		],
		'/groupe/{id}/membres' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'membres'],
		],
		'/groupe/{id}/membres/{section@int}' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'membres', '{section@int}'],
		],
		'/groupe/{id}/mes-fermes' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'mes-fermes'],
		],
		'/groupe/{id}/notifications' => [
			'request' => 'notification/notification',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'notifications'],
		],
		'/groupe/{id}/notifications/reglages' => [
			'request' => 'notification/notification',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'notifications', 'reglages'],
		],
		'/groupe/{id}/section/{section@int}' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'section', '{section@int}'],
		],
		'/groupe/{id}/sous-groupes' => [
			'request' => 'structure/community',
			'priority' => 5,
			'route' => ['groupe', '{id}', 'sous-groupes'],
		],
		'/historique' => [
			'request' => 'securing/log',
			'priority' => 5,
			'route' => ['historique'],
		],
		'/inscription' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription'],
		],
		'/inscription/accompagnement' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription', 'accompagnement'],
		],
		'/inscription/client' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription', 'client'],
		],
		'/inscription/formation' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription', 'formation'],
		],
		'/inscription/producteur' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription', 'producteur'],
		],
		'/invitation' => [
			'request' => 'structure/invite',
			'priority' => 5,
			'route' => ['invitation'],
		],
		'/itineraire/{id}' => [
			'request' => 'sequence/sequence',
			'priority' => 5,
			'route' => ['itineraire', '{id}'],
		],
		'/jouer' => [
			'request' => 'game/index',
			'priority' => 5,
			'route' => ['jouer'],
		],
		'/mail/verify:check' => [
			'request' => 'user/verify',
			'priority' => 5,
			'route' => ['mail', 'verify:check'],
		],
		'/mes-formations' => [
			'request' => 'learning/mine',
			'priority' => 5,
			'route' => ['mes-formations'],
		],
		'/minify/{version}/{filename}' => [
			'request' => 'dev/minify',
			'priority' => 5,
			'route' => ['minify', '{version}', '{filename}'],
		],
		'/note/{id}' => [
			'request' => 'note/note',
			'priority' => 5,
			'route' => ['note', '{id}'],
		],
		'/outil/{id@int}' => [
			'request' => 'work/tool',
			'priority' => 5,
			'route' => ['outil', '{id@int}'],
		],
		'/presentation/invitation' => [
			'request' => 'main/index',
			'priority' => 5,
			'route' => ['presentation', 'invitation'],
		],
		'/produit/{id}' => [
			'request' => 'product/product',
			'priority' => 5,
			'route' => ['produit', '{id}'],
		],
		'/public/:404' => [
			'request' => 'website/public',
			'priority' => 5,
			'route' => ['public', ':404'],
		],
		'/public/robots.txt' => [
			'request' => 'website/public',
			'priority' => 1,
			'route' => ['public', 'robots.txt'],
		],
		'/public/{domain}' => [
			'request' => 'website/public',
			'priority' => 5,
			'route' => ['public', '{domain}'],
		],
		'/public/{domain}/' => [
			'request' => 'website/public',
			'priority' => 5,
			'route' => ['public', '{domain}'],
		],
		'/public/{domain}/:test' => [
			'request' => 'website/public',
			'priority' => 5,
			'route' => ['public', '{domain}', ':test'],
		],
		'/public/{domain}/robots.txt' => [
			'request' => 'website/public',
			'priority' => 1,
			'route' => ['public', '{domain}', 'robots.txt'],
		],
		'/public/{domain}/sitemap.xml' => [
			'request' => 'website/public',
			'priority' => 1,
			'route' => ['public', '{domain}', 'sitemap.xml'],
		],
		'/public/{domain}/{destination}' => [
			'request' => 'website/public',
			'priority' => 5,
			'route' => ['public', '{domain}', '{destination}'],
		],
		'/reseau/' => [
			'request' => 'structure/communityAddressAi',
			'priority' => 5,
			'route' => ['reseau'],
		],
		'/reseau/embed-learning.js' => [
			'request' => 'learning/public',
			'priority' => 1,
			'route' => ['reseau', 'embed-learning.js'],
		],
		'/reseau/robots.txt' => [
			'request' => 'structure/communityAddressAi',
			'priority' => 1,
			'route' => ['reseau', 'robots.txt'],
		],
		'/reseau/{fqn}' => [
			'request' => 'structure/communityAddressAi',
			'priority' => 5,
			'route' => ['reseau', '{fqn}'],
		],
		'/reseau/{fqn}/faq' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'faq'],
		],
		'/reseau/{fqn}/formations' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'formations'],
		],
		'/reseau/{fqn}/formations/{date}' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'formations', '{date}'],
		],
		'/reseau/{fqn}/inscription/{token}' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'inscription', '{token}'],
		],
		'/reseau/{fqn}/inscription/{token}/attestation' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'inscription', '{token}', 'attestation'],
		],
		'/reseau/{fqn}/inscription/{token}/avis' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'inscription', '{token}', 'avis'],
		],
		'/robots.txt' => [
			'request' => 'main/seo',
			'priority' => 5,
			'route' => ['robots.txt'],
		],
		'/serie/{id}' => [
			'request' => 'series/series',
			'priority' => 5,
			'route' => ['serie', '{id}'],
		],
		'/sitemap.xml' => [
			'request' => 'main/sitemap',
			'priority' => 5,
			'route' => ['sitemap.xml'],
		],
		'/tache/{id}' => [
			'request' => 'series/task',
			'priority' => 5,
			'route' => ['tache', '{id}'],
		],
		'/tutoriels' => [
			'request' => 'learning/tutorial',
			'priority' => 5,
			'route' => ['tutoriels'],
		],
		'/user/avatar/modele/{id}' => [
			'request' => 'user/avatarUpload',
			'priority' => 5,
			'route' => ['user', 'avatar', 'modele', '{id}'],
		],
	],
	'HEAD' => [
	],
	'POST' => [
		'/@module/learning/Date/doQuick' => [
			'request' => 'learning/date',
			'priority' => 5,
			'route' => ['@module', 'learning', 'Date', 'doQuick'],
		],
		'/@module/learning/Date/quick' => [
			'request' => 'learning/date',
			'priority' => 5,
			'route' => ['@module', 'learning', 'Date', 'quick'],
		],
		'/@module/learning/DateEnrollment/doQuick' => [
			'request' => 'learning/dateEnrollment',
			'priority' => 5,
			'route' => ['@module', 'learning', 'DateEnrollment', 'doQuick'],
		],
		'/@module/learning/DateEnrollment/quick' => [
			'request' => 'learning/dateEnrollment',
			'priority' => 5,
			'route' => ['@module', 'learning', 'DateEnrollment', 'quick'],
		],
		'/@module/map/Bed/doQuick' => [
			'request' => 'map/bed',
			'priority' => 5,
			'route' => ['@module', 'map', 'Bed', 'doQuick'],
		],
		'/@module/map/Bed/quick' => [
			'request' => 'map/bed',
			'priority' => 5,
			'route' => ['@module', 'map', 'Bed', 'quick'],
		],
		'/@module/plant/Forecast/doQuick' => [
			'request' => 'plant/forecast',
			'priority' => 5,
			'route' => ['@module', 'plant', 'Forecast', 'doQuick'],
		],
		'/@module/plant/Forecast/quick' => [
			'request' => 'plant/forecast',
			'priority' => 5,
			'route' => ['@module', 'plant', 'Forecast', 'quick'],
		],
		'/@module/plant/Variety/doQuick' => [
			'request' => 'plant/variety',
			'priority' => 5,
			'route' => ['@module', 'plant', 'Variety', 'doQuick'],
		],
		'/@module/plant/Variety/quick' => [
			'request' => 'plant/variety',
			'priority' => 5,
			'route' => ['@module', 'plant', 'Variety', 'quick'],
		],
		'/@module/product/Product/doQuick' => [
			'request' => 'product/product',
			'priority' => 5,
			'route' => ['@module', 'product', 'Product', 'doQuick'],
		],
		'/@module/product/Product/quick' => [
			'request' => 'product/product',
			'priority' => 5,
			'route' => ['@module', 'product', 'Product', 'quick'],
		],
		'/@module/product/Unit/doQuick' => [
			'request' => 'product/unit',
			'priority' => 5,
			'route' => ['@module', 'product', 'Unit', 'doQuick'],
		],
		'/@module/product/Unit/quick' => [
			'request' => 'product/unit',
			'priority' => 5,
			'route' => ['@module', 'product', 'Unit', 'quick'],
		],
		'/@module/sequence/Crop/doQuick' => [
			'request' => 'sequence/crop',
			'priority' => 5,
			'route' => ['@module', 'sequence', 'Crop', 'doQuick'],
		],
		'/@module/sequence/Crop/quick' => [
			'request' => 'sequence/crop',
			'priority' => 5,
			'route' => ['@module', 'sequence', 'Crop', 'quick'],
		],
		'/@module/sequence/Sequence/doQuick' => [
			'request' => 'sequence/sequence',
			'priority' => 5,
			'route' => ['@module', 'sequence', 'Sequence', 'doQuick'],
		],
		'/@module/sequence/Sequence/quick' => [
			'request' => 'sequence/sequence',
			'priority' => 5,
			'route' => ['@module', 'sequence', 'Sequence', 'quick'],
		],
		'/@module/series/Cultivation/doQuick' => [
			'request' => 'series/cultivation',
			'priority' => 5,
			'route' => ['@module', 'series', 'Cultivation', 'doQuick'],
		],
		'/@module/series/Cultivation/quick' => [
			'request' => 'series/cultivation',
			'priority' => 5,
			'route' => ['@module', 'series', 'Cultivation', 'quick'],
		],
		'/@module/series/Series/doQuick' => [
			'request' => 'series/series',
			'priority' => 5,
			'route' => ['@module', 'series', 'Series', 'doQuick'],
		],
		'/@module/series/Series/quick' => [
			'request' => 'series/series',
			'priority' => 5,
			'route' => ['@module', 'series', 'Series', 'quick'],
		],
		'/@module/series/Task/doQuick' => [
			'request' => 'series/task',
			'priority' => 5,
			'route' => ['@module', 'series', 'Task', 'doQuick'],
		],
		'/@module/series/Task/quick' => [
			'request' => 'series/task',
			'priority' => 5,
			'route' => ['@module', 'series', 'Task', 'quick'],
		],
		'/@module/structure/Member/doQuick' => [
			'request' => 'structure/member',
			'priority' => 5,
			'route' => ['@module', 'structure', 'Member', 'doQuick'],
		],
		'/@module/structure/Member/quick' => [
			'request' => 'structure/member',
			'priority' => 5,
			'route' => ['@module', 'structure', 'Member', 'quick'],
		],
		'/@module/toolbox/CompositionLine/doQuick' => [
			'request' => 'toolbox/compositionLine',
			'priority' => 5,
			'route' => ['@module', 'toolbox', 'CompositionLine', 'doQuick'],
		],
		'/@module/toolbox/CompositionLine/quick' => [
			'request' => 'toolbox/compositionLine',
			'priority' => 5,
			'route' => ['@module', 'toolbox', 'CompositionLine', 'quick'],
		],
		'/@module/toolbox/CompositionSpecies/doQuick' => [
			'request' => 'toolbox/compositionSpecies',
			'priority' => 5,
			'route' => ['@module', 'toolbox', 'CompositionSpecies', 'doQuick'],
		],
		'/@module/toolbox/CompositionSpecies/quick' => [
			'request' => 'toolbox/compositionSpecies',
			'priority' => 5,
			'route' => ['@module', 'toolbox', 'CompositionSpecies', 'quick'],
		],
		'/@module/website/Menu/doQuick' => [
			'request' => 'website/menu',
			'priority' => 5,
			'route' => ['@module', 'website', 'Menu', 'doQuick'],
		],
		'/@module/website/Menu/quick' => [
			'request' => 'website/menu',
			'priority' => 5,
			'route' => ['@module', 'website', 'Menu', 'quick'],
		],
		'/@module/website/News/doQuick' => [
			'request' => 'website/news',
			'priority' => 5,
			'route' => ['@module', 'website', 'News', 'doQuick'],
		],
		'/@module/website/News/quick' => [
			'request' => 'website/news',
			'priority' => 5,
			'route' => ['@module', 'website', 'News', 'quick'],
		],
		'/@module/work/Method/doQuick' => [
			'request' => 'work/method',
			'priority' => 5,
			'route' => ['@module', 'work', 'Method', 'doQuick'],
		],
		'/@module/work/Method/quick' => [
			'request' => 'work/method',
			'priority' => 5,
			'route' => ['@module', 'work', 'Method', 'quick'],
		],
		'/@module/work/Supplier/doQuick' => [
			'request' => 'work/supplier',
			'priority' => 5,
			'route' => ['@module', 'work', 'Supplier', 'doQuick'],
		],
		'/@module/work/Supplier/quick' => [
			'request' => 'work/supplier',
			'priority' => 5,
			'route' => ['@module', 'work', 'Supplier', 'quick'],
		],
		'/@module/work/Tool/doQuick' => [
			'request' => 'work/tool',
			'priority' => 5,
			'route' => ['@module', 'work', 'Tool', 'doQuick'],
		],
		'/@module/work/Tool/quick' => [
			'request' => 'work/tool',
			'priority' => 5,
			'route' => ['@module', 'work', 'Tool', 'quick'],
		],
		'/api/oauth/authorize' => [
			'request' => 'api/authorize',
			'priority' => 5,
			'route' => ['api', 'oauth', 'authorize'],
		],
		'/api/oauth/token' => [
			'request' => 'api/token',
			'priority' => 5,
			'route' => ['api', 'oauth', 'token'],
		],
		'/formation/lecon/{id}/:doPreview' => [
			'request' => 'learning/lesson',
			'priority' => 5,
			'route' => ['formation', 'lecon', '{id}', ':doPreview'],
		],
		'/formation/{id}/:doCustomize' => [
			'request' => 'learning/course',
			'priority' => 5,
			'route' => ['formation', '{id}', ':doCustomize'],
		],
		'/formation/{id}/:doPreview' => [
			'request' => 'learning/course',
			'priority' => 5,
			'route' => ['formation', '{id}', ':doPreview'],
		],
		'/inscription' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription'],
		],
		'/inscription/accompagnement' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription', 'accompagnement'],
		],
		'/inscription/client' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription', 'client'],
		],
		'/inscription/formation' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription', 'formation'],
		],
		'/inscription/producteur' => [
			'request' => 'user/signUp',
			'priority' => 5,
			'route' => ['inscription', 'producteur'],
		],
		'/public/{domain}/:doContact' => [
			'request' => 'website/public',
			'priority' => 5,
			'route' => ['public', '{domain}', ':doContact'],
		],
		'/public/{domain}/:doNewsletter' => [
			'request' => 'website/public',
			'priority' => 5,
			'route' => ['public', '{domain}', ':doNewsletter'],
		],
		'/public/{domain}/customize' => [
			'request' => 'website/public',
			'priority' => 1,
			'route' => ['public', '{domain}', 'customize'],
		],
		'/reseau/{fqn}/:doCustomize' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', ':doCustomize'],
		],
		'/reseau/{fqn}/:doPreview' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', ':doPreview'],
		],
		'/reseau/{fqn}/formations/{date}/:doCancel' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'formations', '{date}', ':doCancel'],
		],
		'/reseau/{fqn}/formations/{date}/:doCommit' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'formations', '{date}', ':doCommit'],
		],
		'/reseau/{fqn}/formations/{date}/:doCreate' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'formations', '{date}', ':doCreate'],
		],
		'/reseau/{fqn}/inscription/{token}/:doCancel' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'inscription', '{token}', ':doCancel'],
		],
		'/reseau/{fqn}/inscription/{token}/avis/:doDelete' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'inscription', '{token}', 'avis', ':doDelete'],
		],
		'/reseau/{fqn}/inscription/{token}/avis/:doReview' => [
			'request' => 'learning/public',
			'priority' => 5,
			'route' => ['reseau', '{fqn}', 'inscription', '{token}', 'avis', ':doReview'],
		],
	],
	'PUT' => [
	],
]);
?>