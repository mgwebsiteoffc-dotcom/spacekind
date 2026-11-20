# Visual assets, media and permissions

The application reuses the approved Spacekind HTML concept’s warm ivory/forest-green/rust palette, editorial typography and large interior imagery. **Spacekind is the confirmed brand.** The prototype’s former provisional-name notice is historical, not the current instruction.

## Reference images

`public/assets/interiors/*.webp` are nine optimised concept references, **not verified studio-owned portfolios**. Sources recorded during concept work include CharmyDecor, IdeaBloom, Euphoria Interiors, HomeDecoric and Unsplash. Some source material may contain renders or AI imagery. Their presence in a concept does not establish a production licence.

- Seeded studios and their project/contact/outcome records are fictional.
- Seeded galleries use **Reference image** classification and reference captions.
- Inspiration/placeholder imagery is identified as a Spacekind reference, never fabricated completed studio work.
- Studio upload forms require rights confirmation and completed/render/concept classification.

**Do not launch these portfolios commercially as-is.** Replace references with owned/licensed assets and authentic business material, or independently obtain the necessary permissions.

## Phase 1B reference-motion studies

`public/assets/reels/warm-reference.mp4` and `kitchen-reference.mp4` are four-second, 432 × 768 H.264/no-audio motion studies generated locally with FFmpeg from the existing living/kitchen references. Visible labels identify development/reference motion. These are not camera footage or a claim of completed work by a studio. Reusing the underlying image does **not** resolve its licensing requirements.

The seed captions explain the illustrative nature. The associated `https://en.wikipedia.org/wiki/Interior_design` is general reading only: it is not an image source attribution, endorsement or evidence that the video was published there. No remote fetching or Instagram publishing occurs.

`ContentDemoSeeder` adds four explicitly illustrative articles: two platform/editorial and two studio perspectives, including a named-author example. Byline names, content and outcomes are fictional demonstration material, not professional endorsements or customer case studies.

The generated 13 MiB QA upload/geometry cover under `qa/` are synthetic test fixtures, not public portfolio content and not included in the source package. Public demo reference clips are included so a freshly seeded fictional demo and isolated media tests can run.

## Fonts

Six local DM Sans / DM Serif Display TTF files came from the concept’s embedded font resources. Runtime does not call Google Fonts. Upstream SIL Open Font License notices are in `docs/font-licenses/`; preserve them when redistributing. Original TTFs are retained; no WOFF2 conversion is claimed.

## Private/public media separation

Plans remain private CRM attachments, outside the web root, behind authenticated tenant-scoped downloads. Gallery/video content is public only when eligible and its studio is approved/active. Draft video files/covers and article-revision covers use controlled routes with access checks; they do not become public just because someone knows their path. Demo reference clips are deliberately distributable public development assets; real uploads are not stored there.

File/MIME/codec/size/duration checks and rights checkboxes do not replace production malware screening, EXIF/metadata/privacy review, moderation, takedown, licensing or retention/deletion operations. Back up media separately from the development relational JSON snapshot.


## Phase 2 PDF dependency / assets

`composer.lock` adds `dompdf/dompdf` (3.1.6), `dompdf/php-font-lib`, `dompdf/php-svg-lib`, `masterminds/html5` and `sabberworm/php-css-parser`; install from the lock and retain the dependency licences under vendor. Dependencies are not bundled into the incremental patch. Dompdf uses its bundled DejaVu Sans for INR/PDF text. No remote fonts/images/scripts are fetched by the PDF renderer.

New local `public/assets/finance.css` / `finance.js` extend the existing design; no front-end build or CDN is required. `tests/Fixtures/billing-logo.jpg` is a tiny synthetic geometric QA image, not a customer logo. Actual studio JPG logos are private uploads and deliberately excluded from the patch. Issued snapshot paths keep old branding; do not clean up historic logo files without a retention/reference audit.
