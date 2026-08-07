jQuery(function ($) {
  // Target your specific carousel instance/class:
  var $carousel = $('.diec_event_carousel_0 .owl-carousel');

  if (!$carousel.length || typeof $carousel.owlCarousel !== 'function') return;

  // Give the carousel a stable ID for aria-controls relationships
  $carousel.each(function (idx) {
    var $c = $(this);
    if (!$c.attr('id')) $c.attr('id', 'events-carousel-' + idx);
  });

  function ensureLiveRegion($c) {
    var id = $c.attr('id');
    var $live = $('#' + id + '-live');
    if (!$live.length) {
      $live = $('<p/>', {
        id: id + '-live',
        class: 'sr-only',
        'aria-live': 'polite',
        'aria-atomic': 'true'
      }).insertBefore($c);
    }
    return $live;
  }

  function labelCarousel($c) {
    // Treat as a region users can find quickly
    $c.attr({
      role: 'region',
      'aria-roledescription': 'carousel',
      'aria-label': $c.attr('aria-label') || 'Events carousel',
      tabindex: '0'
    });
  }

  function labelNav($c) {
    var $prev = $c.find('.owl-prev');
    var $next = $c.find('.owl-next');
    var $dots = $c.find('.owl-dots .owl-dot');

    // Prev/Next buttons
    $prev.attr({
      type: 'button',
      'aria-label': 'Previous event',
      'aria-controls': $c.attr('id')
    });

    $next.attr({
      type: 'button',
      'aria-label': 'Next event',
      'aria-controls': $c.attr('id')
    });

    // Dots as tabs (simple + widely supported pattern)
    if ($dots.length) {
      $c.find('.owl-dots').attr({ role: 'tablist', 'aria-label': 'Choose an event' });

      $dots.each(function (i) {
        $(this).attr({
          role: 'tab',
          type: 'button',
          'aria-label': 'Go to event ' + (i + 1),
          'aria-controls': $c.attr('id'),
          'aria-selected': $(this).hasClass('active') ? 'true' : 'false',
          tabindex: $(this).hasClass('active') ? '0' : '-1'
        });
      });
    }
  }

  function setSlideA11y($c) {
    // Owl wraps items in .owl-item; "active" ones are visible.
    var $items = $c.find('.owl-item');
    var total = $items.length;

    $items.each(function (i) {
      var $item = $(this);
      var isActive = $item.hasClass('active');

      // Make the slide itself identifiable
      $item.attr({
        role: 'group',
        'aria-roledescription': 'slide',
        'aria-label': 'Item ' + (i + 1) + ' of ' + total,
        'aria-hidden': isActive ? 'false' : 'true'
      });

      // Prevent keyboard focus from entering hidden slides
      $item.find('a, button, input, select, textarea, [tabindex]')
        .each(function () {
          var $el = $(this);

          // store original tabindex once
          if ($el.attr('data-orig-tabindex') == null) {
            var orig = $el.attr('tabindex');
            $el.attr('data-orig-tabindex', orig == null ? '' : orig);
          }

          if (!isActive) {
            $el.attr('tabindex', '-1');
          } else {
            var origVal = $el.attr('data-orig-tabindex');
            if (origVal === '') $el.removeAttr('tabindex');
            else $el.attr('tabindex', origVal);
          }
        });
    });
  }

  function updateDotsA11y($c) {
    var $dots = $c.find('.owl-dots .owl-dot');
    if (!$dots.length) return;

    $dots.each(function () {
      var $dot = $(this);
      var active = $dot.hasClass('active');
      $dot.attr({
        'aria-selected': active ? 'true' : 'false',
        tabindex: active ? '0' : '-1'
      });
    });
  }

  function announce($c, text) {
    var $live = ensureLiveRegion($c);
    // Clear then set to force announcement in more SR/browser combos
    $live.text('');
    window.setTimeout(function () { $live.text(text); }, 10);
  }

  function bindKeyboard($c) {
    // Left/Right to move slides when focus is on the carousel region
    $c.on('keydown', function (e) {
      var key = e.key;

      // Don’t steal arrows from text fields
      var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
      if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

      if (key === 'ArrowLeft') {
        e.preventDefault();
        $c.trigger('prev.owl.carousel');
      } else if (key === 'ArrowRight') {
        e.preventDefault();
        $c.trigger('next.owl.carousel');
      } else if (key === 'Home') {
        e.preventDefault();
        $c.trigger('to.owl.carousel', [0, 300, true]);
      } else if (key === 'End') {
        e.preventDefault();
        // go to last (approx) index
        var count = $c.find('.owl-item').length;
        if (count) $c.trigger('to.owl.carousel', [count - 1, 300, true]);
      }
    });

    // Dots: Enter/Space activate, arrows move between dots
    $c.on('keydown', '.owl-dots .owl-dot', function (e) {
      var $dots = $c.find('.owl-dots .owl-dot');
      var idx = $dots.index(this);

      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).trigger('click');
      } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        $dots.eq(Math.max(0, idx - 1)).focus();
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        $dots.eq(Math.min($dots.length - 1, idx + 1)).focus();
      }
    });
  }

  function addPausePlayIfNeeded($c) {
    // Only add if autoplay is on (or might be enabled later)
    var autoplay = ($c.attr('data-autoplay') || '').toLowerCase();
    var isAutoplay = autoplay === 'on' || autoplay === 'true' || autoplay === '1';

    if (!isAutoplay) return;

    var id = $c.attr('id');
    if ($('#' + id + '-toggle').length) return;

    var $btn = $('<button/>', {
      id: id + '-toggle',
      type: 'button',
      class: 'et_pb_button et_pb_button_small',
      text: 'Pause carousel',
      'aria-pressed': 'false'
    });

    $btn.insertBefore($c);

    var paused = false;
    $btn.on('click', function () {
      paused = !paused;
      if (paused) {
        $c.trigger('stop.owl.autoplay');
        $btn.text('Play carousel').attr('aria-pressed', 'true');
        announce($c, 'Carousel paused');
      } else {
        $c.trigger('play.owl.autoplay', [5000]);
        $btn.text('Pause carousel').attr('aria-pressed', 'false');
        announce($c, 'Carousel playing');
      }
    });
  }

  function initA11y($c) {
    labelCarousel($c);
    ensureLiveRegion($c);
    bindKeyboard($c);

    // Wait for Owl to build nav/dots/items
    $c.on('initialized.owl.carousel refreshed.owl.carousel', function () {
      labelNav($c);
      setSlideA11y($c);
      updateDotsA11y($c);

      // Announce initial slide
      var $active = $c.find('.owl-item.active').first();
      if ($active.length) announce($c, $active.attr('aria-label'));
      addPausePlayIfNeeded($c);
    });

    $c.on('changed.owl.carousel translated.owl.carousel', function () {
      setSlideA11y($c);
      updateDotsA11y($c);

      var $active = $c.find('.owl-item.active').first();
      if ($active.length) announce($c, $active.attr('aria-label'));
    });

    // If Owl already initialized before this script ran, force a refresh pass
    if ($c.hasClass('owl-loaded')) {
      $c.trigger('refresh.owl.carousel');
    }
  }

  $carousel.each(function () {
    initA11y($(this));
  });
});