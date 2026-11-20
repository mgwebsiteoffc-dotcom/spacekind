<?php

namespace App\Http\Controllers;

use App\Models\EmailDeliverySetting;
use App\Models\EmailTemplate;
use App\Models\RazorpayCredential;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IntegrationController extends Controller
{
    public function index()
    {
        return view('admin.integrations', [
            'credentials' => RazorpayCredential::orderBy('mode')->get(['mode', 'key_id', 'updated_at'])->keyBy('mode'),
            'smtp' => EmailDeliverySetting::find(1, ['host', 'port', 'encryption', 'username', 'from_address', 'from_name']),
            'templates' => EmailTemplate::all()->keyBy('purpose'),
        ]);
    }

    public function gateway(Request $request, string $mode)
    {
        abort_if(config('spacekind.demo'), 403, 'Do not enter credentials in shared accounts.');
        $data = $request->validate([
            'key_id' => ['required', 'string', "regex:/^rzp_{$mode}_[A-Za-z0-9]{6,64}$/D"],
            'key_secret' => ['nullable', 'string', 'min:8', 'max:256', 'regex:/^\S+$/D'],
        ]);
        DB::transaction(function () use ($data, $mode) {
            $record = RazorpayCredential::lockForUpdate()->firstOrNew(['mode' => $mode]);
            if ($record->key_id !== $data['key_id'] && empty($data['key_secret'])) {
                throw ValidationException::withMessages(['key_secret' => 'Supply the matching secret when setting or changing the key ID.']);
            }
            $record->key_id = $data['key_id'];
            if (! empty($data['key_secret'])) {
                $record->key_secret = $data['key_secret'];
            }
            $record->save();
            Audit::record('admin.razorpay_saved', $record, ['mode' => $mode]);
        }, 3);

        return back()->with('success', 'Razorpay details saved. Checkout is not enabled.');
    }

    public function removeGateway(string $mode)
    {
        abort_if(config('spacekind.demo'), 403);
        RazorpayCredential::where('mode', $mode)->delete();
        Audit::record('admin.razorpay_removed', null, ['mode' => $mode]);

        return back()->with('success', 'Stored credential removed. Revoke it at Razorpay if needed.');
    }

    public function emailDelivery(Request $request)
    {
        abort_if(config('spacekind.demo'), 403);
        $data = $request->validate([
            'host' => ['required', 'string', 'max:253', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?$/D'],
            'port' => 'required|integer|between:1,65535', 'encryption' => 'required|in:starttls,tls',
            'username' => 'required|string|max:255', 'smtp_password' => 'nullable|string|max:512',
            'from_address' => 'required|email:rfc|max:254', 'from_name' => 'required|string|max:100',
        ]);
        DB::transaction(function () use ($data) {
            $setting = EmailDeliverySetting::lockForUpdate()->find(1) ?? new EmailDeliverySetting;
            $password = $data['smtp_password'] ?? null;
            unset($data['smtp_password']);
            $setting->fill($data);
            if ((! $setting->exists || $setting->isDirty(['host', 'port', 'encryption', 'username'])) && $password === null) {
                throw ValidationException::withMessages(['smtp_password' => 'Enter the SMTP password for a new or changed connection.']);
            }
            if ($password !== null) {
                $setting->smtp_password = $password;
            }
            $setting->forceFill(['id' => 1])->save();
            Audit::record('admin.email_delivery_saved', $setting);
        }, 3);

        return back()->with('success', 'Email settings saved. No email was sent.');
    }

    public function removeEmailDelivery()
    {
        abort_if(config('spacekind.demo'), 403);
        EmailDeliverySetting::whereKey(1)->delete();
        Audit::record('admin.email_delivery_removed');

        return back()->with('success', 'Stored email settings removed.');
    }

    public function template(Request $request, string $purpose)
    {
        $data = $request->validate(['subject' => ['required', 'string', 'max:160', 'not_regex:/[\r\n]/'], 'body' => 'required|string|max:8000']);
        $template = EmailTemplate::updateOrCreate(compact('purpose'), $data);
        Audit::record('admin.email_template_saved', $template, compact('purpose'));

        return back()->with('success', 'Email template saved. No message was sent.');
    }
}
