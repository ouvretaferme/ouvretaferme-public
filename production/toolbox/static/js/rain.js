class ToolboxRain {

	/**
	 * « Déjà ce jour-là » suit la date et le pluviomètre choisis. La pluie déjà saisie part avec la
	 * requête : le bloc revient avec elle, au lieu de la vider sous les doigts.
	 */
	static refreshDay(field) {

		const form = field.closest('form');

		new Ajax.Query(form)
			.url('/toolbox/rain:day')
			.body(new FormData(form))
			.fetch();

	}

}
