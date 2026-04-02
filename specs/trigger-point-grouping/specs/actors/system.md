# 系統

## 描述
Power Funnel 後端系統，負責提供 REST API 回傳分組後的觸發點資料。從 ETriggerPoint enum 讀取所有觸發點，依分組結構組織後回傳給前端。

## 關鍵屬性
- 提供 GET /trigger-points API
- 從 ETriggerPoint enum 讀取觸發點清單
- 排除已棄用的 REGISTRATION_CREATED
- 為枚舉存根標記 disabled 狀態
