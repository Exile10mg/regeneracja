// my-reports.js - modal w tym samym widoku (bez fetchFreshReport)
(function () {
  window.upInit = window.upInit || {};

  window.upInit.myReports = function (containerEl) {
    if (!containerEl) return;

    // Paginacja
    const tbody = containerEl.querySelector('#reports-tbody');
    const paginationInfo = containerEl.querySelector('#pagination-info');
    const prevBtn = containerEl.querySelector('#prev-page-btn');
    const nextBtn = containerEl.querySelector('#next-page-btn');
    const currentPageInput = containerEl.querySelector('#current-page');
    const totalPagesInput = containerEl.querySelector('#total-pages');
    const pageSizeInput = containerEl.querySelector('#page-size');

    if (tbody && paginationInfo && currentPageInput && totalPagesInput && prevBtn && nextBtn) {
      const pageSize = parseInt(pageSizeInput?.value || '10', 10);
      const totalPages = parseInt(totalPagesInput.value, 10);
      let currentPage = 1;

      function showPage(page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        currentPageInput.value = page;

        // Ukryj wszystkie wiersze
        const allRows = tbody.querySelectorAll('tr[data-date]');
        allRows.forEach((row, index) => {
          const rowPage = Math.floor(index / pageSize) + 1;
          if (rowPage === page) {
            row.classList.remove('is-hidden');
          } else {
            row.classList.add('is-hidden');
          }
        });

        // Aktualizuj przyciski
        prevBtn.disabled = (page === 1);
        nextBtn.disabled = (page >= totalPages);

        // Aktualizuj info o stronie
        paginationInfo.textContent = `Strona ${page} z ${totalPages}`;
      }

      prevBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        showPage(currentPage - 1);
      });

      nextBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        showPage(currentPage + 1);
      });

      // Inicjalizacja
      showPage(1);
    }

    /* ---------- Modal system - uproszczony ---------- */
    const TODAY_MODAL_CACHE = {};

    async function ensureTodayModalTemplate(dateISO) {
      if (TODAY_MODAL_CACHE[dateISO]) return TODAY_MODAL_CACHE[dateISO];
      const u = new URL(UPPANEL.ajax_url);
      u.searchParams.set('action', 'up_load_view');
      u.searchParams.set('view', 'today');
      u.searchParams.set('date', dateISO);
      u.searchParams.set('nonce', UPPANEL.nonce);
      const res = await fetch(u.toString(), { credentials: 'same-origin' });
      const json = await res.json();
      if (!json?.success) throw new Error('Nie udało się pobrać widoku „Dzisiejszy raport"');
      const temp = document.createElement('div');
      temp.innerHTML = json.data.html || '';
      const modal = temp.querySelector('#kptr-modal');
      const confirm = temp.querySelector('#kptr-confirm');
      const styles = Array.from(temp.querySelectorAll('style')).map(s => s.textContent).join('\n');
      TODAY_MODAL_CACHE[dateISO] = { modal, confirm, styles };
      return TODAY_MODAL_CACHE[dateISO];
    }

    function attachStylesOnce(cssText) {
      if (!cssText) return;
      const id = 'up-today-modal-style';
      if (document.getElementById(id)) return;
      const s = document.createElement('style');
      s.id = id;
      s.textContent = cssText;
      document.head.appendChild(s);
    }

    function removeIfExists(sel) {
      document.querySelectorAll(sel).forEach(n => n.remove());
    }

    function closeAnyModal() {
      document.querySelectorAll('#kptr-modal, #kptr-confirm').forEach(m => {
        m.setAttribute('aria-hidden', 'true');
        m.remove();
      });
    }

    async function saveReport(mode, modal, dateISO) {
      const form = modal.querySelector('#kptr-form');
      if (!form) return;
      const fd = new FormData(form);
      fd.append('action', 'up_save_report');
      fd.append('mode', mode);
      fd.append('date', dateISO);
      fd.append('nonce', UPPANEL.report_nonce);
      const footer = modal.querySelector('.kptr-modal__footer');
      const prev = footer?.innerHTML;
      if (footer) footer.innerHTML = '<span class="kp-saving">Zapisywanie…</span>';
      try {
        const r = await fetch(UPPANEL.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd });
        const j = await r.json();
        if (j?.success) {
          closeAnyModal();
          // Przeładuj widok moich raportów
          window.location.hash = 'view=my-reports&_=' + Date.now();
        } else {
          alert(j?.data?.message || 'Nie udało się zapisać.');
          if (footer && prev != null) footer.innerHTML = prev;
        }
      } catch (e) {
        alert('Błąd połączenia podczas zapisu.');
        if (footer && prev != null) footer.innerHTML = prev;
      }
    }

    async function deleteReport(dateISO) {
      try {
        const fd = new FormData();
        fd.append('action', 'up_save_report');
        fd.append('mode', 'delete');
        fd.append('up_date', dateISO);
        fd.append('nonce', UPPANEL.report_nonce);
        const r = await fetch(UPPANEL.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd });
        const j = await r.json();
        if (j?.success) {
          closeAnyModal();
          // Przeładuj widok moich raportów
          window.location.hash = 'view=my-reports&_=' + Date.now();
        } else {
          alert(j?.data?.message || 'Nie udało się usunąć.');
        }
      } catch (e) {
        alert('Błąd połączenia podczas usuwania.');
      }
    }

    function resetReportForm(modal){
      modal.querySelectorAll('input[type="number"][name^="up_tasks"]').forEach(i => { i.value = 0; });
      modal.querySelectorAll('input[type="text"][name^="up_tasks"]').forEach(i => { i.value = ''; });
      modal.querySelectorAll('textarea[name^="up_tasks"]').forEach(t => { t.value = ''; });
      const g = modal.querySelector('textarea[name="up_note"]');
      if (g) g.value = '';
    }
    
    function fillReportForm(modal, rep){
      if (!rep || typeof rep !== 'object') return;
      if (rep.tasks && typeof rep.tasks === 'object'){
        // Iteruj po kategoriach
        Object.keys(rep.tasks).forEach(catKey => {
          const catTasks = rep.tasks[catKey];
          if (catTasks && typeof catTasks === 'object'){
            // Iteruj po zadaniach w kategorii
            Object.keys(catTasks).forEach(taskKey => {
              const taskData = catTasks[taskKey] || {};
              const qty  = Number(taskData.qty || 0);
              const time = String(taskData.time || '');
              const note = String(taskData.note || '');
              
              const qtyInp  = modal.querySelector(`input[name="up_tasks[${catKey}][${taskKey}][qty]"]`);
              const timeInp = modal.querySelector(`input[name="up_tasks[${catKey}][${taskKey}][time]"]`);
              const noteTa  = modal.querySelector(`textarea[name="up_tasks[${catKey}][${taskKey}][note]"]`);
              
              if (qtyInp) qtyInp.value = qty;
              if (timeInp) timeInp.value = time;
              if (noteTa) noteTa.value = note;
            });
          }
        });
      }
      const g = modal.querySelector('textarea[name="up_note"]');
      if (g) g.value = String(rep.note || '');
    }
    
    async function fetchFreshReport(dateISO){
      const fd = new FormData();
      fd.append('action', 'up_get_report');
      fd.append('date', dateISO);
      fd.append('nonce', UPPANEL.report_nonce);
    
      const r = await fetch(UPPANEL.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      const j = await r.json();
      if (!j?.success) throw new Error(j?.data?.message || 'Brak danych');
      return j.data.report || null;
    }

    async function openTodayModalCloned(dateISO) {
      console.log('Otwieranie modala dla:', dateISO);
      try {
        const tpl = await ensureTodayModalTemplate(dateISO);
        attachStylesOnce(tpl.styles);

        removeIfExists('#kptr-modal');
        removeIfExists('#kptr-confirm');

        const modal = tpl.modal.cloneNode(true);
        const confirm = tpl.confirm ? tpl.confirm.cloneNode(true) : null;
        document.body.appendChild(modal);
        if (confirm) document.body.appendChild(confirm);

        // Ustaw datę w ukrytym polu
        const dateInp = modal.querySelector('#kptr-date-input, input[name="up_date"]');
        if (dateInp) dateInp.value = dateISO;

        // Formatowanie daty do wyświetlenia
        const [y, m, d] = dateISO.split('-').map(Number);
        const dt = new Date(Date.UTC(y, m - 1, d, 12, 0, 0));
        const raw = new Intl.DateTimeFormat('pl-PL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(dt);
        const label = raw.charAt(0).toUpperCase() + raw.slice(1);
        
        // Ustaw tytuł modala
        modal.querySelector('#kptr-modal-title')?.replaceChildren(document.createTextNode('Raport — ' + label));
        if (confirm) {
          confirm.querySelector('.kptr-modal__title')?.replaceChildren(document.createTextNode('Usunąć raport dla ' + label + '?'));
          const confDate = confirm.querySelector('#kptr-confirm-date');
          if (confDate) confDate.textContent = label;
        }

        // Przyciski zapisywania (mobile i desktop)
        modal.querySelectorAll('button[name="kptr_action"][value="submit"]')
          .forEach(btn => btn?.addEventListener('click', (e) => { e.preventDefault(); saveReport('submit', modal, dateISO); }));

        // Przyciski zamykające
        modal.querySelectorAll('.kptr-modal__close').forEach(btn => btn.addEventListener('click', closeAnyModal));

        // Przyciski usuwania (mobile i desktop) - BEZ POTWIERDZENIA
        const delBtns = modal.querySelectorAll('.kptr-delete-mobile, .kptr-delete-desktop');
        if (delBtns.length > 0) {
          delBtns.forEach(delBtn => {
            delBtn.addEventListener('click', async () => await deleteReport(dateISO));
          });
        }

        // Pokaż modal - usuń hidden i aria-hidden + wymusz style
        modal.removeAttribute('hidden');
        modal.removeAttribute('aria-hidden');
        modal.setAttribute('aria-hidden', 'false');
        
        // Wymusz wizualność inline stylami + szerokość jak w calendar/today
        modal.style.display = 'grid';
        modal.style.position = 'fixed';
        modal.style.inset = '0';
        modal.style.zIndex = '9999';
        modal.style.placeItems = 'center';
        modal.style.background = 'rgba(0,0,0,0.5)';
        modal.style.padding = '16px';
        
        // Szerokość .kptr-modal__dialog jak w today.php: min(960px, 100%)
        const dialog = modal.querySelector('.kptr-modal__dialog');
        if (dialog) {
          dialog.style.width = 'min(960px, 100%)';
          dialog.style.maxHeight = '85vh';
        }
        
        console.log('Modal otwarty, aria-hidden:', modal.getAttribute('aria-hidden'));
        console.log('Modal w DOM:', document.body.contains(modal));
        console.log('Modal computed display:', window.getComputedStyle(modal).display);

      } catch (err) {
        console.error('Błąd podczas otwierania modala:', err);
        alert('Nie udało się otworzyć modala edycji.');
      }
    }

    // Kliknięcie na wiersz - otwórz modal (TUTAJ, bez przekierowania)
    if (tbody) {
      tbody.addEventListener('click', function (e) {
        const tr = e.target.closest('tr[data-date]');
        if (!tr) return;

        const date = tr.dataset.date;
        if (!date) return;

        e.preventDefault();
        e.stopPropagation();

        // Otwórz modal bez przekierowania
        openTodayModalCloned(date);
      });
    }

    // ESC zamyka modal
    document.addEventListener('keydown', (e) => {
      if (!containerEl.isConnected) return;
      if (e.key === 'Escape') closeAnyModal();
    });

    console.log('Moduł Moje raporty zainicjalizowany');
  };
})();
