<h3>Experience &amp; completed projects</h3><p class="field-help">These details are public after listing approval. Leave unknown figures blank; portfolio photos do not count as projects.</p>
<div class="form-grid"><label class="field"><span>Year the studio started</span><input type="number" name="established_year" min="1800" max="{{ now()->year }}" value="{{ old('established_year', $business->established_year) }}"></label><label class="field"><span>Total completed projects</span><input type="number" name="completed_project_count" min="0" max="1000000" value="{{ old('completed_project_count', $business->completed_project_count) }}"></label></div>
<p class="field-help">Add up to three public project summaries and three awards. Use only information you have permission to publish. Do not include client contacts, budgets or private addresses. Clear a row to remove it.</p>
@foreach(['project_highlights'=>'Completed project','awards'=>'Award'] as $key=>$label)
@for($i=0; $i<3; $i++)
<details class="studio-proof-editor" @if($errors->has("$key.$i.*")) open @endif><summary>{{ $label }} {{ $i+1 }}</summary>
<label class="field"><span>{{ $label }} title</span><input name="{{ $key }}[{{ $i }}][title]" maxlength="120" value="{{ old("$key.$i.title", $business->{$key}[$i]['title'] ?? '') }}"></label>
@if($key==='project_highlights')
<label class="field"><span>What did your studio deliver?</span><textarea rows="3" maxlength="500" name="{{ $key }}[{{ $i }}][scope]">{{ old("$key.$i.scope", $business->{$key}[$i]['scope'] ?? '') }}</textarea></label>
@else
<label class="field"><span>Awarding organisation</span><input name="{{ $key }}[{{ $i }}][issuer]" maxlength="120" value="{{ old("$key.$i.issuer", $business->{$key}[$i]['issuer'] ?? '') }}"></label>
<label class="field"><span>Announcement link (optional, HTTPS)</span><input type="url" name="{{ $key }}[{{ $i }}][url]" maxlength="500" value="{{ old("$key.$i.url", $business->{$key}[$i]['url'] ?? '') }}"></label>
@endif
<label class="field"><span>{{ $key==='awards'?'Award year':'Completion year' }}</span><input type="number" min="1800" max="{{ now()->year }}" name="{{ $key }}[{{ $i }}][year]" value="{{ old("$key.$i.year", $business->{$key}[$i]['year'] ?? '') }}"></label></details>
@endfor
@endforeach
