@php
    $owner = auth()->user()->isOwner();
    // Each group opens on the current screen, including detail and edit pages.
    $groups = ['Client work' => [
        ['Enquiries', 'people', 'enquiries', 'enquiries*'], ['Clients', 'chat', 'clients', 'clients*'], ['Projects', 'home', 'projects', 'projects*'],
    ]];
    if ($owner) {
        $groups['Money & labour'] = [
            ['Quotations & invoices', 'file', 'finance', 'finance*'], ['Payments & advances', 'wallet', 'finance/payments', 'finance/payments'],
            ['Worker register', 'people', 'labour', 'labour*'], ['Attendance', 'calendar', 'labour/attendance', 'labour/attendance'],
        ];
        $groups['Portfolio & marketing'] = [
            ['Works & inspiration', 'image', 'studio', 'studio'], ['Testimonials', 'chat', 'testimonials', 'testimonials'],
            ['Videos & links', 'image', 'reels', 'reels*'], ['Studio journal', 'file', 'blog', 'blog*'], ['Campaign links', 'chart', 'campaign-links', 'campaign-links*'],
        ];
    }
    $groups['Studio settings'] = $owner ? [
        ['Team & access', 'people', 'team', 'team'], ['Finance settings', 'wallet', 'finance/settings', 'finance/settings'],
        ['Service catalogue', 'list', 'finance/catalogue', 'finance/catalogue'], ['Subscription bills', 'file', 'billing', 'billing*'],
        ['Plan & roadmap', 'spark', 'modules', 'modules'], ['All studio tools', 'grid', 'tools', 'tools'],
    ] : [['Plan & roadmap', 'spark', 'modules', 'modules']];
    $current = function ($item) {
        if ($item[2] === 'finance' && request()->is('workspace/finance/payments', 'workspace/finance/settings', 'workspace/finance/catalogue')) return false;
        if ($item[2] === 'labour' && request()->is('workspace/labour/attendance')) return false;
        return request()->is('workspace/'.$item[3]);
    };
@endphp
<button type="button" class="studio-menu-toggle" aria-expanded="true" aria-controls="studio-navigation" hidden><x-icon name="list"/> Studio menu <x-icon name="right"/></button>
<nav class="app-nav studio-navigation" aria-label="Studio workspace" id="studio-navigation">
<a href="/workspace" class="app-nav-link {{ request()->is('workspace')?'active':'' }}" @if(request()->is('workspace')) aria-current="page" @endif><x-icon name="grid"/><span>Overview</span></a>
@foreach($groups as $label=>$items)
<details class="studio-nav-group" @if(collect($items)->contains($current) || ($loop->first && request()->is('workspace'))) open @endif>
<summary>{{ $label }}<x-icon name="right"/></summary>
@foreach($items as $item)
<a class="app-nav-link {{ $current($item)?'active':'' }}" href="/workspace/{{ $item[2] }}" @if($current($item)) aria-current="page" @endif><x-icon :name="$item[1]"/><span>{{ $item[0] }}</span></a>
@endforeach</details>
@endforeach</nav>
