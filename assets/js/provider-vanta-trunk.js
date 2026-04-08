(function (window) {
  window.SYNQBgAnimProviders = window.SYNQBgAnimProviders || {};
  const registry = window.SYNQBgAnimProviders;

  function hexToVanta(value, fallback) {
    if (!value) return fallback != null ? fallback : 0x000000;
    if (typeof value === 'number') return value;

    let normalized = String(value).trim();

    if (/^0x[0-9a-fA-F]+$/.test(normalized)) {
      return parseInt(normalized, 16);
    }

    if (normalized[0] === '#') {
      normalized = normalized.slice(1);
    }

    if (normalized.length === 3) {
      normalized =
        normalized[0] +
        normalized[0] +
        normalized[1] +
        normalized[1] +
        normalized[2] +
        normalized[2];
    } else if (normalized.length === 8) {
      normalized = normalized.slice(0, 6);
    } else if (normalized.length !== 6) {
      return fallback != null ? fallback : 0x000000;
    }

    return parseInt('0x' + normalized, 16);
  }

  const providerDefinition = {
    init: function (el, config) {
      if (typeof window.VANTA === 'undefined' || !window.VANTA.TRUNK) {
        console.warn('[SYNQ AB] VANTA.TRUNK not available');
        return null;
      }

      const providerCfg = config.provider || {};
      const targetLayer = config.layer || el;
      const reducedMotion = !!config.motionReduced;

      const color = hexToVanta(providerCfg.color, 0x98465f);
      const bgColor = hexToVanta(providerCfg.bgColor, 0x222426);

      const scale =
        typeof providerCfg.scale === 'number' ? providerCfg.scale : 1.0;
      const spacing =
        typeof providerCfg.spacing === 'number' ? providerCfg.spacing : 0.0;
      const chaos =
        typeof providerCfg.chaos === 'number' ? providerCfg.chaos : 1.0;

      const vantaOptions = {
        el: targetLayer,
        mouseControls: !reducedMotion,
        touchControls: !reducedMotion,
        gyroControls: false,
        minHeight: 200.0,
        minWidth: 200.0,
        scale: scale,
        scaleMobile: 1.0,
        color: color,
        backgroundColor: bgColor,
        spacing: spacing,
        chaos: chaos,
      };

      const instance = window.VANTA.TRUNK(vantaOptions);
      el._synqVantaTrunkInstance = instance;

      return instance;
    },

    destroy: function (el, instance) {
      const resolved = instance || el._synqVantaTrunkInstance;
      if (resolved && typeof resolved.destroy === 'function') {
        resolved.destroy();
      }

      el._synqVantaTrunkInstance = null;
    },
  };

  if (typeof window.SYNQBgAnimRegisterProvider === 'function') {
    window.SYNQBgAnimRegisterProvider('vanta_trunk', providerDefinition);
    return;
  }

  registry['vanta_trunk'] = providerDefinition;

  if (typeof window.SYNQBgAnimInit === 'function') {
    window.SYNQBgAnimInit();
  }
})(window);
