🚀 快速安裝指南 (Quick Start Guide)

本指南將引導您在 Windows 環境下使用 Laragon 部署 InfraInventory V4.1。

環境準備

下載並安裝 Laragon WAMP (推薦 Full 版本)。

啟動 Laragon，點擊 Start All 確保 Apache 與 MySQL 已啟動。

步驟 1：部署程式碼

進入 Laragon 的網頁根目錄 (通常是 C:\laragon\www\)。

建立一個新資料夾，命名為 infra-inventory。

將本專案的所有檔案 (包括 index.html 和 api/ 資料夾) 放入該目錄中。

路徑結構應如下所示：

C:\laragon\www\infra-inventory\index.html
C:\laragon\www\infra-inventory\api\config.php
...


步驟 2：設定資料庫連線

打開 api/config.php 檔案。

確認資料庫設定 (Laragon 預設為 root / 無密碼)：

$host = 'localhost';
$db_name = 'infra_inventory'; // 系統將自動建立此名稱
$username = 'root';
$password = ''; // Laragon 預設為空，若有修改請在此填入


步驟 3：初始化資料庫 (一鍵安裝)

我們提供了一個自動化腳本來建立資料庫、資料表與預設管理員帳號。

打開瀏覽器，訪問以下網址：
http://localhost/infra-inventory/api/setup_database.php

您應該會看到綠色的 "✅ Installation Successful" 訊息。

此腳本會自動建立 infra_inventory 資料庫。

建立所有必要的資料表 (inventory, brands, models, users, audit_logs)。

寫入預設的種子數據 (Seed Data)。

安全建議：安裝完成後，建議刪除或重新命名 api/setup_database.php 以防止資料被誤重置。

步驟 4：登入系統

回到首頁：http://localhost/infra-inventory/

使用預設管理員帳號登入：

Username: admin

Password: password123

常見問題 (FAQ)

Q: 登入時出現 "Request failed with status code 404"？
A: 請檢查您的 api 資料夾是否位置正確。瀏覽器按 F12 打開 Network 分頁，確認 API 請求的路徑是否為 api/login.php。

Q: 如何備份資料？
A: 登入後點擊右上角的 Settings (⚙️) -> Backup & Restore。輸入一組密碼後點擊下載。請務必記住該密碼，還原時需要用到。

Q: 深色模式如何開啟？
A: 系統會自動偵測您的作業系統設定 (Windows/Mac 的深色主題)。若要測試，請將您的電腦主題改為深色，網頁將自動切換。