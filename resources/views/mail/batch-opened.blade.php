Hello {{ $grantee->full_name }},

{{ $body }}

Batch: {{ $batch->name }} ({{ $batch->academic_year }} · {{ $batch->semester }})
@if($batch->submission_deadline)
Submission deadline: {{ $batch->submission_deadline->toDayDateTimeString() }}
@endif
