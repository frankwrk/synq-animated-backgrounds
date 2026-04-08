# SYNQ Animated Backgrounds for Elementor

A WordPress plugin that adds animated background controls to **Elementor Container** widgets.

Current version: `0.2.0`
Bundled provider: **Vanta Topology**

## Overview

This plugin adds a **Background Animation** control section to Elementor Containers and initializes a JS animation provider on the frontend only when needed.

At runtime, the plugin:

1. Registers container controls in Elementor.
2. Serializes sanitized config into container `data-*` attributes.
3. Loads only required frontend assets (core + used providers).
4. Initializes/destroys animation instances with lifecycle-aware JS logic.

## Compatibility Requirements

Plugin header requirements:

- WordPress `>= 6.6`
- PHP `>= 7.4`
- Elementor `>= 4.0.0`
- Tested up to WordPress `6.9.4`

If requirements are not met, the plugin does not bootstrap and shows an **admin notice** with exact compatibility errors.

## Architecture

### Bootstrap

File: `synq-animated-backgrounds.php`

- Declares plugin metadata and version constants.
- Validates compatibility (WordPress, PHP, Elementor).
- Shows admin notices when incompatible.
- Loads plugin classes only after requirements pass.
- Loads translations via `load_plugin_textdomain()`.

### Core Plugin Orchestrator

File: `includes/class-plugin.php`

Responsibilities:

- Registers available providers.
- Injects Elementor controls into Container layout tab.
- Detects animation usage from `_elementor_data` and pre-enqueues only required providers.
- Adds render-time fallback enqueue for dynamic Elementor contexts.
- Sanitizes and clamps shared config values before writing to HTML.

### Provider Contract

File: `includes/class-provider-interface.php`

Each provider must implement:

- `get_type()`
- `get_label()`
- `register_controls($element)`
- `normalize_config(array $settings, array $base_config)`
- `enqueue_scripts()`

### Vanta Topology Provider

File: `includes/providers/class-provider-vanta-topology.php`

Responsibilities:

- Adds provider-specific controls.
- Sanitizes colors (`sanitize_hex_color`) and clamps numeric ranges server-side.
- Registers/enqueues pinned dependency versions:
  - `three.js r121`
  - `p5.js 1.1.9`
  - `vanta.topology 0.5.24`
- Exposes a filter for URL overrides:
  - `synq_ab_vanta_topology_script_urls`

## Elementor Controls

Section: **Background Animation** (Container > Layout)

Shared controls:

- `Enable Background Animation` (`synq_bg_anim_enable`)
- `Animation Type` (`synq_bg_anim_type`)
- `Disable on Mobile` (`synq_bg_anim_disable_mobile`)
- `Intensity (generic)` (`synq_bg_anim_intensity`, clamped `0.0` to `1.0`)
- `Speed (generic)` (`synq_bg_anim_speed`, clamped `0.5` to `2.0`)

Vanta Topology controls:

- `Line Color`
- `Background Color`
- `Scale` (clamped `0.5` to `2.0`)
- `Points (Density)` (clamped `5` to `20`)
- `Spacing` (clamped `5` to `50`)

## Frontend Lifecycle

### Core runtime

File: `assets/js/core.js`

Key behaviors:

- Maintains provider registry: `window.SYNQBgAnimProviders`.
- Adds registration API: `window.SYNQBgAnimRegisterProvider(type, provider)`.
- Re-runs initialization after provider registration via custom event (`synqBgAnimProviderRegistered`).
- Supports lazy init/destroy using `IntersectionObserver`.
- Destroys active animations on `document.hidden`, re-inits on visibility restore.
- Handles Elementor editor rerenders (`frontend/element_ready/container` hooks).

### Layering model (stability)

Instead of forcing `z-index` for every child with a blanket selector, runtime creates an explicit layer:

- `.synq-bg-anim-layer` for background canvas host.
- `.synq-bg-anim-content-layer` on non-layer children.

CSS file: `assets/css/frontend.css`

This isolates animation canvas behavior and avoids brittle global stacking rules.

### Provider runtime

File: `assets/js/provider-vanta-topology.js`

- Uses the registration handshake (`SYNQBgAnimRegisterProvider`) when available.
- Mounts Vanta to explicit layer (`config.layer`) instead of the raw wrapper.
- Applies reduced-motion behavior by disabling mouse/touch controls when `motionReduced` is set.

## Asset Loading Strategy

### Conditional loading

`includes/class-plugin.php` performs two-stage loading:

1. **Pre-detection stage** (`wp_enqueue_scripts`):
   - Parses current post `_elementor_data` JSON.
   - Finds enabled animation providers.
   - Enqueues only required assets.

2. **Render fallback stage** (`elementor/frontend/before_render`):
   - Ensures assets still load in dynamic contexts (theme builder/templates) where pre-detection may miss.

This replaces the old “enqueue all providers on all Elementor pages” behavior.

## Extension Guide (Adding Providers)

1. Add provider class in `includes/providers/` implementing `SYNQ_Bg_Provider_Interface`.
2. Register provider in `SYNQ_Animated_Backgrounds_Plugin::register_providers()`.
3. Add provider runtime JS in `assets/js/`.
4. Register provider in JS with `window.SYNQBgAnimRegisterProvider('type', providerObject)`.
5. Sanitize and clamp all provider settings in `normalize_config()`.
6. Pin dependency versions (avoid floating `@latest`).
7. Validate using [`docs/test-matrix.md`](docs/test-matrix.md).

## Hardening Changes in v0.2.0

The following improvements were implemented for stability/performance before adding new providers:

1. Added WordPress plugin compatibility headers and license metadata.
2. Added runtime compatibility checks + admin notices for unsupported environments.
3. Switched from global provider enqueueing to conditional per-page/provider loading.
4. Pinned external provider dependency versions (removed `@latest` usage).
5. Added provider registration handshake/event so late-loaded providers initialize reliably.
6. Added lazy init/destroy (`IntersectionObserver`) and page-visibility lifecycle controls.
7. Added server-side sanitization and numeric clamping for shared/provider configs.
8. Replaced blanket stacking approach with explicit animation background layer.
9. Added test matrix documentation for regression-safe provider expansion.

## File Map

- `synq-animated-backgrounds.php` — bootstrap, compatibility checks, notices.
- `includes/class-plugin.php` — controls, config serialization, conditional asset loading.
- `includes/class-provider-interface.php` — provider contract.
- `includes/providers/class-provider-vanta-topology.php` — Vanta provider implementation.
- `assets/js/core.js` — provider registry + lifecycle runtime.
- `assets/js/provider-vanta-topology.js` — Vanta provider JS adapter.
- `assets/css/frontend.css` — explicit layer-based stacking.
- `docs/test-matrix.md` — QA scenarios and expected outcomes.
