/* Tailwind runtime config — must load right after the Tailwind CDN,
   before the page renders.
   ------------------------------------------------------------------
   Design tokens for StyleDesk — the single source of truth for
   colors + font across every page.
   In the Laravel + Vue build these become tailwind.config.js theme tokens
   (or CSS custom properties) so components stay consistent.
   ------------------------------------------------------------------ */
tailwind.config = {
  theme: {
    extend: {
      fontFamily: { sans: ['Inter', 'sans-serif'] },
      colors: {
        // Text
        head: '#0f0f10',   // headings / near-black
        ink: '#23272f',    // primary body text
        sub: '#6b7280',    // secondary text
        faint: '#9ca3af',  // muted / placeholder

        // Surfaces & borders
        line: '#e5e7eb',   // hairline borders
        stroke: '#d1d5db', // input borders (slightly stronger)
        hover: '#f3f4f6',  // hover fill
        sel: '#eef1f4',    // selected fill
        canvas: '#ffffff', // page background

        /* Brand — custom properties rather than literals, because App
           Settings › Branding repaints these at runtime. The fallback
           after each comma is the StyleDesk default, so a page that
           never loads the branding store still renders correctly.

           Consequence: an opacity modifier (bg-brand/10) cannot be used
           on these. Tailwind rewrites those to rgb(<channels> / alpha)
           and a var() holding a full colour has no channels to give it.
           Use a literal tint instead. */
        brand: 'var(--sd-brand, #3d348b)',            // primary CTA + app bar surface
        'brand-dark': 'var(--sd-brand-dark, #2f2870)',// primary hover / pressed
        banner: 'var(--sd-banner, #3c096c)',          // announcement strip above the app bar
        link: 'var(--sd-link, #2563eb)',              // hyperlinks
        accent: 'var(--sd-accent, #b45309)',          // highlights, badges, chart marks
        btn: 'var(--sd-btn, #3d348b)',                // button fill, if it differs from brand
        'btn-ink': 'var(--sd-btn-ink, #ffffff)',      // text on that fill

        // Status
        success: '#22c55e',
        warning: '#f59e0b',
        danger: '#ef4444',
      },
      borderRadius: {
        card: '10px',
      },
    },
  },
};
