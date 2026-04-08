(function (window) {
  window.SYNQBgAnimProviders = window.SYNQBgAnimProviders || {};

  const providerRegistry = window.SYNQBgAnimProviders;
  const instanceMap = new WeakMap();

  function initElement(el) {
    const type = el.getAttribute('data-bg-anim');
    if (!type) return;

    const rawConfig = el.getAttribute('data-bg-anim-config');
    if (!rawConfig) return;

    let config;
    try {
      config = JSON.parse(rawConfig);
    } catch (e) {
      console.error('[SYNQ Animated Backgrounds] Invalid JSON config', e);
      return;
    }

    const provider = providerRegistry[type];
    // If provider not registered yet, just skip silently.
    if (!provider || typeof provider.init !== 'function') {
      return;
    }

    // Destroy previous instance if any (e.g. Elementor editor rerender)
    const existing = instanceMap.get(el);
    if (existing && typeof provider.destroy === 'function') {
      provider.destroy(el, existing);
      instanceMap.delete(el);
    }

    // Disable on mobile if requested
    if (config.disableMobile && window.matchMedia) {
      const mq = window.matchMedia('(max-width: 767px)');
      if (mq.matches) {
        return;
      }
    }

    // Respect prefers-reduced-motion (simple policy)
    if (window.matchMedia) {
      const rm = window.matchMedia('(prefers-reduced-motion: reduce)');
      if (rm.matches && config.intensity && config.intensity > 0.8) {
        config.intensity = 0.4;
      }
    }

    const instance = provider.init(el, config);
    if (instance) {
      instanceMap.set(el, instance);
    }
  }

  function initAll() {
    // If no providers yet, just bail quietly
    if (!providerRegistry || Object.keys(providerRegistry).length === 0) {
      return;
    }

    const elements = document.querySelectorAll('[data-bg-anim]');
    elements.forEach(initElement);
  }

  // Expose initializer globally so other scripts (or debug) can trigger it if needed.
  window.SYNQBgAnimInit = initAll;

  // Single init on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    // DOM already parsed
    initAll();
  }

  // Elementor editor support: re-init when a container is rendered,
  // but ONLY when we are in edit mode.
  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction(
      'frontend/element_ready/container',
      function () {
        try {
          if (
            window.elementorFrontend.isEditMode &&
            window.elementorFrontend.isEditMode()
          ) {
            initAll();
          }
        } catch (e) {
          // If isEditMode is not available for some reason, fail silently.
        }
      }
    );
  }

})(window);