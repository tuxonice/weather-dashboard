# EU compliance audit

Static review of the code and templates, plus header checks against the live site (meteo.tlab.pt). This is not legal advice.

Work through the items in order. Tick a box when the fix is merged.

## Summary

The app sets no cookies, has no analytics, accounts, forms or geolocation. The main problem is that the privacy statement claims more than the system actually does.

## 1. Privacy statement is inaccurate (GDPR Art. 4(1), Art. 13)

- [x] Remove the Apache access log from `docu/apache-setup.md` (`CustomLog` line and its `tail` command)
- [x] Remove the Apache error log from `docu/apache-setup.md` (`ErrorLog` line and its `tail` commands)
- [x] Live server: access and error logs are off (confirmed by the operator; not verifiable from this repo)
- [x] Fix the privacy text in (done; the "no access logs" sentence is only true once the live server item above is done) `translations/messages.pt.yaml` and `translations/messages.en.yaml` (`terms.section_privacy_body`)

**Problem:** the text says "Este sítio não recolhe dados pessoais" (this site collects no personal data). That is not true:

- The Apache access log (`CustomLog ... combined`, see `docu/apache-setup.md`) records visitor IP addresses, timestamps, URLs and user agents. An IP address is personal data under GDPR. The CJEU *Breyer* ruling (C-582/14) confirms this when the operator can reasonably identify the visitor.
- Leaflet map pages load tiles from `tile.openstreetmap.org`, so each visitor's IP goes to OpenStreetMap's tile servers.

**Fix:** say what is logged, for how long, and that map tiles come from OpenStreetMap.

## 2. No controller identity, contact or data-subject rights (GDPR Art. 13)

- [x] Name the operator of the site: a "Site operator" section was added to the Terms page and then removed by the operator's decision, since the footer already has the contact email. The operator's name is no longer shown on the site (it remains in the MIT licence line). Re-add it if a legal basis requires it (see §7).
- [x] Add a contact method to the footer (`meteo@tlab.pt`, from `APP_OPERATOR_EMAIL`)
- [x] State a log retention period: no access logs are kept on the live server, per the operator (not verifiable from this repo)
- [x] Data-subject rights: a "Your rights" section was written and then removed from the Terms page by the operator's decision. The site collects no personal data beyond emails sent to the contact address, which the Privacy section now mentions. Art. 13 information duties apply to data collected, so re-add the section if the site starts collecting any.

**Problem:** the only identity is the MIT licence line ("Copyright (c) 2026 Helder Correia"). The footer (`templates/base.html.twig`, around line 210) has no contact information.

## 3. Third-party transfer to OpenStreetMap (GDPR Art. 44–49)

- [x] Decide: disclose it, switch to an EU-hosted tile provider, or self-host tiles. **Decision: keep OpenStreetMap, disclosed** in the privacy notice (done). Visitors' IPs still go to OSM's servers, which may be outside the EU; revisit if that changes.

**Affected templates** (`L.tileLayer('https://{s}.tile.openstreetmap.org/...')`):

- `templates/Forecast/Meteorology/fire-risk.index.html.twig`
- `templates/outlook/index.html.twig`
- `templates/Observation/Meteorology/station.map.html.twig`
- `templates/Observation/Seismic/seismic.index.html.twig`
- `templates/Observation/Biology/bivalves.index.html.twig`

**Note:** the UK (OSM Foundation's base) has an EU adequacy decision. Verify its current status, and verify the tile CDN's hosting locations, before deciding.

## 4. Security headers missing on the live site (GDPR Art. 32)

- [x] Add the headers to the setup doc (`docu/apache-setup.md`, now includes HSTS)
- [ ] Apply the headers to the production Apache vhost (operator action)
- [ ] Verify with `curl -sI https://meteo.tlab.pt/pt`
- [x] Decide on a Content-Security-Policy: strict policy enforced now (`script-src 'self'`, `style-src 'self'`). Implemented in `src/Framework/SecurityHeadersSubscriber.php`. All inline scripts and styles moved to `public/js/pages/` and `public/css/pages/`; styles use `data-style` plus `public/js/pages/inline-styles.js`. Verified: 400 pages crawled with no inline code left, the browser blocks injected inline scripts, and the map, chart, SVG and console checks pass.
- [ ] Still to check on the live site after deploy: the CSP header is sent, and no CSP violations appear in the browser console

**Problem:** a live response returns only `Cache-Control` and `Server`. Missing:

- `X-Content-Type-Options`
- `X-Frame-Options`
- `Referrer-Policy`
- `Permissions-Policy`
- `Strict-Transport-Security`
- `Content-Security-Policy`

`docu/apache-setup.md` lists most of these, so the production vhost likely does not match the doc.

## 5. ePrivacy (cookies and local storage): compliant, document it

- [x] Mention the service worker's on-device cache in the privacy text

**Status:**

- No `Set-Cookie` on the live site, and no `session_start` in the code.
- `sessionStorage` is used for the selected map layer (`templates/Observation/Meteorology/station.map.html.twig`, around line 378). It is never sent to the server and serves the user's own request, so it likely falls under the strictly-necessary exemption.
- The service worker (`public/sw.js`) caches the app shell on the device for offline use.

## 6. Accessibility (Directive 2016/2102, European Accessibility Act 2019/882)

- [x] Confirm whether the site is in scope: operated by a private person, so the Accessibility Directive (public sector only) does not apply, and the European Accessibility Act does not cover a free information service. Not in scope; a full audit is optional good practice.
- [x] Optional: Lighthouse accessibility pass on the home, station map and sea state pages: all score 100 after fixing colour contrast on the home page and the language switcher's accessible name. This is not a full audit: other pages, keyboard use and screen-reader behaviour were not tested.

**Status:**

- The Accessibility Directive applies to public-sector bodies.
- The European Accessibility Act covers a defined list of services, which does not clearly include a weather information site.
- `<html lang>` is set; `aria-` attributes are used; no `<img>` without `alt` was found in the templates.

## 7. Portuguese identification duty (DL 7/2004, Art. 10)

- [x] Check whether the provider identification duty applies. **Decision (operator): DL 7/2004 art. 10 does not apply.** The site is personal and non-economic. This matches the original 2004 text of art. 3 n.º 1, which defines a "serviço da sociedade da informação" as one provided "mediante remuneração ou pelo menos no âmbito de uma actividade económica" ([WIPO Lex](https://www.wipo.int/wipolex/en/legislation/details/5537)). Not checked against the consolidated text (data.dre.pt needs a browser to render) or Law 12-A/2026.
- [x] If it applies, add the identification to the legal notice (item 2): not applicable for now. The Terms page and footer already give the operator's name and contact email. Re-check if the site becomes commercial (ads, sponsorship or paid features).

## 8. Vendored library licences

- [x] Verify amCharts 5's licence: it is a [linkware licence](https://github.com/amcharts/amcharts5/blob/master/LICENSE). Use in any project, including commercial, is allowed, and so is bundling the files with your own project (self-hosting is covered). Conditions: the branding link/logo must not be disabled, hidden or altered, and the original LICENSE file must ship with the files. No licence key is set in `public/vendor/amcharts5/index.js`, so the logo shows, and no CSS or JS in the app hides it.
- [x] Add the original amCharts LICENSE file to `public/vendor/amcharts5/LICENSE` (required by the licence)
- [x] Confirm the free-tier licence text explicitly permits self-hosting the library files: yes, "Bundle amCharts software with your own projects" (see the LICENSE file above)
- [x] Confirm the licences of Leaflet, Chart.js, Bootstrap and Bootstrap Icons. Bootstrap 5.3.3, Bootstrap Icons 1.11.3 and Chart.js 4.4.3 are MIT; Leaflet 1.9.4 is BSD 2-Clause. These require the copyright notice and licence text to be kept with the files, not a visible credit. The original LICENSE file for each is now in its `public/vendor/<library>/` folder, taken from the tag matching the vendored version. The bundled files also keep their own header comments.
- [x] Map attribution: Leaflet's default attribution control is on, and every tile layer sets "© OpenStreetMap contributors" linking to openstreetmap.org/copyright (OSM's own tile-use requirement)

**Files:** `public/vendor/` (added in commit `d7c9bd2`).

## Not applicable (as far as reviewed)

- **AI Act:** the tide model is a deterministic harmonic calculation, not an AI system.
- **Cyber Resilience Act:** a hosted web service is not a product placed on the market.
- **NIS2:** applies only to entities in covered sectors.
- **DSA:** no user-generated content and no hosting of third-party material.

## Suggested order

1. Items 1 and 2 (privacy text, operator identity, contact)
2. Item 3 (OpenStreetMap decision)
3. Item 4 (security headers)
4. Items 7 and 8 (verification)
5. Items 5 and 6 (documentation and scope)
