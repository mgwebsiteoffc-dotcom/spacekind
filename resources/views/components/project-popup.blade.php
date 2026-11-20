@props(['settings'])
<div id="project-capture" data-entry="{{ (int)$settings['entry'] }}" data-exit="{{ (int)$settings['exit'] }}" data-delay="{{ $settings['delay'] }}">
@if($settings['floating'])<a class="button dark project-float" href="/project-brief" data-open-project><x-icon name="chat"/>{{ $settings['button'] }}</a>@endif
<dialog class="project-dialog" aria-labelledby="project-prompt-title"><header><span class="eyebrow">PROJECT CONCIERGE</span><button type="button" class="icon-button" data-close aria-label="Close project form"><x-icon name="close"/></button></header><h2 id="project-prompt-title">{{ $settings['heading'] }}</h2><x-platform-brief-form/></dialog></div>
<script src="/assets/project-popup.js?v=2" defer></script>
