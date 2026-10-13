# 🖨️ DICOM Print to PDF Server

<p align="center">
  <strong>A Virtual DICOM Printer for CT, MRI, and X-Ray Modalities</strong>
</p>

<p align="center">
  Convert DICOM film print jobs into properly sized PDF documents and access them through a simple web dashboard.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Platform-Windows-0078D4?style=for-the-badge&logo=windows" alt="Windows">
  <img src="https://img.shields.io/badge/Backend-Node.js-339933?style=for-the-badge&logo=nodedotjs&logoColor=white" alt="Node.js">
  <img src="https://img.shields.io/badge/Web_Server-Apache-D22128?style=for-the-badge&logo=apache" alt="Apache">
  <img src="https://img.shields.io/badge/Protocol-DICOM-0A7EA4?style=for-the-badge" alt="DICOM">
  <img src="https://img.shields.io/badge/Output-PDF-red?style=for-the-badge&logo=adobeacrobatreader" alt="PDF">
</p>

---

## 📌 Overview

**DICOM Print to PDF Server** is a Windows-based virtual DICOM printer designed for medical imaging environments.

It receives film print jobs from compatible medical imaging modalities, such as CT, MRI, and X-Ray systems, processes the jobs using DCMTK, and saves the resulting print output as PDF files at the configured film size.

A web-based interface allows authorized users on the network to view and download generated PDF documents.

### ✨ Key Features

- 🏥 **DICOM Print Reception** — Receive print jobs from compatible imaging modalities.
- 🖨️ **Virtual Printer** — Process DICOM print jobs without requiring a physical film printer.
- 📄 **PDF Output** — Generate PDF documents using the configured film dimensions.
- 📅 **Date-Wise Access** — Browse generated documents by date through the web interface.
- 🌐 **Web Dashboard** — Access the application through a browser on the local network.
- ⚙️ **Background Services** — Run the DICOM receiver and PDF watcher using PM2.
- 🔄 **Automatic Restart** — Restart supported background processes after failures.
- 🇮🇳 **Timezone Configuration** — Configure the application timezone, including `Asia/Kolkata`.

> **Note:** PDF generation, film-size handling, date-wise organization, and dashboard functionality depend on the implementation and configuration included in the project.

---

## 🏗️ System Architecture

```text
┌─────────────────────────────┐
│     CT / MRI / X-Ray        │
│       DICOM Modality        │
└──────────────┬──────────────┘
               │
               │ DICOM Print
               │ TCP Port 1122
               ▼
┌─────────────────────────────┐
│       DCMTK Print SCP       │
│       dcmprscp / DCMTK      │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│     Print Job Database      │
│       /data/database        │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│      PDF File Watcher       │
│         watcher.js          │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│       Generated PDFs        │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│      Apache Web Server      │
│       PHP Dashboard         │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  View / Browse / Download   │
│       PDF Documents         │
└─────────────────────────────┘
```

---

## 📋 Prerequisites

Install the following software before configuring the application.

| Software | Requirement | Purpose |
|---|---|---|
| XAMPP | Apache and PHP | Web dashboard |
| Node.js | Version 20 or newer | Background services |
| npm | Bundled with Node.js | Package installation |
| DCMTK | Windows 64-bit binaries | DICOM print processing |
| PM2 | Global installation | Process management |
| Windows Firewall | Administrative access | Allow DICOM network traffic |

---

## 🚀 Installation Guide

### Step 1 — Install XAMPP

1. Download XAMPP from the [official website](https://www.apachefriends.org/).
2. Install XAMPP on your Windows computer.
3. Open the XAMPP Control Panel.
4. Start the **Apache** service.
5. Confirm that the default web root directory exists.

```text
C:\xampp\htdocs
```

### Step 2 — Install Node.js

1. Download the LTS version of Node.js from [nodejs.org](https://nodejs.org/).
2. Install Node.js and npm.
3. Open Command Prompt and verify the installation.

```bash
node -v
npm -v
```

**Requirement:** Node.js v20 or newer.

### Step 3 — Install DCMTK

1. Download the Windows 64-bit binaries from the [official DCMTK website](https://dicom.offis.de/dcmtk.php.en).
2. Extract the downloaded archive to the `C:\dcmtk` directory.
3. Verify that the executable files are available in the following folder.

```text
C:\dcmtk\bin
```

The DCMTK executable paths must match the actual installation directory.

### Step 4 — Copy the Project

Copy or clone the project into the XAMPP web root.

The expected directory structure is:

```text
C:\xampp\htdocs\dicom_print\
│
├── ecosystem.config.js
├── scp-runner.js
├── watcher.js
├── dcmprscp.cfg
├── config.json
├── package.json
├── index.php
│
└── data\
    └── database\
```

> **Important:** This is an illustrative directory structure. Keep the actual filenames and folders supplied by your project. Do not create placeholder files in place of existing application files.

---

## ⚙️ Configuration

Before starting the services, update the application paths and settings to match your installation.

**Default project directory:**

```text
C:/xampp/htdocs/dicom_print
```

Use forward slashes (`/`) in JavaScript and INI path values, or properly escaped backslashes (`\\`) in JSON strings.

### 1. Configure `ecosystem.config.js`

Update the script and working directory paths for both background applications.

```javascript
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
```

### 2. Configure `scp-runner.js`

Update the DCMTK configuration file path.

```javascript
const ARGS = [
  '-c',
  'C:/xampp/htdocs/dicom_print/dcmprscp.cfg',
  '-p',
  'PDF_DICOM',
  '-v'
];
```

### 3. Configure `watcher.js`

Update the main application directory.

```javascript
const APP = 'C:/xampp/htdocs/dicom_print';
```

### 4. Configure `dcmprscp.cfg`

Locate the `[Printer_Database]` section and update the database path.

```ini
[Printer_Database]
Database = "C:/xampp/htdocs/dicom_print/data/database"
```

If your project uses `dcmprscp.template.cfg`, update the template instead and follow the project's configuration-generation process.

### 5. Configure `config.json`

Verify the DCMTK directory, DICOM Application Entity Title, listening port, timezone, and default film size.

```json
{
  "dcmtkBin": "C:\\dcmtk\\bin",
  "aeTitle": "PDF_DICOM",
  "port": 1122,
  "timezone": "Asia/Kolkata",
  "defaultFilm": "14INX17IN"
}
```

**Configuration reference**

| Setting | Value | Description |
|---|---|---|
| `dcmtkBin` | `C:\dcmtk\bin` | DCMTK binary directory |
| `aeTitle` | `PDF_DICOM` | DICOM printer AE Title |
| `port` | `1122` | Configured DICOM listener port |
| `timezone` | `Asia/Kolkata` | Application timezone |
| `defaultFilm` | `14INX17IN` | Default film-size setting |

> **⚠️ Important:** Ensure that the port, AE Title, film-size identifier, configuration file, and database paths agree with the values supported by your DCMTK configuration and project.

---

## 📦 Install Dependencies

Open **Command Prompt as Administrator**.

Navigate to the project directory:

```bat
cd /d C:\xampp\htdocs\dicom_print
```

Install the Node.js dependencies:

```bash
npm install
```

Wait for npm to finish. Resolve any reported dependency or installation errors before proceeding.

---

## 🔥 Configure Windows Firewall

Allow incoming TCP traffic on port `1122`, assuming this is the configured DICOM listener port.

Run the following command in an elevated Command Prompt:

```bat
netsh advfirewall firewall add rule name="DICOM Print 1122" dir=in action=allow protocol=TCP localport=1122
```

Verify the rule:

```bat
netsh advfirewall firewall show rule name="DICOM Print 1122"
```

**Security recommendations**

- Restrict access to trusted modality devices and authorized network segments wherever possible.
- Do not expose the DICOM listener or patient PDF files directly to the public internet.
- Configure firewall rules according to your hospital's network security policy.

---

## 🟢 Start Background Services with PM2

PM2 manages the DICOM receiver and PDF watcher as background processes.

### 1. Install PM2

```bash
npm install -g pm2
```

For Windows startup integration, install the startup package:

```bash
npm install -g pm2-windows-startup
```

Register PM2 for startup:

```bash
pm2-startup install
```

> **Windows note:** Startup integration may depend on the installed PM2 startup package version and Windows configuration. Verify that the startup command succeeds and follow any instructions printed by the package.

### 2. Start the Applications

From the project directory, run:

```bash
pm2 start ecosystem.config.js
```

Save the current process list:

```bash
pm2 save
```

### 3. Verify Service Status

```bash
pm2 list
```

Both applications should show an `online` status:

| Application | Expected status |
|---|---|
| `dicom-print-scp` | `online` |
| `dicom-pdf-watcher` | `online` |

### 4. View Logs

Check the logs if a service fails to start.

```bash
pm2 logs
```

View logs for an individual process:

```bash
pm2 logs dicom-print-scp
```

```bash
pm2 logs dicom-pdf-watcher
```

### 5. Restart or Stop Services

Restart both applications:

```bash
pm2 restart all
```

Stop both applications:

```bash
pm2 stop all
```

Remove the applications from PM2:

```bash
pm2 delete all
```

**Note:** `pm2 delete all` removes all processes from the current PM2 process list, not just this project. Use it carefully if other applications are managed by PM2.

---

## 🌐 Access the Web Dashboard

After Apache and the required background services are running, open a browser on the server or an authorized device on the same network.

**Local access**

```text
http://localhost/dicom_print/
```

**Network access**

```text
http://YOUR-SERVER-IP/dicom_print/
```

Replace `YOUR-SERVER-IP` with the Windows server's actual LAN IP address.

Example:

```text
http://192.168.1.100/dicom_print/
```

The example IP is illustrative. Use the IP address assigned to your server.

---

## 🏥 Configure the DICOM Modality

Configure the CT, MRI, or X-Ray modality to send print jobs to the Windows server.

| Modality setting | Configuration |
|---|---|
| Printer / Destination AE Title | `PDF_DICOM` |
| Destination IP Address | Windows server's LAN IP |
| Destination TCP Port | `1122` |
| Network protocol | DICOM over TCP/IP |

Before testing, confirm that the modality supports the configured DICOM Print service and that the AE Title, port, and network settings match the receiver configuration.

**Recommended validation procedure**

1. Confirm that the DICOM receiver is running in PM2.
2. Verify network connectivity and firewall permissions.
3. Send a test print from the modality.
4. Check the DICOM receiver logs for incoming activity.
5. Confirm that the print job is processed.
6. Verify that the generated PDF is available through the dashboard.
7. Check the PDF's page dimensions and image orientation.

---

## 🛠️ Troubleshooting

### Problem: DICOM receiver is offline

**Possible causes**
- Incorrect DCMTK installation path.
- Missing or invalid configuration file.
- Port already in use.
- Incorrect project directory.
- Invalid PM2 configuration.

**Check the logs:**

```bash
pm2 logs dicom-print-scp
```

Verify that `dcmprscp.cfg` exists and that the configured DCMTK executable is present.

### Problem: No PDF files are generated

**Possible causes**
- The modality cannot connect to the receiver.
- The print job was not received or processed successfully.
- The watcher is not running.
- Database or output paths are incorrect.
- A file permission or processing error occurred.

**Check both services:**

```bash
pm2 logs dicom-print-scp
pm2 logs dicom-pdf-watcher
```

Confirm that the expected data directories exist and that the application account has the necessary permissions.

### Problem: The dashboard does not open

**Check the following:**

- Apache is running in the XAMPP Control Panel.
- The project is located inside `C:\xampp\htdocs\dicom_print`.
- The correct URL is being used.
- PHP errors or Apache errors are not preventing the page from loading.
- The Windows firewall allows the required web traffic.

### Problem: The modality cannot connect

**Verify the network settings:**

- Correct server IP address.
- Correct AE Title: `PDF_DICOM`.
- Correct destination port: `1122`.
- Windows Firewall rules.
- No other process is using the configured port.
- The receiver is listening on the appropriate network interface.

### Problem: PM2 services do not start after reboot

**Verify the startup integration:**

```bash
pm2 list
pm2 save
```

Confirm that the Windows startup integration is installed correctly for the account running PM2. Test after a controlled restart.

---

## 🔐 Security & Patient Data Protection

This application may handle sensitive medical images and patient information. Deploy it with appropriate safeguards.

- 🔒 Restrict access to authorized users and trusted devices.
- 🛡️ Use firewall rules to limit DICOM traffic to approved network sources.
- 🌐 Avoid exposing the dashboard or DICOM receiver directly to the internet.
- 📁 Protect PDF output directories and database files with appropriate Windows permissions.
- 💾 Maintain secure backups and an appropriate retention policy.
- 🧹 Remove temporary files and retained patient data according to organizational policy.
- 🔑 Add suitable authentication and access controls if the dashboard does not already provide them.
- 📋 Follow applicable healthcare privacy, security, and data-retention requirements.

---

## 📁 Project Configuration Summary

| Component | Purpose |
|---|---|
| `ecosystem.config.js` | PM2 application definitions |
| `scp-runner.js` | Starts the configured DICOM print receiver |
| `watcher.js` | Monitors print-processing files or events |
| `dcmprscp.cfg` | DCMTK print configuration |
| `config.json` | Application and network settings |
| `data/database` | Configured print database directory |
| `index.php` | Dashboard entry point, if included in the project |

*The filenames and responsibilities above reflect the documented configuration. Actual behavior depends on the project's implementation.*

---

## ✅ Deployment Checklist

Before using the system in a production environment, verify each item.

- [ ] XAMPP and Apache are installed and running.
- [ ] Node.js and npm are installed.
- [ ] DCMTK binaries are available.
- [ ] All project paths are correct.
- [ ] The configuration files are valid.
- [ ] Node.js dependencies are installed.
- [ ] The configured DICOM port is reachable from authorized devices.
- [ ] Both PM2 services are online.
- [ ] The modality can successfully send a test print.
- [ ] Generated PDFs have been checked for correct dimensions and image content.
- [ ] Dashboard access and file permissions have been tested.
- [ ] Patient-data security and backup procedures are in place.

---

## 📄 License

Add the applicable license for this project before distributing it publicly. If no license is provided, the repository should not be assumed to grant permission to reuse, modify, or redistribute its code.

---

<p align="center">
  <strong>DICOM Print to PDF Server</strong>
  <br>
  Simplifying DICOM film-print workflows with PDF output and browser-based access.
</p>
