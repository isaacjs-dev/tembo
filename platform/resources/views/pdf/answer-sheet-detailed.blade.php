<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Cartão Resposta Detalhado - {{ $exam->title }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #111;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        .page-break { page-break-after: always; }

        /* === FIDUCIAL MARKERS === */
        .fiducial {
            width: {{ ($layout['fiducial_size_mm'] ?? 4.76) }}mm;
            height: {{ ($layout['fiducial_size_mm'] ?? 4.76) }}mm;
            background: #000;
            position: absolute;
        }
        .fid-tl { top: 0; left: 0; }
        .fid-tr { top: 0; right: 0; }
        .fid-bl { bottom: 0; left: 0; }
        .fid-br { bottom: 0; right: 0; }

        .sheet-wrapper {
            position: relative;
            width: 100%;
            min-height: 270mm;
        }

        /* === HEADER === */
        .sheet-header {
            width: 100%;
            border: 1.5px solid #000;
            border-collapse: collapse;
            margin-bottom: 4mm;
        }
        .sheet-header td { padding: 5px; vertical-align: top; }
        .institution-name {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .field-line {
            font-size: 9px;
            margin-bottom: 2px;
            border-bottom: 1px dotted #888;
            padding-bottom: 1px;
        }
        .qr-cell {
            width: 24mm;
            text-align: center;
            vertical-align: middle;
            border-left: 1.5px solid #000;
            background: #fafafa;
        }
        .qr-cell img { width: 20mm; height: 20mm; }
        .version-badge {
            display: inline-block;
            background: #e5e7eb;
            border: 1px solid #d1d5db;
            border-radius: 3px;
            padding: 1px 4px;
            font-weight: bold;
            font-size: 8px;
        }
        .page-indicator { float: right; font-size: 11px; font-weight: bold; }

        .instructions {
            margin-bottom: 3mm;
            padding: 2px 5px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 2px;
            font-size: 9px;
            font-weight: bold;
            color: #333;
        }

        /* === BUBBLE GRID (4 columns) === */
        .bubble-area table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .col-block { vertical-align: top; padding: 0 1.5mm; width: 25%; }
        .bubble-table { width: 100%; border-collapse: collapse; }
        .bubble-table th {
            background: #374151;
            color: #fff;
            border: 1px solid #4b5563;
            padding: 3px 1px;
            text-align: center;
            font-size: 8px;
            text-transform: uppercase;
        }
        .bubble-table td {
            border: 1px solid #d1d5db;
            padding: 3px 1px;
            text-align: center;
            vertical-align: middle;
        }
        .row-even { background: #ffffff; }
        .row-odd  { background: #f3f4f6; }
        .q-num { font-weight: bold; font-size: 9px; width: 18px; }
        .q-bubbles { text-align: left; padding-left: 2px !important; white-space: nowrap; }
        .bubble {
            display: inline-block;
            width: {{ ($layout['bubble_diameter_mm'] ?? 4.2) }}mm;
            height: {{ ($layout['bubble_diameter_mm'] ?? 4.2) }}mm;
            border: 1.2px solid #000;
            border-radius: 50%;
            line-height: {{ ($layout['bubble_diameter_mm'] ?? 4.2) }}mm;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            color: #000;
            box-sizing: content-box;
            vertical-align: middle;
            margin: 0 0.5px;
        }

        /* === DISCIPLINE HEADER === */
        .discipline-divider {
            background: #f0f0f0;
            border: 1px solid #d1d5db;
            text-align: center;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #4b5563;
            padding: 2px;
        }

        .sheet-footer {
            margin-top: 3mm;
            font-size: 7px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>

@php
    $letters = ['A', 'B', 'C', 'D', 'E'];
    $columns = $layout['columns'] ?? 4;
    $rowsPerCol = $layout['rows_per_column'] ?? 15;
@endphp

@foreach($allCopiesData as $copyIndex => $copyData)
    @php
        $copy = $copyData['copy'];
        $pagesData = $copyData['pagesData'];
    @endphp

    @foreach($pagesData as $pageIndex => $pageData)
        <div class="sheet-wrapper">
        {{-- Fiducial Markers --}}
        <div class="fiducial fid-tl"></div>
        <div class="fiducial fid-tr"></div>
        <div class="fiducial fid-bl"></div>
        <div class="fiducial fid-br"></div>

        @if($pageData['totalPages'] > 1)
            <div class="page-indicator">Folha {{ $pageData['page'] }}/{{ $pageData['totalPages'] }}</div>
        @endif

        <div style="text-align: center; margin-bottom: 2mm;">
            <strong style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">CARTÃO RESPOSTA</strong>
        </div>

        {{-- Full header (page 1) --}}
        @if($pageData['page'] === 1)
            <table class="sheet-header">
                <tr>
                    <td>
                        <div class="institution-name">{{ $exam->organization?->name ?? 'DADOS DA INSTITUIÇÃO' }}</div>
                        <div class="field-line">
                            <strong>NOME:</strong>
                            <span style="display:inline-block; width: 75mm;"></span>
                            <span style="float:right;"><strong>DATA:</strong> ____/____/________</span>
                        </div>
                        <div class="field-line" style="clear:both;">
                            <strong>Nº:</strong>
                            <span style="display:inline-block; width: 15mm;"></span>
                            <strong style="margin-left:5px;">TURMA:</strong>
                            <span style="display:inline-block; width: 20mm;"></span>
                            <strong style="margin-left:5px;">DISCIPLINA:</strong>
                            <span style="display:inline-block; width: 35mm;"></span>
                            <strong style="margin-left:5px;">VERSÃO:</strong>
                            <span class="version-badge">#{{ $copy->copy_number }}</span>
                        </div>
                    </td>
                    <td class="qr-cell">
                        <img src="data:image/svg+xml;base64,{!! $pageData['qrBase64'] !!}" alt="QR">
                    </td>
                </tr>
            </table>
        @else
            <table class="sheet-header">
                <tr>
                    <td style="padding: 3px 5px;">
                        <div class="field-line" style="margin:0; border:none;">
                            <strong>NOME:</strong>
                            <span style="display:inline-block; width: 70mm; border-bottom: 1px dotted #888;"></span>
                            <strong style="margin-left:5px;">VERSÃO:</strong>
                            <span class="version-badge">#{{ $copy->copy_number }}</span>
                        </div>
                    </td>
                    <td class="qr-cell" style="width: 20mm; padding: 2px;">
                        <img src="data:image/svg+xml;base64,{!! $pageData['qrBase64'] !!}" alt="QR" style="width: 16mm; height: 16mm;">
                    </td>
                </tr>
            </table>
        @endif

        <div class="instructions">
            ✎ Preencha completamente a bolha com caneta escura. Não rasure.
        </div>

        {{-- Bubble Grid - 4 Columns --}}
        <div class="bubble-area">
            <table>
                <tr>
                    @php
                        $questions = $pageData['questions'];
                        $totalQ = $questions->count();
                    @endphp

                    @for($col = 0; $col < $columns; $col++)
                        <td class="col-block">
                            @php $colStart = $col * $rowsPerCol; @endphp

                            @if($colStart < $totalQ)
                                <table class="bubble-table">
                                    <tr>
                                        <th style="width: 18px;">Q.</th>
                                        <th style="text-align: left; padding-left: 2px;">RESPOSTAS</th>
                                    </tr>
                                    @for($row = 0; $row < $rowsPerCol; $row++)
                                        @php $qIdx = $colStart + $row; @endphp
                                        @if($qIdx < $totalQ)
                                            @php
                                                $q = $questions->get($qIdx);
                                                $globalNum = $pageData['qStart'] + $qIdx;
                                                $optCount = $q->type === 'true_false'
                                                    ? 2
                                                    : count($q->content['options'] ?? []);
                                            @endphp
                                            <tr class="{{ $row % 2 === 0 ? 'row-even' : 'row-odd' }}">
                                                <td class="q-num">{{ sprintf('%02d', $globalNum) }}</td>
                                                <td class="q-bubbles">
                                                    @if($q->type === 'multiple_choice' || $q->type === 'true_false')
                                                        @for($opt = 0; $opt < $optCount; $opt++)
                                                            <div class="bubble">{{ $letters[$opt] ?? '' }}</div>
                                                        @endfor
                                                    @else
                                                        <span style="font-size:7px; color:#888;">Diss.</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endfor
                                </table>
                            @endif
                        </td>
                    @endfor
                </tr>
            </table>
        </div>

        <div class="sheet-footer">
            {{ $exam->title }} · V.{{ $copy->copy_number }} · Folha {{ $pageData['page'] }}/{{ $pageData['totalPages'] }}
            · {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    @if(!$loop->last || !$loop->parent->last)
        <div class="page-break"></div>
    @endif
    @endforeach
@endforeach

</body>
</html>
