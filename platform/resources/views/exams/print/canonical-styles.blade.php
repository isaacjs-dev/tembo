@php
    $canonicalPageLayout = $canonicalPageLayout ?? ($document['layout'] ?? []);
    $canonicalMargins = $canonicalPageLayout['margins_mm'] ?? [15, 15, 15, 15];
    $canonicalOrientation = ($canonicalPageLayout['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
@endphp
<style>
    @if($canonicalEmitPageRule ?? true)
    @page {
        size: A4 {{ $canonicalOrientation }};
        margin: {{ $canonicalMargins[0] }}mm {{ $canonicalMargins[1] }}mm {{ $canonicalMargins[2] }}mm {{ $canonicalMargins[3] }}mm;
    }
    @endif

    .canonical-page { color: #111; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 11pt; line-height: 1.42; }
    .canonical-source { color: #777; font-size: 7pt; text-align: right; }
    .canonical-header { border-bottom: 1.5pt solid #111; margin-bottom: 7mm; padding-bottom: 3mm; text-align: center; }
    .canonical-header-title { font-size: 18pt; font-weight: 700; margin: 0 0 1.5mm; }
    .canonical-header-text { font-size: 10pt; margin: 0.5mm 0; }
    .canonical-header-fields { border-collapse: collapse; margin-top: 3mm; width: 100%; }
    .canonical-header-fields td { border-bottom: 0.6pt dotted #777; padding: 1.5mm 1mm; text-align: left; }
    .canonical-header-label { font-size: 8pt; font-weight: 700; text-transform: uppercase; width: 24mm; }
    .canonical-header-line { border-top: 0.8pt solid #777; margin: 2mm 0; }
    .canonical-document-label { color: #555; font-size: 8pt; letter-spacing: 0.6pt; margin: 1mm 0 0; text-transform: uppercase; }
    .canonical-question { margin: 0 0 6mm; padding: 0 0 4mm; }
    .canonical-question.avoid-break { break-inside: avoid; page-break-inside: avoid; }
    .canonical-question.separator-line { border-bottom: 0.5pt solid #bbb; }
    .canonical-question.separator-box { border: 0.7pt solid #999; padding: 3mm; }
    .canonical-question-title { font-size: 10.5pt; font-weight: 700; margin: 0 0 2mm; text-align: justify; }
    .canonical-points { color: #666; font-size: 8pt; font-weight: 400; white-space: nowrap; }
    .canonical-options { list-style: none; margin: 2mm 0 0; padding: 0 0 0 4mm; }
    .canonical-options li { margin: 0 0 1.6mm; page-break-inside: avoid; }
    .canonical-option-label { display: inline-block; font-weight: 700; width: 8mm; }
    .canonical-option-mark { display: inline-block; margin-right: 2mm; }
    .canonical-essay-space { border: 0.6pt solid #999; height: 28mm; margin-top: 3mm; }
    .canonical-resource { background: #f6f6f6; border: 0.6pt solid #999; margin: 2mm 0 3mm; padding: 2.5mm; }
    .canonical-resource-title { font-size: 9pt; font-weight: 700; margin: 0 0 1mm; }
    .canonical-resource-body { font-size: 9pt; margin: 0; white-space: pre-wrap; }
    .canonical-resource-image { display: block; margin: 2mm auto; max-height: 90mm; max-width: 100%; }
    .canonical-resource-source { color: #666; font-size: 7.5pt; margin: 1mm 0 0; word-wrap: break-word; }
    .canonical-two-columns { border-collapse: collapse; table-layout: fixed; width: 100%; }
    .canonical-two-columns > tbody > tr > td { padding: 0 3mm; vertical-align: top; width: 50%; }
    .canonical-two-columns > tbody > tr > td:first-child { padding-left: 0; }
    .canonical-two-columns > tbody > tr > td:last-child { padding-right: 0; }

    @media screen {
        body.canonical-preview-body { background: #e5e7eb; margin: 0; padding: 18px; }
        body.canonical-preview-body .canonical-page { background: white; box-shadow: 0 8px 28px rgba(0, 0, 0, .16); box-sizing: border-box; margin: 0 auto; min-height: {{ $canonicalOrientation === 'landscape' ? '210mm' : '297mm' }}; padding: {{ $canonicalMargins[0] }}mm {{ $canonicalMargins[1] }}mm {{ $canonicalMargins[2] }}mm {{ $canonicalMargins[3] }}mm; width: {{ $canonicalOrientation === 'landscape' ? '297mm' : '210mm' }}; }
    }

    @media print {
        .canonical-page { margin: 0; padding: 0; }
    }
</style>
