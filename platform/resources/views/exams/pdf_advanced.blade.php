<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Lote: {{ $exam->title }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
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
    <div class="watermark">Versão #{{ $copy->copy_number }} - {{ $copy->validation_hash }}</div>

    <div class="header">
        <h1>{{ $exam->title }}</h1>
        @if($exam->organization) <p style="margin:0;">{{ $exam->organization->name }}</p> @endif
        <p style="margin:5px 0 0 0; font-size: 12px; color: #555;">Caderno de Prova - Versão {{ $copy->copy_number }}</p>
    </div>

    @foreach($orderedQuestions as $index => $question)
        @php
            $optMap = $copy->options_map[$question->id] ?? null;
        @endphp
        <div class="question">
            <div class="question-statement">
                @if(!($options['hide_question_term'] ?? false))
                    Questão 
                @endif
                {{ $index + 1 }}{{ $options['question_separator'] ?? '.' }} {!! strip_tags($question->content['statement']) !!}
                
                @if($options['show_question_value'] ?? true)
                    <span style="font-size: 10px; color: #666; font-weight: normal;">(Valor: {{ number_format($question->pivot->points, 1) }})</span>
                @endif
            </div>

            @if($question->type === 'multiple_choice' && $optMap)
                <ul class="options">
                    @foreach($optMap as $i => $originalOptIndex)
                        <li style="page-break-inside: avoid;">
                            <span class="option-letter">{{ $letters[$i] }})</span>
                            @if($options['show_option_brackets'] ?? false) ( &nbsp;&nbsp; ) @endif
                            {{ $question->content['options'][$originalOptIndex] ?? '' }}
                        </li>
                    @endforeach
                </ul>
            @elseif($question->type === 'true_false' && $optMap)
                <ul class="options">
                    @foreach($optMap as $i => $originalOptIndex)
                        <li style="page-break-inside: avoid;">
                            <span class="option-letter">{{ $letters[$i] }})</span>
                            @if($options['show_option_brackets'] ?? false) ( &nbsp;&nbsp; ) @endif
                            {{ $originalOptIndex == 0 ? 'Verdadeiro' : 'Falso' }}
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="essay-space"></div>
            @endif
        </div>
    @endforeach

    <div class="page-break"></div>

    {{-- 2. Cartão-Resposta (OMR) — geometria nova (marcadores + QR assinado com `g`), legível pelo motor --}}
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

            @if($cardPage['page'] === 1)
                <table class="omr-header-box" style="left: 8mm; top: 11mm; width: 174mm;">
                    <tr>
                        <td>
                            <div class="omr-inst">{{ $exam->organization?->name ?? 'DADOS DA INSTITUIÇÃO' }}</div>
                            <div class="omr-field"><strong>NOME:</strong> <span class="omr-dotted" style="width: 86mm;">&nbsp;</span> <strong style="margin-left:6px;">DATA:</strong> ____/____/______</div>
                            <div class="omr-field"><strong>MATRÍCULA / Nº:</strong> <span class="omr-dotted" style="width: 24mm;">&nbsp;</span> <strong style="margin-left:8px;">TURMA:</strong> <span class="omr-dotted" style="width: 18mm;">&nbsp;</span> <strong style="margin-left:8px;">VERSÃO:</strong> <span class="omr-badge">#{{ $copy->copy_number }}</span></div>
                        </td>
                        <td class="omr-qr-cell" style="width: 30mm;">
                            <img src="data:image/svg+xml;base64,{!! $cardPage['qrBase64'] !!}" alt="QR" style="width: 24mm; height: 24mm;">
                        </td>
                    </tr>
                </table>
            @else
                <div style="position: absolute; left: 8mm; top: 11mm; font-size: 11px;">
                    <strong>NOME:</strong> <span class="omr-dotted" style="width: 80mm;">&nbsp;</span>
                    <strong style="margin-left:8px;">VERSÃO:</strong> <span class="omr-badge">#{{ $copy->copy_number }}</span>
                    — Folha {{ $cardPage['page'] }}/{{ $cardPage['totalPages'] }}
                </div>
            @endif

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

        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    <div class="page-break"></div>

    {{-- 3. Professor Answer Key (Gabarito do Professor) --}}
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

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>
