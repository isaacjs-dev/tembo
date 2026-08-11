<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] }}</title>
    @include('exams.print.canonical-styles', ['canonicalPageLayout' => $document['layout']])
</head>
<body>
    @include('exams.print.canonical-document', ['document' => $document])
</body>
</html>
