<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $exam->title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
        }

        .student-info {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 25px;
            border-radius: 5px;
        }

        .student-info div {
            margin-bottom: 5px;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 5px;
        }

        .question {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .question-statement {
            font-weight: bold;
            margin-bottom: 10px;
            text-align: justify;
        }

        .options {
            list-style-type: none;
            padding-left: 0;
        }

        .options li {
            margin-bottom: 8px;
        }

        .option-circle {
            display: inline-block;
            width: 15px;
            height: 15px;
            border: 1px solid #000;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }

        .true-false-options {
            display: flex;
            gap: 20px;
        }

        .essay-space {
            margin-top: 15px;
            border: 1px solid #ccc;
            height: 150px;
            border-radius: 5px;
        }

        .page-break {
            page-break-after: always;
        }

        .question-resources { margin: 8px 0 12px; padding: 8px; border: 1px solid #777; background: #f7f7f7; }
        .question-resource + .question-resource { margin-top: 8px; border-top: 1px solid #bbb; padding-top: 8px; }
        .question-resource-title { margin: 0 0 4px; font-weight: bold; }
        .question-resource-body { margin: 0; white-space: pre-wrap; }
        .question-resource-image { display: block; max-width: 100%; max-height: 420px; margin: 6px auto; }
        .question-resource-file, .question-resource-url { margin: 4px 0 0; font-size: 11px; color: #555; }
    </style>
</head>

<body>

    <div class="header">
        <h1>{{ $exam->title }}</h1>
        @if($exam->organization)
            <p>{{ $exam->organization->name }}</p>
        @endif
    </div>

    <div class="student-info">
        <div><strong>Nome do Aluno:</strong> ________________________________________________________________</div>
        <div><strong>Turma:</strong> ____________________ &nbsp;&nbsp;&nbsp; <strong>Data:</strong> ____/____/________
            &nbsp;&nbsp;&nbsp; <strong>Nota:</strong> _______</div>
    </div>

    @foreach($exam->questions as $index => $question)
        <div class="question">
            <div class="question-statement">
                {{ $index + 1 }}. {{ $question->content['statement'] }}
                <span style="font-size: 11px; color: #666; font-weight: normal;">(Valor:
                    {{ number_format($question->pivot->points, 1) }})</span>
            </div>

            @include('questions.partials.resource-list', ['context' => 'pdf'])

            @if($question->type === 'multiple_choice')
                <ul class="options">
                    @foreach($question->content['options'] as $optIndex => $optionText)
                        <li><span class="option-circle"></span> {{ $optionText }}</li>
                    @endforeach
                </ul>
            @elseif($question->type === 'true_false')
                <div class="true-false-options">
                    <span style="display:inline-block; margin-right: 30px;"><span class="option-circle"></span> (V)
                        Verdadeiro</span>
                    <span style="display:inline-block;"><span class="option-circle"></span> (F) Falso</span>
                </div>
            @else
                <div class="essay-space"></div>
            @endif
        </div>
    @endforeach

</body>

</html>
