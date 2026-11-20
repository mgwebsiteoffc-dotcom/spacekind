<?php

namespace App\Support;

use Illuminate\Http\Request;

class CampaignLabels
{
    public const KEYS = ['source', 'medium', 'campaign', 'content', 'term'];

    public static function rules(bool $required = false): array
    {
        $rules = [];
        foreach (self::KEYS as $key) {
            $rules['utm_'.$key] = [$required && in_array($key, ['source', 'medium', 'campaign']) ? 'required' : 'nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9 _\.\-]+$/', 'not_regex:/\d{8,}/'];
        }

        return $rules;
    }

    public static function fromQuery(Request $r): array
    {
        $labels = [];
        foreach (self::KEYS as $key) {
            $v = $r->query('utm_'.$key);
            if (is_string($v) && strlen($v) <= 80 && preg_match('/^[a-zA-Z0-9 _\.\-]+$/', $v) && ! preg_match('/\d{8,}/', $v)) {
                $labels['utm_'.$key] = $v;
            }
        }

        return $labels;
    }

    public static function query(Request $r, ?string $context = null): string
    {
        $params = self::fromQuery($r);
        if ($context) {
            $params['entry_context'] = $context;
        }

        return $params ? '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
    }
}
