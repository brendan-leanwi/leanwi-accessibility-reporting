document.addEventListener('DOMContentLoaded', function () {
    const tabsContainer = document.querySelector('.et_pb_tabs_controls');
    const tabs = tabsContainer.querySelectorAll('li');
    const panels = document.querySelectorAll('.et_pb_all_tabs .et_pb_tab');

    // Set ARIA roles
    tabsContainer.setAttribute('role', 'tablist');
		tabsContainer.setAttribute('aria-describedby', 'tab-instructions');
  
    tabs.forEach((tab, index) => {
        const link = tab.querySelector('a');
        const panel = panels[index];

        // Wrap <a> in <h3> (semantic heading)
        const heading = document.createElement('h3');
        link.setAttribute('role', 'tab');
        link.setAttribute('id', `tab-${index}`);
        link.setAttribute('aria-controls', `tabpanel-${index}`);
        link.setAttribute('aria-selected', tab.classList.contains('et_pb_tab_active') ? 'true' : 'false');
        link.setAttribute('tabindex', tab.classList.contains('et_pb_tab_active') ? '0' : '-1');
        heading.appendChild(link.cloneNode(true));
        link.replaceWith(heading);

        // Setup panel attributes
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('id', `tabpanel-${index}`);
        panel.setAttribute('aria-labelledby', `tab-${index}`);
        if (!tab.classList.contains('et_pb_tab_active')) {
            panel.style.display = 'none';
        }

        // Add click behavior
        const newLink = heading.querySelector('a');
        newLink.addEventListener('click', function (e) {
            e.preventDefault();

            // Deselect all tabs and hide panels
            tabs.forEach((t, i) => {
                const a = t.querySelector('a');
                if (a) {
                    a.setAttribute('aria-selected', 'false');
                    a.setAttribute('tabindex', '-1');
                }
                panels[i].style.display = 'none';
                panels[i].classList.remove('et-pb-active-slide');
                t.classList.remove('et_pb_tab_active');
            });

            // Select this tab and show its panel
            newLink.setAttribute('aria-selected', 'true');
            newLink.setAttribute('tabindex', '0');
            tab.classList.add('et_pb_tab_active');
            panel.style.display = 'block';
            panel.classList.add('et-pb-active-slide');

            // Wait briefly before moving focus so the DOM is visibly updated
            setTimeout(() => {
                const firstFocusable = panel.querySelector(
                    'a, button, input, textarea, select, [tabindex]:not([tabindex="-1"])'
                );
                if (firstFocusable) {
                    firstFocusable.focus();
                } else {
                    panel.setAttribute('tabindex', '-1');
                    panel.focus();
                }
            }, 100); // 100ms works well with Divi's transitions
        });


        // Add arrow key navigation
        newLink.addEventListener('keydown', function (e) {
            let newIndex = null;
            if (e.key === 'ArrowRight') newIndex = (index + 1) % tabs.length;
            if (e.key === 'ArrowLeft') newIndex = (index - 1 + tabs.length) % tabs.length;

            if (newIndex !== null) {
                e.preventDefault();
                const targetTab = tabs[newIndex].querySelector('a');
                if (targetTab) {
                    targetTab.click();
                    targetTab.focus();
                }
            }
        });
    });
});