module.exports = {
  apps: [
    {
      name: 'dicom-print-scp',
      script: 'C:/xampp/htdocs/dicom_print/scp-runner.js',
      cwd: 'C:/xampp/htdocs/dicom_print/data',
      autorestart: true,
      restart_delay: 3000,
    },
    {
      name: 'dicom-pdf-watcher',
      script: 'C:/xampp/htdocs/dicom_print/watcher.js',
      cwd: 'C:/xampp/htdocs/dicom_print/data',
      autorestart: true,
      restart_delay: 3000,
    },
  ],
};