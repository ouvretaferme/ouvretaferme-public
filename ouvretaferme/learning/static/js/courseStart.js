/**
 * Les lignes de leçons du premier module, dans le parcours de création d'une formation. Le reste du
 * parcours est rendu par le serveur, étape par étape : ce script n'ajoute et ne retire que des lignes.
 *
 * Une ligne ajoutée prend l'index qui suit le dernier donné (`data-index` de la liste), jamais le
 * nombre de lignes : après un retrait, deux lignes porteraient le même `lessonName[<index>]`, et le
 * refus de l'une s'afficherait sous l'autre.
 */
class LearningCourseStart {

	static add(button) {

		// La liste précède le bouton et le gabarit le suit (`CourseStartUi::getStepModule()`).
		const list = button.previousElementSibling;
		const index = parseInt(list.dataset.index);

		list.dataset.index = index + 1;

		list.insertAdjacentHTML('beforeend', button.nextElementSibling.innerHTML.replaceAll('__i__', index));

		list.lastElementChild.qs('input').focus();

	}

	static remove(link) {
		link.closest('.onboarding-row').remove();
	}

}
