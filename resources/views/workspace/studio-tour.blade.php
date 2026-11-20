@php
$stops = [
    ['home', 'Profile & gallery', 'Introduce your studio', 'Tell homeowners where you work, which projects you take on and your usual budget range. A couple looking for a kitchen renovation should be able to tell if you’re a fit.', '#studio-introduction', 'Show me the profile form'],
    ['image', 'Profile & gallery / Works & inspiration', 'Add a project homeowners can picture', 'Choose a photo you have permission to publish. In the caption, say what the client needed and what you changed. Label renders and inspiration separately from completed work.', '#studio-upload', 'Show me the uploader'],
    ['shield', 'Profile & gallery / Listing review', 'Get ready to share your listing', 'Use “Save & submit for review” on the profile form. Your workspace is ready now, but the public listing and enquiry link work only after approval. Once approved, copy the link for your website or social bio.', '#studio-enquiry-link', 'Show me my enquiry link'],
    ['chat', 'Workspace / Enquiries', 'Follow up on your first brief', 'When a homeowner submits your enquiry form, open their brief in Enquiries. Review their location and budget, add a note after you speak, and set a follow-up date.', '/workspace/enquiries', 'Open my enquiries'],
];
@endphp
<dialog id="studio-tour" class="studio-tour small-dialog" aria-labelledby="tour-label" @if(session('start_studio_tour')) data-start-tour @endif>
    <header><strong id="tour-label">Your workspace tour</strong><button type="button" class="text-link" data-tour-skip>Skip tour</button></header>
    <div class="tour-progress" aria-hidden="true">@foreach($stops as $_)<i></i>@endforeach</div>
    @foreach($stops as [$icon, $area, $heading, $body, $url, $label])
        <section data-tour-step @if(!$loop->first) hidden @endif>
            <div class="tour-location"><x-icon :name="$icon"/>{{ $area }}</div>
            <small>Step {{ $loop->iteration }} of {{ $loop->count }}</small>
            <h2 tabindex="-1" aria-label="Step {{ $loop->iteration }} of {{ $loop->count }}: {{ $heading }}">{{ $heading }}</h2>
            <p>{{ $body }}</p><a class="text-link" href="{{ $url }}">{{ $label }} <x-icon name="arrow"/></a>
        </section>
    @endforeach
    <footer><button type="button" class="button outline" data-tour-back>Back</button><button type="button" class="button dark" data-tour-next>Next</button></footer>
</dialog>
