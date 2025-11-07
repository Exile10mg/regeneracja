<?php
if ( ! defined('ABSPATH') ) exit;

/** @var WP_User $current_user – przekazany z panelu */
$uid = $current_user->ID;

/** DATA – użyj parametru 'date' jeśli istnieje, inaczej "dziś" */
$requestedDate = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
if ($requestedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
    $todayIso = $requestedDate;
    $ts = strtotime($todayIso);
} else {
    $ts = current_time('timestamp');
    $todayIso = date('Y-m-d', $ts);
}
$todayLbl = date_i18n('l, j F Y', $ts);

/** Kategorie główne i podkategorie */
$up_CATEGORIES = [
  'pompy_cr' => [
    'label' => 'Pompy CR',
    'tasks' => [
      'piaskowanie' => 'Piaskowanie CR',
      'rozbieranie' => 'Rozbieranie CR',
      'czyszczenie' => 'Czyszczenie CR',
      'skladanie'   => 'Składanie CR',
      'walki'       => 'Wałki CR',
      'inne'        => 'Inne CR',
    ]
  ],
  'pompy_vp' => [
    'label' => 'Pompy VP',
    'tasks' => [
      'piaskowanie_vp' => 'Piaskowanie VP',
      'skladanie_vp'   => 'Składanie VP',
      'kalibracja_vp'  => 'Kalibracja VP',
      'sterowniki_przygotowanie_vp' => 'Sterowniki przygotowanie VP',
      'sterowniki_naprawa_vp' => 'Sterowniki naprawa VP',
      'inne_vp' => 'Inne VP',
    ]
  ],
  'wtryski_cri' => [
    'label' => 'Wtryski/CRi',
    'tasks' => [
      'testowanie_cri' => 'Testowanie CRi',
      'regeneracja_cri' => 'Regeneracja CRi',
      'inne_cri' => 'Inne CRi',
    ]
  ],
  'turbo' => [
    'label' => 'Turbo',
    'tasks' => [
      'piaskowanie_turbo' => 'Piaskowanie turbo',
      'skladanie_turbo' => 'Składanie turbo',
      'inne_turbo' => 'Inne turbo',
    ]
  ],
];

/** Wczytaj raport dzisiejszy (SSR – tylko TODAY; backfill zrobi JS) */
$all        = get_user_meta($uid, 'kp_reports', true);
if (!is_array($all)) $all = [];
$todayRow   = isset($all[$todayIso]) ? (array)$all[$todayIso] : [];
$status     = isset($todayRow['status']) ? (string)$todayRow['status'] : '';
$isSub      = ($status === 'submitted');
$isDraft    = ($status === 'draft');
$timeStr    = !empty($todayRow['time']) ? date_i18n('H:i', strtotime($todayRow['time'])) : '';
$noteGlobal = isset($todayRow['note']) ? (string)$todayRow['note'] : '';
$tasks      = (isset($todayRow['tasks']) && is_array($todayRow['tasks'])) ? $todayRow['tasks'] : [];

/** Puste wartości dla formularza (SSR) - iterate over nested structure */
foreach ($up_CATEGORIES as $catKey => $catData) {
  if (!isset($tasks[$catKey])) $tasks[$catKey] = [];
  foreach ($catData['tasks'] as $taskKey => $taskLabel) {
    if (!isset($tasks[$catKey][$taskKey])) {
      $tasks[$catKey][$taskKey] = ['qty'=>0, 'time'=>'', 'note'=>''];
    }
  }
}

/** Badge */
function up_badge($status){
  if ($status === 'submitted') return '<span class="kptr-badge is-submitted">Złożony</span>';
  if ($status === 'draft')     return '<span class="kptr-badge is-draft">Szkic</span>';
  return '<span class="kptr-badge is-empty">Brak</span>';
}
?>

<style>
/* ====== Dzisiejszy raport – modern styling with FA6 icons & animations ====== */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

@keyframes shimmer {
  0% { background-position: -1000px 0; }
  100% { background-position: 1000px 0; }
}

.kptr-card{ 
  background:transparent; 
  display:flex; 
  flex-direction:column; 
  gap:20px; 
  color:#333; 
  padding:0;
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
  content:"\f073";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  color:#ED1C24;
  font-size:24px;
}

.kptr-sub{ 
  color:#666; 
  margin:6px 0 0; 
  font-size:14px;
  display:flex;
  align-items:center;
  gap:8px;
  flex-wrap:wrap;
}

.kptr-badge{ 
  font-size:11px; 
  line-height:1; 
  border-radius:999px; 
  padding:5px 10px;
  border:1px solid;
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-weight:600;
  transition: all 0.2s ease;
}

.kptr-badge.is-submitted{ 
  background: #e8f5e9;
  border-color: #4caf50; 
  color: #2e7d32;
}

.kptr-badge.is-draft{ 
  background: #fff3e0;
  border-color: #ff9800; 
  color: #e65100;
}

.kptr-badge.is-empty{ 
  background: #f5f5f5;
  border-color: #ddd;
  color: #999;
}

.kptr-head{ 
  display:flex; 
  align-items:flex-start; 
  justify-content:space-between; 
  gap:16px;
}

.kptr-head__titlewrap{ 
  display:grid; 
  gap:8px;
  flex:1;
}

.kptr-head__actions{ 
  display:flex; 
  gap:10px;
}

.kptr-alert{ 
  padding:14px 16px 14px 48px; 
  border-radius:10px; 
  border:1px solid;
  position:relative;
  font-size:14px;
  line-height:1.5;
}

.kptr-alert::before{
  content:"";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  position:absolute;
  left:16px;
  top:50%;
  transform:translateY(-50%);
  font-size:20px;
}

.kptr-alert--success{ 
  background: #e8f5e9;
  border-color: #4caf50; 
  color: #2e7d32;
}
.kptr-alert--success::before{ content:"\f00c"; color:#4caf50; }

.kptr-alert--info{ 
  background: #e3f2fd;
  border-color: #2196f3;
  color: #0d47a1;
}
.kptr-alert--info::before{ content:"\f05a"; color:#2196f3; }

.kptr-alert--warn{ 
  background: #fff3e0;
  border-color: #ff9800; 
  color: #e65100;
}
.kptr-alert--warn::before{ content:"\f071"; color:#ff9800; }

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

.kptr-btn::before{
  font-family:"Font Awesome 6 Free";
  font-weight:900;
}

.kptr-btn:hover{ 
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
}

.kptr-btn:active{ 
  transform: translateY(0);
  box-shadow: 0 2px 4px rgba(0,0,0,.1);
}

.kptr-btn--primary{ 
  background: linear-gradient(135deg, #ED1C24 0%, #c8141b 100%);
  border-color: #ED1C24;
  color:#fff;
  box-shadow: 0 2px 8px rgba(237,28,36,.3);
}

.kptr-btn--primary::before{ content:"\f044"; }

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

.kptr-btn--ghost::before{ content:"\f0c7"; }

.kptr-btn--ghost:hover{
  background:#ED1C24;
  color:#fff;
  border-color:#ED1C24;
}

.kptr-btn--danger{ 
  background:transparent;
  color: #d32f2f; 
  border-color: #d32f2f;
}

/* Usuń ikonę z przycisku delete w stopce modala */
.kptr-modal__footer .kptr-btn--danger::before{ content:""; }

.kptr-btn--danger:hover{ 
  background: #d32f2f;
  color:#fff;
  border-color: #d32f2f;
}

/* Summary box with icon */
.kptr-summary{ 
  padding:0;
  margin:0;
}

.kptr-summary__title{ 
  font-weight:700; 
  margin-bottom:16px;
  color:#333;
  display:flex;
  align-items:center;
  gap:8px;
  font-size:17px;
}

.kptr-summary__list{ 
  margin:0; 
  padding-left:24px;
  display:flex;
  flex-direction:column;
  gap:6px;
}

.kptr-summary__list li{
  color:#555;
  line-height:1.6;
}

.kptr-summary__list li strong{
  color:#333;
  font-weight:600;
}

/* Modal animations */
@keyframes modalFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes modalSlideUp {
  from { opacity: 0; transform: translateY(50px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

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

.kptr-modal__title::before{
  content:"\f15c";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  color:#ED1C24;
  font-size:22px;
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

/* Form styling with better UX */
.kptr-form-grid{ 
  display:flex; 
  flex-direction:column; 
  gap:24px;
}

.kptr-category-section{
  display:flex;
  flex-direction:column;
  gap:14px;
}

.kptr-category-header{
  font-size:18px;
  font-weight:700;
  margin:0;
  padding:12px 16px;
  border-left:4px solid;
  border-radius:8px;
  display:flex;
  align-items:center;
  gap:10px;
}

.kptr-category-header::before{
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  font-size:20px;
}

/* Pompy CR - Red */
.kptr-category-section[data-category="pompy_cr"] .kptr-category-header{ color:#ED1C24; background: linear-gradient(135deg, #fff5f5 0%, #fff 100%); border-left-color:#ED1C24; box-shadow: 0 2px 4px rgba(237,28,36,0.1); }
.kptr-category-section[data-category="pompy_cr"] .kptr-category-header::before{ content:"\f085"; color:#ED1C24; }

/* Pompy VP - Blue */
.kptr-category-section[data-category="pompy_vp"] .kptr-category-header{ color:#2196F3; background: linear-gradient(135deg, #e3f2fd 0%, #fff 100%); border-left-color:#2196F3; box-shadow: 0 2px 4px rgba(33,150,243,0.1); }
.kptr-category-section[data-category="pompy_vp"] .kptr-category-header::before{ content:"\f492"; color:#2196F3; }

/* Wtryski/CRi - Orange */
.kptr-category-section[data-category="wtryski_cri"] .kptr-category-header{ color:#FF9800; background: linear-gradient(135deg, #fff3e0 0%, #fff 100%); border-left-color:#FF9800; box-shadow: 0 2px 4px rgba(255,152,0,0.1); }
.kptr-category-section[data-category="wtryski_cri"] .kptr-category-header::before{ content:"\f1e6"; color:#FF9800; }

/* Turbo - Green */
.kptr-category-section[data-category="turbo"] .kptr-category-header{ color:#4CAF50; background: linear-gradient(135deg, #e8f5e9 0%, #fff 100%); border-left-color:#4CAF50; box-shadow: 0 2px 4px rgba(76,175,80,0.1); }
.kptr-category-section[data-category="turbo"] .kptr-category-header::before{ content:"\f3fd"; color:#4CAF50; }

/* Uwagi ogólne - Purple */
.kptr-category-section[data-category="general"] .kptr-category-header{ color:#9C27B0; background: linear-gradient(135deg, #f3e5f5 0%, #fff 100%); border-left-color:#9C27B0; box-shadow: 0 2px 4px rgba(156,39,176,0.1); }
.kptr-category-section[data-category="general"] .kptr-category-header::before{ content:"\f249"; color:#9C27B0; }

.kptr-form-row{ 
  display:grid; 
  grid-template-columns: 200px 1fr; 
  gap:18px; 
  padding:16px; 
  border:2px solid #f5f5f5;
  border-radius:12px; 
  background:#fafafa;
  transition: all 0.2s ease;
}

.kptr-form-row--general{ grid-template-columns: 1fr !important; }

.kptr-form-row:hover{
  border-color:#e0e0e0;
  box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

/* Focus colors per category */
.kptr-category-section[data-category="pompy_cr"] .kptr-form-row:has(input:focus),
.kptr-category-section[data-category="pompy_cr"] .kptr-form-row:has(textarea:focus){
  border-color:#ED1C24;
  background:#fff;
  box-shadow: 0 4px 12px rgba(237,28,36,.15);
}

.kptr-category-section[data-category="pompy_vp"] .kptr-form-row:has(input:focus),
.kptr-category-section[data-category="pompy_vp"] .kptr-form-row:has(textarea:focus){
  border-color:#2196F3;
  background:#fff;
  box-shadow: 0 4px 12px rgba(33,150,243,.15);
}

.kptr-category-section[data-category="wtryski_cri"] .kptr-form-row:has(input:focus),
.kptr-category-section[data-category="wtryski_cri"] .kptr-form-row:has(textarea:focus){
  border-color:#FF9800;
  background:#fff;
  box-shadow: 0 4px 12px rgba(255,152,0,.15);
}

.kptr-category-section[data-category="turbo"] .kptr-form-row:has(input:focus),
.kptr-category-section[data-category="turbo"] .kptr-form-row:has(textarea:focus){
  border-color:#4CAF50;
  background:#fff;
  box-shadow: 0 4px 12px rgba(76,175,80,.15);
}

.kptr-category-section[data-category="general"] .kptr-form-row:has(input:focus),
.kptr-category-section[data-category="general"] .kptr-form-row:has(textarea:focus){
  border-color:#9C27B0;
  background:#fff;
  box-shadow: 0 4px 12px rgba(156,39,176,.15);
}

.kptr-form-label{ 
  font-weight:600;
  color:#333;
}

.kptr-cat-t{
  font-size:15px;
  margin-bottom:4px;
  display:flex;
  align-items:center;
  gap:8px;
}

/* Icons colors per category */
.kptr-category-section[data-category="pompy_cr"] .kptr-cat-t::before{
  content:"\f0a4";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  color:#ED1C24;
  font-size:14px;
}

.kptr-category-section[data-category="pompy_vp"] .kptr-cat-t::before{
  content:"\f0a4";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  color:#2196F3;
  font-size:14px;
}

.kptr-category-section[data-category="wtryski_cri"] .kptr-cat-t::before{
  content:"\f0a4";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  color:#FF9800;
  font-size:14px;
}

.kptr-category-section[data-category="turbo"] .kptr-cat-t::before{
  content:"\f0a4";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  color:#4CAF50;
  font-size:14px;
}

.kptr-category-section[data-category="general"] .kptr-cat-t::before{
  content:"\f0a4";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  color:#9C27B0;
  font-size:14px;
}

.kptr-form-fields{ 
  display:grid; 
  grid-template-columns: 120px 140px 1fr;
  gap:12px;
  align-items:start;
}

.kptr-field__label{ 
  font-size:12px; 
  color:#666;
  font-weight:600;
  margin-bottom:6px;
  display:block;
}

.kptr-field--qty input,
.kptr-field--time input,
.kptr-field--note textarea,
.kptr-field--full textarea{
  width:100%; 
  padding:12px 14px; 
  border:2px solid #e0e0e0;
  border-radius:10px; 
  background:#fff;
  font-size:14px;
  color:#333;
  transition: all 0.2s ease;
  font-family:inherit;
}

.kptr-field--qty input:hover,
.kptr-field--time input:hover,
.kptr-field--note textarea:hover,
.kptr-field--full textarea:hover{
  border-color:#bdbdbd;
}

/* Input focus colors per category */
.kptr-category-section[data-category="pompy_cr"] .kptr-field--qty input:focus,
.kptr-category-section[data-category="pompy_cr"] .kptr-field--time input:focus,
.kptr-category-section[data-category="pompy_cr"] .kptr-field--note textarea:focus{
  outline:none;
  border-color:#ED1C24;
  box-shadow: 0 0 0 3px rgba(237,28,36,0.1);
  background:#fff;
}

.kptr-category-section[data-category="pompy_vp"] .kptr-field--qty input:focus,
.kptr-category-section[data-category="pompy_vp"] .kptr-field--time input:focus,
.kptr-category-section[data-category="pompy_vp"] .kptr-field--note textarea:focus{
  outline:none;
  border-color:#2196F3;
  box-shadow: 0 0 0 3px rgba(33,150,243,0.1);
  background:#fff;
}

.kptr-category-section[data-category="wtryski_cri"] .kptr-field--qty input:focus,
.kptr-category-section[data-category="wtryski_cri"] .kptr-field--time input:focus,
.kptr-category-section[data-category="wtryski_cri"] .kptr-field--note textarea:focus{
  outline:none;
  border-color:#FF9800;
  box-shadow: 0 0 0 3px rgba(255,152,0,0.1);
  background:#fff;
}

.kptr-category-section[data-category="turbo"] .kptr-field--qty input:focus,
.kptr-category-section[data-category="turbo"] .kptr-field--time input:focus,
.kptr-category-section[data-category="turbo"] .kptr-field--note textarea:focus{
  outline:none;
  border-color:#4CAF50;
  box-shadow: 0 0 0 3px rgba(76,175,80,0.1);
  background:#fff;
}

.kptr-category-section[data-category="general"] .kptr-field--full textarea:focus{
  outline:none;
  border-color:#9C27B0;
  box-shadow: 0 0 0 3px rgba(156,39,176,0.1);
  background:#fff;
}

.kptr-field--qty input{
  text-align:center;
  font-weight:600;
  font-size:16px;
}

.kptr-field--time input{
  text-align:center;
  font-weight:600;
  font-size:14px;
}

@media (max-width:920px){ 
  .kptr-form-row{ grid-template-columns: 1fr; }
  .kptr-form-fields{ 
    grid-template-columns: 1fr;
    gap:10px;
  }
}

@media (max-width: 680px){
  .kptr-head{ flex-direction:column; align-items:flex-start; gap:10px; }
  .kptr-head__actions{ width:100%; }
  .kptr-head__actions .kptr-btn{ width:100%; min-height:42px; }
  
  .kptr-form-row{ grid-template-columns: 1fr; gap:12px; padding:10px; }
  .kptr-form-fields{ grid-template-columns: 1fr; }
  .kptr-field--qty input,
  .kptr-field--time input,
  .kptr-field--note textarea,
  .kptr-field--full textarea{
    padding:12px; font-size:16px;
  }
  
  .kptr-modal{ padding:0; background:rgba(0,0,0,.5); }
  .kptr-modal__dialog{
    width:100%; height:100vh; max-height:100vh;
    border-radius:0; box-shadow:none;
  }
}

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

@media (max-width:680px){
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

  .kptr-modal__dialog{
    height:100vh !important;
    max-height:100vh !important;
    width:100% !important;
    border-radius:0 !important;
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

  :where(input, select, textarea, button, .kptr-btn){
    font-size:16px !important;
    line-height:1.35;
  }
}
</style>

<div class="kptr-card" id="kptr-today-card" data-today-iso="<?php echo esc_attr($todayIso); ?>">
  <div class="kptr-head">
    <div class="kptr-head__titlewrap">
      <h2 class="kptr-title" id="kptr-head-title">Dzisiejszy raport</h2>
      <div class="kptr-sub" id="kptr-head-sub">
        Dziś: <strong><?php echo esc_html($todayLbl); ?></strong>
        &nbsp;•&nbsp; Status: <?php echo up_badge($status); ?>
      </div>
    </div>

    <div class="kptr-head__actions">
      <button type="button" class="kptr-btn kptr-btn--primary" id="kptr-open-modal">
        <?php echo ($isSub || $isDraft) ? 'Edytuj' : 'Wypełnij'; ?>
      </button>
    </div>
  </div>

  <?php if ($isSub): ?>
    <div class="kptr-alert kptr-alert--success">Raport na dziś został złożony. Możesz go edytować — zapis nadpisze dane.</div>
  <?php elseif ($isDraft): ?>
    <div class="kptr-alert kptr-alert--info">Masz zapisany szkic raportu. Uzupełnij dane w modalu i kliknij „Złóż raport".</div>
  <?php else: ?>
    <div class="kptr-alert kptr-alert--warn">Nie złożono jeszcze raportu na dzisiejszy dzień.</div>
  <?php endif; ?>

  <?php
  // Check if there is any data to display
  $hasAnyData = false;
  foreach ($up_CATEGORIES as $catKey => $catData) {
    foreach ($catData['tasks'] as $taskKey => $taskLabel) {
      if (isset($tasks[$catKey][$taskKey]['qty']) && (int)$tasks[$catKey][$taskKey]['qty'] > 0) {
        $hasAnyData = true;
        break 2;
      }
    }
  }
  
  if ($noteGlobal !== '' || $hasAnyData): 
    $categoryIcons = [
      'pompy_cr' => '<i class="fas fa-cog"></i>',
      'pompy_vp' => '<i class="fas fa-pump-soap"></i>',
      'wtryski_cri' => '<i class="fas fa-syringe"></i>',
      'turbo' => '<i class="fas fa-fan"></i>'
    ];
    $categoryColors = [
      'pompy_cr' => ['bg' => '#fff5f5', 'border' => '#ED1C24', 'text' => '#ED1C24'],
      'pompy_vp' => ['bg' => '#e3f2fd', 'border' => '#2196F3', 'text' => '#2196F3'],
      'wtryski_cri' => ['bg' => '#fff3e0', 'border' => '#FF9800', 'text' => '#FF9800'],
      'turbo' => ['bg' => '#e8f5e9', 'border' => '#4CAF50', 'text' => '#4CAF50']
    ];
  ?>
    <div class="kptr-summary">
      <div class="kptr-summary__title"><i class="fas fa-chart-bar"></i> Podsumowanie dzisiejszego raportu</div>
      <div style="display:flex;flex-direction:column;gap:16px;margin-top:8px;">
        <?php foreach ($up_CATEGORIES as $catKey => $catData): 
          $catHasData = false;
          $catItemsHTML = '';
          $colors = $categoryColors[$catKey] ?? ['bg' => '#f5f5f5', 'border' => '#666', 'text' => '#666'];
          $icon = $categoryIcons[$catKey] ?? '<i class="fas fa-box"></i>';
          
          foreach ($catData['tasks'] as $taskKey => $taskLabel) {
            if (!isset($tasks[$catKey][$taskKey])) continue;
            $taskData = $tasks[$catKey][$taskKey];
            $qty = (int)($taskData['qty'] ?? 0);
            if ($qty <= 0) continue;
            
            $catHasData = true;
            $time = !empty($taskData['time']) ? '<span style="color:#777;font-size:14px;margin-left:10px;"><i class="far fa-clock"></i> ' . esc_html($taskData['time']) . '</span>' : '';
            $note = !empty($taskData['note']) ? '<div style="color:#666;font-size:13px;margin-top:6px;padding-left:8px;border-left:2px solid #e0e0e0;padding-left:12px;line-height:1.5;"><i class="far fa-comment-dots" style="color:#999;"></i> <em>' . esc_html($taskData['note']) . '</em></div>' : '';
            $catItemsHTML .= '<div style="padding:10px 0;border-bottom:1px solid rgba(0,0,0,0.05);"><div style="display:flex;align-items:center;gap:6px;"><strong style="color:#222;font-size:14px;">' . esc_html($taskLabel) . ':</strong> <span style="color:' . $colors['text'] . ';font-weight:700;font-size:15px;">' . esc_html($qty) . ' szt.</span>' . $time . '</div>' . $note . '</div>';
          }
          
          if ($catHasData): ?>
            <div style="background:<?php echo esc_attr($colors['bg']); ?>;border-left:4px solid <?php echo esc_attr($colors['border']); ?>;border-radius:10px;padding:14px 16px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding-bottom:10px;border-bottom:2px solid <?php echo esc_attr($colors['border']); ?>;">
                <span style="font-size:20px;color:<?php echo esc_attr($colors['text']); ?>;"><?php echo $icon; ?></span>
                <strong style="color:<?php echo esc_attr($colors['text']); ?>;font-size:16px;"><?php echo esc_html($catData['label']); ?></strong>
              </div>
              <?php echo $catItemsHTML; ?>
            </div>
          <?php endif;
        endforeach; ?>
        
        <?php if ($noteGlobal !== ''): ?>
          <div style="background:linear-gradient(135deg, #f3e5f5 0%, #fce4ec 100%);border-left:4px solid #9C27B0;border-radius:10px;padding:14px 16px;box-shadow:0 2px 8px rgba(156,39,176,0.15);">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
              <i class="fas fa-comment-alt" style="font-size:18px;color:#9C27B0;"></i>
              <strong style="color:#9C27B0;font-size:15px;">Uwagi ogólne do dnia pracy</strong>
            </div>
            <div style="color:#555;line-height:1.7;padding-left:28px;font-size:14px;"><?php echo nl2br(esc_html($noteGlobal)); ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- MODAL -->
<div class="kptr-modal" id="kptr-modal" aria-hidden="true" aria-labelledby="kptr-modal-title" role="dialog">
  <div class="kptr-modal__dialog" role="document">
    <div class="kptr-modal__header">
      <h3 class="kptr-modal__title" id="kptr-modal-title">Raport — <span id="kptr-modal-date">dzisiaj</span></h3>
      <button type="button" class="kptr-btn kptr-btn--danger kptr-modal__close" id="kptr-close" aria-label="Zamknij">✕</button>
    </div>

    <form class="kptr-modal__body" id="kptr-form" autocomplete="off">
      <input type="hidden" name="up_date" id="kptr-date-input" value="<?php echo esc_attr($todayIso); ?>">
      <?php wp_nonce_field('up_report_action', 'nonce'); ?>

      <div class="kptr-form-grid">
        <?php foreach ($up_CATEGORIES as $catKey => $catData): ?>
          <div class="kptr-category-section" data-category="<?php echo esc_attr($catKey); ?>">
            <h4 class="kptr-category-header"><?php echo esc_html($catData['label']); ?></h4>
            
            <?php foreach ($catData['tasks'] as $taskKey => $taskLabel): 
              $taskData = $tasks[$catKey][$taskKey] ?? ['qty'=>0, 'time'=>'', 'note'=>''];
              $qty  = (int)($taskData['qty'] ?? 0);
              $time = (string)($taskData['time'] ?? '');
              $note = (string)($taskData['note'] ?? '');
            ?>
            <div class="kptr-form-row">
              <div class="kptr-form-label">
                <div class="kptr-cat-t"><?php echo esc_html($taskLabel); ?></div>
              </div>
              <div class="kptr-form-fields">
                <label class="kptr-field kptr-field--qty">
                  <span class="kptr-field__label">Ilość (szt.)</span>
                  <input type="number" min="0" step="1" inputmode="numeric"
                         name="up_tasks[<?php echo esc_attr($catKey); ?>][<?php echo esc_attr($taskKey); ?>][qty]"
                         value="<?php echo esc_attr($qty); ?>">
                </label>
                <label class="kptr-field kptr-field--time">
                  <span class="kptr-field__label">Czas</span>
                  <input type="text" 
                         name="up_tasks[<?php echo esc_attr($catKey); ?>][<?php echo esc_attr($taskKey); ?>][time]"
                         placeholder="np. 2h" 
                         value="<?php echo esc_attr($time); ?>">
                </label>
                <label class="kptr-field kptr-field--note">
                  <span class="kptr-field__label">Uwagi</span>
                  <textarea rows="2" name="up_tasks[<?php echo esc_attr($catKey); ?>][<?php echo esc_attr($taskKey); ?>][note]"
                            placeholder="Uwagi dla: <?php echo esc_attr($taskLabel); ?>"><?php echo esc_textarea($note); ?></textarea>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

        <div class="kptr-category-section" data-category="general">
          <h4 class="kptr-category-header">Uwagi ogólne</h4>
          <div class="kptr-form-row kptr-form-row--general">
            <label class="kptr-field kptr-field--full">
              <span class="kptr-field__label">Uwagi ogólne do całego dnia pracy (opcjonalnie)</span>
              <textarea rows="4" name="up_note" placeholder="Np. problemy, propozycje, jakość, obserwacje z dnia pracy..."><?php echo esc_textarea($noteGlobal); ?></textarea>
            </label>
          </div>
        </div>

        <!-- Przyciski wewnątrz formularza (tylko mobile) -->
        <div class="kptr-form-actions">
          <button type="button" name="kptr_action" value="submit" class="kptr-btn kptr-btn--primary kptr-submit-mobile">Złóż raport</button>
          <button type="button" class="kptr-btn kptr-btn--danger kptr-delete-mobile">Usuń raport</button>
        </div>
      </div>
    </form>

    <!-- Stopka modala z przyciskami (tylko desktop) -->
    <div class="kptr-modal__footer">
      <button type="button" name="kptr_action" value="submit" class="kptr-btn kptr-btn--primary kptr-submit-desktop">Złóż raport</button>
      <button type="button" class="kptr-btn kptr-btn--danger kptr-delete-desktop">Usuń raport</button>
    </div>
  </div>
</div>

<!-- POTWIERDZENIE USUNIĘCIA -->
<div class="kptr-modal" id="kptr-confirm" aria-hidden="true" role="dialog">
  <div class="kptr-modal__dialog kptr-modal__dialog--confirm" role="document">
    <div class="kptr-modal__header">
      <h3 class="kptr-modal__title">Usunąć raport dla <span id="kptr-confirm-date">…</span>?</h3>
      <button type="button" class="kptr-btn kptr-btn--danger kptr-modal__close" id="kptr-confirm-close" aria-label="Zamknij">✕</button>
    </div>
    <div class="kptr-modal__body">
      <p>Tej operacji nie można cofnąć.</p>
    </div>
    <div class="kptr-modal__footer">
      <button type="button" class="kptr-btn kptr-btn--ghost"  id="kptr-confirm-cancel">Anuluj</button>
      <button type="button" class="kptr-btn kptr-btn--danger" id="kptr-confirm-ok">Usuń</button>
    </div>
  </div>
</div>

<script>
(function(){
  function getHashParam(name){
    const m = location.hash.match(new RegExp(name+'=([^&]+)'));
    return m ? decodeURIComponent(m[1]) : null;
  }
  function isISO(s){ return /^\d{4}-\d{2}-\d{2}$/.test(s); }
  function fmtDatePL(iso){
    const [y,m,d] = iso.split('-').map(Number);
    const dt = new Date(Date.UTC(y, m-1, d, 12, 0, 0));
    const s = new Intl.DateTimeFormat('pl-PL', {weekday:'long', day:'numeric', month:'long', year:'numeric'}).format(dt);
    return s.charAt(0).toUpperCase() + s.slice(1);
  }
  function todayISO(){ return document.getElementById('kptr-today-card')?.dataset.todayIso || ''; }
  function clampToPastOrToday(dateIso){
    const t = todayISO();
    if (!t || !isISO(dateIso)) return t;
    return (dateIso > t) ? t : dateIso;
  }

  const card    = document.getElementById('kptr-today-card');
  const headT   = document.getElementById('kptr-head-title');
  const headSub = document.getElementById('kptr-head-sub');
  const modalT  = document.getElementById('kptr-modal-title');
  const modalD  = document.getElementById('kptr-modal-date');
  const dateInp = document.getElementById('kptr-date-input');
  const delBtnsMobile  = document.querySelectorAll('.kptr-delete-mobile');
  const delBtnsDesktop = document.querySelectorAll('.kptr-delete-desktop');

  const openBtn = document.getElementById('kptr-open-modal');
  const modal   = document.getElementById('kptr-modal');
  const closeEls= document.querySelectorAll('.kptr-modal__close');

  function setModalVisible(el, show){
    el?.setAttribute('aria-hidden', show ? 'false' : 'true');
  }

  function updateForSelectedDate(){
    const t = todayISO();
    let sel = getHashParam('date');
    if (!sel || !isISO(sel)) sel = t;
    sel = clampToPastOrToday(sel);

    if (dateInp) dateInp.value = sel;

    const isToday = (sel === t);
    const label = isToday ? (<?php echo json_encode($todayLbl); ?>) : fmtDatePL(sel);

    if (headT) headT.textContent = isToday ? 'Dzisiejszy raport' : 'Raport — ' + label;
    if (headSub) {
      headSub.innerHTML = (isToday
        ? 'Dziś: <strong><?php echo esc_js($todayLbl); ?></strong>'
        : 'Wybrana data: <strong>'+label+'</strong>')
        + ' &nbsp;•&nbsp; <span class="kptr-badge is-empty">Status zostanie zaktualizowany po zapisie</span>';
    }
    if (modalT) modalT.textContent = 'Raport — ' + (isToday ? 'dzisiaj' : label);
    if (modalD) modalD.textContent = isToday ? 'dzisiaj' : label;
    if (confDate) confDate.textContent = isToday ? 'dzisiaj' : label;

    if (openBtn) openBtn.disabled = false;

    return sel;
  }

  let currentSelected = updateForSelectedDate();

  openBtn?.addEventListener('click', () => setModalVisible(modal, true));
  closeEls.forEach(el => el.addEventListener('click', () => setModalVisible(modal, false)));

  // Obsługa przycisków usuwania (mobile i desktop) - BEZ POTWIERDZENIA
  async function handleDelete(){
    if (!window.UPPANEL) return;
    try{
      const fd = new FormData();
      fd.append('action', 'up_save_report');
      fd.append('mode', 'delete');
      fd.append('nonce', UPPANEL.report_nonce);
      fd.append('up_date', dateInp?.value || todayISO());
      const res = await fetch(UPPANEL.ajax_url, { method:'POST', credentials:'same-origin', body: fd });
      const json = await res.json();
      if (json && json.success){
        setModalVisible(modal, false);
        const url = new URL(location.href); url.hash = 'view=today'; history.replaceState(null,'',url.toString());
        window.dispatchEvent(new HashChangeEvent('hashchange'));
      } else {
        alert((json?.data?.message)||'Nie udało się usunąć raportu.');
      }
    }catch(e){ alert('Błąd połączenia podczas usuwania.'); }
  }

  delBtnsMobile.forEach(btn => btn?.addEventListener('click', handleDelete));
  delBtnsDesktop.forEach(btn => btn?.addEventListener('click', handleDelete));

  window.addEventListener('hashchange', ()=>{
    currentSelected = updateForSelectedDate();
  });

  // Nasłuchuj na event zapisu raportu i przeładuj widok
  document.addEventListener('up:report:saved', function(e){
    // Przeładuj widok aby pokazać zaktualizowane dane
    window.location.hash = 'view=today&_=' + Date.now();
  });
})();
</script>
