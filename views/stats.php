<?php
if (!defined('ABSPATH')) exit;

/** @var WP_User $current_user */
$uid = $current_user->ID;

// Kategorie z kolorami
$up_CATEGORIES = [
  'pompy_cr' => [
    'label' => 'Pompy CR',
    'color' => '#ED1C24',
    'gradient' => 'linear-gradient(90deg, #ED1C24, #ff4757)'
  ],
  'pompy_vp' => [
    'label' => 'Pompy VP',
    'color' => '#2196F3',
    'gradient' => 'linear-gradient(90deg, #2196F3, #64b5f6)'
  ],
  'wtryski_cri' => [
    'label' => 'Wtryski/CRi',
    'color' => '#FF9800',
    'gradient' => 'linear-gradient(90deg, #FF9800, #ffb74d)'
  ],
  'turbo' => [
    'label' => 'Turbo',
    'color' => '#4CAF50',
    'gradient' => 'linear-gradient(90deg, #4CAF50, #81c784)'
  ],
];

// Pobierz wszystkie raporty
$all_reports = get_user_meta($uid, 'kp_reports', true);
if (!is_array($all_reports)) {
    $all_reports = [];
}

$todayISO = date('Y-m-d', current_time('timestamp'));

// Podstawowe statystyki (zawsze z całego czasu)
$total_reports = count($all_reports);
$submitted_reports = count(array_filter($all_reports, fn($r) => ($r['status'] ?? '') === 'submitted'));

// Oblicz sumy kategorii
$category_totals = [];
$total_quantity = 0;

foreach ($up_CATEGORIES as $cat_key => $cat_data) {
    $category_totals[$cat_key] = 0;
}

foreach ($all_reports as $date => $report) {
    if (!isset($report['tasks']) || !is_array($report['tasks'])) continue;

    foreach ($up_CATEGORIES as $cat_key => $cat_data) {
        if (isset($report['tasks'][$cat_key]) && is_array($report['tasks'][$cat_key])) {
            foreach ($report['tasks'][$cat_key] as $task_data) {
                $qty = (int)($task_data['qty'] ?? 0);
                $category_totals[$cat_key] += $qty;
                $total_quantity += $qty;
            }
        }
    }
}

// Znajdź najproduktywniejszą kategorię
$top_category = 'pompy_cr';
$max_value = 0;
foreach ($category_totals as $cat_key => $value) {
    if ($value > $max_value) {
        $max_value = $value;
        $top_category = $cat_key;
    }
}

// Oblicz zakres dat
$report_dates = array_keys($all_reports);
sort($report_dates);
$first_report = $report_dates[0] ?? $todayISO;

// Oblicz streaki (serie kolejnych dni z raportami)
$current_streak = 0;
$max_streak = 0;
$temp_streak = 0;

if ($total_reports > 0) {
    $start_date = new DateTime($first_report);
    $end_date = new DateTime($todayISO);
    $interval = new DateInterval('P1D');
    $period_iter = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

    foreach ($period_iter as $date) {
        $date_str = $date->format('Y-m-d');
        $has_report = isset($all_reports[$date_str]) &&
                     ($all_reports[$date_str]['status'] ?? '') === 'submitted';

        if ($has_report) {
            $temp_streak++;
            $max_streak = max($max_streak, $temp_streak);
            if ($date_str === $todayISO || $date_str === date('Y-m-d', strtotime('yesterday'))) {
                $current_streak = $temp_streak;
            }
        } else {
            $temp_streak = 0;
            if ($date_str === $todayISO) {
                $current_streak = 0;
            }
        }
    }
}
?>

<style>
/* ===== Statystyki - Style główne ===== */
.kptr-stats-container{
  display:flex;
  flex-direction:column;
  gap:20px;
  color:#333;
  position:relative;
  min-height:400px;
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
  content:"\f080";
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

/* Empty State */
.kptr-empty{
  text-align:center;
  padding:60px 20px;
  color:#999;
  background:#fff;
  border:1px solid #e0e0e0;
  border-radius:12px;
}

.kptr-empty::before{
  content:"\f080";
  font-family:"Font Awesome 6 Free";
  font-weight:900;
  font-size:48px;
  color:#ddd;
  display:block;
  margin-bottom:16px;
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
  transition:all 0.2s ease;
  text-decoration:none;
}

.kptr-btn:hover{
  transform:translateY(-2px);
  box-shadow:0 4px 12px rgba(0,0,0,.15);
}

.kptr-btn--primary{
  background:linear-gradient(135deg, #ED1C24 0%, #c8141b 100%);
  border-color:#ED1C24;
  color:#fff;
}

.kptr-btn--primary:hover{
  color:#fff;
  text-decoration:none;
}

/* Karty statystyk */
.kptr-stats-cards{
  display:grid;
  grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
  gap:16px;
}

.kptr-stat-card{
  background:#fff;
  border:1px solid #e0e0e0;
  border-radius:12px;
  padding:20px;
  display:flex;
  align-items:center;
  gap:16px;
  box-shadow:0 2px 12px rgba(0,0,0,.08);
  transition:transform 0.2s ease, box-shadow 0.2s ease, opacity 0.4s ease;
  opacity:0;
  animation:fadeInUp 0.5s ease forwards;
}

.kptr-stat-card:nth-child(1){ animation-delay:0.1s; }
.kptr-stat-card:nth-child(2){ animation-delay:0.2s; }
.kptr-stat-card:nth-child(3){ animation-delay:0.3s; }

.kptr-stat-card:hover{
  transform:translateY(-2px);
  box-shadow:0 6px 20px rgba(0,0,0,0.12);
}

@keyframes fadeInUp{
  from{
    opacity:0;
    transform:translateY(20px);
  }
  to{
    opacity:1;
    transform:translateY(0);
  }
}

.kptr-stat-card__icon{
  width:56px;
  height:56px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(237, 28, 36, 0.1);
  border-radius:12px;
  flex-shrink:0;
  color:#ED1C24;
  font-size:24px;
}

.kptr-stat-card__content h4{
  margin:0 0 4px 0;
  font-size:14px;
  font-weight:600;
  color:#333;
}

.kptr-stat-card__content p{
  margin:0;
  font-size:18px;
  font-weight:700;
  color:#ED1C24;
}

/* Podział na kategorie */
.kptr-category-section{
  background:#fff;
  border:1px solid #e0e0e0;
  border-radius:12px;
  padding:24px;
  box-shadow:0 2px 12px rgba(0,0,0,.08);
  opacity:0;
  animation:fadeInUp 0.5s ease forwards;
  animation-delay:0.35s;
}

.kptr-section-title{
  margin:0 0 20px 0;
  font-size:18px;
  font-weight:700;
  color:#333;
}

.kptr-category-bars{
  display:flex;
  flex-direction:column;
  gap:20px;
}

.kptr-category-bar{
  display:flex;
  flex-direction:column;
  gap:8px;
  opacity:0;
  animation:fadeInUp 0.5s ease forwards;
}

.kptr-category-bar:nth-child(1){ animation-delay:0.4s; }
.kptr-category-bar:nth-child(2){ animation-delay:0.5s; }
.kptr-category-bar:nth-child(3){ animation-delay:0.6s; }
.kptr-category-bar:nth-child(4){ animation-delay:0.7s; }

.kptr-category-bar__header{
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.kptr-category-bar__label{
  font-weight:600;
  color:#333;
  font-size:15px;
}

.kptr-category-bar__value{
  font-weight:700;
  font-size:15px;
}

.kptr-category-bar__track{
  height:10px;
  background:#f0f0f0;
  border-radius:5px;
  overflow:hidden;
  position:relative;
}

.kptr-category-bar__fill{
  height:100%;
  border-radius:5px;
  transition:width 0.8s ease;
  animation:fillBar 1.2s ease-out forwards;
  transform-origin:left;
}

.kptr-category-bar__percentage{
  font-size:13px;
  color:#666;
  text-align:right;
}

@keyframes fillBar{
  from{ transform:scaleX(0); }
  to{ transform:scaleX(1); }
}

/* Responsive Design */
@media (max-width: 768px){
  .kptr-stats-cards{
    grid-template-columns:1fr;
    gap:12px;
  }

  .kptr-stat-card{
    padding:16px;
  }

  .kptr-category-section{
    padding:16px;
  }
}

@media (max-width: 480px){
  .kptr-stat-card{
    flex-direction:column;
    text-align:center;
    gap:12px;
  }

  .kptr-stat-card__icon{
    margin:0 auto;
  }

  .kptr-category-bar__label{
    font-size:13px;
  }

  .kptr-category-bar__value{
    font-size:13px;
  }
}
</style>

<div class="kptr-stats-container">
  <div>
    <h2 class="kptr-title">Statystyki</h2>
    <div class="kptr-sub">Twoje statystyki pracy z całego okresu aktywności</div>
  </div>

  <?php if (count($all_reports) === 0): ?>
    <!-- Pusty stan -->
    <div class="kptr-empty">
        Brak raportów do wyświetlenia.
    </div>
  <?php else: ?>
    <!-- Karty statystyk -->
    <div class="kptr-stats-cards">
        <div class="kptr-stat-card">
            <div class="kptr-stat-card__icon">
              <i class="fas fa-trophy"></i>
            </div>
            <div class="kptr-stat-card__content">
                <h4>Najlepsza kategoria</h4>
                <p><?= esc_html($up_CATEGORIES[$top_category]['label']) ?> - <?= number_format($category_totals[$top_category], 0, ',', ' ') ?> szt.</p>
            </div>
        </div>

        <div class="kptr-stat-card">
            <div class="kptr-stat-card__icon">
              <i class="fas fa-fire"></i>
            </div>
            <div class="kptr-stat-card__content">
                <h4>Najdłuższa seria</h4>
                <p><?= $max_streak ?> <?= $max_streak === 1 ? 'dzień' : ($max_streak < 5 ? 'dni' : 'dni') ?></p>
            </div>
        </div>

        <div class="kptr-stat-card">
            <div class="kptr-stat-card__icon">
              <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="kptr-stat-card__content">
                <h4>Złożone raporty</h4>
                <p><?= $submitted_reports ?> / <?= $total_reports ?> raportów</p>
            </div>
        </div>
    </div>

    <!-- Podział na kategorie -->
    <div class="kptr-category-section">
        <h3 class="kptr-section-title">Podział na kategorie</h3>
        <div class="kptr-category-bars">
            <?php foreach ($up_CATEGORIES as $cat_key => $cat_data):
                $total = $category_totals[$cat_key];
                $percentage = $total_quantity > 0 ? ($total / $total_quantity) * 100 : 0;
            ?>
                <div class="kptr-category-bar" data-category="<?= esc_attr($cat_key) ?>">
                    <div class="kptr-category-bar__header">
                        <span class="kptr-category-bar__label"><?= esc_html($cat_data['label']) ?></span>
                        <span class="kptr-category-bar__value" style="color:<?= esc_attr($cat_data['color']) ?>">
                            <?= number_format($total, 0, ',', ' ') ?> szt.
                        </span>
                    </div>
                    <div class="kptr-category-bar__track">
                        <div class="kptr-category-bar__fill"
                             style="width: <?= $percentage ?>%; background: <?= esc_attr($cat_data['gradient']) ?>"></div>
                    </div>
                    <div class="kptr-category-bar__percentage"><?= round($percentage, 1) ?>%</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
  <?php endif; ?>
</div>
