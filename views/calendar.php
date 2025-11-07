<?php
if ( ! defined('ABSPATH') ) exit;
/** @var WP_User $current_user */
$uid = $current_user->ID;
$ts  = current_time('timestamp');
$todayY = (int) date('Y', $ts);
$todayM = (int) date('n', $ts);
$todayISO = date('Y-m-d', $ts);

// Wszystkie raporty usera
$all = get_user_meta($uid, 'kp_reports', true);
if (!is_array($all)) $all = [];

// Uproszczone dane do JS
$jsReports = [];
foreach ($all as $date => $row) {
    if (!is_array($row)) continue;
    $jsReports[$date] = [
        'status' => isset($row['status']) ? $row['status'] : '',
        'time'   => isset($row['time']) ? $row['time'] : '',
        'note'   => isset($row['note']) ? wp_strip_all_tags((string)$row['note']) : '',
        'tasks'  => isset($row['tasks']) && is_array($row['tasks']) ? $row['tasks'] : [],
    ];
}

$total_reports = count($all);
?>
<div class="kptr-calendar-container">
  <div class="kptr-calendar-header">
    <h2 class="kptr-title">Kalendarz</h2>
    <div class="kptr-sub">Przeglądaj raporty pracy w kalendarzu (<?php echo $total_reports; ?> <?php echo $total_reports === 1 ? 'raport' : 'raportów'; ?>)</div>
  </div>

  <section class="kptr-calendar" data-initial-year="<?= esc_attr($todayY) ?>" data-initial-month="<?= esc_attr($todayM) ?>">
    <header class="kptr-cal__head">
      <button type="button" class="kptr-cal__nav kptr-cal__prev" aria-label="Poprzedni miesiąc">&lsaquo;</button>
      <h2 class="kptr-cal__title" aria-live="polite"></h2>
      <button type="button" class="kptr-cal__nav kptr-cal__next" aria-label="Następny miesiąc">&rsaquo;</button>
    </header>

  <div class="kptr-cal__grid" role="grid">
    <div class="kptr-cal__dow" role="row">
      <span class="kptr-cal__dowcell" role="columnheader" aria-label="Poniedziałek">Pn</span>
      <span class="kptr-cal__dowcell" role="columnheader" aria-label="Wtorek">Wt</span>
      <span class="kptr-cal__dowcell" role="columnheader" aria-label="Środa">Śr</span>
      <span class="kptr-cal__dowcell" role="columnheader" aria-label="Czwartek">Cz</span>
      <span class="kptr-cal__dowcell" role="columnheader" aria-label="Piątek">Pt</span>
      <span class="kptr-cal__dowcell" role="columnheader" aria-label="Sobota">So</span>
      <span class="kptr-cal__dowcell" role="columnheader" aria-label="Niedziela">Nd</span>
    </div>
    <div class="kptr-cal__days" role="rowgroup"></div>
  </div>

  <footer class="kptr-cal__legend">
    <span class="kptr-badge is-submitted">Złożony</span>
    <span class="kptr-badge is-empty">Brak wpisu</span>
  </footer>
  </section>
</div>

<!-- Modal -->
<div class="kptr-modal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="kptr-modal-title">
  <div class="kptr-modal__box">
    <div class="kptr-modal__header">
      <h3 id="kptr-modal-title">Szczegóły dnia</h3>
      <button type="button" class="kptr-modal__close" aria-label="Zamknij">&times;</button>
    </div>
    <div class="kptr-modal__body"></div>
    <div class="kptr-modal__footer">
      <button type="button" class="kptr-btn kptr-btn--ghost kptr-modal__close">Zamknij</button>
      <button type="button" class="kptr-btn kptr-btn--primary kptr-goto-today" hidden>Przejdź do „Dzisiejszy raport"</button>
    </div>
  </div>
</div>

<style>
/* Kontener główny kalendarza */
.kptr-calendar-container{
  display:flex;
  flex-direction:column;
  gap:20px;
  color:#333;
}

.kptr-calendar-header{
  margin-bottom:10px;
}

.kptr-title{
  margin:0;
  font-weight:700;
  font-size:clamp(22px,2.5vw,26px);
  color:#222;
  display:flex;
  align-items:center;
  gap:10px;
}

.kptr-title::before{
  content:"\f133";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  color:#ED1C24;
  font-size:24px;
}

.kptr-sub{
  color:#666;
  margin:6px 0 0;
  font-size:14px;
}

/* Kontener kalendarza */
.kptr-calendar {
  display: grid;
  gap: 16px;
  background: #fff;
  padding: 20px;
  border-radius: 12px;
}

/* Nagłówek z nawigacją */
.kptr-cal__head { 
  display: flex; 
  align-items: center; 
  justify-content: space-between; 
  gap: 12px;
  margin-bottom: 8px;
}

.kptr-cal__title { 
  margin: 0; 
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
}

.kptr-cal__nav { 
  border: 1px solid #ddd; 
  background: #fff; 
  border-radius: 8px; 
  padding: 8px 14px; 
  cursor: pointer;
  font-size: 18px;
  font-weight: 600;
  transition: all 0.2s ease;
}

.kptr-cal__nav:hover {
  background: #f7f7f7;
  border-color: #bbb;
}

/* Siatka kalendarza */
.kptr-cal__grid { 
  display: grid; 
  gap: 8px; 
}

/* Nagłówki dni tygodnia */
.kptr-cal__dow { 
  display: grid; 
  grid-template-columns: repeat(7, 1fr); 
  font-weight: 600; 
  color: #4a5568;
  background: #f7fafc;
  border-radius: 8px;
  padding: 8px 0;
  margin-bottom: 4px;
}

.kptr-cal__dowcell { 
  text-align: center; 
  padding: 8px 0;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Siatka dni */
.kptr-cal__days { 
  display: grid; 
  grid-template-columns: repeat(7, 1fr); 
  gap: 8px; 
}

/* Pojedyncza komórka dnia */
.kptr-day { 
  border: 2px solid #e2e8f0; 
  border-radius: 12px; 
  background: #fff; 
  min-height: 90px; 
  padding: 10px; 
  position: relative; 
  display: flex; 
  flex-direction: column;
  gap: 8px;
  transition: all 0.2s ease;
}

.kptr-day:hover:not(.is-future) {
  border-color: #cbd5e0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  transform: translateY(-2px);
}

.kptr-day.is-out { 
  opacity: 0.4;
  background: #fafafa;
}

/* Numer dnia */
.kptr-day__num { 
  font-size: 16px; 
  line-height: 1; 
  color: #2d3748;
  font-weight: 600;
}

/* Tagi/badge */
.kptr-day__tags { 
  margin-top: auto; 
  display: flex; 
  gap: 6px; 
  flex-wrap: wrap; 
}

.kptr-badge { 
  font-size: 11px; 
  border-radius: 6px; 
  padding: 4px 10px; 
  border: 1px solid;
  font-weight: 600;
  white-space: nowrap;
  letter-spacing: 0.3px;
}

/* Usunięto ikony Font Awesome ze statusów */

/* Status badges */
.kptr-badge.is-submitted { 
  background: #e6f7ed; 
  border-color: #38a169; 
  color: #22543d;
}

.kptr-badge.is-empty { 
  background: #f7fafc; 
  border-color: #cbd5e0;
  color: #718096;
}

/* Dzień dzisiejszy */
.kptr-day.is-today { 
  border-color: #4299e1 !important;
  box-shadow: 0 0 0 3px rgba(66, 153,225, 0.1);
}

/* Dzień ze złożonym raportem */
.kptr-day.has-report.is-submitted { 
  border-color: #48bb78;
  background: linear-gradient(to bottom, #f0fff4, #fff);
}

/* Przycisk w komórce */
.kptr-cal__cellbtn { 
  position: absolute; 
  inset: 0; 
  border: 0; 
  background: transparent; 
  cursor: pointer;
  border-radius: 12px;
}
/* Modal - identyczny jak w today.php */
@keyframes modalFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes modalSlideUp {
  from { opacity: 0; transform: translateY(50px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.kptr-modal[hidden] { display:none !important; }
.kptr-modal[aria-hidden="true"]{ display:none !important; }

.kptr-modal{
  position:fixed;
  inset:0;
  z-index:1000;
  display:grid;
  place-items:center;
  padding:16px;
  background:rgba(0,0,0,.5);
  backdrop-filter:blur(4px);
  animation: modalFadeIn 0.3s ease-out;
}

.kptr-modal__box,
.kptr-modal__dialog{
  width:min(960px,100%);
  max-height:85vh;
  background:#fff;
  color:#333;
  border:1px solid #e0e0e0;
  border-radius:16px;
  box-shadow:0 24px 64px rgba(0,0,0,.3);
  display:flex;
  flex-direction:column;
  overflow:hidden;
  animation: modalSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.kptr-modal__header {
  padding:16px 20px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  border-bottom:2px solid #f5f5f5;
  background: linear-gradient(135deg, #fafafa 0%, #fff 100%);
}

.kptr-modal__title{
  font-size:20px;
  font-weight:700;
  color:#222;
  margin:0;
  display:flex;
  align-items:center;
  gap:10px;
}

.kptr-modal__footer{
  padding:16px 20px;
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:12px;
  border-top:2px solid #f5f5f5;
  background:#fafafa;
}

.kptr-modal__body{
  padding:20px;
  overflow:auto;
  flex:1;
}

.kptr-modal__close{
  -webkit-appearance:none;
  appearance:none;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:40px;
  height:40px;
  background:#fff;
  color:#d32f2f;
  border:2px solid #ffcdd2;
  border-radius:10px;
  font-size:20px;
  line-height:1;
  cursor:pointer;
  transition:all .2s ease;
  font-weight:900;
}

.kptr-modal__close:hover{
  background:#d32f2f;
  color:#fff;
  border-color:#d32f2f;
  transform:rotate(90deg) scale(1.1);
}

.kptr-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  padding:10px 18px;
  border-radius:8px;
  border:2px solid;
  background:#fff;
  color:#333;
  cursor:pointer;
  font-weight:600;
  font-size:14px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position:relative;
  overflow:hidden;
}

.kptr-btn:hover{
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
}

.kptr-btn--primary{
  background: linear-gradient(135deg, #ED1C24 0%, #c8141b 100%);
  border-color: #ED1C24;
  color:#fff;
  box-shadow: 0 2px 8px rgba(237,28,36,.3);
}

.kptr-btn--primary:hover{
  background: linear-gradient(135deg, #ff2830 0%, #ED1C24 100%);
  border-color: #ff2830;
  box-shadow: 0 6px 20px rgba(237,28,36,.4);
}

.kptr-btn--ghost{
  background:transparent;
  color:#ED1C24;
  border-color:#ED1C24;
}

.kptr-btn--ghost:hover{
  background:#ED1C24;
  color:#fff;
  border-color:#ED1C24;
}
.kptr-cal__cellbtn { position:absolute; inset:0; border:0; background:transparent; cursor:pointer; }
.kptr-day__note { margin-top:6px; font-size:12px; color:#666; line-height:1.4; max-height:38px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
.kptr-table { width:100%; border-collapse:collapse; }
.kptr-table th, .kptr-table td { border:1px solid #eee; padding:6px 8px; text-align:left; font-size:13px; }
.kptr-table th { background:#fafafa; }
.kptr-calendar{--kp-accent: #7c3aed;--kp-hover-bg: color-mix(in srgb, var(--kp-accent) 8%, transparent);--kp-hover-border: color-mix(in srgb, var(--kp-accent) 35%, #e6e6e6);--kp-ring: color-mix(in srgb, var(--kp-accent) 65%, #ffffff);}
.kptr-calendar .kptr-day{transition: background-color .15s ease, border-color .15s ease, transform .05s ease;}
.kptr-calendar .kptr-day:hover{background: var(--kp-hover-bg) !important;border-color: var(--kp-hover-border) !important;box-shadow: inset 0 0 0 1px var(--kp-hover-border);}
.kptr-calendar .kptr-day.is-out:hover{background: color-mix(in srgb, var(--kp-accent) 4%, transparent) !important;border-color: #e6e6e6 !important;box-shadow: none;}
.kptr-calendar .kptr-cal__cellbtn{background: transparent !important;box-shadow: none !important;border: 0;padding: 0;appearance: none;-webkit-appearance: none;}
.kptr-calendar .kptr-cal__cellbtn:hover,.kptr-calendar .kptr-cal__cellbtn:active{background: transparent !important;box-shadow: none !important;}
.kptr-calendar .kptr-cal__cellbtn:focus{outline: none;}
.kptr-calendar .kptr-cal__cellbtn:focus-visible{outline: 2px solid var(--kp-ring);outline-offset: 2px;border-radius: 12px;}
.kptr-calendar .kptr-day.is-today{box-shadow: inset 0 0 0 2px var(--kp-ring);}
@media (prefers-color-scheme: dark){.kptr-calendar{--kp-hover-bg: color-mix(in srgb, var(--kp-accent) 16%, transparent);--kp-hover-border: color-mix(in srgb, var(--kp-accent) 45%, rgba(255,255,255,.18));--kp-ring: color-mix(in srgb, var(--kp-accent) 75%, #cbd5e1);}}
.kptr-btn--danger{
  background:transparent;
  color: #d32f2f;
  border-color: #d32f2f;
}
.kptr-btn--danger:hover{
  background: #d32f2f;
  color:#fff;
  border-color: #d32f2f;
}

.kptr-cal__nav{-webkit-appearance:none; appearance:none;display:inline-flex; align-items:center; justify-content:center;min-width:36px; height:36px; padding:0 10px;border:1px solid #ED1C24;border-radius:10px;background:#ED1C24;color:#fff;cursor:pointer;transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .06s ease;}
.kptr-cal__nav:hover{background:#c8141b;border-color:#c8141b;}
.kptr-cal__nav:active{background:#aa1117;border-color:#aa1117;transform: translateY(1px);}
.kptr-cal__nav:focus-visible{outline:2px solid rgba(237,28,36,.4);outline-offset:2px;}
.kptr-cal__nav[disabled],.kptr-cal__nav:disabled{opacity:.5;cursor:not-allowed;transform:none;}
.kptr-calendar .kptr-day.is-future .kptr-cal__cellbtn{pointer-events: none;cursor: default;}
.kptr-calendar .kptr-day.is-future{opacity: .55;}
.kptr-calendar .kptr-day.is-future:hover{background: #fff !important;border-color: #e6e6e6 !important;box-shadow: none !important;}
.kptr-calendar *{ box-sizing:border-box; }
.kptr-badge{ font-size:11px; padding:3px 7px; }
.kptr-day__num{ font-size:14px; }
@media (max-width:640px){
  .kptr-calendar{ padding:12px; }
  .kptr-cal__title{ font-size:clamp(18px,4.2vw,22px); }
  .kptr-cal__head{ gap:6px; }
  .kptr-cal__nav{ min-width:34px; height:34px; padding:0 8px; border-radius:10px; }
  .kptr-cal__dow{ gap:0; }
  .kptr-cal__dowcell{ padding:4px 0; font-size:12px; }
  .kptr-cal__days{ gap:5px; }
  .kptr-day{min-height:72px;padding:6px;border-radius:10px;}
  .kptr-day__num{ font-size:13px; }
  .kptr-badge{ font-size:10px; padding:2px 6px; }
}
@media (max-width:480px){
  .kptr-calendar{ padding:10px; }
  .kptr-cal__title{ font-size:clamp(16px,5vw,20px); }
  .kptr-cal__nav{ min-width:30px; height:30px; padding:0 8px; }
  .kptr-cal__days{ gap:4px; }
  .kptr-day{min-height:64px;padding:5px;border-radius:9px;}
  .kptr-day__num{ font-size:12px; }
  .kptr-badge{ font-size:9.5px; padding:2px 5px; }
}
@media (max-width:360px){
  .kptr-calendar{ padding:8px; }
  .kptr-cal__days{ gap:3px; }
  .kptr-day{min-height:58px;padding:4px;border-radius:8px;}
  .kptr-badge{ font-size:9px; padding:1px 5px; }
  .kptr-day.is-today{ box-shadow: inset 0 0 0 2px rgba(125,92,255,.6); }
}
@media (min-width:1024px){.kptr-cal__title{ font-size:22px !important; }.kptr-cal__head{ gap:6px !important; }.kptr-cal__nav{ min-width:32px !important; height:32px !important; padding:0 8px !important; border-radius:8px !important; }.kptr-cal__dow{ gap:0 !important; }.kptr-cal__dowcell{ padding:4px 0 !important; font-size:13px !important; }.kptr-cal__days{ gap:6px !important; grid-template-columns:repeat(7,minmax(0,1fr)) !important; }.kptr-day{min-height:78px !important;padding:6px !important;border-radius:10px !important;}.kptr-day__num{ font-size:13px !important; }.kptr-day .kptr-badge{ font-size:10.5px !important; padding:2px 6px !important; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }.kptr-day{ border:1px solid #e6e6e6 !important; box-shadow:none !important; }.kptr-day.has-report.is-submitted{ box-shadow:inset 0 0 0 2px #28a745 !important; border-color:#e6e6e6 !important; }}
@media (min-width:1024px){.kp-content,.kptr-card,.kptr-calendar,.kptr-cal__grid{scrollbar-width: none;-ms-overflow-style: none;}.kp-content::-webkit-scrollbar,.kptr-card::-webkit-scrollbar,.kptr-calendar::-webkit-scrollbar,.kptr-cal__grid::-webkit-scrollbar{display: none;}}
.kptr-cal__days{ grid-template-columns:repeat(7,minmax(0,1fr)) !important; }
.kptr-cal__dow { grid-template-columns:repeat(7,minmax(0,1fr)) !important; }
.kptr-day{ min-width:0 !important; }
.kptr-cal__days .kptr-day .kptr-badge{display:inline-block;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:top;}
.kptr-cal__days .kptr-day .kptr-day__tags{flex-wrap:nowrap;overflow:hidden;gap:6px;}

/* Przyciski w formularzu (tylko mobile) */
.kptr-form-actions{
  display:none; /* Ukryj na desktop */
  gap:12px;
  justify-content:flex-end;
  padding-top:20px;
  margin-top:20px;
  padding-bottom:80px;
  border-top:2px solid #f5f5f5;
}

/* Stopka modala (tylko desktop) */
.kptr-modal__footer{
  display:flex; /* Pokaż na desktop */
}

/* Responsive dla modala */
@media (max-width: 680px){
  /* Na mobile: pokaż przyciski w formularzu, ukryj stopkę */
  .kptr-form-actions{
    display:flex;
  }

  .kptr-modal__footer{
    display:none !important;
  }
  .kptr-modal{
    padding:0 !important;
    background:rgba(0,0,0,.5);
  }

  .kptr-modal__dialog,
  .kptr-modal__box{
    width:100% !important;
    height:100vh !important;
    max-height:100vh !important;
    border-radius:0 !important;
    box-shadow:none !important;
  }

  .kptr-form-actions{
    flex-direction:column;
    gap:8px;
    padding-bottom:120px;
  }

  .kptr-form-actions .kptr-btn{
    width:100%;
    min-height:44px;
    justify-content:center;
  }
}
</style>

<!-- Dane dla JS -->
<script type="application/json" id="up-cal-data">
<?php
echo wp_json_encode([
  'initYear'  => $todayY,
  'initMonth' => $todayM,
  'todayISO'  => $todayISO,
  'reports'   => $jsReports,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
</script>
