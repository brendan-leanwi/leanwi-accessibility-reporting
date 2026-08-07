jQuery(document).ready(function ($) {
  // Hide Month/Week/Day buttons from screen readers
  document.querySelectorAll(
    '.fc-dayGridMonth-button, .fc-timeGridWeek-button, .fc-timeGridDay-button'
  ).forEach(button => {
    button.setAttribute('aria-hidden', 'true');
    button.setAttribute('tabindex', '-1');
  });

  function fixListViewAccessibility() {
    const table = document.querySelector('.fc-list-table');
    if (!table) return false;
    
    // Add a SR-only table header once
    if (!table.querySelector('thead')) {
      const thead = document.createElement('thead');
      thead.innerHTML = `
        <tr>
          <th scope="col" class="sr-only">Time</th>
          <th scope="col" class="sr-only">Indicator</th>
          <th scope="col" class="sr-only">Event</th>
        </tr>
      `;
      table.insertBefore(thead, table.querySelector('tbody'));
    }

    table.setAttribute('role', 'table');

    table.querySelectorAll('tr.fc-list-heading').forEach(row => {
      row.setAttribute('role', 'rowgroup');
      row.querySelectorAll('td').forEach(cell => {
        cell.setAttribute('scope', 'colgroup');
      });
    });

    table.querySelectorAll('tr.fc-list-item').forEach(row => {
      row.setAttribute('role', 'row');

      const timeCell = row.querySelector('.fc-list-item-time');
      const titleCell = row.querySelector('.fc-list-item-title');
      const dotCell = row.querySelector('.fc-list-item-marker');

      if (timeCell && !timeCell.hasAttribute('data-accessibility-fixed')) {
        timeCell.setAttribute('role', 'cell');

        const timeText = timeCell.textContent.trim();

        if (timeText === '') {
          timeCell.innerHTML = '<span class="sr-only">Event occurs throughout the day</span><span aria-hidden="true" tabindex="-1">All Day</span>';
        } else {
          timeCell.innerHTML = '<span class="sr-only">Event time is ' + timeText + '</span><span aria-hidden="true" tabindex="-1">' + timeText + '</span>';
        }

        // Prevent double-processing
        timeCell.setAttribute('data-accessibility-fixed', 'true');
      }

      if (titleCell) {
        titleCell.setAttribute('role', 'cell');
      }

      if (dotCell) {
        dotCell.setAttribute('aria-hidden', 'true');
        dotCell.setAttribute('tabindex', '-1');
      }
    });

    return true;
  }

  // Retry for up to 2 seconds (every 100ms)
  let retries = 0;
  const maxRetries = 20;
  const interval = setInterval(() => {
    const success = fixListViewAccessibility();
    retries++;
    if (success || retries >= maxRetries) {
      clearInterval(interval);
    }
  }, 100);

  // Also rerun when calendar changes views
  $(document).on('tribeEventsAfterViewRender', function () {
    setTimeout(() => {
      fixListViewAccessibility();
    }, 500);
  });
});
