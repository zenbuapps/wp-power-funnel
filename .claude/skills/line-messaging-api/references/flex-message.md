# Flex Message 完整元件參考

## 結構層次

```
FlexMessage
└── contents: FlexContainer
    ├── FlexBubble (type: "bubble")
    │   ├── header: FlexBox
    │   ├── hero: FlexComponent
    │   ├── body: FlexBox
    │   └── footer: FlexBox
    └── FlexCarousel (type: "carousel")
        └── contents: FlexBubble[]
```

## FlexContainer

抽象基礎類別，由 `FlexBubble` 和 `FlexCarousel` 實作。

```php
use LINE\Clients\MessagingApi\Model\FlexBubble;
use LINE\Clients\MessagingApi\Model\FlexCarousel;
```

## FlexBubble 屬性

| 屬性 | 型別 | 說明 |
|------|------|------|
| type | string | 固定: "bubble" |
| direction | string | `ltr` \| `rtl` (預設 ltr) |
| size | string | `nano`\|`micro`\|`kilo`\|`mega`\|`giga` |
| header | FlexBox | 頂部區塊，選填 |
| hero | FlexComponent | 主視覺區塊，選填 |
| body | FlexBox | 主內容區塊，選填 |
| footer | FlexBox | 底部區塊，選填 |
| styles | FlexBubbleStyles | 各區塊樣式，選填 |
| action | Action | 整個 Bubble 的點擊動作 |

## FlexCarousel 屬性

| 屬性 | 型別 | 說明 |
|------|------|------|
| type | string | 固定: "carousel" |
| contents | FlexBubble[] | Bubble 陣列，最多 12 個 |

## FlexComponent 型別

所有 Flex 元件的基礎類別。

| type 值 | PHP Class | 說明 |
|---------|-----------|------|
| `box` | `FlexBox` | 容器，可橫向/縱向排版 |
| `text` | `FlexText` | 文字 |
| `image` | `FlexImage` | 圖片 |
| `button` | `FlexButton` | 按鈕 |
| `icon` | `FlexIcon` | 圖示（inline 用） |
| `video` | `FlexVideo` | 影片 |
| `separator` | `FlexSeparator` | 分隔線 |
| `filler` | `FlexFiller` | 填充空間 |
| `span` | `FlexSpan` | 文字片段（用於 FlexText.contents） |

## FlexBox 完整屬性

```php
new FlexBox([
    'type'            => 'box',         // 必填
    'layout'          => 'vertical',    // 必填: horizontal | vertical | baseline
    'contents'        => [],            // FlexComponent[]，baseline 不支援 box/button/image/video
    'flex'            => 1,             // 同層佔比
    'spacing'         => 'md',          // none|xs|sm|md|lg|xl|xxl
    'margin'          => 'md',          // none|xs|sm|md|lg|xl|xxl|自訂px
    'position'        => 'relative',    // relative | absolute
    'offsetTop'       => '10px',        // position=absolute 時有效
    'offsetBottom'    => '10px',
    'offsetStart'     => '10px',
    'offsetEnd'       => '10px',
    'backgroundColor' => '#FFFFFF',
    'borderColor'     => '#000000',
    'borderWidth'     => '1px',         // 或: light|normal|medium|semi-bold|bold
    'cornerRadius'    => '8px',         // 或: xs|sm|md|lg|xl|xxl
    'width'           => '100px',
    'maxWidth'        => '200px',
    'height'          => '50px',
    'maxHeight'       => '100px',
    'paddingAll'      => '10px',
    'paddingTop'      => '5px',
    'paddingBottom'   => '5px',
    'paddingStart'    => '5px',
    'paddingEnd'      => '5px',
    'action'          => $action,
    'justifyContent'  => 'flex-start',  // flex-start|center|flex-end|space-between|space-around|space-evenly
    'alignItems'      => 'flex-start',  // flex-start | center | flex-end
    'background'      => $linearGradient, // FlexBoxBackground
]);
```

## FlexText 完整屬性

```php
new FlexText([
    'type'        => 'text',
    'text'        => '文字',     // 必填（除非有 contents）
    'contents'    => [$span],   // FlexSpan[]，與 text 擇一
    'size'        => 'md',      // xxs|xs|sm|md|lg|xl|xxl|3xl|4xl|5xl
    'color'       => '#333333',
    'weight'      => 'bold',    // regular | bold
    'align'       => 'start',   // start | center | end
    'gravity'     => 'top',     // top | center | bottom
    'wrap'        => true,
    'maxLines'    => 3,         // 最多顯示行數
    'lineSpacing' => '10px',
    'decoration'  => 'underline', // none | underline | line-through
    'style'       => 'italic',    // normal | italic
    'adjustMode'  => 'shrinkToFit',
    'scaling'     => false,
    'flex'        => 1,
    'margin'      => 'sm',
    'position'    => 'relative',
    'action'      => $action,
]);
```

## FlexImage 完整屬性

```php
new FlexImage([
    'type'            => 'image',
    'url'             => 'https://...',   // 必填，https only
    'flex'            => 1,
    'margin'          => 'md',
    'position'        => 'relative',
    'align'           => 'center',        // start | center | end
    'gravity'         => 'top',           // top | center | bottom
    'size'            => 'full',          // xxs|xs|sm|md|lg|xl|xxl|3xl|4xl|5xl|full
    'aspectRatio'     => '20:13',         // 1:1, 1.91:1, 4:3, etc.
    'aspectMode'      => 'cover',         // cover | fit
    'backgroundColor' => '#FFFFFF',
    'animated'        => false,
    'action'          => $action,
]);
```

## FlexButton 完整屬性

```php
new FlexButton([
    'type'       => 'button',
    'action'     => $action,    // 必填
    'flex'       => 1,
    'color'      => '#1DB446',  // 十六進位色碼
    'style'      => 'primary',  // link | primary | secondary
    'gravity'    => 'center',   // top | center | bottom
    'margin'     => 'md',
    'position'   => 'relative',
    'height'     => 'md',       // md | sm
    'adjustMode' => 'shrinkToFit',
    'scaling'    => false,
]);
```

## FlexIcon 屬性

只能用於 layout=baseline 的 FlexBox 中：

```php
new FlexIcon([
    'type'        => 'icon',
    'url'         => 'https://...', // 必填
    'size'        => 'md',
    'aspectRatio' => '1:1',
    'margin'      => 'sm',
    'position'    => 'relative',
    'scaling'     => false,
]);
```

## FlexSeparator 屬性

```php
new FlexSeparator([
    'type'   => 'separator',
    'margin' => 'md',
    'color'  => '#DDDDDD',
]);
```

## FlexFiller 屬性

```php
new FlexFiller([
    'type' => 'filler',
    'flex' => 1,
]);
```

## FlexSpan 屬性（用於 FlexText.contents）

```php
new FlexSpan([
    'type'       => 'span',
    'text'       => '文字片段',
    'size'       => 'md',
    'color'      => '#FF0000',
    'weight'     => 'bold',
    'style'      => 'italic',
    'decoration' => 'underline',
]);
```

## FlexVideo 屬性

```php
new FlexVideo([
    'type'        => 'video',
    'url'         => 'https://...', // 必填，mp4，https
    'previewUrl'  => 'https://...', // 必填，縮圖圖片
    'altContent'  => $image,        // 必填，FlexComponent（顯示於不支援的環境）
    'aspectRatio' => '20:13',
    'action'      => $action,
]);
```

## FlexBoxLinearGradient（漸層背景）

```php
new FlexBoxLinearGradient([
    'type'        => 'linearGradient',
    'angle'       => '90deg',
    'startColor'  => '#FF0000',
    'endColor'    => '#0000FF',
    'centerColor' => '#FFFFFF',  // 選填
    'centerPosition' => '50%',   // 選填
]);
```

## 完整範例：活動報名 Flex Bubble

```php
use LINE\Clients\MessagingApi\Model\{
    FlexMessage, FlexBubble, FlexBox, FlexText, FlexImage,
    FlexButton, FlexSeparator, FlexCarousel, URIAction, PostbackAction
};

$bubble = new FlexBubble([
    'type' => 'bubble',
    'hero' => new FlexImage([
        'type'       => 'image',
        'url'        => 'https://example.com/activity.jpg',
        'size'       => 'full',
        'aspectRatio'=> '20:13',
        'aspectMode' => 'cover',
    ]),
    'body' => new FlexBox([
        'type'     => 'box',
        'layout'   => 'vertical',
        'contents' => [
            new FlexText([
                'type'   => 'text',
                'text'   => '活動標題',
                'weight' => 'bold',
                'size'   => 'xl',
            ]),
            new FlexSeparator(['type' => 'separator', 'margin' => 'md']),
            new FlexText([
                'type'    => 'text',
                'text'    => '活動說明文字，可以很長',
                'size'    => 'sm',
                'color'   => '#666666',
                'wrap'    => true,
                'maxLines'=> 3,
            ]),
        ],
    ]),
    'footer' => new FlexBox([
        'type'     => 'box',
        'layout'   => 'vertical',
        'contents' => [
            new FlexButton([
                'type'   => 'button',
                'style'  => 'primary',
                'color'  => '#1DB446',
                'action' => new PostbackAction([
                    'type'        => 'postback',
                    'label'       => '立即報名',
                    'data'        => json_encode(['action' => 'register', 'activity_id' => 123]),
                    'displayText' => '我要報名',
                ]),
            ]),
        ],
    ]),
]);

$flexMsg = new FlexMessage([
    'type'     => 'flex',
    'altText'  => '活動報名通知',
    'contents' => $bubble,
]);
```

## FlexCarousel 範例

```php
$carousel = new FlexCarousel([
    'type'     => 'carousel',
    'contents' => [$bubble1, $bubble2, $bubble3], // 最多 12 個
]);

$flexMsg = new FlexMessage([
    'type'     => 'flex',
    'altText'  => '多項活動選擇',
    'contents' => $carousel,
]);
```

## 尺寸值參考

### 間距/邊距 (spacing/margin)
`none | xs | sm | md | lg | xl | xxl | 自訂px (如 "10px")`

### 文字尺寸 (size)
`xxs | xs | sm | md | lg | xl | xxl | 3xl | 4xl | 5xl`

### Bubble 尺寸
`nano | micro | kilo | mega | giga`

### 圖片尺寸 (image size)
`xxs | xs | sm | md | lg | xl | xxl | 3xl | 4xl | 5xl | full`
