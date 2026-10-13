cd /d C:\xampp\htdocs\dicom_print

npm install

netsh advfirewall firewall add rule name="DICOM Print 1122" dir=in action=allow protocol=TCP localport=1122

npm install -g pm2 pm2-windows-startup

pm2-startup install

pm2 start ecosystem.config.js

pm2 save

pm2 list

------------------------

pm2 delete all
pm2 flush


netstat -an | findstr 1122


pm2 logs dicom-print-scp

---------------------------------

Important Note: Always use forward slashes (/) or double backslashes (\\) for directory paths in JavaScript and JSON files.1. ecosystem.config.jsUpdate the script and cwd paths for both the dicom-print-scp and dicom-pdf-watcher background applications.   JavaScriptscript: 'C:/xampp/htdocs/dicom_print/scp-runner.js',
cwd: 'C:/xampp/htdocs/dicom_print/data',
2. scp-runner.jsUpdate the path to the dcmprscp.cfg file inside the ARGS array.   JavaScriptconst ARGS   = ['-c', 'C:/xampp/htdocs/dicom_print/dcmprscp.cfg', '-p', 'PDF_DICOM', '-v'];
3. watcher.jsUpdate the APP variable to point to your new main directory.   JavaScriptconst APP  = 'C:/xampp/htdocs/dicom_print';
4. dcmprscp.cfg (or dcmprscp.template.cfg)Update the Database path under the [Printer_Database] section so the system knows where to store the raw prints.   PlaintextDatabase = "C:/xampp/htdocs/dicom_print/data/database"
5. config.jsonVerify that the path pointing to your DCMTK binaries is correct for the new machine.   JSON"dcmtkBin": "C:\\dcmtk\\bin"