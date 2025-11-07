// stats.js - Obsługa animacji statystyk
(function () {
  window.upInit = window.upInit || {};

  window.upInit.stats = function (containerEl) {
    if (!containerEl) return;

    console.log('Statystyki załadowane - wyświetlanie danych z całego okresu');

    // Animacja elementów przy załadowaniu
    animateElements(containerEl);
  };

  // Funkcja animująca elementy statystyk
  function animateElements(containerEl) {
    const cards = containerEl.querySelectorAll('.kptr-stat-card, .kptr-category-bar');

    cards.forEach((card, index) => {
      // CSS już obsługuje animacje, ale możemy dodać dodatkową interakcję jeśli potrzeba
      card.style.willChange = 'opacity, transform';
      
      // Usunięcie will-change po zakończeniu animacji
      setTimeout(() => {
        card.style.willChange = 'auto';
      }, 800 + (index * 50));
    });
  }
})();
