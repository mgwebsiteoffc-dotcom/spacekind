@php($party=$party??[])
@php($prefix=$prefix??'')
@foreach(['name'=>'Billing name','address'=>'Complete billing address','state'=>'Billing state / UT','gstin'=>'GSTIN (optional)','email'=>'Email (optional)','phone'=>'Phone (optional)'] as $field=>$label)
<label class="field"><span>{{ $label }}</span>@php($n=$prefix?$prefix.'['.$field.']':$field)@php($o=$prefix?$prefix.'.'.$field:$field)
@if($field==='address')<textarea name="{{ $n }}" rows="3" required minlength="8" maxlength="600">{{ old($o,$party[$field]??'') }}</textarea>
@elseif($field==='state')<select name="{{ $n }}" required><option value="">Choose a state</option>@foreach(\App\Support\BillingDetails::STATES as $code=>$name)<option value="{{ $code }}" @selected((string)old($o,$party[$field]??'')===(string)$code)>{{ $code }} · {{ $name }}</option>@endforeach</select>
@else<input name="{{ $n }}" value="{{ old($o,$party[$field]??'') }}" @required($field==='name') type="{{ $field==='email'?'email':'text' }}" maxlength="{{ $field==='gstin'?15:($field==='phone'?25:120) }}" @if($field==='gstin') placeholder="Format checked, registration not verified" @endif>@endif</label>
@endforeach
