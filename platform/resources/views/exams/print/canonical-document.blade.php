<section class="canonical-page" data-document-source="{{ $document['source'] }}" data-document-hash="{{ $document['document_hash'] }}">
    @if($document['copy_number'])
        <div class="canonical-source">Versão #{{ $document['copy_number'] }} · {{ $document['validation_hash'] }}</div>
    @endif

    <header class="canonical-header" style="min-height: {{ $document['header']['height_mm'] }}mm">
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
