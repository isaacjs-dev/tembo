<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview — {{ $document['title'] }}</title>
    @include('exams.print.canonical-styles', ['canonicalPageLayout' => $document['layout']])
</head>
<body class="canonical-preview-body">
    @include('exams.print.canonical-document', ['document' => $document])
</body>
</html>
