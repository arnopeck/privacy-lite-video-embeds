(function () {
  'use strict';

  function buildEmbedUrl(videoId, autoplay) {
    var params = [
      'rel=0',
      'modestbranding=1',
      'playsinline=1'
    ];

    if (autoplay) {
      params.unshift('autoplay=1');
    }

    return 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(videoId) + '?' + params.join('&');
  }

  function loadVideo(wrapper) {
    var videoId = wrapper.getAttribute('data-plye-video-id');
    if (!videoId || !/^[A-Za-z0-9_-]{6,20}$/.test(videoId)) {
      return;
    }

    var autoplay = wrapper.getAttribute('data-plye-autoplay') === '1';
    var iframe = document.createElement('iframe');

    iframe.className = 'plye-video__iframe';
    iframe.src = buildEmbedUrl(videoId, autoplay);
    iframe.title = 'YouTube video player';
    iframe.loading = 'lazy';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    iframe.allowFullscreen = true;

    wrapper.classList.add('plye-video--loaded');
    wrapper.innerHTML = '';
    wrapper.appendChild(iframe);
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.plye-video__button');
    if (!button) {
      return;
    }

    var wrapper = button.closest('.plye-video');
    if (!wrapper || wrapper.classList.contains('plye-video--loaded')) {
      return;
    }

    event.preventDefault();
    loadVideo(wrapper);
  });
}());
