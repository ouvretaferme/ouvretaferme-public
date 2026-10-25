class StructureCommunityStartAi extends MainOnboarding {

	// MainOnboarding fait avancer l'assistant sur Entrée dans un champ, sans soumettre le
	// formulaire : la saisie de l'étape serait perdue. Les deux avances voulues — passer l'étape,
	// et l'enregistrement, qui recharge l'écran — n'empruntent pas ce chemin.
	static next() {
	}

	static init() {

		// ⚠️ **L'adresse ne garde pas l'étape.** Le serveur s'en est déjà servi pour ouvrir le
		// parcours au bon endroit ; la laisser rendrait l'étape collante — un rechargement, un
		// favori ou un partage de l'adresse rouvriraient toujours celle-là, même une fois faite.
		// `history.removeArgument()` est le mécanisme du dépôt, celui que `Lime.Tab.restore()`
		// emploie pour `tab`, et il remplace l'entrée d'historique au lieu d'en ajouter une.
		history.removeArgument('demarrage');

		// La première promotion est rendue par le serveur sous `name[1]` : une rangée ajoutée qui
		// repartirait de 1 porterait le même nom, et son envoi écraserait la première.
		this.index = 1;

	}

}

// Passer une étape avance d'un cran, sans rien enregistrer. La dernière n'a pas ce lien : passer
// n'y mènerait nulle part, et son pied porte à la place un lien qui ferme le parcours pour de bon,
// avec sa propre action. Ce délégué ne la voit donc jamais.
document.delegateEventListener('click', '.onboarding [data-action="onboarding-skip"]', function() {
	StructureCommunityStartAi.goTo(StructureCommunityStartAi.step + 1);
});

// La croix retire le bandeau de l'écran et rien de plus : l'adresse qui l'a demandé ne revient
// pas, donc la visite suivante arrive sans lui.
document.delegateEventListener('click', '[data-action="onboarding-welcome-close"]', function() {
	this.closest('[data-onboarding-welcome]').remove();
});

// « Continuer » d'une étape qui n'enregistre rien : elle s'est enregistrée à chaque geste, il ne
// reste qu'à avancer. Les étapes à formulaire, elles, avancent par leur rechargement.
document.delegateEventListener('click', '.onboarding [data-action="onboarding-next"]', function() {
	StructureCommunityStartAi.goTo(StructureCommunityStartAi.step + 1);
});

document.delegateEventListener('click', '.onboarding [data-action="onboarding-add-row"]', function() {

	const step = this.closest('.onboarding-step');

	StructureCommunityStartAi.addRow(step.qs('[data-onboarding-template]'), step.qs('[data-onboarding-rows]'));

});

document.delegateEventListener('click', '.onboarding [data-action="onboarding-remove-row"]', function() {
	StructureCommunityStartAi.removeRow(this.closest('[data-index]'));
});

// Le lien fait toujours entrer un membre ordinaire : il disparaît quand on invite l'équipe.
document.delegateEventListener('change', '.onboarding [name="audience"]', function() {

	this.closest('.onboarding-step').qs('[data-onboarding-links]').classList.toggle('hide', this.value === 'staff');

});
