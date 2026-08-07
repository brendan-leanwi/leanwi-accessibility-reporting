//Working version with titles as H2s
document.addEventListener('DOMContentLoaded', () => {
  const tabModules = Array.from(document.querySelectorAll('.dsm_advanced_tabs')).filter(moduleEl => {
    const tablist = moduleEl.querySelector('.dsm-advanced-tabs-wrapper');
    const contentWrapper = moduleEl.querySelector('.dsm-advanced-tabs-content-wrapper');
    return (
      tablist &&
      contentWrapper &&
      tablist.querySelector('.dsm-tab') &&
      contentWrapper.querySelector('.dsm-content-wrapper')
    );
  });

  if (!tabModules.length) return;

  if (!document.getElementById('tab-instructions')) {
    const inst = document.createElement('div');
    inst.id = 'tab-instructions';
    inst.className = 'sr-only';
    inst.textContent =
      'Tabs: Use Left/Right Arrow or the heading 2 key to move between tabs. Press Enter or Space to activate a tab and move into its content. ' +
      'From inside tab content, press Control+H to return to the active tab.';
    document.body.appendChild(inst);
  }

  const focusableSelector =
    'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), ' +
    'details, [tabindex]:not([tabindex="-1"])';

  function normalizeId(el) {
    if (!el) return '';
    const trimmed = (el.getAttribute('id') || '').trim();
    if (trimmed && el.id !== trimmed) el.id = trimmed;
    return el.id;
  }

  function findActiveTabIndex(tabs) {
    let idx = tabs.findIndex(t => t.classList.contains('dsm-tab-active-state'));
    if (idx >= 0) return idx;
    idx = tabs.findIndex(t => (t.getAttribute('aria-selected') || '').trim() === 'true');
    if (idx >= 0) return idx;
    return 0;
  }

  function applyTabAria(tabs, activeIndex) {
    tabs.forEach((tab, i) => {
      tab.setAttribute('role', 'tab');
      tab.setAttribute('aria-describedby', 'tab-instructions');
      tab.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
      tab.setAttribute('tabindex', i === activeIndex ? '0' : '-1');
    });
  }

  function getPanels(moduleEl) {
    return Array.from(moduleEl.querySelectorAll('.dsm-advanced-tabs-content-wrapper .dsm-content-wrapper'));
  }

  function findPanelForTab(tab, moduleEl, panels, indexFallback) {
    const controlsId = (tab.getAttribute('aria-controls') || '').trim();
    if (controlsId) {
      const byId = moduleEl.querySelector(`#${CSS.escape(controlsId)}`);
      if (byId) return byId;
    }

    const key = (tab.getAttribute('data-tabid') || tab.getAttribute('data-tabId') || '').trim();
    if (key) {
      const byData = moduleEl.querySelector(
        `.dsm-content-wrapper[data-contentid="${key}"], .dsm-content-wrapper[data-contentId="${key}"]`
      );
      if (byData) return byData;
    }

    return panels[indexFallback] || null;
  }

  // 🔥 This is the key fix: guarantee only ONE panel is visible/active
  function enforceSingleActivePanel(moduleEl, activePanel) {
    const panels = getPanels(moduleEl);

    panels.forEach(panel => {
      panel.setAttribute('role', 'tabpanel');
      panel.setAttribute('aria-describedby', 'tab-instructions');

      const isActive = panel === activePanel;

      // ARIA sync
      panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');

      // Class/state sync (don’t touch tab visuals—only panels)
      if (isActive) {
        panel.classList.add('dsm-active');
        panel.classList.remove('none');

        const inner = panel.querySelector('.dsm-inner-content-wrapper');
        if (inner) inner.classList.remove('none');

        // Ensure visible (matches plugin’s "active = flex")
        panel.style.display = 'flex';
      } else {
        panel.classList.remove('dsm-active');
        panel.classList.add('none');

        const inner = panel.querySelector('.dsm-inner-content-wrapper');
        if (inner) inner.classList.add('none');

        // Force hidden so we never get two visible panels
        panel.style.display = 'none';
      }
    });
  }

  function focusIntoPanel(panel) {
    if (!panel) return;
    const firstFocusable = panel.querySelector(focusableSelector);
    if (firstFocusable) {
      firstFocusable.focus();
    } else {
      panel.setAttribute('tabindex', '-1');
      panel.focus();
    }
  }

  function focusActiveTab(tabs) {
    const idx = findActiveTabIndex(tabs);
    (tabs[idx] || tabs[0])?.focus();
  }

  tabModules.forEach((moduleEl, moduleIndex) => {
    let currentIndex = 0;

    const tablist = moduleEl.querySelector('.dsm-advanced-tabs-wrapper');
    const contentWrapper = moduleEl.querySelector('.dsm-advanced-tabs-content-wrapper');
    if (!tablist || !contentWrapper) return;

    const tabs = Array.from(tablist.querySelectorAll('.dsm-tab'));
    const panels = Array.from(contentWrapper.querySelectorAll('.dsm-content-wrapper'));
    if (!tabs.length || !panels.length) return;

    tabs.forEach(tab => {
      // Prevent double wrapping
      if (tab.parentElement && tab.parentElement.tagName === 'H2') return;

      const h2 = document.createElement('h2');
      h2.className = 'dsm-tab-heading';
      h2.style.margin = '0';
      h2.style.fontSize = 'inherit';

      tab.parentNode.insertBefore(h2, tab);
      h2.appendChild(tab);
    });

    tablist.setAttribute('role', 'tablist');
    tablist.setAttribute('aria-describedby', 'tab-instructions');

    // Normalize IDs + relationships + return shortcut
    tabs.forEach((tab, i) => {
      const tabId = normalizeId(tab) || `dsm-tab-${moduleIndex}-${i}`;
      tab.id = tabId;

      const controlsRaw = (tab.getAttribute('aria-controls') || '').trim();
      const panel =
        (controlsRaw && moduleEl.querySelector(`#${CSS.escape(controlsRaw)}`)) ||
        panels[i] ||
        null;

      if (panel) {
        const panelId = normalizeId(panel) || `dsm-panel-${moduleIndex}-${i}`;
        panel.id = panelId;

        tab.setAttribute('aria-controls', panelId);
        panel.setAttribute('aria-labelledby', tabId);

        panel.addEventListener('keydown', (e) => {
          const k = (e.key || '').toLowerCase();
          const ctrlOnly = e.ctrlKey && !e.altKey && !e.metaKey;

          if (ctrlOnly && (k === 'h' || e.key === 'ArrowUp')) {
            e.preventDefault();
            (tabs[currentIndex] || tabs[0])?.focus();
          }
        });
      }
    });

    // Initial sync
    const initialActive = findActiveTabIndex(tabs);
    applyTabAria(tabs, initialActive);
    const initialPanel = findPanelForTab(tabs[initialActive], moduleEl, panels, initialActive);
    if (initialPanel) {
      enforceSingleActivePanel(moduleEl, initialPanel);
    }
    
    function handleActivation(indexHint) {
      // Let Divi Supreme do whatever it does, but we will enforce the final state.
      currentIndex = indexHint;

      setTimeout(() => {
        const activeTab = tabs[indexHint] || tabs[0];
        const panel = findPanelForTab(activeTab, moduleEl, panels, indexHint);

        // Update ARIA selection based on what the user chose (not what the plugin reports)
        applyTabAria(tabs, indexHint);

        if (panel) {
          enforceSingleActivePanel(moduleEl, panel);
          setTimeout(() => focusIntoPanel(panel), 80);
        }
      }, 0);
    }


    tabs.forEach((tab, i) => {
      tab.addEventListener('click', () => handleActivation(i));

      tab.addEventListener('keydown', (e) => {
        const key = e.key;
        const count = tabs.length;
        let targetIndex = null;

        if (key === 'ArrowRight') targetIndex = (i + 1) % count;
        if (key === 'ArrowLeft') targetIndex = (i - 1 + count) % count;
        if (key === 'Home') targetIndex = 0;
        if (key === 'End') targetIndex = count - 1;

        if (targetIndex !== null) {
          e.preventDefault();
          tabs[targetIndex].focus();
          return;
        }

        if (key === 'Enter' || key === ' ') {
          e.preventDefault();
          tab.click();
        }
      });
    });
  });
});
