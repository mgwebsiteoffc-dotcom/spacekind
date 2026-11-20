@if(auth()->user()->isOwner())
<details class="quick-create"><summary class="create-trigger"><x-icon name="plus"/>Create</summary>
<nav class="create-panel" aria-label="Quick create"><strong>Create new</strong>
@foreach([['enquiries/new','people','Enquiry'],['finance/documents/new?kind=quotation','file','Quotation'],['finance/documents/new?kind=invoice','wallet','Invoice'],['labour#add-worker','people','Worker'],['blog/new','file','Journal article'],['reels#upload','image','Video'],['campaign-links#build-link','chart','Campaign link']] as [$path,$icon,$label])
<a href="/workspace/{{ $path }}"><x-icon :name="$icon"/>{{ $label }}</a>
@endforeach</nav></details>
@else<a class="create-trigger" href="/workspace/enquiries/new"><x-icon name="plus"/>Enquiry</a>@endif
