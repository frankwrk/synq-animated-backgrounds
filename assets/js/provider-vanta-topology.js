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
      if (typeof window.VANTA === 'undefined' || !window.VANTA.TOPOLOGY) {
        console.warn('[SYNQ AB] VANTA.TOPOLOGY not available');
        return null;
      }

      const providerCfg = config.provider || {};
      const targetLayer = config.layer || el;
      const reducedMotion = !!config.motionReduced;

      const lineColor = hexToVanta(providerCfg.lineColor, 0x2a2a2a);
      const bgColor = hexToVanta(providerCfg.bgColor, 0x000000);

      const scale =
        typeof providerCfg.scale === 'number' ? providerCfg.scale : 1.0;
      const points =
        typeof providerCfg.points === 'number' ? providerCfg.points : 10.0;
      const spacing =
        typeof providerCfg.spacing === 'number' ? providerCfg.spacing : 15.0;

      const vantaOptions = {
        el: targetLayer,
        mouseControls: !reducedMotion,
        touchControls: !reducedMotion,
        gyroControls: false,
        minHeight: 200.0,
        minWidth: 200.0,
        scale: scale,
        scaleMobile: 1.0,
        color: lineColor,
        backgroundColor: bgColor,
        points: points,
        spacing: spacing,
      };

      const instance = window.VANTA.TOPOLOGY(vantaOptions);
      el._synqVantaInstance = instance;

      return instance;
    },

    destroy: function (el, instance) {
      const resolved = instance || el._synqVantaInstance;
      if (resolved && typeof resolved.destroy === 'function') {
        resolved.destroy();
      }

      el._synqVantaInstance = null;
    },
  };

  if (typeof window.SYNQBgAnimRegisterProvider === 'function') {
    window.SYNQBgAnimRegisterProvider('vanta_topology', providerDefinition);
    return;
  }

  registry['vanta_topology'] = providerDefinition;

  if (typeof window.SYNQBgAnimInit === 'function') {
    window.SYNQBgAnimInit();
  }
})(window);
