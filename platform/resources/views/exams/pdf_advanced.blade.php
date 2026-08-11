@php
    $outputType = $outputType ?? 'legacy_all';
    $includeExam = in_array($outputType, ['exam', 'both', 'legacy_all'], true);
    $includeAnswerSheet = in_array($outputType, ['answer_sheet', 'both', 'legacy_all'], true);
    $includeAnswerKey = in_array($outputType, ['answer_key', 'legacy_all'], true);
    $printDocuments ??= $copies->mapWithKeys(fn ($copy) => [
        $copy->id => app(App\Services\CanonicalPrintDocumentService::class)->copy($exam, $copy),
    ]);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Lote: {{ $exam->title }}</title>
    <style>
        @if($outputType !== 'exam')
        @page { size: A4 portrait; margin: 10mm; }
        @endif
        body { font-family: Arial, sans-serif; font-size: 13px; color: #111; line-height: 1.4; margin: 0; padding: 0; }
        .page-break { page-break-after: always; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { margin: 0 0 5px 0; font-size: 22px; }
        
        .question { margin-bottom: 20px; page-break-inside: avoid; }
        .question-statement { font-weight: bold; margin-bottom: 8px; text-align: justify; }
        .options { list-style-type: none; padding-left: 0; margin: 0; }
        .options li { margin-bottom: 6px; }
        .option-letter { font-weight: bold; width: 22px; display: inline-block; }
        
        /* Bubble Sheet */
        .wrapper-inner-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid transparent; }
        .marker-td { width: 24px; padding: 0; }
        .marker { width: 18px; height: 18px; background: #000; }
        
        .bubble-grid { width: 100%; border-collapse: collapse; }
        .bubble-grid th { background-color: #e5e7eb; border: 1px solid #9ca3af; padding: 8px 4px; text-align: center; font-size: 11px; text-transform: uppercase; font-weight: bold; }
        .bubble-grid td { border: 1px solid #d1d5db; padding: 7px 4px; text-align: center; vertical-align: middle; }
        .zebra-row-even { background-color: #ffffff; }
        .zebra-row-odd { background-color: #f3f4f6; }
        .bubble { display: inline-block; width: 16px; height: 15px; padding-bottom: 1px; border: 1.5px solid #000; border-radius: 50%; line-height: 15px; font-size: 10px; font-weight: bold; margin: 2px 3px 0 3px; text-align: center; color: #000; box-sizing: content-box; vertical-align: middle; }
        
        .discipline-header { background-color: #f9fafb; font-weight: bold; font-size: 10px; text-align: center; border: 1px solid #d1d5db; padding: 6px; text-transform: uppercase; color: #4b5563; }
        
        .watermark { position: absolute; top: 10px; right: 10px; font-size: 10px; color: #777; }
        .clear { clear: both; }
        
        /* Helper Table for columns */
        .col-wrapper { display: inline-block; width: 32%; vertical-align: top; margin-right: 1%; margin-bottom: 10px; }
        .prof-key { font-size: 16px; margin-bottom: 5px; border-bottom: 1px solid #ccc; padding: 5px; }
        .essay-space { margin-top: 10px; border: 1px solid #ccc; height: 100px; border-radius: 4px; }
        .question-resources { margin: 8px 0 12px; padding: 8px; border: 1px solid #777; background: #f7f7f7; }
        .question-resource + .question-resource { margin-top: 8px; border-top: 1px solid #bbb; padding-top: 8px; }
        .question-resource-title { margin: 0 0 4px; font-weight: bold; }
        .question-resource-body { margin: 0; white-space: pre-wrap; }
        .question-resource-image { display: block; max-width: 100%; max-height: 420px; margin: 6px auto; }
        .question-resource-file, .question-resource-url { margin: 4px 0 0; font-size: 11px; color: #555; }

        /* === Cartão-Resposta OMR (posicionamento absoluto = mesma geometria do `g`) === */
        .omr-sheet { position: relative; width: 190mm; height: 255mm; }
        .omr-title { position: absolute; left: 0; top: 4mm; width: 190mm; text-align: center; font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .omr-header-box { position: absolute; border: 1.5px solid #000; border-collapse: collapse; }
        .omr-header-box td { padding: 5px 7px; vertical-align: top; }
        .omr-inst { font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .omr-field { font-size: 10px; margin-top: 6px; }
        .omr-dotted { display: inline-block; border-bottom: 1px dotted #888; }
        .omr-qr-cell { text-align: center; vertical-align: middle; border-left: 1.5px solid #000; background: #fafafa; }
        .omr-badge { display: inline-block; background: #e5e7eb; border: 1px solid #d1d5db; border-radius: 3px; padding: 1px 5px; font-weight: bold; font-size: 9px; }
        .omr-instructions { position: absolute; font-size: 9px; font-weight: bold; color: #333; }
        .omr-qnum { position: absolute; font-size: 9px; font-weight: bold; }
        .omr-essay { position: absolute; font-size: 8px; color: #888; }
        .omr-bubble { position: absolute; border: 1.2px solid #000; border-radius: 50%; text-align: center; font-size: 7px; font-weight: bold; color: #c7ccd1; box-sizing: border-box; }
    </style>
    @if(isset($printDocuments) && $printDocuments->isNotEmpty())
        @include('exams.print.canonical-styles', [
            'canonicalPageLayout' => $printDocuments->first()['layout'],
            'canonicalEmitPageRule' => $outputType === 'exam',
        ])
    @endif
</head>
<body>

@php
    $letters = ['A', 'B', 'C', 'D', 'E'];
@endphp

@foreach($copies as $copyIndex => $copy)
    @php
        $map = $copy->questions_map ?? [];
        $orderedQuestions = collect($map)->map(function ($qId) use ($exam) {
            return $exam->questions->firstWhere('id', $qId);
        })->filter();
    @endphp

    {{-- 1. Student Exam Pages --}}
    @if($includeExam)
    @include('exams.print.canonical-document', ['document' => $printDocuments[$copy->id]])

    @if($includeAnswerSheet || $includeAnswerKey)<div class="page-break"></div>@endif
    @endif

    {{-- 2. Cartão-Resposta (OMR) — geometria nova (marcadores + QR assinado com `g`), legível pelo motor --}}
    @if($includeAnswerSheet)
    @php $cardPages = $cardPagesByCopy[$copy->id] ?? []; @endphp
    @foreach($cardPages as $cardPage)
        @php
            $geo = $cardPage['geometry'];
            $frame = $geo['frame']; $cells = $geo['cells']; $bub = $geo['bubbleMm'];
            $fid = $frame['fid']; $half = $fid / 2;
            $fx = $frame['x']; $fy = $frame['y']; $fw = $frame['w']; $fh = $frame['h'];
        @endphp
        <div class="omr-sheet">
            <div class="omr-title">CARTÃO RESPOSTA</div>

            <table class="omr-header-box" style="left: 8mm; top: 8mm; width: 174mm;">
                <tr>
                    <td>
                        @if($cardPage['page'] === 1)
                            <div class="omr-inst">{{ $exam->organization?->name ?? 'DADOS DA INSTITUIÇÃO' }}</div>
                            <div class="omr-field"><strong>NOME:</strong> {{ $copy->student?->name ?? '' }} <span class="omr-dotted" style="width: {{ $copy->student ? '42mm' : '86mm' }};">&nbsp;</span> <strong style="margin-left:6px;">DATA:</strong> ____/____/______</div>
                            <div class="omr-field"><strong>MATRÍCULA / Nº:</strong> <span class="omr-dotted" style="width: 24mm;">&nbsp;</span> <strong style="margin-left:8px;">TURMA:</strong> <span class="omr-dotted" style="width: 18mm;">&nbsp;</span> <strong style="margin-left:8px;">VERSÃO:</strong> <span class="omr-badge">#{{ $copy->copy_number }}</span></div>
                        @else
                            <div class="omr-inst">{{ $exam->organization?->name ?? 'DADOS DA INSTITUIÇÃO' }}</div>
                            <div class="omr-field"><strong>NOME:</strong> {{ $copy->student?->name ?? '' }} <span class="omr-dotted" style="width: {{ $copy->student ? '42mm' : '74mm' }};">&nbsp;</span></div>
                            <div class="omr-field"><strong>VERSÃO:</strong> <span class="omr-badge">#{{ $copy->copy_number }}</span> <strong style="margin-left:12px;">FOLHA:</strong> {{ $cardPage['page'] }}/{{ $cardPage['totalPages'] }}</div>
                        @endif
                    </td>
                    <td class="omr-qr-cell" style="width: 34mm; padding: 2px;">
                        <img src="data:image/svg+xml;base64,{!! $cardPage['qrBase64'] !!}" alt="QR da folha {{ $cardPage['page'] }}" style="width: {{ $cardPage['qrPrint']['size_mm'] }}mm; height: {{ $cardPage['qrPrint']['size_mm'] }}mm;">
                    </td>
                </tr>
            </table>

            <div class="omr-instructions" style="left: 8mm; top: {{ $fy - $half - 4 }}mm; width: 174mm;">Preencha a bolha completamente com caneta escura, sem amassar a folha.</div>

            {{-- Marcadores fiduciais (centros nos 4 cantos do frame) --}}
            <div style="position: absolute; background: #000; left: {{ $fx - $half }}mm; top: {{ $fy - $half }}mm; width: {{ $fid }}mm; height: {{ $fid }}mm;"></div>
            <div style="position: absolute; background: #000; left: {{ $fx + $fw - $half }}mm; top: {{ $fy - $half }}mm; width: {{ $fid }}mm; height: {{ $fid }}mm;"></div>
            <div style="position: absolute; background: #000; left: {{ $fx + $fw - $half }}mm; top: {{ $fy + $fh - $half }}mm; width: {{ $fid }}mm; height: {{ $fid }}mm;"></div>
            <div style="position: absolute; background: #000; left: {{ $fx - $half }}mm; top: {{ $fy + $fh - $half }}mm; width: {{ $fid }}mm; height: {{ $fid }}mm;"></div>

            {{-- Questões e bolhas (posições absolutas = mesmas do `g` do QR) --}}
            @foreach($cells as $cell)
                <div class="omr-qnum" style="left: {{ $cell['numX'] }}mm; top: {{ $cell['numY'] }}mm;">{{ sprintf('%02d', $cell['num']) }}</div>
                @if($cell['essay'])
                    <div class="omr-essay" style="left: {{ $cell['numX'] + 8 }}mm; top: {{ $cell['numY'] }}mm;">Dissertativa</div>
                @else
                    @foreach($cell['bubbles'] as $b)
                        <div class="omr-bubble" style="left: {{ $b['x'] }}mm; top: {{ $b['y'] }}mm; width: {{ $bub }}mm; height: {{ $bub }}mm; line-height: {{ $bub }}mm;">{{ $b['label'] }}</div>
                    @endforeach
                @endif
            @endforeach
        </div>

        @if(!$loop->last || $includeAnswerKey)
            <div class="page-break"></div>
        @endif
    @endforeach
    @endif

    {{-- 3. Professor Answer Key (Gabarito do Professor) --}}
    @if($includeAnswerKey)
    <div class="header">
        <h2>GABARITO DO PROFESSOR (Versão {{ $copy->copy_number }})</h2>
        <p>A ordem das alternativas cadastradas foi permutada e estas são as respostas corrigidas para a versão {{ $copy->copy_number }}.</p>
    </div>

    <div style="padding: 20px;">
        @foreach($orderedQuestions as $index => $q)
            @php
                $correctOptionOriginalId = $q->content['correct_option'] ?? null;
                $optMap = $copy->options_map[$q->id] ?? null;
                $displayAnswer = 'N/A';

                if ($optMap !== null && $correctOptionOriginalId !== null && $correctOptionOriginalId !== '') {
                    $shuffledIndex = array_search($correctOptionOriginalId, $optMap);
                    if ($shuffledIndex !== false) {
                        $displayAnswer = $letters[$shuffledIndex];
                    }
                } elseif ($q->type === 'true_false' && $correctOptionOriginalId !== null) {
                    $shuffledIndex = array_search($correctOptionOriginalId, $optMap);
                    if ($shuffledIndex !== false) {
                        $displayAnswer = $letters[$shuffledIndex];
                    }
                }
            @endphp

            <div class="col-wrapper">
                <div class="prof-key">
                    <strong>
                        @if(!($options['hide_question_term'] ?? false))
                            Questão 
                        @endif
                        {{ sprintf('%02d', $index + 1) }}{{ in_array($options['question_separator'] ?? '.', ['.', ')']) ? ':' : ($options['question_separator'] ?? '') }}
                    </strong> 
                    @if($q->type === 'multiple_choice' || $q->type === 'true_false')
                        <span style="color: #d32f2f; font-weight: bold;">[ {{ $displayAnswer }} ]</span>
                    @else
                        <span style="color: #666; font-size:12px;">Dissertativa</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>
