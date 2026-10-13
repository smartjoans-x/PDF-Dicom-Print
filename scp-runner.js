// Runs dcmprscp (DICOM Print SCP) under PM2, restarts it if it stops,
// and writes data/scp-status.json so the PHP page can show online/offline.
const { spawn } = require('child_process');
const fs = require('fs');

const EXE    = 'C:\\dcmtk\\bin\\dcmprscp.exe';
const ARGS   = ['-c', 'C:/xampp/htdocs/dicom_print/dcmprscp.cfg', '-p', 'PDF_DICOM', '-v'];
/*const ARGS   = ['-c', 'C:\\xampp\htdocs\dicom_print\\dcmprscp.cfg', '-p', 'PDF_DICOM', '-v'];*/
const CWD    = 'C:\\xampp\htdocs\dicom_print\\data';
const STATUS = 'C:\\xampp\htdocs\dicom_print\\data\\scp-status.json';

let child = null;
let alive = false;

function writeStatus() {
  try {
    fs.writeFileSync(STATUS, JSON.stringify({ running: alive, pid: child ? child.pid : null, updated: new Date().toISOString() }));
  } catch (_) {}
}

function start() {
  console.log(new Date().toLocaleString(), '| Starting dcmprscp on AE PDF_DICOM port 1122');
  child = spawn(EXE, ARGS, { cwd: CWD, windowsHide: true });
  child.stdout.on('data', (d) => process.stdout.write(d));
  child.stderr.on('data', (d) => process.stdout.write(d));
  child.on('spawn', () => { alive = true; writeStatus(); });

  let restarted = false;
  const restart = (why) => {
    alive = false;
    writeStatus();
    if (restarted) return;
    restarted = true;
    console.log(new Date().toLocaleString(), '| dcmprscp stopped (' + why + '), restarting in 5 sec');
    setTimeout(start, 5000);
  };
  child.on('exit', (code) => restart('exit code ' + code));
  child.on('error', (e) => restart(e.message));
}

setInterval(writeStatus, 20000); // heartbeat

function stopChild() { alive = false; writeStatus(); if (child) try { child.kill(); } catch (_) {} }
process.on('SIGINT',  () => { stopChild(); process.exit(0); });
process.on('SIGTERM', () => { stopChild(); process.exit(0); });
process.on('exit', stopChild);

start();