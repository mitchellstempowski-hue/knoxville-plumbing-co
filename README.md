# Knoxville Plumbing Co — knoxvilleplumbingco.com

Static lead-gen site for Knoxville, TN plumbing (rank & rent). Zero-dependency HTML/CSS/JS. Cloned from the site-01 template and fully re-localized.

- **No phone number.** This site is form-first and free to run: every CTA ("Request a Callback" / "Get a Free Quote") routes to the quote form on the homepage (/#quote). The visitor leaves their number in the form; a local pro calls them back. Do NOT add a business number unless one is assigned later.
- Quote form posts JSON to the mailer at https://vellops.com/quote-knoxville.php (hosted on Hostinger; source in this repo as quote-knoxville.php). Fields: name, phone, service, details + company honeypot.
- Honesty rules: no fabricated reviews/testimonials, no invented address or license claims; the site is a referral service and says so in the footer.
- Deploy: GitHub Pages. Push to main, then verify every changed URL live. Preserve the CNAME file.
