@props(['value'])<span {{ $attributes->class(['status-chip','stage-'.Str::slug($value)]) }}>{{ $value }}</span>
