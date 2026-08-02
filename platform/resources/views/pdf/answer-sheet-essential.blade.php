<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Cartão Resposta - {{ $exam->title }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            margin: 0;
            padding: 0;
        }

        /* Cada folha ocupa exatamente uma página A4. Posicionamento ABSOLUTO em mm:
           a blade imprime marcadores e bolhas nas MESMAS posições calculadas pelo
           gerador (AnswerSheetGeneratorService::buildPageGeometry) — fonte de verdade
           única, para casar com a leitura do motor OMR. */
        .sheet {
            position: relative;
            width: 210mm;
            height: 297mm;
            overflow: hidden;
        }
        .page-break { page-break-after: always; }

        /* Marcadores fiduciais (4 cantos do frame da grade) */
        .fid { position: absolute; background: #000; }

        /* Cabeçalho */
        .title {
            position: absolute; left: 0; top: 6mm; width: 210mm;
            text-align: center; font-size: 15px; font-weight: bold; letter-spacing: 1px;
            text-transform: uppercase;
        }
        .header-box {
            position: absolute; border: 1.5px solid #000; border-collapse: collapse;
        }
        .header-box td { padding: 5px 7px; vertical-align: top; }
        .inst-name { font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .field { font-size: 10px; margin-top: 7px; }
        .dotted { display: inline-block; border-bottom: 1px dotted #888; }
        .qr-cell { text-align: center; vertical-align: middle; border-left: 1.5px solid #000; background: #fafafa; }
        .version-badge {
            display: inline-block; background: #e5e7eb; border: 1px solid #d1d5db;
            border-radius: 3px; padding: 1px 5px; font-weight: bold; font-size: 9px;
        }
        .instructions {
            position: absolute; font-size: 9px; font-weight: bold; color: #333;
        }

        /* Grade */
        .qnum { position: absolute; font-size: 9px; font-weight: bold; }
        .essay { position: absolute; font-size: 8px; color: #888; }
        .bubble {
            position: absolute;
            border: 1.2px solid #000;
            border-radius: 50%;
            text-align: center;
            font-size: 7px;
            font-weight: bold;
            /* Letra clara: o aluno enxerga A/B/C, mas não conta como preenchimento na leitura OMR. */
            color: #c7ccd1;
            box-sizing: border-box;
        }

        .footer {
            position: absolute; left: 0; width: 210mm; text-align: center;
            font-size: 8px; color: #999;
        }
    </style>
</head>
<body>

@foreach($allCopiesData as $copyIndex => $copyData)
    @php
        $copy = $copyData['copy'];
        $pagesData = $copyData['pagesData'];
    @endphp

    @foreach($pagesData as $pageIndex => $pageData)
        @php
            $geo   = $pageData['geometry'];
            $frame = $geo['frame'];
            $cells = $geo['cells'];
            $bub   = $geo['bubbleMm'];
            $fid   = $frame['fid'];
            $half  = $fid / 2;
            // Centros dos marcadores = cantos do frame
            $fx = $frame['x']; $fy = $frame['y']; $fw = $frame['w']; $fh = $frame['h'];
        @endphp

        <div class="sheet">
            {{-- Título --}}
            <div class="title">Cartão Resposta</div>

            {{-- Cabeçalho (acima do frame da grade) --}}
            <table class="header-box" style="left: 12mm; top: 13mm; width: 186mm;">
                <tr>
                    <td>
                        <div class="inst-name">{{ $exam->organization?->name ?? 'DADOS DA INSTITUIÇÃO' }}</div>
                        <div class="field"><strong>NOME:</strong> <span class="dotted" style="width: 95mm;">&nbsp;</span>
                            <strong style="margin-left:6px;">DATA:</strong> ____/____/______</div>
                        <div class="field">
                            <strong>MATRÍCULA / Nº:</strong> <span class="dotted" style="width: 28mm;">&nbsp;</span>
                            <strong style="margin-left:8px;">TURMA:</strong> <span class="dotted" style="width: 22mm;">&nbsp;</span>
                            <strong style="margin-left:8px;">VERSÃO:</strong> <span class="version-badge">#{{ $copy->copy_number }}</span>
                        </div>
                    </td>
                    <td class="qr-cell" style="width: 24mm;">
                        <img src="data:image/svg+xml;base64,{!! $pageData['qrBase64'] !!}" alt="QR" style="width: 20mm; height: 20mm;">
                    </td>
                </tr>
            </table>

            {{-- Instruções (logo acima do frame) --}}
            <div class="instructions" style="left: 12mm; top: {{ $fy - $half - 4 }}mm; width: 186mm;">
                Preencha a bolha completamente com caneta escura, sem amassar a folha.
            </div>

            {{-- Marcadores fiduciais (centros nos 4 cantos do frame) --}}
            <div class="fid" style="left: {{ $fx - $half }}mm;        top: {{ $fy - $half }}mm;        width: {{ $fid }}mm; height: {{ $fid }}mm;"></div>
            <div class="fid" style="left: {{ $fx + $fw - $half }}mm;  top: {{ $fy - $half }}mm;        width: {{ $fid }}mm; height: {{ $fid }}mm;"></div>
            <div class="fid" style="left: {{ $fx + $fw - $half }}mm;  top: {{ $fy + $fh - $half }}mm;  width: {{ $fid }}mm; height: {{ $fid }}mm;"></div>
            <div class="fid" style="left: {{ $fx - $half }}mm;        top: {{ $fy + $fh - $half }}mm;  width: {{ $fid }}mm; height: {{ $fid }}mm;"></div>

            {{-- Questões e bolhas (posições absolutas = mesmas do `g` do QR) --}}
            @foreach($cells as $cell)
                <div class="qnum" style="left: {{ $cell['numX'] }}mm; top: {{ $cell['numY'] }}mm;">{{ sprintf('%02d', $cell['num']) }}</div>
                @if($cell['essay'])
                    <div class="essay" style="left: {{ $cell['numX'] + 9 }}mm; top: {{ $cell['numY'] }}mm;">Dissertativa</div>
                @else
                    @foreach($cell['bubbles'] as $b)
                        <div class="bubble" style="left: {{ $b['x'] }}mm; top: {{ $b['y'] }}mm; width: {{ $bub }}mm; height: {{ $bub }}mm; line-height: {{ $bub }}mm;">{{ $b['label'] }}</div>
                    @endforeach
                @endif
            @endforeach

            {{-- Rodapé --}}
            <div class="footer" style="top: {{ $fy + $fh + 6 }}mm;">
                {{ $exam->title }} · Versão #{{ $copy->copy_number }} · Folha {{ $pageData['page'] }}/{{ $pageData['totalPages'] }}
                · Gerado em {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>

        @if(!$loop->last || !$loop->parent->last)
            <div class="page-break"></div>
        @endif
    @endforeach
@endforeach

</body>
</html>
