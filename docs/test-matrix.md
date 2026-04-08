# SYNQ Animated Backgrounds Test Matrix

Use this matrix before releasing provider changes.

## Target Environment

- WordPress `6.9.4`
- Elementor `4.0.1`
- Elementor prerelease check sample: `4.0.0-dev4`
- Elementor Pro (enabled)
- PHP `8.4`

## Verification Checklist

| ID | Area | Scenario | Expected Result |
|---|---|---|---|
| T1 | Compatibility | Elementor deactivated | Plugin does not bootstrap; admin notice explains requirement |
| T2 | Compatibility | Elementor active, compatible versions | Plugin bootstraps with no admin notice |
| T2b | Compatibility | Elementor prerelease build (`4.0.0-dev4`) with supported numeric base | Plugin bootstraps with no admin notice |
| T3 | Controls | Container with animation disabled | No `data-bg-anim` attributes in container markup |
| T4 | Controls | Container with animation enabled + provider selected | `data-bg-anim` and JSON config attributes present |
| T5 | Sanitization | Invalid color value injected in Elementor data | Fallback hex defaults are used safely |
| T6 | Sanitization | Out-of-range numeric values in Elementor data | Values are clamped to configured min/max |
| T7 | Loading | Page with no animated containers | Provider scripts are not enqueued |
| T8 | Loading | Page with Vanta animated container | Core + Vanta scripts are enqueued |
| T8b | Loading | Page with Trunk animated container | Core + Trunk scripts are enqueued |
| T9 | Runtime | Scroll animated container into view | Animation initializes when intersecting |
| T10 | Runtime | Scroll container far out of view | Animation instance is destroyed |
| T11 | Runtime | Browser tab hidden then shown | Active instances destroy on hide and re-init on show |
| T12 | Editor | Change control values in Elementor editor | Re-render updates animation without duplicate instances |
| T12b | Editor | Page initially has no animated containers; enable animation in editor | Animation scripts are already available and preview initializes immediately |
| T13 | Mobile policy | Disable on Mobile = Yes on viewport <= 767px | Animation does not initialize |
| T14 | Reduced motion | `prefers-reduced-motion: reduce` enabled | Provider receives reduced-motion behavior |
| T15 | Layering | Complex container content (buttons, overlays, z-index) | Canvas remains behind content and does not block interaction |
| T16 | Updates | New higher release published on configured GitHub repo | WordPress shows plugin update available |
| T17 | Updates | Release package has source zipball folder name mismatch | Update process normalizes folder or aborts safely without replacing active plugin with invalid path |
| T18 | Updates | GitHub API unavailable or rate-limited | Plugin remains active; update check fails silently without frontend/admin fatal errors |

## Manual Test Steps

1. Create two Elementor pages:
   - One page with no animated containers.
   - One page with at least two animated containers.
2. For animated page, set different provider values for each container.
3. Verify script loading in browser devtools:
   - No-animation page should not load provider scripts.
   - Animation page should load only required provider scripts.
4. In Elementor editor, modify controls and confirm instance cleanup/re-init works.
5. Test on desktop and mobile viewport widths.
6. Enable `prefers-reduced-motion` and re-check runtime behavior.
7. Repeat the same checks for both `Vanta – Topology` and `Vanta – Trunk` provider types.
8. Publish a test tag/release in GitHub and confirm WordPress update discovery.
9. Attempt update using source zipball-only release and verify directory normalization safety behavior.

## Local Code Checks

Run before release:

```bash
for f in $(rg --files -g '*.php'); do php -l "$f"; done
node --check assets/js/core.js
node --check assets/js/provider-vanta-topology.js
node --check assets/js/provider-vanta-trunk.js
```

Expected: all commands exit `0`.
