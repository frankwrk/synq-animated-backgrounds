(function (window, document) {
  window.SYNQBgAnimProviders = window.SYNQBgAnimProviders || {};

  const providerRegistry = window.SYNQBgAnimProviders;
  const instanceMap = new WeakMap();
  const observedElements = new Set();
  const activeElements = new Set();
  let intersectionObserver = null;

  function parseConfig(el) {
    const rawConfig = el.getAttribute('data-bg-anim-config');
    if (!rawConfig) return null;

    try {
      return JSON.parse(rawConfig);
    } catch (error) {
      console.error('[SYNQ Animated Backgrounds] Invalid JSON config', error);
      return null;
    }
  }

  function getDirectLayer(el) {
    for (let i = 0; i < el.children.length; i += 1) {
      const child = el.children[i];
      if (child.classList && child.classList.contains('synq-bg-anim-layer')) {
        return child;
      }
    }

    return null;
  }

  function ensureLayer(el) {
    let layer = getDirectLayer(el);

    if (!layer) {
      layer = document.createElement('div');
      layer.className = 'synq-bg-anim-layer';
      layer.setAttribute('aria-hidden', 'true');
      el.insertBefore(layer, el.firstChild);
    }

    for (let i = 0; i < el.children.length; i += 1) {
      const child = el.children[i];
      if (child === layer) continue;
      child.classList.add('synq-bg-anim-content-layer');
    }

    return layer;
  }

  function isMobileDisabled(config) {
    if (!config || !config.disableMobile || !window.matchMedia) return false;
    return window.matchMedia('(max-width: 767px)').matches;
  }

  function applyReducedMotion(config) {
    const runtimeConfig = Object.assign({}, config);

    if (window.matchMedia) {
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
      if (reduced.matches) {
        runtimeConfig.motionReduced = true;
        runtimeConfig.intensity = Math.min(
          Number(runtimeConfig.intensity || 0),
          0.4
        );
      }
    }

    return runtimeConfig;
  }

  function destroyElement(el) {
    const existing = instanceMap.get(el);
    if (!existing) return;

    if (
      existing.provider &&
      typeof existing.provider.destroy === 'function'
    ) {
      existing.provider.destroy(el, existing.instance);
    }

    instanceMap.delete(el);
    activeElements.delete(el);
  }

  function initElement(el) {
    const type = el.getAttribute('data-bg-anim');
    if (!type) return;

    const provider = providerRegistry[type];
    if (!provider || typeof provider.init !== 'function') {
      return;
    }

    const parsedConfig = parseConfig(el);
    if (!parsedConfig) {
      destroyElement(el);
      return;
    }

    if (isMobileDisabled(parsedConfig)) {
      destroyElement(el);
      return;
    }

    destroyElement(el);

    const runtimeConfig = applyReducedMotion(parsedConfig);
    runtimeConfig.layer = ensureLayer(el);

    const instance = provider.init(el, runtimeConfig);
    instanceMap.set(el, {
      provider,
      instance: instance || null,
      type,
    });

    activeElements.add(el);
  }

  function cleanupStaleObservedElements() {
    observedElements.forEach(function (el) {
      if (document.body.contains(el)) {
        return;
      }

      if (intersectionObserver) {
        intersectionObserver.unobserve(el);
      }

      destroyElement(el);
      observedElements.delete(el);
    });
  }

  function initAll(forceImmediate) {
    cleanupStaleObservedElements();

    const elements = document.querySelectorAll('[data-bg-anim]');
    if (elements.length === 0) {
      return;
    }

    elements.forEach(function (el) {
      if (forceImmediate || !intersectionObserver) {
        initElement(el);
      }

      if (intersectionObserver && !observedElements.has(el)) {
        observedElements.add(el);
        intersectionObserver.observe(el);
      }
    });
  }

  function destroyAll() {
    activeElements.forEach(function (el) {
      destroyElement(el);
    });
  }

  function setupIntersectionObserver() {
    if (!('IntersectionObserver' in window)) {
      return;
    }

    intersectionObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            initElement(entry.target);
            return;
          }

          destroyElement(entry.target);
        });
      },
      {
        root: null,
        rootMargin: '200px 0px',
        threshold: 0,
      }
    );
  }

  function registerProvider(type, provider) {
    if (!type || !provider || typeof provider.init !== 'function') {
      return;
    }

    providerRegistry[type] = provider;

    window.dispatchEvent(
      new CustomEvent('synqBgAnimProviderRegistered', {
        detail: { type },
      })
    );
  }

  window.SYNQBgAnimRegisterProvider = registerProvider;
  window.SYNQBgAnimInit = function () {
    initAll(true);
  };
  window.SYNQBgAnimDestroyAll = destroyAll;

  window.addEventListener('synqBgAnimProviderRegistered', function () {
    initAll(true);
  });

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      destroyAll();
      return;
    }

    initAll(false);
  });

  setupIntersectionObserver();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll(false);
    });
  } else {
    initAll(false);
  }

  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    const onContainerReady = function () {
      try {
        if (
          window.elementorFrontend.isEditMode &&
          window.elementorFrontend.isEditMode()
        ) {
          initAll(true);
        }
      } catch (error) {
        // Fail silently in non-standard Elementor runtimes.
      }
    };

    window.elementorFrontend.hooks.addAction(
      'frontend/element_ready/container',
      onContainerReady
    );
    window.elementorFrontend.hooks.addAction(
      'frontend/element_ready/container.default',
      onContainerReady
    );
  }
})(window, document);
