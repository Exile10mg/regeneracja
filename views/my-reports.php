<?php
if ( ! defined('ABSPATH') ) exit;

/** @var WP_User $current_user */
$uid = $current_user->ID;

// Pobierz wszystkie raporty
$all_reports = get_user_meta($uid, 'kp_reports', true);
if (!is_array($all_reports)) {
    $all_reports = [];
}

// Sortuj po dacie malejąco (najnowsze pierwsze)
krsort($all_reports);

function render_badge($status) {
    if ($status === 'submitted') return '<span class="kptr-badge is-submitted">Złożony</span>';
    if ($status === 'draft') return '<span class="kptr-badge is-draft">Szkic</span>';
    return '<span class="kptr-badge is-empty">Brak</span>';
}

function get_category_sum($report, $cat_key) {
    $sum = 0;
    if (!isset($report['tasks'][$cat_key])) return 0;
    
    foreach ($report['tasks'][$cat_key] as $task_data) {
        $sum += isset($task_data['qty']) ? (int)$task_data['qty'] : 0;
    }
    return $sum;
}

$PAGE_SIZE = 10;
$total_reports = count($all_reports);
?>

<style>
.kptr-reports-container{
  display:flex;
  flex-direction:column;
  gap:20px;
  color:#333;
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
  content:"\f0cb";
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

.kptr-badge{
  font-size:11px;
  line-height:1;
  border-radius:999px;
  padding:5px 10px;
  border:1px solid;
  display:inline-flex;
  align-items:center;
  gap:4px;
  font-weight:600;
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

.kptr-table-wrap{
  width:100%;
  overflow:auto;
  border-radius:12px;
}

.kptr-table{
  width:100%;
  border-collapse:collapse;
  font-size:14px;
  background:#fff;
}

.kptr-table th,
.kptr-table td{
  border:1px solid #e0e0e0;
  padding:12px;
  text-align:center;
  vertical-align:middle;
}

.kptr-table th{
  position:sticky;
  top:0;
  z-index:2;
  background:#f8f9fa;
  font-weight:600;
  white-space:nowrap;
  box-shadow:0 1px 0 rgba(0,0,0,.06);
}

.kptr-table tbody tr{
  transition:background 0.15s;
  cursor:pointer;
}

.kptr-table tbody tr:hover{
  background:rgba(237,28,36,0.08);
}

.kptr-table tbody tr.is-hidden{
  display:none;
}

.kptr-table td:first-child{
  text-align:center;
  min-width:100px;
}

.kptr-table th.cat-cr{
  background:#fff5f5;
  color:#ED1C24;
  border-left:3px solid #ED1C24;
}

.kptr-table th.cat-vp{
  background:#e3f2fd;
  color:#2196F3;
  border-left:3px solid #2196F3;
}

.kptr-table th.cat-cri{
  background:#fff3e0;
  color:#FF9800;
  border-left:3px solid #FF9800;
}

.kptr-table th.cat-turbo{
  background:#e8f5e9;
  color:#4CAF50;
  border-left:3px solid #4CAF50;
}

.kptr-empty{
  text-align:center;
  padding:60px 20px;
  color:#999;
  background:#fff;
  border:1px solid #e0e0e0;
  border-radius:12px;
}

.kptr-empty::before{
  content:"\f15c";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  font-size:48px;
  color:#ddd;
  display:block;
  margin-bottom:16px;
}

.kptr-load-more{
  display:flex;
  justify-content:center;
  padding-top:20px;
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
  transition: all 0.2s ease;
}

.kptr-btn:hover{
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
}

.kptr-btn--primary{
  background: linear-gradient(135deg, #ED1C24 0%, #c8141b 100%);
  border-color: #ED1C24;
  color:#fff;
}

.kptr-btn--danger{
  background:#fff;
  border-color:#dc3545;
  color:#dc3545;
}

.kptr-btn--danger:hover{
  background:#dc3545;
  color:#fff;
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

/* Pagination */
.kptr-pagination{
  display:flex;
  align-items:center;
  justify-content:center;
  gap:16px;
  padding:20px 0;
  flex-wrap:wrap;
}

.kptr-pagination__info{
  font-size:14px;
  font-weight:600;
  color:#333;
  padding:0 8px;
  white-space:nowrap;
}

.kptr-pagination__btn{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:10px 18px;
  border-radius:8px;
  border:2px solid #ED1C24;
  background:#fff;
  color:#ED1C24;
  cursor:pointer;
  font-weight:600;
  font-size:14px;
  transition: all 0.2s ease;
}

.kptr-pagination__btn:hover:not(:disabled){
  background:#ED1C24;
  color:#fff;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(237,28,36,.3);
}

.kptr-pagination__btn:disabled{
  opacity:0.4;
  cursor:not-allowed;
  border-color:#ccc;
  color:#999;
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

@media (max-width: 768px){
  .kptr-table{
    font-size:12px;
  }
  .kptr-table th,
  .kptr-table td{
    padding:8px 6px;
  }

  /* Paginacja na mobile */
  .kptr-pagination{
    gap:10px;
  }

  .kptr-pagination__btn{
    padding:8px 12px;
    font-size:13px;
  }

  .kptr-pagination__info{
    font-size:13px;
  }
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

  .kptr-modal__dialog{
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

<div class="kptr-reports-container">
  <div>
    <h2 class="kptr-title">Moje raporty</h2>
    <div class="kptr-sub">Historia wypełnionych raportów pracy (<?php echo $total_reports; ?> <?php echo $total_reports === 1 ? 'raport' : 'raportów'; ?>)</div>
  </div>

  <?php if (empty($all_reports)): ?>
    <div class="kptr-empty">Brak raportów do wyświetlenia</div>
  <?php else: ?>
    <div class="kptr-table-wrap">
      <table class="kptr-table" id="reports-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Status</th>
            <th class="cat-cr">CR</th>
            <th class="cat-vp">VP</th>
            <th class="cat-cri">CRi</th>
            <th class="cat-turbo">Turbo</th>
          </tr>
        </thead>
        <tbody id="reports-tbody">
          <?php 
          $i = 0;
          foreach ($all_reports as $date => $report): 
            $i++;
            $hidden_class = ($i > $PAGE_SIZE) ? ' is-hidden' : '';
            $ts = strtotime($date);
            $date_disp = $ts ? date_i18n('d.m.Y', $ts) : $date;
            
            $sum_cr = get_category_sum($report, 'pompy_cr');
            $sum_vp = get_category_sum($report, 'pompy_vp');
            $sum_cri = get_category_sum($report, 'wtryski_cri');
            $sum_turbo = get_category_sum($report, 'turbo');
          ?>
            <tr<?php echo $hidden_class; ?> data-date="<?php echo esc_attr($date); ?>">
              <td><?php echo esc_html($date_disp); ?></td>
              <td><?php echo render_badge($report['status'] ?? ''); ?></td>
              <td><?php echo $sum_cr; ?></td>
              <td><?php echo $sum_vp; ?></td>
              <td><?php echo $sum_cri; ?></td>
              <td><?php echo $sum_turbo; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_reports > $PAGE_SIZE): 
      $total_pages = ceil($total_reports / $PAGE_SIZE);
    ?>
      <div class="kptr-pagination">
        <button type="button" class="kptr-pagination__btn" id="prev-page-btn" disabled>
          <i class="fas fa-chevron-left"></i> Poprzednia
        </button>

        <span class="kptr-pagination__info" id="pagination-info">Strona 1 z <?php echo $total_pages; ?></span>

        <button type="button" class="kptr-pagination__btn" id="next-page-btn">
          Następna <i class="fas fa-chevron-right"></i>
        </button>

        <input type="hidden" id="current-page" value="1">
        <input type="hidden" id="total-pages" value="<?php echo $total_pages; ?>">
        <input type="hidden" id="page-size" value="<?php echo $PAGE_SIZE; ?>">
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
