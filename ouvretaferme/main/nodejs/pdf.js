'use strict';

const argv = require('minimist')(process.argv.slice(2));
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const { PDFDocument } = require('pdf-lib');

// CLI Args
const url = argv.url;
const destination = argv.destination;
const headerTemplate = argv.header || null;
const title = argv.title || null;
const footerTemplate = argv.footer || null;
const landscape = !!(argv.landscape || false);
const width = argv.width || null; // Utilisé pour les tickets de caisse
const decodeTemplate = (template) => decodeURIComponent((template + '').replace(/\+/g, '%20'));

// Chromium imprime ses propres en-tête et pied — la date, le titre du document, la
// pagination — dès que displayHeaderFooter est vrai sans gabarit fourni. Demander une
// pagination seule faisait donc apparaître la date et le titre en haut de chaque page :
// les deux gabarits sont fournis, vides à défaut.
const headerFooterArgs = (headerTemplate || footerTemplate) ? {
	displayHeaderFooter: true,
	headerTemplate: headerTemplate ? decodeTemplate(headerTemplate) : '<span></span>',
	footerTemplate: footerTemplate ? decodeTemplate(footerTemplate) : '<span></span>',
	margin: {bottom: '1cm'}
} : {};

(async() => {

	const browser = await puppeteer.launch({
		executablePath: '/usr/bin/google-chrome',
		args: ['--no-sandbox']
	});

	const page = await browser.newPage();

	if(width) {
		const widthPx = Math.round(parseFloat(width) * 96 / 25.4);
		await page.setViewport({width: widthPx, height: 100});
	}

	// ERR_CERT_VERIFIER_CHANGED n'est pas un refus du certificat : Chrome abandonne les requêtes en
	// vol quand il reconfigure son vérificateur, et la même requête se relance.
	for(let attempt = 1; ; attempt++) {

		try {
			await page.goto(url, {
				waitUntil: 'networkidle0',
				timeout: 0,
			});
			break;
		} catch(error) {
			if(attempt === 3 || error.message.includes('net::ERR_CERT_VERIFIER_CHANGED') === false) {
				throw error;
			}
		}

	}

	// networkidle0 ne dit rien du rendu WebGL : la carte du plan de ferme pose ses couches
	// et ses étiquettes après le dernier octet reçu. Les pages concernées lèvent pdfWait,
	// puis pdfReady quand elles sont peintes ; les autres ne paient rien.
	if(await page.evaluate(() => window.pdfWait === true)) {
		await page.waitForFunction(() => window.pdfReady === true, {timeout: 15000}).catch(() => {});
	}

	const pdfOptions = {
		path: destination,
		printBackground: true,
		scale: 1,
		preferCSSPageSize: true,
		...headerFooterArgs,
		landscape,
	};

	if(width) {

		// media 'print' explicite : c'est ce que page.pdf() utilise en interne,
		// la mesure doit se faire dans le même contexte pour être fiable.
		await page.emulateMediaType('print');

		// scrollHeight ne descend jamais sous la hauteur du viewport (fixée à 100px ci-dessus),
		// donc la valeur mesurée ici reflète bien la hauteur réelle du contenu.
		// Note : passer width/height directement à page.pdf() produit des dimensions
		// permutées avec ce couple puppeteer-core/Chromium ; on passe donc par une
		// règle @page injectée, seul chemin fiable ici (comme pour les autres PDF).
		const heightPx = await page.evaluate(() => document.documentElement.scrollHeight);
		const heightMm = (heightPx * 25.4 / 96) + 5; // marge de sécurité contre l'arrondi

		await page.addStyleTag({content: '@page { size: ' + width + ' ' + heightMm + 'mm; margin: 0; }'});

	} else {

		pdfOptions.format = 'A4';

	}

	await page.pdf(pdfOptions);

	const buffer = fs.readFileSync(destination);
	const pdfDoc = await PDFDocument.load(buffer)
	pdfDoc.setTitle(decodeURIComponent((title + '').replace(/\+/g, '%20')))
	const pdfBytes = await pdfDoc.save()

	await fs.writeFileSync(destination, pdfBytes);

	await browser.close();


})();
