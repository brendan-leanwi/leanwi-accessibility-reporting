 /* Version works ok but requires you to escape focus mode when go back to tab title */
(function () {
  function getTabLabel(tab) {
    const labelEl = tab.querySelector('a.pac_dtm-label, a.tt_tabs_navigation, a, button');
    return (labelEl ? labelEl.textContent : tab.getAttribute('aria-label') || tab.textContent)
      .replace(/\s+/g, ' ')
      .trim();
  }

  function ensureId(el, prefix) {
    if (el.id) return el.id;
    el.id = prefix + Math.random().toString(36).slice(2, 9);
    return el.id;
  }

  function getTabs(tablist) {
    return Array.from(tablist.querySelectorAll('[role="tab"]'));
  }

  function getActiveTab(tablist) {
    const tabs = getTabs(tablist);
    return tabs.find(t => t.getAttribute('aria-selected') === 'true' || t.classList.contains('active')) || tabs[0] || null;
  }

  function getPanelForTab(tab) {
    if (!tab) return null;
    const panelId = tab.getAttribute('aria-controls');
    if (!panelId) return null;
    return document.getElementById(panelId);
  }

  function firstFocusable(panel) {
    if (!panel) return null;
    return panel.querySelector(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), ' +
      '[tabindex]:not([tabindex="-1"])'
    );
  }

  function focusIntoPanel(tablist) {
    const activeTab = getActiveTab(tablist);
    const panel = getPanelForTab(activeTab);
    if (!panel) return;

    // ensure back link (anchor jump) exists + correct label
    const tabId = ensureId(activeTab, 'a11y_tab_');
    let back = panel.querySelector('.a11y-back-to-tab');
    if (!back) {
      back = document.createElement('a');
      back.className = 'a11y-back-to-tab';
      panel.insertAdjacentElement('afterbegin', back);
    }
    back.href = '#' + tabId;
    back.textContent = 'Back to tab: ' + getTabLabel(activeTab) + '.';

    // focus first focusable, otherwise focus the panel container
    if (!panel.hasAttribute('tabindex')) panel.setAttribute('tabindex', '-1');

    const first = firstFocusable(panel);
    (first || panel).focus();
  }

  function focusBackToTab(tablist) {
    const activeTab = getActiveTab(tablist);
    if (activeTab) activeTab.focus();
  }

  function enhanceTablist(tablist) {
    if (tablist.dataset.a11yTabIntoPanelDone === 'true') return;
    tablist.dataset.a11yTabIntoPanelDone = 'true';

    // 1) TAB from the active tab goes into the panel
    // Use capture so we run even if plugin stops propagation later.
    tablist.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab' || e.shiftKey) return;

      const activeTab = getActiveTab(tablist);
      if (!activeTab) return;

      // Are we currently on the active tab OR something inside it (like an inner <a>)?
      const focused = document.activeElement;
      const onActiveTab = (focused === activeTab) || (activeTab.contains(focused));

      if (onActiveTab) {
        e.preventDefault();
        focusIntoPanel(tablist);
      }
    }, true);

    // 2) SHIFT+TAB at the start of the panel goes back to the active tab
    // Attach to each panel once.
    getTabs(tablist).forEach(tab => {
      const panel = getPanelForTab(tab);
      if (!panel || panel.dataset.a11yShiftTabDone === 'true') return;
      panel.dataset.a11yShiftTabDone = 'true';

      panel.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || !e.shiftKey) return;

        const activeTab = getActiveTab(tablist);
        const activePanel = getPanelForTab(activeTab);
        if (!activePanel || activePanel !== panel) return;

        const first = firstFocusable(panel);

        // If focus is on the panel itself, or on the first focusable item, shift+tab should return to tabs
        if (document.activeElement === panel || (first && document.activeElement === first)) {
          e.preventDefault();
          focusBackToTab(tablist);
        }
      }, true);
    });
  }

  function run() {
    document.querySelectorAll('[role="tablist"]').forEach(enhanceTablist);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();