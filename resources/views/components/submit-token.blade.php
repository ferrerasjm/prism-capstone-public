{{-- One-time token backing App\Support\SubmissionGuard: it survives a validation
     redisplay via old(), so only a genuine double-submit reuses a spent token. --}}
<input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
