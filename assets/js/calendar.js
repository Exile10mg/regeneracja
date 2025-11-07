// assets/js/calendar.js
(function () {
  window.upInit = window.upInit || {};

  /* ---------- utils ---------- */
  function iso(y, m, d) {
    const mm = (m < 10 ? '0' : '') + m;
    const dd = (d < 10 ? '0' : '') + d;
    return `${y}-${mm}-${dd}`;
  }
  function firstDowISO(y, m) {
    const d = new Date(Date.UTC(y, m - 1, 1));
    let w = d.getUTCDay();
    if (w === 0) w = 7;
    return w;
  }
  function daysInMonth(y, m) {
    return new Date(Date.UTC(y, m, 0)).getUTCDate();
  }
  function cmpISO(a, b){ return a === b ? 0 : (a < b ? -1 : 1); }
  function canEditDate(dateISO, todayISO){ return dateISO <= todayISO; }

  const MONTHS_PL = [
    'Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
    'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'
  ];

  window.upInit.calendar = function (containerEl) {
    if (!containerEl) return;

    const dataEl = containerEl.querySelector('#up-cal-data');
    let payload = { initYear: null, initMonth: null, todayISO: '', reports: {} };
    try { if (dataEl?.textContent) payload = JSON.parse(dataEl.textContent); }
    catch (e) { console.error('UP Calendar: Nieprawidłowe JSON', e); }

    const WRAP = containerEl.querySelector('.kptr-calendar');
    if (!WRAP) return;

    const titleEl = WRAP.querySelector('.kptr-cal__title');
    const daysWrap = WRAP.querySelector('.kptr-cal__days');

    const TODAY_ISO = payload.todayISO || '';
    const REPORTS   = payload.reports || {};
    let curY = Number(payload.initYear)  || Number(WRAP.dataset.initialYear)  || new Date().getUTCFullYear();
    let curM = Number(payload.initMonth) || Number(WRAP.dataset.initialMonth) || (new Date().getUTCMonth() + 1);

    /* ---------- render jednego dnia ---------- */
    function makeDay(y, m, d, isOutOfMonth) {
      const el = document.createElement('div');
      el.className = 'kptr-day';

      const dateISO = iso(y, m, d);
      const rep = REPORTS[dateISO];

      const cmp = cmpISO(dateISO, TODAY_ISO);
      const isPast   = (cmp < 0);
      const isToday  = (cmp === 0);
      const isFuture = (cmp > 0);

      if (isOutOfMonth) el.classList.add('is-out');
      if (isToday) el.classList.add('is-today');
      if (isPast)  el.classList.add('is-past');
      if (isFuture){ el.classList.add('is-future','is-out'); }

      let statusHtml = '';
      if (rep && rep.status === 'submitted') {
        el.classList.add('has-report','is-submitted');
        statusHtml = '<span class="kptr-badge is-submitted">Złożony</span>';
      } else {
        statusHtml = '<span class="kptr-badge is-empty">Brak</span>';
      }

      const btnHtml = (isFuture)
        ? ''
        : `<button class="kptr-cal__cellbtn" data-date="${dateISO}" aria-label="Szczegóły dnia ${dateISO}"></button>`;

      el.innerHTML = `
        <div class="kptr-day__num">${d}</div>
        <div class="kptr-day__tags">${statusHtml}</div>
        ${btnHtml}
      `;

      if (isFuture) {
        el.setAttribute('aria-disabled', 'true');
        el.style.cursor = 'default';
      }

      return el;
    }

    /* ---------- render całego miesiąca ---------- */
    function render() {
      if (titleEl) titleEl.textContent = `${MONTHS_PL[curM - 1]} ${curY}`;
      if (!daysWrap) return;

      daysWrap.innerHTML = '';
      const pad  = firstDowISO(curY, curM) - 1;
      const days = daysInMonth(curY, curM);

      const prevM = curM === 1 ? 12 : curM - 1;
      const prevY = curM === 1 ? curY - 1 : curY;
      const prevDays = daysInMonth(prevY, prevM);

      for (let i = pad; i > 0; i--) {
        daysWrap.appendChild(makeDay(prevY, prevM, prevDays - i + 1, true));
      }
      for (let d = 1; d <= days; d++) {
        daysWrap.appendChild(makeDay(curY, curM, d, false));
      }

      const total = pad + days;
      const tail = (7 - (total % 7)) % 7;
      const nextM = curM === 12 ? 1 : curM + 1;
      const nextY = curM === 12 ? curY + 1 : curY;
      for (let d = 1; d <= tail; d++) {
        daysWrap.appendChild(makeDay(nextY, nextM, d, true));
      }
    }

    /* ---------- wstrzyknięcie modala „today" (1:1) dla dni ≤ dziś ---------- */
    const TODAY_MODAL_CACHE = {};
    async function ensureTodayModalTemplate(dateISO){
      if (TODAY_MODAL_CACHE[dateISO]) return TODAY_MODAL_CACHE[dateISO];
      const u = new URL(UPPANEL.ajax_url);
      u.searchParams.set('action','up_load_view');
      u.searchParams.set('view','today');
      u.searchParams.set('date', dateISO);
      u.searchParams.set('nonce',UPPANEL.nonce);
      const res = await fetch(u.toString(),{credentials:'same-origin'});
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
    function attachStylesOnce(cssText){
      if (!cssText) return;
      const id = 'up-today-modal-style';
      if (document.getElementById(id)) return;
      const s = document.createElement('style');
      s.id = id;
      s.textContent = cssText;
      document.head.appendChild(s);
    }
    function removeIfExists(sel){ document.querySelectorAll(sel).forEach(n => n.remove()); }
    function closeAnyModal(){
      document.querySelectorAll('#kptr-modal, #kptr-confirm').forEach(m=>{
        m.setAttribute('aria-hidden','true'); m.remove();
      });
    }
    async function saveReport(mode, modal, dateISO){
      const form = modal.querySelector('#kptr-form');
      if (!form) return;
      const fd = new FormData(form);
      fd.append('action','up_save_report');
      fd.append('mode', mode);
      fd.append('date', dateISO);
      fd.append('nonce', UPPANEL.report_nonce);
      const footer = modal.querySelector('.kptr-modal__footer');
      const prev = footer?.innerHTML;
      if (footer) footer.innerHTML = '<span class="kp-saving">Zapisywanie…</span>';
      try{
        const r = await fetch(UPPANEL.ajax_url, { method:'POST', credentials:'same-origin', body: fd });
        const j = await r.json();
        if (j?.success){
          if (!REPORTS[dateISO]) REPORTS[dateISO] = {};
          REPORTS[dateISO].status = 'submitted';
          REPORTS[dateISO].time   = new Date().toISOString().slice(0,19).replace('T',' ');
          render();
          closeAnyModal();
        } else {
          alert(j?.data?.message || 'Nie udało się zapisać.');
          if (footer && prev!=null) footer.innerHTML = prev;
        }
      }catch(e){
        alert('Błąd połączenia podczas zapisu.');
        if (footer && prev!=null) footer.innerHTML = prev;
      }
    }
    async function deleteReport(dateISO){
      try{
        const fd = new FormData();
        fd.append('action','up_save_report');
        fd.append('mode','delete');
        fd.append('up_date', dateISO);
        fd.append('nonce', UPPANEL.report_nonce);
        const r = await fetch(UPPANEL.ajax_url, { method:'POST', credentials:'same-origin', body: fd });
        const j = await r.json();
        if (j?.success){
          if (REPORTS[dateISO]) delete REPORTS[dateISO];
          render();
          closeAnyModal();
        } else {
          alert(j?.data?.message || 'Nie udało się usunąć.');
        }
      }catch(e){ alert('Błąd połączenia podczas usuwania.'); }
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

    async function openTodayModalCloned(dateISO){
      if (!canEditDate(dateISO, TODAY_ISO)) return;
    
      try{
        const tpl = await ensureTodayModalTemplate(dateISO);
        attachStylesOnce(tpl.styles);
    
        removeIfExists('#kptr-modal');
        removeIfExists('#kptr-confirm');
    
        const modal   = tpl.modal.cloneNode(true);
        const confirm = tpl.confirm ? tpl.confirm.cloneNode(true) : null;
        document.body.appendChild(modal);
        if (confirm) document.body.appendChild(confirm);
    
        const dateInp = modal.querySelector('#kptr-date-input, input[name="up_date"]');
        if (dateInp) dateInp.value = dateISO;
    
        const [y,m,d] = dateISO.split('-').map(Number);
        const dt = new Date(Date.UTC(y,m-1,d,12,0,0));
        const raw = new Intl.DateTimeFormat('pl-PL', {weekday:'long', day:'numeric', month:'long', year:'numeric'}).format(dt);
        const label = raw.charAt(0).toUpperCase() + raw.slice(1);
        modal.querySelector('#kptr-modal-title')?.replaceChildren(document.createTextNode('Raport — ' + label));
        confirm?.querySelector('.kptr-modal__title')?.replaceChildren(document.createTextNode('Usunąć raport dla ' + label + '?'));
        const confDate = confirm?.querySelector('#kptr-confirm-date'); if (confDate) confDate.textContent = label;
    
        // Obsługa przycisków zapisywania (mobile i desktop)
        modal.querySelectorAll('button[name="kptr_action"][value="submit"]')
          .forEach(btn => btn?.addEventListener('click', (e)=>{ e.preventDefault(); saveReport('submit', modal, dateISO); }));

        modal.querySelectorAll('.kptr-modal__close').forEach(btn=> btn.addEventListener('click', closeAnyModal));

        // Obsługa przycisków usuwania (mobile i desktop) - BEZ POTWIERDZENIA
        const delBtns = modal.querySelectorAll('.kptr-delete-mobile, .kptr-delete-desktop');
        if (delBtns.length > 0){
          delBtns.forEach(delBtn => {
            delBtn.addEventListener('click', async ()=> await deleteReport(dateISO));
          });
        }
    
        modal.hidden = false;
        modal.setAttribute('aria-hidden','false');
    
      }catch(err){
        console.error(err);
        alert('Nie udało się otworzyć modala edycji.');
      }
    }

    /* ---------- eventy ---------- */
    WRAP.querySelector('.kptr-cal__prev')?.addEventListener('click', () => {
      if (curM === 1) { curM = 12; curY -= 1; } else { curM -= 1; }
      render();
    });
    WRAP.querySelector('.kptr-cal__next')?.addEventListener('click', () => {
      if (curM === 12) { curM = 1; curY += 1; } else { curM += 1; }
      render();
    });

    WRAP.querySelector('.kptr-cal__days')?.addEventListener('click', (e) => {
      const btn = e.target.closest('.kptr-cal__cellbtn');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      const dateISO = btn.dataset.date;
      if (!canEditDate(dateISO, TODAY_ISO)) return;
      openTodayModalCloned(dateISO);
    });

    document.addEventListener('keydown', (e) => {
      if (!containerEl.isConnected) return;
      if (e.key === 'Escape') closeAnyModal();
    });

    /* ---------- start ---------- */
    render();
  };
})();
