<section class="canonical-page" data-document-source="{{ $document['source'] }}" data-document-hash="{{ $document['document_hash'] }}">
    @if($document['copy_number'])
        <div class="canonical-source">Versão #{{ $document['copy_number'] }} · {{ $document['validation_hash'] }}</div>
    @endif

    <header class="canonical-header {{ $document['header']['mode'] === 'canvas' ? 'canonical-header-canvas' : '' }}" style="min-height: {{ $document['header']['height_mm'] }}mm;{{ $document['header']['mode'] === 'canvas' ? 'height: '.$document['header']['height_mm'].'mm' : '' }}">
        @if($document['header']['mode'] === 'canvas')
            @foreach($document['header']['elements'] as $element)
                @php
                    $canvasHeight = max(1, $document['header']['canvas_height']);
                    $position = sprintf(
                        'left:%.2f%%;top:%.2f%%;width:%.2f%%;height:%.2f%%;',
                        $element['x'] / 10,
                        $element['y'] / $canvasHeight * 100,
                        $element['width'] / 10,
                        $element['height'] / $canvasHeight * 100,
                    );
                @endphp
                @if(in_array($element['type'], ['text', 'field'], true))
                    <div class="canonical-canvas-element canonical-canvas-{{ $element['type'] }}"
                        style="{{ $position }}text-align:{{ $element['align'] }};font-size:{{ $element['render_font_size'] }}pt;font-weight:{{ $element['font_weight'] }};color:{{ $element['color'] }}">
                        {{ $element['value'] }}
                    </div>
                @elseif($element['type'] === 'image')
                    <img class="canonical-canvas-element canonical-canvas-image" style="{{ $position }}"
                        src="{{ $element['src'] }}" alt="{{ $element['alt_text'] }}">
                @elseif($element['type'] === 'line')
                    <div class="canonical-canvas-element canonical-canvas-line"
                        style="{{ $position }}border-top:{{ $element['border_width'] }}pt solid {{ $element['border_color'] }}"></div>
                @else
                    <div class="canonical-canvas-element canonical-canvas-rectangle"
                        style="{{ $position }}border:{{ $element['border_width'] }}pt solid {{ $element['border_color'] }};background:{{ $element['fill'] }}"></div>
                @endif
            @endforeach
        @else
        @php $canonicalFields = []; @endphp
        @foreach($document['header']['elements'] as $element)
            @if($element['type'] === 'field')
                @php $canonicalFields[] = $element; @endphp
            @elseif($element['type'] === 'line')
                <div class="canonical-header-line"></div>
            @elseif(($element['token'] ?? null) === 'assessment.title')
                <h1 class="canonical-header-title">{{ $element['value'] }}</h1>
            @else
                <p class="canonical-header-text">{{ $element['value'] }}</p>
            @endif
        @endforeach
        <p class="canonical-document-label">Caderno de Prova</p>
        @if($canonicalFields !== [])
            <table class="canonical-header-fields">
                <tbody>
                    @foreach($canonicalFields as $field)
                        <tr><td class="canonical-header-label">{{ $field['label'] }}</td><td>{{ $field['value'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @endif
    </header>

    @if($document['layout']['columns'] === 2)
        <table class="canonical-two-columns">
            <tbody>
                @foreach(array_chunk($document['questions'], 2) as $questionRow)
                    <tr>
                        @foreach($questionRow as $question)
                            <td>@include('exams.print.question', ['question' => $question, 'document' => $document])</td>
                        @endforeach
                        @if(count($questionRow) === 1)<td></td>@endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        @foreach($document['questions'] as $question)
            @include('exams.print.question', ['question' => $question, 'document' => $document])
        @endforeach
    @endif
</section>
