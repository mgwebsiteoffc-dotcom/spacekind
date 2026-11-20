@extends('layouts.admin')
@section('title','Integrations & email')
@section('content')
<div class="page-heading"><div><div class="eyebrow section-kicker">PLATFORM SETTINGS</div><h1>Integrations &amp; email.</h1><p>Manage payment credentials, email copy and measurement settings for Spacekind.</p></div></div>
<section class="app-panel form-panel"><h2>Website measurement</h2><p>Set up Google Analytics (GA4 / gtag.js), Google Tag Manager, Meta Pixel and Microsoft Clarity by ID. Eligible pages and visitor consent are managed together.</p><a class="button outline" href="/admin/tracking">Manage tracking &amp; consent</a></section>
<h2>Razorpay gateway</h2><p>Test and live credentials are separate. Secrets are encrypted and never displayed. Saving them does not verify the account, start checkout or enable webhooks.</p>
@if(config('spacekind.demo'))<div class="notice subtle">Credential changes are disabled in shared workspaces.</div>@endif
<div class="detail-columns">@foreach(['test','live'] as $mode)
<section class="app-panel form-panel"><h3>{{ ucfirst($mode) }} credentials</h3><p>{{ isset($credentials[$mode])?'Stored · not verified':'Not configured' }}</p>
@if(!config('spacekind.demo'))<form action="/admin/integrations/razorpay/{{ $mode }}" method="post">@csrf @method('PUT')
<label class="field"><span>Key ID</span><input name="key_id" required maxlength="80" placeholder="rzp_{{ $mode }}_…" value="{{ $credentials->get($mode)?->key_id }}" autocomplete="off"></label>
<label class="field"><span>Key secret</span><input type="password" name="key_secret" maxlength="256" autocomplete="new-password" @required(!isset($credentials[$mode]))><small>Leave blank to retain the secret for an unchanged key ID.</small></label>
<button class="button dark">Save {{ $mode }} credentials</button></form>
@if(isset($credentials[$mode]))<form method="post" action="/admin/integrations/razorpay/{{ $mode }}" data-confirm="Remove the stored credential?"><br>@csrf @method('DELETE')<button class="button outline">Remove {{ $mode }} credential</button></form>@endif @endif</section>@endforeach</div>
<section class="app-panel form-panel"><h2>Email templates</h2><p>Save the subject and plain-text body for each message. Template changes do not send email or enable delivery.</p>
@foreach(\App\Models\EmailTemplate::PURPOSES as $key=>$label)
<details><summary>{{ $label }} · {{ isset($templates[$key])?'Saved':'Not configured' }}</summary><form method="post" action="/admin/integrations/templates/{{ $key }}">@csrf @method('PUT')
<label class="field"><span>Subject</span><input name="subject" maxlength="160" required value="{{ $templates->get($key)?->subject }}"></label>
<label class="field"><span>Message</span><textarea name="body" rows="6" maxlength="8000" required>{{ $templates->get($key)?->body }}</textarea></label><button class="button dark">Save template</button></form></details><hr>@endforeach</section>
@endsection
