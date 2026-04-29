const BUDGET = 1000;
let currentUser = null;
let studentBooks = [];   // books για τον logged student (από server)
let studentSelection = []; // επιλεγμένα book ids

// ── API HELPER ────────────────────────────────────────────────
async function api(url, options = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', ...(options.headers||{}) },
    ...options,
  });
  if (options.raw) return res;            // για CSV download
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Σφάλμα server');
  return data;
}

// ── AUTH ──────────────────────────────────────────────────────
async function doLogin() {
  const u = val('login-user'), p = val('login-pass');
  const btn = document.getElementById('btn-login');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>Σύνδεση...';
  try {
    const data = await api('../api/login.php', { method:'POST', body: JSON.stringify({username:u, password:p}) });
    currentUser = data.user;
    hide('login-err');
    if (currentUser.role === 'admin') { showScreen('admin'); await loadAdmin(); }
    else                              { showScreen('student'); await loadStudent(); }
  } catch(e) {
    show('login-err', e.message);
  }
  btn.disabled = false;
  btn.textContent = 'Είσοδος';
}

async function doLogout() {
  await api('../api/login.php', { method:'DELETE' }).catch(()=>{});
  currentUser = null;
  showScreen('login');
  document.getElementById('login-user').value = '';
  document.getElementById('login-pass').value = '';
}

// check existing session on load
window.addEventListener('load', async () => {
  try {
    const data = await api('../api/login.php');
    if (data.ok) {
      currentUser = data.user;
      if (currentUser.role === 'admin') { showScreen('admin'); await loadAdmin(); }
      else                              { showScreen('student'); await loadStudent(); }
    }
  } catch(e) {}
});

// ── ADMIN ─────────────────────────────────────────────────────
async function loadAdmin() { await Promise.all([loadStudents(), loadBooks()]); }

async function loadStudents() {
  const rows = await api('../api/students.php');
  document.getElementById('student-count').textContent = rows.length;
  const tbody = document.getElementById('students-tbody');
  if (!rows.length) { tbody.innerHTML = '<tr><td colspan="5" class="empty-msg">Δεν υπάρχουν σπουδαστές</td></tr>'; return; }
  tbody.innerHTML = rows.map(s => `<tr>
    <td style="font-weight:600;">${esc(s.name)}</td>
    <td style="font-family:monospace;font-size:12px;color:#666;">${esc(s.username)}</td>
    <td>${dirBadge(s.direction)}</td>
    <td><span class="badge ${s.submitted ? 'badge-done':'badge-pending'}">${s.submitted ? '✓ Υποβλήθηκε':'Εκκρεμεί'}</span></td>
    <td><button class="btn btn-danger btn-sm" onclick="deleteStudent(${s.id})">Διαγραφή</button></td>
  </tr>`).join('');
}

async function addStudent() {
  const name=val('ns-name'), user=val('ns-user'), pass=val('ns-pass'), dir=val('ns-dir');
  try {
    await api('../api/students.php', { method:'POST', body: JSON.stringify({name,username:user,password:pass,direction:dir}) });
    hide('add-student-form');
    ['ns-name','ns-user','ns-pass'].forEach(f => document.getElementById(f).value='');
    await loadStudents();
  } catch(e) { show('add-student-err', e.message); }
}

async function deleteStudent(id) {
  if (!confirm('Να διαγραφεί ο σπουδαστής;')) return;
  await api('../api/students.php', { method:'DELETE', body: JSON.stringify({id}) });
  await loadStudents();
}

async function importStudents() {
  const raw = document.getElementById('csv-students').value.trim();
  const lines = raw.split('\n').filter(l => l.trim() && !l.toLowerCase().startsWith('name'));
  const parsed = lines.map(line => {
    const p = line.split(',').map(x=>x.trim());
    return { name:p[0]||'', username:p[1]||'', password:p[2]||'', direction:(p[3]||'').toUpperCase() };
  });
  try {
    const res = await api('../api/students.php?action=import', { method:'POST', body: JSON.stringify({lines:parsed}) });
    document.getElementById('import-student-msg').innerHTML =
      `<div class="success-msg">Εισήχθησαν ${res.imported} σπουδαστές.${res.skipped ? ` (${res.skipped} αγνοήθηκαν)`:''}</div>`;
    document.getElementById('csv-students').value = '';
    await loadStudents();
  } catch(e) { document.getElementById('import-student-msg').innerHTML = `<div class="warn">${e.message}</div>`; }
}

async function loadBooks() {
  const rows = await api('../api/books.php');
  document.getElementById('book-count').textContent = rows.length;
  const tbody = document.getElementById('books-tbody');
  if (!rows.length) { tbody.innerHTML = '<tr><td colspan="5" class="empty-msg">Δεν υπάρχουν βιβλία</td></tr>'; return; }
  tbody.innerHTML = rows.map(b => `<tr>
    <td style="font-weight:600;">${esc(b.title)}</td>
    <td style="color:#666;">${esc(b.author)}</td>
    <td style="font-weight:700;color:#1d4ed8;">${parseFloat(b.cost).toFixed(2)}€</td>
    <td>${dirBadge(b.direction)}</td>
    <td><button class="btn btn-danger btn-sm" onclick="deleteBook(${b.id})">Διαγραφή</button></td>
  </tr>`).join('');
}

async function addBook() {
  const title=val('nb-title'), author=val('nb-author'), cost=val('nb-cost'), dir=val('nb-dir');
  try {
    await api('../api/books.php', { method:'POST', body: JSON.stringify({title,author,cost:parseFloat(cost),direction:dir}) });
    hide('add-book-form');
    ['nb-title','nb-author','nb-cost'].forEach(f => document.getElementById(f).value='');
    await loadBooks();
  } catch(e) { show('add-book-err', e.message); }
}

async function deleteBook(id) {
  if (!confirm('Να διαγραφεί το βιβλίο;')) return;
  await api('../api/books.php', { method:'DELETE', body: JSON.stringify({id}) });
  await loadBooks();
}

async function importBooks() {
  const raw = document.getElementById('csv-books').value.trim();
  const lines = raw.split('\n').filter(l => l.trim() && !l.toLowerCase().startsWith('title'));
  const parsed = lines.map(line => {
    const p = line.split(',').map(x=>x.trim());
    return { title:p[0]||'', author:p[1]||'', cost:parseFloat(p[2])||0, direction:(p[3]||'').toUpperCase() };
  });
  try {
    const res = await api('../api/books.php?action=import', { method:'POST', body: JSON.stringify({lines:parsed}) });
    document.getElementById('import-book-msg').innerHTML =
      `<div class="success-msg">Εισήχθησαν ${res.imported} βιβλία.${res.skipped ? ` (${res.skipped} αγνοήθηκαν)`:''}</div>`;
    document.getElementById('csv-books').value = '';
    await loadBooks();
  } catch(e) { document.getElementById('import-book-msg').innerHTML = `<div class="warn">${e.message}</div>`; }
}
//search bar - filter 
function filterStudents() {
  const q = document.getElementById('student-search').value.toLowerCase();
  document.querySelectorAll('#students-tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

async function loadReports() {
  const [summary, students, demand] = await Promise.all([
    api('../api/report.php?type=summary'),
    api('../api/report.php?type=students'),
    api('../api/report.php?type=demand'),
  ]);
  document.getElementById('r-total').textContent   = summary.total;
  document.getElementById('r-done').textContent    = summary.done;
  document.getElementById('r-pending').textContent = summary.pending;
  document.getElementById('r-avg').textContent     = summary.avg ? summary.avg + '€' : '—';

  const tbody = document.getElementById('report-tbody');
  tbody.innerHTML = students.map(s => `<tr>
    <td style="font-weight:600;">${esc(s.name)}</td>
    <td>${dirBadge(s.direction)}</td>
    <td style="font-size:12px;color:#666;">${s.book_titles.length ? s.book_titles.map(esc).join(' · ') : '—'}</td>
    <td style="font-weight:700;color:#1d4ed8;">${s.total_cost.toFixed(2)}€</td>
    <td><span class="badge ${s.submitted?'badge-done':'badge-pending'}">${s.submitted?'✓ Υποβ.':'Εκκρ.'}</span></td>
<td style="white-space:nowrap;">
  <button class="btn btn-sm" onclick="exportStudentCSV(${s.id})">↓ CSV</button>
  <button class="btn btn-sm" onclick="printReceipt(${s.id})" style="margin-left:4px;">🖨 PDF</button>
</td>
  </tr>`).join('') || '<tr><td colspan="6" class="empty-msg">—</td></tr>';

  const demandEl = document.getElementById('book-demand');
  const withDemand = demand.filter(b => b.demand > 0);
  if (!withDemand.length) { demandEl.innerHTML = '<p style="color:#aaa;font-size:13px;">Δεν υπάρχουν επιλογές ακόμα.</p>'; return; }
  demandEl.innerHTML = '<div class="card">' + withDemand.map(b => `
    <div class="report-book">
      <div>
        <div style="font-weight:600;">${esc(b.title)}</div>
        <div style="font-size:12px;color:#888;margin-top:2px;">${dirBadge(b.direction)}&nbsp;&nbsp;${parseFloat(b.cost).toFixed(2)}€/τεμ · Σύνολο: ${b.subtotal.toFixed(2)}€</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:12px;color:#888;">τεμάχια</span>
        <span class="report-cnt">${b.demand}</span>
      </div>
    </div>`).join('') + '</div>';
}

function exportCSV() {
  const a = document.createElement('a');
  a.href = '../api/report.php?type=csv';
  a.download = 'report_vivlia.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

function exportStudentCSV(id) {
  const a = document.createElement('a');
  a.href = '../api/report.php?type=student_csv&student_id=' + id;
  a.download = '';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

// ── STUDENT ───────────────────────────────────────────────────
async function loadStudent() {
  const u = currentUser;
  document.getElementById('student-greeting').textContent = 'Γεια σου, ' + u.name.split(' ')[0] + '!';
  document.getElementById('student-dir-label').textContent =
    ({ A:'Κατεύθυνση Α', B:'Κατεύθυνση Β', C:'Κατεύθυνση Γ' }[u.direction]) + ' — Επέλεξε τα βιβλία σου (μέγιστο 1000€)';

  const [books, sel] = await Promise.all([
    api('../api/books.php?direction=' + u.direction),
    api('../api/selection.php'),
  ]);
  studentBooks     = books;
  studentSelection = sel.books.map(b => b.id);

  if (sel.submitted) {
    renderSubmittedState(sel.books);
  } else {
    renderSelectionState();
  }
}

function printReceipt(id) {
  window.open('../api/receipt.php?student_id=' + id, '_blank');
}
function renderSubmittedState(books) {
  const total = books.reduce((a,b) => a + parseFloat(b.cost), 0);
  document.getElementById('student-submitted-msg').style.display = 'block';
  document.getElementById('student-selection-wrap').style.display = 'none';
  document.getElementById('student-submitted-detail').textContent =
    `${books.length} βιβλία επιλεγμένα · Συνολικό κόστος: ${total.toFixed(2)}€`;
}

function renderSelectionState() {
  document.getElementById('student-submitted-msg').style.display = 'none';
  document.getElementById('student-selection-wrap').style.display = 'block';
  const el = document.getElementById('student-books-list');
  if (!studentBooks.length) {
    el.innerHTML = '<div class="card empty-msg">Δεν υπάρχουν βιβλία για την κατεύθυνσή σου ακόμα.</div>';
    return;
  }
  el.innerHTML = '<div class="card">' + studentBooks.map(b => `
    <div class="book-item">
      <input type="checkbox" class="book-check" id="book-${b.id}"
        ${studentSelection.includes(b.id)?'checked':''} onchange="toggleBook(${b.id})" />
      <label for="book-${b.id}" class="book-info" style="cursor:pointer;">
        <div class="book-title">${esc(b.title)}</div>
        <div class="book-author">${esc(b.author)}</div>
      </label>
      <div class="book-price">${parseFloat(b.cost).toFixed(2)}€</div>
    </div>`).join('') + '</div>';
  updateBudget();
}

async function toggleBook(id) {
  const cb = document.getElementById('book-' + id);
  if (cb.checked) { if (!studentSelection.includes(id)) studentSelection.push(id); }
  else            { studentSelection = studentSelection.filter(x => x !== id); }
  updateBudget();
  // auto-save στον server (fire and forget)
  api('../api/selection.php', { method:'POST', body: JSON.stringify({book_ids: studentSelection}) }).catch(()=>{});
}

function updateBudget() {
  const total = studentBooks.filter(b => studentSelection.includes(b.id)).reduce((a,b) => a+parseFloat(b.cost), 0);
  const pct   = Math.min((total/BUDGET)*100, 100);
  const bar   = document.getElementById('budget-bar');
  bar.style.width = pct + '%';
  bar.className   = 'budget-bar' + (total > BUDGET ? ' budget-over' : '');
  document.getElementById('budget-text').textContent = total.toFixed(2) + '€ / 1000€';
  document.getElementById('budget-remaining').textContent = 'Απομένουν: ' + Math.max(0, BUDGET-total).toFixed(2) + '€';
  document.getElementById('budget-over-msg').style.display = total > BUDGET ? 'inline' : 'none';
}

async function submitSelection() {
  const err = document.getElementById('submit-err');
  try {
    const res = await api('../api/selection.php', { method:'PUT', body: JSON.stringify({action:'submit'}) });
    err.style.display = 'none';
    const selBooks = studentBooks.filter(b => studentSelection.includes(b.id));
    renderSubmittedState(selBooks);
  } catch(e) { err.textContent = e.message; err.style.display='block'; }
}

async function editSubmission() {
  await api('../api/selection.php', { method:'PUT', body: JSON.stringify({action:'unsubmit'}) });
  renderSelectionState();
}

// ── TABS ──────────────────────────────────────────────────────
function switchTab(name) {
  const names = ['students','books','reports'];
  document.querySelectorAll('.tab').forEach((t,i) => t.classList.toggle('active', names[i]===name));
  document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  if (name==='reports') loadReports();
}

// ── UTILS ─────────────────────────────────────────────────────
function showScreen(name) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById('screen-'+name).classList.add('active');
}
function toggleForm(showId, hideId) {
  document.getElementById(hideId).style.display = 'none';
  const el = document.getElementById(showId);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function hide(id)         { document.getElementById(id).style.display = 'none'; }
function show(id, txt='') { const el=document.getElementById(id); el.style.display='block'; if(txt) el.textContent=txt; }
function val(id)          { return document.getElementById(id).value.trim(); }
function dirBadge(d)      { const l={A:'Κατ. Α',B:'Κατ. Β',C:'Κατ. Γ'}[d]||d; return `<span class="badge badge-${d}">${l}</span>`; }
function esc(s)           { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

document.getElementById('login-pass').addEventListener('keydown', e => { if(e.key==='Enter') doLogin(); });
document.getElementById('login-user').addEventListener('keydown', e => { if(e.key==='Enter') document.getElementById('login-pass').focus(); });
