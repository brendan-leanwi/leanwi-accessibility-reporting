document.addEventListener("DOMContentLoaded", function () {
  function updateVideoLabels() {
    // Step 1: copy iframe titles into parent slide
    document.querySelectorAll('[class*="et_pb_video_slider_item_"]').forEach(item => {
      const iframe = item.querySelector('iframe');
      if (iframe) {
        const title = iframe.getAttribute('title');
        if (title) {
          item.setAttribute('data-video-title', title);
          const playBtn = item.querySelector('.et_pb_video_play');
          if (playBtn) {
            playBtn.setAttribute('aria-label', `Play ${title} video`);
          }
        }
      }
    });

    // Step 2: update carousel items
    document.querySelectorAll('.et_pb_carousel_item').forEach(carousel => {
      const slideId = carousel.getAttribute('data-slide-id');
      const playBtn = carousel.querySelector('.et_pb_video_play');
      const videoItem = document.querySelector(`.et_pb_video_slider_item_${slideId}`);
      if (playBtn && videoItem) {
        const title = videoItem.getAttribute('data-video-title');
        if (title) {
          playBtn.setAttribute('aria-label', `Play ${title} video`);
        }
      }
    });
    
    // Step 3: update prev/next arrow buttons
    document.querySelectorAll('.et_pb_carousel').forEach(carouselWrapper => {
      const visibleItems = carouselWrapper.querySelectorAll('.et-carousel-group.active .et_pb_carousel_item');
      const prevArrow = carouselWrapper.querySelector('.et-pb-arrow-prev');
      const nextArrow = carouselWrapper.querySelector('.et-pb-arrow-next');

      if (prevArrow && visibleItems.length) {
        // Look at the first visible item for context
        const firstVisible = visibleItems[0];
        const label = firstVisible.querySelector('.et_pb_video_play')?.getAttribute('aria-label');
        prevArrow.setAttribute('aria-label', label ? `Previous videos before ${label}` : 'Previous videos');
      }

      if (nextArrow && visibleItems.length) {
        // Look at the last visible item for context
        const lastVisible = visibleItems[visibleItems.length - 1];
        const label = lastVisible.querySelector('.et_pb_video_play')?.getAttribute('aria-label');
        nextArrow.setAttribute('aria-label', label ? `Next videos after ${label}` : 'Next videos');
      }
    });
  }

  // Run once after load, then again after a short delay to catch late-inserted elements
  updateVideoLabels();
  setTimeout(updateVideoLabels, 1000);
});

// Code to manage the transcript toggles
function toggleTranscript(button) {
  const transcriptId = button.getAttribute('aria-controls');
  const transcript = document.getElementById(transcriptId);
  if (!transcript) return;

  const expanded = button.getAttribute('aria-expanded') === 'true';
  button.setAttribute('aria-expanded', !expanded);
  transcript.hidden = expanded;
  transcript.setAttribute('aria-hidden', expanded);

  // Update button text based on current state
  //const transcriptTitle = transcript.querySelector('h4') 
  //  ? transcript.querySelector('h4').textContent.replace('Transcript: ', '')
  //  : 'Transcript';
    
  button.textContent = expanded 
    ? `Show '${transcriptTitle}' Transcript` 
    : `Hide '${transcriptTitle}' Transcript`;
}