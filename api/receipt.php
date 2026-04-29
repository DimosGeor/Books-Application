<?php
// api/receipt.php
// GET ?student_id=X  — εκτυπώσιμη φόρμα παραλαβής ανά σπουδαστή
// GET ?all=1         — όλοι οι σπουδαστές σε μία σελίδα (bulk print)
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

requireAdmin();

$pdo = db();

function getStudentData(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.username, u.direction, u.submitted
         FROM users u WHERE u.id = ? AND u.role = 'student'"
    );
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if (!$student) return null;

    $books = $pdo->prepare(
        "SELECT b.title, b.author, b.cost
         FROM selections s JOIN books b ON b.id = s.book_id
         WHERE s.student_id = ?
         ORDER BY b.title"
    );
    $books->execute([$id]);
    $student['books'] = $books->fetchAll();
    $student['total'] = array_sum(array_column($student['books'], 'cost'));
    return $student;
}

$all       = isset($_GET['all']);
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($all) {
    $ids = $pdo->query("SELECT id FROM users WHERE role='student' AND submitted=1 ORDER BY direction, name")
               ->fetchAll(PDO::FETCH_COLUMN);
    $students = array_filter(array_map(fn($id) => getStudentData($pdo, $id), $ids));
} elseif ($studentId) {
    $s = getStudentData($pdo, $studentId);
    if (!$s) { http_response_code(404); echo 'Ο σπουδαστής δεν βρέθηκε.'; exit; }
    $students = [$s];
} else {
    http_response_code(400); echo 'Missing student_id'; exit;
}

$dirLabel = ['A' => 'ΕΙΣΑΓΓΕΛΕΩΝ', 'B' => 'ΔΙΟΙΚΗΤΙΚΗΣ ΔΙΚΑΙΟΣΥΝΗΣ', 'C' => 'ΠΟΛΙΤΙΚΗΣ-ΠΟΙΝΙΚΗΣ ΔΙΚΑΙΟΣΥΝΗΣ'];
$today    = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="el">
<head>

<meta charset="UTF-8"/>
<title>Φόρμα Παραλαβής Βιβλίων</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: #111;
    background: #e8e8e8;
  }

  /* κάθε φόρμα = μία σελίδα A4 */
  .receipt {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    margin: 20px auto;
    padding: 18mm 18mm 14mm 18mm;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 16px rgba(0,0,0,.15);
    position: relative;
  }

  /* ── HEADER ── */
  .header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2.5px solid #1a1a1a;
    padding-bottom: 12px;
    margin-bottom: 18px;
  }
  .logo-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .logo-icon {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, #1d4ed8 0%, #7c3aed 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: #fff;
    flex-shrink: 0;
  }
  .logo-text { line-height: 1.2; }
  .logo-text .org   { font-size: 18px; font-weight: 800; letter-spacing: -.3px; }
  .logo-text .sub   { font-size: 11px; color: #666; margin-top: 2px; }
  .header-right     { text-align: right; line-height: 1.7; font-size: 12px; color: #444; }
  .header-right strong { font-size: 13px; color: #111; }

  /* ── TITLE BAND ── */
  .title-band {
    background: #1a1a1a;
    color: #fff;
    text-align: center;
    padding: 9px 0;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: .5px;
    margin-bottom: 20px;
  }

  /* ── STUDENT INFO ── */
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 24px;
    margin-bottom: 22px;
  }
  .info-field {
    border-bottom: 1px solid #ccc;
    padding-bottom: 4px;
  }
  .info-field .lbl  { font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
  .info-field .val  { font-size: 14px; font-weight: 700; }

  /* ── BOOKS TABLE ── */
  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
    font-size: 13px;
  }
  thead tr {
    background: #1a1a1a;
    color: #fff;
  }
  thead th {
    padding: 8px 10px;
    text-align: left;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: .3px;
  }
  thead th:last-child { text-align: right; }
  tbody tr:nth-child(even) { background: #f7f7f7; }
  tbody tr:hover           { background: #eef3ff; }
  tbody td {
    padding: 8px 10px;
    border-bottom: 1px solid #e8e8e8;
    vertical-align: top;
  }
  tbody td:last-child { text-align: right; font-weight: 700; white-space: nowrap; }
  .td-num { width: 30px; color: #aaa; font-size: 11px; }
  .book-author { font-size: 11px; color: #888; margin-top: 1px; }

  /* ── TOTAL ROW ── */
  .total-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 24px;
  }
  .total-box {
    background: #1a1a1a;
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: .3px;
  }
  .total-box span { font-size: 12px; font-weight: 400; opacity: .75; margin-right: 8px; }

  /* ── NOTE ── */
  .note-box {
    border: 1px dashed #ccc;
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 12px;
    color: #666;
    margin-bottom: 24px;
    line-height: 1.6;
  }
  .note-box strong { color: #333; }

  /* ── SIGNATURES ── */
  .signatures {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid #ddd;
  }
  .sig-block .sig-label {
    font-size: 11px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 48px;  /* χώρος για υπογραφή */
  }
  .sig-block .sig-line {
    border-top: 1.5px solid #555;
    padding-top: 6px;
    font-size: 12px;
    color: #333;
  }

  /* ── FOOTER ── */
  .doc-footer {
    text-align: center;
    font-size: 10px;
    color: #bbb;
    margin-top: 14px;
  }

  /* ── PRINT ── */
  @media print {
    body { background: #fff; }
    .receipt {
      margin: 0;
      padding: 14mm 16mm 12mm 16mm;
      box-shadow: none;
      page-break-after: always;
      min-height: unset;
    }
    .receipt:last-child { page-break-after: avoid; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>

<!-- Print / Close buttons (hidden on print) -->
<div class="no-print" style="text-align:center;padding:16px 0 4px;display:flex;gap:10px;justify-content:center;">
  <button onclick="window.print()"
    style="padding:10px 28px;background:#1a1a1a;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
    🖨 Εκτύπωση / Αποθήκευση PDF
  </button>
  <button onclick="window.close()"
    style="padding:10px 20px;background:none;border:1px solid #ccc;border-radius:8px;font-size:14px;cursor:pointer;">
    Κλείσιμο
  </button>
</div>

<?php foreach ($students as $s): ?>
<div class="receipt">

  <!-- HEADER -->
  <div class="header">
    <div class="logo-wrap">
      <div > <img src="/images/LogoGR.png" alt="EsdiLogo" style="width:auto; height:60px;"> </div>
      <div class="logo-text">
       <!-- <div class="org">Βιβλιοθήκη ΕΣΔι</div> 
        <div class="sub">Σύστημα Διανομής Εκπαιδευτικού Υλικού</div> -->
      </div>
    </div>
    <div class="header-right">
      <div><strong>Φόρμα Παραλαβής</strong></div>
      <div>Ημερομηνία: <?= $today ?></div>
      <div>Κωδικός: #<?= str_pad($s['id'], 5, '0', STR_PAD_LEFT) ?></div>
    </div>
  </div>

  <!-- TITLE BAND -->
  <div class="title-band">ΒΕΒΑΙΩΣΗ ΠΑΡΑΛΑΒΗΣ ΕΚΠΑΙΔΕΥΤΙΚΟΥ ΥΛΙΚΟΥ</div>

  <!-- STUDENT INFO -->
  <div class="info-grid">
    <div class="info-field">
      <div class="lbl">Ονοματεπώνυμο Σπουδαστή</div>
      <div class="val"><?= htmlspecialchars($s['name']) ?></div>
    </div>
    <div class="info-field">
      <div class="lbl">Κατεύθυνση</div>
      <div class="val"><?= $dirLabel[$s['direction']] ?? $s['direction'] ?></div>
    </div>
    <div class="info-field">
      <div class="lbl">Email </div>
      <div class="val" style="font-family:monospace;font-size:13px;"><?= htmlspecialchars($s['username']) ?></div>
    </div>
    <div class="info-field">
      <div class="lbl">Αριθμός Βιβλίων</div>
      <div class="val"><?= count($s['books']) ?> τεμάχια</div>
    </div>
  </div>

  <!-- BOOKS TABLE -->
  <table>
    <thead>
      <tr>
        <th class="td-num">#</th>
        <th>Τίτλος Βιβλίου</th>
        <th>Συγγραφέας</th>
      <!--  <th style="text-align:right;">Κόστος</th> -->
      </tr>
    </thead>
    <tbody>
      <?php if (empty($s['books'])): ?>
        <tr><td colspan="4" style="text-align:center;color:#aaa;padding:20px;">Δεν υπάρχουν επιλεγμένα βιβλία</td></tr>
      <?php else: ?>
        <?php foreach ($s['books'] as $i => $b): ?>
        <tr>
          <td class="td-num"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:600;"><?= htmlspecialchars($b['title']) ?></div>
          </td>
          <td><div class="book-author"><?= htmlspecialchars($b['author']) ?></div></td>
        <!--  <td><?= number_format((float)$b['cost'], 2, ',', '.') ?>€</td> -->
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- TOTAL 
  <div class="total-row">
    <div class="total-box">
      <span>Συνολικό Κόστος</span>
      <?= number_format((float)$s['total'], 2, ',', '.') ?>€
    </div>
  </div> -->

  <!-- NOTE -->
  <div class="note-box">
    <strong>Σημείωση:</strong> Με την υπογραφή του παρόντος, ο/η σπουδαστής/τρια βεβαιώνει ότι παρέλαβε
    τα ανωτέρω συγγράμματα από την Ε.Σ.Δι.
  </div>

  <!-- SIGNATURES -->
  <div class="signatures">
    <div class="sig-block">
      <div class="sig-label">Υπογραφή Σπουδαστή / Τριας</div>
      <div class="sig-line"><?= htmlspecialchars($s['name']) ?></div>
    </div>
    <div class="sig-block">
      <div class="sig-label">Υπογραφή &amp; Σφραγίδα Υπαλλήλου</div>
      <div class="sig-line">Ονοματεπώνυμο: ___________________</div>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="doc-footer">
    Έγγραφο παραχθέν αυτόματα &bull; <?= $today ?> &bull; Κωδικός: #<?= str_pad($s['id'], 5, '0', STR_PAD_LEFT) ?>
  </div>

</div>
<?php endforeach; ?>

</body>
</html>
