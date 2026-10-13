// ============================================================
//  DICOM Print -> PDF  (SL Diagnostics)   v2
//  - PDF page size = film size sent by the modality (14INX17IN etc.)
//  - Black background, film fills the page
//  - img\Header.png on top, img\footer.png at bottom (full width)
//  - No patient text on the page
// ============================================================
const fs = require('fs');
const path = require('path');
const { execFile } = require('child_process');
const chokidar = require('chokidar');
const dicomParser = require('dicom-parser');
const PDFDocument = require('pdfkit');

const APP  = 'C:\\xampp\htdocs\dicom_print';
const BASE = path.join(APP, 'data');
const CONFIG = {
  dcmj2pnm: 'C:\\dcmtk\\bin\\dcmj2pnm.exe',
  watchDirs: [path.join(BASE, 'database'), path.join(BASE, 'spool')],
  pdfDir: path.join(BASE, 'pdfs'),
  tmpDir: path.join(BASE, 'tmp'),
  processedFile: path.join(BASE, 'processed.json'),
  headerImg: path.join(APP, 'img', 'Header.png'),
  footerImg: path.join(APP, 'img', 'footer.png'),
  defaultFilm: '14INX17IN', // used when the modality does not send a film size
  groupWaitMs: 8000,        // films arriving within 8 sec = one PDF
};

const HARDCOPY = ['1.2.840.10008.5.1.1.29', '1.2.840.10008.5.1.1.30'];
const STORED_PRINT = '1.2.840.10008.5.1.1.27';

for (const d of [...CONFIG.watchDirs, CONFIG.pdfDir, CONFIG.tmpDir]) fs.mkdirSync(d, { recursive: true });
const log = (...a) => console.log(new Date().toLocaleString(), '|', ...a);

// ---------- processed list ----------
let processed = new Set();
try { processed = new Set(JSON.parse(fs.readFileSync(CONFIG.processedFile, 'utf8'))); } catch (_) {}
function markDone(file) {
  processed.add(file);
  fs.writeFileSync(CONFIG.processedFile, JSON.stringify([...processed]));
}

// ---------- DICOM tags ----------
function readTags(file) {
  const buf = fs.readFileSync(file);
  if (buf.length < 132 || buf.toString('ascii', 128, 132) !== 'DICM') return null;
  const ds = dicomParser.parseDicom(new Uint8Array(buf), { untilTag: 'x7fe00010' });
  const s = (t) => (ds.string(t) || '').trim();
  return {
    sopClass: s('x00080016'),
    patientName: s('x00100010'),
    patientId: s('x00100020'),
    sessionLabel: s('x20000050'),  // Film Session Label
    filmSize: s('x20100050'),      // Film Size ID e.g. 14INX17IN
    orientation: s('x20100040'),   // PORTRAIT / LANDSCAPE
  };
}

const cleanName = (n) => (n || '').replace(/\^+/g, ' ').trim();
const safeName = (n) => cleanName(n).replace(/[^A-Za-z0-9 _-]/g, '').trim().replace(/\s+/g, '_').slice(0, 60);

function stamp(d = new Date()) {
  const p = (x) => String(x).padStart(2, '0');
  return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}_${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
}

function run(cmd, args) {
  return new Promise((resolve, reject) =>
    execFile(cmd, args, (err, so, se) => (err ? reject(new Error(se || err.message)) : resolve(so)))
  );
}

// Film Size ID -> [width, height] in PDF points (72 pt = 1 inch)
function filmToPoints(id) {
  const v = (id || '').toUpperCase().replace(/\s/g, '');
  const iso = { A4: [595.28, 841.89], A3: [841.89, 1190.55] };
  if (iso[v]) return iso[v];
  const m = v.match(/^(\d+(?:\.\d+)?)(IN|CM)X(\d+(?:\.\d+)?)(IN|CM)$/);
  if (!m) return null;
  const pt = (n, u) => (u === 'IN' ? n * 72 : (n * 72) / 2.54);
  return [pt(parseFloat(m[1]), m[2]), pt(parseFloat(m[3]), m[4])];
}

function pageSizeFor(filmSize, orientation, img) {
  let [w, h] = filmToPoints(filmSize) || filmToPoints(CONFIG.defaultFilm);
  const short = Math.min(w, h), long = Math.max(w, h);
  let landscape;
  if (/LANDSCAPE/i.test(orientation)) landscape = true;
  else if (/PORTRAIT/i.test(orientation)) landscape = false;
  else landscape = img.width > img.height; // not sent: follow the film image
  return landscape ? [long, short] : [short, long];
}

// ---------- batching ----------
let batch = null;
let queue = Promise.resolve();

function newBatch() {
  return { files: [], extra: [], name: '', pid: '', label: '', filmSize: '', orientation: '' };
}

function onFile(file) {
  if (processed.has(file)) return;
  if (path.basename(file).toLowerCase() === 'index.dat') return;

  let t;
  try { t = readTags(file); } catch (e) { log('Skip (parse error):', file, e.message); return; }
  if (!t) return;

  const isImage = HARDCOPY.includes(t.sopClass);
  const isStoredPrint = t.sopClass === STORED_PRINT;
  if (!isImage && !isStoredPrint) { markDone(file); return; }

  // a different, named patient arriving = new PDF
  if (batch && t.patientName && batch.name && t.patientName !== batch.name) flush();
  if (!batch) batch = newBatch();

  if (t.patientName) batch.name = t.patientName;
  if (t.patientId) batch.pid = t.patientId;
  if (t.sessionLabel) batch.label = t.sessionLabel;
  if (t.filmSize) batch.filmSize = t.filmSize;
  if (t.orientation) batch.orientation = t.orientation;

  if (isImage) {
    batch.files.push(file);
    log(`Film received (${batch.files.length})`);
  } else {
    batch.extra.push(file);
    log(`Print job info: film ${t.filmSize || '-'} ${t.orientation || ''}`);
  }

  clearTimeout(batch.timer);
  batch.timer = setTimeout(flush, CONFIG.groupWaitMs);
}

function flush() {
  if (!batch) return;
  const job = batch;
  batch = null;
  clearTimeout(job.timer);
  if (!job.files.length) { job.extra.forEach(markDone); return; }
  queue = queue.then(() => makePdf(job)).catch((e) => log('PDF ERROR:', e.message));
}

// ---------- PDF ----------
async function makePdf(job) {
  const pngs = [];
  for (const f of job.files) {
    const out = path.join(CONFIG.tmpDir, `${path.basename(f)}_${Date.now()}.png`);
    try { await run(CONFIG.dcmj2pnm, ['+on', '+Wm', f, out]); pngs.push(out); }
    catch (e) { log('dcmj2pnm failed:', f, e.message); }
  }
  if (!pngs.length) { [...job.files, ...job.extra].forEach(markDone); return; }

  const title = cleanName(job.name) || cleanName(job.label);
  const base = safeName(title) || 'FILM';
  const pdfName = `${base}_${stamp()}.pdf`;
  const tmpPdf = path.join(CONFIG.tmpDir, pdfName);

  const headerBuf = fs.existsSync(CONFIG.headerImg) ? fs.readFileSync(CONFIG.headerImg) : null;
  const footerBuf = fs.existsSync(CONFIG.footerImg) ? fs.readFileSync(CONFIG.footerImg) : null;
  let usedSize = '';

  await new Promise((resolve, reject) => {
    const doc = new PDFDocument({ autoFirstPage: false, margin: 0, info: { Title: title || pdfName } });
    const ws = fs.createWriteStream(tmpPdf);
    ws.on('finish', resolve);
    ws.on('error', reject);
    doc.pipe(ws);

    const header = headerBuf ? doc.openImage(headerBuf) : null;
    const footer = footerBuf ? doc.openImage(footerBuf) : null;

    for (const png of pngs) {
      const film = doc.openImage(png);
      const [pw, ph] = pageSizeFor(job.filmSize, job.orientation, film);
      usedSize = `${(pw / 72).toFixed(1)}x${(ph / 72).toFixed(1)} in`;
      doc.addPage({ size: [pw, ph], margin: 0 });

      // black background
      doc.rect(0, 0, pw, ph).fill('#000000');

      // header & footer at full page width, keeping their shape
      const hh = header ? (pw * header.height) / header.width : 0;
      const fh = footer ? (pw * footer.height) / footer.width : 0;
      if (header) doc.image(header, 0, 0, { width: pw });
      if (footer) doc.image(footer, 0, ph - fh, { width: pw });

      // film fills everything between header and footer
      doc.image(film, 0, hh, { fit: [pw, ph - hh - fh], align: 'center', valign: 'center' });
    }
    doc.end();
  });

  const finalPdf = path.join(CONFIG.pdfDir, pdfName);
  fs.renameSync(tmpPdf, finalPdf);
  fs.writeFileSync(
    finalPdf.replace(/\.pdf$/i, '.json'),
    JSON.stringify({
      patientName: title || pdfName.replace(/\.pdf$/i, ''),
      patientId: job.pid,
      pages: pngs.length,
      filmSize: job.filmSize || CONFIG.defaultFilm + ' (default)',
      savedAt: new Date().toISOString(),
    })
  );

  pngs.forEach((p) => fs.unlink(p, () => {}));
  [...job.files, ...job.extra].forEach(markDone);
  log(`PDF saved: ${pdfName} | ${pngs.length} film(s) | film ${job.filmSize || 'default'} | page ${usedSize}`);
}

// ---------- start ----------
chokidar
  .watch(CONFIG.watchDirs, {
    ignoreInitial: false,
    depth: 3,
    awaitWriteFinish: { stabilityThreshold: 2000, pollInterval: 300 },
  })
  .on('add', onFile)
  .on('error', (e) => log('Watcher error:', e.message));

log('DICOM Print -> PDF watcher v2 running. Watching:', CONFIG.watchDirs.join(' , '));
log('Header:', fs.existsSync(CONFIG.headerImg) ? 'found' : 'NOT FOUND', '| Footer:', fs.existsSync(CONFIG.footerImg) ? 'found' : 'NOT FOUND');