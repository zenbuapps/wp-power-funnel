# System

## 描述
Power Funnel 工作流引擎系統。負責監聽 WooCommerce 訂單狀態變更、顧客註冊、Powerhouse 訂閱生命週期事件，並轉換為對應的觸發點 hook。

## 關鍵屬性
- 監聽 WooCommerce 訂單狀態變更事件（6 個狀態）
- 監聽 WordPress user_register hook（顧客註冊）
- 監聽 Powerhouse 訂閱生命週期 hook（7 個事件）
- 建立 Serializable Context Callable Set
- 延遲求值 context（支援 WaitNode）
