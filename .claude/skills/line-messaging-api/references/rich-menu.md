# Rich Menu 完整操作參考

## 概述

Rich Menu 是顯示在 LINE 聊天視窗底部的自訂選單。尺寸固定為寬 2500px，高 1686px（全尺寸）或 843px（半尺寸）。

## 建立 Rich Menu 完整流程

### 步驟 1：建立 Rich Menu 定義

```php
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Model\RichMenuRequest;
use LINE\Clients\MessagingApi\Model\RichMenuSize;
use LINE\Clients\MessagingApi\Model\RichMenuArea;
use LINE\Clients\MessagingApi\Model\RichMenuBounds;
use LINE\Clients\MessagingApi\Model\URIAction;
use LINE\Clients\MessagingApi\Model\PostbackAction;

$richMenuRequest = new RichMenuRequest([
    'size'        => new RichMenuSize(['width' => 2500, 'height' => 1686]),
    'selected'    => true,      // 是否預設展開
    'name'        => '選單名稱',  // max 300 字，僅管理用途
    'chatBarText' => '功能選單',  // max 14 字，顯示在選單欄
    'areas'       => [
        new RichMenuArea([
            'bounds' => new RichMenuBounds([
                'x'      => 0,
                'y'      => 0,
                'width'  => 833,
                'height' => 1686,
            ]),
            'action' => new PostbackAction([
                'type'  => 'postback',
                'label' => '功能1',
                'data'  => 'function=1',
            ]),
        ]),
        new RichMenuArea([
            'bounds' => new RichMenuBounds([
                'x'      => 833,
                'y'      => 0,
                'width'  => 834,
                'height' => 1686,
            ]),
            'action' => new URIAction([
                'type'  => 'uri',
                'label' => '官網',
                'uri'   => 'https://example.com',
            ]),
        ]),
        // 最多 20 個區域
    ],
]);

$result = $api->createRichMenu($richMenuRequest);
$richMenuId = $result->getRichMenuId(); // string: richmenu-xxx
```

### 步驟 2：上傳背景圖片

需要使用 `MessagingApiBlobApi`：

```php
use LINE\Clients\MessagingApi\Api\MessagingApiBlobApi;
use GuzzleHttp\Client;

$blobApi = new MessagingApiBlobApi(
    client: new Client(),
    config: $config,
);

// 上傳圖片（JPEG 或 PNG，max 1MB）
$imageData = file_get_contents('/path/to/rich-menu.png');
$blobApi->setRichMenuImage($richMenuId, $imageData);
```

### 步驟 3：設定與連結

```php
// 設為預設 Rich Menu（所有用戶）
$api->setDefaultRichMenu($richMenuId);

// 或連結到特定用戶
$api->linkRichMenuIdToUser($userId, $richMenuId);
```

## RichMenuRequest 完整屬性

| 屬性 | 型別 | 說明 |
|------|------|------|
| size | RichMenuSize | 必填: 寬高 |
| selected | bool | 是否預設展開（true=展開） |
| name | string | 選單名稱，max 300 字 |
| chatBarText | string | 選單欄文字，max 14 字 |
| areas | RichMenuArea[] | 可點擊區域，max 20 個 |

## RichMenuSize 屬性

```php
new RichMenuSize(['width' => 2500, 'height' => 1686]);  // 全尺寸
new RichMenuSize(['width' => 2500, 'height' => 843]);   // 半尺寸
```

## RichMenuArea 屬性

```php
new RichMenuArea([
    'bounds' => new RichMenuBounds([
        'x'      => 0,      // 左上角 X 坐標
        'y'      => 0,      // 左上角 Y 坐標
        'width'  => 1250,
        'height' => 843,
    ]),
    'action' => $action,    // 任意 Action 型別
]);
```

## 管理操作

### 查詢

```php
// 取得特定 Rich Menu
$richMenu = $api->getRichMenu($richMenuId);
$richMenu->getRichMenuId();
$richMenu->getSize();       // RichMenuSize
$richMenu->isSelected();    // bool
$richMenu->getName();
$richMenu->getChatBarText();
$richMenu->getAreas();      // RichMenuArea[]

// 列出所有 Rich Menu
$list = $api->getRichMenuList();
// 回傳 RichMenuListResponse，最多 1000 個

// 取得預設 Rich Menu ID
$defaultId = $api->getDefaultRichMenuId();

// 取得用戶目前的 Rich Menu ID
$userMenuId = $api->getRichMenuIdOfUser($userId);
```

### 刪除與解除

```php
// 刪除 Rich Menu（需先解除所有連結）
$api->deleteRichMenu($richMenuId);

// 取消預設 Rich Menu
$api->cancelDefaultRichMenu();

// 解除特定用戶的 Rich Menu
$api->unlinkRichMenuIdFromUser($userId);

// 批次解除
use LINE\Clients\MessagingApi\Model\RichMenuBulkUnlinkRequest;
$api->unlinkRichMenuIdFromUsers(new RichMenuBulkUnlinkRequest([
    'userIds' => [$userId1, $userId2],
]));
```

### 批次連結

```php
use LINE\Clients\MessagingApi\Model\RichMenuBulkLinkRequest;

$api->linkRichMenuIdToUsers(new RichMenuBulkLinkRequest([
    'richMenuId' => $richMenuId,
    'userIds'    => [$userId1, $userId2], // max 150,000
]));
```

## Rich Menu Alias（別名）

別名允許透過名稱而非 ID 切換 Rich Menu，適合搭配 `RichMenuSwitchAction`：

```php
use LINE\Clients\MessagingApi\Model\CreateRichMenuAliasRequest;
use LINE\Clients\MessagingApi\Model\UpdateRichMenuAliasRequest;

// 建立別名
$api->createRichMenuAlias(new CreateRichMenuAliasRequest([
    'richMenuAliasId' => 'richmenu-alias-main',  // 自訂 ID
    'richMenuId'      => $richMenuId,
]));

// 取得別名
$alias = $api->getRichMenuAlias($richMenuAliasId);

// 列出所有別名
$api->getRichMenuAliasList();

// 更新別名
$api->updateRichMenuAlias($richMenuAliasId, new UpdateRichMenuAliasRequest([
    'richMenuId' => $newRichMenuId,
]));

// 刪除別名
$api->deleteRichMenuAlias($richMenuAliasId);
```

### RichMenuSwitchAction（切換選單）

```php
use LINE\Clients\MessagingApi\Model\RichMenuSwitchAction;

$switchAction = new RichMenuSwitchAction([
    'type'            => 'richmenuswitch',
    'richMenuAliasId' => 'richmenu-alias-sub',
    'data'            => 'switched_to_sub',  // Postback data
]);
```

## 批次操作（Bulk）

```php
use LINE\Clients\MessagingApi\Model\RichMenuBatchRequest;
use LINE\Clients\MessagingApi\Model\RichMenuBatchLinkOperation;
use LINE\Clients\MessagingApi\Model\RichMenuBatchUnlinkOperation;

$batchRequest = new RichMenuBatchRequest([
    'operations' => [
        new RichMenuBatchLinkOperation([
            'type'       => 'link',
            'richMenuId' => $richMenuId,
            'userIds'    => [$userId1, $userId2],
        ]),
        new RichMenuBatchUnlinkOperation([
            'type'    => 'unlink',
            'userIds' => [$userId3],
        ]),
    ],
    'webhook' => $webhookUrl, // 選填，批次完成後通知
]);

$api->richMenuBatch($batchRequest);

// 查詢批次進度
$progress = $api->getRichMenuBatchProgress($requestId);
$progress->getPhase(); // waiting | processing | succeeded | failed
```

## 常見版型

### 3 格水平排版（全尺寸）

```
width=2500, height=1686
area1: x=0,    y=0, width=833, height=1686
area2: x=833,  y=0, width=834, height=1686
area3: x=1667, y=0, width=833, height=1686
```

### 2x3 格排版（全尺寸）

```
width=2500, height=1686
上排高: 843, 下排高: 843
左: 833, 中: 834, 右: 833
area1: x=0,    y=0,   width=833, height=843  (左上)
area2: x=833,  y=0,   width=834, height=843  (中上)
area3: x=1667, y=0,   width=833, height=843  (右上)
area4: x=0,    y=843, width=833, height=843  (左下)
area5: x=833,  y=843, width=834, height=843  (中下)
area6: x=1667, y=843, width=833, height=843  (右下)
```
