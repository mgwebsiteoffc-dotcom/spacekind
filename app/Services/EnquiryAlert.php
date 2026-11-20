<?php

namespace App\Services;

use App\Models\Business;
use App\Models\EmailDeliverySetting;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Support\Audit;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EnquiryAlert
{
    public function notify(Business $studio, Lead $lead): void
    {
        if (config('spacekind.demo') || $studio->is_demo || ! config('spacekind.enquiry_email')) {
            return;
        }
        try {
            $smtp = EmailDeliverySetting::find(1);
            $recipient = $studio->users()->where('role', 'owner')->where('is_active', true)->orderBy('id')->value('email');
            if (! $smtp || ! $recipient) {
                Audit::record('enquiry.email_unconfigured', $lead);
                return;
            }
            $copy = EmailTemplate::where('purpose', 'enquiry')->first();
            $message = (new Email)->from(new Address($smtp->from_address, $smtp->from_name))->to($recipient)
                ->subject($copy?->subject ?? 'A new project enquiry for your studio')
                ->text(($copy?->body ?? 'A homeowner has sent your studio a project enquiry. Sign in to your Spacekind workspace to review it.')."\n\nReference: ".$lead->reference);
            $this->deliver($smtp, $message);
            Audit::record('enquiry.email_accepted', $lead);
        } catch (\Throwable) {
            // Provider errors can include credentials or addresses. Keep the brief even if SMTP fails.
            logger()->warning('Studio enquiry alert failed.', ['lead_id' => $lead->id]);
        }
    }

    public function deliver(EmailDeliverySetting $smtp, Email $message): void
    {
        $transport = new EsmtpTransport($smtp->host, $smtp->port, $smtp->encryption === 'tls');
        $transport->setRequireTls(true);
        $transport->getStream()->setTimeout(8);
        $transport->setUsername($smtp->username);
        $transport->setPassword($smtp->smtp_password);
        $transport->send($message);
    }
}
