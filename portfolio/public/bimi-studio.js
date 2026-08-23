/**
 * BIMI Studio — SVG Tiny 1.2 PS Converter & Live Inbox Simulator
 * Zero backend overhead, 100% browser-based & privacy-safe.
 */
(function () {
  'use strict';

  // DOM Elements
  const dropZone = document.getElementById('drop-zone');
  const fileInput = document.getElementById('file-input');
  const canvasPreviewWrapper = document.getElementById('canvas-preview-wrapper');
  const canvasPreview = document.getElementById('canvas-preview');
  const paddingSlider = document.getElementById('padding-slider');
  const paddingValue = document.getElementById('padding-val');
  const scaleSlider = document.getElementById('scale-slider');
  const scaleValue = document.getElementById('scale-val');
  const offsetXSlider = document.getElementById('offset-x-slider');
  const offsetXNum = document.getElementById('offset-x-num');
  const offsetXVal = document.getElementById('offset-x-val');
  const offsetYSlider = document.getElementById('offset-y-slider');
  const offsetYNum = document.getElementById('offset-y-num');
  const offsetYVal = document.getElementById('offset-y-val');
  const alignPresetBtns = document.querySelectorAll('.btn-align-preset');
  const resetTransformBtn = document.getElementById('reset-transform-btn');
  const bgRadios = document.querySelectorAll('input[name="bg-type"]');
  const customColorInput = document.getElementById('custom-color-input');
  const circleMaskCheck = document.getElementById('circle-mask-check');
  const contrastLightBadge = document.getElementById('contrast-light');
  const contrastDarkBadge = document.getElementById('contrast-dark');
  const contrastNote = document.getElementById('contrast-note');
  const downloadBtn = document.getElementById('download-bimi-btn');
  const domainInput = document.getElementById('dns-domain-input');
  const svgUrlInput = document.getElementById('dns-svg-url-input');
  const vmcUrlInput = document.getElementById('dns-vmc-url-input');
  const dnsSnippet = document.getElementById('dns-snippet-code');
  const copyDnsBtn = document.getElementById('copy-dns-btn');

  // Simulator Elements
  const senderInput = document.getElementById('sim-sender-input');
  const subjectInput = document.getElementById('sim-subject-input');
  const clientBtns = document.querySelectorAll('.client-btn');
  const themeBtns = document.querySelectorAll('.theme-btn');
  const simPreviews = document.querySelectorAll('.sim-preview-container');

  // State
  let currentFile = null;
  let rawSvgString = null;
  let rasterImg = null;
  let activeBg = 'transparent';
  let customBgColor = '#ffffff';
  let activePadding = 15; // percentage
  let showCircleMask = true;
  let activeClient = 'gmail-mobile';
  let activeTheme = 'light';

  // Pan & Scale State (User manual adjustment)
  let userPanX = 0;
  let userPanY = 0;
  let userScale = 1.0;
  let isDragging = false;
  let dragStartX = 0;
  let dragStartY = 0;
  let initialPanX = 0;
  let initialPanY = 0;

  // Default sample logo (Bahdan's Mark / Geometric Shield)
  const defaultSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
    <rect width="512" height="512" rx="100" fill="#2563eb"/>
    <path d="M156 128h120c48 0 80 28 80 68 0 28-16 50-40 60 32 10 52 34 52 68 0 44-36 76-88 76H156V128zm64 54v60h52c18 0 30-10 30-30s-12-30-30-30h-52zm0 106v68h58c22 0 36-12 36-34s-14-34-36-34h-58z" fill="#ffffff"/>
  </svg>`;

  function init() {
    setupEventListeners();
    loadSvgContent(defaultSvg);
  }

  function syncOffsetUI() {
    const roundedX = Math.round(userPanX);
    const roundedY = Math.round(userPanY);
    if (offsetXSlider) offsetXSlider.value = roundedX;
    if (offsetXNum) offsetXNum.value = roundedX;
    if (offsetXVal) offsetXVal.textContent = roundedX + 'px';

    if (offsetYSlider) offsetYSlider.value = roundedY;
    if (offsetYNum) offsetYNum.value = roundedY;
    if (offsetYVal) offsetYVal.textContent = roundedY + 'px';
  }

  function setupEventListeners() {
    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('dragover');
      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        handleFile(e.dataTransfer.files[0]);
      }
    });

    fileInput.addEventListener('change', (e) => {
      if (e.target.files && e.target.files[0]) {
        handleFile(e.target.files[0]);
      }
    });

    if (paddingSlider) {
      paddingSlider.addEventListener('input', (e) => {
        activePadding = parseInt(e.target.value, 10);
        if (paddingValue) paddingValue.textContent = activePadding + '%';
        renderStudio();
      });
    }

    if (scaleSlider) {
      scaleSlider.addEventListener('input', (e) => {
        userScale = parseInt(e.target.value, 10) / 100;
        if (scaleValue) scaleValue.textContent = Math.round(userScale * 100) + '%';
        renderStudio();
      });
    }

    if (offsetXSlider) {
      offsetXSlider.addEventListener('input', (e) => {
        userPanX = parseInt(e.target.value, 10);
        syncOffsetUI();
        renderStudio();
      });
    }

    if (offsetXNum) {
      offsetXNum.addEventListener('input', (e) => {
        userPanX = parseInt(e.target.value, 10) || 0;
        syncOffsetUI();
        renderStudio();
      });
    }

    if (offsetYSlider) {
      offsetYSlider.addEventListener('input', (e) => {
        userPanY = parseInt(e.target.value, 10);
        syncOffsetUI();
        renderStudio();
      });
    }

    if (offsetYNum) {
      offsetYNum.addEventListener('input', (e) => {
        userPanY = parseInt(e.target.value, 10) || 0;
        syncOffsetUI();
        renderStudio();
      });
    }

    alignPresetBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const panX = parseInt(btn.getAttribute('data-pan-x') || '0', 10);
        const panY = parseInt(btn.getAttribute('data-pan-y') || '0', 10);
        userPanX = panX;
        userPanY = panY;
        syncOffsetUI();
        renderStudio();
      });
    });

    if (resetTransformBtn) {
      resetTransformBtn.addEventListener('click', () => {
        userPanX = 0;
        userPanY = 0;
        userScale = 1.0;
        if (scaleSlider) scaleSlider.value = 100;
        if (scaleValue) scaleValue.textContent = '100%';
        syncOffsetUI();
        renderStudio();
      });
    }

    // Drag-to-reposition on Canvas
    if (canvasPreviewWrapper) {
      canvasPreviewWrapper.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return; // Left click only
        isDragging = true;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        initialPanX = userPanX;
        initialPanY = userPanY;
        canvasPreviewWrapper.classList.add('is-dragging');
        e.preventDefault();
      });

      window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        const rect = canvasPreviewWrapper.getBoundingClientRect();
        const factor = 512 / (rect.width || 380);
        userPanX = initialPanX + (e.clientX - dragStartX) * factor;
        userPanY = initialPanY + (e.clientY - dragStartY) * factor;
        syncOffsetUI();
        renderStudio();
      });

      window.addEventListener('mouseup', () => {
        if (isDragging) {
          isDragging = false;
          canvasPreviewWrapper.classList.remove('is-dragging');
        }
      });

      // Touch drag support
      canvasPreviewWrapper.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
          isDragging = true;
          dragStartX = e.touches[0].clientX;
          dragStartY = e.touches[0].clientY;
          initialPanX = userPanX;
          initialPanY = userPanY;
          canvasPreviewWrapper.classList.add('is-dragging');
        }
      }, { passive: true });

      window.addEventListener('touchmove', (e) => {
        if (!isDragging || e.touches.length !== 1) return;
        const rect = canvasPreviewWrapper.getBoundingClientRect();
        const factor = 512 / (rect.width || 380);
        userPanX = initialPanX + (e.touches[0].clientX - dragStartX) * factor;
        userPanY = initialPanY + (e.touches[0].clientY - dragStartY) * factor;
        renderStudio();
      }, { passive: true });

      window.addEventListener('touchend', () => {
        if (isDragging) {
          isDragging = false;
          canvasPreviewWrapper.classList.remove('is-dragging');
        }
      });

      // Mouse wheel zoom
      canvasPreviewWrapper.addEventListener('wheel', (e) => {
        e.preventDefault();
        const zoomDelta = e.deltaY < 0 ? 0.08 : -0.08;
        userScale = Math.min(2.5, Math.max(0.3, userScale + zoomDelta));
        if (scaleSlider) scaleSlider.value = Math.round(userScale * 100);
        if (scaleValue) scaleValue.textContent = Math.round(userScale * 100) + '%';
        renderStudio();
      }, { passive: false });
    }

    bgRadios.forEach((radio) => {
      radio.addEventListener('change', (e) => {
        activeBg = e.target.value;
        if (customColorInput) {
          customColorInput.style.display = activeBg === 'custom' ? 'inline-block' : 'none';
        }
        renderStudio();
      });
    });

    if (customColorInput) {
      customColorInput.addEventListener('input', (e) => {
        customBgColor = e.target.value;
        if (activeBg === 'custom') {
          renderStudio();
        }
      });
    }

    if (circleMaskCheck) {
      circleMaskCheck.addEventListener('change', (e) => {
        showCircleMask = e.target.checked;
        const mask = document.getElementById('circle-guide-overlay');
        if (mask) mask.style.display = showCircleMask ? 'block' : 'none';
      });
    }

    // Simulator events
    clientBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        clientBtns.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        activeClient = btn.getAttribute('data-client');
        updateSimulatorClientView();
      });
    });

    themeBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        themeBtns.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        activeTheme = btn.getAttribute('data-theme');
        updateSimulatorTheme();
      });
    });

    if (senderInput) {
      senderInput.addEventListener('input', updateSimulatorText);
    }
    if (subjectInput) {
      subjectInput.addEventListener('input', updateSimulatorText);
    }

    // DNS Generator events
    if (domainInput) domainInput.addEventListener('input', updateDnsSnippet);
    if (svgUrlInput) svgUrlInput.addEventListener('input', updateDnsSnippet);
    if (vmcUrlInput) vmcUrlInput.addEventListener('input', updateDnsSnippet);

    if (copyDnsBtn) {
      copyDnsBtn.addEventListener('click', () => {
        if (!dnsSnippet) return;
        navigator.clipboard.writeText(dnsSnippet.textContent || '').then(() => {
          const original = copyDnsBtn.textContent;
          copyDnsBtn.textContent = 'Copied!';
          setTimeout(() => {
            copyDnsBtn.textContent = original;
          }, 2000);
        });
      });
    }

    if (downloadBtn) {
      downloadBtn.addEventListener('click', downloadCompliantBimiSvg);
    }
  }

  function handleFile(file) {
    currentFile = file;
    userPanX = 0;
    userPanY = 0;
    userScale = 1.0;
    if (scaleSlider) scaleSlider.value = 100;
    if (scaleValue) scaleValue.textContent = '100%';

    const isSvg = file.type === 'image/svg+xml' || file.name.toLowerCase().endsWith('.svg');

    if (isSvg) {
      const reader = new FileReader();
      reader.onload = (e) => {
        rasterImg = null;
        loadSvgContent(e.target.result);
      };
      reader.readAsText(file);
    } else {
      // Raster image (PNG, JPG, WebP)
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          rasterImg = img;
          rawSvgString = null;
          renderStudio();
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  }

  function loadSvgContent(svgStr) {
    rawSvgString = svgStr;
    renderStudio();
  }

  /**
   * Sanitizes and transforms raw SVG/image into strict SVG Tiny 1.2 PS format.
   */
  function buildCleanBimiSvgString(rawSvg, options) {
    const { paddingPercent, bgType, customBg, panX = 0, panY = 0, scale = 1.0 } = options;
    const size = 512;
    const pad = (size * (paddingPercent / 100));
    const innerSize = size - (pad * 2);

    let bgColor = 'none';
    if (bgType === 'white') bgColor = '#ffffff';
    else if (bgType === 'dark') bgColor = '#111827';
    else if (bgType === 'custom') bgColor = customBg || '#ffffff';

    if (rasterImg) {
      // Create SVG Tiny 1.2 wrapping raster
      const canvas = document.createElement('canvas');
      canvas.width = innerSize;
      canvas.height = innerSize;
      const ctx = canvas.getContext('2d');
      if (ctx) {
        const fitScale = Math.min(innerSize / rasterImg.width, innerSize / rasterImg.height);
        const w = rasterImg.width * fitScale;
        const h = rasterImg.height * fitScale;
        const x = (innerSize - w) / 2;
        const y = (innerSize - h) / 2;
        ctx.drawImage(rasterImg, x, y, w, h);
      }
      const dataUrl = canvas.toDataURL('image/png');

      return `<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.2" baseProfile="tiny-ps" viewBox="0 0 512 512" width="100%" height="100%">
  <title>BIMI Logo</title>
  ${bgColor !== 'none' ? `<rect width="512" height="512" fill="${bgColor}"/>` : ''}
  <g transform="translate(256, 256) translate(${panX.toFixed(2)}, ${panY.toFixed(2)}) scale(${scale.toFixed(4)}) translate(-256, -256) translate(${pad}, ${pad})">
    <image xlink:href="${dataUrl}" width="${innerSize}" height="${innerSize}"/>
  </g>
</svg>`;
    }

    if (!rawSvg) return '';

    try {
      const parser = new DOMParser();
      const doc = parser.parseFromString(rawSvg, 'image/svg+xml');
      const svgRoot = doc.documentElement;

      if (svgRoot.nodeName.toLowerCase() !== 'svg') {
        throw new Error('Invalid SVG element');
      }

      // Remove dangerous or non-compliant elements
      const forbiddenTags = ['script', 'foreignobject', 'iframe', 'style', 'audio', 'video', 'animate', 'set'];
      forbiddenTags.forEach((tag) => {
        const elements = doc.getElementsByTagName(tag);
        while (elements.length > 0) {
          elements[0].parentNode.removeChild(elements[0]);
        }
      });

      // Remove forbidden attributes (event handlers, javascript:, editor metadata)
      const allElements = doc.querySelectorAll('*');
      allElements.forEach((el) => {
        const attrs = Array.from(el.attributes);
        attrs.forEach((attr) => {
          const name = attr.name.toLowerCase();
          const val = attr.value.toLowerCase();
          if (name.startsWith('on') || val.includes('javascript:') || name.startsWith('sodipodi:') || name.startsWith('inkscape:') || name.startsWith('sketch:') || name.startsWith('i:') || name.startsWith('adobe:')) {
            el.removeAttribute(attr.name);
          }
        });
      });

      // Extract viewBox or width/height
      let origVb = svgRoot.getAttribute('viewBox');
      let origW = parseFloat(svgRoot.getAttribute('width') || '512');
      let origH = parseFloat(svgRoot.getAttribute('height') || '512');

      let vbX = 0, vbY = 0, vbW = 512, vbH = 512;
      if (origVb) {
        const vbParts = origVb.trim().split(/[\s,]+/).map(Number);
        if (vbParts.length === 4 && vbParts[2] > 0 && vbParts[3] > 0) {
          vbX = vbParts[0];
          vbY = vbParts[1];
          vbW = vbParts[2];
          vbH = vbParts[3];
        }
      } else if (origW > 0 && origH > 0) {
        vbW = origW;
        vbH = origH;
      }

      // Calculate scale to fit in innerSize x innerSize
      const fitScale = Math.min(innerSize / vbW, innerSize / vbH);
      const scaledW = vbW * fitScale;
      const scaledH = vbH * fitScale;
      const offsetX = pad + (innerSize - scaledW) / 2 - (vbX * fitScale);
      const offsetY = pad + (innerSize - scaledH) / 2 - (vbY * fitScale);

      // Extract inner content of source SVG
      let innerContent = '';
      for (let i = 0; i < svgRoot.childNodes.length; i++) {
        const child = svgRoot.childNodes[i];
        if (child.nodeType === Node.ELEMENT_NODE) {
          innerContent += new XMLSerializer().serializeToString(child) + '\n';
        }
      }

      return `<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.2" baseProfile="tiny-ps" viewBox="0 0 512 512" width="100%" height="100%">
  <title>BIMI Logo</title>
  ${bgColor !== 'none' ? `<rect width="512" height="512" fill="${bgColor}"/>` : ''}
  <g transform="translate(256, 256) translate(${panX.toFixed(2)}, ${panY.toFixed(2)}) scale(${scale.toFixed(4)}) translate(-256, -256) translate(${offsetX.toFixed(2)}, ${offsetY.toFixed(2)}) scale(${fitScale.toFixed(4)})">
    ${innerContent}
  </g>
</svg>`;
    } catch (e) {
      console.error('Error generating BIMI SVG:', e);
      return rawSvg;
    }
  }

  function renderStudio() {
    const bimiSvg = buildCleanBimiSvgString(rawSvgString, {
      paddingPercent: activePadding,
      bgType: activeBg,
      customBg: customBgColor,
      panX: userPanX,
      panY: userPanY,
      scale: userScale,
    });

    if (canvasPreview) {
      canvasPreview.innerHTML = bimiSvg;
    }

    // Update Live Simulator Avatars
    const encodedSvg = 'data:image/svg+xml;utf8,' + encodeURIComponent(bimiSvg);
    const simAvatars = document.querySelectorAll('.sim-avatar-img');
    simAvatars.forEach((img) => {
      img.src = encodedSvg;
    });

    evaluateContrast();
    updateDnsSnippet();
  }

  function evaluateContrast() {
    let effectiveBg = '#ffffff';
    if (activeBg === 'white') effectiveBg = '#ffffff';
    else if (activeBg === 'dark') effectiveBg = '#111827';
    else if (activeBg === 'custom') effectiveBg = customBgColor;
    else if (activeBg === 'transparent') effectiveBg = 'transparent';

    if (contrastLightBadge && contrastDarkBadge && contrastNote) {
      if (effectiveBg === 'transparent') {
        contrastLightBadge.textContent = 'Light Inbox: Checks needed';
        contrastLightBadge.className = 'contrast-badge badge-warning';
        contrastDarkBadge.textContent = 'Dark Inbox: Warning';
        contrastDarkBadge.className = 'contrast-badge badge-warning';
        contrastNote.textContent = 'Transparent background detected. If your logo contains dark glyphs, it may become invisible in Dark Mode. Consider adding a solid background or light stroke.';
      } else if (effectiveBg === '#ffffff') {
        contrastLightBadge.textContent = 'Light Inbox: Good';
        contrastLightBadge.className = 'contrast-badge badge-pass';
        contrastDarkBadge.textContent = 'Dark Inbox: Solid White Badge';
        contrastDarkBadge.className = 'contrast-badge badge-pass';
        contrastNote.textContent = 'Solid white background ensures consistent contrast across both light and dark client themes.';
      } else {
        contrastLightBadge.textContent = 'Light Inbox: Pass';
        contrastLightBadge.className = 'contrast-badge badge-pass';
        contrastDarkBadge.textContent = 'Dark Inbox: Pass';
        contrastDarkBadge.className = 'contrast-badge badge-pass';
        contrastNote.textContent = 'Solid brand background maintains readability in all email clients.';
      }
    }
  }

  function updateSimulatorClientView() {
    simPreviews.forEach((preview) => {
      if (preview.id === `preview-${activeClient}`) {
        preview.style.display = 'block';
      } else {
        preview.style.display = 'none';
      }
    });
  }

  function updateSimulatorTheme() {
    const containers = document.querySelectorAll('.inbox-theme-wrapper');
    containers.forEach((wrapper) => {
      if (activeTheme === 'dark') {
        wrapper.classList.add('theme-dark-mode');
        wrapper.classList.remove('theme-light-mode');
      } else {
        wrapper.classList.add('theme-light-mode');
        wrapper.classList.remove('theme-dark-mode');
      }
    });
  }

  function updateSimulatorText() {
    const sender = senderInput && senderInput.value.trim() !== '' ? senderInput.value.trim() : 'Acme Security';
    const subject = subjectInput && subjectInput.value.trim() !== '' ? subjectInput.value.trim() : 'Your Monthly Verification Code';

    document.querySelectorAll('.sim-sender-text').forEach((el) => {
      el.textContent = sender;
    });
    document.querySelectorAll('.sim-subject-text').forEach((el) => {
      el.textContent = subject;
    });
  }

  function updateDnsSnippet() {
    if (!dnsSnippet) return;
    const domain = (domainInput && domainInput.value.trim()) || 'yourcompany.com';
    const svgUrl = (svgUrlInput && svgUrlInput.value.trim()) || `https://${domain}/logo-bimi.svg`;
    const vmcUrl = (vmcUrlInput && vmcUrlInput.value.trim()) || '';

    let tag = `v=BIMI1; l=${svgUrl};`;
    if (vmcUrl !== '') {
      tag += ` a=${vmcUrl};`;
    }

    dnsSnippet.textContent = `default._bimi.${domain} IN TXT "${tag}"`;
  }

  function downloadCompliantBimiSvg() {
    const bimiSvg = buildCleanBimiSvgString(rawSvgString, {
      paddingPercent: activePadding,
      bgType: activeBg,
      customBg: customBgColor,
      panX: userPanX,
      panY: userPanY,
      scale: userScale,
    });

    const blob = new Blob([bimiSvg], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'logo-bimi.svg';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
