'use strict';

/**
 * Lit le XML attaché à un Factur-X (factur-x.xml) et l'écrit dans le fichier de destination.
 *
 * Sert à l'envoi à la plateforme agréée : le PDF stocké ne part que si son XML est encore celui que
 * la facture produirait aujourd'hui (selling\InvoicingLib::getFacturx()).
 *
 * Code de sortie 2 quand le PDF n'a pas de factur-x.xml : c'est un PDF simple, pas une erreur.
 *
 * Usage : node facturxReadAi.js --source=in.pdf --destination=factur-x.xml
 */

const argv = require('minimist')(process.argv.slice(2));
const fs = require('fs');
const { PDFDocument, PDFName, PDFDict, PDFArray, PDFRawStream, decodePDFRawStream } = require('pdf-lib');

const FILENAME = 'factur-x.xml';

(async() => {

	const doc = await PDFDocument.load(fs.readFileSync(argv.source), {updateMetadata: false});

	const names = doc.catalog.lookupMaybe(PDFName.of('Names'), PDFDict);
	const embeddedFiles = names ? names.lookupMaybe(PDFName.of('EmbeddedFiles'), PDFDict) : undefined;
	const entries = embeddedFiles ? embeddedFiles.lookupMaybe(PDFName.of('Names'), PDFArray) : undefined;

	if(entries === undefined) {
		process.exit(2);
	}

	// L'arbre alterne nom du fichier et spécification de fichier
	for(let i = 0; i + 1 < entries.size(); i += 2) {

		if(entries.lookup(i).decodeText() !== FILENAME) {
			continue;
		}

		const fileSpec = entries.lookup(i + 1, PDFDict);
		const stream = fileSpec.lookup(PDFName.of('EF'), PDFDict).lookup(PDFName.of('F'), PDFRawStream);

		fs.writeFileSync(argv.destination, Buffer.from(decodePDFRawStream(stream).decode()));
		return;

	}

	process.exit(2);

})().catch((error) => {
	console.error(error);
	process.exit(1);
});
