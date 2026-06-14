document.addEventListener('DOMContentLoaded', function () {
  const burger = document.getElementById('navBurger');
  const nav    = document.getElementById('mainNav');

  if (burger && nav) {
    burger.addEventListener('click', function () {
      const isOpen = nav.classList.toggle('mobile-open');
      burger.classList.toggle('open', isOpen);
    });
  }

  // Accordion for nav items on mobile
  document.querySelectorAll('.nav-item > a').forEach(function (link) {
    link.addEventListener('click', function (e) {
      if (window.innerWidth > 768) return;
      const item = link.closest('.nav-item');
      if (!item.querySelector('.dropdown')) return;
      e.preventDefault();
      const wasOpen = item.classList.contains('expanded');
      document.querySelectorAll('.nav-item').forEach(function (i) {
        i.classList.remove('expanded');
      });
      if (!wasOpen) item.classList.add('expanded');
    });
  });

  // Close nav on resize to desktop
  window.addEventListener('resize', function () {
    if (window.innerWidth > 768 && nav) {
      nav.classList.remove('mobile-open');
      if (burger) burger.classList.remove('open');
    }
  });

  // Sponsoren-Slider: Dot-Indikator + Sync mit Scroll-Position
  const track = document.querySelector('[data-sponsors-track]');
  const dotsEl = document.querySelector('[data-sponsors-dots]');

  if (track && dotsEl) {
    let dots = [];
    let activeIndex = 0;

    const pageCount = () => {
      const pages = Math.ceil(track.scrollWidth / track.clientWidth);
      // Wenn alles sichtbar ist, keine Dots zeigen.
      return track.scrollWidth - track.clientWidth < 2 ? 0 : pages;
    };

    const buildDots = () => {
      const count = pageCount();
      dotsEl.innerHTML = '';
      dots = [];
      if (count <= 1) return;
      for (let i = 0; i < count; i++) {
        const b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('aria-label', 'Sponsoren Seite ' + (i + 1));
        b.addEventListener('click', () => {
          track.scrollTo({ left: i * track.clientWidth, behavior: 'smooth' });
        });
        dotsEl.appendChild(b);
        dots.push(b);
      }
      updateActive();
    };

    const updateActive = () => {
      if (!dots.length) return;
      const idx = Math.round(track.scrollLeft / track.clientWidth);
      const clamped = Math.max(0, Math.min(dots.length - 1, idx));
      if (clamped === activeIndex) return;
      dots[activeIndex] && dots[activeIndex].classList.remove('is-active');
      dots[clamped].classList.add('is-active');
      activeIndex = clamped;
    };

    let scrollRaf = null;
    track.addEventListener('scroll', () => {
      if (scrollRaf) return;
      scrollRaf = requestAnimationFrame(() => {
        updateActive();
        scrollRaf = null;
      });
    }, { passive: true });

    // Keyboard-Support für den Track (Pfeiltasten).
    track.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      e.preventDefault();
      const dir = e.key === 'ArrowRight' ? 1 : -1;
      track.scrollBy({ left: dir * track.clientWidth, behavior: 'smooth' });
    });

    let resizeT = null;
    window.addEventListener('resize', () => {
      clearTimeout(resizeT);
      resizeT = setTimeout(() => {
        activeIndex = 0;
        buildDots();
      }, 150);
    });

    // Initial: erster Dot aktiv setzen.
    buildDots();
    if (dots.length) {
      dots[0].classList.add('is-active');
    }
  }

});
