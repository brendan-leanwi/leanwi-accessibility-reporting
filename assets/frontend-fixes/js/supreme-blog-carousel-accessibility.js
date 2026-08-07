document.addEventListener('DOMContentLoaded', function () {
  const carousel = document.querySelector('.dsm-blog-carousel');
  if (!carousel) return;

  // Detect screen reader users (indirectly via prefers-reduced-motion)
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // If the Swiper is already initialized by your theme/plugin, get instance
  const swiperEl = carousel.querySelector('.swiper-container');
  const swiper = swiperEl?.swiper;

  if (swiper) {
    if (prefersReducedMotion && swiper.autoplay) {
      swiper.autoplay.stop();
    }

    // Add manual navigation enhancements (optional)
    addAccessibilityFeatures();
  } else {
    // Swiper might not be ready yet; wait for it
    const interval = setInterval(() => {
      const readySwiper = carousel.querySelector('.swiper-container')?.swiper;
      if (readySwiper) {
        if (prefersReducedMotion && readySwiper.autoplay) {
          readySwiper.autoplay.stop();
        }
        addAccessibilityFeatures();
        clearInterval(interval);
      }
    }, 100);
  }

  // Your accessibility function from earlier
  function addAccessibilityFeatures() {
    carousel.setAttribute('role', 'region');
    carousel.setAttribute('aria-label', 'Library News Carousel');
    carousel.setAttribute('aria-describedby', 'carousel-instructions');
    carousel.setAttribute('tabindex', '-1');

    carousel.querySelectorAll('.swiper-slide').forEach(slide => {
      slide.setAttribute('tabindex', '0');
    });

    const instructions = document.createElement('p');
    instructions.classList.add('sr-only');
    instructions.id = 'carousel-instructions';
    instructions.textContent = 'Use the next and previous buttons to navigate through the carousel items. Use the escape key to leave the carousel.';
    carousel.insertBefore(instructions, carousel.firstChild);

    const liveRegion = document.createElement('div');
    liveRegion.classList.add('sr-only');
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.setAttribute('aria-atomic', 'true');
    liveRegion.id = 'carousel-live-region';
    carousel.appendChild(liveRegion);

    const nextButton = carousel.querySelector('.swiper-button-next');
    const prevButton = carousel.querySelector('.swiper-button-prev');

    if (nextButton) {
      nextButton.setAttribute('aria-label', 'Next slide');
      nextButton.setAttribute('role', 'button');
    }

    if (prevButton) {
      prevButton.setAttribute('aria-label', 'Previous slide');
      prevButton.setAttribute('role', 'button');
    }

    const slides = carousel.querySelectorAll('.swiper-slide');
    slides.forEach((slide, index) => {
      slide.setAttribute('role', 'group');
      slide.setAttribute('aria-roledescription', 'slide');
      slide.setAttribute('aria-label', `Slide ${index + 1} of ${slides.length}`);
    });

    function updateAriaHidden() {
      slides.forEach(slide => {
        slide.removeAttribute('aria-hidden');
        slide.setAttribute('tabindex', '0');
      });
    }
    
    updateAriaHidden();

    const paginationWrapper = carousel.querySelector('.swiper-pagination');
    if (paginationWrapper) {
      paginationWrapper.setAttribute('aria-hidden', 'true');
      paginationWrapper.setAttribute('inert', '');
    }

    const swiperInstance = carousel.querySelector('.swiper-container')?.swiper;
    if (swiperInstance) {
      swiperInstance.on('slideChange', () => {
        updateAriaHidden();
        hideNavigationArrows();
        hidePaginationBullets(); // Call this again in case bullets were re-rendered
      });
    }
    
    function hideNavigationArrows() {
      document.querySelectorAll('.swiper-button-prev, .swiper-button-next').forEach(btn => {
        btn.setAttribute('tabindex', '-1');
        btn.setAttribute('aria-hidden', 'true');
        btn.removeAttribute('role');
      });
    }
    hideNavigationArrows();

    function hidePaginationBullets() {
      document.querySelectorAll('.swiper-pagination-bullet').forEach(el => {
        el.setAttribute('tabindex', '-1');
        el.setAttribute('aria-hidden', 'true');
      });
    }
    hidePaginationBullets();

  }
  document.querySelector('.skip-carousel').addEventListener('click', function(e) {
    e.preventDefault();
    const target = document.getElementById('after-carousel');
    if (target) {
      target.setAttribute('tabindex', '-1');
      target.focus();
    }
  });
  
  //Capture the escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      const target = document.getElementById('after-carousel');
      if (target) {
        target.setAttribute('tabindex', '-1');
        target.focus();
      }
    }
  });

});
