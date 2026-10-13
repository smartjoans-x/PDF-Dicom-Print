# 🖨️ DICOM Print to PDF Server

> **Overview:** This system acts as a virtual DICOM printer. It receives film prints from modalities (CT, MRI, X-Ray), saves every print job as a PDF at the actual film size, and allows you to view or download them date-wise from a web interface. 🏥📄

---

## 🚀 Prerequisites & Installation

### 1️⃣ Install XAMPP (Web Server & PHP)
* 📥 Download and install **XAMPP** from [apachefriends.org](https://www.apachefriends.org/).
* ▶️ Start the **Apache** service from the XAMPP Control Panel.
* 📂 The web root directory will be `C:\xampp\htdocs`.

### 2️⃣ Install Node.js
* 📥 Download and install the LTS version of **Node.js** (v20 or newer) from [nodejs.org](https://nodejs.org/).
* 💻 Open Command Prompt and verify the installation:

```cmd
node -v
npm -v
3️⃣ Install DCMTK (DICOM Toolkit)
📥 Download the Windows 64-bit binaries (zip file) from the DCMTK official site.

🗜️ Extract the entire zip folder to your C: drive so that the binary files exist at C:\dcmtk\bin.

4️⃣ Setup the Project
📋 Copy or clone this project folder into your XAMPP web root directory.

📍 The main path should be: C:\xampp\htdocs\dicom_print

🛠️ Required Path Configurations
Before starting the server, you must update the hardcoded paths in the following 5 files to point to your new directory.

⚠️ IMPORTANT: Always use forward slashes (/) or double backslashes (\\) in these configurations. Do not use single backslashes (\).

📄 1. ecosystem.config.js
Update the script and cwd paths for both applications.

JavaScript
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
📄 2. scp-runner.js
Update the path to the configuration file in line 7.

JavaScript
const ARGS = ['-c', 'C:/xampp/htdocs/dicom_print/dcmprscp.cfg', '-p', 'PDF_DICOM', '-v'];
📄 3. watcher.js
Update the main application path in line 15.

JavaScript
const APP = 'C:/xampp/htdocs/dicom_print';
📄 4. dcmprscp.cfg (or dcmprscp.template.cfg)
Find the [Printer_Database] section and update the Database path.

Ini, TOML
Database = "C:/xampp/htdocs/dicom_print/data/database"
📄 5. config.json
Verify the path to your DCMTK installation, AE Title, and port settings.

JSON
{
  "dcmtkBin": "C:\\dcmtk\\bin",
  "aeTitle": "PDF_DICOM",
  "port": 1122,
  "timezone": "Asia/Kolkata",
  "defaultFilm": "14INX17IN"
}
📦 Install Dependencies & Open Firewall
Open Command Prompt as Administrator and navigate to the project folder:

DOS
cd /d C:\xampp\htdocs\dicom_print
1. Install required Node.js packages:

DOS
npm install
2. Open the port in Windows Firewall:
(Assuming you are using default port 1122)

DOS
netsh advfirewall firewall add rule name="DICOM Print 1122" dir=in action=allow protocol=TCP localport=1122
🟢 Starting the Service (PM2)
To keep the application running in the background and ensure it starts automatically on system reboot, install PM2:

DOS
npm install -g pm2 pm2-windows-startup
pm2-startup install
Start your background services:

DOS
pm2 start ecosystem.config.js
pm2 save
Verify everything is running properly:

DOS
pm2 list
✅ Both dicom-print-scp and dicom-pdf-watcher should show a status of online.

🎯 Usage
Configure your Modality: Set up your CT/MRI DICOM Printer settings with the AE Title (PDF_DICOM), the PC's IP Address, and the Port (1122).

Access Dashboard: Open your web browser and navigate to http://YOUR-SERVER-IP/dicom_print/ to view and download your generated PDFs. 🎉
