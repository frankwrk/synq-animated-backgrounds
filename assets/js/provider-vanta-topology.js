(function (window) {
  window.SYNQBgAnimProviders = window.SYNQBgAnimProviders || {};
  const registry = window.SYNQBgAnimProviders;

  function hexToVanta(value, fallback) {
    if (!value) return fallback != null ? fallback : 0x000000;

    if (typeof value === 'number') return value;

    let v = String(value).trim();

    // Already 0xRRGGBB
    if (/^0x[0-9a-fA-F]+$/.test(v)) {
      return parseInt(v, 16);
    }

    // Strip leading '#'
    if (v[0] === '#') {
      v = v.slice(1);
    }

    // Handle 3, 6, or 8 hex digits
    if (v.length === 3) {
      // fff -> ffffff
      v = v[0] + v[0] + v[1] + v[1] + v[2] + v[2];
    } else if (v.length === 8) {
      // RRGGBBAA -> RRGGBB (drop alpha)
      v = v.slice(0, 6);
    } else if (v.length !== 6) {
      return fallback != null ? fallback : 0x000000;
    }

    return parseInt('0x' + v, 16);
  }

  registry['vanta_topology'] = {
    init: function (el, config) {
      if (typeof window.VANTA === 'undefined' || !window.VANTA.TOPOLOGY) {
        console.warn('[SYNQ AB] VANTA.TOPOLOGY not available');
        return null;
      }

      const providerCfg = config.provider || {};

      const lineColor = hexToVanta(providerCfg.lineColor, 0x2a2a2a);
      const bgColor   = hexToVanta(providerCfg.bgColor,   0x000000);

      const scale   = typeof providerCfg.scale === 'number'   ? providerCfg.scale   : 1.0;
      const points  = typeof providerCfg.points === 'number'  ? providerCfg.points  : 10.0;
      const spacing = typeof providerCfg.spacing === 'number' ? providerCfg.spacing : 15.0;

      const vantaOptions = {
        el: el,
        mouseControls: true,
        touchControls: true,
        gyroControls: false,
        minHeight: 200.0,
        minWidth: 200.0,
        scale: scale,
        scaleMobile: 1.0,
        color: lineColor,
        backgroundColor: bgColor,
        points: points,
        spacing: spacing
      };

      const instance = window.VANTA.TOPOLOGY(vantaOptions);
      el._synqVantaInstance = instance;

      return instance;
    },

    destroy: function (el, instance) {
      const inst = instance || el._synqVantaInstance;
      if (inst && typeof inst.destroy === 'function') {
        inst.destroy();
      }
      el._synqVantaInstance = null;
    }
  };

})(window);