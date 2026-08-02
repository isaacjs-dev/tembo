import './bootstrap';
import '@fontsource-variable/plus-jakarta-sans';
import 'material-symbols/outlined.css';

import Alpine from 'alpinejs';
import jsQR from 'jsqr';
import Sortable from 'sortablejs';
import { OmrBrowserEngine, OmrEngine } from './omr-core/index';

let chartPromise;
let konvaPromise;
let pdfPromise;

window.loadChart = () => {
    chartPromise ??= import('chart.js/auto').then(({ default: Chart }) => {
        window.Chart = Chart;
        return Chart;
    });

    return chartPromise;
};

window.loadKonva = () => {
    konvaPromise ??= import('konva').then(({ default: Konva }) => {
        window.Konva = Konva;
        return Konva;
    });

    return konvaPromise;
};

window.loadPdfJs = () => {
    pdfPromise ??= Promise.all([
        import('pdfjs-dist/build/pdf.mjs'),
        import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
    ]).then(([pdfjsLib, { default: workerUrl }]) => {
        pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;
        window.pdfjsLib = pdfjsLib;
        return pdfjsLib;
    });

    return pdfPromise;
};

window.Alpine = Alpine;
window.jsQR = jsQR;
window.Sortable = Sortable;
window.OmrEngine = OmrEngine;
window.OmrBrowserEngine = OmrBrowserEngine;

Alpine.start();
