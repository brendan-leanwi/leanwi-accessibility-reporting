document.addEventListener("DOMContentLoaded", function () {
  const sliders = document.querySelectorAll(".et_pb_slider");

  sliders.forEach((slider) => {
    initializeAccessibleSlider(slider);
  });

  function initializeAccessibleSlider(slider, attempt = 0) {
    const $ = window.jQuery;
    const diviSlider = $ && $.data(slider, "et_pb_simple_slider");

    // Divi initializes its sliders on DOM ready too. Wait for its native
    // slider instance so the custom controls never compete with Divi.
    if (!diviSlider || typeof diviSlider.et_slider_move_to !== "function") {
      if (attempt < 50) {
        window.setTimeout(() => initializeAccessibleSlider(slider, attempt + 1), 100);
      }
      return;
    }

    const slides = slider.querySelectorAll(".et_pb_slide");
    if (!slides.length) return;

    // Build numbered buttons
    const controls = document.createElement("div");
    controls.className = "custom-slider-controls";

    slides.forEach((slide, index) => {
      const btn = document.createElement("button");
      btn.textContent = index + 1;
      btn.setAttribute("aria-label",
                       `Go to slide ${index + 1} of ${slides.length} total slides`);
      btn.setAttribute("data-slide-index", index);

      btn.addEventListener("click", () => {
        activateSlide(index);
        btn.focus();
      });

      controls.appendChild(btn);
    });

    slider.appendChild(controls);
    // Ensure buttons remain tabbable
    controls.querySelectorAll("button").forEach(btn => {
      btn.setAttribute("tabindex", "0");
    });

    const buttons = controls.querySelectorAll("button");

    let currentIndex = 0;

    function syncSlideState() {
      const activeSlide = slider.querySelector(".et_pb_slide.et-pb-active-slide");
      const activeIndex = Array.prototype.indexOf.call(slides, activeSlide);

      if (activeIndex < 0) return;

      currentIndex = activeIndex;

      slides.forEach((slide, i) => {
        slide.setAttribute("aria-hidden", i === activeIndex ? "false" : "true");
        slide.querySelectorAll("img").forEach(img => img.removeAttribute("aria-hidden"));
      });

      buttons.forEach((btn, i) => {
        btn.setAttribute("aria-current", i === activeIndex ? "true" : "false");
      });
    }

    function activateSlide(newIndex) {
      if (diviSlider.et_animation_running || newIndex === currentIndex) return;

      // Use Divi's native transition so it updates active classes, sizing,
      // image centering, animation state, and any automatic rotation timer.
      diviSlider.et_slider_move_to(newIndex);
    }

    syncSlideState();

    // Keep the accessible state aligned when Divi changes slides through
    // autoplay, swipe gestures, arrows, or the custom numbered controls.
    const slideObserver = new MutationObserver(syncSlideState);
    slides.forEach((slide) => {
      slideObserver.observe(slide, {
        attributes: true,
        attributeFilter: ["class"]
      });
    });

    function focusFirstInSlide(slide) {
      const focusable = slide.querySelector(
        "a, button, input, textarea, [tabindex]:not([tabindex='-1']), h1, h2, h3, h4, h5, h6, p, img"
      );
      if (focusable) {
        focusable.setAttribute("tabindex", "-1");
        focusable.focus();
      } else {
        slide.setAttribute("tabindex", "0");
        slide.focus();
      }
    }

    // Keyboard navigation on numbered buttons
    controls.addEventListener("keydown", (e) => {
      const current = document.activeElement;
      if (!current.matches("button[data-slide-index]")) return;
      const idx = parseInt(current.getAttribute("data-slide-index"));

      if (e.key === "ArrowRight") {
        e.preventDefault();
        const next = buttons[(idx + 1) % buttons.length];
        next.focus();
        next.click();
      } else if (e.key === "ArrowLeft") {
        e.preventDefault();
        const prev = buttons[(idx - 1 + buttons.length) % buttons.length];
        prev.focus();
        prev.click();
      } else if (e.shiftKey && e.key === "Tab" && idx === 0) {
        // Shift+Tab from first button → focus slide
        e.preventDefault();
        focusFirstInSlide(slides[currentIndex]);
      }
    });

    // Keyboard navigation inside slides
    slides.forEach((slide, idx) => {
      slide.addEventListener("keydown", (e) => {
        if ((e.key === "Tab" && !e.shiftKey) || e.key === "ArrowDown") {
          // Tab or ArrowDown → focus current numbered button
          e.preventDefault();
          buttons[currentIndex].focus();
        }
      });
      if (!slide.querySelector("a, button, input, textarea, [tabindex]:not([tabindex='-1'])")) {
        slide.setAttribute("tabindex", "0");
      }
    });

    // Global shortcuts
    document.addEventListener("keydown", (e) => {
      if (e.key.toLowerCase() === "s" && e.ctrlKey) {
        // Ctrl+S → focus slide
        e.preventDefault();
        focusFirstInSlide(slides[currentIndex]);
      } else if (e.key.toLowerCase() === "x" && e.ctrlKey) {
        // Ctrl+X → focus current slide number button
        e.preventDefault();
        buttons[currentIndex].focus();
      }
    });
  }
});
  
// Add event listener for skip link keybaord focus
document.addEventListener("DOMContentLoaded", function() {
  const skipLink = document.querySelector('a[href="#after-slider"]');
  const target = document.getElementById("after-slider");

  if (skipLink && target) {
    skipLink.addEventListener("click", function(e) {
      e.preventDefault(); // Prevent default jump
      target.setAttribute("tabindex", "-1"); // Make it focusable temporarily
      target.focus(); // Move keyboard focus
      window.scrollTo({ top: target.offsetTop, behavior: "smooth" }); // Optional smooth scroll
    });
  }
});
