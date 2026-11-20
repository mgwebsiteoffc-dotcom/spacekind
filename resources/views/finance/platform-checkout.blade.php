@if(!config('spacekind.demo') && !$invoice->business->is_demo && $invoice->status === 'issued')
@php
    $attempt = \App\Models\PlatformCheckout::where('platform_invoice_id', $invoice->id)->latest('id')->first();
    $mode = config('spacekind.razorpay_mode');
    $pending = $attempt?->state === 'pending';
    $canPay = $owner && $invoice->balance_minor > 0 && in_array($mode, ['test','live'], true) && (!$pending || $attempt->mode === $mode) && \App\Models\RazorpayCredential::where('mode', $mode)->exists();
@endphp
@if($attempt || $canPay)<section class="app-panel form-panel"><h2>Pay Spacekind</h2>
@if($attempt)<p>Latest Razorpay checkout: <strong>{{ ucfirst($attempt->state) }} · {{ $attempt->mode }}</strong>.</p>@endif
@if($canPay)<form method="post" action="{{ $prefix }}/{{ $invoice->id }}/checkout/pay">@csrf
<p>{{ $mode === 'test' ? 'Test checkout only. No real money is collected or credited.' : 'One-time payment on Razorpay. No automatic renewal or ad spend.' }}</p>
<button class="button dark">{{ $mode === 'test' ? 'Test checkout' : 'Pay with Razorpay' }} · {{ \App\Support\Money::format($invoice->balance_minor) }}</button></form>@endif
@if($pending)<form method="post" action="{{ $prefix }}/{{ $invoice->id }}/checkout/refresh">@csrf<p>After paying, return here and refresh. Only a paid status confirmed by Razorpay in live mode credits this bill.</p><button class="button outline">Refresh payment status</button></form>@endif
</section>@endif
@endif
