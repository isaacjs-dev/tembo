<article class="canonical-question {{ $question['avoid_break'] ? 'avoid-break' : '' }} separator-{{ $document['layout']['separator'] }}">
    <p class="canonical-question-title">
        @unless($document['preferences']['hide_question_term'])Questão @endunless{{ $question['number'] }}{{ $document['preferences']['question_separator'] }} {{ $question['statement'] }}
        @if($document['preferences']['show_question_value'])<span class="canonical-points">(Valor: {{ number_format($question['points'], 1, ',', '.') }})</span>@endif
    </p>

    @foreach($question['resources'] as $resource)
        <aside class="canonical-resource" aria-label="Material de apoio">
            <p class="canonical-resource-title">{{ $resource['title'] }}</p>
            @if($resource['body'] !== '')
                <p class="canonical-resource-body">{{ $resource['body'] }}</p>
            @endif
            @if($resource['image_data_uri'])
                <img class="canonical-resource-image" src="{{ $resource['image_data_uri'] }}" alt="{{ $resource['alt_text'] }}">
            @elseif($resource['mime_type'])
                <p class="canonical-resource-source">Arquivo de apoio: {{ $resource['mime_type'] }}</p>
            @endif
            @if($resource['external_url'])
                <p class="canonical-resource-source">Fonte: {{ $resource['external_url'] }}</p>
            @endif
        </aside>
    @endforeach

    @if(in_array($question['type'], ['multiple_choice', 'true_false'], true))
        <ul class="canonical-options">
            @foreach($question['options'] as $option)
                <li><span class="canonical-option-label">{{ $option['label'] }})</span>@if($document['preferences']['show_option_brackets'])<span class="canonical-option-mark">( &nbsp;&nbsp; )</span>@endif{{ $option['text'] }}</li>
            @endforeach
        </ul>
    @else
        <div class="canonical-essay-space" aria-label="Espaço para resposta"></div>
    @endif
</article>
