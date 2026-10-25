<?php
namespace map;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return match($fqn) {

			'Configuration::defaultBedWidth.size' => s("La largeur de planche par défaut ne peut pas être inférieure à 5 cm."),

			'Plot::zone.check', 'Plot::zone.owned' => s("Cette parcelle n'existe pas ou ne vous appartient pas."),
			'Plot::seasonLast.consistency' => s("Merci d'être cohérent entre la saison de début et la saison de fin."),
			'Plot::area.check' => s("Veuillez indiquer la surface du jardin."),
			'Plot::name.check' => s("Veuillez choisir un nom."),
			'Plot::decorGreenhouse.check' => s("Ce modèle d'abri n'existe pas."),
			'Plot::length.check', 'Plot::length.required' => s("Veuillez indiquer la longueur de l'abri."),
			'Plot::width.check', 'Plot::width.required' => s("Veuillez indiquer la largeur de l'abri."),
			'Plot::length.drawn' => s("Ce contour est trop étroit pour un abri, redessinez-le sur la carte."),
			'Plot::coordinates.check', 'Plot::coordinates.ring' => s("Ce contour n'a pas pu être enregistré. Il doit compter entre 3 et {value} points différents, chacun à une longitude et une latitude valides.", MapSetting::SHAPE_POINTS_MAX),
			'Plot::coordinates.drawing' => s("Dessinez le jardin sur la carte, ou choisissez de le dessiner plus tard."),
			'Plot::coordinates.redraw' => s("Redessinez le jardin sur la carte avant d'enregistrer."),
			'Plot::coordinates.drawingMap' => s("Dessinez le jardin sur la carte avant de le créer."),
			'Plot::coordinates.filled' => s("Ces planches sont posées à même la parcelle, elles en suivent le contour."),
			'Plot::placement.check', 'Plot::placement.ring' => s("La copie n'a pas pu être posée à cet endroit. Rapprochez-la du jardin d'origine."),
			'Plot::placement.shape' => s("Seul un jardin dessiné sur la carte se pose en copie à côté de lui."),
			'Plot::ids.check' => s("Cette parcelle a changé depuis l'affichage de la page, l'ordre n'a pas été enregistré. Rechargez la page et recommencez."),
			'Plot::onboarding.max' => s("Le démarrage guidé accepte au plus {value} jardins : créez les premiers ici, vous ajouterez les autres un peu plus tard.", MapSetting::$onboardingPlotsMax),
			'Plot::deleteUsed' => s("Vous ne pouvez pas supprimer un jardin qui a déjà été cultivé. Vous devez soit modifier les dates d'exploitation du jardin, soit supprimer préalablement les séries qui s'y trouvent."),

			'Zone::area.check' => s("Veuillez indiquer la surface de la parcelle."),
			'Zone::coordinates.drawing' => s("Dessinez la parcelle sur la carte, ou choisissez de la dessiner plus tard."),
			'Zone::coordinates.drawingMap' => s("Dessinez la parcelle sur la carte avant de la créer."),
			'Zone::coordinates.redraw' => s("Redessinez la parcelle sur la carte avant d'enregistrer."),
			'Zone::coordinates.check', 'Zone::coordinates.ring' => s("Ce contour n'a pas pu être enregistré. Il doit compter entre 3 et {value} points différents, chacun à une longitude et une latitude valides.", MapSetting::SHAPE_POINTS_MAX),
			'Zone::decorGrass.check' => s("Cette herbe n'est pas reconnue."),
			'Zone::decorMeadow.check' => s("Ce pré n'est pas reconnu."),
			'Zone::decorSoil.check' => s("Ce sol n'est pas reconnu."),
			'Zone::name.check' => s("Veuillez choisir un nom pour votre parcelle."),
			'Zone::seasonLast.consistency' => s("Merci d'être cohérent entre la saison de début et la saison de fin."),
			'Zone::deleteUsed' => s("Vous ne pouvez pas supprimer une parcelle qui a déjà été cultivée. Vous devez soit modifier les dates d'exploitation de la parcelle, soit supprimer préalablement les séries qui s'y trouvent."),

			'Bed::plot.check', 'Bed::plot.owned' => s("Ce jardin n'existe pas ou ne vous appartient pas."),
			'Bed::plot.duplicate' => s("Une planche de ce jardin porte déjà le nom d'une des planches déplacées."),
			'Bed::name.empty' => s("Veuillez donner un nom à cette planche."),
			'Bed::name.check' => s("Le nom d'une planche doit faire entre 1 et {value} lettres.", BedUi::p('name')->range[1]),
			'Bed::name.duplicate' => s("Ce nom est déjà utilisé par une autre planche."),
			'Bed::names.check' => s("Vous devez donner un nom à chaque planche."),
			'Bed::names.different' => s("Vous devez donner un nom différent à chaque planche."),
			'Bed::ids.check' => s("Veuillez sélectionner au moins une planche."),
			'Bed::number.check' => s("Indiquez un nombre de planches entre 1 et {value}.", MapSetting::$onboardingBedsMax),
			'Bed::length.check' => s("Veuillez indiquer la longueur des planches, en mètres entiers."),
			'Bed::width.check' => s("Veuillez indiquer la largeur des planches, en centimètres entiers."),
			'Bed::size.empty' => s("Indiquez au moins une dimension à changer."),
			'Bed::alleyWidth.check' => s("La largeur de passe-pied doit être un nombre entier de centimètres, ou rester vide."),
			'Bed::seasonLast.consistency' => s("Merci d'être cohérent entre la saison de début et la saison de fin."),
			'Bed::canNotDraw' => s("Vous ne pouvez pas dessiner ces planches car le jardin ou la parcelle ne sont pas cartographiés."),
			'Bed::draw.invalid' => s("Le rang de planches dessiné n'a pas pu être lu, dessinez-le à nouveau."),

			'Draw::corner.check', 'Draw::corner.placed', 'Draw::corner.bounded', 'Draw::angle.check', 'Draw::angle.placed' => s("Vous n'avez pas dessiné les planches sur la carte."),
			'Draw::angle.inside' => s("Les planches dépassent de leur emplacement. Déplacez le rang ou réduisez le nombre de planches."),
			'Draw::bed.spaced' => s("Il faut au moins deux planches dessinées pour recommencer leur dessin."),
			'Draw::bed.sized' => s("Une planche sans longueur ou sans largeur ne peut pas être dessinée, indiquez d'abord ses dimensions."),
			'Draw::season.check' => s("Cette saison n'est pas ouverte dans votre structure."),
			'Draw::season.exploited' => s("Les planches ne sont pas exploitées pendant cette saison, leur dessin ne peut pas y être modifié."),

			'Decor::kit.check', 'Decor::kit.format', 'Decor::kit.default' => s("Cet élément de décor n'est pas reconnu."),
			'Decor::structure.adopted' => s("Le décor de ce plan vient d'être enregistré depuis une autre fenêtre. Rechargez la page pour le retrouver."),
			'Decor::structure.unadopted' => s("Le décor de ce plan a été modifié depuis une autre fenêtre. Rechargez la page pour le retrouver."),
			'Decor::default.check', 'Decor::seed.check' => s("Le décor par défaut n'a pas pu être enregistré. Rechargez la page et recommencez."),
			'Decor::coordinates.check', 'Decor::angle.check', 'Decor::size.check', 'Decor::width.check' => s("La position de cet élément n'a pas pu être enregistrée. Rechargez la page et recommencez."),
			'Decor::coordinates.drawn' => s("Dessinez d'abord une parcelle sur la carte pour placer le décor autour d'elle."),
			'Decor::coordinates.near', 'Decor::trace.near' => s("Le décor se place à moins de {value} mètres de vos parcelles.", \util\TextUi::number(MapSetting::DECOR_NEAR, 0)),
			'Decor::trace.check' => s("Ce tracé n'a pas pu être enregistré. Il doit compter entre 2 et {value} points.", \util\TextUi::number(MapSetting::DECOR_TRACE_POINTS, 0)),
			'Decor::trace.collapsed' => s("Ce tracé n'a pas de longueur. Dessinez-le entre deux points distincts."),
			'Decor::trace.kept' => s("Ce tracé garde au moins deux points. Pour l'effacer, retirez-le."),
			'Decor::width.traced' => s("Une largeur se donne à un chemin ou à un cours d'eau dessiné."),
			'Decor::zone.check', 'Decor::zone.owned' => s("Cette parcelle n'existe pas ou ne vous appartient pas."),
			'Decor::plot.check', 'Decor::plot.owned' => s("Ce jardin n'existe pas ou ne vous appartient pas."),
			'Decor::zone.outlined', 'Decor::plot.outlined' => s("Dessinez d'abord le contour de cet espace sur la carte pour pouvoir le clôturer."),
			'Decor::zone.alone', 'Decor::plot.alone' => s("Cet espace a déjà sa clôture. Changez-la plutôt que d'en ajouter une autre."),
			'Decor::plot.exclusive' => s("Une clôture entoure une parcelle ou un jardin, pas les deux à la fois."),
			'Decor::model.check' => s("Ce modèle de clôture n'est pas reconnu."),
			'Decor::model.fence', 'Decor::hedge.fence', 'Decor::gate.check', 'Decor::gate.fence' => s("Une clôture, une haie ou un portail se pose le long d'un tracé ou autour d'un espace."),
			'Decor::hedge.enclosure' => s("Choisissez une clôture, une haie, ou les deux pour entourer cet espace."),
			'Decor::structure.limit' => s("Votre plan compte déjà {value} éléments de décor, le maximum. Retirez-en un pour en ajouter un autre.", MapSetting::DECOR_MAX),
			'Decor::structure.triangles' => s("Votre décor est déjà aussi chargé que la scène peut le montrer. Retirez un bosquet, un verger ou un bâtiment pour en poser un autre."),
			'Decor::trace.limit' => s("Votre décor compte trop de haies, de clôtures et d'autres tracés. Raccourcissez-en un ou retirez-en un avant d'en poser ou d'en allonger un autre."),
			'Decor::layer.check', 'Decor::layer.owned' => s("Ce calque n'existe plus. Rechargez la page et recommencez."),
			'Decor::kit.irrigation', 'Decor::layer.irrigation' => s("Le réseau d'irrigation n'est pas disponible."),
			'Decor::diameter.check' => s("Le diamètre d'un tuyau se donne en millimètres, entre {min} et {max}.", ['min' => Decor::model()->getPropertyRange('diameter')[0], 'max' => Decor::model()->getPropertyRange('diameter')[1]]),
			'Decor::laying.check' => s("Indiquez si le tuyau est posé au sol ou enterré."),
			'Decor::diameter.pipe', 'Decor::laying.pipe' => s("Un diamètre ou une pose se donne à un tuyau dessiné."),
			'Decor::building.check', 'Decor::building.owned' => s("Ce bâtiment n'existe plus. Rechargez la page et recommencez."),
			'Decor::kit.building' => s("Un bâtiment de la structure se pose depuis sa vignette, dans la rangée des bâtiments."),
			'Decor::object.check', 'Decor::object.owned' => s("Cet élément n'existe plus. Rechargez la page et recommencez."),
			'Decor::kit.object' => s("Un élément importé se pose depuis sa vignette, dans sa rangée."),
			'DecorObject::structure.limit' => s("Votre plan compte déjà {value} éléments importés, le maximum. Supprimez-en un de sa rangée pour en importer un autre.", MapSetting::DECOR_OBJECT_MAX),
			'DecorObject::name.check' => s("Donnez à l'élément un nom, de {value} caractères au plus.", DecorObject::model()->getPropertyRange('name')[1]),

			'DecorBuilding::type.check' => s("Ce type de bâtiment n'est pas reconnu."),
			'DecorBuilding::structure.triangles' => s("Votre décor est déjà aussi chargé que la scène peut le montrer. Retirez un bosquet, un verger ou un bâtiment pour personnaliser ce hangar."),
			'DecorBuilding::type.decor' => s("Un hangar se personnalise depuis un hangar posé sur le plan."),
			'DecorBuilding::name.check' => s("Donnez au bâtiment un nom, de {value} caractères au plus.", DecorBuilding::model()->getPropertyRange('name')[1]),
			'DecorBuilding::length.check', 'DecorBuilding::length.type', 'DecorBuilding::length.bounds' => s("Indiquez une longueur que ce bâtiment admet, en mètres."),
			'DecorBuilding::width.check', 'DecorBuilding::width.type', 'DecorBuilding::width.bounds' => s("Indiquez une largeur que ce bâtiment admet, en mètres."),
			'DecorBuilding::height.check', 'DecorBuilding::height.type', 'DecorBuilding::height.bounds' => s("Indiquez une hauteur des murs que ce bâtiment admet, en mètres."),
			'DecorBuilding::settings.check', 'DecorBuilding::settings.choices' => s("Ces choix ne sont pas reconnus. Rechargez la page et recommencez."),
			'DecorBuilding::settings.excluded' => s("Ces choix ne vont pas ensemble. Changez la couverture, sa teinte ou le nombre de fenêtres."),
			'DecorBuilding::structure.limit' => s("Votre plan compte déjà {value} bâtiments, le maximum. Supprimez-en un de la rangée pour en ajouter un autre.", MapSetting::DECOR_BUILDING_MAX),
			'DecorBuilding::decorChanged' => s("Ce hangar a été modifié depuis une autre fenêtre. Rechargez la page pour le retrouver."),

			'DecorUpload::file.check' => s("Choisissez le fichier .glb de votre modèle."),
			'DecorUpload::file.throttled' => s("Vous ne pouvez pas envoyer plus de {value} fichiers en 24 heures, en comptant chaque « Valider » et chaque ajout. Ce fichier n'a pas été envoyé.", MapSetting::DECOR_UPLOAD_LIMIT),
			'DecorUpload::file.unavailable' => s("L'import est indisponible pour le moment, réessayez plus tard."),
			'DecorUpload::file.conformity' => fn(string $refusal, array $values, string $family) => AlertUi::getDecorUploadRefusal($refusal, $values, $family),
			'DecorUpload::commitment.check' => s("Cochez l'engagement pour envoyer votre modèle."),
			'DecorUpload::reason.check', 'DecorUpload::reason.empty' => s("Choisissez le motif du retrait."),
			'DecorUpload::reasonText.empty' => s("Écrivez la précision que l'auteur lira dans l'e-mail."),
			'DecorUpload::reasonText.check' => s("La précision tient en {value} caractères au plus.", DecorUpload::model()->getPropertyRange('reasonText')[1]),
			'DecorUpload::stale' => s("Ce modèle a changé depuis l'affichage de la liste. Rechargez la page."),
			'DecorUpload::keepAllNone' => s("Aucun des modèles affichés n'est encore à examiner. Rechargez la page pour revoir la file."),

			'DecorLayer::name.check' => s("Donnez au calque un nom, de {value} caractères au plus.", DecorLayer::model()->getPropertyRange('name')[1]),
			'DecorLayer::type.check' => s("Ce type de calque n'est pas reconnu."),
			'DecorLayer::type.irrigation' => s("Les calques d'irrigation ne sont pas disponibles."),
			'DecorLayer::structure.limit' => s("Votre plan compte déjà {value} calques, le maximum. Supprimez-en un pour en ajouter un autre.", MapSetting::DECOR_LAYER_MAX),

			'Configuration::decorGrass.check' => s("Cette herbe n'est pas reconnue."),
			'Configuration::decorMeadow.check' => s("Ce pré n'est pas reconnu."),
			'Configuration::decorSoil.check' => s("Ce sol n'est pas reconnu."),

			default => NULL

		};

	}

	/**
	 * Le refus d'un bâtiment téléversé, par son code (`refusals` de `base/scene/upload/decor.json`) : chaque code a sa branche,
	 * et un code sans branche lève une erreur plutôt qu'un message vide (`DecorUploadAi.t.php`). Les phrases que le bâtiment
	 * partage avec l'avatar sont les siennes, pour que les deux éditeurs disent le même refus de la même façon. Un élément
	 * d'Eau, de Nature ou de Matériel n'a ni murs ni toit : les phrases qui en parlent ont la leur.
	 */
	public static function getDecorUploadRefusal(string $refusal, array $values, string $family): string {

		$building = ($family === DecorUploadLib::FAMILY_BUILDING);

		if($refusal === 'weight') {
			// Le poids s'arrondit au dixième supérieur, comme celui de l'avatar (`\user\AlertUi::getAvatarUploadRefusal()`)
			return \user\AlertUi::getAvatarUploadWeight(\util\TextUi::size(\util\TextUi::number(ceil($values['size'] / 104857.6) / 10, NULL)), \util\TextUi::size($values['max']));
		}

		$reason = match($refusal) {
			'format' => s("Il ne se lit pas comme un fichier .glb. Exportez votre modèle au format .glb."),
			'external' => s("Il demande des fichiers ou des extensions qu'il ne contient pas. Exportez-le en un seul fichier .glb, sans extension."),
			'texture' => s("Une texture est posée sur une pièce. Chaque pièce prend une seule couleur."),
			'rig' => s("Il porte une armature, une animation ou des formes animées. Exportez des pièces fixes."),
			'nodes' => s("Il a trop de pièces ou trop d'objets imbriqués."),
			'piece' => $building ?
				s("La pièce « {name} » n'est pas une pièce de bâtiment. Nommez-la parmi {pieces}.", ['name' => encode($values['name']), 'pieces' => $values['pieces']]) :
				s("La pièce « {name} » n'est pas une pièce de cet élément. Nommez-la parmi {pieces}.", ['name' => encode($values['name']), 'pieces' => $values['pieces']]),
			'missing' => $building ?
				s("Aucune pièce ne s'appelle « {value} ». Le bâtiment porte au moins cette pièce.", $values['piece']) :
				s("Aucune pièce ne s'appelle « {value} ». L'élément porte au moins cette pièce.", $values['piece']),
			'triangles' => s("Il compte {count} triangles, pour {max} au plus.", ['count' => \util\TextUi::number($values['count']), 'max' => \util\TextUi::number($values['max'])]),
			'colors' => s("Il porte {count} couleurs, pour {max} au plus.", ['count' => \util\TextUi::number($values['count']), 'max' => \util\TextUi::number($values['max'])]),
			'bounds' => s("Il dépasse {length} m de long ou {width} m de large. Vérifiez son échelle : un mètre de votre logiciel vaut un mètre ici.", ['length' => \util\TextUi::number($values['length'], NULL), 'width' => \util\TextUi::number($values['width'], NULL)]),
			'ground' => $building ?
				s("Il ne repose pas au sol. Placez le bas des murs à la hauteur 0.") :
				s("Il ne repose pas au sol. Placez son point le plus bas à la hauteur 0."),
			'anchor' => $building ?
				s("Le repère « {value} » est placé hors du bâtiment. Posez-le contre ses murs ou sur son toit.", encode($values['name'])) :
				s("Le repère « {value} » est placé hors de l'élément. Posez-le sur lui.", encode($values['name'])),
		};

		$title = $building ? s("Ce fichier ne peut pas devenir votre bâtiment.") : s("Ce fichier ne peut pas devenir un élément de votre décor.");

		return '<b>'.$title.'</b><br/>'.$reason.'<br/>'.s("Donnez ce message à Claude, il corrigera le modèle.");

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'Configuration::updatedCrop' => s("La configuration des cultures a bien été mise à jour !"),

			'Draw::created' => s("Le dessin des planches a bien été enregistré."),
			'Draw::deleted' => s("Le dessin des planches a bien été effacé."),
			'Draw::respaced' => s("Les planches ont retrouvé leur espacement."),

			'Bed::createdCollection' => s("Les planches ont bien été ajoutées."),
			'Bed::updatedCollection' => s("Les planches ont bien été modifiées."),
			'Bed::deletedCollection' => s("Les planches ont bien été supprimées."),

			'Zone::onboarded' => s("Votre plan est prêt ! Tout se modifie d'un clic ici : renommer, ajouter des planches, dessiner sur la carte."),
			'Zone::created' => s("La parcelle a bien été ajoutée."),
			'Zone::updated' => s("La parcelle a bien été mise à jour."),
			'Zone::deleted' => s("La parcelle a bien été supprimée."),

			'Plot::created' => s("Le jardin a bien été ajouté."),
			'Plot::updated' => s("Le jardin a bien été mis à jour."),
			'Plot::deleted' => s("Le jardin a bien été supprimé."),

			'Plot::greenhouseAdded' => s("Le jardin est désormais couvert par un abri."),
			'Plot::greenhouseRemoved' => s("L'abri a bien été retiré, le jardin est désormais en plein champ."),

			'Plot::duplicated' => s("Le jardin a bien été dupliqué."),

			'DecorUpload::keptAll' => s("Les modèles sont validés."),
			'DecorUpload::withdrawn' => s("Le modèle est retiré."),
			'DecorUpload::chosen' => s("Le modèle est sélectionné."),

			default => NULL

		};

	}

}
?>
