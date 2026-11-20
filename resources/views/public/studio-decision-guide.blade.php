<nav class="studio-jump-links" aria-label="Explore Spacekind for studios">
    <a href="#why-spacekind">Why Spacekind?</a><a href="#compare">Compare workflows</a><a href="#pricing">Pricing</a><a href="#getting-started">Getting started</a>
</nav>
<section id="why-spacekind" class="landing-section studio-fit" aria-labelledby="fit-title">
    <div class="studio-fit-intro"><div class="eyebrow section-kicker">WHY SPACEKIND</div><h2 id="fit-title">Where does your studio lose the thread?</h2>
    <p>You don’t need to replace the way you design. Start with the part of running the studio that needs a better handoff.</p><a class="text-link" href="#compare">See what changes <x-icon name="right"/></a></div>
    <div class="studio-fit-options">
        <details name="studio-priority" open><summary><span class="fit-num">01</span>“We get enquiries, but too little detail.”</summary><div><h3>Start the call with a brief.</h3><p>Share your approved studio profile instead of asking every prospect the same opening questions. Their location, floor area, scope, budget and readiness arrive together. Decide whether to book a call or clarify the fit.</p><a class="text-link" href="/#studios">See how homeowners find a studio <x-icon name="arrow"/></a></div></details>
        <details name="studio-priority"><summary><span class="fit-num">02</span>“Follow-ups depend on whoever remembers.”</summary><div><h3>Give the next conversation an owner.</h3><p>After a site visit, assign the enquiry, note what the family is deciding and set a follow-up date. A teammate can pick up the conversation without searching your personal chats.</p></div></details>
        <details name="studio-priority"><summary><span class="fit-num">03</span>“We know what we quoted, not what’s been paid.”</summary><div><h3>Keep the estimate and the payment record together.</h3><p>Prepare an itemised quotation, convert an accepted quote to an invoice and record the customer’s payment. Keep receipts with the financial record rather than treating a message as proof of payment.</p></div></details>
    </div>
</section>
<section id="compare" class="landing-section studio-compare" aria-labelledby="compare-title">
    <div class="section-top"><div><div class="eyebrow section-kicker">TRADITIONAL WORKFLOW / SPACEKIND</div><h2 id="compare-title">Same studio. A different working day.</h2><p>If you run your studio through calls, chats and spreadsheets, here’s what changes.</p></div></div>
    <div class="compare-grid"><div class="compare-head" aria-hidden="true"><span>Studio task</span><span>With separate tools</span><strong>With Spacekind</strong></div>
    @foreach([
        ['First enquiry', 'Ask for the location, square footage and budget across several messages.', 'Review one project brief before deciding whether to arrange a consultation.'],
        ['Team handoff', 'Forward the chat and explain who promised to call next.', 'Assign an owner, save notes and check overdue follow-ups in the pipeline.'],
        ['Quotes & collections', 'Copy line items between documents and reconcile receipts in a sheet.', 'Carry an accepted quotation into an invoice, then record payments and linked receipts.'],
        ['Campaign review', 'Compare ad reports with a separate list of client names.', 'Use campaign links and retained source labels to review studio-recorded outcomes. Unknown sources stay unknown.'],
    ] as [$task,$traditional,$connected])
        <article class="compare-row"><h3>{{ $task }}</h3><p><small>With separate tools</small>{{ $traditional }}</p><p><small>With Spacekind</small>{{ $connected }}</p></article>
    @endforeach</div>
    <div class="compare-next"><p><strong>Try it on one enquiry.</strong> You can use the workspace before your public listing is approved. Registration doesn’t start a paid checkout.</p><a class="button dark" href="/register">Create my studio workspace <x-icon name="arrow"/></a></div>
</section>
