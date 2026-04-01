---
name: line-messaging-api
description: |
  LINE Messaging API + linecorp/line-bot-sdk PHP SDK (v12.x) 完整技術參考。
  涵蓋 MessagingApiApi 所有方法、訊息型別、Flex Message、Template Message、
  Rich Menu、Webhook 事件解析、簽章驗證，以及在此 Power Funnel WordPress 專案中的實際用法模式。
  當需要：發送 LINE 訊息（push/reply/multicast/broadcast/narrowcast）、處理 LINE Webhook 事件、
  建立 Flex Message 或 Template Message、操作 Rich Menu、取得用戶資料時，必須使用此 SKILL。
---

# LINE Messaging API — PHP SDK v12.x 技術參考

## SDK 版本與 Namespace

```
linecorp/line-bot-sdk: ^12.3 | PHP: 8.1+
```

| 用途 | Namespace |
|------|-----------|
| Messaging API 主類別 | `LINE\Clients\MessagingApi\Api\MessagingApiApi` |
| Messaging API 模型 | `LINE\Clients\MessagingApi\Model\*` |
| Messaging API 例外 | `LINE\Clients\MessagingApi\ApiException` |
| Messaging API 設定 | `LINE\Clients\MessagingApi\Configuration` |
| Webhook 事件 | `LINE\Webhook\Model\*` |
| Webhook 解析 | `LINE\Parser\EventRequestParser` |
| Webhook 驗證 | `LINE\Parser\SignatureValidator` |
| HTTP Header 常數 | `LINE\Constants\HTTPHeader` |

---

## 初始化

```php
use GuzzleHttp\Client;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;

$config = new Configuration();
$config->setAccessToken($channelAccessToken);
$api = new MessagingApiApi(client: new Client(), config: $config);
```

### Power Funnel 專案用法

```php
// Factory（singleton，使用 SettingDTO 設定）
$api = MessagingApiFactory::create();

// 高階封裝
$service = MessageService::instance();
$service->send_text_message($userId, '文字');
$service->send_template_message($userId, $templateMessage);
$service->reply($replyToken, $messages);       // array<Message>
$service->multicast($userIds, $messages);
$service->broadcast($messages);
$service->get_profile($userId);                // => UserProfileResponse
```

---

## 訊息發送 API

### pushMessage

```php
// 必填: to, messages (Message[], max 5)
// 選填: notificationDisabled, customAggregationUnits
$api->pushMessage(new PushMessageRequest([
    'to'       => $userId,
    'messages' => [$textMessage],
]), $retryKey); // $retryKey: UUID，冪等用，可 null
// => PushMessageResponse
```

### replyMessage

```php
// replyToken 有效期 30 秒，只能用一次
$api->replyMessage(new ReplyMessageRequest([
    'replyToken' => $event->getReplyToken(),
    'messages'   => [$message],
]));
// => ReplyMessageResponse
```

### multicast（max 500 人）

```php
$api->multicast(new MulticastRequest([
    'to'       => ['U111...', 'U222...'],
    'messages' => [$message],
]), $retryKey);
```

### broadcast

```php
$api->broadcast(new BroadcastRequest(['messages' => [$message]]), $retryKey);
```

### narrowcast

```php
$api->narrowcast(new NarrowcastRequest([
    'messages'  => [$message],
    'recipient' => $recipient, // 選填
    'filter'    => $filter,    // 選填
    'limit'     => $limit,     // 選填
]));
$api->getNarrowcastProgress($requestId); // 查進度
```

### 其他發送

```php
$api->markMessagesAsRead($request);
$api->markMessagesAsReadByToken($request);
$api->showLoadingAnimation($request);
```

---

## 訊息型別

所有訊息繼承 `LINE\Clients\MessagingApi\Model\Message`。

**Message 基礎屬性：** `type` (string), `quickReply` (QuickReply), `sender` (Sender)

### TextMessage

```php
new TextMessage(['type' => 'text', 'text' => '文字', // 必填 max 5000
    'emojis' => [$emoji], 'quoteToken' => $token]);
```

### ImageMessage

```php
new ImageMessage(['type' => 'image',
    'originalContentUrl' => 'https://...', // 必填 max 10MB JPEG/PNG
    'previewImageUrl'    => 'https://...', // 必填 max 1MB
]);
```

### VideoMessage

```php
new VideoMessage(['type' => 'video',
    'originalContentUrl' => 'https://...', // 必填 max 200MB MP4
    'previewImageUrl'    => 'https://...', // 必填
    'trackingId'         => 'id123',       // 選填
]);
```

### AudioMessage

```php
new AudioMessage(['type' => 'audio',
    'originalContentUrl' => 'https://...', // 必填 M4A
    'duration'           => 60000,         // 必填 毫秒
]);
```

### LocationMessage

```php
new LocationMessage(['type' => 'location',
    'title' => '名稱', 'address' => '地址',   // 均必填
    'latitude' => 25.0478, 'longitude' => 121.5319,
]);
```

### StickerMessage

```php
new StickerMessage(['type' => 'sticker',
    'packageId' => '1', 'stickerId' => '1',  // 均必填
]);
```

### TemplateMessage

```php
new TemplateMessage(['type' => 'template',
    'altText'  => '替代文字',  // 必填
    'template' => $template,  // 必填，見 Template 型別
]);
```

Template 型別（選填屬性見 `references/flex-message.md`）：

| class | type | 必填 | 最多 |
|---|---|---|---|
| `ButtonsTemplate` | `buttons` | text, actions | 4 actions |
| `ConfirmTemplate` | `confirm` | text, actions | 2 actions |
| `CarouselTemplate` | `carousel` | columns (CarouselColumn[]) | 10 cols, 3 actions/col |
| `ImageCarouselTemplate` | `image_carousel` | columns | 10 cols |

### FlexMessage

```php
new FlexMessage(['type' => 'flex',
    'altText'  => '替代文字',  // 必填
    'contents' => $container, // 必填，FlexBubble 或 FlexCarousel
]);
```

**詳細 Flex Message 元件：** `references/flex-message.md`

---

## Actions

| class | type | 關鍵屬性 |
|---|---|---|
| `URIAction` | `uri` | uri (必填), label, altUri |
| `PostbackAction` | `postback` | data (必填), label, displayText, inputOption, fillInText |
| `MessageAction` | `message` | text (必填, max 300), label |
| `DatetimePickerAction` | `datetimepicker` | data (必填), mode (必填: date/time/datetime), initial/max/min |
| `ClipboardAction` | `clipboard` | clipboardText (必填), label |
| `RichMenuSwitchAction` | `richmenuswitch` | richMenuAliasId (必填), data |
| `CameraAction` | `camera` | label |
| `CameraRollAction` | `cameraRoll` | label |
| `LocationAction` | `location` | label |

---

## Webhook 處理

### 簽章驗證與事件解析

```php
use LINE\Constants\HTTPHeader;
use LINE\Parser\EventRequestParser;

$signature = $request->get_header(HTTPHeader::LINE_SIGNATURE); // 'X-Line-Signature'
$body = $request->get_body();

$parsed = EventRequestParser::parseEventRequest($body, $channelSecret, $signature);
// 拋出 InvalidSignatureException | InvalidEventRequestException

foreach ($parsed->getEvents() as $event) {
    $type   = $event->getType();          // message|follow|unfollow|postback|...
    $source = $event->getSource();        // Source
    $userId = $source->getUserId();       // LINE UID
}
```

### Event 型別對照

| type | Class | 關鍵方法 |
|---|---|---|
| `message` | `MessageEvent` | `getReplyToken()`, `getMessage()` |
| `follow` | `FollowEvent` | `getReplyToken()`, `getFollow()` |
| `unfollow` | `UnfollowEvent` | (無 replyToken) |
| `postback` | `PostbackEvent` | `getReplyToken()`, `getPostback()` |
| `join` | `JoinEvent` | `getReplyToken()` |
| `leave` | `LeaveEvent` | (無 replyToken) |
| `memberJoined` | `MemberJoinedEvent` | `getJoined()` |
| `memberLeft` | `MemberLeftEvent` | `getLeft()` |
| `beacon` | `BeaconEvent` | `getBeacon()` |
| `accountLink` | `AccountLinkEvent` | `getLink()` |
| `videoPlayComplete` | `VideoPlayCompleteEvent` | `getVideoPlayComplete()` |

### Event 基礎屬性

```php
$event->getType()            // event type string
$event->getSource()          // Source: UserSource | GroupSource | RoomSource
$event->getTimestamp()       // int Unix ms
$event->getMode()            // active | standby
$event->getWebhookEventId()  // unique event ID
```

### Source 判斷

```php
$source->getType(); // user | group | room
if ($source instanceof \LINE\Webhook\Model\UserSource) {
    $userId = $source->getUserId();
}
if ($source instanceof \LINE\Webhook\Model\GroupSource) {
    $groupId = $source->getGroupId();
    $userId  = $source->getUserId();
}
```

### MessageEvent 訊息類型

```php
$message = $event->getMessage();
if ($message instanceof \LINE\Webhook\Model\TextMessageContent) {
    $text = $message->getText();
    $quoteToken = $message->getQuoteToken();
}
if ($message instanceof \LINE\Webhook\Model\ImageMessageContent) {
    $provider = $message->getContentProvider();
}
if ($message instanceof \LINE\Webhook\Model\LocationMessageContent) {
    $lat = $message->getLatitude(); $lng = $message->getLongitude();
}
if ($message instanceof \LINE\Webhook\Model\StickerMessageContent) {
    $packageId = $message->getPackageId(); $stickerId = $message->getStickerId();
}
```

### PostbackEvent

```php
$postback = $event->getPostback();
$data = $postback->getData(); // string: postback data (Power Funnel 用 JSON)
```

---

## 用戶資料 API

```php
$profile = $api->getProfile($userId);
$profile->getDisplayName();    // string
$profile->getUserId();         // string LINE UID
$profile->getPictureUrl();     // string|null
$profile->getStatusMessage();  // string|null

$botInfo = $api->getBotInfo();
$botInfo->getChatMode();       // chat | bot
$botInfo->getMarkAsReadMode(); // auto | manual

$api->getFollowers($start, $limit); // default limit=300
```

---

## Rich Menu API

**詳細操作：** `references/rich-menu.md`

```php
// 建立
$result = $api->createRichMenu(new RichMenuRequest([
    'size'        => new RichMenuSize(['width' => 2500, 'height' => 1686]),
    'selected'    => true,
    'name'        => '選單名稱',    // max 300 字
    'chatBarText' => '選單',        // max 14 字
    'areas'       => [
        new RichMenuArea([
            'bounds' => new RichMenuBounds(['x' => 0, 'y' => 0, 'width' => 2500, 'height' => 1686]),
            'action' => $action,
        ]),
    ],
]));
$richMenuId = $result->getRichMenuId();

// 圖片上傳（需 MessagingApiBlobApi）
$blobApi = new \LINE\Clients\MessagingApi\Api\MessagingApiBlobApi(client: $client, config: $config);
$blobApi->setRichMenuImage($richMenuId, $imageData);

// 管理
$api->setDefaultRichMenu($richMenuId);
$api->linkRichMenuIdToUser($userId, $richMenuId);
$api->unlinkRichMenuIdFromUser($userId);
$api->cancelDefaultRichMenu();
$api->deleteRichMenu($richMenuId);
$api->getRichMenuList();
$api->getRichMenuIdOfUser($userId);

// 別名（搭配 RichMenuSwitchAction）
$api->createRichMenuAlias($createRichMenuAliasRequest);
$api->getRichMenuAliasList();
$api->deleteRichMenuAlias($richMenuAliasId);

// Webhook 設定
$api->setWebhookEndpoint(new \LINE\Clients\MessagingApi\Model\SetWebhookEndpointRequest([
    'webhookEndpoint' => 'https://example.com/wp-json/power-funnel/v1/line-callback',
]));
$api->testWebhookEndpoint();
```

---

## 配額 API

```php
$quota = $api->getMessageQuota();
$quota->getType();   // limited | none
$quota->getValue();  // int 月配額上限

$consumption = $api->getMessageQuotaConsumption();
$consumption->getTotalUsage(); // int 本月已用

$api->getNumberOfSentPushMessages('20240101');    // YYYYMMDD
$api->getNumberOfSentReplyMessages('20240101');
$api->getNumberOfSentMulticastMessages('20240101');
$api->getNumberOfSentBroadcastMessages('20240101');
```

---

## QuickReply / Sender

```php
// QuickReply
$msg->setQuickReply(new QuickReply([
    'items' => [
        new QuickReplyItem([
            'type'   => 'action',
            'action' => new MessageAction(['type' => 'message', 'label' => '是', 'text' => 'yes']),
        ]),
    ],
]));

// Sender（自訂顯示）
$msg->setSender(new Sender(['name' => '自訂名稱', 'iconUrl' => 'https://...']));
```

---

## 例外處理

```php
use LINE\Clients\MessagingApi\ApiException;

try {
    $api->pushMessage($request);
} catch (ApiException $e) {
    $e->getCode();            // HTTP 狀態碼
    $e->getMessage();         // 錯誤訊息
    $e->getResponseBody();    // 原始回應 body
}
// 常見: 400 格式錯誤, 401 Token 無效, 403 無權限, 429 超速率限制
```

---

## 常數

```php
// LINE\Constants\HTTPHeader
HTTPHeader::LINE_SIGNATURE  // 'X-Line-Signature'
HTTPHeader::LINE_RETRY_KEY  // 'X-Line-Retry-Key'

// LINE\Constants\MessageType
MessageType::TEXT / TEMPLATE / IMAGEMAP / STICKER / LOCATION / IMAGE / AUDIO / VIDEO / FLEX

// LINE\Constants\TemplateType
TemplateType::CONFIRM / BUTTONS / CAROUSEL / IMAGE_CAROUSEL

// LINE\Constants\ActionType
ActionType::MESSAGE / POSTBACK / URI / DATETIME_PICKER / CAMERA / CAMERA_ROLL / LOCATION / RICH_MENU_SWITCH

// LINE\Constants\PostbackInputOption
PostbackInputOption::CLOSE_RICH_MENU / OPEN_RICH_MENU / OPEN_KEYBOARD / OPEN_VOICE
```

---

## Power Funnel 整合模式

### Carousel 報名訊息範例

```php
use LINE\Clients\MessagingApi\Model\{TemplateMessage, CarouselTemplate, CarouselColumn, PostbackAction};

$templateMsg = new TemplateMessage([
    'type'    => 'template',
    'altText' => '活動報名通知',
    'template'=> new CarouselTemplate([
        'columns' => [
            new CarouselColumn([
                'thumbnailImageUrl' => $imageUrl,
                'title'             => $activityTitle,     // max 40 字
                'text'              => $description,       // max 120 字
                'actions'           => [
                    new PostbackAction([
                        'type'        => 'postback',
                        'label'       => '立即報名',
                        'data'        => json_encode([
                            'action'        => 'register',
                            'activity_id'   => $activityId,
                            'promo_link_id' => $promoLinkId,
                        ]),
                        'displayText' => '我要報名！',
                    ]),
                ],
            ]),
        ],
    ]),
]);
MessageService::instance()->send_template_message($userId, $templateMsg);
```

### EventWebhookHelper 用法（Power Funnel 封裝）

```php
$helper  = new EventWebhookHelper($event);   // LINE\Webhook\Model\Event
$payload = $helper->get_payload();           // array: decoded JSON from postback.data
$action  = $helper->get_action();            // EAction|null
$userId  = $helper->get_identity_id();       // LINE UID
$actId   = $helper->get_activity_id();       // payload['activity_id']
$promoId = $helper->get_promo_link_id();     // payload['promo_link_id']
```

### Webhook 端點（Power Funnel）

```
POST /wp-json/power-funnel/v1/line-callback
觸發: power_funnel/line/webhook/{type}/{action}
      power_funnel/line/webhook/{type}
```

---

## 延伸參考

- `references/flex-message.md` — Flex Message 所有元件完整屬性（FlexBox, FlexText, FlexImage, FlexButton...）
- `references/rich-menu.md` — Rich Menu 批次操作、別名、常見版型
