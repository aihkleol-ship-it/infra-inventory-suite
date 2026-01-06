InfraInventory V4.1 - 企業級 IT 資產管理系統

InfraInventory 是一個輕量化、無需 Node.js 編譯環境的現代化 IT 資產管理系統 (ITAM)。專為基礎設施團隊設計，用於追蹤伺服器、網路設備、生命週期 (EOS) 及稽核紀錄。

🌟 主要功能 (Features)

核心資產管理

完整 CRUD：新增、修改、刪除伺服器與網路設備。

主數據管理 (Master Data)：動態管理品牌 (Brands)、型號 (Models) 與設備類型。

關聯式防呆：新增設備時強制選擇已定義的型號，確保數據一致性。

位置管理：支援主位置 (Location) 與子位置 (Sub Location，如機櫃/U數) 紀錄。

智慧功能

EOS 風險儀表板：自動計算並以紅/橘/綠燈號顯示設備維保到期風險 (End of Support)。

視覺化圖表：內建 Chart.js 儀表板，即時顯示資產分佈與狀態統計。

CSV 匯入/匯出：支援批量匯入資產 (附範本下載) 與一鍵匯出完整報表。

安全與稽核

角色權限控制 (RBAC)：

Admin：完全控制 (含使用者管理、備份還原)。

Editor：資產維護 (新增/修改/刪除)。

Viewer：僅限瀏覽與匯出 (唯讀模式)。

稽核軌跡 (Audit Logs)：自動記錄所有資料變更 (Who, When, What)，不可竄改。

加密備份與還原：支援 AES-256 加密的全資料庫備份與災難復原 (Disaster Recovery)。

使用者體驗 (UI/UX)

自適應深色模式 (Dark Mode)：自動跟隨作業系統設定切換深色/淺色主題。

單頁應用 (SPA)：基於 React 構建，操作流暢無刷新。

免編譯部署：直接使用 CDN 載入 React 與 TailwindCSS，解壓縮即可在 Apache/Nginx 上運行。

🛠 技術架構 (Tech Stack)

前端 (Frontend): React 18 (via CDN), TailwindCSS (via CDN), Axios, Chart.js, Phosphor Icons.

後端 (Backend): Native PHP 8.0+ (PDO SQLite/MySQL).

資料庫 (Database): MySQL 8.0 / MariaDB.

環境需求: 任何支援 PHP 與 MySQL 的 Web Server (推薦 Laragon, XAMPP, 或 Docker).

📂 目錄結構

/infra-inventory
├── api/
│   ├── config.php         # 資料庫連線設定
│   ├── inventory.php      # 資產 CRUD API
│   ├── settings.php       # 主數據 (品牌/型號) API
│   ├── users.php          # 使用者管理 API
│   ├── logs.php           # 稽核紀錄 API
│   ├── backup.php         # 加密備份 API
│   ├── restore.php        # 備份還原 API
│   ├── import.php         # CSV 匯入處理
│   ├── logger.php         # 稽核日誌記錄器
│   └── setup_database.php # (安裝用) 資料庫初始化腳本
├── index.html             # 前端主程式 (React SPA)
└── QUICK_START.md         # 安裝說明


📜 授權 (License)

MIT License.